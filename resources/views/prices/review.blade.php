@extends('layouts.app')

@section('title', 'Price Review Details')

@section('content')
    <div class="price-review-page" id="price-review-page" data-price-id="{{ request()->route('priceReference') ?? '' }}">
        <header class="price-review-header">
            <div>
                <p class="price-review-eyebrow">
                    AGRITIMBANG / PRICE VALIDATION
                </p>

                <h1>Price Review Details</h1>

                <p>
                    Examine the submitted livestock price reference
                    before making an approval decision.
                </p>
            </div>

            <a href="{{ route('prices.approvals') }}" class="price-review-secondary">
                Back to Approval Queue
            </a>
        </header>

        <div id="review-error" class="price-review-alert price-review-alert-error" role="alert" hidden></div>

        <div id="review-success" class="price-review-alert price-review-alert-success" role="status" hidden></div>

        <div id="review-loading" class="price-review-loading">
            Loading price reference...
        </div>

        <div id="review-content" hidden>

            <section class="price-review-card">
                <div class="price-review-card-header">
                    <div>
                        <h2>Reference Information</h2>
                        <p>Submitted price and applicable locality.</p>
                    </div>

                    <span id="review-status" class="price-review-status">
                        —
                    </span>
                </div>

                <div id="review-details" class="price-review-detail-grid"></div>
            </section>

            <section class="price-review-card">
                <div class="price-review-card-header">
                    <div>
                        <h2>Submission & Accountability</h2>
                        <p>
                            Identify the responsible encoder and
                            track the reference's review history.
                        </p>
                    </div>
                </div>

                <div id="review-accountability" class="price-review-detail-grid"></div>
            </section>

            <section class="price-review-card">
                <div class="price-review-card-header">
                    <div>
                        <h2>Validation Timeline</h2>
                        <p>
                            Recorded submission, approval,
                            and rejection actions.
                        </p>
                    </div>
                </div>

                <div id="review-validation-list" class="price-review-validation-list"></div>
            </section>

            <section id="review-decision-card" class="price-review-card" hidden>

                <div class="price-review-card-header">
                    <div>
                        <h2>Review Decision</h2>
                        <p>
                            Approve the reference or reject it
                            with a documented explanation.
                        </p>
                    </div>
                </div>

                <div class="price-review-decision">

                    <label class="price-review-field">
                        <span>Review Remarks</span>

                        <textarea id="review-remarks" rows="4" maxlength="1000"
                            placeholder="Enter your review remarks or reason for rejection..."></textarea>

                        <small id="review-remarks-error"></small>
                    </label>

                    <div class="price-review-actions">
                        <button id="review-reject" type="button" class="price-review-danger">
                            Reject Reference
                        </button>

                        <button id="review-approve" type="button" class="price-review-primary">
                            Approve Reference
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection

@push('styles')
    @vite([
        'resources/css/price-review.css',
        'resources/js/pages/price-review.js'
    ])
@endpush