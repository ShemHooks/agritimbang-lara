@extends('layouts.app')

@section('title', 'Price Reference Form')

@section('content')
    <div class="price-form-page" id="price-form-page" data-price-id="{{ request()->route('priceReference') ?? '' }}">

        <header class="price-form-header">
            <div>
                <p class="price-form-eyebrow">AGRITIMBANG / WEEKLY PRICES</p>
                <h1 id="price-form-title">Create Price Reference</h1>
                <p id="price-form-description">
                    Register an official livestock price proposal for MAO review.
                </p>
            </div>

            <a href="{{ route('prices.index') }}" class="price-form-back">
                Back to Prices
            </a>
        </header>

        <div id="price-form-error" class="price-form-alert price-form-alert-error" role="alert" hidden></div>

        <div id="price-form-success" class="price-form-alert price-form-alert-success" role="status" hidden></div>

        <section class="price-form-card">
            <div class="price-form-card-header">
                <h2>Price Information</h2>
                <p>
                    The reference will be saved as a draft.
                    You can submit it for review afterward.
                </p>
            </div>

            <form id="price-reference-form" novalidate>
                <div class="price-form-grid">

                    <label class="price-form-field">
                        <span>Species <em>*</em></span>
                        <select id="price-species" name="species_id" required>
                            <option value="">Select species</option>
                        </select>
                        <small data-error-for="species_id"></small>
                    </label>


                    <!-- Weekly Price Recommendation -->
                    <div class="price-form-full price-species-heading">
                        <!-- <div>
                                    <label for="price-species">Species <em>*</em></label>
                                </div> -->

                        <button type="button" id="open-price-recommendations" class="price-view-recommendations">
                            <span aria-hidden="true">↗</span>
                            View Recommended Prices
                        </button>
                    </div>

                    {{-- <div class="price-form-field price-form-full">
                        <select id="price-species" name="species_id" required>
                            <option value="">Select species</option>
                        </select>
                        <small data-error-for="species_id"></small>
                    </div> --}}


                    <label class="price-form-field">
                        <span>Breed</span>
                        <select id="price-breed" name="breed_id" disabled>
                            <option value="">All breeds</option>
                        </select>
                        <small data-error-for="breed_id"></small>
                    </label>

                    <label class="price-form-field">
                        <span>Barangay</span>
                        <select id="price-barangay" name="barangay_id">
                            <option value="">Municipality-wide</option>
                        </select>
                        <small data-error-for="barangay_id"></small>
                    </label>

                    <label class="price-form-field">
                        <span>Sale Purpose <em>*</em></span>
                        <select id="price-purpose" name="sale_purpose" required>
                            <option value="">Select purpose</option>
                            <option value="slaughter">Slaughter</option>
                            <option value="breeding">Breeding</option>
                            <option value="fattening">Fattening</option>
                            <option value="work">Work</option>
                        </select>
                        <small data-error-for="sale_purpose"></small>
                    </label>

                    <label class="price-form-field">
                        <span>Price per Kilogram (PHP) <em>*</em></span>
                        <input id="price-amount" name="price_per_kg" type="number" min="0.01" step="0.01"
                            placeholder="e.g. 195.00" required>
                        <small data-error-for="price_per_kg"></small>
                    </label>

                    <label class="price-form-field">
                        <span>Effective From <em>*</em></span>
                        <input id="price-effective-from" name="effective_from" type="date" required>
                        <small data-error-for="effective_from"></small>
                    </label>

                    <label class="price-form-field">
                        <span>Effective To</span>
                        <input id="price-effective-to" name="effective_to" type="date">
                        <small data-error-for="effective_to"></small>
                    </label>

                    <label class="price-form-field">
                        <span>Price Source <em>*</em></span>
                        <input id="price-source" name="source" type="text" maxlength="255"
                            placeholder="e.g. MAO Weekly Market Survey" required>
                        <small data-error-for="source"></small>
                    </label>

                    <label class="price-form-field price-form-full">
                        <span>Remarks</span>
                        <textarea id="price-remarks" name="remarks" rows="4" maxlength="1000"
                            placeholder="Additional notes about the price reference..."></textarea>
                        <small data-error-for="remarks"></small>
                    </label>

                </div>

                <footer class="price-form-actions">
                    <a href="{{ route('prices.index') }}" class="price-form-cancel">
                        Cancel
                    </a>

                    <button type="submit" id="price-form-submit" class="price-form-primary">
                        Save Draft
                    </button>
                </footer>
            </form>
        </section>

        <div id="price-recommendations-modal" class="price-modal" role="dialog" aria-modal="true"
            aria-labelledby="price-modal-title" aria-hidden="true" hidden>
            <div class="price-modal-backdrop" data-close-price-modal></div>

            <div class="price-modal-dialog">
                <div class="price-modal-header">
                    <div>
                        <span class="price-modal-eyebrow">AGRITIMBANG MARKET INSIGHTS</span>
                        <h2 id="price-modal-title">Recommended Livestock Prices</h2>
                        <p id="price-modal-period">
                            Previous completed week
                        </p>
                    </div>

                    <button type="button" id="close-price-recommendations" class="price-modal-close"
                        aria-label="Close recommendations">
                        &times;
                    </button>
                </div>

                <div class="price-modal-body">
                    <div class="price-modal-summary">
                        <div>
                            <span>Species with data</span>
                            <strong id="price-modal-species-count">—</strong>
                        </div>

                        <div>
                            <span>Livestock sold</span>
                            <strong id="price-modal-livestock-count">—</strong>
                        </div>
                    </div>

                    <div class="price-modal-search">
                        <input type="search" id="price-modal-search" placeholder="Search livestock species..."
                            autocomplete="off">
                    </div>

                    <div id="price-modal-loading" class="price-modal-state" hidden>
                        Loading verified transaction prices...
                    </div>

                    <div id="price-modal-error" class="price-modal-error" hidden></div>

                    <div id="price-modal-list" class="price-modal-list"></div>

                    <p class="price-modal-disclaimer">
                        Recommendations are calculated from verified livestock
                        transactions. These are reference values only and do not
                        replace the official price validation process.
                    </p>
                </div>

                <div class="price-modal-footer">
                    <button type="button" id="price-modal-done">
                        Done
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    @vite([
        'resources/css/price-form.css',
        'resources/js/pages/price-form.js'
    ])
@endpush