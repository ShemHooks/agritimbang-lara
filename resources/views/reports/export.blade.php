<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>{{ $report['title'] }}</title>
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
h1 { font-size: 19px; margin-bottom: 8px; }
.meta { margin-bottom: 12px; color: #555; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
th, td { border: 1px solid #ccc; padding: 5px; overflow-wrap: anywhere; }
th { background: #eee; text-align: left; }
tr { page-break-inside: avoid; }
.note { margin-top: 10px; font-style: italic; }
</style></head><body>
<h1>{{ $report['title'] }}</h1>
<div class="meta">Municipality ID: {{ $report['municipality_id'] }} | Generated: {{ $report['generated_at'] }} | Records: {{ $report['count'] }}</div>
<table><thead><tr>@foreach ($report['columns'] as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
<tbody>@forelse ($report['rows'] as $row)<tr>@foreach ($report['columns'] as $key => $heading)<td>{{ $row[$key] ?? '' }}</td>@endforeach</tr>
@empty<tr><td colspan="{{ count($report['columns']) }}">No records found for the selected filters.</td></tr>@endforelse</tbody></table>
@if ($report['note'])<p class="note">{{ $report['note'] }}</p>@endif
</body></html>
