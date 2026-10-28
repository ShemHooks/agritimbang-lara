@extends('layouts.app')

@push('styles')
    @vite([
        'resources/css/lgu-encoders.css',
        'resources/js/pages/lgu-encoders.js'
    ])
@endpush

@section('content')
    <div class="le-page">
        <div class="le-welcome">
            <div>
                <span class="le-eyebrow">LGU WORKSPACE / ACCOUNT MANAGEMENT</span>
                <h1>Encoder Management<span>.</span></h1>
                <p>Manage LGU Encoder accounts within your assigned municipality.</p>
            </div>
        </div>

        <div class="le-heading">
            <div>
                <h2>LGU Encoders</h2>
                <p>Create encoder accounts and manage their access.</p>
            </div>
            <div class="le-heading-actions">
                <button type="button" class="le-btn le-btn-outline" id="le-refresh">
                    Refresh
                </button>
                <button type="button" class="le-btn le-btn-primary" id="le-create">
                    + Create Encoder
                </button>
            </div>
        </div>

        <div class="le-stats">
            <div class="le-stat">
                <span>Total Encoders</span>
                <strong id="le-total">—</strong>
            </div>
            <div class="le-stat">
                <span>Active Encoders</span>
                <strong id="le-active">—</strong>
            </div>
            <div class="le-stat">
                <span>Inactive Encoders</span>
                <strong id="le-inactive">—</strong>
            </div>
            <div class="le-stat">
                <span>Suspended Encoders</span>
                <strong id="le-suspended">—</strong>
            </div>
        </div>

        <div class="le-panel">
            <div class="le-filter-heading">
                <div>
                    <h3>Encoder Directory</h3>
                    <p>Search and filter registered encoder accounts.</p>
                </div>
            </div>

            <div class="le-filters">
                <input id="le-search" type="search" placeholder="Search name or email..." autocomplete="off">
                <select id="le-status">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                </select>
                <button type="button" class="le-btn le-btn-outline" id="le-reset">
                    Reset
                </button>
            </div>

            <div id="le-error" class="le-error" role="alert" hidden></div>
            <div id="le-loading" class="le-loading" hidden>Loading encoders...</div>

            <div class="le-table-wrap">
                <table class="le-table">
                    <thead>
                        <tr>
                            <th>Encoder</th>
                            <th>Contact</th>
                            <th>Station / Designation</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="le-table-body">
                        <tr>
                            <td colspan="5" class="le-empty">Loading encoders...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="le-pagination">
                <span id="le-result-count">—</span>
                <div>
                    <button type="button" id="le-prev" class="le-btn le-btn-outline">
                        Previous
                    </button>
                    <span id="le-page-label">Page 1</span>
                    <button type="button" id="le-next" class="le-btn le-btn-outline">
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="le-modal" class="le-modal" hidden>
        <div class="le-modal-backdrop" data-close-modal></div>
        <div class="le-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="le-modal-title">
            <div class="le-modal-header">
                <h3 id="le-modal-title"></h3>
                <button type="button" id="le-modal-close" aria-label="Close modal">×</button>
            </div>
            <div id="le-modal-content"></div>
        </div>
    </div>
@endsection