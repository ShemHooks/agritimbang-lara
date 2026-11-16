@extends('layouts.app')

@section('title', 'Auction Schedule Details')

@push('styles')
    @vite(['resources/css/auction-management.css', 'resources/js/pages/farmer-auction-detail.js'])
@endpush

@section('content')
    <div class="auction-workspace" id="farmer-auction-detail-page" data-schedule-id="{{ $scheduleId }}">
        <header class="auction-workspace-header">
            <div>
                <span class="auction-eyebrow">FARMER WORKSPACE / AUCTIONS</span>
                <h1 id="fad-title">Auction Schedule</h1>
                <p id="fad-subtitle">Loading auction details...</p>
            </div>
            <a href="/auctions" class="auction-secondary-button">Back to Auctions</a>
        </header>

        <div class="auction-alert" id="fad-alert" role="status" aria-live="polite" hidden></div>

        <section class="auction-panel">
            <div class="auction-panel-toolbar">
                <div>
                    <h2>Schedule Information</h2>
                    <p>Review the auction details and registration period.</p>
                </div>
                <span id="fad-status"></span>
            </div>

            <div id="fad-details" class="auction-schedule-details">
                <p class="auction-muted">Loading schedule information...</p>
            </div>

            <div class="auction-detail-actions" id="fad-actions" hidden>
                <a class="auction-primary-button" id="fad-register" href="#">Register Livestock</a>
            </div>

            <p class="auction-muted" id="fad-registration-note"></p>
        </section>
    </div>
@endsection