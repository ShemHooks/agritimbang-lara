@extends('layouts.app')

@section('title', 'Auction Schedule Management')

@push('styles')
    @vite(['resources/css/auction-management.css', 'resources/js/pages/auction-schedules.js'])
@endpush

@section('content')
    <div class="auction-workspace" id="auction-schedule-admin-page">
        <header class="auction-workspace-header">
            <div>
                <span class="auction-eyebrow">LGU ENCODER / AUCTION MANAGEMENT</span>
                <h1>Auction Schedule Management</h1>
                <p>Create and manage livestock auction schedules.</p>
            </div>
            <button type="button" class="auction-primary-button" id="as-create-button">+ Create Auction Schedule</button>
        </header>

        <div class="auction-alert" id="as-alert" role="status" aria-live="polite" hidden></div>
        <div class="auction-alert" id="as-assignment-notice" role="status" aria-live="polite" hidden></div>

        <section class="auction-summary-grid" aria-label="Auction schedule summary">
            <article>
                <span>Total Schedules</span>
                <strong id="as-count-total">—</strong>
            </article>
            <article>
                <span>Published (Current Page)</span>
                <strong id="as-count-published">—</strong>
            </article>
            <article>
                <span>Ongoing (Current Page)</span>
                <strong id="as-count-ongoing">—</strong>
            </article>
            <article>
                <span>Registered Livestock (Current Page)</span>
                <strong id="as-count-registrations">—</strong>
            </article>
        </section>

        <section class="auction-panel">
            <div class="auction-panel-toolbar">
                <div>
                    <h2>Auction Schedules</h2>
                </div>
                <div class="auction-toolbar-controls">
                    <input type="search" id="as-search" placeholder="Search schedules..." aria-label="Search schedules">
                    <select id="as-status-filter" aria-label="Filter by status">
                        <option value="all">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <button type="button" class="auction-secondary-button" id="as-refresh">Refresh</button>
                </div>
            </div>

            <div class="auction-table-scroll">
                <table class="auction-data-table">
                    <thead>
                        <tr>
                            <th>Auction</th>
                            <th>Date & Time</th>
                            <th>Venue</th>
                            <th>Registration Window</th>
                            <th>Livestock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="as-table-body">
                        <tr>
                            <td colspan="7">Loading auction schedules...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="auction-pagination">
                <span id="as-page-info"></span>
                <div>
                    <button type="button" id="as-prev" class="auction-secondary-button">Previous</button>
                    <button type="button" id="as-next" class="auction-secondary-button">Next</button>
                </div>
            </div>
        </section>

        <section class="auction-panel" id="as-detail-panel" hidden>
            <div class="auction-panel-toolbar">
                <div>
                    <h2 id="as-detail-title">Auction Details</h2>
                    <p id="as-detail-subtitle"></p>
                </div>
                <button type="button" class="auction-secondary-button" id="as-close-detail">Close</button>
            </div>
            <div id="as-detail-content"></div>
        </section>

        <dialog class="auction-dialog" id="as-form-dialog">
            <form id="as-form" novalidate>
                <div class="auction-dialog-heading">
                    <div>
                        <span class="auction-eyebrow">AUCTION EVENT</span>
                        <h2 id="as-form-title">Create Auction Schedule</h2>
                    </div>
                    <button type="button" class="auction-dialog-close" data-as-close>×</button>
                </div>

                <div class="auction-dialog-body">
                    <div class="auction-alert is-error" id="as-form-error" role="alert" hidden></div>

                    <label>
                        Title of Auction *
                        <input id="as-title" name="title" maxlength="180" required placeholder="e.g. Barili Weekly Livestock Auction">
                    </label>

                    <div class="auction-form-grid">
                        <label>
                            Auction Date *
                            <input id="as-date" name="auction_date" type="date" required>
                        </label>
                        <label>
                            Start Time *
                            <input id="as-start" name="start_time" type="time" required>
                        </label>
                        <label>
                            Expected End Time *
                            <input id="as-end" name="expected_end_time" type="time" required>
                        </label>
                        <label>
                            Venue / Auction House *
                            <select id="as-venue" name="auction_house_id" required>
                                <option value="">Loading active auction houses...</option>
                            </select>
                        </label>
                        <label>
                            Municipality
                            <input id="as-municipality" type="text" readonly>
                        </label>
                        <label>
                            Barangay
                            <input id="as-barangay" type="text" readonly>
                        </label>
                        <label>
                            Registration Opens *
                            <input id="as-opens" name="registration_opens_at" type="datetime-local" required>
                        </label>
                        <label>
                            Registration Closes *
                            <input id="as-closes" name="registration_closes_at" type="datetime-local" required>
                        </label>
                    </div>

                    <label>
                        Description / Instructions (Optional)
                        <textarea id="as-description" name="description" rows="3" maxlength="3000" placeholder="Additional information for farmers..."></textarea>
                    </label>

                    <p class="auction-form-note">All four livestock species are accepted: Carabao, Cattle, Goat, and Hog.</p>
                </div>

                <div class="auction-dialog-footer">
                    <button type="button" class="auction-secondary-button" data-as-close>Cancel</button>
                    <button type="submit" class="auction-secondary-button" id="as-draft-button" data-as-submit="draft">Save as Draft</button>
                    <button type="submit" class="auction-primary-button" id="as-publish-button" data-as-submit="published">Publish Schedule</button>
                </div>
            </form>
        </dialog>
    </div>
@endsection