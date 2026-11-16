<?php

namespace App\Http\Controllers;

use App\Actions\CreateBuyerInterestAction;
use App\Actions\UpdateBuyerInterestAction;
use App\Http\Requests\LoginBuyerRequest;
use App\Http\Requests\StoreBuyerInterestRequest;
use App\Http\Requests\UpdateBuyerInterestRequest;
use App\Models\AuctionRegistration;
use App\Models\AuctionSchedule;
use App\Models\Buyer;
use App\Services\BuyerLoginService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BuyerInterestController extends Controller
{
    public function __construct(private readonly BuyerLoginService $buyerLoginService)
    {
    }

    public function publicIndex(): View
    {
        $today = now('Asia/Manila')->toDateString();
        $listedRegistration = fn ($query) => $query
            ->where('status', 'registered')
            ->whereNotNull('active_livestock_id')
            ->whereHas('livestock', fn ($livestock) => $livestock->where('status', 'available'));
        $livestockRelations = [
            'livestock' => fn ($query) => $query->with([
                'species',
                'breed',
                'municipality',
                'farmer.user',
                'latestWeightEstimation',
                'latestValuation',
            ]),
        ];

        $schedules = AuctionSchedule::query()
            ->where('status', 'published')
            ->whereDate('auction_date', '>=', $today)
            ->whereHas('registrations', $listedRegistration)
            ->with([
                'municipality',
                'registrations' => fn ($query) => $listedRegistration($query)->with($livestockRelations),
            ])
            ->orderBy('auction_date')
            ->orderBy('start_time')
            ->get();

        return view('browse.forsale-livestock', compact('schedules'));
    }

    public function showPage(AuctionSchedule $schedule): View
    {
        abort_unless(
            $schedule->status === 'published'
                && $schedule->auction_date?->greaterThanOrEqualTo(now('Asia/Manila')->toDateString()),
            404
        );

        return view('buyer-interest.index', ['scheduleId' => $schedule->id]);
    }

    public function listing(AuctionSchedule $schedule): JsonResponse
    {
        abort_unless(
            $schedule->status === 'published'
                && $schedule->auction_date?->greaterThanOrEqualTo(now('Asia/Manila')->toDateString()),
            404
        );

        $schedule->load('municipality');

        $items = AuctionRegistration::query()
            ->where('auction_schedule_id', $schedule->id)
            ->where('status', 'registered')
            ->whereNotNull('active_livestock_id')
            ->whereHas('livestock', fn ($query) => $query->where('status', 'available'))
            ->with([
                'livestock' => fn ($query) => $query->with([
                    'species',
                    'breed',
                    'municipality',
                    'farmer.user',
                    'latestWeightEstimation',
                    'latestValuation',
                ]),
            ])
            ->get();

        return response()->json([
            'data' => [
                'schedule' => [
                    'id' => $schedule->id,
                    'title' => $schedule->title,
                    'auction_date' => $schedule->auction_date?->format('Y-m-d'),
                    'municipality' => $schedule->municipality?->name,
                    'municipality_id' => $schedule->municipality_id,
                ],
                'livestock' => $items->map(function (AuctionRegistration $registration) use ($schedule) {
                    $livestock = $registration->livestock;

                    return [
                        'id' => $livestock?->id,
                        'name' => $livestock?->species?->name ?? 'Livestock',
                        'breed' => $livestock?->breed?->name ?? '—',
                        'age' => $livestock?->age_months ? $livestock->age_months . ' months' : '—',
                        'weight' => $livestock?->actual_weight_kg ? number_format((float) $livestock->actual_weight_kg, 2) . ' kg' : '—',
                        'starting_price' => $livestock?->latestValuation?->estimated_value,
                        'municipality' => $livestock?->municipality?->name ?? $schedule->municipality?->name,
                        'auction_date' => $schedule->auction_date?->format('Y-m-d'),
                        'seller_name' => $livestock?->farmer?->user ? trim($livestock->farmer->user->first_name . ' ' . $livestock->farmer->user->last_name) : 'Seller',
                        'photo' => $livestock?->photo_path ? asset('storage/' . $livestock->photo_path) : asset('assets/logo.png'),
                    ];
                })->values(),
            ],
        ]);
    }

    public function store(StoreBuyerInterestRequest $request, AuctionSchedule $schedule, CreateBuyerInterestAction $action): JsonResponse
    {
        $result = $action($schedule, $request->validated(), $request->input('livestock_ids', []));
        $buyer = $result['buyer'];

        return response()->json([
            'message' => 'Temporary account created successfully! Your code: ' . $buyer->account_code . '. Please save this code to update your interests later. It expires after ' . $schedule->auction_date->format('F d, Y') . '.',
            'data' => [
                'buyer' => [
                    'id' => $buyer->id,
                    'name' => $buyer->full_name,
                    'account_code' => $buyer->account_code,
                    'expires_at' => $buyer->expires_at?->format('Y-m-d H:i:s'),
                ],
                'interest_count' => $result['interest_count'],
                'pdf_url' => $result['pdf_url'],
            ],
        ], 201);
    }

    public function login(LoginBuyerRequest $request, AuctionSchedule $schedule): JsonResponse
    {
        $result = $this->buyerLoginService->resolve($schedule, $request->input('account_code'));
        $buyer = $result['buyer'];

        if (! $buyer) {
            $status = $result['status'] === 'wrong_event' ? 422 : 404;

            return response()->json([
                'message' => $result['message'],
                'errors' => ['account_code' => [$result['message']]],
            ], $status);
        }

        $interests = $buyer->interests()->with(['livestock.species', 'livestock.breed'])->get()->map(function ($interest) {
            $livestock = $interest->livestock;

            return [
                'id' => $interest->id,
                'livestock_id' => $livestock?->id,
                'name' => $livestock?->species?->name ?? 'Livestock',
                'breed' => $livestock?->breed?->name ?? '—',
                'status' => $interest->status,
            ];
        });

        return response()->json([
            'message' => $result['message'],
            'data' => [
                'buyer' => [
                    'id' => $buyer->id,
                    'name' => $buyer->full_name,
                    'account_code' => $buyer->account_code,
                    'mobile_number' => $buyer->mobile_number,
                    'expires_at' => $buyer->expires_at?->format('Y-m-d H:i:s'),
                    'is_expired' => $buyer->isExpired(),
                    'can_update' => ! $buyer->isExpired(),
                ],
                'interests' => $interests,
                'pdf_url' => route('buyer-interest.pdf', ['schedule' => $schedule->id, 'buyer' => $buyer->id]),
                'can_update' => ! $buyer->isExpired(),
            ],
        ]);
    }

    public function update(UpdateBuyerInterestRequest $request, AuctionSchedule $schedule): JsonResponse
    {
        $resolve = $this->buyerLoginService->resolveForUpdate($schedule, $request->input('account_code'));
        if ($resolve['status'] === 'not_found_or_expired') {
            return response()->json([
                'message' => $resolve['message'],
                'errors' => ['account_code' => [$resolve['message']]],
            ], 422);
        }

        $buyer = $resolve['buyer'];
        $result = (new UpdateBuyerInterestAction)($buyer, $schedule, $request->input('livestock_ids', []));

        $message = $result['added_count'] > 0
            ? 'Your interests have been updated. Added ' . $result['added_count'] . ' new livestock to your list.'
            : 'Your interests have been updated. Added 0 new livestock to your list.';

        return response()->json([
            'message' => $message,
            'data' => [
                'buyer' => [
                    'id' => $buyer->id,
                    'account_code' => $buyer->account_code,
                ],
                'added_count' => $result['added_count'],
                'pdf_url' => $result['pdf_url'],
            ],
        ]);
    }

    public function pdf(AuctionSchedule $schedule, Buyer $buyer)
    {
        abort_unless((string) $buyer->auction_id === (string) $schedule->id, 404);

        $buyer->load(['interests.livestock.species', 'interests.livestock.breed']);
        $schedule->load('municipality');

        $pdf = Pdf::loadView('buyer-interest.pdf', [
            'buyer' => $buyer,
            'schedule' => $schedule,
            'interests' => $buyer->interests,
        ]);

        return $pdf->download('buyer-interest-' . $buyer->account_code . '.pdf');
    }
}
