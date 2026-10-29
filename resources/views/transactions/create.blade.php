@extends('layouts.app')

@section('title', 'Record Private Sale')

@push('styles')
    @vite('resources/css/transaction-create.css')
@endpush

@section('content')
    <div class="sale-page" id="sale-page">
        <div class="sale-heading">
            <div>
                <p class="sale-eyebrow">FARMER TRANSACTIONS</p>
                <h1>Record Private Sale</h1>
                <p>Record livestock sold outside an auction house.</p>
            </div>
            <a href="/livestock" class="sale-back">My Livestock</a>
        </div>

        <div class="sale-notice">
            Private transactions are recorded by farmers.
            Auction-house transactions must be recorded by an assigned LGU encoder.
        </div>

        <div id="sale-alert" class="sale-alert" hidden></div>

        <form id="sale-form">
            <section class="sale-card">
                <h2>Buyer Information</h2>

                <div class="sale-grid">
                    <label>
                        Buyer's full name
                        <input name="buyer_name" required maxlength="255" placeholder="Enter full name">
                    </label>

                    <label>
                        Mobile number
                        <input name="buyer_phone" required maxlength="30" placeholder="09XXXXXXXXX">
                        <small>Confirmation link will be sent via SMS.</small>
                    </label>

                    <label>
                        Transaction date
                        <input name="transaction_date" type="date" required>
                    </label>
                </div>
            </section>

            <section class="sale-card">
                <div class="sale-section-heading">
                    <div>
                        <h2>Livestock Items</h2>
                        <p>Select registered livestock and its saved valuation.</p>
                    </div>

                    <button type="button" id="add-item" class="sale-btn sale-btn-outline">
                        + Add Livestock
                    </button>
                </div>

                <div id="sale-items"></div>

                <div class="sale-total">
                    <span>Total Selling Price</span>
                    <strong id="sale-total">₱0.00</strong>
                </div>
            </section>

            <section class="sale-card">
                <h2>Additional Information</h2>
                <label>
                    Remarks
                    <textarea name="remarks" rows="4" maxlength="2000" placeholder="Optional transaction notes"></textarea>
                </label>
            </section>

            <div class="sale-actions">
                <button type="button" id="save-draft" class="sale-btn sale-btn-outline">
                    Save Draft
                </button>

                <button type="submit" id="submit-sale" class="sale-btn sale-btn-primary">
                    Submit & Send SMS
                </button>
            </div>
        </form>
    </div>
@endsection


@vite('resources/js/pages/transaction-create.js')