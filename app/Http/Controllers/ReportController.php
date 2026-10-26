<?php

namespace App\Http\Controllers;

use App\Services\ReportExportService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly ReportExportService $exports,
    ) {}

    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'type' => ['required', Rule::in(ReportService::TYPES)],
            'format' => ['required', Rule::in(['pdf', 'xlsx'])],
            'municipality_id' => ['nullable', 'uuid', 'exists:municipalities,id'],
            'species_id' => ['nullable', 'uuid', 'exists:species,id'],
            'sale_purpose' => ['nullable', 'string', 'max:60'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'weeks' => ['nullable', 'integer', 'between:1,12'],
        ]);
        if ($filters['type'] === 'forecast' && empty($filters['species_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['species_id' => 'Species is required for forecast reports.']);
        }
        $report = $this->reports->build($request->user(), $filters);
        return $this->exports->download($report, $filters['format']);
    }
}
