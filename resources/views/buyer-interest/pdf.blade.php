<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buyer Interest Receipt</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; }
        h1 { font-size: 28px; margin-bottom: 12px; }
        .meta { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>Buyer Interest Receipt</h1>
    <div class="meta">
        <p><strong>Buyer:</strong> {{ e($buyer->full_name) }}</p>
        <p><strong>Account Code:</strong> {{ e($buyer->account_code) }}</p>
        <p><strong>Auction Date:</strong> {{ e($schedule->auction_date?->format('F d, Y')) }}</p>
        <p><strong>Municipality:</strong> {{ e($schedule->municipality?->name ?? '—') }}</p>
        <p><strong>Submitted:</strong> {{ e(now('Asia/Manila')->format('F d, Y h:i A')) }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Livestock</th>
                <th>Breed</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($interests as $interest)
                <tr>
                    <td>{{ e($interest->livestock?->species?->name ?? 'Livestock') }}</td>
                    <td>{{ e($interest->livestock?->breed?->name ?? '—') }}</td>
                    <td>{{ e($interest->status) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No livestock selected.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
