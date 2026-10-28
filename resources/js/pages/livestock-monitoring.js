import api from "../api";
import L from "leaflet";
import "leaflet/dist/leaflet.css";

// =====================================================
// UTILITIES
// =====================================================

const $ = (id) => document.getElementById(id);

const MANAGEMENT_ROLES = ["admin", "lgu_authority", "lgu_encoder"];

const STATUSES = ["available", "in_transaction", "sold", "archived"];

const STATUS_LABELS = {
    available: "Available",
    in_transaction: "In transaction",
    sold: "Sold",
    archived: "Archived",
};

const INITIAL_BOUNDS = L.latLngBounds([9.0, 121.5], [12.0, 125.5]);

const MUNICIPALITY_HUES = [
    105, 205, 25, 275, 345, 165, 45, 235, 135, 15, 305, 185, 75, 255, 325, 155,
];

const state = {
    role: null,
    page: 1,
    lastPage: 1,
    recordsRequest: 0,
    filters: {
        status: "",
        species_id: "",
        breed_id: "",
    },
};

const mapState = {
    map: null,
    layer: null,
    bounds: null,
    requestId: 0,
    filtersLoaded: false,
    municipalityNames: new Map(),
};

const formatNumber = (value) =>
    new Intl.NumberFormat("en-PH").format(Number(value) || 0);

const normalizeRole = (value) =>
    String(value ?? "")
        .toLowerCase()
        .replace(/[-\s]+/g, "_");

