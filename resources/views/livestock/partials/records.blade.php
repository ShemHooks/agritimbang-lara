{{-- =========================================================
AGRITIMBANG — LIVESTOCK RECORDS
Shared Livestock Management Module
========================================================= --}}

<section class="ld-panel ld-filter-panel">

    <div class="ld-panel-header">
        <div>
            <span class="ld-section-eyebrow">
                SEARCH & FILTER
            </span>

            <h2>Find Livestock</h2>

            <p>
                Filter livestock records by ownership,
                classification, location, and status.
            </p>
        </div>

        <button type="button" id="ld-reset-filters" class="ld-reset-button">
            Reset Filters
        </button>
    </div>

    <form id="ld-filter-form" class="ld-filter-grid">

        {{-- Search --}}
        <div class="ld-field ld-search-field">
            <label for="ld-search">
                Search Livestock
            </label>

            <input type="search" id="ld-search" name="search" placeholder="Livestock ID, farmer code, or farmer name"
                autocomplete="off">
        </div>

        {{-- Status --}}
        <div class="ld-field">
            <label for="ld-status">Status</label>

            <select id="ld-status" name="status">
                <option value="">All Statuses</option>
                <option value="available">Available</option>
                <option value="in_transaction">In Transaction</option>
                <option value="sold">Sold</option>
                <option value="archived">Archived</option>
            </select>
        </div>

        {{-- Municipality — Admin only --}}
        <div class="ld-field" id="ld-municipality-field" hidden>

            <label for="ld-municipality">
                Municipality
            </label>

            <select id="ld-municipality" name="municipality_id" disabled>
                <option value="">All Municipalities</option>
            </select>
        </div>

        {{-- Barangay --}}
        <div class="ld-field">
            <label for="ld-barangay">
                Barangay
            </label>

            <select id="ld-barangay" name="barangay_id" disabled>
                <option value="">All Barangays</option>
            </select>
        </div>

        {{-- Species --}}
        <div class="ld-field">
            <label for="ld-species">
                Species
            </label>

            <select id="ld-species" name="species_id" disabled>
                <option value="">All Species</option>
            </select>
        </div>

        {{-- Breed --}}
        <div class="ld-field">
            <label for="ld-breed">
                Breed
            </label>

            <select id="ld-breed" name="breed_id" disabled>
                <option value="">All Breeds</option>
            </select>
        </div>

        {{-- Pagination Size --}}
        <div class="ld-field">
            <label for="ld-per-page">
                Records Per Page
            </label>

            <select id="ld-per-page" name="per_page">
                <option value="10">10 Records</option>
                <option value="15">15 Records</option>
                <option value="20" selected>20 Records</option>
                <option value="50">50 Records</option>
            </select>
        </div>

        <div class="ld-filter-actions">
            <button type="submit" class="ld-btn ld-btn-primary">
                Apply Filters
            </button>
        </div>

    </form>

</section>


{{-- =========================================================
LIVESTOCK DIRECTORY TABLE
========================================================= --}}

<section class="ld-panel ld-table-panel">

    <div class="ld-panel-header">
        <div>
            <span class="ld-section-eyebrow">
                REGISTERED LIVESTOCK
            </span>

            <h2>Livestock Directory</h2>

            <p id="ld-result-description">
                Loading livestock records...
            </p>
        </div>

        <span id="ld-result-count" class="ld-count-badge">
            — Records
        </span>
    </div>

    {{-- Loading State --}}
    <div id="ld-loading" class="ld-notice" role="status">
        Loading livestock records...
    </div>

    {{-- Error State --}}
    <div id="ld-error" class="ld-notice ld-error" role="alert" hidden>

        <div>
            <strong>
                Unable to load livestock records
            </strong>

            <p id="ld-error-message">
                An unexpected error occurred.
            </p>
        </div>

        <button type="button" id="ld-retry">
            Try Again
        </button>
    </div>

    {{-- Table Container --}}
    <div id="ld-table-container" class="ld-table-container" hidden>

        <table class="ld-table">
            <thead>
                <tr>
                    <th>Livestock ID</th>
                    <th>Farmer / Owner</th>
                    <th>Species & Breed</th>
                    <th>Sex / Age</th>
                    <th>Weight</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th class="ld-actions-column">
                        Actions
                    </th>
                </tr>
            </thead>

            <tbody id="ld-table-body">
                {{-- Rendered dynamically by JavaScript --}}
            </tbody>
        </table>

    </div>

    {{-- Empty State --}}
    <div id="ld-empty" class="ld-empty-state" hidden>

        <div class="ld-empty-icon" aria-hidden="true">
            ◇
        </div>

        <h3>No Livestock Records Found</h3>

        <p>
            No livestock records match your current filters.
            Try adjusting your search criteria.
        </p>
    </div>

    {{-- Pagination --}}
    <div id="ld-pagination" class="ld-pagination" hidden>

        <p id="ld-pagination-info">
            Showing 0 of 0 records
        </p>

        <div class="ld-pagination-controls">

            <button type="button" id="ld-prev-page" class="ld-page-button" disabled>
                Previous
            </button>

            <span id="ld-page-indicator">
                Page 1 of 1
            </span>

            <button type="button" id="ld-next-page" class="ld-page-button" disabled>
                Next
            </button>

        </div>
    </div>

</section>