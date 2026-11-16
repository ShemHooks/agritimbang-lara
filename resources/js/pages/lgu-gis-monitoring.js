import api from "../api";
import L from "leaflet";
import "leaflet/dist/leaflet.css";

const $ = (id) => document.getElementById(id);

const INITIAL_BOUNDS = L.latLngBounds([9.0, 121.5], [12.0, 125.5]);

const COLORS = [105, 205, 25, 275, 345, 165, 45, 235, 135, 15, 305, 185];

const state = {
    map: null,
    layer: null,
    bounds: null,
    requestId: 0,
    popupRequestId: 0,
    currentBarangayId: null,
};

const number = (value) =>
    new Intl.NumberFormat("en-PH").format(Number(value) || 0);

const peso = (value) =>
    new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value) || 0);

function escapeHtml(value) {
    return String(value ?? "").replace(
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
}

function getError(error) {
    return (
        error.response?.data?.message ||
        error.message ||
        "Something went wrong."
    );
}

function unwrapList(response) {
    const data = response.data?.data;

    if (Array.isArray(data)) return data;
    if (Array.isArray(data?.data)) return data.data;

    return [];
}

function municipalityColor(id) {
    let hash = 0;

    for (const character of String(id ?? "")) {
        hash = (hash * 31 + character.charCodeAt(0)) | 0;
    }

    return COLORS[(hash >>> 0) % COLORS.length];
}

function featureCount(feature) {
    const properties = feature.properties ?? {};
    return properties.dataset === "verified_sales"
        ? Number(properties.verified_sales_total) || 0
        : Number(properties.total_livestock) || 0;
}

function featureStyle(feature, maximum) {
    const count = featureCount(feature);
    const ratio = maximum > 0 ? count / maximum : 0;

    return {
        color: count === 0 ? "#cbd8cc" : "#39734a",
        weight: 1,
        opacity: 0.95,
        fillColor: count === 0 ? "#edf2ed" : `hsl(132, ${28 + ratio * 28}%, ${91 - ratio * 48}%)`,
        fillOpacity: 0.82,
    };
}

function initializeMap() {
    if (state.map) return;

    state.map = L.map("gis-map", {
        zoomControl: true,
        scrollWheelZoom: false,
        preferCanvas: true,
        minZoom: 4,
        maxZoom: 18,
    });

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap contributors",
        maxZoom: 19,
    }).addTo(state.map);

    state.map.fitBounds(INITIAL_BOUNDS);

    requestAnimationFrame(() => {
        state.map.invalidateSize();
    });
}

function speciesMarkup(items) {
    if (!Array.isArray(items) || !items.length) {
        return `<p class="gis-empty">No livestock species recorded.</p>`;
    }

    return items
        .map(
            (item) => `
        <div class="gis-popup-row">
            <span>${escapeHtml(item.species_name)}</span>
            <strong>${number(item.total)}</strong>
        </div>
    `,
        )
        .join("");
}

function popupMarkup(feature) {
    const p = feature.properties ?? {};
    const sales = p.dataset === "verified_sales";

    const overview = sales
        ? `
            <div class="gis-popup-total">
                <span>Verified sales</span>
                <strong>${peso(p.verified_sales_total)}</strong>
            </div>
            <div class="gis-popup-row">
                <span>Verified transactions</span>
                <strong>${number(p.verified_transactions)}</strong>
            </div>
            <div class="gis-popup-row">
                <span>Livestock items sold</span>
                <strong>${number(p.verified_items)}</strong>
            </div>
        `
        : `
            <div class="gis-popup-total">
                <span>Total livestock</span>
                <strong>${number(p.total_livestock)}</strong>
            </div>
            <div class="gis-popup-row">
                <span>Available</span>
                <strong>${number(p.available)}</strong>
            </div>
            <div class="gis-popup-row">
                <span>In transaction</span>
                <strong>${number(p.in_transaction)}</strong>
            </div>
            <div class="gis-popup-row">
                <span>Sold</span>
                <strong>${number(p.sold)}</strong>
            </div>
        `;

    return `
        <div class="gis-popup">
            <div class="gis-popup-heading">
                <strong>${escapeHtml(p.name ?? "Barangay")}</strong>
                <span>${escapeHtml(p.municipality_name ?? "")}</span>
                <small>${escapeHtml(p.province_name ?? "")}</small>
            </div>

            <div class="gis-popup-section">
                ${overview}
            </div>

            <div class="gis-popup-section">
                <h4>${sales ? "Verified sales by species" : "Livestock by species"}</h4>
                ${speciesMarkup(p.by_species)}
            </div>
        </div>
    `;
}

function openBarangay(feature, layer) {
    layer.bindPopup(popupMarkup(feature), {
        minWidth: 250,
        maxWidth: 340,
        maxHeight: 440,
        autoPan: true,
    }).openPopup();
}

