@extends('layouts.app')

@section('title', 'Transaction Verification')

@push('styles')
    @vite('resources/css/lgu-transaction-verification.css')
@endpush

@section('content')
    <div class="verification-page">
        <div class="verification-heading">
            <div>
                <p class="verification-eyebrow">LGU TRANSACTION MANAGEMENT</p>
                <h1>Transaction Verification</h1>
                <p>Review livestock sales, validate buyer confirmation, and make verification decisions.</p>
            </div>
            <a href="/transactions/disputes" class="verification-link">Dispute Resolution →</a>
        </div>

        <div id="verification-alert" class="verification-alert" role="alert" hidden></div>

        <div class="verification-stats">
            <article class="verification-stat">
                <span>Pending Verification</span>
                <strong id="stat-pending">—</strong>
            </article>
            <article class="verification-stat">
                <span>Verified</span>
                <strong id="stat-verified">—</strong>
            </article>
            <article class="verification-stat">
                <span>Rejected</span>
                <strong id="stat-rejected">—</strong>
            </article>
            <article class="verification-stat">
                <span>Disputed</span>
                <strong id="stat-disputed">—</strong>
            </article>
        </div>

        <section class="verification-card">
            <div class="verification-toolbar">
                <div>
                    <h2>Transaction Review Queue</h2>
                    <p>Transactions awaiting LGU verification.</p>
                </div>
                <button type="button" id="verification-refresh" class="verification-btn verification-outline">
                    Refresh
                </button>
            </div>

            <div class="verification-filters">
                <input id="verification-search" type="search" placeholder="Search by transaction ID, farmer, or buyer…"
                    aria-label="Search transactions">
                <select id="verification-status" aria-label="Filter transaction status">
                    <option value="">All statuses</option>
                    <option value="pending_lgu_verification">Pending verification</option>
                    <option value="verified">Verified</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>

            <div class="verification-table-wrap">
                <table class="verification-table">
                    <thead>
                        <tr>
                            <th>Transaction</th>
                            <th>Farmer</th>
                            <th>Buyer</th>
                            <th>Source</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="verification-rows">
                        <tr>
                            <td colspan="7" class="verification-empty">Loading transactions…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <dialog id="verification-dialog" class="verification-dialog">
        <div class="verification-dialog-heading">
            <div>
                <p class="verification-eyebrow">TRANSACTION REVIEW</p>
                <h2 id="verification-dialog-title">Transaction Details</h2>
            </div>
            <button type="button" id="verification-close" class="verification-close" aria-label="Close">×</button>
        </div>

        <div id="verification-details" class="verification-details"></div>
        <div id="verification-dialog-alert" class="verification-alert" role="alert" hidden></div>

        <div id="verification-decision" class="verification-decision">
            <label for="verification-reason">Rejection reason</label>
            <textarea id="verification-reason" rows="3" minlength="5" maxlength="2000"
                placeholder="Required when rejecting a transaction"></textarea>

            <div class="verification-dialog-actions">
                <button type="button" id="verification-reject" class="verification-btn verification-danger">
                    Reject Transaction
                </button>
                <button type="button" id="verification-approve" class="verification-btn verification-primary">
                    Verify Transaction
                </button>
            </div>
        </div>
    </dialog>
@endsection

@push('script')
    @vite('resources/js/pages/lgu-transaction-verification.js')
@endpush