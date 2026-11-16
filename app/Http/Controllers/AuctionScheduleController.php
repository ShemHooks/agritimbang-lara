<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterAuctionLivestockRequest;
use App\Http\Requests\StoreAuctionScheduleRequest;
use App\Http\Requests\UpdateAuctionScheduleRequest;
use App\Models\AuctionRegistration;
use App\Models\AuctionSchedule;
use App\Services\AuctionScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuctionScheduleController extends Controller
{
    public function __construct(private readonly AuctionScheduleService $service) {}

    public function venues(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->encoderVenues($request->user())]);
    }

    public function encoderIndex(Request $request): JsonResponse
    {
        return response()->json($this->service->encoderIndex($request->user()));
    }

    public function store(StoreAuctionScheduleRequest $request): JsonResponse
    {
        $schedule = $this->service->create($request->user(), $request->validated());
        return response()->json(['message' => 'Auction schedule created successfully.', 'data' => $schedule], 201);
    }

    public function encoderShow(Request $request, AuctionSchedule $schedule): JsonResponse
    {
        return response()->json(['data' => $this->service->encoderShow($request->user(), $schedule)]);
    }

    public function encoderRegistrations(Request $request, AuctionSchedule $schedule): JsonResponse
    {
        return response()->json(['data' => $this->service->encoderRegistrations($request->user(), $schedule)]);
    }

    public function update(UpdateAuctionScheduleRequest $request, AuctionSchedule $schedule): JsonResponse
    {
        $schedule = $this->service->updateSchedule($request->user(), $schedule, $request->validated());
        return response()->json(['message' => 'Auction schedule updated successfully.', 'data' => $schedule]);
    }

    public function changeStatus(Request $request, AuctionSchedule $schedule): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:published,cancelled']]);
        $schedule = $this->service->changeStatus($request->user(), $schedule, $data['status']);
        return response()->json(['message' => 'Auction schedule status updated.', 'data' => $schedule]);
    }

    public function farmerIndex(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->farmerIndex($request->user())]);
    }

    public function farmerShow(Request $request, AuctionSchedule $schedule): JsonResponse
    {
        return response()->json(['data' => $this->service->farmerShow($request->user(), $schedule)]);
    }

    public function farmerEligibleLivestock(Request $request, AuctionSchedule $schedule): JsonResponse
    {
        return response()->json(['data' => $this->service->farmerEligibleLivestock($request->user(), $schedule)]);
    }

    public function register(RegisterAuctionLivestockRequest $request, AuctionSchedule $schedule): JsonResponse
    {
        $registrations = $this->service->registerLivestock($request->user(), $schedule, $request->validated('livestock_ids'));
        return response()->json([
            'message' => $registrations->count().' livestock record(s) registered for auction successfully.',
            'data' => $registrations,
        ], 201);
    }

    public function farmerRegistrations(Request $request): JsonResponse
    {
        return response()->json($this->service->farmerRegistrations($request->user()));
    }

    public function withdraw(Request $request, AuctionRegistration $registration): JsonResponse
    {
        $registration = $this->service->withdraw($request->user(), $registration);
        return response()->json(['message' => 'Auction registration withdrawn.', 'data' => $registration]);
    }
}
