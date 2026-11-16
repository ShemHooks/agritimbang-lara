@extends('layouts.app')

@section('content')
    <main class="l55-page" id="l55-edit" data-id="{{ $livestockId }}">
        <div class="l55-top">
            <a href="{{ url('/livestock/' . $livestockId) }}">
                ← Back to details
            </a>
        </div>

        <header class="l55-header">
            <div>
                <p class="l55-eyebrow">LIVESTOCK MANAGEMENT</p>
                <h1>Edit livestock</h1>
                <p>Update the animal's information.</p>
            </div>
        </header>

        <div id="l55-error" class="l55-error" role="alert" hidden></div>
        <p id="l55-loading">Loading livestock record…</p>

        <form id="l55-form" class="l55-card" hidden novalidate>
            <h2>Animal information</h2>

            <div class="l55-fields">
                <label>
                    Species
                    <select name="species_id" id="l55-species" required></select>
                </label>

                <label>
                    Breed
                    <select name="breed_id" id="l55-breed">
                        <option value="">Unspecified</option>
                    </select>
                </label>

                <label>
                    Sex
                    <select name="sex" id="l55-sex">
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="unknown">Unknown</option>
                    </select>
                </label>

                <label>
                    Age (months)
                    <input type="number" name="age_months" min="0" max="600" step="1" placeholder="Not recorded">
                </label>

                <label>
                    <strong>Kahimtang sa pagpanganak</strong>
                    <select name="reproductive_status" id="l55-reproductive">
                        <option value="unknown">Wala mahibal-i</option>
                        <option value="has_given_birth">Nakapanganak na</option>
                        <option value="never_given_birth">Wala pa makapanganak</option>
                        <option value="not_applicable">Dili magamit</option>
                    </select>
                    <small>Pagpili sa kahimtang sa pagpanganak sa hayop.</small>
                </label>

                <label>
                    <strong>Kadaghan sa pagpanganak</strong>
                    <input type="number" name="parity" id="l55-parity" min="0" step="1" placeholder="Pila na ka beses nanganak">
                    <small id="l55-parity-hint">Para lamang kini sa baye nga hayop.</small>
                </label>

                <label>
                    Sale purpose
                    <select name="sale_purpose">
                        <option value="slaughter">Slaughter</option>
                        <option value="breeding">Breeding</option>
                        <option value="fattening">Fattening</option>
                        <option value="work">Work</option>
                    </select>
                </label>

                <div class="l55-condition-launch">
                    <span><strong>Body condition / Kahimtang sa lawas</strong><small id="l55-condition-result">No new checklist assessment yet.</small></span>
                    <input type="hidden" name="condition_score" id="l55-condition-score" value="">
                    <button type="button" class="l55-primary" id="l55-condition-open">Open Body Condition Checklist</button>
                </div>

                <label>
                    Actual weight (kg)
                    <input type="number" name="actual_weight_kg" min="0.01" step="0.01" placeholder="Not recorded">
                </label>

                <div class="l55-photo-field">
                    <label for="l55-photo"><strong>Livestock photo</strong><small>Upload a clear photo of this animal (JPG, PNG, or WEBP; maximum 5 MB).</small></label>
                    <img id="l55-photo-preview" class="l55-photo-preview" alt="Livestock photo preview" hidden>
                    <input type="file" name="photo" id="l55-photo" accept="image/jpeg,image/png,image/webp">
                </div>
            </div>

            <p class="l55-hint">
                Ownership, municipality, barangay, and transaction status
                cannot be edited here.
            </p>

            <div class="l55-actions">
                <a id="l55-cancel" class="l55-secondary" href="#">Cancel</a>
                <button type="submit" class="l55-primary" id="l55-save">
                    Save changes
                </button>
            </div>
        </form>

        <div id="l55-condition-modal" class="l55-condition-modal" hidden>
            <button type="button" class="l55-condition-backdrop" data-l55-condition-close aria-label="Close checklist"></button>
            <section class="l55-condition-dialog" role="dialog" aria-modal="true" aria-labelledby="l55-condition-title">
                <header>
                    <h2 id="l55-condition-title">Livestock Condition Checklist</h2>
                    <p>Answer all seven questions. The assessment score and 1–5 condition score are calculated automatically.</p>
                </header>
                <div id="l55-condition-questions"></div>
                <div class="l55-condition-summary">
                    <div><span>Assessment Score</span><strong id="l55-condition-total">0/7 answered</strong></div>
                    <div><span>Condition Score</span><strong id="l55-condition-mapped">Awaiting assessment</strong></div>
                    <div class="l55-condition-legend"><strong>Legend · Total Points / Result</strong><span>0–4: Poor condition · 5–8: Needs attention · 9–11: Fair condition · 12–14: Good condition</span></div>
                </div>
                <footer>
                    <button type="button" class="l55-secondary" id="l55-condition-close">Close</button>
                    <button type="button" class="l55-primary" id="l55-condition-apply" disabled>Use Condition Score</button>
                </footer>
            </section>
        </div>
    </main>
@endsection

@vite([
    'resources/css/livestock-details.css',
    'resources/js/pages/livestock-edit.js'
])