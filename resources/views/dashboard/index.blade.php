@extends('layouts.app')

@section('title', 'Overview')

@push('styles')
    @vite([
        'resources/css/dashboard.css',
        'resources/css/prices.css',
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
            <h2 id="overview-heading">Overview</h2>
            <p id="dashboard-subtitle">
                Your workspace at a glance
            </p>
        </div>

        <span class="date-pill" id="today-date"></span>
    </div>

    <section class="prices-card" id="dashboard-official-prices" style="margin: 18px 0;">
        <div class="prices-card-header">
            <div>
                <h2>Opisyal nga Presyo sa Hayop</h2>
                <p>Latest approved livestock prices for Carabao, Cattle, Goat, and Hog by sale purpose.</p>
            </div>
            <a href="/prices" class="prices-secondary">Tan-awa ang tanang presyo</a>
        </div>
        <div class="prices-category-tabs" role="tablist" aria-label="Official livestock price purposes">
            <button type="button" class="active" data-dashboard-price-category="slaughter" role="tab" aria-selected="true">
                Slaughter
            </button>
            <button type="button" data-dashboard-price-category="breeding" role="tab" aria-selected="false">
                Breeding
            </button>
            <button type="button" data-dashboard-price-category="fattening" role="tab" aria-selected="false">
                Fattening
            </button>
            <button type="button" data-dashboard-price-category="work" role="tab" aria-selected="false">
                Work
            </button>
        </div>
        <div class="prices-reference-slider" id="dashboard-price-grid" aria-live="polite">
            <p>Nagakuha sa opisyal nga presyo...</p>
        </div>
    </section>

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
                    <span>Total livestock this month</span>
                    <span class="stat-icon">♧</span>
                </div>

                <strong id="metric-livestock">—</strong>
                <small id="metric-livestock-caption">Livestock records created during the current month</small>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span>Verified transactions</span>
                    <span class="stat-icon">✓</span>
                </div>

                <strong id="metric-transactions">—</strong>
                <small id="metric-transactions-caption">Verified transactions this month</small>
            </article>

            <article class="stat-card" id="metric-sales-card">
                <div class="stat-top">
                    <span>Verified sales</span>
                    <span class="stat-icon">₱</span>
                </div>

                <strong id="metric-sales">—</strong>
                <small id="metric-sales-caption">Verified sales this month</small>
            </article>

            <article class="stat-card" id="metric-inventory-value-card">
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
                        <p>Current livestock records grouped by status</p>
                    </div>
                </div>

                <div class="inventory-chart">
                    <div class="inventory-donut-wrap">
                        <div id="livestock-status-chart" class="inventory-donut" role="img"
                            aria-label="Livestock inventory status chart">
                            <svg viewBox="0 0 120 120" aria-hidden="true" focusable="false">
                                <circle class="inventory-donut-track" cx="60" cy="60" r="44"></circle>
                                <g id="livestock-status-slices"></g>
                            </svg>
                            <div class="inventory-donut-center">
                                <strong id="livestock-status-total">0</strong>
                                <span>This month</span>
                            </div>
                        </div>
                    </div>

                    <div id="livestock-status-legend" class="inventory-status-legend"></div>

                    <div id="livestock-empty" class="dashboard-empty" hidden>
                        No livestock records are available yet.
                    </div>
                </div>
            </article>

            <article class="panel" id="financial-overview-panel">
                <div class="panel-heading">
                    <div>
                        <h3>Financial overview</h3>
                        <p>Year-to-date verified sales and current inventory estimates</p>
                    </div>
                </div>

                <div class="financial-summary">
                    <div class="financial-summary-item">
                        <span>Verified sales this year</span>
                        <strong id="financial-sales">—</strong>
                    </div>

                    <div class="financial-summary-item">
                        <span>Estimated inventory value</span>
                        <strong id="financial-inventory">—</strong>
                    </div>

                    <p class="financial-note">
                        Inventory values are estimates, not completed sales.
                        Sales totals include verified transactions from the current calendar year. Inventory value is the latest estimate for active livestock.
                    </p>
                </div>
            </article>
        </section>
    </div>

    <!-- FARMER DASHBOARD -->
    <div id="farmer-dashboard" hidden>

        <div class="farmer-month-filter" style="display:flex;align-items:center;gap:12px;margin:0 0 16px;flex-wrap:wrap;">
            <label for="farmer-dashboard-month" style="font-weight:600;">
                Monthly results
            </label>
            <input id="farmer-dashboard-month" type="month" value="{{ now()->format('Y-m') }}" aria-label="Select dashboard month">
        </div>

        <section class="stats-grid" aria-label="Farmer overview metrics">

            <article class="stat-card">
                <div class="stat-top">
                    <span>Akong mga Hayop</span>
                    <small class="monthly-card-period">KARONG BUWANA</small>
                    <span class="stat-icon">♧</span>
                </div>

                <strong id="farmer-livestock-count">—</strong>
                <small id="farmer-livestock-caption">
                    Loading livestock records...
                </small>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span>Mga Transaksyon</span>
                    <small class="monthly-card-period">KARONG BUWANA</small>
                    <span class="stat-icon">⇄</span>
                </div>

                <strong id="farmer-transactions-count">—</strong>
                <small id="farmer-transactions-caption">Your saved and submitted livestock transactions</small>
                <small id="farmer-transactions-value" class="farmer-monthly-sales">Monthly selling value: ₱0.00</small>
                <small id="farmer-transactions-status" class="farmer-monthly-status"></small>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span>Opisyal nga Presyo</span>
                    <span class="stat-icon">₱</span>
                </div>

                <strong id="farmer-price-species-count">4 species</strong>
                <small>Mga presyo nga gi-aprubahan sa imong munisipyo</small>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span>Kahimtang sa Account</span>
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
                        <h3>Imong mga Hayop</h3>
                        <p>Kahimtang sa imong mga hayop karon.</p>
                    </div>
                </div>

                <div id="farmer-livestock-summary" class="inventory-chart farmer-inventory-chart" aria-live="polite">
                    <div class="farmer-inventory-chart-top">
                        <div>
                            <span class="farmer-inventory-eyebrow">KAHIMTANG SA IMONG MGA HAYOP</span>
                            <strong id="farmer-livestock-total">—</strong>
                            <small>Rehistrado sa piniling bulan</small>
                        </div>
                    </div>
                    <div class="inventory-chart">
                        <div class="inventory-donut-wrap">
                            <div id="farmer-livestock-chart" class="inventory-donut" role="img" aria-label="Livestock inventory status chart">
                                <svg viewBox="0 0 120 120" aria-hidden="true" focusable="false">
                                    <circle class="inventory-donut-track" cx="60" cy="60" r="44"></circle>
                                    <g id="farmer-livestock-slices"></g>
                                </svg>
                                <div class="inventory-donut-center">
                                    <strong id="farmer-livestock-chart-total">0</strong>
                                    <span>This month</span>
                                </div>
                            </div>
                        </div>
                        <div id="farmer-livestock-legend" class="inventory-status-legend">
                            <div class="farmer-inventory-empty">Nag-load sa kahimtang sa imong mga hayop…</div>
                        </div>
                    </div>
                </div>
                <div class="dashboard-quick-links">
                    <a href="/livestock">Tan-awa ang Akong mga Hayop</a>
                    <a href="/transactions">Tan-awa ang Akong mga Transaksyon</a>
                </div>
            </article>

            <article class="panel">
                <div class="panel-heading">
                    <div>
                        <h3>Sugdi Dinhi</h3>
                        <p>Simple steps to manage your livestock and sales.</p>
                    </div>
                </div>

                <div class="getting-started">
                    <div class="guide-item">
                        <span>01</span>
                        <div>
                            <strong>Siguroha nga updated ang imong profile</strong>
                            <p>
                                Make sure your farm location and account
                                information are accurate.
                            </p>
                        </div>
                    </div>

                    <div class="guide-item">
                        <span>02</span>
                        <div>
                            <strong>Tan-awa ug dumala ang imong mga hayop</strong>
                            <p>
                                Record livestock information and
                                monitor your inventory.
                            </p>
                        </div>
                    </div>

                    <div class="guide-item">
                        <span>03</span>
                        <div>
                            <strong>Tan-awa ang opisyal nga presyo</strong>
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