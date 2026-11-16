@extends('layouts.public')

@section('title', 'Livestock For Sale')

@section('content')
    <div class="public-container">
        <p class="public-eyebrow">Upcoming public auctions</p>
        <h1 class="public-title">Livestock For Sale</h1>
        <p class="public-muted">Browse livestock registered for upcoming auctions by municipality. Select an auction to express interest in its listings.</p>

        @if ($schedules->isEmpty())
            <div class="alert alert-warning" role="status">
                There are no livestock listings for upcoming auctions right now. Please check back soon.
            </div>
        @else
            <label class="public-filter" for="municipality-filter">
                <span>Filter by municipality</span>
                <select id="municipality-filter">
                    <option value="">All municipalities</option>
                    @foreach ($schedules->pluck('municipality')->filter()->unique('id')->sortBy('name') as $municipality)
                        <option value="{{ $municipality->id }}">{{ $municipality->name }}</option>
                    @endforeach
                </select>
            </label>

            <div id="auction-listings">
                @foreach ($schedules->groupBy('municipality_id') as $municipalitySchedules)
                    @php($municipality = $municipalitySchedules->first()->municipality)
                    <section class="schedule-section municipality-listings" data-municipality="{{ $municipality?->id }}">
                        <h2 class="public-title" style="font-size: 1.5rem">{{ $municipality?->name ?? 'Municipality not specified' }}</h2>
                        @foreach ($municipalitySchedules as $schedule)
                            <article class="schedule-section">
                                <div class="schedule-heading">
                                    <div>
                                        <h2>{{ $schedule->title }}</h2>
                                        <div class="schedule-meta">
                                            {{ $schedule->auction_date->format('F j, Y') }}
                                            @if ($schedule->start_time)
                                                · {{ \Illuminate\Support\Carbon::parse($schedule->start_time)->format('g:i A') }}
                                            @endif
                                            @if ($schedule->venue)
                                                · {{ $schedule->venue }}
                                            @endif
                                            · {{ $schedule->registrations->count() }} livestock
                                        </div>
                                    </div>
                                    <a class="btn btn-primary" href="{{ route('buyer-interest.index', ['schedule' => $schedule->id]) }}">
                                        View listing &amp; express interest
                                    </a>
                                </div>

                                <div class="livestock-grid">
                                    @foreach ($schedule->registrations as $registration)
                                        @php($livestock = $registration->livestock)
                                        @if ($livestock)
                                            <article class="livestock-card">
                                                <img class="livestock-photo" src="{{ $livestock->photo_path ? asset('storage/' . $livestock->photo_path) : asset('assets/logo.png') }}" alt="{{ $livestock->species?->name ?? 'Livestock' }}">
                                                <div class="livestock-card-body">
                                                    <h3>{{ $livestock->species?->name ?? 'Livestock' }}@if ($livestock->breed) · {{ $livestock->breed->name }}@endif</h3>
                                                    <p>
                                                        Age: {{ $livestock->age_months ? $livestock->age_months . ' months' : '—' }}<br>
                                                        Weight: {{ $livestock->actual_weight_kg ? number_format((float) $livestock->actual_weight_kg, 2) . ' kg' : '—' }}<br>
                                                        Starting price: {{ $livestock->latestValuation?->estimated_value !== null ? '₱' . number_format((float) $livestock->latestValuation->estimated_value, 2) : 'Not set' }}<br>
                                                        Seller: {{ $livestock->farmer?->user ? trim($livestock->farmer->user->first_name . ' ' . $livestock->farmer->user->last_name) : 'Seller' }}
                                                    </p>
                                                </div>
                                            </article>
                                        @endif
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    </section>
                @endforeach
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            document.getElementById('municipality-filter')?.addEventListener('change', (event) => {
                const municipalityId = event.target.value;
                document.querySelectorAll('.municipality-listings').forEach((section) => {
                    section.hidden = municipalityId !== '' && section.dataset.municipality !== municipalityId;
                });
            });
        </script>
    @endpush
@endsection