const escapeHtml = (value) =>
    String(value ?? "").replace(
        /[&<>"']/g,
        (character) =>
            ({
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': "&quot;",
                "'": "&#39;",
            })[character],
    );

function setText(id, value) {
    const element = $(id);

    if (element) {
        element.textContent = value;
    }
}

function getError(error) {
    return (
        error.response?.data?.message ||
        error.message ||
        "Something went wrong."
    );
}

function showError(message) {
    setText("livestock-error-message", message);

    if ($("livestock-error")) {
        $("livestock-error").hidden = false;
    }
}

function hideError() {
    if ($("livestock-error")) {
        $("livestock-error").hidden = true;
    }
}

function unwrapList(response) {
    const data = response.data?.data;

    if (Array.isArray(data)) {
        return data;
    }

    if (Array.isArray(data?.data)) {
        return data.data;
    }

    return [];
}

// =====================================================
// STATISTICS AND CHARTS
// =====================================================

function renderBars(id, entries) {
    const container = $(id);

    if (!container) return;

    container.replaceChildren();

    const items = entries.filter((item) => Number(item.total) > 0);

    if (!items.length) {
        const empty = document.createElement("p");

        empty.className = "lm-empty";
        empty.textContent = "No livestock data available yet.";

        container.appendChild(empty);
        return;
    }

    const maximum = Math.max(...items.map((item) => Number(item.total)));

    for (const item of items) {
        const row = document.createElement("div");
        row.className = "lm-chart-row";

        const heading = document.createElement("div");
        heading.className = "lm-chart-label";

        const name = document.createElement("span");
        name.textContent = item.name;

        const count = document.createElement("strong");
        count.textContent = formatNumber(item.total);

        heading.append(name, count);

        const track = document.createElement("div");
        track.className = "lm-chart-track";

        const fill = document.createElement("div");
        fill.className = "lm-chart-fill";

        fill.style.width = `${(Number(item.total) / maximum) * 100}%`;

        track.appendChild(fill);
        row.append(heading, track);

        container.appendChild(row);
    }
}

function renderStats(data) {
    const statuses = data.livestock_by_status ?? {};

    setText("lm-total", formatNumber(data.total_livestock));

    setText("lm-available", formatNumber(statuses.available));

    setText("lm-in-transaction", formatNumber(statuses.in_transaction));

    setText("lm-sold", formatNumber(statuses.sold));

    renderBars(
        "lm-status-chart",
        STATUSES.map((status) => ({
            name: STATUS_LABELS[status],
            total: statuses[status] ?? 0,
        })),
    );
}

function renderManagementAnalytics(data) {
    renderBars(
        "lm-species-chart",
        (data.by_species ?? []).map((item) => ({
            name: item.name ?? "Unknown species",
            total: item.total,
        })),
    );

    renderBars(
        "lm-barangay-chart",
        (data.by_barangay ?? []).map((item) => ({
            name: item.name ?? "Unknown barangay",
            total: item.total,
        })),
    );

    if ($("lm-barangay-panel")) {
        $("lm-barangay-panel").hidden = false;
    }
}

function renderFarmerSpecies(records) {
    const counts = new Map();

    for (const record of records) {
        const name = record.species?.name ?? "Unknown species";

        counts.set(name, (counts.get(name) ?? 0) + 1);
    }

    renderBars(
        "lm-species-chart",
        [...counts].map(([name, total]) => ({
            name,
            total,
        })),
    );
}

async function loadFarmerStats() {
    const responses = await Promise.all(
        STATUSES.map((status) =>
            api.get("/farmer/livestock", {
                params: { status },
            }),
        ),
    );

    const statuses = Object.fromEntries(
        STATUSES.map((status, index) => [
            status,
            Number(responses[index].data?.data?.total ?? 0),
        ]),
    );

    renderStats({
        total_livestock: Object.values(statuses).reduce(
            (sum, value) => sum + value,
            0,
        ),
        livestock_by_status: statuses,
    });
}

// =====================================================
// LIVESTOCK RECORDS
// =====================================================

function makeCell(value) {
    const cell = document.createElement("td");

    cell.textContent = value ?? "—";

    return cell;
}

function farmerName(record) {
    const user = record.farmer?.user;

    if (!user) return "—";

    return (
        [user.first_name, user.last_name].filter(Boolean).join(" ") ||
        user.name ||
        user.full_name ||
        "—"
    );
}

function renderRecords(paginator) {
    const body = $("lm-records-body");

    if (!body) return;

    body.replaceChildren();

    const records = paginator.data ?? [];

    if (!records.length) {
        const row = document.createElement("tr");

        const cell = makeCell("No matching livestock records found.");

        cell.colSpan = 5;
        cell.className = "lm-empty-cell";

        row.appendChild(cell);
        body.appendChild(row);
    }

    for (const record of records) {
        const row = document.createElement("tr");

        const location =
            [record.barangay?.name, record.municipality?.name]
                .filter(Boolean)
                .join(", ") || "—";

        row.append(
            makeCell(record.species?.name ?? "Unknown species"),
            makeCell(record.breed?.name ?? "—"),
            makeCell(
                state.role === "farmer" ? "My livestock" : farmerName(record),
            ),
            makeCell(location),
            makeCell(STATUS_LABELS[record.status] ?? record.status ?? "—"),
        );

        body.appendChild(row);
    }

    state.page = Number(paginator.current_page) || 1;

    state.lastPage = Number(paginator.last_page) || 1;

    setText(
        "lm-page-info",
        `Page ${state.page} of ${state.lastPage} · ` +
            `${formatNumber(paginator.total)} records`,
    );

    if ($("lm-prev-page")) {
        $("lm-prev-page").disabled = state.page <= 1;
    }

    if ($("lm-next-page")) {
        $("lm-next-page").disabled = state.page >= state.lastPage;
    }
}

async function loadRecords() {
    const requestId = ++state.recordsRequest;

    const endpoint =
        state.role === "farmer" ? "/farmer/livestock" : "/lgu/livestock";

    const params = {
        page: state.page,
    };

    for (const [key, value] of Object.entries(state.filters)) {
        if (value) {
            params[key] = value;
        }
    }

    const response = await api.get(endpoint, { params });

    if (requestId !== state.recordsRequest) {
        return;
    }

    const paginator = response.data?.data;

    if (!paginator || !Array.isArray(paginator.data)) {
        throw new Error("Invalid livestock records response.");
    }

    renderRecords(paginator);

    if (state.role === "farmer") {
        renderFarmerSpecies(paginator.data);
    }
}

async function loadSpeciesOptions() {
    const select = $("lm-filter-species");

    if (!select) return;

    const response = await api.get("/reference/species");

    select.replaceChildren(new Option("All species", ""));

    for (const item of unwrapList(response)) {
        select.add(new Option(item.name, item.id));
    }
}

async function loadBreedOptions(speciesId) {
    const select = $("lm-filter-breed");

    if (!select) return;

    select.replaceChildren(new Option("All breeds", ""));

    select.disabled = !speciesId;

    if (!speciesId) return;

    const response = await api.get(
        `/reference/species/${encodeURIComponent(speciesId)}/breeds`,
    );

    if ($("lm-filter-species")?.value !== speciesId) {
        return;
    }

    for (const breed of unwrapList(response)) {
        select.add(new Option(breed.name, breed.id));
    }
}

// =====================================================
// GIS HELPERS
// =====================================================

function municipalityKey(feature) {
    const p = feature.properties ?? {};

    return String(
        p.municipality_id ??
            p.municipality?.id ??
            p.municipality_psgc_code ??
            p.municipality_name ??
            "unknown",
    );
}

function municipalityName(feature) {
    const p = feature.properties ?? {};

    const key = municipalityKey(feature);

    return (
        p.municipality_name ??
        p.municipality?.name ??
        mapState.municipalityNames.get(key) ??
        "Unknown municipality"
    );
}

function barangayName(feature) {
    const p = feature.properties ?? {};

    return p.barangay_name ?? p.name ?? "Unknown barangay";
}

function livestockCount(feature) {
    const raw = feature.properties?.total_livestock;

    if (raw === null || raw === undefined || raw === "") {
        return null;
    }

    const value = Number(raw);

    return Number.isFinite(value) && value >= 0 ? value : null;
}

function municipalityHue(key) {
    let hash = 0;

    for (const character of String(key)) {
        hash = (hash * 31 + character.charCodeAt(0)) | 0;
    }

    return MUNICIPALITY_HUES[(hash >>> 0) % MUNICIPALITY_HUES.length];
}

function barangayColor(feature, maximum) {
    const hue = municipalityHue(municipalityKey(feature));

    const count = livestockCount(feature);

    if (count === null) {
        return `hsl(${hue}, 12%, 83%)`;
    }

    if (count === 0) {
        return `hsl(${hue}, 45%, 89%)`;
    }

    const ratio = maximum > 0 ? count / maximum : 0;

    const lightness = 82 - ratio * 39;

    return `hsl(${hue}, 58%, ${lightness}%)`;
}

function isActiveFeature(feature) {
    const p = feature.properties ?? {};

    if (
        p.municipality_is_active !== undefined &&
        p.municipality_is_active !== null
    ) {
        return (
            p.municipality_is_active === true ||
            p.municipality_is_active === 1 ||
            p.municipality_is_active === "1" ||
            p.municipality_is_active === "true"
        );
    }

    // Backend must enforce active municipality
    // filtering when this property is missing.
    return true;
}

function getActiveFeatures(collection) {
    return (
        Array.isArray(collection?.features) ? collection.features : []
    ).filter(isActiveFeature);
}

function getMaximumByMunicipality(features) {
    const maximums = new Map();

    for (const feature of features) {
        const key = municipalityKey(feature);
        const count = livestockCount(feature) ?? 0;

        maximums.set(key, Math.max(maximums.get(key) ?? 0, count));
    }

    return maximums;
}

function unwrapGeojson(response) {
    const payload = response.data;

    if (payload?.type === "FeatureCollection") {
        return payload;
    }

    if (payload?.data?.type === "FeatureCollection") {
        return payload.data;
    }

    throw new Error("Invalid GIS FeatureCollection response.");
}

// =====================================================
// BARANGAY POPUPS
// =====================================================

function createPopup(feature) {
    const p = feature.properties ?? {};

    const count = livestockCount(feature);

    return `
        <div class="lm-map-popup">
            <strong>
                ${escapeHtml(barangayName(feature))}
            </strong>

            <p>
                Municipality:
                <b>${escapeHtml(municipalityName(feature))}</b>
            </p>

            <p>
                Province:
                <b>${escapeHtml(p.province_name ?? "N/A")}</b>
            </p>

            <p>
                Total livestock:
                <b>${count === null ? "Not available" : formatNumber(count)}</b>
            </p>

            <p>
                Available:
                ${formatNumber(p.available)}
            </p>

            <p>
                In transaction:
                ${formatNumber(p.in_transaction)}
            </p>

            <p>
                Sold:
                ${formatNumber(p.sold)}
            </p>

            <p>
                Archived:
                ${formatNumber(p.archived)}
            </p>
        </div>
    `;
}

// =====================================================
// LEAFLET 2D MAP
// =====================================================

function initializeMap() {
    if (mapState.map) return;

    const element = $("lm-map");

    if (!element) {
        throw new Error("Missing #lm-map container.");
    }

    mapState.map = L.map(element, {
        zoomControl: true,
        scrollWheelZoom: false,
        preferCanvas: true,
        minZoom: 4,
        maxZoom: 18,
    });

    // Full geographic context.
    // No Cebu-only boundary restriction.
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap contributors",
        maxZoom: 19,
    }).addTo(mapState.map);

    mapState.map.fitBounds(INITIAL_BOUNDS);

    mapState.map.on("zoomend", updateBarangayLabels);

    requestAnimationFrame(() => {
        mapState.map.invalidateSize();
    });
}