function renderSummary(features) {
    const sales = $("gis-dataset").value === "verified_sales";

    const total = features.reduce(
        (sum, feature) => sum + featureCount(feature),
        0,
    );

    const available = features.reduce(
        (sum, feature) => sum + (Number(feature.properties?.available) || 0),
        0,
    );

    const transactions = features.reduce(
        (sum, feature) =>
            sum + (Number(feature.properties?.verified_transactions) || 0),
        0,
    );

    const speciesTotals = new Map();
    let leading = null;

    for (const feature of features) {
        const properties = feature.properties ?? {};
        const value = featureCount(feature);

        if (!leading || value > leading.value) {
            leading = {
                name: properties.name ?? "—",
                value,
            };
        }

        for (const item of properties.by_species ?? []) {
            const name = item.species_name ?? "Unknown";

            speciesTotals.set(
                name,
                (speciesTotals.get(name) ?? 0) + (Number(item.total) || 0),
            );
        }
    }

    const topSpecies =
        [...speciesTotals.entries()].sort((a, b) => b[1] - a[1])[0]?.[0] ?? "—";

    $("gis-stat-one-label").textContent = sales
        ? "Verified sales value"
        : "Total livestock";

    $("gis-stat-one").textContent = sales ? peso(total) : number(total);

    $("gis-stat-two-label").textContent = sales
        ? "Verified transactions"
        : "Available";

    $("gis-stat-two").textContent = number(sales ? transactions : available);

    $("gis-stat-three-label").textContent = "Top species";
    $("gis-stat-three").textContent = topSpecies;

    $("gis-stat-four-label").textContent = "Leading barangay";
    $("gis-stat-four").textContent = leading?.value > 0 ? leading.name : "—";

    $("gis-map-caption").textContent = sales
        ? "Verified sales are grouped by the recorded transaction barangay."
        : "Livestock inventory is grouped by the barangay of record.";
}

function renderGeojson(collection) {
    if (state.layer) {
        state.map.removeLayer(state.layer);
        state.layer = null;
    }

    const features = (collection.features ?? []).filter(
        (feature) => feature.properties?.municipality_is_active !== false,
    );

    renderSummary(features);

    const maximums = new Map();

    for (const feature of features) {
        const municipalityId = feature.properties?.municipality_id;

        maximums.set(
            municipalityId,
            Math.max(maximums.get(municipalityId) ?? 0, featureCount(feature)),
        );
    }

    state.layer = L.geoJSON(
        {
            type: "FeatureCollection",
            features,
        },
        {
            style: (feature) =>
                featureStyle(
                    feature,
                    maximums.get(feature.properties?.municipality_id) ?? 0,
                ),

            onEachFeature(feature, layer) {
                const p = feature.properties ?? {};

                const isSales = p.dataset === "verified_sales";

                const hoverValue = isSales
                    ? `${peso(p.verified_sales_total)} verified sales`
                    : `${number(p.total_livestock)} livestock`;

                layer.bindTooltip(
                    `<strong>${escapeHtml(p.name ?? "Barangay")}</strong><br>${hoverValue}`,
                    {
                        sticky: true,
                        direction: "top",
                    },
                );

                layer.on("mouseover", () => {
                    layer.setStyle({
                        weight: 2.5,
                        fillOpacity: 0.94,
                    });

                    layer.bringToFront?.();
                });

                layer.on("mouseout", () => {
                    state.layer?.resetStyle(layer);
                });

                layer.on("click", () => {
                    openBarangay(feature, layer);
                });
            },
        },
    ).addTo(state.map);

    if (state.layer.getLayers().length) {
        const bounds = state.layer.getBounds();

        if (bounds.isValid()) {
            state.bounds = bounds;

            state.map.fitBounds(bounds, {
                padding: [24, 24],
                maxZoom: 12,
            });
        }
    } else {
        state.bounds = INITIAL_BOUNDS;
        state.map.fitBounds(INITIAL_BOUNDS);
    }

    const municipalities = new Set(
        features.map((feature) => feature.properties?.municipality_id),
    );

    $("gis-map-summary").textContent =
        `${$("gis-dataset").value === "verified_sales" ? "Verified transactions" : "Livestock inventory"} · ` +
        `${number(municipalities.size)} municipalities · ${number(features.length)} barangays`;

    requestAnimationFrame(() => {
        state.map.invalidateSize();
    });
}

