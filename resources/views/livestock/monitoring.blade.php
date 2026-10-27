@extends('layouts.app')

@section('title', 'Livestock Monitoring')

@push('styles')
    @vite([
        'resources/css/livestock-monitoring.css',
        'resources/js/pages/livestock-monitoring.js'
    ])
@endpush

@section('content')
    <section class="welcome">
        <div>
            <span class="eyebrow">LIVESTOCK MANAGEMENT</span>
            <h1>
                Livestock <span class="welcome-dot">Monitoring.</span>
            </h1>
            <p id="livestock-welcome">
                Monitor livestock inventory and distribution.
            </p>
        </div>
        <span class="welcome-ornament" aria-hidden="true">✳</span>
    </section>

    <div class="overview-heading">
        <div>
            <h2>Livestock Monitoring</h2>
            <p id="livestock-scope">Loading authorized records...</p>
        </div>

        <button type="button" id="livestock-refresh" class="lm-button">
            Refresh data
        </button>
    </div>

    <div id="livestock-loading" class="lm-notice" role="status" hidden>
        Loading livestock monitoring data...
    </div>

    <div id="livestock-error" class="lm-notice lm-error" role="alert" hidden>
        <span id="livestock-error-message"></span>

        <button type="button" id="livestock-retry">
            Try again
        </button>
    </div>

    <div id="livestock-content" hidden>

        {{-- LIVESTOCK STATISTICS --}}
        <section class="lm-stats" aria-label="Livestock statistics">
            <article class="lm-stat">
                <span>Total livestock</span>
                <strong id="lm-total">—</strong>
                <small>Registered livestock records</small>
            </article>

            <article class="lm-stat">
                <span>Available</span>
                <strong id="lm-available">—</strong>
                <small>Currently available</small>
            </article>

            <article class="lm-stat">
                <span>In transaction</span>
                <strong id="lm-in-transaction">—</strong>
                <small>Ongoing transactions</small>
            </article>

            <article class="lm-stat">
                <span>Sold</span>
                <strong id="lm-sold">—</strong>
                <small>Completed livestock sales</small>
            </article>
        </section>

        {{-- ANALYTICS --}}
        <section class="lm-panels">
            <article class="lm-panel">
                <div class="lm-panel-heading">
                    <h3>Livestock by species</h3>
                    <p>Distribution of recorded livestock</p>
                </div>

                <div id="lm-species-chart" class="lm-chart"></div>
            </article>

            <article class="lm-panel">
                <div class="lm-panel-heading">
                    <h3>Livestock status</h3>
                    <p>Current inventory breakdown</p>
                </div>

                <div id="lm-status-chart" class="lm-chart"></div>
            </article>
        </section>

        {{-- BARANGAY DISTRIBUTION --}}
        <section id="lm-barangay-panel" class="lm-panel" hidden>
            <div class="lm-panel-heading">
                <h3>Barangay distribution</h3>
                <p>Livestock records grouped by barangay</p>
            </div>

            <div id="lm-barangay-chart" class="lm-chart"></div>
        </section>

        {{-- LIVESTOCK RECORDS --}}
        <section id="lm-records-panel" class="lm-panel" hidden>
            <div class="lm-records-header">
                <div class="lm-panel-heading">
                    <h3>Livestock records</h3>

                    <p id="lm-records-description">
                        Browse registered livestock
                    </p>
                </div>
            </div>

            <div class="lm-filters">
                <select id="lm-filter-status" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    <option value="available">Available</option>
                    <option value="in_transaction">In transaction</option>
                    <option value="sold">Sold</option>
                    <option value="archived">Archived</option>
                </select>

                <select id="lm-filter-species" aria-label="Filter by species">
                    <option value="">All species</option>
                </select>

                <select id="lm-filter-breed" aria-label="Filter by breed" disabled>
                    <option value="">All breeds</option>
                </select>

                <button type="button" id="lm-clear-filters" class="lm-button-secondary">
                    Clear filters
                </button>
            </div>

            <div class="lm-table-scroll">
                <table class="lm-table">
                    <thead>
                        <tr>
                            <th>Livestock</th>
                            <th>Breed</th>
                            <th>Farmer</th>
                            <th>Location</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody id="lm-records-body">
                        <tr>
                            <td colspan="5">Loading records...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="lm-pagination">
                <span id="lm-page-info">—</span>

                <div>
                    <button type="button" id="lm-prev-page" disabled>
                        Previous
                    </button>

                    <button type="button" id="lm-next-page" disabled>
                        Next
                    </button>
                </div>
            </div>
        </section>

        {{-- GEOGRAPHIC LIVESTOCK MONITORING — LEAFLET 2D ONLY --}}
        <section id="lm-map-panel" class="lm-panel" hidden>
            <div class="lm-map-header">
                <div class="lm-panel-heading">
                    <h3>Geographic livestock monitoring</h3>

                    <p>
                        Explore livestock distribution across
                        authorized municipalities and barangays.
                    </p>
                </div>

                <div class="lm-map-actions">
                    <select id="lm-map-municipality" aria-label="Filter map by municipality" hidden>
                        <option value="">All municipalities</option>
                    </select>

                    <select id="lm-map-species" aria-label="Filter map by species">
                        <option value="">All species</option>
                    </select>

                    <button type="button" id="lm-map-reset" class="lm-button-secondary">
                        Reset view
                    </button>
                </div>
            </div>

            <div class="lm-map-container">
                <div id="lm-map"></div>
            </div>

            <div class="lm-map-footer">
                <div class="lm-map-legend">
                    <span>Livestock population</span>

                    <span class="lm-legend-item">
                        <i class="lm-legend-dot lm-density-low"></i>
                        Low
                    </span>

                    <span class="lm-legend-item">
                        <i class="lm-legend-dot lm-density-medium"></i>
                        Medium
                    </span>

                    <span class="lm-legend-item">
                        <i class="lm-legend-dot lm-density-high"></i>
                        High
                    </span>
                </div>

                <p id="lm-map-summary" aria-live="polite">
                    Loading geographic monitoring data...
                </p>
            </div>
        </section>

    </div>
@endsection