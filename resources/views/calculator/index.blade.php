@extends('layouts.app')

@section('content')
    <main class="calc-page" id="weight-calculator">

        {{-- =====================================================
        PAGE HEADER
        ====================================================== --}}

        <header class="calc-heading">
            <div>
                <p class="calc-eyebrow">AGRITIMBANG / LIVESTOCK CALCULATOR</p>
                <h1>Livestock Calculators</h1>
                <p>
                    Estimate your animal's weight, check its estimated value,
                    and understand the price calculation.
                </p>
            </div>

            <button
                type="button"
                id="calc-help-open"
                class="calc-help-trigger"
                aria-haspopup="dialog"
                aria-controls="calc-help-modal"
            >
                <span class="calc-help-icon" aria-hidden="true">?</span>
                <span>Unsaon paggamit?</span>
            </button>
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
                Fair Price Calculation Breakdown
            </button>
        </nav>

        {{-- Shared API error message --}}
        <div id="calc-error" class="calc-error" role="alert" hidden></div>

        <div id="calc-condition-modal" class="calc-condition-modal" hidden>
            <div class="calc-condition-backdrop" data-condition-close></div>
            <section class="calc-condition-dialog" role="dialog" aria-modal="true" aria-labelledby="calc-condition-title">
                <div class="calc-card-heading">
                    <h2 id="calc-condition-title">Livestock Condition Checklist</h2>
                    <p>Answer all seven questions. The assessment score and 1–5 condition score are calculated automatically.</p>
                </div>
                <div id="calc-condition-questions"></div>
                <div class="calc-condition-summary">
                    <div><span>Assessment Score</span><strong id="calc-condition-assessment-score">— / 14</strong></div>
                    <div><span>Condition Score</span><strong id="calc-condition-score">Awaiting assessment</strong></div>
                    <div class="calc-condition-legend">
                        <strong>Legend · Total Points / Result</strong>
                        <span>0–4: Poor condition · 5–8: Needs attention · 9–11: Fair condition · 12–14: Good condition</span>
                    </div>
                </div>
                <div class="calc-condition-actions">
                    <button type="button" id="calc-condition-close" class="calc-secondary">Close</button>
                    <button type="button" id="calc-condition-apply" class="calc-primary">Use Condition Score</button>
                </div>
            </section>
        </div>

        {{-- =====================================================
        TAB 1: WEIGHT ESTIMATION
        ====================================================== --}}

        <div id="calc-weight-panel">
            <div class="calc-layout">

                {{-- Animal measurements --}}
                <section class="calc-card">
                    <div class="calc-card-heading">
                        <h2>Weight Estimation</h2>
                        <p>
                            Pilia ang imong hayop ug isulod ang mga sukod in centimeters.
                            Ask an LGU Encoder for help if needed.
                        </p>

                        <button
                            type="button"
                            id="calc-measure-open"
                            class="calc-measure-trigger"
                            aria-haspopup="dialog"
                            aria-controls="calc-measure-modal"
                        >
                            <span aria-hidden="true">📏</span>
                            <span>Giunsa pagsukod / How to measure</span>
                        </button>
                    </div>

                    <form id="calc-form">
                        <label class="calc-field">
                            <span>Existing livestock record (optional)</span>
                            <select id="calc-livestock">
                                <option value="">Calculate without a saved livestock record</option>
                            </select>
                            <small>Choose an existing animal to prefill its details, or leave blank to calculate manually.</small>
                        </label>

                        <div id="calc-manual-entry-fields">
                        <div class="calc-field-grid calc-profile-grid">
                            <label class="calc-field">
                                <span>Species <b>*</b></span>
                                <select id="calc-species" required>
                                    <option value="">Loading species...</option>
                                </select>
                            </label>
                            <label class="calc-field">
                                <span>Breed</span>
                                <select id="calc-breed" disabled>
                                    <option value="">Select species first</option>
                                </select>
                                <small id="calc-breed-help">Some species require a breed to select the correct formula.</small>
                            </label>
                            <label class="calc-field">
                                <span>Sex <b>*</b></span>
                                <select id="calc-sex" required>
                                    <option value="">Select sex</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="unknown">Unknown</option>
                                </select>
                            </label>
                            <label class="calc-field">
                                <span>Age (months)</span>
                                <input id="calc-age-months" type="number" min="0" max="600" step="1" placeholder="e.g. 24">
                                <small>Required for goats to select the appropriate formula.</small>
                            </label>
                        </div>
                        </div>

                        <div
                            id="calc-animal-info"
                            class="calc-animal-info"
                            hidden
                        ></div>

                        <div class="calc-field-grid">
                            <label class="calc-field calc-measure-field">
                                <span>Palibot sa dughan / Heart Girth</span>
                                <div class="calc-measure-input">
                                    <input id="calc-girth" type="number" min="0.01" max="500" step="any" placeholder="Enter measurement">
                                    <select id="calc-girth-unit" aria-label="Heart girth unit">
                                        <option value="cm">Centimeter (cm)</option>
                                        <option value="dupa">Dupa</option>
                                        <option value="tiil">Tiil</option>
                                        <option value="bisig">Bisig / Bukton</option>
                                        <option value="dangaw">Dangaw</option>
                                    </select>
                                </div>
                                <small id="calc-girth-guide" class="calc-measure-guide">Use centimeters or select a familiar unit.</small>
                            </label>

                            <label class="calc-field calc-measure-field">
                                <span>Gitas-on sa lawas / Body Length</span>
                                <div class="calc-measure-input">
                                    <input id="calc-length" type="number" min="0.01" max="500" step="any" placeholder="Enter measurement">
                                    <select id="calc-length-unit" aria-label="Body length unit">
                                        <option value="cm">Centimeter (cm)</option>
                                        <option value="dupa">Dupa</option>
                                        <option value="tiil">Tiil</option>
                                        <option value="bisig">Bisig / Bukton</option>
                                        <option value="dangaw">Dangaw</option>
                                    </select>
                                </div>
                                <small id="calc-length-guide" class="calc-measure-guide">Use centimeters or select a familiar unit.</small>
                            </label>

                            <label class="calc-field" id="calc-height-field">
                                <span>Gitas-on sa lubot / Rump Height</span>
                                <div class="calc-measure-input">
                                    <input id="calc-height" disabled type="number" min="0.01" max="300" step="any" placeholder="Enter measurement">
                                    <select id="calc-height-unit" disabled aria-label="Rump height unit">
                                        <option value="cm">Centimeter (cm)</option>
                                        <option value="dupa">Dupa</option>
                                        <option value="tiil">Tiil</option>
                                        <option value="bisig">Bisig / Bukton</option>
                                        <option value="dangaw">Dangaw</option>
                                    </select>
                                </div>
                                <small id="calc-height-guide" class="calc-measure-guide">Use centimeters or select a familiar unit.</small>
                                <small id="calc-height-help" class="calc-measure-guide">Required for goats; disabled for other species.</small>
                            </label>
                        </div>

                        <div class="calc-measure-warning" role="note">
                            <strong>Hinumdumi / Measurement reminder</strong>
                            <p>Ang dupa, tiil, bisig/bukton ug dangaw mga banabana lamang. Lahi-lahi ang sukod sa matag tawo. Mas tukma ang paggamit og measuring tape. I-convert sa sentimetro (cm) ang sukod sa dili pa kwentahon.</p>
                        </div>

                        <button id="calc-submit" class="calc-primary" type="submit">
                            Calculate Estimated Weight
                        </button>
                    </form>
                </section>

                {{-- Estimation result --}}
                <section class="calc-card calc-result-card">
                    <div class="calc-card-heading">
                        <h2>Estimation result</h2>
                        <p>
                            Results are previewed first. A manual calculation does not create a livestock record.
                        </p>
                    </div>

                    <div id="calc-empty" class="calc-empty">
                        <div class="calc-empty-icon">⚖</div>
                        <h3>No estimation yet</h3>
                        <p>
                            Enter the animal details and measurements, then calculate its estimated weight.
                        </p>
                    </div>

                    <div id="calc-result" hidden>
                        <div class="calc-weight">
                            <span>Estimated live eeight</span>
                            <strong id="calc-weight-value">—</strong>
                            <small>kilograms (kg)</small>
                        </div>

                        <div class="calc-result-grid">
                            <div>
                                <span>Confidence Level</span>
                                <strong id="calc-confidence">—</strong>
                            </div>

                            <div>
                                <span>Frame Score</span>
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

                        <div
                            id="calc-warnings"
                            class="calc-warnings"
                            hidden
                        ></div>

                        <div class="calc-next-action">
                            <button type="button" id="calc-go-valuation" class="calc-secondary">Calculate Value</button>
                            <small>Continue to Livestock Valuation with the animal details and calculated weight already filled in.</small>
                        </div>

                        <div id="calc-farmer-actions" class="calc-farmer-actions" hidden>
                            <p>Save this animal to your livestock records or continue toward auction registration.</p>
                            <button type="button" id="calc-save-livestock" class="calc-primary">Save to Livestock</button>
                            <button type="button" id="calc-register-auction" class="calc-secondary">Register for Auction</button>
                            <small id="calc-auction-note">The current project does not yet contain an auction schedule registration module. This saves the livestock first; coordinate with your LGU Encoder for auction registration.</small>
                        </div>
                    </div>
                </section>
            </div>

            {{-- Estimation history belongs inside Pagbanabana sa Timbang --}}
            <section class="calc-card calc-history">
                <div class="calc-card-heading">
                    <h2>Estimation history</h2>
                    <p>
                        Previously saved estimates for the selected livestock.
                    </p>
                </div>

                <div class="calc-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Estimated weight</th>
                                <th>Formula</th>
                                <th>Kasaligan sa resulta</th>
                                <th>Porma sa lawas</th>
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
                            price references and approved mga adjustment factor.
                        </p>
                    </div>

                    <form id="calc-valuation-form">
                        <label class="calc-field">
                            <span>Livestock record</span>

                            <select id="calc-valuation-livestock">
                                <option value="">Calculate without a saved livestock record</option>
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
                                <option value="">Awtomatiko — gamita ang pinakabag-ong banabana</option>
                            </select>
                        </label>

                        <div id="calc-manual-valuation-fields" class="calc-manual-valuation-fields" hidden>
                            <label class="calc-field">
                                <span>Livestock species <b>*</b></span>
                                <select id="calc-valuation-species" required>
                                    <option value="">Select species</option>
                                </select>
                            </label>
                            <label class="calc-field">
                                <span>Sale purpose <b>*</b></span>
                                <select id="calc-manual-sale-purpose">
                                    <option value="">Select purpose</option>
                                    <option value="slaughter">Slaughter</option>
                                    <option value="breeding">Breeding</option>
                                    <option value="fattening">Fattening</option>
                                    <option value="work">Work</option>
                                </select>
                            </label>
                            <div class="calc-field-grid">
                                <div class="calc-field">
                                    <span>Condition assessment <b>*</b></span>
                                    <button type="button" id="calc-condition-open" class="calc-secondary">Complete Condition Checklist</button>
                                    <small id="calc-condition-result">Not yet assessed. Complete the checklist to calculate the condition score automatically.</small>
                                </div>
                                <label class="calc-field">
                                    <span>Weight (kg)</span>
                                    <input id="calc-manual-valuation-weight" type="number" min="0.01" max="10000" step="0.01" placeholder="Enter weight in kg">
                                    <small>Enter a scale-measured weight when no actual or calculated weight is available. Maximum two decimal places.</small>
                                </label>
                                <label class="calc-field">
                                    <span>Reproductive status</span>
                                    <select id="calc-manual-reproductive-status">
                                        <option value="unknown">Unknown</option>
                                        <option value="has_given_birth">Has given birth</option>
                                        <option value="never_given_birth">Never given birth</option>
                                        <option value="not_applicable">Not applicable</option>
                                    </select>
                                </label>
                                <label class="calc-field">
                                    <span>Parity (if applicable)</span>
                                    <input id="calc-manual-parity" type="number" min="1" max="50" step="1" placeholder="Number of births">
                                </label>
                            </div>
                        </div>

                        <p class="calc-note" id="calc-saved-valuation-note">
                            Actual scale weight takes priority when available. Otherwise, the system uses the selected or latest weight estimation.
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
                            Select a saved livestock record, or calculate its weight first and use calculator-only valuation without saving an animal.
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
                                <span>Weight Source</span>
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
                            <h3>Pricing Adjustment Factors</h3>
                            <div id="calc-adjustment-list"></div>
                        </div>

                        <details class="calc-snapshot">
                            <summary>View calculation snapshot</summary>
                            <pre id="calc-snapshot-json"></pre>
                        </details>
                        <div id="calc-valuation-farmer-actions" class="calc-farmer-actions" hidden>
                            <p>Keep the calculated animal details and valuation when registering the livestock.</p>
                            <button type="button" id="calc-valuation-save-livestock" class="calc-primary">Save Livestock</button>
                            <button type="button" id="calc-valuation-register-auction" class="calc-secondary">Save Livestock &amp; Join Auction</button>
                            <small>Actual auction schedule registration still requires an available auction schedule module.</small>
                        </div>
                    </div>
                </section>
            </div>

            {{-- Valuation history belongs inside Valuation --}}
            <section class="calc-card calc-history">
                <div class="calc-card-heading">
                    <h2>Valuation History</h2>
                    <p>
                        Previously calculated livestock market values.
                    </p>
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
                    <button
                        id="calc-valuation-prev"
                        type="button"
                        disabled
                    >
                        Previous
                    </button>

                    <span id="calc-valuation-page">Page 1</span>

                    <button
                        id="calc-valuation-next"
                        type="button"
                        disabled
                    >
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
                    <button
                        id="calc-fpc-prev"
                        type="button"
                        disabled
                    >
                        Previous
                    </button>

                    <span id="calc-fpc-page-label">Page 1 of 1</span>

                    <button
                        id="calc-fpc-next"
                        type="button"
                        disabled
                    >
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
                            the gitipig nga bili sa hayop.
                        </p>
                    </div>

                    <div id="calc-fpc-price-details"></div>
                </section>

                {{-- Formula explanation --}}
                <section class="calc-card calc-fpc-section">
                    <div class="calc-card-heading">
                        <h2>Calculation Formula</h2>
                        <p>
                            Reproducible FPC calculation recorded by Laravel.
                        </p>
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

    {{-- =====================================================
    MEASUREMENT INSTRUCTIONS MODAL
    ====================================================== --}}

    <div
        id="calc-measure-modal"
        class="calc-measure-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="calc-measure-title"
        hidden
    >
        <div class="calc-measure-backdrop"></div>

        <section class="calc-measure-dialog" tabindex="-1">
            <header class="calc-measure-header">
                <div>
                    <p class="calc-help-eyebrow">
                        AGRITIMBANG / WEIGHT ESTIMATION
                    </p>

                    <h2 id="calc-measure-title">
                        Giunsa pagsukod sa imong hayop?
                    </h2>

                    <p>
                        Use a soft measuring tape. Keep the animal standing
                        naturally and ask another person to help.
                    </p>
                </div>

                <button
                    type="button"
                    class="calc-help-close"
                    id="calc-measure-close"
                    aria-label="Close measurement instructions"
                >
                    &times;
                </button>
            </header>

            <div class="calc-measure-body">
                <div class="calc-measure-grid">
                    <article class="calc-measure-step">
                        <h3>Palibot sa dughan</h3>

                        <p class="calc-measure-subtitle">
                            Libot sa dughan
                        </p>

                        <img
                            src="/images/calculator/measure-heart-girth.svg"
                            alt="Illustration showing tape around the chest just behind the front legs"
                            loading="lazy"
                        >

                        <p class="calc-measure-label">
                            Details
                        </p>

                        <p>
                            Ilibot ang tape sa pinakalapad nga bahin sa dughan,
                            luyo sa atubangang tiil. Dili hugot kaayo ug dili luag.
                        </p>
                    </article>

                    <article class="calc-measure-step">
                        <h3>Gitas-on sa lawas</h3>

                        <p class="calc-measure-subtitle">
                            Gitas-on sa lawas
                        </p>

                        <img
                            src="/images/calculator/measure-body-length.svg"
                            alt="Illustration measuring from the shoulder point to the pin bone"
                            loading="lazy"
                        >

                        <p class="calc-measure-label">
                            Details
                        </p>

                        <p>
                            Sukda gikan sa abaga (shoulder point) hangtod sa
                            bukog sa likod sa hawak (pin bone), subay sa
                            kilid sa hayop.
                        </p>
                    </article>

                    <article class="calc-measure-step">
                        <h3>Gitas-on sa lubot</h3>

                        <p class="calc-measure-subtitle">
                            Taas sa lubot
                        </p>

                        <img
                            src="/images/calculator/measure-rump-height.svg"
                            alt="Illustration measuring vertically from the ground to the top of the rump"
                            loading="lazy"
                        >

                        <p class="calc-measure-label">
                            Details
                        </p>

                        <p>
                            Sukda gikan sa yuta hangtod sa pinakataas nga bahin
                            sa hawak o rump. Siguroha nga patag ang baroganan.
                        </p>
                    </article>
                </div>

                <p class="calc-measure-note">
                    <strong>Note:</strong>
                    Dili tanan nga pamaagi nanginahanglan sa tanang sukod.
                    Sundon ang mga sukod nga gipangayo sa calculator.
                    Ang resulta estimate lamang, dili kapuli sa timbangan.
                </p>
            </div>
        </section>
    </div>

    {{-- =====================================================
    CALCULATOR HELP MODAL
    ====================================================== --}}

    <div
        id="calc-help-modal"
        class="calc-help-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="calc-help-title"
        aria-describedby="calc-help-description"
        hidden
    >
        <div
            class="calc-help-backdrop"
            data-help-close
        ></div>

        <div class="calc-help-dialog" tabindex="-1">
            <header class="calc-help-header">
                <div>
                    <p class="calc-help-eyebrow">
                        GIYA SA PAGGAMIT SA AGRITIMBANG
                    </p>

                    <h2 id="calc-help-title">
                        Giya sa Kalkulasyon sa Hayop
                    </h2>

                    <p id="calc-help-description">
                        Hibal-i kung unsaon pagbanabana sa timbang sa hayop,
                        pagkalkulo sa patas nga bili sa merkado, ug pagsabot sa FPC.
                    </p>
                </div>

                <button
                    type="button"
                    id="calc-help-close"
                    class="calc-help-close"
                    aria-label="Isira ang giya sa kalkulasyon"
                >
                    &times;
                </button>
            </header>

            <div class="calc-help-body">
                <nav
                    class="calc-help-nav"
                    aria-label="Mga hilisgutan sa giya"
                >
                    <button
                        type="button"
                        class="active"
                        data-help-tab="overview"
                    >
                        Kinatibuk-ang Giya
                    </button>

                    <button
                        type="button"
                        data-help-tab="weight"
                    >
                        Pagbanabana sa Timbang
                    </button>

                    <button
                        type="button"
                        data-help-tab="valuation"
                    >
                        Pagkalkulo sa Bili sa Hayop
                    </button>

                    <button
                        type="button"
                        data-help-tab="fpc"
                    >
                        Breakdown sa Fair Price Calculation
                    </button>
                </nav>

                <div class="calc-help-content">

                    <!-- OVERVIEW -->
                    <section data-help-panel="overview">
                        <span class="calc-help-kicker">
                            PAGSUGOD
                        </span>

                        <h3>
                            Unsa ang gamit sa calculator?
                        </h3>

                        <p>
                            Makatabang ang AgriTimbang sa pagbanabana sa
                            buhing timbang sa hayop, pagkalkulo sa gibanabanang
                            patas nga bili niini, ug pagsusi sa mga hinungdan sa presyo
                            nga gigamit sa kalkulasyon.
                        </p>

                        <figure class="calc-help-illustration">
                            <img
                                src="/images/calculator/measurement-overview.svg"
                                alt="Illustration showing livestock body measurements"
                                loading="lazy"
                            >

                            <figcaption>
                                Mga sukod nga gamiton sa pagbanabana sa timbang.
                            </figcaption>
                        </figure>

                        <div class="calc-help-steps">
                            <div class="calc-help-step">
                                <span>01</span>

                                <div>
                                    <strong>
                                        Banabana sa Timbang
                                    </strong>

                                    <p>
                                        Pilia ang rekord sa hayop ug isulod
                                        ang mga sukod nga gikinahanglan sa
                                        pormula sa pagbanabana sa timbang.
                                    </p>
                                </div>
                            </div>

                            <div class="calc-help-step">
                                <span>02</span>

                                <div>
                                    <strong>
                                        Kalkulaha ang Bili
                                    </strong>

                                    <p>
                                        Gamita ang anaa nga datos sa timbang ug
                                        opisyal nga reperensiya sa presyo aron
                                        mabanabana ang bili sa merkado.
                                    </p>
                                </div>
                            </div>

                            <div class="calc-help-step">
                                <span>03</span>

                                <div>
                                    <strong>
                                        Susiha ang Breakdown sa Fair Price Calculation
                                    </strong>

                                    <p>
                                        Susiha ang timbang, opisyal nga presyo,
                                        mga adjustment factor, ug mga gitipig nga
                                        detalye sa kalkulasyon.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="calc-help-note">
                            Banabana lamang ang mga nakalkulang bili ug dili
                            garantiya sa kataposang presyo sa pagbaligya sa hayop.
                        </div>
                    </section>

                    <!-- WEIGHT -->
                    <section data-help-panel="weight" hidden>
                        <span class="calc-help-kicker">
                            BAHIN 01
                        </span>

                        <figure class="calc-help-illustration">
                            <img
                                src="/images/calculator/weight-measurement.svg"
                                alt="Illustration showing heart girth and body length measurement"
                                loading="lazy"
                            >

                            <figcaption>
                                Palibot sa dughan ug gitas-on sa lawas. Mahimong cm, dupa, tiil, bisig/bukton, o dangaw ang gamiton.
                            </figcaption>
                        </figure>

                        <h3>
                            Pagbanabana sa Timbang
                        </h3>

                        <p>
                            Banabaya ang buhing timbang sa hayop gamit ang
                            mga sukod sa lawas bisan walay
                            aktuwal nga timbangan.
                        </p>

                        <h4>
                            Unsaon paggamit
                        </h4>

                        <ol>
                            <li>Pilia ang species, breed kon gikinahanglan, sex, ug edad sa hayop.</li>
                            <li>Sukda ang gikinahanglang bahin sa lawas.</li>
                            <li>Pilia ang cm o pamilyar nga sukod sama sa dupa, tiil, bisig/bukton, ug dangaw.</li>
                            <li>Susiha ang giya sa matag yunit ug ang mga pasidaan sa sukod.</li>
                            <li>I-klik ang <strong>Calculate Estimated Weight</strong>. Dili pa kini maghimo og livestock record.</li>
                            <li>Kung Farmer, pilia ang pag-save sa livestock o pagpadayon sa auction registration.</li>
                        </ol>

                        <h4>
                            Pagsabot sa mga sukod
                        </h4>

                        <div class="calc-help-definitions">
                            <div>
                                <strong>
                                    Palibot sa dughan
                                </strong>

                                <p>
                                    Ang sukod libot sa
                                    dughan sa hayop, diha-diha sa luyo sa atubangang mga tiil.
                                </p>
                            </div>

                            <div>
                                <strong>
                                    Gitas-on sa lawas
                                </strong>

                                <p>
                                    Ang sukod gikan sa bahin sa abaga
                                    padulong sa likod sa hayop.
                                    Sunda ang hustong mga timailhan
                                    sa pagsukod sa hayop.
                                </p>
                            </div>

                            <div>
                                <strong>
                                    Gitas-on sa lubot
                                </strong>

                                <p>
                                    Ang bertikal nga gitas-on gikan sa patag nga yuta
                                    hangtod sa lubot o hawak sa hayop.
                                </p>
                            </div>
                        </div>

                        <h4>
                            Pagsabot sa resulta
                        </h4>

                        <div class="calc-help-definitions">
                            <div>
                                <strong>
                                    Gibanabanang buhing timbang
                                </strong>

                                <p>
                                    Ang nakalkulang timbang sa kilo (kg).
                                </p>
                            </div>

                            <div>
                                <strong>
                                    Kasaligan sa resulta
                                </strong>

                                <p>
                                    Ang timailhan sa kasaligan sa resulta nga
                                    gihatag sa sistema.
                                </p>
                            </div>

                            <div>
                                <strong>
                                    Klasipikasyon sa porma sa lawas
                                </strong>

                                <p>
                                    Ang klasipikasyon sa porma sa lawas sa hayop
                                    nga resulta sa kalkulasyon.
                                </p>
                            </div>

                            <div>
                                <strong>
                                    Pormula ug bersiyon
                                </strong>

                                <p>
                                    Nagpakita sa pamaagi ug bersiyon sa pormula
                                    nga gigamit sa gitipig nga resulta.
                                </p>
                            </div>
                        </div>

                        <div class="calc-help-note">
                            Magdepende sa gipiling pormula ang mga sukod
                            nga gikinahanglan. Ang mga sukod nga dili kinahanglan
                            mahimong biyaan nga walay sulod. Aron mas tukma ang banabana,
                            sukda pag-ayo ang hayop ug ipahimutang sa hustong paagi
                            ang tape measure.
                        </div>
                    </section>

                    <!-- VALUATION -->
                    <section data-help-panel="valuation" hidden>
                        <span class="calc-help-kicker">
                            BAHIN 02
                        </span>

                        <figure class="calc-help-illustration">
                            <img
                                src="/images/calculator/valuation-guide.svg"
                                alt="Illustration showing livestock valuation using weight and official price"
                                loading="lazy"
                            >

                            <figcaption>
                                Ang banabana nga bili magdepende sa timbang
                                ug opisyal nga presyo.
                            </figcaption>
                        </figure>

                        <h3>
                            Pagkalkulo sa Bili sa Hayop
                        </h3>

                        <p>
                            Kalkulaha ang gibanabanang patas nga bili sa merkado gamit ang
                            timbang sa hayop, opisyal nga reperensiya sa presyo,
                            ug giaprobahang mga adjustment factor.
                        </p>

                        <h4>
                            Unsaon paggamit
                        </h4>

                        <ol>
                            <li>Pilia ang rekord sa hayop.</li>
                            <li>
                                Pilia ang gitipig nga banabana sa timbang, o ibilin
                                ang gipili sa
                                <strong>
                                    Awtomatiko — gamita ang pinakabag-ong banabana
                                </strong>.
                            </li>
                            <li>
                                I-klik ang
                                <strong>Kalkulaha ug Tipigi ang Bili</strong>.
                            </li>
                            <li>
                                Susiha ang gibanabanang patas nga bili sa merkado
                                ug ang mga pag-usab sa presyo.
                            </li>
                        </ol>

                        <h4>
                            Unsaon pagpili sa timbang?
                        </h4>

                        <p>
                            Kon adunay aktuwal nga timbang gikan sa timbangan,
                            mao kini ang unahon. Kung wala, gamiton sa calculator
                            ang gipili o pinakabag-ong banabana
                            sa timbang.
                        </p>

                        <h4>
                            Pagsabot sa resulta
                        </h4>

                        <div class="calc-help-definitions">
                            <div>
                                <strong>
                                    Gigikanan sa timbang
                                </strong>

                                <p>
                                    Nagpakita kon
                                    aktuwal nga timbang o banabana ang gigamit.
                                </p>
                            </div>

                            <div>
                                <strong>
                                    Base nga presyo matag kilo
                                </strong>

                                <p>
                                    Ang opisyal nga reperensiya sa presyo
                                    sa wala pa ang mga adjustment.
                                </p>
                            </div>

                            <div>
                                <strong>
                                    Presyo matag kilo human sa adjustment
                                </strong>

                                <p>
                                    Ang presyo human iaplikar ang giaprobahang
                                    mga adjustment factor.
                                </p>
                            </div>

                            <div>
                                <strong>
                                    Gibanabanang patas nga bili sa merkado
                                </strong>

                                <p>
                                    Ang kinatibuk-ang gibanabanang bili sa
                                    hayop nga gipakita sa piso.
                                </p>
                            </div>
                        </div>

                        <div class="calc-help-equation">
                            <span>
                                KINATIBUK-ANG PORMULA SA PAGKALKULO
                            </span>

                            <strong>
                                Patas nga bili sa merkado =
                                Gigamit nga timbang × Presyo matag kilo human sa adjustment
                            </strong>
                        </div>

                        <div class="calc-help-note">
                            Magdepende ang pagkalkulo sa anaa nga datos sa timbang,
                            magamit nga opisyal nga presyo, ug mga adjustment factor.
                            Kon kulang ang gikinahanglang datos, dili makalkulo ang bili.
                        </div>
                    </section>

                    <!-- FPC -->
                    <section data-help-panel="fpc" hidden>
                        <span class="calc-help-kicker">
                            BAHIN 03
                        </span>

                        <figure class="calc-help-illustration">
                            <img
                                src="/images/calculator/fpc-guide.svg"
                                alt="Illustration showing livestock condition and frame assessment"
                                loading="lazy"
                            >

                            <figcaption>
                                Tan-awa ang kondisyon ug porma sa lawas
                                aron masabtan ang mga adjustment factor.
                            </figcaption>
                        </figure>

                        <h3>
                            Pagkalkulo sa Makatarunganong Presyo (FPC-1.1)
                        </h3>

                        <p>
                            Gipatin-aw sa Breakdown sa Fair Price Calculation kon giunsa pagkalkulo
                            ang gitipig nga bili sa hayop.
                        </p>

                        <h4>
                            Unsaon paggamit
                        </h4>

                        <ol>
                            <li>
                                Ablihi ang tab nga
                                <strong>Breakdown sa Fair Price Calculation</strong>.
                            </li>
                            <li>Pilia ang rekord sa hayop.</li>
                            <li>
                                Pilia ang usa sa mga gitipig nga kalkulasyon sa bili.
                            </li>
                            <li>
                                Susiha ang bili, timbang, ug mga hinungdan sa presyo.
                            </li>
                            <li>
                                Ablihi ang kopya sa kalkulasyon kon
                                kinahanglan ang detalyadong datos.
                            </li>
                        </ol>

                        <h4>
                            Mga adjustment factor sa presyo
                        </h4>

                        <div class="calc-help-definitions">
                            <div>
                                <strong>
                                    Lahi sa hayop
                                </strong>

                                <p>
                                    Ang adjustment nga may kalabotan sa
                                    lahi sa hayop.
                                </p>
                            </div>

                            <div>
                                <strong>
                                    Edad
                                </strong>

                                <p>
                                    Ang adjustment nga may kalabotan sa
                                    edad sa hayop.
                                </p>
                            </div>

                            <div>
                                <strong>
                                    Kahimtang sa pagpanganak
                                </strong>

                                <p>
                                    Ang adjustment nga may kalabotan sa
                                    kahimtang sa pagpanganak.
                                </p>
                            </div>

                            <div>
                                <strong>
                                    Porma sa lawas
                                </strong>

                                <p>
                                    Ang adjustment nga may kalabotan sa
                                    klasipikasyon sa porma sa lawas.
                                </p>
                            </div>

                            <div>
                                <strong>
                                    Kondisyon sa lawas
                                </strong>

                                <p>
                                    Ang adjustment nga may kalabotan sa
                                    natala nga kondisyon sa lawas sa hayop.
                                </p>
                            </div>
                        </div>

                        <div class="calc-help-equation">
                            <span>
                                PORMULA SA PRESYO SA FPC-1.1
                            </span>

                            <strong>
                                Adjusted Price/kg = Base Price/kg ×
                                Lahi sa hayop × Edad × Kahimtang sa pagpanganak ×
                                Porma sa lawas × Kondisyon sa lawas
                            </strong>
                        </div>

                        <div class="calc-help-equation">
                            <span>
                                KATAPUSANG BILI
                            </span>

                            <strong>
                                Patas nga bili sa merkado =
                                Gigamit nga timbang × Presyo matag kilo human sa adjustment
                            </strong>
                        </div>

                        <h4>
                            Unsa ang kopya sa kalkulasyon?
                        </h4>

                        <p>
                            Naglangkob kini sa gitipig nga mga input ug
                            resulta aron masusi ang
                            impormasyon nga gigamit sa pagkalkulo sa bili.
                        </p>

                        <div class="calc-help-note">
                            Gipakita sa Breakdown sa Fair Price Calculation ang ebidensiya sa
                            gitipig nga pagkalkulo. Dili kini mopuli sa opisyal
                            nga proseso sa pag-validate sa presyo.
                        </div>
                    </section>
                </div>
            </div>

            <footer class="calc-help-footer">
                <span>
                    AgriTimbang · Giya sa Calculator
                </span>

                <button
                    type="button"
                    class="calc-help-done"
                    data-help-close
                >
                    Got it
                </button>
            </footer>
        </div>
    </div>
@endsection

@vite([
    'resources/css/calculator.css',
    'resources/js/pages/calculator.js'
])