async function loadMap() {
    const requestId = ++state.requestId;

    ++state.popupRequestId;
    state.currentBarangayId = null;

    const params = {};

    if ($("gis-municipality").value) {
        params.municipality_id = $("gis-municipality").value;
    }

    if ($("gis-species").value) {
        params.species_id = $("gis-species").value;
    }

    params.dataset = $("gis-dataset").value || "livestock";

    if (params.dataset === "livestock" && $("gis-status").value) {
        params.status = $("gis-status").value;
    }

    if (params.dataset === "verified_sales") {
        if ($("gis-sale-purpose").value) {
            params.sale_purpose = $("gis-sale-purpose").value;
        }

        if ($("gis-date-from").value) {
            params.date_from = $("gis-date-from").value;
        }

        if ($("gis-date-to").value) {
            params.date_to = $("gis-date-to").value;
        }
    }

    $("gis-map-loading").hidden = false;
    $("gis-error").hidden = true;

    try {
        const response = await api.get("/lgu/gis-monitoring/geojson", {
            params,
        });

        if (requestId !== state.requestId) return;

        const collection =
            response.data?.type === "FeatureCollection"
                ? response.data
                : response.data?.data;

        if (collection?.type !== "FeatureCollection") {
            throw new Error("Invalid GIS GeoJSON response.");
        }

        renderGeojson(collection);
    } catch (error) {
        if (requestId !== state.requestId) return;

        $("gis-error").textContent = getError(error);
        $("gis-error").hidden = false;

        $("gis-map-summary").textContent = "Unable to load geographic data.";
    } finally {
        if (requestId === state.requestId) {
            $("gis-map-loading").hidden = true;
        }
    }
}

function syncDatasetFilters() {
    const sales = $("gis-dataset").value === "verified_sales";

    $("gis-status-filter").hidden = sales;

    ["gis-purpose-filter", "gis-date-from-wrap", "gis-date-to-wrap"].forEach(
        (id) => {
            $(id).hidden = !sales;
        },
    );
}

async function loadFilters() {
    const [municipalities, species] = await Promise.all([
        api.get("/gis/municipalities"),
        api.get("/reference/species"),
    ]);

    const municipalitySelect = $("gis-municipality");
    const speciesSelect = $("gis-species");

    municipalitySelect.replaceChildren(
        new Option("All municipalities", ""),
    );

    speciesSelect.replaceChildren(
        new Option("All species", ""),
    );

    for (const municipality of unwrapList(municipalities)) {
        municipalitySelect.add(
            new Option(
                `${municipality.name} · ${municipality.province}`,
                municipality.id,
            ),
        );
    }

    for (const item of unwrapList(species)) {
        speciesSelect.add(new Option(item.name, item.id));
    }
}

function resetView() {
    $("gis-municipality").value = "";
    $("gis-species").value = "";
    $("gis-dataset").value = "livestock";
    $("gis-status").value = "";
    $("gis-sale-purpose").value = "";
    $("gis-date-from").value = "";
    $("gis-date-to").value = "";

    syncDatasetFilters();
    loadMap();

    if (state.map) {
        state.map.fitBounds(state.bounds ?? INITIAL_BOUNDS, {
            padding: [24, 24],
            maxZoom: 12,
        });
    }
}

async function toggleFullscreen() {
    const wrapper = $("gis-map-stage") || $("gis-map").parentElement;

    try {
        if (document.fullscreenElement === wrapper) {
            await document.exitFullscreen();
        } else if (!document.fullscreenElement) {
            await wrapper.requestFullscreen();
        }
    } catch (error) {
        console.error("GIS fullscreen error:", error);
    }
}

async function initialize() {
    if (!sessionStorage.getItem("agritimbang_token")) {
        return;
    }

    try {
        const response = await api.get("/auth/me");
        const user = response.data?.data;

        const roles = (user?.roles ?? []).map((role) =>
            String(typeof role === "string" ? role : role.slug)
                .toLowerCase()
                .replace(/[-\s]+/g, "_"),
        );

        if (!roles.includes("lgu_authority")) {
            throw new Error("Only LGU Authorities may access this page.");
        }

        initializeMap();

        setTimeout(() => {
            state.map?.invalidateSize();
        }, 150);

        await loadFilters();

        syncDatasetFilters();
        await loadMap();
    } catch (error) {
        $("gis-error").textContent = getError(error);
        $("gis-error").hidden = false;
    }
}

$("gis-municipality")?.addEventListener("change", loadMap);
$("gis-species")?.addEventListener("change", loadMap);

$("gis-dataset")?.addEventListener("change", () => {
    syncDatasetFilters();

    if ($("gis-dataset").value === "verified_sales") {
        $("gis-status").value = "";
    } else {
        $("gis-sale-purpose").value = "";
        $("gis-date-from").value = "";
        $("gis-date-to").value = "";
    }

    loadMap();
});

$("gis-status")?.addEventListener("change", loadMap);
$("gis-sale-purpose")?.addEventListener("change", loadMap);
$("gis-date-from")?.addEventListener("change", loadMap);
$("gis-date-to")?.addEventListener("change", loadMap);

$("gis-reset")?.addEventListener("click", resetView);
$("gis-fullscreen")?.addEventListener("click", toggleFullscreen);

document.addEventListener("fullscreenchange", () => {
    const fullscreen =
        document.fullscreenElement === $("gis-map").parentElement;

    $("gis-fullscreen").textContent = fullscreen
        ? "Exit fullscreen"
        : "Fullscreen";

    state.map?.invalidateSize();
});

window.addEventListener("resize", () => {
    state.map?.invalidateSize();
});

initialize();