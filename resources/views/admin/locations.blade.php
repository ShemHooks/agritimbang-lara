@extends('layouts.app')

@section('title', 'Location Management')

@push('styles')
    @vite('resources/css/location-management.css')
@endpush

@section('content')
    <div class="location-page">
        <header class="location-heading">
            <div>
                <p class="location-eyebrow">SYSTEM ADMINISTRATION / REFERENCE DATA</p>
                <h1>Location Management</h1>
                <p>Manage the availability of municipalities and barangays in AgriTimbang.</p>
            </div>
            <button type="button" id="location-refresh" class="location-btn">
                <i data-lucide="refresh-cw"></i> Refresh
            </button>
        </header>

        <div id="location-alert" class="location-alert" role="alert" hidden></div>

        <div class="location-stats">
            <article class="location-stat">
                <span>Total Records</span>
                <strong id="location-total">—</strong>
            </article>
            <article class="location-stat">
                <span>Active</span>
                <strong id="location-active">—</strong>
            </article>
            <article class="location-stat">
                <span>Inactive</span>
                <strong id="location-inactive">—</strong>
            </article>
        </div>

        <section class="location-card">
            <div class="location-toolbar">
                <div>
                    <h2>Geographic Reference Directory</h2>
                    <p>Manage existing PSGC dataset records without deleting them.</p>
                </div>
            </div>

            <div class="location-tabs" role="tablist" aria-label="Location type">
                <button type="button" class="location-tab is-active" id="location-municipalities-tab"
                    data-location-tab="municipalities" role="tab" aria-selected="true">
                    Municipalities
                </button>
                <button type="button" class="location-tab" id="location-barangays-tab" data-location-tab="barangays"
                    role="tab" aria-selected="false">
                    Barangays
                </button>
            </div>

            <div class="location-filters">
                <input type="search" id="location-search" placeholder="Search name, province or PSGC code..."
                    aria-label="Search locations">

                <select id="location-municipality" aria-label="Select municipality" hidden>
                    <option value="">All municipalities</option>
                </select>

                <select id="location-status" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="location-table-wrap">
                <table class="location-table">
                    <thead id="location-head">
                        <tr>
                            <th>Municipality</th>
                            <th>Province</th>
                            <th>PSGC Code</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="location-rows">
                        <tr>
                            <td colspan="5" class="location-empty">Loading locations...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <footer class="location-pagination">
                <span id="location-count">—</span>
                <div>
                    <button type="button" id="location-prev" class="location-btn">Previous</button>
                    <span id="location-page-label">Page 1</span>
                    <button type="button" id="location-next" class="location-btn">Next</button>
                </div>
            </footer>
        </section>
    </div>

    <dialog id="location-confirm" class="location-dialog">
        <form method="dialog">
            <h2 id="location-confirm-title">Confirm status change</h2>
            <p id="location-confirm-message"></p>
            <div class="location-dialog-actions">
                <button type="submit" value="cancel" class="location-btn">Cancel</button>
                <button type="submit" value="confirm" id="location-confirm-submit"
                    class="location-btn location-primary">Confirm</button>
            </div>
        </form>
    </dialog>
@endsection

@push('script')
    @vite('resources/js/pages/location-management.js')
@endpush