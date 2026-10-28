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
                <h1>Register Livestock<span>.</span></h1>
                <p>Add a livestock record to your verified farmer profile.</p>
            </div>
        </div>

        <div class="lr-heading">
            <div>
                <h2>Livestock Registration</h2>
                <p>Complete the information below to register your livestock.</p>
            </div>
            <a href="/livestock" class="lr-btn lr-btn-outline">
                Back to Livestock
            </a>
        </div>

        <div id="lr-page-error" class="lr-alert lr-alert-error" role="alert" hidden></div>
        <div id="lr-page-success" class="lr-alert lr-alert-success" role="status" hidden></div>

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
                        <h3>Reproductive Information</h3>
                        <p>Provide reproductive details when applicable.</p>
                    </div>
                </div>

                <div class="lr-grid">
                    <label class="lr-field">
                        <span>Reproductive Status</span>
                        <select name="reproductive_status" id="lr-reproductive">
                            <option value="unknown">Unknown</option>
                            <option value="has_given_birth">Has Given Birth</option>
                            <option value="never_given_birth">Never Given Birth</option>
                            <option value="not_applicable">Not Applicable</option>
                        </select>
                        <small class="lr-field-error" data-error-for="reproductive_status"></small>
                    </label>

                    <label class="lr-field">
                        <span>Parity</span>
                        <input name="parity" id="lr-parity" type="number" min="0" step="1" placeholder="Number of births">
                        <small id="lr-parity-hint">Applicable to female livestock.</small>
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

                    <label class="lr-field">
                        <span>Condition Score <b>*</b></span>
                        <select name="condition_score" required>
                            <option value="">Select score</option>
                            <option value="1">1 — Very Poor</option>
                            <option value="2">2 — Poor</option>
                            <option value="3">3 — Moderate</option>
                            <option value="4">4 — Good</option>
                            <option value="5">5 — Excellent</option>
                        </select>
                        <small class="lr-field-error" data-error-for="condition_score"></small>
                    </label>

                    <label class="lr-field">
                        <span>Actual Weight (kg)</span>
                        <input name="actual_weight_kg" type="number" min="0.01" step="0.01" placeholder="e.g. 125.50">
                        <small>Optional. Enter a measured weight, if available.</small>
                        <small class="lr-field-error" data-error-for="actual_weight_kg"></small>
                    </label>
                </div>
            </section>

            <div class="lr-footer">
                <p>
                    Ownership, municipality, barangay, age group, and
                    initial status are assigned by the system.
                </p>

                <div class="lr-actions">
                    <button type="reset" class="lr-btn lr-btn-outline">
                        Reset
                    </button>
                    <button type="submit" class="lr-btn lr-btn-primary" id="lr-submit">
                        Register Livestock
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection