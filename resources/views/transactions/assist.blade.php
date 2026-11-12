@extends('layouts.app')

@section('title', 'Encode Transaction')

@vite([
    'resources/css/transaction-assist.css',
    'resources/js/pages/transaction-assist.js'
])

@section('content')
    <div class="tx-page" id="transaction-assist-page">
        <header class="tx-header">
            <div>
                <span class="tx-eyebrow">LGU ENCODER WORKSPACE</span>
                <h1>Encode Transaction</h1>
                <p>Record livestock sales on behalf of verified farmers.</p>
            </div>
            <a href="/dashboard" class="tx-back">Back to Dashboard</a>
        </header>

        <div id="tx-notice" class="tx-notice" hidden></div>

        <div class="tx-layout">
            <main class="tx-main">
                <section class="tx-card">
                    <div class="tx-card-heading">
                        <span class="tx-step">01</span>
                        <div>
                            <h2>Select Farmer</h2>
                            <p>Search by farmer name or registration code.</p>
                        </div>
                    </div>

                    <label for="tx-farmer-search">Find a farmer</label>
                    <input id="tx-farmer-search" type="search" placeholder="Search verified farmers..." autocomplete="off">

                    <div id="tx-farmer-results" class="tx-results"></div>
                    <div id="tx-selected-farmer" class="tx-selected" hidden></div>
                </section>

                <section class="tx-card">
                    <div class="tx-card-heading">
                        <span class="tx-step">02</span>
                        <div>
                            <h2>Select Livestock</h2>
                            <p>Choose livestock belonging to the selected farmer.</p>
                        </div>
                    </div>

                    <div id="tx-livestock-list" class="tx-livestock-list">
                        <p class="tx-muted">Select a farmer to view livestock.</p>
                    </div>
                </section>

                <section class="tx-card">
                    <div class="tx-card-heading">
                        <span class="tx-step">03</span>
                        <div>
                            <h2>Transaction Details</h2>
                            <p>Provide the buyer and sale information.</p>
                        </div>
                    </div>

                    <form id="tx-form">
                        <div class="tx-fields">
                            <label>
                                Buyer Name *
                                <input id="tx-buyer-name" required maxlength="255">
                            </label>

                            <label>
                                Buyer Phone *
                                <input id="tx-buyer-phone" required maxlength="30">
                            </label>

                            <label>
                                Transaction Date *
                                <input id="tx-date" type="date" required>
                            </label>

                            <label class="tx-full">
                                Remarks
                                <textarea id="tx-remarks" rows="3" maxlength="2000"></textarea>
                            </label>
                        </div>

                        <h3>Selected Livestock & Selling Prices</h3>
                        <div id="tx-selected-items">
                            <p class="tx-muted">No livestock selected.</p>
                        </div>

                        <div class="tx-actions">
                            <button type="submit" id="tx-save">
                                Save Transaction Draft
                            </button>
                        </div>
                    </form>
                </section>
            </main>

            <aside class="tx-summary">
                <div class="tx-card">
                    <span class="tx-eyebrow">TRANSACTION SUMMARY</span>
                    <h2>Sale Overview</h2>

                    <div class="tx-summary-row">
                        <span>Selected livestock</span>
                        <strong id="tx-count">0</strong>
                    </div>

                    <div class="tx-summary-row">
                        <span>Total reference value</span>
                        <strong id="tx-reference">₱0.00</strong>
                    </div>

                    <div class="tx-summary-row tx-total">
                        <span>Total selling price</span>
                        <strong id="tx-total">₱0.00</strong>
                    </div>

                    <p class="tx-muted">
                        Reference values are based on saved livestock valuations.
                        Actual selling prices may differ.
                    </p>

                    <div id="tx-draft-actions" hidden>
                        <p id="tx-draft-message"></p>
                        <<button type="button" id="tx-submit">
                            Submit for LGU Verification
                            </button>
                    </div>
                </div>
            </aside>
        </div>
    </div>
@endsection