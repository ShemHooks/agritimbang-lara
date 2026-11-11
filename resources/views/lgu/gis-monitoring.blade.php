@extends('layouts.app')

@section('title', 'GIS Monitoring')

@section('content')
    <div class="gis-page" id="gis-monitoring-page">

        <div id="gis-map-wrapper" class="gis-map-wrapper">

            <div id="gis-map" class="gis-map"></div>

            <!-- Floating filters -->
            <div class="gis-floating-controls">

                <div class="gis-floating-heading">
                    <span class="gis-live-dot"></span>
                    <strong>GIS Monitoring</strong>
                </div>

                <label class="gis-filter">
                    <span>Municipality</span>
                    <select id="gis-municipality">
                        <option value="">All municipalities</option>
                    </select>
                </label>

                <label class="gis-filter">
                    <span>Species</span>
                    <select id="gis-species">
                        <option value="">All species</option>
                    </select>
                </label>

                <div class="gis-actions">
                    <button type="button" id="gis-reset" class="gis-btn">
                        Reset View
                    </button>

                    <button type="button" id="gis-fullscreen" class="gis-btn">
                        Fullscreen
                    </button>
                </div>

                <div class="gis-floating-status" id="gis-map-summary">
                    Loading barangay boundaries...
                </div>
            </div>

            <!-- Loading overlay -->
            <div id="gis-map-loading" class="gis-map-overlay" hidden>
                <div class="gis-spinner"></div>
                <span>Loading geographic data...</span>
            </div>

            <!-- Error notification -->
            <div id="gis-error" class="gis-error" hidden></div>

        </div>

    </div>
@endsection

@vite([
    'resources/css/lgu-gis-monitoring.css',
    'resources/js/pages/lgu-gis-monitoring.js',
])