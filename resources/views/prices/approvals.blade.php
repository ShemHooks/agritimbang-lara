@extends('layouts.app')

@section('title', 'Price Approval Queue')

@section('content')
    <div class="price-approvals-page" id="price-approvals-page">
        <header class="price-approvals-header">
            <div>
                <p class="price-approvals-eyebrow">
                    AGRITIMBANG / PRICE VALIDATION
                </p>

                <h1>Price Approval Queue</h1>

                <p>
                    Review livestock price references submitted
                    by LGU Encoders in your assigned municipality.
                </p>
            </div>

            <a href="{{ route('prices.index') }}" class="price-approvals-secondary">
                Back to Price Management
            </a>
        </header>

        <div id="approvals-error" class="price-approvals-alert" role="alert" hidden></div>

        <section class="price-approvals-summary">
            <article class="price-approvals-stat">
                <span>Total Matching References</span>
                <strong id="approvals-total">—</strong>
            </article>

            <article class="price-approvals-stat">
                <span>Pending on Current Page</span>
                <strong id="approvals-pending">—</strong>
            </article>
        </section>

        <section class="price-approvals-card weekly-engine-card">
            <div class="price-approvals-card-header">
                <div>
                    <h2>Weekly Price Recommendation Engine</h2>
                    <p>
                        Generate municipality-wide recommendations from verified transaction
                        observations. Each species and sale purpose is calculated separately
                        using IQR outlier screening.
                    </p>
                </div>
            </div>

            <div class="weekly-engine-controls">
                <label>
                    <span>Choose a date within the completed sales week</span>
                    <input id="weekly-engine-date" type="date" required>
                </label>

                <button
                    id="weekly-engine-generate"
                    type="button"
                    class="price-approvals-review"
                >
                    Generate weekly recommendations
                </button>

                <button
                    id="weekly-engine-refresh"
                    type="button"
                    class="price-approvals-secondary"
                >
                    Refresh
                </button>
            </div>

            <div
                id="weekly-engine-message"
                class="weekly-engine-message"
                role="status"
                hidden
            ></div>

            <div class="weekly-species-slider">
                <div
                    class="weekly-species-tabs"
                    id="weekly-species-tabs"
                    role="tablist"
                    aria-label="Livestock species"
                >
                    <button
                        type="button"
                        data-species="carabao"
                        class="is-active"
                        role="tab"
                        aria-selected="true"
                    >
                        Carabao
                    </button>

                    <button
                        type="button"
                        data-species="cattle"
                        role="tab"
                        aria-selected="false"
                    >
                        Cattle
                    </button>

                    <button
                        type="button"
                        data-species="goat"
                        role="tab"
                        aria-selected="false"
                    >
                        Goat
                    </button>

                    <button
                        type="button"
                        data-species="hog"
                        role="tab"
                        aria-selected="false"
                    >
                        Hog
                    </button>
                </div>

                <div class="weekly-species-slide" id="weekly-species-slide">
                    <div class="weekly-species-table-wrap">
                        <table class="price-approvals-table weekly-species-table">
                            <thead>
                                <tr>
                                    <th>Sale purpose</th>
                                    <th>Week</th>
                                    <th>Observations</th>
                                    <th>Included / excluded</th>
                                    <th>Recommended price (₱/kg)</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody id="weekly-species-body">
                                <tr>
                                    <td colspan="7" class="price-approvals-empty">
                                        Loading recommendations…
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <div class="weekly-calculation-modal" id="weekly-calculation-modal" hidden>
            <div class="weekly-modal-backdrop" data-weekly-modal-close></div>

            <section
                class="weekly-modal-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="weekly-modal-title"
            >
                <header class="weekly-modal-header">
                    <div>
                        <span class="weekly-modal-eyebrow">
                            CALCULATION TRANSPARENCY
                        </span>

                        <h2 id="weekly-modal-title">Recommendation calculation</h2>

                        <p id="weekly-modal-summary"></p>
                    </div>

                    <button
                        id="weekly-modal-close"
                        type="button"
                        aria-label="Close calculation"
                    >
                        ×
                    </button>
                </header>

                <div id="weekly-modal-metrics" class="weekly-detail-metrics"></div>

                <div class="weekly-calculation-grid">
                    <div class="weekly-formula-panel">
                        <p><strong>IQR = Q3 − Q1.</strong></p>
                        <p>Lower bound = Q1 − 1.5 × IQR.</p>
                        <p>Upper bound = Q3 + 1.5 × IQR.</p>
                        <p>
                            Recommended price = sum of included observed prices per kg
                            ÷ included observations.
                        </p>
                        <p>Minimum required: 4 included observations.</p>
                    </div>

                    <div class="weekly-result-panel">
                        <span>Current result</span>
                        <strong id="weekly-modal-result">—</strong>
                    </div>
                </div>

                <div class="weekly-modal-table-heading">
                    <h3>Transaction observations</h3>
                    <span>Verified livestock items used in the calculation</span>
                </div>

                <div class="weekly-modal-table-wrap">
                    <table class="price-approvals-table weekly-observation-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Farmer</th>
                                <th>Livestock (species)</th>
                                <th>Weight</th>
                                <th>Observed price</th>
                                <th>Price / kg</th>
                                <th>Decision</th>
                                <th>Reason</th>
                            </tr>
                        </thead>

                        <tbody id="weekly-modal-observations"></tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="price-approvals-card">
            <div class="price-approvals-card-header">
                <div>
                    <h2>Pending Price References</h2>
                    <p>
                        Select a submitted reference to review
                        its details and make a decision.
                    </p>
                </div>

                <button
                    id="approvals-refresh"
                    type="button"
                    class="price-approvals-secondary"
                >
                    Refresh
                </button>
            </div>

            <form id="approvals-filters" class="price-approvals-filters">
                <label>
                    <span>Species</span>

                    <select id="approvals-species">
                        <option value="">All species</option>
                    </select>
                </label>

                <label>
                    <span>Sale Purpose</span>

                    <select id="approvals-purpose">
                        <option value="">All purposes</option>
                        <option value="slaughter">Slaughter</option>
                        <option value="breeding">Breeding</option>
                        <option value="fattening">Fattening</option>
                        <option value="work">Work</option>
                    </select>
                </label>

                <button
                    id="approvals-reset"
                    type="button"
                    class="price-approvals-secondary"
                >
                    Reset Filters
                </button>
            </form>

            <div class="price-approvals-table-wrap">
                <table class="price-approvals-table">
                    <thead>
                        <tr>
                            <th>Livestock</th>
                            <th>Locality</th>
                            <th>Sale Purpose</th>
                            <th>Price / kg</th>
                            <th>Submitted By</th>
                            <th>Submitted At</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody id="approvals-table-body">
                        <tr>
                            <td colspan="8" class="price-approvals-empty">
                                Loading pending references...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <footer class="price-approvals-pagination">
                <span id="approvals-page-info">
                    Page 1
                </span>

                <div>
                    <button id="approvals-prev" type="button" disabled>
                        Previous
                    </button>

                    <button id="approvals-next" type="button" disabled>
                        Next
                    </button>
                </div>
            </footer>
        </section>
    </div>
@endsection

@push('styles')
    @vite([
        'resources/css/price-approvals.css',
        'resources/js/pages/price-approvals.js'
    ])
@endpush