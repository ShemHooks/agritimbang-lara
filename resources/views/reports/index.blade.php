@extends('layouts.app')

@push('styles')
    @vite([
        'resources/css/reports.css',
        'resources/js/pages/reports.js'
    ])
@endpush

@section('content')
    <div class="rp-page">
        <header class="rp-hero">
            <div>
                <span class="rp-eyebrow">AGRITIMBANG / REPORTING</span>
                <h1>Reports & Exports<span>.</span></h1>
                <p>
                    Generate municipality-scoped livestock, market price,
                    transaction, valuation, and forecasting reports.
                </p>
            </div>
            <div class="rp-hero-icon" aria-hidden="true">↗</div>
        </header>

        <div class="rp-heading">
            <div>
                <h2>Report Generator</h2>
                <p>Configure the report details and select an export format.</p>
            </div>
            <span class="rp-limit">Maximum 5,000 records per report</span>
        </div>

        <div id="rp-error" class="rp-alert rp-alert-error" role="alert" hidden></div>
        <div id="rp-success" class="rp-alert rp-alert-success" role="status" hidden></div>

        <form id="rp-form" class="rp-form">
            <section class="rp-card">
                <div class="rp-section-header">
                    <span class="rp-step">01</span>
                    <div>
                        <h3>Report Configuration</h3>
                        <p>Select the data you want to export.</p>
                    </div>
                </div>

                <div class="rp-grid">
                    <label class="rp-field">
                        <span>Report Type <b>*</b></span>
                        <select name="type" id="rp-type" required>
                            <option value="livestock">Livestock Records</option>
                            <option value="prices">Official Market Prices</option>
                            <option value="transactions">Verified Transactions</option>
                            <option value="valuations">Livestock Valuations</option>
                            <option value="forecast">Price Forecast (ARIMA)</option>
                        </select>
                    </label>

                    <label class="rp-field">
                        <span>Municipality</span>
                        <select name="municipality_id" id="rp-municipality">
                            <option value="">Use my assigned municipality</option>
                        </select>
                        <small>
                            Your LGU permissions determine which municipalities
                            can be included.
                        </small>
                    </label>

                    <label class="rp-field">
                        <span>Species</span>
                        <select name="species_id" id="rp-species">
                            <option value="">All species</option>
                        </select>
                    </label>

                    <label class="rp-field" id="rp-purpose-field" hidden>
                        <span>Sale Purpose</span>
                        <select name="sale_purpose" id="rp-purpose">
                            <option value="">All purposes</option>
                            <option value="slaughter">Slaughter</option>
                            <option value="breeding">Breeding</option>
                            <option value="fattening">Fattening</option>
                            <option value="work">Work</option>
                        </select>
                    </label>
                </div>
            </section>

            <section class="rp-card" id="rp-date-section">
                <div class="rp-section-header">
                    <span class="rp-step">02</span>
                    <div>
                        <h3>Date Range</h3>
                        <p>Optional. Leave blank to include all available dates.</p>
                    </div>
                </div>

                <div class="rp-grid">
                    <label class="rp-field">
                        <span>From Date</span>
                        <input type="date" name="date_from" id="rp-date-from">
                    </label>

                    <label class="rp-field">
                        <span>To Date</span>
                        <input type="date" name="date_to" id="rp-date-to">
                    </label>
                </div>
            </section>

            <section class="rp-card" id="rp-forecast-section" hidden>
                <div class="rp-section-header">
                    <span class="rp-step">02</span>
                    <div>
                        <h3>Forecast Settings</h3>
                        <p>Generate statistical price estimates using ARIMA.</p>
                    </div>
                </div>

                <div class="rp-grid">
                    <label class="rp-field">
                        <span>Forecast Horizon <b>*</b></span>
                        <select name="weeks" id="rp-weeks">
                            @for ($week = 1; $week <= 12; $week++)
                                <option value="{{ $week }}" @selected($week === 4)>
                                    {{ $week }}
                                    {{ $week === 1 ? 'week' : 'weeks' }}
                                </option>
                            @endfor
                        </select>
                        <small>Forecast between 1 and 12 weeks ahead.</small>
                    </label>
                </div>

                <div class="rp-note">
                    Forecasts are statistical estimates and do not guarantee
                    future market prices.
                </div>
            </section>

            <section class="rp-card">
                <div class="rp-section-header">
                    <span class="rp-step">03</span>
                    <div>
                        <h3>Export Format</h3>
                        <p>Choose how you want to download your report.</p>
                    </div>
                </div>

                <div class="rp-export-grid">
                    <label class="rp-export-option">
                        <input type="radio" name="format" value="pdf" checked>
                        <span class="rp-export-content">
                            <strong>PDF Document</strong>
                            <small>Landscape A4, ready for printing</small>
                            <span class="rp-export-tag">.PDF</span>
                        </span>
                    </label>

                    <label class="rp-export-option">
                        <input type="radio" name="format" value="xlsx">
                        <span class="rp-export-content">
                            <strong>Excel Spreadsheet</strong>
                            <small>Tabular data for further analysis</small>
                            <span class="rp-export-tag">.XLSX</span>
                        </span>
                    </label>
                </div>
            </section>

            <footer class="rp-footer">
                <p>
                    Reports include only records permitted by your account's
                    municipality access.
                </p>

                <div class="rp-actions">
                    <button type="reset" class="rp-btn rp-btn-outline">
                        Reset Filters
                    </button>
                    <button type="submit" class="rp-btn rp-btn-primary" id="rp-submit">
                        Generate & Download
                    </button>
                </div>
            </footer>
        </form>
    </div>
@endsection