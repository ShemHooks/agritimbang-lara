@extends('layouts.app')

@section('content')
    <main class="l55-page" id="l55-show" data-id="{{ $livestockId }}">
        <div class="l55-top">
            <a href="{{ url('/livestock') }}">← Back to livestock</a>
            <span id="l55-status" class="l55-pill">Loading…</span>
        </div>

        <header class="l55-header">
            <div>
                <p class="l55-eyebrow">LIVESTOCK MANAGEMENT</p>
                <h1>Livestock details</h1>
                <p id="l55-subtitle">Loading record…</p>
            </div>


            <div>
                <a id="l55-edit-link" class="l55-primary" href="#" hidden>
                    Edit livestock
                </a>

                <a id="l55-estimate-link" class="l55-primary" href="#" hidden>
                    Estimate Weight
                </a>
            </div>
        </header>

        <div id="l55-error" class="l55-error" role="alert" hidden></div>
        <div id="l55-loading">Loading livestock details…</div>

        <div id="l55-details" hidden>
            <section class="l55-card">
                <h2>Animal information</h2>
                <div id="l55-animal" class="l55-grid"></div>
            </section>

            <section class="l55-card">
                <h2>Ownership & location</h2>
                <div id="l55-location" class="l55-grid"></div>
            </section>

            <section class="l55-card">
                <h2>Record information</h2>
                <div id="l55-record" class="l55-grid"></div>
            </section>
        </div>
    </main>
@endsection

@vite([
    'resources/css/livestock-details.css',
    'resources/js/pages/livestock-show.js'
])