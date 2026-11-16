@extends('layouts.app')

@section('title', 'GIS Monitoring')

@push('styles')
    @vite(['resources/css/lgu-gis-monitoring.css', 'resources/js/pages/lgu-gis-monitoring.js'])
@endpush

@section('content')
<div class="gis-page gis-monitoring-page" id="gis-monitoring-page">
    <header class="gis-heading">
        <div>
            <span class="gis-eyebrow">AGRITIMBANG · GEOGRAPHIC MONITORING</span>
            <h1>Geographic Livestock Monitoring</h1>
            <p>Explore livestock inventory and verified sales across authorized municipalities and barangays.</p>
        </div>
        <span class="gis-heading-icon" aria-hidden="true">🗺️</span>
    </header>

    <section class="gis-filters" aria-label="GIS monitoring filters">
        <label class="gis-filter">
            <span>Municipality</span>
            <select id="gis-municipality"><option value="">Loading municipalities…</option></select>
        </label>
        <label class="gis-filter">
            <span>Species</span>
            <select id="gis-species"><option value="">All species</option></select>
        </label>
        <label class="gis-filter">
            <span>Map layer</span>
            <select id="gis-dataset">
                <option value="livestock">Livestock inventory</option>
                <option value="verified_sales">Verified transactions</option>
            </select>
        </label>
        <label class="gis-filter" id="gis-status-filter">
            <span>Livestock status</span>
            <select id="gis-status">
                <option value="">All statuses</option>
                <option value="available">Available</option>
                <option value="in_transaction">In transaction</option>
                <option value="sold">Sold</option>
            </select>
        </label>
        <label class="gis-filter gis-sales-filter" id="gis-purpose-filter" hidden>
            <span>Sale purpose</span>
            <select id="gis-sale-purpose">
                <option value="">All sale purposes</option>
                <option value="slaughter">Slaughter</option>
                <option value="breeding">Breeding</option>
                <option value="fattening">Fattening</option>
                <option value="work">Work</option>
            </select>
        </label>
        <label class="gis-filter gis-sales-filter" id="gis-date-from-wrap" hidden>
            <span>Sales from</span>
            <input id="gis-date-from" type="date">
        </label>
        <label class="gis-filter gis-sales-filter" id="gis-date-to-wrap" hidden>
            <span>Sales to</span>
            <input id="gis-date-to" type="date">
        </label>
        <button id="gis-reset" type="button" class="gis-reset-button">Reset filters</button>
    </section>

    <div id="gis-error" class="gis-error gis-message" role="alert" hidden></div>

    <section class="gis-summary" aria-live="polite">
        <article><span id="gis-stat-one-label">Total livestock</span><strong id="gis-stat-one">—</strong></article>
        <article><span id="gis-stat-two-label">Available</span><strong id="gis-stat-two">—</strong></article>
        <article><span id="gis-stat-three-label">Top species</span><strong id="gis-stat-three">—</strong></article>
        <article><span id="gis-stat-four-label">Leading barangay</span><strong id="gis-stat-four">—</strong></article>
    </section>

    <section class="gis-map-card">
        <div class="gis-map-heading">
            <div>
                <h2>Barangay map</h2>
                <p id="gis-map-caption">Select a layer to view municipal activity.</p>
            </div>
            <div class="gis-map-heading-actions">
                <span id="gis-map-summary" class="gis-map-status">Loading map…</span>
                <button id="gis-fullscreen" type="button" class="gis-map-action">Fullscreen</button>
            </div>
        </div>
        <div class="gis-map-stage" id="gis-map-stage">
            <div id="gis-map" role="application" aria-label="Interactive municipal barangay map"></div>
            <div id="gis-map-loading" class="gis-map-overlay" hidden>
                <div class="gis-spinner"></div>
                <span>Loading geographic data...</span>
            </div>
        </div>
        <div class="gis-legend" aria-label="Map legend">
            <strong>Activity intensity</strong>
            <span><i class="gis-legend-swatch level-1"></i> 1–9</span>
            <span><i class="gis-legend-swatch level-2"></i> 10–39</span>
            <span><i class="gis-legend-swatch level-3"></i> 40–99</span>
            <span><i class="gis-legend-swatch level-4"></i> 100+</span>
            <span><i class="gis-legend-swatch level-0"></i> No records</span>
        </div>
        <p class="gis-disclaimer">Map tiles © OpenStreetMap contributors. Barangay boundaries come from the bundled Cebu GeoJSON dataset. Counts are based on records entered in AgriTimbang. Verified transaction maps use the recorded transaction barangay as the geographic location.</p>
    </section>
</div>
@endsection