function renderMunicipalityLegend(municipalities) {
    let container = $("lm-municipality-legend");

    if (!container) {
        const panel = $("lm-map-panel");

        if (!panel) return;

        container = document.createElement("div");

        container.id = "lm-municipality-legend";

        container.className = "lm-municipality-legend";

        panel.appendChild(container);
    }

    container.replaceChildren();

    const heading = document.createElement("strong");

    heading.textContent = "Active municipality colors";

    const items = document.createElement("div");

    items.className = "lm-municipality-legend-items";

    const sorted = [...municipalities.entries()].sort((a, b) =>
        a[1].localeCompare(b[1]),
    );

    for (const [key, name] of sorted) {
        const item = document.createElement("span");

        item.className = "lm-municipality-legend-item";

        const swatch = document.createElement("i");

        swatch.style.backgroundColor = `hsl(${municipalityHue(key)}, 58%, 55%)`;

        const label = document.createElement("span");

        label.textContent = name;

        item.append(swatch, label);
        items.appendChild(item);
    }

    container.append(heading, items);
}

const MIN_LABEL_ZOOM = 11;

function updateBarangayLabels() {
    if (!mapState.map || !mapState.layer) return;

    const show = mapState.map.getZoom() >= MIN_LABEL_ZOOM;

    mapState.layer.eachLayer((layer) => {
        const tooltip = layer.getTooltip();

        if (!tooltip) return;

        if (show) {
            layer.openTooltip();
        } else {
            layer.closeTooltip();
        }
    });
}

