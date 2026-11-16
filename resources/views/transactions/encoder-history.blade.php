@extends('layouts.app')

@section('title', 'Transaction History')

@push('styles')
    @vite(['resources/css/transactions-history.css'])
@endpush

@section('content')
    <main
        class="lgu-history-page transaction-page"
        id="lgu-history-page"
        data-transaction-history-endpoint="{{ request()->is('admin/transactions/history') ? '/admin/transactions/history' : (request()->is('lgu/transactions/history') ? '/lgu/transactions/history' : '/lgu/encoder/transactions/history') }}"
    >
        <header class="lgu-history-header transaction-header">
            <div>
                <span class="lgu-history-eyebrow eyebrow">
                    AGRITIMBANG / TRANSACTIONS
                </span>

                <h1>Transaction History</h1>

                <p>
                    Search, filter, and view transaction validation history.
                    Encoder accounts are limited to their assigned municipality.
                </p>
            </div>

            <a class="tx-btn tx-btn-primary" href="{{ route('transactions.assist') }}">
                + Assist Transaction
            </a>
        </header>

        <section class="lgu-history-panel transaction-card">
            <form id="lgu-history-filters" class="lgu-history-filters">
                <label class="lgu-history-search">
                    <span>Search</span>
                    <input
                        id="history-search"
                        type="search"
                        placeholder="Farmer, phone, or transaction ID"
                    >
                </label>

                <label>
                    <span>Status</span>

                    <select id="history-status">
                        <option value="">All statuses</option>
                        <option value="pending">Awaiting buyer confirmation</option>
                        <option value="pending_lgu_verification">For LGU verification</option>
                        <option value="verified">Verified</option>
                        <option value="rejected">Rejected</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </label>

                <label>
                    <span>Species</span>
                    <select id="history-species">
                        <option value="">All species</option>
                    </select>
                </label>

                <label>
                    <span>Sale purpose</span>

                    <select id="history-purpose">
                        <option value="">All purposes</option>
                        <option value="Slaughter">Slaughter</option>
                        <option value="Breeding">Breeding</option>
                        <option value="Fattening">Fattening</option>
                        <option value="Work">Work</option>
                    </select>
                </label>

                <label>
                    <span>From</span>
                    <input id="history-date-from" type="date">
                </label>

                <label>
                    <span>To</span>
                    <input id="history-date-to" type="date">
                </label>

                <div class="lgu-history-filter-actions">
                    <button class="tx-btn tx-btn-primary" type="submit">
                        Apply filters
                    </button>

                    <button
                        class="tx-btn tx-btn-outline"
                        id="history-reset"
                        type="button"
                    >
                        Reset
                    </button>
                </div>
            </form>

            <div class="lgu-history-meta">
                <strong id="history-count">0 transactions</strong>
                <span id="history-page-info">Page 1</span>
            </div>

            <div
                id="history-message"
                class="lgu-history-message"
                role="status"
                hidden
            ></div>

            <div class="lgu-history-table-wrap table-responsive">
                <table class="lgu-history-table" id="tx-history-table">
                    <thead>
                        <tr>
                            <th>Transaction</th>
                            <th>Date</th>
                            <th>Farmer</th>
                            <th>Items</th>
                            <th>Total selling price</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody id="history-table-body">
                        <tr>
                            <td colspan="7">
                                Loading transaction history…
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <footer class="lgu-history-pagination" id="tx-history-pagination">
                <button id="history-prev" type="button" disabled>
                    Previous
                </button>

                <span id="history-page-label">Page 1</span>

                <button id="history-next" type="button" disabled>
                    Next
                </button>
            </footer>
        </section>

        <dialog id="history-detail-dialog" class="lgu-history-dialog">
            <div class="lgu-history-dialog-head">
                <div>
                    <span>TRANSACTION RECORD</span>
                    <h2 id="detail-title">Transaction details</h2>
                    <p id="detail-subtitle"></p>
                </div>

                <button
                    type="button"
                    id="detail-close"
                    aria-label="Close"
                >
                    ×
                </button>
            </div>

            <div id="detail-content" class="lgu-history-detail-content">
                Loading details…
            </div>
        </dialog>
    </main>
@endsection

@vite(['resources/js/pages/transactions-history.js'])