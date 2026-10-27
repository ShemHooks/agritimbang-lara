@extends('layouts.app')

@section('title', 'Transaction Analytics')

@push('styles')
    @vite([
        'resources/css/transaction-analytics.css',
        'resources/js/pages/transaction-analytics.js'
    ])
@endpush

@section('content')

    {{-- PAGE INTRODUCTION --}}
    <section class="welcome">
        <div>
            <span class="eyebrow">LIVESTOCK MANAGEMENT</span>

            <h1>
                Transaction <span class="welcome-dot">Analytics.</span>
            </h1>

            <p>
                Monitor livestock transaction activity, verification
                outcomes, and official sales performance.
            </p>
        </div>

        <span class="welcome-ornament" aria-hidden="true">✳</span>
    </section>

    {{-- PAGE HEADING --}}
    <div class="ta-overview-heading">
        <div>
            <h2>Transaction Overview</h2>

            <p id="ta-scope">
                Loading authorized transaction records...
            </p>
        </div>

        <button type="button" id="ta-refresh" class="ta-button">
            Refresh data
        </button>
    </div>

    {{-- LOADING STATE --}}
    <div id="ta-loading" class="ta-notice" role="status" hidden>
        Loading transaction analytics...
    </div>

    {{-- ERROR STATE --}}
    <div id="ta-error" class="ta-notice ta-error" role="alert" hidden>
        <span id="ta-error-message"></span>

        <button type="button" id="ta-retry">
            Try again
        </button>
    </div>

    {{-- MAIN CONTENT --}}
    <div id="ta-content" hidden>

        {{-- FILTERS --}}
        <section class="ta-panel ta-filter-panel">
            <div class="ta-panel-heading">
                <div>
                    <h3>Analytics Filters</h3>

                    <p>
                        View transaction performance by municipality
                        and reporting year.
                    </p>
                </div>
            </div>

            <div class="ta-filters">

                <div class="ta-filter-field" id="ta-municipality-field">
                    <label for="ta-municipality">
                        Municipality
                    </label>

                    <select id="ta-municipality">
                        <option value="">
                            All authorized municipalities
                        </option>
                    </select>
                </div>

                <div class="ta-filter-field">
                    <label for="ta-year">
                        Reporting Year
                    </label>

                    <select id="ta-year">
                        <option value="">
                            All years
                        </option>
                    </select>
                </div>

                <div class="ta-filter-actions">
                    <button type="button" id="ta-clear-filters" class="ta-button-secondary">
                        Clear Filters
                    </button>
                </div>

            </div>
        </section>

        {{-- KPI CARDS --}}
        <section class="ta-stats" aria-label="Transaction statistics">

            <article class="ta-stat">
                <span>Total Transactions</span>

                <strong id="ta-total-transactions">
                    —
                </strong>

                <small>
                    All recorded transaction statuses
                </small>
            </article>

            <article class="ta-stat">
                <span>Verified Transactions</span>

                <strong id="ta-verified-transactions">
                    —
                </strong>

                <small>
                    Successfully verified livestock transactions
                </small>
            </article>

            <article class="ta-stat">
                <span>Verified Sales</span>

                <strong id="ta-verified-sales">
                    —
                </strong>

                <small>
                    Total selling value of verified transactions
                </small>
            </article>

            <article class="ta-stat">
                <span>Verification Rate</span>

                <strong id="ta-verification-rate">
                    —
                </strong>

                <small>
                    Verified transactions as a percentage of total
                </small>
            </article>

        </section>

        {{-- TRANSACTION STATUS + MONTHLY SUMMARY --}}
        <div class="ta-analytics-grid">

            {{-- STATUS DISTRIBUTION --}}
            <section class="ta-panel">
                <div class="ta-panel-heading">
                    <div>
                        <h3>Transaction Status Distribution</h3>

                        <p>
                            Breakdown of transactions by
                            their current status.
                        </p>
                    </div>
                </div>

                <div id="ta-status-chart" class="ta-status-chart"></div>

                <div id="ta-status-legend" class="ta-status-legend"></div>
            </section>

            {{-- MONTHLY SUMMARY --}}
            <section class="ta-panel">
                <div class="ta-panel-heading">
                    <div>
                        <h3>Monthly Verification Summary</h3>

                        <p>
                            Verified transaction activity
                            for the selected reporting period.
                        </p>
                    </div>
                </div>

                <div class="ta-summary-list">

                    <div class="ta-summary-item">
                        <span>Verified This Period</span>

                        <strong id="ta-period-verified">
                            —
                        </strong>
                    </div>

                    <div class="ta-summary-item">
                        <span>Verified Sales This Period</span>

                        <strong id="ta-period-sales">
                            —
                        </strong>
                    </div>

                    <div class="ta-summary-item">
                        <span>Average Sale per Verified Transaction</span>

                        <strong id="ta-average-sale">
                            —
                        </strong>
                    </div>

                    <div class="ta-summary-item">
                        <span>Months with Verified Activity</span>

                        <strong id="ta-active-months">
                            —
                        </strong>
                    </div>

                </div>

                <p class="ta-summary-note">
                    Period statistics use verified transaction dates
                    and exclude unverified transactions.
                </p>
            </section>

        </div>

        {{-- MONTHLY VERIFIED SALES CHART --}}
        <section class="ta-panel">
            <div class="ta-panel-heading">
                <div>
                    <h3>Monthly Verified Sales</h3>

                    <p>
                        Track the total selling value of verified
                        livestock transactions over time.
                    </p>
                </div>

                <div class="ta-chart-controls">
                    <label for="ta-chart-metric">
                        Display
                    </label>

                    <select id="ta-chart-metric">
                        <option value="sales">
                            Sales Value
                        </option>

                        <option value="count">
                            Transaction Count
                        </option>
                    </select>
                </div>
            </div>

            <div class="ta-chart-wrapper">
                <div id="ta-monthly-chart" class="ta-chart" role="img" aria-label="Monthly verified transaction chart">
                </div>
            </div>

            <p class="ta-chart-note">
                Data is based on the verification date.
                Only transactions with verified status are included.
            </p>
        </section>

        {{-- MONTHLY ACTIVITY TABLE --}}
        <section class="ta-panel">
            <div class="ta-panel-heading">
                <div>
                    <h3>Monthly Transaction History</h3>

                    <p>
                        Historical summary of verified transactions
                        and recorded selling values.
                    </p>
                </div>
            </div>

            <div class="ta-table-scroll">
                <table class="ta-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Verified Transactions</th>
                            <th>Total Selling Value</th>
                            <th>Average Selling Value</th>
                        </tr>
                    </thead>

                    <tbody id="ta-monthly-body">
                        <tr>
                            <td colspan="4">
                                Loading monthly transaction history...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="ta-pagination">
                <span id="ta-page-info">
                    —
                </span>

                <div class="ta-pagination-actions">
                    <button type="button" id="ta-prev-page" disabled>
                        Previous
                    </button>

                    <button type="button" id="ta-next-page" disabled>
                        Next
                    </button>
                </div>
            </div>
        </section>

    </div>

@endsection