function renderGeojson(collection) {
    initializeMap();

    const map = mapState.map;

    if (mapState.layer) {
        map.removeLayer(mapState.layer);
        mapState.layer = null;
    }

    const features = getActiveFeatures(collection);

    const maximums = getMaximumByMunicipality(features);

    const municipalityNames = new Map();

    for (const feature of features) {
        municipalityNames.set(
            municipalityKey(feature),
            municipalityName(feature),
        );
    }

    const group = L.geoJSON(
        {
            type: "FeatureCollection",
            features,
        },
        {
            style(feature) {
                const key = municipalityKey(feature);

                const hue = municipalityHue(key);

                return {
                    color: `hsl(${hue}, 55%, 29%)`,
                    weight: 0.9,
                    opacity: 0.95,
                    fillColor: barangayColor(feature, maximums.get(key) ?? 0),
                    fillOpacity: 0.75,
                };
            },

            onEachFeature(feature, layer) {
                const name = barangayName(feature);
                const municipality = municipalityName(feature);
                const count = livestockCount(feature);

                const label = document.createElement("div");
                label.className = "lm-barangay-count-label";

                const nameElement = document.createElement("strong");
                nameElement.textContent = name;

                const countElement = document.createElement("span");
                countElement.textContent =
                    count === null
                        ? "Count unavailable"
                        : `${formatNumber(count)} livestock`;

                label.append(nameElement, countElement);

                layer.bindTooltip(label, {
                    permanent: false,
                    direction: "center",
                    className: "lm-count-tooltip",
                    opacity: 1,
                });

                layer.bindPopup(createPopup(feature));

                layer.on("mouseover", () => {
                    layer.setStyle({
                        color: "#263a1b",
                        weight: 2.5,
                        fillOpacity: 0.95,
                    });

                    layer.bringToFront?.();

                    if (mapState.map.getZoom() < MIN_LABEL_ZOOM) {
                        layer.openTooltip();
                    }
                });

                layer.on("mouseout", () => {
                    if (mapState.layer === group) {
                        group.resetStyle(layer);
                    }

                    if (mapState.map.getZoom() < MIN_LABEL_ZOOM) {
                        layer.closeTooltip();
                    }
                });
            },
        },
    );

    mapState.layer = group;

    group.addTo(map);

    updateBarangayLabels();

    if (group.getLayers().length > 0) {
        const bounds = group.getBounds();

        if (bounds.isValid()) {
            mapState.bounds = bounds;

            map.fitBounds(bounds, {
                padding: [25, 25],
                maxZoom: 12,
            });
        }
    } else {
        mapState.bounds = INITIAL_BOUNDS;

        map.fitBounds(INITIAL_BOUNDS);
    }

    renderMunicipalityLegend(municipalityNames);

    const counted = features.filter(
        (feature) => livestockCount(feature) !== null,
    ).length;

    setText(
        "lm-map-summary",
        `${formatNumber(municipalityNames.size)} active municipalities · ` +
            `${formatNumber(features.length)} barangay polygons · ` +
            `${formatNumber(counted)} with livestock counts`,
    );

    requestAnimationFrame(() => {
        map.invalidateSize();
    });
}

