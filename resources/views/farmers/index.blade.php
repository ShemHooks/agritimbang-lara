@extends('layouts.app')

@section('title', 'Farmer Directory')

@push('styles')
    @vite([
        'resources/css/farmer-directory.css',
        'resources/js/pages/farmer-directory.js'
    ])
@endpush

@section('content')

    <section class="welcome">
        <div>
            <span class="eyebrow">FARMER & LIVESTOCK MANAGEMENT</span>
            <h1>Farmer <span class="welcome-dot">Directory.</span></h1>
            <p>
                Browse registered farmers, review account verification,
                and explore municipality-based farmer records.
            </p>
        </div>

        <span class="welcome-ornament" aria-hidden="true">✳</span>
    </section>

    <section class="fd-heading">
        <div>
            <h2>Registered Farmers</h2>
            <p id="fd-scope">Loading authorized farmer records...</p>
        </div>

        <button type="button" id="fd-refresh" class="fd-button">
            Refresh Directory
        </button>
    </section>

    {{-- SUMMARY --}}
    <section class="fd-stats">
        <article class="fd-stat">
            <span>Registered Farmers</span>
            <strong id="fd-total">—</strong>
            <small>Records matching the current filters</small>
        </article>

        <article class="fd-stat">
            <span>Verified Farmers</span>
            <strong id="fd-verified">—</strong>
            <small>Verified accounts in the current result set</small>
        </article>

        <article class="fd-stat">
            <span>Pending Verification</span>
            <strong id="fd-pending">—</strong>
            <small>Pending accounts in the current result set</small>
        </article>
    </section>

    {{-- FILTERS --}}
    <section class="fd-panel">
        <div class="fd-panel-heading">
            <div>
                <h3>Search & Filter</h3>
                <p>Find farmers by name, contact information, or location.</p>
            </div>
        </div>

        <div class="fd-filters">
            <div class="fd-filter fd-search">
                <label for="fd-search">Search Farmer</label>
                <input id="fd-search" type="search" placeholder="Name, email, phone, farmer code..." autocomplete="off">
            </div>

            <div class="fd-filter">
                <label for="fd-municipality">Municipality</label>
                <select id="fd-municipality">
                    <option value="">All authorized municipalities</option>
                </select>
            </div>

            <div class="fd-filter">
                <label for="fd-barangay">Barangay</label>
                <select id="fd-barangay">
                    <option value="">All barangays</option>
                </select>
            </div>

            <div class="fd-filter">
                <label for="fd-verification">Verification</label>
                <select id="fd-verification">
                    <option value="">All statuses</option>
                    <option value="verified">Verified</option>
                    <option value="pending">Pending</option>
                    <option value="unverified">Unverified</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>

            <div class="fd-filter-actions">
                <button type="button" id="fd-reset" class="fd-button-secondary">
                    Reset Filters
                </button>
            </div>
        </div>
    </section>

    {{-- DIRECTORY TABLE --}}
    <section class="fd-panel">
        <div class="fd-panel-heading">
            <div>
                <h3>Farmer Records</h3>
                <p id="fd-result-count">Loading records...</p>
            </div>
        </div>

        <div id="fd-loading" class="fd-notice" role="status" hidden>
            Loading farmer directory...
        </div>

        <div id="fd-error" class="fd-notice fd-error" role="alert" hidden>
            <span id="fd-error-message"></span>
            <button type="button" id="fd-retry">Try Again</button>
        </div>

        <div class="fd-table-scroll">
            <table class="fd-table">
                <thead>
                    <tr>
                        <th>Farmer</th>
                        <th>Farmer Code</th>
                        <th>Municipality / Barangay</th>
                        <th>Verification</th>
                        <th>Account</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody id="fd-table-body">
                    <tr>
                        <td colspan="6" class="fd-empty">
                            Loading farmer records...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="fd-pagination">
            <span id="fd-page-info">—</span>

            <div class="fd-pagination-actions">
                <button type="button" id="fd-prev" disabled>Previous</button>
                <button type="button" id="fd-next" disabled>Next</button>
            </div>
        </div>
    </section>

@endsection