@extends('layouts.app')

@section('title', 'Price Submission History')

@section('content')
    <div class="price-history-page" id="price-history-page">

        <header class="price-history-header">
            <div>
                <p class="price-history-eyebrow">
                    AGRITIMBANG / WEEKLY PRICES
                </p>

                <h1>Price Submission History</h1>

                <p>
                    Monitor livestock price references submitted
                    within your assigned municipality.
                </p>
            </div>

            <a href="{{ route('prices.index') }}" class="price-history-secondary">
                Back to Prices
            </a>
        </header>

        <div id="history-error" class="price-history-alert price-history-alert-error" role="alert" hidden></div>

        <div id="history-success" class="price-history-alert price-history-alert-success" role="status" hidden></div>

        <section class="price-history-summary">
            <article class="price-history-stat">
                <span>Total Records</span>
                <strong id="history-total">—</strong>
            </article>

            <article class="price-history-stat">
                <span>Drafts on Page</span>
                <strong id="history-drafts">—</strong>
            </article>

            <article class="price-history-stat">
                <span>Pending on Page</span>
                <strong id="history-pending">—</strong>
            </article>

            <article class="price-history-stat">
                <span>Official on Page</span>
                <strong id="history-official">—</strong>
            </article>
        </section>

        <section class="price-history-card">

            <div class="price-history-card-header">
                <div>
                    <h2>Submission Records</h2>
                    <p>
                        Review price references and their
                        current validation status.
                    </p>
                </div>

                <div class="price-history-header-actions">
                    <button id="history-refresh" type="button" class="price-history-secondary">
                        Refresh
                    </button>

                    <a href="{{ route('prices.create') }}" class="price-history-primary">
                        + New Price
                    </a>
                </div>
            </div>

            <form id="history-filters" class="price-history-filters">

                <label>
                    <span>Status</span>

                    <select id="history-filter-status">
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="pending_review">
                            Pending Review
                        </option>
                        <option value="official">Official</option>
                        <option value="rejected">Rejected</option>
                        <option value="archived">Archived</option>
                    </select>
                </label>

                <label>
                    <span>Species</span>

                    <select id="history-filter-species">
                        <option value="">All species</option>
                    </select>
                </label>

                <label>
                    <span>Sale Purpose</span>

                    <select id="history-filter-purpose">
                        <option value="">All purposes</option>
                        <option value="slaughter">Slaughter</option>
                        <option value="breeding">Breeding</option>
                        <option value="fattening">Fattening</option>
                        <option value="work">Work</option>
                    </select>
                </label>

                <button id="history-reset" type="button" class="price-history-secondary">
                    Reset
                </button>
            </form>

            <div class="price-history-table-wrap">
                <table class="price-history-table">
                    <thead>
                        <tr>
                            <th>Livestock</th>
                            <th>Locality</th>
                            <th>Purpose</th>
                            <th>Price / kg</th>
                            <th>Submitted By</th>
                            <th>Submitted At</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody id="history-table-body">
                        <tr>
                            <td colspan="8" class="price-history-empty">
                                Loading submission history...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <footer class="price-history-pagination">
                <span id="history-page-info">Page 1</span>

                <div>
                    <button id="history-prev" type="button" disabled>
                        Previous
                    </button>

                    <button id="history-next" type="button" disabled>
                        Next
                    </button>
                </div>
            </footer>
        </section>

        <section id="history-detail" class="price-history-card price-history-detail" hidden>

            <div class="price-history-card-header">
                <div>
                    <h2>Submission Details</h2>
                    <p>
                        Reference information and validation history.
                    </p>
                </div>

                <button id="history-detail-close" type="button" class="price-history-secondary">
                    Close
                </button>
            </div>

            <div id="history-detail-content" class="price-history-detail-grid"></div>

            <div id="history-validation-section" class="price-history-validation" hidden>

                <h3>Validation History</h3>

                <div id="history-validation-list"></div>
            </div>
        </section>
    </div>
@endsection

@push('styles')
    @vite([
        'resources/css/price-history.css',
        'resources/js/pages/price-history.js'
    ])
@endpush