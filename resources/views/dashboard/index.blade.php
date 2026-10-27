@extends('layouts.app')

@section('title', 'Overview')

@push('styles')
    @vite([
        'resources/css/dashboard-overview.css',
        'resources/js/pages/dashboard.js'
    ])
@endpush

@section('content')

    <section class="welcome">
        <div>
            <span class="eyebrow">YOUR WORKSPACE</span>

            <h1>
                Welcome back,
                <span id="welcome-name">there</span><span class="welcome-dot">.</span>
            </h1>

            <p id="dashboard-welcome-description">
                Here's what's happening in your AgriTimbang workspace.
            </p>
        </div>

        <span class="welcome-ornament" aria-hidden="true">✳</span>
    </section>

    <div class="overview-heading">
        <div>
            <h2>Overview</h2>
            <p id="dashboard-subtitle">
                Your workspace at a glance
            </p>
        </div>

        <span class="date-pill" id="today-date"></span>
    </div>

    <!-- Dashboard data loading -->
    <div id="dashboard-loading" class="dashboard-feedback" role="status" hidden>
        Loading your dashboard statistics...
    </div>

    <!-- Dashboard data error -->
    <div id="dashboard-error" class="dashboard-feedback dashboard-feedback-error" role="alert" hidden>
        <span id="dashboard-error-message">
            Unable to load dashboard statistics.
        </span>

        <button type="button" id="dashboard-retry">
            Try again
        </button>
    </div>

    <!-- ADMIN / LGU DASHBOARD -->
    <div id="management-dashboard" hidden>

        <!-- Management dashboard header -->
        <div class="management-toolbar">
            <div>
                <span class="management-eyebrow" id="management-role-label">
                    MANAGEMENT DASHBOARD
                </span>

                <h3 id="management-dashboard-title">
                    Agricultural monitoring
                </h3>

                <p id="management-scope-description">
                    Loading your authorized municipality scope...
                </p>
            </div>

            <div class="management-scope-indicator">
                <span class="scope-indicator-dot"></span>
                <span id="management-scope-badge">
                    Authorized data
                </span>
            </div>
        </div>


        <section class="stats-grid management-stats" aria-label="Management overview metrics">

            <article class="stat-card">
                <div class="stat-top">
                    <span>Registered farmers</span>
                    <span class="stat-icon">♙</span>
                </div>

                <strong id="metric-farmers">—</strong>
                <small>Farmer profiles in your permitted scope</small>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span>Total livestock</span>
                    <span class="stat-icon">♧</span>
                </div>

                <strong id="metric-livestock">—</strong>
                <small>All recorded livestock</small>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span>Verified transactions</span>
                    <span class="stat-icon">✓</span>
                </div>

                <strong id="metric-transactions">—</strong>
                <small>Completed verified transactions</small>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span>Verified sales</span>
                    <span class="stat-icon">₱</span>
                </div>

                <strong id="metric-sales">—</strong>
                <small>Total selling price of verified transactions</small>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span>Estimated inventory value</span>
                    <span class="stat-icon">₱</span>
                </div>

                <strong id="metric-inventory-value">—</strong>
                <small>Latest valuations of active inventory</small>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span>Available livestock</span>
                    <span class="stat-icon">◎</span>
                </div>

                <strong id="metric-available">—</strong>
                <small>Currently marked as available</small>
            </article>
        </section>

        <section class="dashboard-panels">

            <article class="panel">
                <div class="panel-heading">
                    <div>
                        <h3>Livestock inventory status</h3>
                        <p>Distribution of livestock by current status</p>
                    </div>
                </div>

                <div class="inventory-chart">
                    <div id="livestock-status-bar" class="inventory-status-bar" role="img"
                        aria-label="Livestock status distribution">
                    </div>

                    <div id="livestock-status-legend" class="inventory-status-legend">
                    </div>

                    <div id="livestock-empty" class="dashboard-empty" hidden>
                        No livestock records are available yet.
                    </div>
                </div>
            </article>

            <article class="panel">
                <div class="panel-heading">
                    <div>
                        <h3>Financial overview</h3>
                        <p>Verified sales and inventory estimates</p>
                    </div>
                </div>

                <div class="financial-summary">
                    <div class="financial-summary-item">
                        <span>Verified sales total</span>
                        <strong id="financial-sales">—</strong>
                    </div>

                    <div class="financial-summary-item">
                        <span>Estimated inventory value</span>
                        <strong id="financial-inventory">—</strong>
                    </div>

                    <p class="financial-note">
                        Inventory values are estimates, not completed sales.
                        Only verified transactions contribute to sales totals.
                    </p>
                </div>
            </article>
        </section>
    </div>

    <!-- FARMER DASHBOARD -->
    <div id="farmer-dashboard" hidden>

        <section class="stats-grid" aria-label="Farmer overview metrics">

            <article class="stat-card">
                <div class="stat-top">
                    <span>My livestock</span>
                    <span class="stat-icon">♧</span>
                </div>

                <strong id="farmer-livestock-count">—</strong>
                <small id="farmer-livestock-caption">
                    Loading livestock records...
                </small>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span>Transactions</span>
                    <span class="stat-icon">⇄</span>
                </div>

                <strong>—</strong>
                <small>Personal transaction summary coming next</small>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span>Official prices</span>
                    <span class="stat-icon">₱</span>
                </div>

                <strong>—</strong>
                <small>Official price module coming next</small>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span>Account status</span>
                    <span class="stat-icon">✓</span>
                </div>

                <strong id="verification-label" class="status-value">—</strong>

                <small id="verification-caption">
                    Checking account status
                </small>
            </article>
        </section>

        <section class="dashboard-panels">

            <article class="panel">
                <div class="panel-heading">
                    <div>
                        <h3>My livestock overview</h3>
                        <p>Your personal livestock inventory</p>
                    </div>
                </div>

                <div id="farmer-livestock-summary" class="dashboard-empty">
                    Your livestock summary will appear here.
                </div>
            </article>

            <article class="panel">
                <div class="panel-heading">
                    <div>
                        <h3>Getting started</h3>
                        <p>Make the most of your workspace</p>
                    </div>
                </div>

                <div class="getting-started">
                    <div class="guide-item">
                        <span>01</span>
                        <div>
                            <strong>Keep your profile updated</strong>
                            <p>
                                Make sure your farm location and account
                                information are accurate.
                            </p>
                        </div>
                    </div>

                    <div class="guide-item">
                        <span>02</span>
                        <div>
                            <strong>Manage your livestock</strong>
                            <p>
                                Record livestock information and
                                monitor your inventory.
                            </p>
                        </div>
                    </div>

                    <div class="guide-item">
                        <span>03</span>
                        <div>
                            <strong>Explore official prices</strong>
                            <p>
                                Compare your livestock valuations
                                with official price references.
                            </p>
                        </div>
                    </div>
                </div>
            </article>
        </section>
    </div>

@endsection