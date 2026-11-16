@extends('layouts.app')

@push('styles')
    @vite([
        'resources/css/livestock-register.css',
        'resources/js/pages/livestock-register.js'
    ])
@endpush

@section('content')
    <div class="lr-page">
        <div class="lr-hero">
            <div>
                <span class="lr-eyebrow">FARMER WORKSPACE / LIVESTOCK</span>
                <h1>Magparehistro og Hayop<span>.</span></h1>
                <p>Register your animal so you can keep its details and estimated value in one place.</p>
            </div>
        </div>

        <div class="lr-heading">
            <div>
                <h2>Hayop nga Irehistro</h2>
                <p>Please fill in the details below. Ask your LGU Encoder for help if needed.</p>
            </div>
            <a href="/livestock" class="lr-btn lr-btn-outline">
                Back to Livestock
            </a>
        </div>

        <div id="lr-page-error" class="lr-alert lr-alert-error" role="alert" hidden></div>
        <div id="lr-page-success" class="lr-alert lr-alert-success" role="status" hidden></div>
        <section id="lr-calculator-summary" class="lr-calculator-summary" hidden aria-live="polite">
            <h3>Weight Estimation from Calculator</h3>
            <p id="lr-calculator-summary-weight"></p>
            <label class="lr-field lr-calculator-estimated-weight-field">
                <span>Calculated Estimated Weight (kg)</span>
                <input id="lr-calculator-estimated-weight" type="number" readonly aria-readonly="true" placeholder="Transferred from Weight Estimation">
                <small>This is the calculated estimate, not an actual scale measurement.</small>
            </label>
            <div id="lr-calculator-summary-measurements"></div>
            <small id="lr-calculator-summary-method"></small>
        </section>

        <form id="lr-form" class="lr-form">
            <section class="lr-card">
                <div class="lr-section-header">
                    <span class="lr-step">01</span>
                    <div>
                        <h3>Livestock Classification</h3>
                        <p>Identify the species, breed, sex, and age.</p>
                    </div>
                </div>

                <div class="lr-grid">
                    <label class="lr-field">
                        <span>Species <b>*</b></span>
                        <select name="species_id" id="lr-species" required>
                            <option value="">Loading species...</option>
                        </select>
                        <small class="lr-field-error" data-error-for="species_id"></small>
                    </label>

                    <label class="lr-field">
                        <span>Breed</span>
                        <select name="breed_id" id="lr-breed" disabled>
                            <option value="">Select a species first</option>
                        </select>
                        <small class="lr-field-error" data-error-for="breed_id"></small>
                    </label>

                    <label class="lr-field">
                        <span>Sex <b>*</b></span>
                        <select name="sex" id="lr-sex" required>
                            <option value="">Select sex</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="unknown">Unknown</option>
                        </select>
                        <small class="lr-field-error" data-error-for="sex"></small>
                    </label>

                    <label class="lr-field">
                        <span>Age (Months)</span>
                        <input name="age_months" type="number" min="0" max="600" step="1" placeholder="e.g. 24">
                        <small>Optional. Age group is calculated automatically.</small>
                        <small class="lr-field-error" data-error-for="age_months"></small>
                    </label>
                </div>
            </section>

            <section class="lr-card">
                <div class="lr-section-header">
                    <span class="lr-step">02</span>
                    <div>
                        <h3>Impormasyon sa Pagpanganak</h3>
                        <p>Pilia ang angay nga kahimtang sa pagpanganak sa imong hayop.</p>
                    </div>
                </div>

                <div class="lr-grid">
                    <label class="lr-field">
                        <span><strong>Kahimtang sa pagpanganak</strong></span>
                        <select name="reproductive_status" id="lr-reproductive">
                            <option value="unknown">Wala mahibal-i</option>
                            <option value="has_given_birth">Nakapanganak na</option>
                            <option value="never_given_birth">Wala pa makapanganak</option>
                            <option value="not_applicable">Dili magamit</option>
                        </select>
                        <small class="lr-field-error" data-error-for="reproductive_status"></small>
                    </label>

                    <label class="lr-field">
                        <span><strong>Kadaghan sa pagpanganak</strong></span>
                        <input name="parity" id="lr-parity" type="number" min="0" step="1" placeholder="Pila na ka beses nanganak">
                        <small id="lr-parity-hint">Para lamang kini sa baye nga hayop.</small>
                        <small class="lr-field-error" data-error-for="parity"></small>
                    </label>
                </div>
            </section>

            <section class="lr-card">
                <div class="lr-section-header">
                    <span class="lr-step">03</span>
                    <div>
                        <h3>Purpose & Condition</h3>
                        <p>Specify the livestock's intended use and condition.</p>
                    </div>
                </div>

                <div class="lr-grid">
                    <label class="lr-field">
                        <span>Sale Purpose <b>*</b></span>
                        <select name="sale_purpose" required>
                            <option value="">Select purpose</option>
                            <option value="slaughter">Slaughter</option>
                            <option value="breeding">Breeding</option>
                            <option value="fattening">Fattening</option>
                            <option value="work">Work</option>
                        </select>
                        <small class="lr-field-error" data-error-for="sale_purpose"></small>
                    </label>



                    <label class="lr-field" id="lr-estimated-weight-field" hidden>
                        <span>Estimated Weight (kg) · Calculator</span>
                        <input id="lr-estimated-weight" type="number" readonly aria-readonly="true" placeholder="Calculated weight appears here">
                        <small>Calculator result only. It is kept separate from the actual scale-measured weight.</small>
                    </label>

                    <label class="lr-field">
                        <span>Actual Weight (kg) · Weighing scale</span>
                        <input name="actual_weight_kg" type="number" min="0.01" max="10000" step="0.01" inputmode="decimal" placeholder="e.g. 125.50">
                        <small>Optional. Enter only the weight measured using a weighing scale. Do not copy the calculator estimate here.</small>
                        <small class="lr-field-error" data-error-for="actual_weight_kg"></small>
                    </label>

                    <label class="lr-field" id="lr-valuation-estimate-field" hidden>
                        <span>Estimated Livestock Value · FPC</span>
                        <input id="lr-valuation-estimated-value" type="text" readonly aria-readonly="true" placeholder="Valuation result appears here">
                        <small id="lr-valuation-estimate-details">Preview result transferred from Livestock Valuation.</small>
                    </label>
                </div>
            </section>


            <section class="lr-card" id="lr-condition-assessment">
                <div class="lr-section-header">
                    <span class="lr-step">04</span>
                    <div>
                        <h3>Checklist sa Kahimtang sa Hayop</h3>
                        <p>
                            Tubaga ang pito ka pangutana aron mahibal-an ang kahimtang sa hayop. English details are shown under each question.
                        </p>
                    </div>
                </div>

                <div id="lr-assessment-questions"></div>

                <div class="lr-assessment-result" aria-live="polite">
                    <div class="lr-assessment-score-block">
                        <span>Assessment Score</span>
                        <h3 id="lr-assessment-score">0 / 14</h3>
                        <p id="lr-assessment-progress">0 of 7 questions answered</p>
                    </div>

                    <div class="lr-assessment-condition-block">
                        <span>Condition Score</span>
                        <h3 id="lr-assessment-label">Awaiting assessment</h3>
                        <p id="lr-assessment-description">Complete all questions to see the result.</p>
                    </div>

                    <div class="lr-assessment-legend" aria-label="Body condition assessment score legend">
                        <h4>Legend · Total Points / Result</h4>
                        <div class="lr-assessment-legend-grid">
                            <div><strong>0–4</strong><span>Poor condition</span></div>
                            <div><strong>5–8</strong><span>Needs attention</span></div>
                            <div><strong>9–11</strong><span>Fair condition</span></div>
                            <div><strong>12–14</strong><span>Good condition</span></div>
                        </div>
                        <small>Maximum: 14 points. Converted to the stored 1–5 condition score.</small>
                    </div>
                </div>

                <small class="lr-field-error" data-error-for="condition_assessment"></small>
            </section>


            <section class="lr-card">
                <div class="lr-section-header">
                    <span class="lr-step">05</span>
                    <div>
                        <h3>Litrato sa Hayop</h3>
                        <p>Upload a clear photo of the animal. This is optional but helps identify your livestock.</p>
                    </div>
                </div>
                <label class="lr-field">
                    <span>Animal photo (JPG, PNG or WEBP, up to 5 MB)</span>
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
                </label>
            </section>

            <div class="lr-actions">
                <button type="reset" class="lr-btn lr-btn-outline">Reset</button>
                <button type="submit" class="lr-btn lr-btn-primary" id="lr-submit">Register Livestock</button>
            </div>
        </form>
    </div>
@endsection