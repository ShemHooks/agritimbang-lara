@extends('layouts.app')

@section('title', 'Dispute Resolution')

@push('styles')
    @vite('resources/css/dispute-resolution.css')
@endpush

@section('content')
    <div class="dispute-page">

        {{-- Page Header --}}
        <header class="dispute-heading">
            <div>
                <p class="dispute-eyebrow">LGU TRANSACTION MANAGEMENT</p>
                <h1>Dispute Resolution</h1>
                <p>
                    Review disputed livestock transactions,
                    investigate reported issues, and document final decisions.
                </p>
            </div>

            <a href="/transactions/review" class="dispute-back">
                ← Transaction Verification
            </a>
        </header>

        {{-- Global Alert --}}
        <div id="dispute-alert" class="dispute-alert" role="alert" hidden></div>

        {{-- Summary Cards --}}
        <div class="dispute-stats">

            <article class="dispute-stat">
                <span>Open Disputes</span>
                <strong id="dispute-open">—</strong>
            </article>

            <article class="dispute-stat">
                <span>Under Review</span>
                <strong id="dispute-reviewing">—</strong>
            </article>

            <article class="dispute-stat">
                <span>Resolved (Upheld)</span>
                <strong id="dispute-resolved">—</strong>
            </article>

            <article class="dispute-stat">
                <span>Dismissed</span>
                <strong id="dispute-dismissed">—</strong>
            </article>

        </div>

        {{-- Dispute Listing --}}
        <section class="dispute-card">

            <div class="dispute-toolbar">
                <div>
                    <h2>Dispute Cases</h2>
                    <p>
                        Manage transaction disputes within your assigned municipality.
                    </p>
                </div>

                <button type="button" id="dispute-refresh" class="dispute-btn dispute-outline">
                    Refresh
                </button>
            </div>

            {{-- Filters --}}
            <div class="dispute-filters">

                <input type="search" id="dispute-search" placeholder="Search dispute or transaction ID…"
                    aria-label="Search disputes" autocomplete="off">

                <select id="dispute-status" aria-label="Filter dispute status">
                    <option value="">All statuses</option>
                    <option value="open">Open</option>
                    <option value="under_review">Under Review</option>
                    <option value="resolved">Resolved (Upheld)</option>
                    <option value="dismissed">Dismissed</option>
                </select>

            </div>

            {{-- Dispute Table --}}
            <div class="dispute-table-wrap">

                <table class="dispute-table">
                    <thead>
                        <tr>
                            <th>Dispute ID</th>
                            <th>Transaction</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody id="dispute-rows">
                        <tr>
                            <td colspan="6" class="dispute-empty">
                                Loading disputes…
                            </td>
                        </tr>
                    </tbody>
                </table>

            </div>

            {{-- Pagination --}}
            <div class="dispute-pagination">

                <button type="button" id="dispute-previous" class="dispute-btn dispute-outline" hidden>
                    Previous
                </button>

                <span id="dispute-page-label"></span>

                <button type="button" id="dispute-next" class="dispute-btn dispute-outline" hidden>
                    Next
                </button>

            </div>

        </section>
    </div>

    {{-- Dispute Review Dialog --}}
    <dialog id="dispute-dialog" class="dispute-dialog" aria-labelledby="dispute-dialog-title">

        <div class="dispute-dialog-heading">
            <div>
                <p class="dispute-eyebrow">DISPUTE CASE REVIEW</p>
                <h2 id="dispute-dialog-title">
                    Dispute Details
                </h2>
            </div>

            <button type="button" id="dispute-close" class="dispute-close" aria-label="Close dispute details">
                ×
            </button>
        </div>

        {{-- Case Information --}}
        <div id="dispute-details" class="dispute-details"></div>

        {{-- Dialog Error Messages --}}
        <div id="dispute-dialog-alert" class="dispute-alert" role="alert" hidden></div>

        {{-- Start Review Action --}}
        <div class="dispute-dialog-actions">

            <button type="button" id="dispute-start-review" class="dispute-btn dispute-outline" hidden>
                Start Review
            </button>

        </div>

        {{-- Resolution Form --}}
        <form id="dispute-resolution-form" hidden>

            <h3>Resolve Dispute</h3>

            <p class="dispute-form-description">
                Review the case details carefully before submitting
                your final resolution.
            </p>

            {{-- Resolution Decision --}}
            <label for="dispute-resolution">
                Resolution Decision
            </label>

            <select id="dispute-resolution" name="resolution" required>
                <option value="">
                    Select resolution
                </option>

                <option value="upheld">
                    Uphold Dispute
                </option>

                <option value="dismissed">
                    Dismiss Dispute
                </option>
            </select>

            {{-- Dynamic Resolution Warning --}}
            <div id="dispute-resolution-warning" class="dispute-resolution-warning" role="status" aria-live="polite" hidden>
            </div>

            {{-- Resolution Notes --}}
            <label for="dispute-resolution-notes">
                Resolution Notes
            </label>

            <textarea id="dispute-resolution-notes" name="resolution_notes" required rows="5" minlength="10"
                maxlength="2000"
                placeholder="Explain the investigation findings, supporting evidence, and reason for your decision..."></textarea>

            <p class="dispute-field-hint">
                Minimum 10 characters, maximum 2,000 characters.
            </p>

            {{-- Submit --}}
            <button type="submit" id="dispute-resolve" class="dispute-btn dispute-primary">
                Submit Resolution
            </button>

        </form>

    </dialog>
@endsection

@push('script')
    @vite('resources/js/pages/dispute-resolution.js')
@endpush