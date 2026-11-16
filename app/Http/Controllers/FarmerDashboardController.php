<?php

namespace App\Http\Controllers;

use App\Models\FarmerProfile;
use App\Models\LivestockRecord;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FarmerDashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $farmer = FarmerProfile::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $requestedMonth = $request->query('month');
        try {
            $month = is_string($requestedMonth) && preg_match('/^\d{4}-\d{2}$/', $requestedMonth)
                ? Carbon::createFromFormat('!Y-m', $requestedMonth)
                : now()->startOfMonth();
            if (!$month || ($requestedMonth && $month->format('Y-m') !== $requestedMonth)) {
                $month = now()->startOfMonth();
            }
        } catch (\Throwable $exception) {
            $month = now()->startOfMonth();
        }

        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $livestockCount = LivestockRecord::query()
            ->where('farmer_id', $farmer->id)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $livestockStatusCounts = LivestockRecord::query()
            ->where('farmer_id', $farmer->id)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $monthlyTransactions = Transaction::query()
            ->where('farmer_id', $farmer->id)
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('transaction_date', [$start, $end])
                    ->orWhere(function ($fallback) use ($start, $end) {
                        $fallback->whereNull('transaction_date')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            });

        $transactionCount = (clone $monthlyTransactions)->count();
        $salesTotal = (clone $monthlyTransactions)->sum('total_selling_price');
        $statusCounts = (clone $monthlyTransactions)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return response()->json([
            'success' => true,
            'data' => [
                'livestock_this_month' => $livestockCount,
                'livestock_status_counts' => $livestockStatusCounts,
                'transactions_this_month' => $transactionCount,
                'selling_value_this_month' => (float) $salesTotal,
                'transaction_status_counts' => $statusCounts,
                'month' => $start->format('Y-m'),
                'month_label' => $start->translatedFormat('F Y'),
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
            ],
        ]);
    }
}
