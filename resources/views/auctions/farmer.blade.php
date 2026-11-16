@extends('layouts.app')

@section('title', 'Livestock Auctions')

@push('styles')
    @vite(['resources/css/auction-management.css', 'resources/js/pages/farmer-auctions.js'])
@endpush

@section('content')
    <div class="auction-workspace" id="farmer-auctions-page">
        <header class="auction-workspace-header">
            <div>
                <span class="auction-eyebrow">FARMER WORKSPACE / AUCTIONS</span>
                <h1>Livestock Auctions</h1>
                <p>Find municipal auctions and register your eligible livestock.</p>
            </div>
            <button type="button" class="auction-secondary-button" id="fa-refresh">Refresh</button>
        </header>

        <div class="auction-alert" id="fa-alert" role="status" aria-live="polite" hidden></div>

        <div class="auction-tabs" role="tablist" aria-label="Auction pages">
            <button type="button" class="is-active" data-fa-tab="available" role="tab" aria-selected="true">Available Auctions</button>
            <button type="button" data-fa-tab="registrations" role="tab" aria-selected="false">My Auction Registrations</button>
        </div>

        <section id="fa-available-panel" class="auction-panel">
            <div class="auction-panel-toolbar">
                <div>
                    <h2>Open Registrations</h2>
                    <p>Register your livestock for auctions accepting registrations now.</p>
                </div>
                <span class="auction-count-pill" id="fa-open-count">0 open</span>
            </div>

            <div id="fa-open-list" class="auction-schedule-grid">
                <p class="auction-muted">Loading open auctions...</p>
            </div>

            <div class="auction-panel-toolbar auction-soon-heading">
                <div>
                    <h2>Soon to Open</h2>
                    <p>Published auctions with registration periods that have not started yet.</p>
                </div>
                <span class="auction-count-pill" id="fa-soon-count">0 soon</span>
            </div>

            <div id="fa-soon-list" class="auction-schedule-grid">
                <p class="auction-muted">Loading upcoming registration windows...</p>
            </div>
        </section>

        <section id="fa-registrations-panel" class="auction-panel" hidden>
            <div class="auction-panel-toolbar">
                <div>
                    <h2>My Auction Registrations</h2>
                    <p>Review the livestock you have registered and withdraw before the deadline if needed.</p>
                </div>
            </div>

            <div class="auction-table-scroll">
                <table class="auction-data-table">
                    <thead>
                        <tr>
                            <th>Livestock</th>
                            <th>Auction Schedule</th>
                            <th>Venue</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="fa-registration-body">
                        <tr>
                            <td colspan="6">Loading registrations...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="auction-pagination">
                <span id="fa-registration-page-info"></span>
                <div>
                    <button type="button" class="auction-secondary-button" id="fa-registration-prev">Previous</button>
                    <button type="button" class="auction-secondary-button" id="fa-registration-next">Next</button>
                </div>
            </div>
        </section>
    </div>
@endsection