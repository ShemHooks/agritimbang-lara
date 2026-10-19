<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignAuctionHouseEncoderRequest;
use App\Http\Requests\StoreAuctionHouseRequest;
use App\Http\Requests\UpdateAuctionHouseRequest;
use App\Http\Requests\UpdateAuctionHouseStatusRequest;
use App\Services\LguAuctionHouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LguAuctionHouseController extends Controller
{
    public function __construct(
        private LguAuctionHouseService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->index(
                $request->user()
            ),
        ]);
    }

    public function store(
        StoreAuctionHouseRequest $request
    ): JsonResponse {
        $house = $this->service->create(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Auction house registered successfully.',
            'data' => $house,
        ], 201);
    }

    public function update(
        UpdateAuctionHouseRequest $request,
        string $auctionHouse
    ): JsonResponse {
        $house = $this->service->update(
            $request->user(),
            $auctionHouse,
            $request->validated()
        );

        return response()->json([
            'message' => 'Auction house updated successfully.',
            'data' => $house,
        ]);
    }

    public function status(
        UpdateAuctionHouseStatusRequest $request,
        string $auctionHouse
    ): JsonResponse {
        $data = $request->validated();

        $house = $this->service->setStatus(
            $request->user(),
            $auctionHouse,
            $data['is_active']
        );

        return response()->json([
            'message' => 'Auction house status updated.',
            'data' => $house,
        ]);
    }

    public function assignEncoder(
        AssignAuctionHouseEncoderRequest $request,
        string $auctionHouse
    ): JsonResponse {
        $data = $request->validated();

        $this->service->assignEncoder(
            $request->user(),
            $auctionHouse,
            $data['user_id']
        );

        return response()->json([
            'message' => 'LGU encoder assigned successfully.',
        ]);
    }

    public function removeEncoder(
        Request $request,
        string $auctionHouse,
        string $user
    ): JsonResponse {
        $this->service->removeEncoder(
            $request->user(),
            $auctionHouse,
            $user
        );

        return response()->json([
            'message' => 'LGU encoder assignment deactivated.',
        ]);
    }
}
