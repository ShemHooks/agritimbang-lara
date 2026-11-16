@extends('layouts.public')

@section('title', 'Buyer Interest')

@section('content')
    <script>
        window.BuyerInterestConfig = {
            csrfToken: '{{ csrf_token() }}',
            scheduleId: @json($scheduleId)
        };
    </script>

    <div class="public-container" id="buyer-interest-page" data-schedule-id="{{ $scheduleId }}">
        <div class="schedule-heading">
            <div>
                <p class="public-eyebrow">Public auction listing</p>
                <h1 class="public-title" id="auction-heading">Auction Date — loading | Municipality — loading</h1>
            </div>
            <a href="{{ route('forsale') }}" class="btn btn-outline">All livestock for sale</a>
        </div>

        <div id="auction-status" class="alert" role="status">Loading auction details...</div>

        <div id="livestock-list" class="livestock-grid"></div>
    </div>

    <div class="selected-bar">
        <div class="public-container selected-bar-inner" style="padding: 0">
            <div id="selected-count"><strong>0 livestock selected</strong></div>
            <div class="selected-actions">
                <button type="button" class="btn btn-outline" id="login-interest-trigger">Login Buyer Account</button>
                <button type="button" class="btn btn-primary" id="create-interest-trigger" disabled>Create Interest</button>
            </div>
        </div>
    </div>

    <div class="interest-modal" id="interestModal" tabindex="-1" aria-labelledby="interestModalLabel" aria-hidden="true" hidden>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="interestModalLabel">Buyer Interest</h2>
                    <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
                </div>
                <div class="modal-body" id="interest-modal-body"></div>
            </div>
        </div>
    </div>

    <div class="interest-modal" id="loginBuyerModal" tabindex="-1" aria-labelledby="loginBuyerModalLabel" aria-hidden="true" hidden>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="loginBuyerModalLabel">Login Buyer Account</h2>
                    <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
                </div>
                <div class="modal-body" id="login-modal-body"></div>
            </div>
        </div>
    </div>

    @vite(['resources/js/pages/buyer-interest.js'])
@endsection