function resetMapView() {
    if (!mapState.map) return;

    mapState.map.fitBounds(mapState.bounds ?? INITIAL_BOUNDS, {
        padding: [25, 25],
        maxZoom: 12,
    });
}

// =====================================================
// GIS API
// =====================================================

async function loadMap() {
    if (!MANAGEMENT_ROLES.includes(state.role)) {
        return;
    }

    const requestId = ++mapState.requestId;

    const municipalityId = $("lm-map-municipality")?.value ?? "";

    const speciesId = $("lm-map-species")?.value ?? "";

    const params = {};

    if (state.role === "admin" && municipalityId) {
        params.municipality_id = municipalityId;
    }

    if (speciesId) {
        params.species_id = speciesId;
    }

    setText("lm-map-summary", "Loading geographic monitoring data...");

    try {
        const response = await api.get("/gis/monitoring-geojson", { params });

        if (requestId !== mapState.requestId) {
            return;
        }

        renderGeojson(unwrapGeojson(response));
    } catch (error) {
        if (requestId !== mapState.requestId) {
            return;
        }

        setText("lm-map-summary", `Unable to load map: ${getError(error)}`);

        console.error("GIS error:", error);
    }
}

async function loadMapFilters() {
    const speciesSelect = $("lm-map-species");

    const municipalitySelect = $("lm-map-municipality");

    if (!speciesSelect || !municipalitySelect) {
        return;
    }

    try {
        const response = await api.get("/reference/species");

        const selected = speciesSelect.value;

        speciesSelect.replaceChildren(new Option("All species", ""));

        for (const item of unwrapList(response)) {
            speciesSelect.add(new Option(item.name, item.id));
        }

        if (
            [...speciesSelect.options].some(
                (option) => option.value === selected,
            )
        ) {
            speciesSelect.value = selected;
        }
    } catch (error) {
        console.warn("GIS species filters:", error);
    }

    if (state.role !== "admin") {
        municipalitySelect.hidden = true;
        mapState.filtersLoaded = true;
        return;
    }

    municipalitySelect.hidden = false;

    try {
        const response = await api.get("/gis/municipalities");

        const municipalities = unwrapList(response);

        const selected = municipalitySelect.value;

        municipalitySelect.replaceChildren(
            new Option("All municipalities", ""),
        );

        mapState.municipalityNames = new Map(
            municipalities.map((item) => [String(item.id), item.name]),
        );

        for (const municipality of municipalities) {
            municipalitySelect.add(
                new Option(municipality.name, municipality.id),
            );
        }

        if (
            [...municipalitySelect.options].some(
                (option) => option.value === selected,
            )
        ) {
            municipalitySelect.value = selected;
        }
    } catch (error) {
        console.warn("GIS municipalities:", error);
    }

    mapState.filtersLoaded = true;
}

