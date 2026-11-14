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
                <p>
                    Estimate livestock weight, calculate fair market value,
                    and review FPC breakdowns.
                </p>
            </div>

            {{-- Calculator Guideline Button --}}
            <button type="button" id="calc-help-open" class="calc-help-trigger" aria-haspopup="dialog"
                aria-controls="calc-help-modal">
                <span class="calc-help-icon" aria-hidden="true">?</span>
                <span>How to use calculator</span>
            </button>
        </header>


        {{-- =====================================================
        CALCULATOR TABS
        ====================================================== --}}
        <nav class="calc-tabs" aria-label="Calculator features">
            <button id="calc-tab-weight" class="active" type="button" data-calc-tab="weight" aria-selected="true"
                aria-controls="calc-weight-panel">
                Weight Estimation
            </button>

            <button id="calc-tab-valuation" type="button" data-calc-tab="valuation" aria-selected="false"
                aria-controls="calc-valuation-panel">
                Livestock Valuation
            </button>

            <button id="calc-tab-fpc" type="button" data-calc-tab="fpc" aria-selected="false"
                aria-controls="calc-fpc-panel">
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
                        <p>Pwede ka mubutang lang sa detalye. Sukda gamit ang tape measure (sentimetro)..</p>
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
                                <input id="calc-girth" type="number" min="0.01" max="500" step="any"
                                    placeholder="Enter heart girth">
                            </label>

                            <label class="calc-field">
                                <span>Body length (cm)</span>
                                <input id="calc-length" type="number" min="0.01" max="500" step="any"
                                    placeholder="Enter body length">
                            </label>

                            <label class="calc-field">
                                <span>Rump height (cm)</span>
                                <input id="calc-height" type="number" min="0.01" max="300" step="any"
                                    placeholder="Enter rump height">
                            </label>
                        </div>

                        <p class="calc-note">
                            Measurement requirements depend on the formula selected
                            by the backend. Unused measurements may be left blank.
                        </p>

                        <button id="calc-submit" class="calc-primary" type="submit">
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

                        <div id="calc-valuation-animal" class="calc-animal-info" hidden></div>

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

                        <button id="calc-valuation-submit" type="submit" class="calc-primary">
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


<div
    id="calc-help-modal"
    class="calc-help-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="calc-help-title"
    aria-describedby="calc-help-description"
    hidden
