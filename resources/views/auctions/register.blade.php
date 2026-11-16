@extends('layouts.app')

@section('title', 'Register Livestock for Auction')

@push('styles')
    @vite(['resources/css/auction-management.css', 'resources/js/pages/farmer-auction-register.js'])
@endpush

@section('content')
    <div class="auction-workspace" id="farmer-auction-register-page" data-schedule-id="{{ $scheduleId }}">
        <header class="auction-workspace-header">
            <div>
                <span class="auction-eyebrow">FARMER WORKSPACE / AUCTIONS</span>
                <h1>Register Livestock</h1>
                <p id="far-title">Loading auction information...</p>
            </div>
            <a id="far-back" href="/auctions" class="auction-secondary-button">Back to Schedule</a>
        </header>

        <div class="auction-alert" id="far-alert" role="status" aria-live="polite" hidden></div>

        <section class="auction-panel">
            <div class="auction-panel-toolbar">
                <div>
                    <h2>Select Eligible Livestock</h2>
                    <p>Choose the animals you intend to bring to this auction. Use the photos to confirm each animal.</p>
                </div>
                <span class="auction-count-pill" id="far-count">0 eligible</span>
            </div>

            <div id="far-list" class="auction-livestock-grid">
                <p class="auction-muted">Loading your eligible livestock...</p>
            </div>

            <div class="auction-register-footer">
                <p class="auction-muted" id="far-help">You may select more than one livestock record.</p>
                <button type="button" class="auction-primary-button" id="far-submit" disabled>Register Selected Livestock</button>
            </div>
        </section>
    </div>
@endsection