async function initializeMapModule() {
    if (!MANAGEMENT_ROLES.includes(state.role)) {
        return;
    }

    if (!$("lm-map-panel") || !$("lm-map")) {
        console.warn("GIS map markup is missing.");
        return;
    }

    $("lm-map-panel").hidden = false;

    initializeMap();

    if (!mapState.filtersLoaded) {
        await loadMapFilters();
    }

    await loadMap();
}

// =====================================================
// ROLE CONFIGURATION
// =====================================================

function configureRole(role) {
    state.role = role;

    const management = MANAGEMENT_ROLES.includes(role);
    const mapPanel = $("lm-map-panel");
    if (mapPanel) mapPanel.hidden = !management;

    const scopes = {
        admin: "System-wide livestock monitoring",
        lgu_authority: "Livestock monitoring for your assigned municipality",
        lgu_encoder: "Municipal livestock monitoring and analytics",
    };
    setText("livestock-scope", scopes[role] ?? "Livestock monitoring");
}

// =====================================================
// MANAGEMENT ANALYTICS
// =====================================================

async function loadManagement() {
    const [overview, analytics] = await Promise.all([
        api.get("/analytics/dashboard"),
        api.get("/analytics/livestock"),
    ]);

    renderStats(overview.data?.data ?? {});

    renderManagementAnalytics(analytics.data?.data ?? {});

    await initializeMapModule();
}

// =====================================================
// INITIALIZATION
// =====================================================

async function initialize() {
    if (!sessionStorage.getItem("agritimbang_token")) return;

    if ($("livestock-loading")) $("livestock-loading").hidden = false;
    hideError();

    try {
        const response = await api.get("/auth/me");
        const user = response.data?.data;
        if (!user) throw new Error("Unable to load account.");

        const roles = (user.roles ?? []).map((role) =>
            normalizeRole(typeof role === "string" ? role : role.slug),
        );
        const role = MANAGEMENT_ROLES.find((item) => roles.includes(item));
        if (!role)
            throw new Error("Your account cannot access livestock monitoring.");

        configureRole(role);
        if ($("livestock-content")) $("livestock-content").hidden = false;
        await loadManagement();
    } catch (error) {
        console.error("Livestock Monitoring Error:", error);
        showError(getError(error));
    } finally {
        if ($("livestock-loading")) $("livestock-loading").hidden = true;
    }
}

