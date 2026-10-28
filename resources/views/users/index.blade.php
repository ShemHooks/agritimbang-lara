@extends('layouts.app')

@section('title', 'User Management')

@push('styles')
    @vite([
        'resources/css/admin-users.css',
        'resources/js/pages/admin-users.js'
    ])
@endpush

@section('content')
    <section class="welcome">
        <div>
            <span class="eyebrow">SYSTEM ADMINISTRATION</span>
            <h1>User <span class="welcome-dot">Management.</span></h1>
            <p>Manage accounts, LGU assignments, and farmer identity verification across AgriTimbang.</p>
        </div>
        <span class="welcome-ornament" aria-hidden="true">✳</span>
    </section>

    <section class="um-heading">
        <div>
            <h2>Account Administration</h2>
            <p>Centralized oversight of all registered platform users.</p>
        </div>
        <div class="um-actions">
            <button id="um-refresh" class="um-btn-secondary" type="button">Refresh</button>
            <button id="um-create" class="um-btn" type="button">+ Create LGU Authority</button>
        </div>
    </section>

    <section class="um-stats">
        <article class="um-stat">
            <span>Total Users</span>
            <strong id="um-total">—</strong>
            <small>Registered accounts</small>
        </article>
        <article class="um-stat">
            <span>Active Accounts</span>
            <strong id="um-active">—</strong>
            <small>Currently active</small>
        </article>
        <article class="um-stat">
            <span>Pending Verification</span>
            <strong id="um-pending">—</strong>
            <small>Farmer applications awaiting review</small>
        </article>
        <article class="um-stat">
            <span>Suspended Accounts</span>
            <strong id="um-suspended">—</strong>
            <small>Restricted accounts</small>
        </article>
    </section>

    <section class="um-panel">
        <div class="um-tabs" role="tablist" aria-label="User categories">
            <button class="um-tab active" data-tab="all" type="button">All Users</button>
            <button class="um-tab" data-tab="lgu_authority" type="button">LGU Authorities</button>
            <button class="um-tab" data-tab="lgu_encoder" type="button">LGU Encoders</button>
            <button class="um-tab" data-tab="farmer" type="button">Farmers</button>
            <button class="um-tab" data-tab="verification" type="button">Verification</button>
        </div>

        <div class="um-panel-heading">
            <div>
                <h3 id="um-section-title">All User Accounts</h3>
                <p id="um-section-description">Browse registered platform accounts.</p>
            </div>
        </div>

        <div class="um-filters">
            <div class="um-filter um-search">
                <label for="um-search">Search User</label>
                <input id="um-search" type="search" placeholder="Name, email, farmer code..." autocomplete="off">
            </div>
            <div class="um-filter">
                <label for="um-status">Account Status</label>
                <select id="um-status">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
            <div class="um-filter">
                <label for="um-municipality">Municipality</label>
                <select id="um-municipality">
                    <option value="">All municipalities</option>
                </select>
            </div>
            <div class="um-filter">
                <label for="um-verification">Verification</label>
                <select id="um-verification">
                    <option value="">All statuses</option>
                    <option value="unverified">Unverified</option>
                    <option value="pending">Pending</option>
                    <option value="verified">Verified</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <button id="um-reset" class="um-btn-secondary" type="button">Reset Filters</button>
        </div>
    </section>

    <section class="um-panel">
        <div class="um-panel-heading">
            <div>
                <h3 id="um-table-title">User Records</h3>
                <p id="um-result-count">Loading records...</p>
            </div>
        </div>

        <div id="um-loading" class="um-notice" role="status" hidden>Loading user records...</div>
        <div id="um-error" class="um-notice um-error" role="alert" hidden></div>

        <div class="um-table-scroll">
            <table class="um-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Municipality</th>
                        <th>Verification</th>
                        <th>Account</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="um-table-body">
                    <tr>
                        <td colspan="6" class="um-empty">Loading records...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="um-pagination">
            <span id="um-page-info">—</span>
            <div>
                <button id="um-prev" type="button" disabled>Previous</button>
                <button id="um-next" type="button" disabled>Next</button>
            </div>
        </div>
    </section>

    <div class="um-modal-backdrop" id="um-modal" hidden>
        <section class="um-modal" role="dialog" aria-modal="true" aria-labelledby="um-modal-title">
            <div class="um-modal-header">
                <div>
                    <span class="eyebrow">USER MANAGEMENT</span>
                    <h2 id="um-modal-title">Account Details</h2>
                </div>
                <button id="um-modal-close" class="um-close" type="button" aria-label="Close">×</button>
            </div>
            <div id="um-modal-content"></div>
        </section>
    </div>
@endsection