@extends('layouts.app')

@section('title', 'Valuation Analytics')

@push('styles')
    @vite([
        'resources/css/valuation-analytics.css',
        'resources/js/pages/valuation-analytics.js'
    ])
@endpush

@section('content')

    <section class="welcome">
        <div>
            <span class="eyebrow">LIVESTOCK MANAGEMENT</span>

            <h1>
                Valuation <span class="welcome-dot">Analytics.</span>
            </h1>

            <p>
                Monitor fair price calculations, estimated livestock
                values, and valuation methodologies.
            </p>
        </div>

        <span class="welcome-ornament" aria-hidden="true">✳</span>
    </section>

    <div class="va-overview-heading">
        <div>
            <h2>Valuation Overview</h2>
            <p id="va-scope">
                Loading authorized valuation records...
            </p>
        </div>

        <button type="button" id="va-refresh" class="va-button">
            Refresh Data
        </button>
    </div>

    <div id="va-loading" class="va-notice" role="status" hidden>
        Loading valuation analytics...
    </div>

    <div id="va-error" class="va-notice va-error" role="alert" hidden>
        <span id="va-error-message"></span>
        <button type="button" id="va-retry">Try Again</button>
    </div>

    <div id="va-content" hidden>

        {{-- FILTERS --}}
        <section class="va-panel">
            <div class="va-panel-heading">
                <div>
                    <h3>Analytics Filters</h3>
                    <p>Explore valuation activity by municipality and year.</p>
                </div>
            </div>

            <div class="va-filters">
                <div class="va-filter-field">
                    <label for="va-municipality">Municipality</label>
                    <select id="va-municipality">
                        <option value="">All authorized municipalities</option>
                    </select>
                </div>

                <div class="va-filter-field">
                    <label for="va-year">Reporting Year</label>
                    <select id="va-year">
                        <option value="">All Years</option>
                    </select>
                </div>

                <div class="va-filter-actions">
                    <button type="button" id="va-clear-filters" class="va-button-secondary">
                        Clear Filters
                    </button>
                </div>
            </div>
        </section>

        {{-- KPI CARDS --}}
        <section class="va-stats">

            <article class="va-stat">
                <span>Total FPC Calculations</span>
                <strong id="va-total-calculations">—</strong>
                <small>Historical valuation calculations</small>
            </article>

            <article class="va-stat">
                <span>Average Estimated Value</span>
                <strong id="va-average-value">—</strong>
                <small>Average value per historical calculation</small>
            </article>

            <article class="va-stat">
                <span>Latest Inventory Value</span>
                <strong id="va-inventory-value">—</strong>
                <small>Latest valuations of eligible livestock</small>
            </article>

            <article class="va-stat">
                <span>Actual Weight Usage</span>
                <strong id="va-actual-rate">—</strong>
                <small>Percentage of calculations using actual weight</small>
            </article>

        </section>

        {{-- ANALYTICS GRID --}}
        <div class="va-analytics-grid">

            {{-- WEIGHT METHODS --}}
            <section class="va-panel">
                <div class="va-panel-heading">
                    <div>
                        <h3>Weight Method Distribution</h3>
                        <p>Actual versus estimated weight calculations.</p>
                    </div>
                </div>

                <div id="va-weight-chart" class="va-weight-chart"></div>
                <div id="va-weight-legend" class="va-weight-legend"></div>
            </section>

            {{-- ADJUSTMENT FACTORS --}}
            <section class="va-panel">
                <div class="va-panel-heading">
                    <div>
                        <h3>Average FPC Adjustment Factors</h3>
                        <p>Historical averages of applied pricing factors.</p>
                    </div>
                </div>

                <div id="va-adjustments" class="va-adjustments"></div>

                <p class="va-panel-note">
                    Factor values represent historical calculations,
                    not current pricing configuration.
                </p>
            </section>

        </div>

        {{-- MONTHLY CALCULATION TREND --}}
        <section class="va-panel">
            <div class="va-panel-heading">
                <div>
                    <h3>Monthly Valuation Activity</h3>
                    <p>Historical calculation volume and average estimated value.</p>
                </div>

                <div class="va-chart-controls">
                    <label for="va-chart-metric">Display</label>

                    <select id="va-chart-metric">
                        <option value="count">Calculation Count</option>
                        <option value="average">Average Estimated Value</option>
                    </select>
                </div>
            </div>

            <div class="va-chart-wrapper">
                <div id="va-monthly-chart" class="va-chart" role="img" aria-label="Monthly valuation activity chart">
                </div>
            </div>

            <p class="va-chart-note">
                Reporting year affects monthly activity only.
                Summary cards and species breakdown remain all-time.
            </p>
        </section>

        {{-- SPECIES BREAKDOWN --}}
        <section class="va-panel">
            <div class="va-panel-heading">
                <div>
                    <h3>Valuations by Species</h3>
                    <p>Historical calculation volume and average fair value.</p>
                </div>
            </div>

            <div id="va-species-chart" class="va-species-chart"></div>
        </section>

        {{-- MONTHLY HISTORY TABLE --}}
        <section class="va-panel">
            <div class="va-panel-heading">
                <div>
                    <h3>Monthly Valuation History</h3>
                    <p>Calculation totals and average estimated values by month.</p>
                </div>
            </div>

            <div class="va-table-scroll">
                <table class="va-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Calculations</th>
                            <th>Average Estimated Value</th>
                        </tr>
                    </thead>

                    <tbody id="va-monthly-body">
                        <tr>
                            <td colspan="3">
                                Loading valuation history...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="va-pagination">
                <span id="va-page-info">—</span>

                <div class="va-pagination-actions">
                    <button type="button" id="va-prev-page" disabled>
                        Previous
                    </button>

                    <button type="button" id="va-next-page" disabled>
                        Next
                    </button>
                </div>
            </div>
        </section>

    </div>

@endsection