<?php

namespace App\Http\Controllers;

use App\Models\Municipality;
use App\Services\DashboardService;
use App\Services\GisAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard, private readonly GisAccessService $access) {}

    private function municipality(Request $request): ?string
    {
        $data = $request->validate(['municipality_id' => ['nullable', 'uuid', Rule::exists(Municipality::class, 'id')]]);
        return $this->access->resolveMunicipality($request->user(), $data['municipality_id'] ?? null);
    }

    public function overview(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->dashboard->overview($this->municipality($request))]);
    }

    public function livestock(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->dashboard->livestock($this->municipality($request))]);
    }

    public function transactions(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->dashboard->transactions($this->municipality($request))]);
    }

    public function valuations(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->dashboard->valuations($this->municipality($request))]);
    }
}
