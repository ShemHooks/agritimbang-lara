@extends('layouts.app')

@section('content')
    <div class="auction-page" id="auction-page">
        <header class="auction-header">
            <div>
                <h1>Auction House Management</h1>
                <p>Manage auction facilities within your municipality.</p>
            </div>

            <button class="auction-btn auction-btn-primary" id="auction-create-btn" type="button">
                + Register Auction House
            </button>
        </header>

        <div id="auction-alert" class="auction-alert" hidden></div>

        <section class="auction-panel">
            <div class="auction-toolbar">
                <input type="search" id="auction-search" placeholder="Search auction houses..."
                    aria-label="Search auction houses">

                <select id="auction-status-filter" aria-label="Filter by status">
                    <option value="all">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="auction-table-wrap">
                <table class="auction-table">
                    <thead>
                        <tr>
                            <th>Auction House</th>
                            <th>Address</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="auction-table-body">
                        <tr>
                            <td colspan="4">Loading auction houses...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="auction-pagination">
                <span id="auction-page-info"></span>

                <div>
                    <button type="button" id="auction-prev" class="auction-btn">Previous</button>

                    <button type="button" id="auction-next" class="auction-btn">Next</button>
                </div>
            </div>
        </section>

        <dialog id="auction-form-dialog" class="auction-dialog">
            <form id="auction-form" novalidate>
                <div class="auction-dialog-header">
                    <h2 id="auction-form-title">Register Auction House</h2>
                    <button type="button" class="auction-close" data-close-dialog="auction-form-dialog"
                        aria-label="Close">×</button>
                </div>

                <div class="auction-dialog-body">
                    <div id="auction-form-error" class="auction-alert" hidden></div>

                    <label for="auction-name">Auction House Name *</label>
                    <input id="auction-name" name="name" maxlength="255" required>
                    <small class="auction-field-error" data-error-for="name"></small>

                    <label for="auction-barangay">Barangay</label>
                    <select id="auction-barangay" name="barangay_id">
                        <option value="">No barangay selected</option>
                    </select>
                    <small class="auction-field-error" data-error-for="barangay_id"></small>

                    <label for="auction-address">Address</label>
                    <input id="auction-address" name="address" maxlength="255">
                    <small class="auction-field-error" data-error-for="address"></small>
                </div>

                <div class="auction-dialog-footer">
                    <button type="button" class="auction-btn" data-close-dialog="auction-form-dialog">
                        Cancel
                    </button>

                    <button type="submit" id="auction-save-btn" class="auction-btn auction-btn-primary">
                        Save Auction House
                    </button>
                </div>
            </form>
        </dialog>

        <dialog id="auction-encoders-dialog" class="auction-dialog">
            <div class="auction-dialog-header">
                <div>
                    <h2>Manage Encoders</h2>
                    <p id="auction-encoder-house-name"></p>
                </div>

                <button type="button" class="auction-close" data-close-dialog="auction-encoders-dialog"
                    aria-label="Close">×</button>
            </div>

            <div class="auction-dialog-body">
                <div id="auction-encoder-error" class="auction-alert is-error" role="alert" hidden></div>

                <label for="auction-encoder-select">
                    Assign LGU Encoder
                </label>

                <select id="auction-encoder-select">
                    <option value="">Select an encoder</option>
                </select>

                <button type="button" id="auction-assign-encoder-btn" class="auction-btn auction-btn-primary">
                    Assign Encoder
                </button>

                <h3>Assigned Encoders</h3>

                <div id="auction-assigned-encoders">
                    Loading...
                </div>
            </div>

            <div class="auction-dialog-footer">
                <button type="button" class="auction-btn" data-close-dialog="auction-encoders-dialog">
                    Close
                </button>
            </div>
        </dialog>
    </div>

    @vite([
        'resources/css/auction-houses.css',
        'resources/js/pages/auction-houses.js'
    ])
@endsection