// =====================================================
// EVENT LISTENERS
// =====================================================

$("livestock-refresh")?.addEventListener("click", initialize);

$("livestock-retry")?.addEventListener("click", initialize);

async function updateRecords() {
    try {
        await loadRecords();
    } catch (error) {
        showError(getError(error));
    }
}

$("lm-filter-status")?.addEventListener("change", (event) => {
    state.filters.status = event.target.value;

    state.page = 1;

    updateRecords();
});

$("lm-filter-species")?.addEventListener("change", async (event) => {
    state.filters.species_id = event.target.value;

    state.filters.breed_id = "";
    state.page = 1;

    try {
        await loadBreedOptions(state.filters.species_id);

        await loadRecords();
    } catch (error) {
        showError(getError(error));
    }
});

$("lm-filter-breed")?.addEventListener("change", (event) => {
    state.filters.breed_id = event.target.value;

    state.page = 1;

    updateRecords();
});

$("lm-clear-filters")?.addEventListener("click", () => {
    state.filters = {
        status: "",
        species_id: "",
        breed_id: "",
    };

    state.page = 1;

    $("lm-filter-status").value = "";

    $("lm-filter-species").value = "";

    $("lm-filter-breed").replaceChildren(new Option("All breeds", ""));

    $("lm-filter-breed").disabled = true;

    updateRecords();
});

$("lm-prev-page")?.addEventListener("click", () => {
    if (state.page <= 1) {
        return;
    }

    state.page--;
    updateRecords();
});

$("lm-next-page")?.addEventListener("click", () => {
    if (state.page >= state.lastPage) {
        return;
    }

    state.page++;
    updateRecords();
});

$("lm-map-municipality")?.addEventListener("change", loadMap);

$("lm-map-species")?.addEventListener("change", loadMap);

$("lm-map-reset")?.addEventListener("click", resetMapView);

window.addEventListener("resize", () => {
    if (mapState.map && $("lm-map")?.offsetWidth > 0) {
        mapState.map.invalidateSize();
    }
});

async function toggleMapFullscreen() {
    const wrapper = $("lm-map-wrapper");

    if (!wrapper) return;

    try {
        if (document.fullscreenElement === wrapper) {
            await document.exitFullscreen();
        } else if (!document.fullscreenElement) {
            await wrapper.requestFullscreen();
        }
    } catch (error) {
        console.error("Map fullscreen error:", error);
    }
}

function syncMapFullscreen() {
    const isFullscreen = document.fullscreenElement === $("lm-map-wrapper");

    const button = $("lm-map-fullscreen");

    if (button) {
        button.title = isFullscreen ? "Exit fullscreen" : "Enter fullscreen";

        button.setAttribute(
            "aria-label",
            isFullscreen ? "Exit fullscreen map" : "Enter fullscreen map",
        );

        button.setAttribute("aria-pressed", String(isFullscreen));
    }

    const icon = $("lm-fullscreen-icon");

    if (icon) {
        icon.innerHTML = isFullscreen
            ? `
                <path d="M8 3v5H3"/>
                <path d="M16 3v5h5"/>
                <path d="M3 16h5v5"/>
                <path d="M21 16h-5v5"/>
              `
            : `
                <path d="M8 3H5a2 2 0 0 0-2 2v3"/>
                <path d="M16 3h3a2 2 0 0 1 2 2v3"/>
                <path d="M3 16v3a2 2 0 0 0 2 2h3"/>
                <path d="M21 16v3a2 2 0 0 1-2 2h-3"/>
              `;
    }

    requestAnimationFrame(() => {
        mapState.map?.invalidateSize();
    });
}

$("lm-map-fullscreen")?.addEventListener("click", toggleMapFullscreen);

document.addEventListener("fullscreenchange", syncMapFullscreen);

// =====================================================
// START APPLICATION
// =====================================================

initialize();
