@extends('layouts.app')

@section('title', 'My Transactions')

@push('styles')
    @vite(['resources/css/transactions-history.css'])
@endpush

@section('content')
    <main class="transaction-page" data-transaction-history-endpoint="/farmer/transactions">
        <header class="transaction-header">
            <div>
                <p class="eyebrow">AGRITIMBANG / FARMER WORKSPACE</p>

                <h1>Mga Transaksyon</h1>

                <p>
                    Review your recorded livestock sales and transaction status.
                </p>
            </div>

            <a class="tx-btn tx-btn-primary" href="{{ route('transactions.create') }}">
                + Record Transaction
            </a>
        </header>

        <section class="transaction-card">
            <div class="transaction-card-heading">
                <div>
                    <h2>Transaction History</h2>
                    <p>Your saved drafts and submitted transactions.</p>
                </div>

                <button type="button" id="tx-history-refresh">
                    Refresh
                </button>
            </div>

            <div id="tx-history-feedback" role="status">
                Loading transactions...
            </div>

            <div class="table-responsive">
                <table class="table" id="tx-history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Buyer</th>
                            <th>Items</th>
                            <th>Total Selling Price</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody id="tx-history-body"></tbody>
                </table>
            </div>

            <div
                id="tx-history-detail"
                class="tx-detail-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="tx-detail-title"
                hidden
            >
                <div class="tx-detail-backdrop" data-tx-detail-close></div>

                <section class="tx-detail-dialog" tabindex="-1">
                    <header class="tx-detail-header">
                        <div>
                            <p class="eyebrow">
                                AGRITIMBANG / TRANSACTION DETAILS
                            </p>

                            <h2 id="tx-detail-title">
                                Detalye sa Transaksyon
                            </h2>
                        </div>

                        <button
                            type="button"
                            class="tx-icon-close"
                            aria-label="Close details"
                            data-tx-detail-close
                        >
                            &times;
                        </button>
                    </header>

                    <div id="tx-detail-summary" class="tx-detail-summary"></div>

                    <div class="tx-detail-items-wrap">
                        <h3>Mga Hayop nga Apil sa Transaksyon</h3>

                        <table class="tx-detail-items">
                            <thead>
                                <tr>
                                    <th>Hayop</th>
                                    <th>Breed</th>
                                    <th>Weight</th>
                                    <th>Reference Value</th>
                                    <th>Price / kg</th>
                                    <th>Selling Price</th>
                                </tr>
                            </thead>

                            <tbody id="tx-detail-items"></tbody>
                        </table>
                    </div>

                    <footer class="tx-detail-footer">
                        <strong>Total Selling Price</strong>
                        <strong id="tx-detail-total">₱0.00</strong>

                        <button
                            type="button"
                            class="tx-btn tx-btn-primary"
                            data-tx-detail-close
                        >
                            Close
                        </button>
                    </footer>
                </section>
            </div>

            <div id="tx-history-pagination">
                <button type="button" id="tx-history-prev" disabled>
                    Previous
                </button>

                <span id="tx-history-page-label">Page 1</span>

                <button type="button" id="tx-history-next" disabled>
                    Next
                </button>
            </div>
        </section>
    </main>
@endsection

@vite(['resources/js/pages/transactions-history.js'])