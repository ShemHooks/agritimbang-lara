{{-- =========================================================
AGRITIMBANG — LIVESTOCK MONITORING
Analytics & GIS Module
========================================================= --}}

<div class="overview-heading">
    <div>
        <h2>Monitoring & Analytics</h2>

        <p id="livestock-scope">
            Loading authorized records...
        </p>
    </div>

    <button type="button" id="livestock-refresh" class="lm-button">
        Refresh Data
    </button>
</div>

{{-- Loading State --}}
<div id="livestock-loading" class="lm-notice" role="status" hidden>
    Loading livestock monitoring data...
</div>

{{-- Error State --}}
<div id="livestock-error" class="lm-notice lm-error" role="alert" hidden>

    <span id="livestock-error-message"></span>

    <button type="button" id="livestock-retry">
        Try Again
    </button>
</div>


<div id="livestock-content" hidden>

    {{-- =====================================================
    ANALYTICS CHARTS
    ===================================================== --}}

    <section class="lm-panels">

        {{-- Species Distribution --}}
        <article class="lm-panel">

            <div class="lm-panel-heading">
                <h3>Livestock by Species</h3>

                <p>
                    Distribution of registered livestock
                    across different species.
                </p>
            </div>

            <div id="lm-species-chart" class="lm-chart">
            </div>

        </article>

        {{-- Status Distribution --}}
        <article class="lm-panel">

            <div class="lm-panel-heading">
                <h3>Livestock Status</h3>

                <p>
                    Current livestock inventory breakdown
                    by transaction and availability status.
                </p>
            </div>

            <div id="lm-status-chart" class="lm-chart">
            </div>

        </article>

    </section>


    {{-- =====================================================
    BARANGAY DISTRIBUTION
    ===================================================== --}}

    <section id="lm-barangay-panel" class="lm-panel" hidden>

        <div class="lm-panel-heading">
            <h3>Barangay Distribution</h3>

            <p>
                Livestock records grouped by barangay
                within the authorized geographic scope.
            </p>
        </div>

        <div id="lm-barangay-chart" class="lm-chart">
        </div>

    </section>


    {{-- =====================================================
    GEOGRAPHIC LIVESTOCK MONITORING
    LEAFLET 2D MAP
    ===================================================== --}}

    <section id="lm-map-panel" class="lm-panel" hidden>

        <div class="lm-map-header">

            <div class="lm-panel-heading">
                <h3>Geographic Livestock Monitoring</h3>

                <p>
                    Explore livestock distribution across
                    authorized municipalities and barangays.
                </p>
            </div>

            {{-- Map Filters --}}
            <div class="lm-map-actions">

                {{-- Municipality Filter --}}
                <select id="lm-map-municipality" aria-label="Filter map by municipality" hidden>
                    <option value="">
                        All Municipalities
                    </option>
                </select>

                {{-- Species Filter --}}
                <select id="lm-map-species" aria-label="Filter map by species">
                    <option value="">
                        All Species
                    </option>
                </select>

                <button type="button" id="lm-map-reset" class="lm-button-secondary">
                    Reset View
                </button>

            </div>
        </div>

        {{-- Leaflet Map --}}
        <div class="lm-map-container" id="lm-map-wrapper">

            <div id="lm-map"></div>

            <button type="button" id="lm-map-fullscreen" class="lm-map-fullscreen-btn" title="Enter fullscreen"
                aria-label="Enter fullscreen map" aria-pressed="false">
                <svg id="lm-fullscreen-icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    aria-hidden="true">
                    <path d="M8 3H5a2 2 0 0 0-2 2v3" />
                    <path d="M16 3h3a2 2 0 0 1 2 2v3" />
                    <path d="M3 16v3a2 2 0 0 0 2 2h3" />
                    <path d="M21 16v3a2 2 0 0 1-2 2h-3" />
                </svg>
            </button>

        </div>

        {{-- Map Footer --}}
        <div class="lm-map-footer">

            {{-- Population Density Legend --}}
            <div class="lm-map-legend">

                <span>
                    Livestock Population
                </span>

                <span class="lm-legend-item">
                    <i class="lm-legend-dot lm-density-low"></i>
                    Low
                </span>

                <span class="lm-legend-item">
                    <i class="lm-legend-dot lm-density-medium"></i>
                    Medium
                </span>

                <span class="lm-legend-item">
                    <i class="lm-legend-dot lm-density-high"></i>
                    High
                </span>

            </div>

            <p id="lm-map-summary" aria-live="polite">
                Loading geographic monitoring data...
            </p>

        </div>

    </section>

</div>