>
    <div class="calc-help-backdrop" data-help-close></div>

    <div class="calc-help-dialog" tabindex="-1">
        <header class="calc-help-header">
            <div>
                <p class="calc-help-eyebrow">AGRITIMBANG USER GUIDE</p>
                <h2 id="calc-help-title">Livestock Calculator Guide</h2>
                <p id="calc-help-description">
                    Learn how to estimate livestock weight,
                    calculate fair market value, and understand FPC.
                </p>
            </div>

            <button
                type="button"
                id="calc-help-close"
                class="calc-help-close"
                aria-label="Close calculator guide"
            >&times;</button>
        </header>

        <div class="calc-help-body">

            <nav class="calc-help-nav" aria-label="Help topics">
                <button type="button" class="active"
                    data-help-tab="overview">Overview</button>
                <button type="button"
                    data-help-tab="weight">Weight Estimation</button>
                <button type="button"
                    data-help-tab="valuation">Livestock Valuation</button>
                <button type="button"
                    data-help-tab="fpc">FPC Breakdown</button>
            </nav>

            <div class="calc-help-content">

                <!-- OVERVIEW -->
                <section data-help-panel="overview">
                    <span class="calc-help-kicker">GETTING STARTED</span>
                    <h3>What does the calculator do?</h3>
                    <p>
                        AgriTimbang helps users estimate an animal's
                        live weight, calculate its estimated fair
                        market value, and inspect the pricing factors
                        used in the calculation.
                    </p>

                    <div class="calc-help-steps">
                        <div class="calc-help-step">
                            <span>01</span>
                            <div>
                                <strong>Estimate Weight</strong>
                                <p>
                                    Select a livestock record and enter
                                    the measurements required by its
                                    weight estimation formula.
                                </p>
                            </div>
                        </div>
                        <div class="calc-help-step">
                            <span>02</span>
                            <div>
                                <strong>Calculate Valuation</strong>
                                <p>
                                    Use available weight evidence and
                                    official price references to
                                    estimate market value.
                                </p>
                            </div>
                        </div>
                        <div class="calc-help-step">
                            <span>03</span>
                            <div>
                                <strong>Review FPC Breakdown</strong>
                                <p>
                                    Inspect the weight, official price,
                                    adjustment factors, and saved
                                    calculation details.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="calc-help-note">
                        Calculated values are estimates, not a
                        guarantee of the final livestock selling price.
                    </div>
                </section>

                <!-- WEIGHT -->
                <section data-help-panel="weight" hidden>
                    <span class="calc-help-kicker">FEATURE 01</span>
                    <h3>Weight Estimation</h3>
                    <p>
                        Estimate an animal's live weight using
                        body measurements without requiring a
                        physical weighing scale.
                    </p>

                    <h4>How to use</h4>
                    <ol>
                        <li>Pilia ang rekord sa hayop.</li>
                        <li>Sukda ang hayop gamit ang sukod-sukod.</li>
                        <li>Isulod ang gikinahanglang sukod sa sentimetro.</li>
                        <li>I-klik ang <strong>Kuwenta ug Tipigi ang Banabana</strong>.</li>
                        <li>Tan-awa ang resulta ug ang kasaysayan sa banabana.</li>
                    </ol>

                    <h4>Understanding the measurements</h4>
                        <div class="calc-how-to-grid">
                            <article><img class="measure-guide-image" src="/images/measure-heart-girth.svg" alt="Tape around the chest, just behind the front legs">
                                <h3>1. Heart girth / Libot sa dughan</h3>
                                <p>Ilibot ang tape sa pinakalapad nga bahin sa dughan, luyo sa atubangang tiil. Dili hugot kaayo ug dili luag.</p>
                            </article>
                            <article><img class="measure-guide-image" src="/images/measure-body-length.svg" alt="Measure from the shoulder point to the pin bone">
                                <h3>2. Body length / Gitas-on sa lawas</h3>
                                <p>Sukda gikan sa abaga (shoulder point) hangtod sa bukog sa likod sa hawak (pin bone), subay sa kilid sa hayop.</p>
                            </article>
                            <article><img class="measure-guide-image" src="/images/measure-rump-height.svg" alt="Measure vertically from the ground to the top of the rump">
                                <h3>3. Rump height / Taas sa lubot</h3>
                                <p>Sukda gikan sa yuta hangtod sa pinakataas nga bahin sa hawak o rump. Siguroha nga patag ang baroganan.</p>
                            </article>
                        </div>

                    <h4>Understanding the result</h4>
                    <div class="calc-help-definitions">
                        <div>
                            <strong>Estimated live weight</strong>
                            <p>The calculated weight in kilograms.</p>
                        </div>
                        <div>
                            <strong>Confidence</strong>
                            <p>
                                The confidence indicator returned by
                                the estimation engine.
                            </p>
                        </div>
                        <div>
                            <strong>Frame category</strong>
                            <p>
                                The animal's frame classification
                                returned by the calculation.
                            </p>
                        </div>
                        <div>
                            <strong>Formula and version</strong>
                            <p>
                                Identify the calculation method and
                                version used for the saved result.
                            </p>
                        </div>
                    </div>

                    <div class="calc-help-note">
                        <strong>Timailhan</strong>: Dili tanan nga pamaagi nanginahanglan sa tanang sukod. 
                        Sundon ang mga sukod nga gipangayo sa calculator. 
                        Ang resulta banabana lamang, dili kapuli sa timbangan.
                        </p>

                        <p>
                        <strong>Mga Kinahanglanon sa Pagsukod</strong>: Magkalahi kini base sa pamaagi nga gipili sa sistema. 
                        Ang mga blangko nga dili kinahanglanon sa maong pamaagi mahimong ibilin nga walay sulod. 
                        Aron mas tukma ang banabana, sukda og maayo ang hayop ug siguroa nga husto ang pagbutang sa imong sukod-sukod.
                        </p>
                    </div>
                </section>

                <!-- VALUATION -->
                <section data-help-panel="valuation" hidden>
                    <span class="calc-help-kicker">FEATURE 02</span>
                    <h3>Livestock Valuation</h3>
                    <p>
                        Calculate an estimated fair market value using
                        livestock weight, official price references,
                        and approved pricing adjustment factors.
                    </p>

                    <h4>How to use</h4>
                    <ol>
                        <li>Pilia ang rekord sa hayop.</li>
                        <li>Pilia ang natipig na banabana sa timbang, o ibilin ang pagpili sa <strong>Automatic — use latest estimation</strong>.</li>
                        <li>I-klik ang <strong>Kuwenta ug Tipigi ang Pagtimbang-timbang</strong>.</li>
                        <li>Tan-awa ang gibanabanang patas nga bili sa merkado ug ang mga pagbag-o sa presyo.</li>
                    </ol>

                    <h4>How is the weight selected?</h4>
                    <p>
                        Kung adunay tinuod nga timbang gikan 
                        sa timbangan, kini ang una nga gamiton. 
                        Kung wala, gamiton sa kuwentador ang gipili 
                        nga banabana sa timbang o ang pinakabag-o nga 
                        natipig nga banabana.
                    </p>

                    <h4>Understanding the result</h4>
                    <div class="calc-help-definitions">
                        <div>
                            <strong>Weight source</strong>
                            <p>
                                Shows whether the valuation used
                                actual scale weight or an estimation.
                            </p>
                        </div>
                        <div>
                            <strong>Base price per kilogram</strong>
                            <p>
                                The official price reference used
                                before pricing adjustments.
                            </p>
                        </div>
                        <div>
                            <strong>Adjusted price per kilogram</strong>
                            <p>
                                The price after applying the approved
                                adjustment factors.
                            </p>
                        </div>
                        <div>
                            <strong>Fair market value</strong>
                            <p>
                                The estimated total value of the
                                animal, expressed in Philippine pesos.
                            </p>
                        </div>
                    </div>

                    <div class="calc-help-equation">
                        <span>GENERAL VALUATION FORMULA</span>
                        <strong>
                            Fair Market Value =
                            Weight Used × Adjusted Price/kg
                        </strong>
                    </div>

                    <div class="calc-help-note">
                        Ang pagtimbang-timbang sa bili nagdepende 
                        sa kasaligang timbang nga makuha, sa opisyal 
                        nga presyo nga magamit, ug sa mga hinungdan 
                        sa pagbag-o nga gihatag sa sistema. Kung kulang 
                        ang gikinahanglang impormasyon, dili mahimo a
                        ng pagkuwenta sa bili.
                    </div>
                </section>

                <!-- FPC -->
                <section data-help-panel="fpc" hidden>
                    <span class="calc-help-kicker">FEATURE 03</span>
                    <h3>Fair Price Calculation (FPC-1.1)</h3>
                    <p>
                        FPC Breakdown explains how a saved livestock
                        valuation was calculated.
                    </p>

                    <h4>How to use</h4>
                    <ol>
                        <li>Ablihi ang tab nga <strong>FPC Breakdown</strong>.</li>
                        <li>Pilia ang rekord sa hayop.</li>
                        <li>Pilia ang usa sa mga natipig nga pagtimbang-timbang niini.</li>
                        <li>Tan-awa ang bili, timbang, ug mga hinungdan sa pagtakda sa presyo.</li>
                        <li>Ibukhad ang kuwentahon kung kinahanglan ang detalyadong impormasyon sa pagkuwenta.</li>
                    </ol>

                    <h4>Pricing adjustment factors</h4>
                    <div class="calc-help-definitions">
                        <div>
                            <strong>Breed</strong>
                            <p>
                                The adjustment associated with
                                the animal's breed.
                            </p>
                        </div>
                        <div>
                            <strong>Age</strong>
                            <p>
                                The adjustment associated with
                                the animal's age.
                            </p>
                        </div>
                        <div>
                            <strong>Reproductive</strong>
                            <p>
                                The adjustment associated with
                                reproductive classification.
                            </p>
                        </div>
                        <div>
                            <strong>Frame</strong>
                            <p>
                                The adjustment associated with
                                frame classification.
                            </p>
                        </div>
                        <div>
                            <strong>Condition</strong>
                            <p>
                                The adjustment associated with
                                the animal's recorded condition.
                            </p>
                        </div>
                    </div>

                    <div class="calc-help-equation">
                        <span>FPC-1.1 PRICING FORMULA</span>
                        <strong>
                            Adjusted Price/kg = Base Price/kg ×
                            Breed × Age × Reproductive ×
                            Frame × Condition
                        </strong>
                    </div>

                    <div class="calc-help-equation">
                        <span>FINAL VALUE</span>
                        <strong>
                            Fair Market Value =
                            Weight Used × Adjusted Price/kg
                        </strong>
                    </div>

                    <h4>What is the calculation snapshot?</h4>
                    <p>
                        It contains the saved calculation inputs and
                        results, allowing reviewers to inspect the
                        information used when the valuation was made.
                    </p>

                    <div class="calc-help-note">
                        FPC Breakdown displays saved valuation
                        evidence. It does not replace the official
                        price validation process.
                    </div>
                </section>

            </div>
        </div>

        <footer class="calc-help-footer">
            <span>AgriTimbang · Calculator User Guide</span>
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