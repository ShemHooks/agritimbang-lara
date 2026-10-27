@extends('layouts.app')

@section('title', 'Price Analytics')

@push('styles')
    @vite([
        'resources/css/price-analytics.css',
        'resources/js/pages/price-analytics.js'
    ])
@endpush

@section('content')
    {{-- PAGE INTRODUCTION --}}
    <section class="welcome">
        <div>
            <span class="eyebrow">LIVESTOCK MANAGEMENT</span>

            <h1>
                Price <span class="welcome-dot">Analytics.</span>
            </h1>

            <p>
                Monitor official livestock prices, compare price
                references, and analyze historical price trends.
            </p>
        </div>

        <span class="welcome-ornament" aria-hidden="true">✳</span>
    </section>

    {{-- PAGE HEADING --}}
    <div class="pa-overview-heading">
        <div>
            <h2>Price Analytics</h2>

            <p id="pa-scope">
                Loading authorized price records...
            </p>
        </div>

        <button type="button" id="pa-refresh" class="pa-button">
            Refresh data
        </button>
    </div>

    {{-- LOADING STATE --}}
    <div id="pa-loading" class="pa-notice" role="status" hidden>
        Loading official livestock price analytics...
    </div>

    {{-- ERROR STATE --}}
    <div id="pa-error" class="pa-notice pa-error" role="alert" hidden>
        <span id="pa-error-message"></span>

        <button type="button" id="pa-retry">
            Try again
        </button>
    </div>

    {{-- MAIN CONTENT --}}
    <div id="pa-content" hidden>

        {{-- FILTERS --}}
        <section class="pa-panel pa-filter-panel">
            <div class="pa-panel-heading">
                <div>
                    <h3>Price filters</h3>

                    <p>
                        Narrow the analysis by municipality,
                        species, breed, and effective date.
                    </p>
                </div>
            </div>

            <div class="pa-filters">
                <div class="pa-filter-field" id="pa-municipality-field">
                    <label for="pa-municipality">
                        Municipality
                    </label>

                    <select id="pa-municipality">
                        <option value="">All authorized municipalities</option>
                    </select>
                </div>

                <div class="pa-filter-field">
                    <label for="pa-species">
                        Species
                    </label>

                    <select id="pa-species">
                        <option value="">All species</option>
                    </select>
                </div>

                <div class="pa-filter-field">
                    <label for="pa-breed">
                        Breed
                    </label>

                    <select id="pa-breed" disabled>
                        <option value="">All breeds</option>
                    </select>
                </div>

                <div class="pa-filter-field">
                    <label for="pa-date-from">
                        From date
                    </label>

                    <input type="date" id="pa-date-from">
                </div>

                <div class="pa-filter-field">
                    <label for="pa-date-to">
                        To date
                    </label>

                    <input type="date" id="pa-date-to">
                </div>

                <div class="pa-filter-actions">
                    <button type="button" id="pa-clear-filters" class="pa-button-secondary">
                        Clear filters
                    </button>
                </div>
            </div>
        </section>

        {{-- SUMMARY STATISTICS --}}
        <section class="pa-stats" aria-label="Price statistics">

            <article class="pa-stat">
                <span>Average price / kg</span>

                <strong id="pa-average-price">
                    —
                </strong>

                <small>
                    Average across matching official references
                </small>
            </article>

            <article class="pa-stat">
                <span>Latest price / kg</span>

                <strong id="pa-latest-price">
                    —
                </strong>

                <small id="pa-latest-description">
                    Most recent official reference
                </small>
            </article>

            <article class="pa-stat">
                <span>Official references</span>

                <strong id="pa-reference-count">
                    —
                </strong>

                <small>
                    Published price records in the selected scope
                </small>
            </article>

            <article class="pa-stat">
                <span>Price change</span>

                <strong id="pa-price-change">
                    —
                </strong>

                <small id="pa-change-description">
                    Change between comparable references
                </small>
            </article>
        </section>

        {{-- PRICE TREND CHART --}}
        <section class="pa-panel">
            <div class="pa-panel-heading">
                <div>
                    <h3>Historical price trends</h3>

                    <p>
                        Track changes in official livestock prices
                        over their effective dates.
                    </p>
                </div>

                <div class="pa-chart-controls">
                    <label for="pa-chart-group">
                        Group by
                    </label>

                    <select id="pa-chart-group">
                        <option value="species">Species</option>
                        <option value="breed">Breed</option>
                    </select>
                </div>
            </div>

            <div class="pa-chart-wrapper">
                <div id="pa-price-chart" class="pa-chart" role="img" aria-label="Historical official livestock price chart">
                </div>
            </div>

            <div id="pa-chart-legend" class="pa-chart-legend"></div>

            <p class="pa-chart-note">
                Prices are shown in Philippine pesos per kilogram.
                Only official references are included.
            </p>
        </section>

        {{-- PRICE COMPARISON --}}
        <section class="pa-panel">
            <div class="pa-panel-heading">
                <div>
                    <h3>Price comparison</h3>

                    <p>
                        Compare average official reference prices
                        across livestock species.
                    </p>
                </div>
            </div>

            <div id="pa-comparison-chart" class="pa-comparison-chart"></div>
        </section>

        {{-- OFFICIAL PRICE HISTORY --}}
        <section class="pa-panel">
            <div class="pa-panel-heading">
                <div>
                    <h3>Official price history</h3>

                    <p>
                        Browse published livestock price references.
                    </p>
                </div>

                <div class="pa-table-tools">
                    <input type="search" id="pa-search" placeholder="Search species or breed..."
                        aria-label="Search price references">
                </div>
            </div>

            <div class="pa-table-scroll">
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th>Species</th>
                            <th>Breed</th>
                            <th>Sale purpose</th>
                            <th>Price / kg</th>
                            <th>Effective from</th>
                            <th>Effective to</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody id="pa-history-body">
                        <tr>
                            <td colspan="7">
                                Loading official price references...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- PAGINATION --}}
            <div class="pa-pagination">
                <span id="pa-page-info">
                    —
                </span>

                <div class="pa-pagination-actions">
                    <button type="button" id="pa-prev-page" disabled>
                        Previous
                    </button>

                    <button type="button" id="pa-next-page" disabled>
                        Next
                    </button>
                </div>
            </div>
        </section>

    </div>
@endsection