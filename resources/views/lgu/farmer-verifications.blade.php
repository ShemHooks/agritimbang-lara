@extends('layouts.app')

@section('title', 'Farmer Verification')

@section('content')
    <div class="fv-page" id="farmer-verification-page">
        <header class="fv-heading">
            <div>
                <p class="fv-eyebrow">LGU AUTHORITY / FARMER MANAGEMENT</p>
                <h1>Farmer Verification</h1>
                <p>Review farmer registration documents in your assigned municipality.</p>
            </div>
            <button class="fv-btn fv-btn-outline" id="fv-refresh" type="button">
                Refresh
            </button>
        </header>

        <section class="fv-panel">
            <div class="fv-panel-header">
                <div>
                    <h2>Pending Applications</h2>
                    <p id="fv-count">Loading applications...</p>
                </div>
            </div>

            <div class="fv-table-wrap">
                <table class="fv-table">
                    <thead>
                        <tr>
                            <th>Farmer</th>
                            <th>Municipality</th>
                            <th>Barangay</th>
                            <th>Verification</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="fv-table-body">
                        <tr>
                            <td colspan="5">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="fv-pagination">
                <button id="fv-prev" class="fv-btn fv-btn-outline" type="button">
                    Previous
                </button>
                <span id="fv-page-info">Page 1</span>
                <button id="fv-next" class="fv-btn fv-btn-outline" type="button">
                    Next
                </button>
            </div>
        </section>

        <div id="fv-alert" class="fv-alert" role="status" hidden></div>

        <div id="fv-modal" class="fv-modal" hidden>
            <div class="fv-backdrop" data-fv-close></div>

            <section class="fv-dialog" role="dialog" aria-modal="true" aria-labelledby="fv-dialog-title">
                <div class="fv-dialog-header">
                    <div>
                        <p class="fv-eyebrow">IDENTITY VERIFICATION</p>
                        <h2 id="fv-dialog-title">Review Farmer</h2>
                    </div>
                    <button type="button" class="fv-icon-btn" id="fv-close" aria-label="Close">×</button>
                </div>

                <div class="fv-dialog-body" id="fv-review-body">
                    Loading farmer details...
                </div>

                <div class="fv-dialog-footer">
                    <button type="button" class="fv-btn fv-btn-outline" id="fv-cancel">Close</button>
                    <button type="button" class="fv-btn fv-btn-danger" id="fv-reject">Reject</button>
                    <button type="button" class="fv-btn fv-btn-primary" id="fv-approve">Approve Farmer</button>
                </div>
            </section>
        </div>
    </div>
@endsection

@vite([
    'resources/css/lgu-farmer-verifications.css',
    'resources/js/pages/lgu-farmer-verifications.js'
])