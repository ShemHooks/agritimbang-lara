
@extends('layouts.app')

@section('content')
    <main class="calc-page" id="weight-calculator">

        {{-- =====================================================
             PAGE HEADER
        ====================================================== --}}
        <header class="calc-heading">
            <div>
                <p class="calc-eyebrow">AGRITIMBANG / LIVESTOCK CALCULATOR</p>
                <h1>Livestock Calculator</h1>
                <p>Estimate livestock weight, calculate fair market value, and review FPC breakdowns.</p>
            </div>
        </header>

        {{-- =====================================================
             CALCULATOR TABS
        ====================================================== --}}
        <nav class="calc-tabs" aria-label="Calculator features">
            <button
                id="calc-tab-weight"
                class="active"
                type="button"
                data-calc-tab="weight"
                aria-selected="true"
                aria-controls="calc-weight-panel"
            >
                Weight Estimation
            </button>

            <button
                id="calc-tab-valuation"
                type="button"
                data-calc-tab="valuation"
                aria-selected="false"
                aria-controls="calc-valuation-panel"
            >
                Livestock Valuation
            </button>

            <button
                id="calc-tab-fpc"
                type="button"
                data-calc-tab="fpc"
                aria-selected="false"
                aria-controls="calc-fpc-panel"
            >
                FPC Breakdown
            </button>
        </nav>

        {{-- Shared API error message --}}
        <div id="calc-error" class="calc-error" role="alert" hidden></div>

        {{-- =====================================================
             TAB 1: WEIGHT ESTIMATION
        ====================================================== --}}
        <div id="calc-weight-panel">
            <div class="calc-layout">

                {{-- Animal measurements --}}
                <section class="calc-card">
                    <div class="calc-card-heading">
                        <h2>Animal measurements</h2>
                        <p>Select a livestock record and enter its measurements in centimeters.</p>
                    </div>

                    <form id="calc-form">
                        <label class="calc-field">
                            <span>Livestock record</span>
                            <select id="calc-livestock" required>
                                <option value="">Loading livestock...</option>
                            </select>
                        </label>

                        <div id="calc-animal-info" class="calc-animal-info" hidden></div>

                        <div class="calc-field-grid">
                            <label class="calc-field">
                                <span>Heart girth (cm)</span>
                                <input
                                    id="calc-girth"
                                    type="number"
                                    min="0.01"
                                    max="500"
                                    step="any"
                                    placeholder="Enter heart girth"
                                >
                            </label>

                            <label class="calc-field">
                                <span>Body length (cm)</span>
                                <input
                                    id="calc-length"
                                    type="number"
                                    min="0.01"
                                    max="500"
                                    step="any"
                                    placeholder="Enter body length"
                                >
                            </label>

                            <label class="calc-field">
                                <span>Rump height (cm)</span>
                                <input
                                    id="calc-height"
                                    type="number"
                                    min="0.01"
                                    max="300"
                                    step="any"
                                    placeholder="Enter rump height"
                                >
                            </label>
                        </div>

                        <p class="calc-note">
                            Measurement requirements depend on the formula selected
                            by the backend. Unused measurements may be left blank.
                        </p>

                        <button
                            id="calc-submit"
                            class="calc-primary"
                            type="submit"
                        >
                            Calculate &amp; Save Estimation
                        </button>
                    </form>
                </section>

                {{-- Estimation result --}}
                <section class="calc-card calc-result-card">
                    <div class="calc-card-heading">
                        <h2>Estimation result</h2>
                        <p>Results are calculated and saved by the AgriTimbang API.</p>
                    </div>

                    <div id="calc-empty" class="calc-empty">
                        <div class="calc-empty-icon">⚖</div>
                        <h3>No estimation yet</h3>
                        <p>Select livestock and calculate to view its estimated weight.</p>
                    </div>

                    <div id="calc-result" hidden>
                        <div class="calc-weight">
                            <span>Estimated live weight</span>
                            <strong id="calc-weight-value">—</strong>
                            <small>kilograms (kg)</small>
                        </div>

                        <div class="calc-result-grid">
                            <div>
                                <span>Confidence</span>
                                <strong id="calc-confidence">—</strong>
                            </div>

                            <div>
                                <span>Frame category</span>
                                <strong id="calc-frame">—</strong>
                            </div>

                            <div>
                                <span>Formula</span>
                                <strong id="calc-formula">—</strong>
                            </div>

                            <div>
                                <span>Version</span>
                                <strong id="calc-version">—</strong>
                            </div>
                        </div>

                        <div id="calc-warnings" class="calc-warnings" hidden></div>
                    </div>
                </section>
            </div>

            {{-- Estimation history belongs inside Weight Estimation --}}
            <section class="calc-card calc-history">
                <div class="calc-card-heading">
                    <h2>Estimation history</h2>
                    <p>Previously saved estimates for the selected livestock.</p>
                </div>

                <div class="calc-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Estimated weight</th>
                                <th>Formula</th>
                                <th>Confidence</th>
                                <th>Frame</th>
                            </tr>
                        </thead>

                        <tbody id="calc-history-body">
                            <tr>
                                <td colspan="5">
                                    Select livestock to view history.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="calc-pagination">
                    <button id="calc-prev" type="button" disabled>
                        Previous
                    </button>

                    <span id="calc-page-label">Page 1</span>

                    <button id="calc-next" type="button" disabled>
                        Next
                    </button>
                </div>
            </section>
        </div>

        {{-- =====================================================
             TAB 2: LIVESTOCK VALUATION
        ====================================================== --}}
        <section id="calc-valuation-panel" hidden>
            <div class="calc-layout">

                {{-- Valuation form --}}
                <section class="calc-card">
                    <div class="calc-card-heading">
                        <h2>Fair Market Valuation</h2>
                        <p>
                            Calculate the estimated market value using official
                            price references and approved adjustment factors.
                        </p>
                    </div>

                    <form id="calc-valuation-form">
                        <label class="calc-field">
                            <span>Livestock record</span>
                            <select id="calc-valuation-livestock" required>
                                <option value="">Select livestock</option>
                            </select>
                        </label>

                        <div
                            id="calc-valuation-animal"
                            class="calc-animal-info"
                            hidden
                        ></div>

                        <label class="calc-field calc-valuation-estimation-field">
                            <span>Weight estimation</span>

                            <select id="calc-valuation-estimation">
                                <option value="">
                                    Automatic — use latest estimation
                                </option>
                            </select>
                        </label>

                        <p class="calc-note">
                            Actual scale weight takes priority when available.
                            Otherwise, the system uses the selected or latest
                            weight estimation.
                        </p>

                        <button
                            id="calc-valuation-submit"
                            type="submit"
                            class="calc-primary"
                        >
                            Calculate &amp; Save Valuation
                        </button>
                    </form>
                </section>

                {{-- Valuation result --}}
                <section class="calc-card calc-result-card">
                    <div class="calc-card-heading">
                        <h2>Valuation Result</h2>
                        <p>Fair price calculation using FPC-1.1.</p>
                    </div>

                    <div id="calc-valuation-empty" class="calc-empty">
                        <h3>No valuation yet</h3>
                        <p>
                            Select livestock and calculate to see
                            its estimated market value.
                        </p>
                    </div>

                    <div id="calc-valuation-result" hidden>
                        <div class="calc-weight">
                            <span>Estimated fair market value</span>
                            <strong id="calc-fair-value">₱ —</strong>
                            <small>Philippine Peso (PHP)</small>
                        </div>

                        <div class="calc-result-grid">
                            <div>
                                <span>Weight source</span>
                                <strong id="calc-weight-source">—</strong>
                            </div>

                            <div>
                                <span>Weight used</span>
                                <strong id="calc-weight-used">—</strong>
                            </div>

                            <div>
                                <span>Base price / kg</span>
                                <strong id="calc-base-price">—</strong>
                            </div>

                            <div>
                                <span>Adjusted price / kg</span>
                                <strong id="calc-adjusted-price">—</strong>
                            </div>

                            <div>
                                <span>Calculation version</span>
                                <strong id="calc-valuation-version">—</strong>
                            </div>
                        </div>

                        <div class="calc-breakdown">
                            <h3>Pricing adjustment factors</h3>
                            <div id="calc-adjustment-list"></div>
                        </div>

                        <details class="calc-snapshot">
                            <summary>View calculation snapshot</summary>
                            <pre id="calc-snapshot-json"></pre>
                        </details>
                    </div>
                </section>
            </div>

            {{-- Valuation history belongs inside Valuation --}}
            <section class="calc-card calc-history">
                <div class="calc-card-heading">
                    <h2>Valuation History</h2>
                    <p>Previously calculated livestock market values.</p>
                </div>

                <div class="calc-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Weight</th>
                                <th>Price / kg</th>
                                <th>Fair Value</th>
                                <th>Version</th>
                            </tr>
                        </thead>

                        <tbody id="calc-valuation-history">
                            <tr>
                                <td colspan="5">
                                    Select livestock to view valuation history.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="calc-pagination">
                    <button id="calc-valuation-prev" type="button" disabled>
                        Previous
                    </button>

                    <span id="calc-valuation-page">Page 1</span>

                    <button id="calc-valuation-next" type="button" disabled>
                        Next
                    </button>
                </div>
            </section>
        </section>

        {{-- =====================================================
             TAB 3: FPC BREAKDOWN
        ====================================================== --}}
        <section id="calc-fpc-panel" hidden>

            {{-- FPC record selection --}}
            <div class="calc-fpc-toolbar calc-card">
                <div>
                    <h2>Fair Price Calculation Breakdown</h2>
                    <p>
                        Review saved FPC-1.1 valuations, official price references,
                        adjustment factors, and calculation evidence.
                    </p>
                </div>

                <div class="calc-fpc-controls">
                    <label class="calc-field">
                        <span>Livestock record</span>
                        <select id="calc-fpc-livestock">
                            <option value="">Select livestock</option>
                        </select>
                    </label>

                    <label class="calc-field">
                        <span>Saved valuation</span>
                        <select id="calc-fpc-valuation">
                            <option value="">Select a valuation</option>
                        </select>
                    </label>
                </div>

                <div class="calc-pagination">
                    <button id="calc-fpc-prev" type="button" disabled>
                        Previous
                    </button>

                    <span id="calc-fpc-page-label">Page 1 of 1</span>

                    <button id="calc-fpc-next" type="button" disabled>
                        Next
                    </button>
                </div>
            </div>

            {{-- FPC empty state --}}
            <div id="calc-fpc-empty" class="calc-card calc-empty">
                <h3>No valuation selected</h3>
                <p>
                    Select a livestock record with a saved valuation
                    to view its calculation breakdown.
                </p>
            </div>

            {{-- FPC calculation details --}}
            <div id="calc-fpc-content" hidden>

                {{-- Summary cards --}}
                <div class="calc-fpc-summary">
                    <article class="calc-card calc-fpc-highlight">
                        <span>Fair Market Value</span>
                        <strong id="calc-fpc-value">—</strong>
                        <small id="calc-fpc-version">—</small>
                    </article>

                    <article class="calc-card calc-fpc-stat">
                        <span>Weight used</span>
                        <strong id="calc-fpc-weight">—</strong>
                        <small id="calc-fpc-weight-source">—</small>
                    </article>

                    <article class="calc-card calc-fpc-stat">
                        <span>Base price / kg</span>
                        <strong id="calc-fpc-base">—</strong>
                        <small>Official reference</small>
                    </article>

                    <article class="calc-card calc-fpc-stat">
                        <span>Adjusted price / kg</span>
                        <strong id="calc-fpc-adjusted">—</strong>
                        <small>After adjustment factors</small>
                    </article>
                </div>

                <div class="calc-fpc-grid">

                    {{-- Adjustment factors --}}
                    <section class="calc-card">
                        <div class="calc-card-heading">
                            <h2>Pricing Adjustment Factors</h2>
                            <p>
                                Approved multipliers applied to the base
                                livestock price.
                            </p>
                        </div>

                        <div id="calc-fpc-factors"></div>

                        <div class="calc-fpc-formula">
                            <span>Final price per kilogram</span>
                            <strong id="calc-fpc-final-price">—</strong>
                        </div>
                    </section>

                    {{-- Weight evidence --}}
                    <section class="calc-card">
                        <div class="calc-card-heading">
                            <h2>Weight &amp; Classification</h2>
                            <p>
                                Recorded weight evidence used during valuation.
                            </p>
                        </div>

                        <div id="calc-fpc-weight-details"></div>
                    </section>
                </div>

                {{-- Official price reference --}}
                <section class="calc-card calc-fpc-section">
                    <div class="calc-card-heading">
                        <h2>Official Price Reference</h2>
                        <p>
                            Price reference information captured when
                            the valuation was calculated.
                        </p>
                    </div>

                    <div id="calc-fpc-price-details"></div>
                </section>

                {{-- Formula explanation --}}
                <section class="calc-card calc-fpc-section">
                    <div class="calc-card-heading">
                        <h2>Calculation Formula</h2>
                        <p>Reproducible FPC calculation recorded by Laravel.</p>
                    </div>

                    <div class="calc-fpc-equation">
                        <p>
                            Adjusted Price/kg = Base Price/kg × Breed × Age ×
                            Reproductive × Frame × Condition
                        </p>
                        <p>
                            Fair Market Value = Weight Used × Adjusted Price/kg
                        </p>
                    </div>

                    <div id="calc-fpc-calculation-details"></div>
                </section>

                {{-- Calculation snapshot --}}
                <section class="calc-card calc-fpc-section">
                    <div class="calc-card-heading">
                        <h2>Calculation Audit Snapshot</h2>
                        <p>
                            Original calculation inputs and results saved
                            by the valuation engine.
                        </p>
                    </div>

                    <details class="calc-snapshot">
                        <summary>View full calculation snapshot</summary>
                        <pre id="calc-fpc-snapshot"></pre>
                    </details>
                </section>
            </div>
        </section>

    </main>
@endsection

@vite([
    'resources/css/calculator.css',
    'resources/js/pages/calculator.js'
])
