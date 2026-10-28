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
                    Reproductive status
                    <select name="reproductive_status" id="l55-reproductive">
                        <option value="has_given_birth">Has given birth</option>
                        <option value="never_given_birth">Never given birth</option>
                        <option value="not_applicable">Not applicable</option>
                        <option value="unknown">Unknown</option>
                    </select>
                </label>

                <label>
                    Parity
                    <input type="number" name="parity" id="l55-parity" min="0" step="1" placeholder="Not applicable">
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

                <label>
                    Condition score (1–5)
                    <select name="condition_score">
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </label>

                <label>
                    Actual weight (kg)
                    <input type="number" name="actual_weight_kg" min="0.01" step="0.01" placeholder="Not recorded">
                </label>
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
    </main>
@endsection

@vite([
    'resources/css/livestock-details.css',
    'resources/js/pages/livestock-edit.js'
])