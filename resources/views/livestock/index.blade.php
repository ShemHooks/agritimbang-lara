@extends('layouts.app')

@section('title', 'Livestock Management')

@push('styles')
    @vite([
        'resources/css/livestock-directory.css',
        'resources/css/livestock-monitoring.css',
        'resources/css/livestock-management.css',
        'resources/js/pages/livestock-management.js',
    ])
@endpush

@section('content')
    <section class="welcome">
        <div>
            <span class="eyebrow">LIVESTOCK MANAGEMENT</span>
            <h1>Livestock <span class="welcome-dot">Management.</span></h1>
            <p>Manage livestock records, monitor inventory, and explore geographic livestock distribution.</p>
        </div>
        <span class="welcome-ornament" aria-hidden="true">✳</span>
    </section>

    <div class="ld-toolbar">
        <div class="ld-toolbar-info">
            <span class="ld-toolbar-label">LIVESTOCK INVENTORY</span>
            <p>Manage and monitor registered livestock records.</p>
        </div>
        <div class="ld-toolbar-actions">
            <button type="button" id="ld-refresh" class="ld-btn ld-btn-secondary">Refresh</button>
            <a href="{{ route('farmer.livestock.create') }}" id="ld-register" class="ld-btn ld-btn-primary" hidden>
                <span aria-hidden="true">+</span> Register Livestock
            </a>
        </div>
    </div>

    @include('livestock.partials.summary')

    <div class="lm-view-tabs" role="tablist" aria-label="Livestock views">
        <button type="button" id="livestock-records-tab" class="lm-view-tab is-active" role="tab"
            aria-controls="livestock-records-view" aria-selected="true" data-livestock-tab="records">
            Livestock Records
        </button>
        <button type="button" id="livestock-monitoring-tab" class="lm-view-tab" role="tab"
            aria-controls="livestock-monitoring-view" aria-selected="false" data-livestock-tab="monitoring" hidden>
            Monitoring &amp; Analytics
        </button>
    </div>

    <section id="livestock-records-view" role="tabpanel" aria-labelledby="livestock-records-tab">
        @include('livestock.partials.records')
    </section>

    <section id="livestock-monitoring-view" role="tabpanel" aria-labelledby="livestock-monitoring-tab" hidden>
        @include('livestock.partials.monitoring')
    </section>
@endsection