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

        <section class="price-approvals-card">

            <div class="price-approvals-card-header">
                <div>
                    <h2>Pending Price References</h2>
                    <p>
                        Select a submitted reference to review
                        its details and make a decision.
                    </p>
                </div>

                <button id="approvals-refresh" type="button" class="price-approvals-secondary">
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

                <button id="approvals-reset" type="button" class="price-approvals-secondary">
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