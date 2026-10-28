@extends('layouts.app')

@section('title', 'Livestock Prices')

@section('content')
    <div class="prices-page" id="price-references-page">

        <header class="prices-header">
            <div>
                <p class="prices-eyebrow">AGRITIMBANG / WEEKLY PRICES</p>

                <h1 id="prices-page-title">Livestock Prices</h1>

                <p id="prices-page-description">
                    Loading your price directory...
                </p>
            </div>



            <div class="prices-header-actions">
                <a href="{{ route('prices.submissions') }}" id="prices-history-button" class="prices-secondary" hidden>
                    Submission History
                </a>

                <a href="{{ route('prices.create') }}" id="prices-create-button" class="prices-primary" hidden>
                    + Create Price Reference
                </a>
                <a href="{{ route('prices.approvals') }}" id="prices-approval-button" class="prices-primary" hidden>
                    Review Pending Prices
                </a>
            </div>


        </header>



        <div id="prices-error" class="prices-alert prices-alert-error" role="alert" hidden></div>

        <div id="prices-success" class="prices-alert prices-alert-success" role="status" hidden></div>

        <section class="prices-summary">
            <article class="prices-stat">
                <span id="prices-total-label">Records on current page</span>
                <strong id="prices-total">—</strong>
            </article>

            <article class="prices-stat" id="prices-draft-card" hidden>
                <span>Draft</span>
                <strong id="prices-draft">—</strong>
            </article>

            <article class="prices-stat" id="prices-pending-card" hidden>
                <span>Pending review</span>
                <strong id="prices-pending">—</strong>
            </article>

            <article class="prices-stat" id="prices-official-card">
                <span>Official</span>
                <strong id="prices-official">—</strong>
            </article>
        </section>

        <section class="prices-card">
            <div class="prices-card-header">
                <div>
                    <h2 id="prices-directory-title">Official Price Directory</h2>
                    <p id="prices-directory-description">
                        MAO-approved livestock price references.
                    </p>
                </div>

                <button id="prices-refresh" type="button" class="prices-secondary">
                    Refresh
                </button>
            </div>

            <form id="prices-filters" class="prices-filters">

                <label id="prices-status-filter-wrap" hidden>
                    <span>Status</span>

                    <select id="prices-filter-status">
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="pending_review">Pending Review</option>
                        <option value="official">Official</option>
                        <option value="rejected">Rejected</option>
                        <option value="archived">Archived</option>
                    </select>
                </label>

                <label>
                    <span>Species</span>

                    <select id="prices-filter-species">
                        <option value="">All species</option>
                    </select>
                </label>

                <label>
                    <span>Sale purpose</span>

                    <select id="prices-filter-purpose">
                        <option value="">All purposes</option>
                        <option value="slaughter">Slaughter</option>
                        <option value="breeding">Breeding</option>
                        <option value="fattening">Fattening</option>
                        <option value="work">Work</option>
                    </select>
                </label>

                <button type="button" id="prices-reset" class="prices-secondary">
                    Reset
                </button>
            </form>

            <div class="prices-table-wrap">
                <table class="prices-table">
                    <thead>
                        <tr>
                            <th>Species / Breed</th>
                            <th>Locality</th>
                            <th>Purpose</th>
                            <th>Price / kg</th>
                            <th>Effective period</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody id="prices-table-body">
                        <tr>
                            <td colspan="7" class="prices-empty">
                                Loading price references...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <footer class="prices-pagination">
                <span id="prices-page-info">Page 1</span>

                <div>
                    <button id="prices-prev" type="button" disabled>
                        Previous
                    </button>

                    <button id="prices-next" type="button" disabled>
                        Next
                    </button>
                </div>
            </footer>
        </section>

        <section id="prices-detail" class="prices-card" hidden>
            <div class="prices-card-header">
                <div>
                    <h2>Price Reference Details</h2>
                    <p>Official price and reference information.</p>
                </div>

                <button id="prices-detail-close" type="button" class="prices-secondary">
                    Close
                </button>
            </div>

            <div id="prices-detail-content" class="prices-detail-grid"></div>
        </section>
    </div>
@endsection

@push('styles')
    @vite([
        'resources/css/prices.css',
        'resources/js/pages/prices.js'
    ])
@endpush