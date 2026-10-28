@extends('layouts.app')

@section('title', 'Farmer Profile Details')

@push('styles')
    @vite([
        'resources/css/farmer-profile.css',
        'resources/js/pages/farmer-profile.js'
    ])
@endpush

@section('content')

    {{-- =========================================================
    PAGE HEADER
    ========================================================= --}}

    <section class="welcome">
        <div>
            <span class="eyebrow">FARMER MANAGEMENT</span>

            <h1>
                Farmer <span class="welcome-dot">Profile.</span>
            </h1>

            <p>
                View registered farmer information, verification status,
                geographic assignment, and account details.
            </p>
        </div>

        <span class="welcome-ornament" aria-hidden="true">✳</span>
    </section>

    {{-- =========================================================
    NAVIGATION AND ACTIONS
    ========================================================= --}}

    <div class="fp-toolbar">
        <a href="/farmers" class="fp-back-button">
            <span aria-hidden="true">←</span>
            Back to Farmer Directory
        </a>

        <button type="button" id="fp-refresh" class="fp-refresh-button">
            Refresh Profile
        </button>
    </div>

    {{-- =========================================================
    LOADING AND ERROR STATES
    ========================================================= --}}

    <div id="fp-loading" class="fp-notice" role="status">
        Loading farmer profile...
    </div>

    <div id="fp-error" class="fp-notice fp-error" role="alert" hidden>
        <div>
            <strong>Unable to load farmer profile</strong>
            <p id="fp-error-message">
                An unexpected error occurred.
            </p>
        </div>

        <button type="button" id="fp-retry">
            Try Again
        </button>
    </div>

    {{-- =========================================================
    MAIN PROFILE CONTENT
    ========================================================= --}}

    <div id="fp-content" class="fp-content" data-farmer-id="{{ $farmer }}" hidden>

        {{-- FARMER IDENTITY CARD --}}
        <section class="fp-identity-card">

            <div class="fp-identity-main">
                <div class="fp-avatar" id="fp-avatar" aria-hidden="true">
                    --
                </div>

                <div class="fp-identity-info">
                    <span class="fp-overline">
                        REGISTERED FARMER
                    </span>

                    <h2 id="fp-full-name">—</h2>

                    <div class="fp-identity-meta">
                        <span>
                            Farmer Code:
                            <strong id="fp-farmer-code">—</strong>
                        </span>

                        <span class="fp-meta-divider" aria-hidden="true">•</span>

                        <span id="fp-location-summary">
                            Location unavailable
                        </span>
                    </div>
                </div>
            </div>

            <div class="fp-identity-status">
                <span class="fp-status-label">
                    Verification Status
                </span>

                <span id="fp-verification-badge" class="fp-badge fp-badge-neutral">
                    —
                </span>
            </div>

        </section>

        {{-- QUICK INFORMATION --}}
        <section class="fp-quick-grid">

            <article class="fp-quick-card">
                <span class="fp-quick-label">
                    Account Status
                </span>

                <strong id="fp-account-status">
                    —
                </strong>

                <small>
                    Current farmer account standing
                </small>
            </article>

            <article class="fp-quick-card">
                <span class="fp-quick-label">
                    Municipality
                </span>

                <strong id="fp-municipality">
                    —
                </strong>

                <small>
                    Registered municipality
                </small>
            </article>

            <article class="fp-quick-card">
                <span class="fp-quick-label">
                    Barangay
                </span>

                <strong id="fp-barangay">
                    —
                </strong>

                <small>
                    Registered barangay
                </small>
            </article>

            <article class="fp-quick-card">
                <span class="fp-quick-label">
                    Registration Date
                </span>

                <strong id="fp-registration-date">
                    —
                </strong>

                <small>
                    Date farmer profile was created
                </small>
            </article>

        </section>

        {{-- DETAILS GRID --}}
        <div class="fp-details-grid">

            {{-- PERSONAL INFORMATION --}}
            <section class="fp-panel">

                <div class="fp-panel-heading">
                    <div>
                        <span class="fp-section-eyebrow">
                            FARMER INFORMATION
                        </span>

                        <h3>Personal Information</h3>

                        <p>
                            Basic identity information associated
                            with this farmer account.
                        </p>
                    </div>
                </div>

                <div class="fp-detail-list">

                    <div class="fp-detail-row">
                        <span>First Name</span>
                        <strong id="fp-first-name">—</strong>
                    </div>

                    <div class="fp-detail-row">
                        <span>Middle Name</span>
                        <strong id="fp-middle-name">—</strong>
                    </div>

                    <div class="fp-detail-row">
                        <span>Last Name</span>
                        <strong id="fp-last-name">—</strong>
                    </div>

                    <div class="fp-detail-row">
                        <span>Farmer Code</span>
                        <strong id="fp-detail-farmer-code">—</strong>
                    </div>

                </div>
            </section>

            {{-- CONTACT INFORMATION --}}
            <section class="fp-panel">

                <div class="fp-panel-heading">
                    <div>
                        <span class="fp-section-eyebrow">
                            COMMUNICATION
                        </span>

                        <h3>Contact Information</h3>

                        <p>
                            Contact details associated with
                            the farmer's registered account.
                        </p>
                    </div>
                </div>

                <div class="fp-detail-list">

                    <div class="fp-detail-row">
                        <span>Email Address</span>
                        <strong id="fp-email">—</strong>
                    </div>

                    <div class="fp-detail-row">
                        <span>Phone Number</span>
                        <strong id="fp-phone">—</strong>
                    </div>

                </div>
            </section>

            {{-- GEOGRAPHIC INFORMATION --}}
            <section class="fp-panel">

                <div class="fp-panel-heading">
                    <div>
                        <span class="fp-section-eyebrow">
                            GEOGRAPHIC ASSIGNMENT
                        </span>

                        <h3>Location Information</h3>

                        <p>
                            Municipality and barangay associated
                            with this farmer profile.
                        </p>
                    </div>
                </div>

                <div class="fp-detail-list">

                    <div class="fp-detail-row">
                        <span>Municipality</span>
                        <strong id="fp-detail-municipality">—</strong>
                    </div>

                    <div class="fp-detail-row">
                        <span>Barangay</span>
                        <strong id="fp-detail-barangay">—</strong>
                    </div>

                </div>
            </section>

            {{-- ACCOUNT AND VERIFICATION --}}
            <section class="fp-panel">

                <div class="fp-panel-heading">
                    <div>
                        <span class="fp-section-eyebrow">
                            ACCOUNT MANAGEMENT
                        </span>

                        <h3>Account & Verification</h3>

                        <p>
                            Current account standing and
                            identity verification information.
                        </p>
                    </div>
                </div>

                <div class="fp-detail-list">

                    <div class="fp-detail-row">
                        <span>Account Status</span>

                        <span id="fp-detail-account-status" class="fp-badge fp-badge-neutral">
                            —
                        </span>
                    </div>

                    <div class="fp-detail-row">
                        <span>Verification Status</span>

                        <span id="fp-detail-verification" class="fp-badge fp-badge-neutral">
                            —
                        </span>
                    </div>

                    <div class="fp-detail-row">
                        <span>Profile Created</span>
                        <strong id="fp-created-at">—</strong>
                    </div>

                    <div class="fp-detail-row">
                        <span>Last Updated</span>
                        <strong id="fp-updated-at">—</strong>
                    </div>

                </div>
            </section>

        </div>

        {{-- =====================================================
        LIVESTOCK SECTION — PHASE 5.3
        ===================================================== --}}

        <section class="fp-panel fp-livestock-panel">

            <div class="fp-panel-heading">
                <div>
                    <span class="fp-section-eyebrow">
                        LIVESTOCK MANAGEMENT
                    </span>

                    <h3>Registered Livestock</h3>

                    <p>
                        Livestock records associated with
                        this farmer profile.
                    </p>
                </div>

                <span class="fp-coming-soon">
                    Phase 5.3
                </span>
            </div>

            <div class="fp-livestock-placeholder">
                <div class="fp-placeholder-icon" aria-hidden="true">
                    ◇
                </div>

                <h4>Livestock Records Integration</h4>

                <p>
                    The farmer's livestock records and
                    inventory summary will be connected
                    during the Livestock Records module.
                </p>
            </div>

        </section>

    </div>

@endsection