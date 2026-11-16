@extends('layouts.app')

@section('title', 'Auction Registrations')

@push('styles')
    @vite([
        'resources/css/auction-management.css', 
        'resources/js/pages/auction-registrations.js'
    ])
@endpush

@section('content')
    <div class="auction-workspace" id="auction-registrations-page" data-schedule-id="{{ $scheduleId }}">
        <header class="auction-workspace-header">
            <div>
                <span class="auction-eyebrow">LGU ENCODER / AUCTION MANAGEMENT</span>
                <h1 id="ar-title">Auction Registrations</h1>
                <p id="ar-subtitle">Loading schedule information...</p>
            </div>
            <a class="auction-action-button" href="/lgu/auction-schedules">Back to Auction Schedules</a>
        </header>

        <div class="auction-alert" id="ar-alert" role="status" aria-live="polite" hidden></div>

        <section class="auction-summary-grid" aria-label="Registration summary">
            <article>
                <span>Total Registrations</span>
                <strong id="ar-total">—</strong>
            </article>
            <article>
                <span>Registered</span>
                <strong id="ar-registered">—</strong>
            </article>
            <article>
                <span>Withdrawn</span>
                <strong id="ar-withdrawn">—</strong>
            </article>
            <article>
                <span>Completed</span>
                <strong id="ar-completed">—</strong>
            </article>
        </section>

        <section class="auction-panel">
            <div class="auction-panel-toolbar">
                <div>
                    <h2>Registered Livestock</h2>
                </div>
                <button type="button" class="auction-secondary-button" id="ar-refresh">Refresh</button>
            </div>

            <div class="auction-table-scroll">
                <table class="auction-data-table">
                    <thead>
                        <tr>
                            <th>Farmer</th>
                            <th>Contact</th>
                            <th>Livestock</th>
                            <th>Sex</th>
                            <th>Sale Purpose</th>
                            <th>Registered At</th>
                            <th>Registered By</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="ar-body">
                        <tr>
                            <td colspan="9">Loading registrations...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="auction-pagination">
                <span id="ar-page-info"></span>
                <div>
                    <button type="button" id="ar-prev" class="auction-secondary-button">Previous</button>
                    <button type="button" id="ar-next" class="auction-secondary-button">Next</button>
                </div>
            </div>
        </section>

        <dialog class="auction-dialog" id="ar-livestock-dialog" aria-labelledby="ar-livestock-dialog-title">
            <form method="dialog">
                <div class="auction-dialog-heading">
                    <div><span class="auction-eyebrow">REGISTERED LIVESTOCK</span><h2 id="ar-livestock-dialog-title">Livestock Details</h2></div>
                    <button type="button" class="auction-dialog-close" id="ar-livestock-close" aria-label="Close">&times;</button>
                </div>
                <div class="auction-dialog-body" id="ar-livestock-details"><p>Loading livestock details...</p></div>
                <div class="auction-dialog-footer"><button type="button" class="auction-secondary-button" id="ar-livestock-close-footer">Close</button></div>
            </form>
        </dialog>
    </div>
@endsection