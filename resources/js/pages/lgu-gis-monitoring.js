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
    return Number(feature.properties?.total_livestock) || 0;
}

function featureStyle(feature, maximum) {
    const hue = municipalityColor(feature.properties?.municipality_id);

    const count = featureCount(feature);
    const ratio = maximum > 0 ? count / maximum : 0;

    return {
        color: `hsl(${hue}, 55%, 30%)`,
        weight: 1,
        opacity: 0.95,
        fillColor:
            count === 0
                ? `hsl(${hue}, 45%, 90%)`
                : `hsl(${hue}, 60%, ${82 - ratio * 38}%)`,
        fillOpacity: 0.76,
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

    return `
        <div class="gis-popup">
            <div class="gis-popup-heading">
                <strong>${escapeHtml(p.name ?? "Barangay")}</strong>
                <span>${escapeHtml(p.municipality_name ?? "")}</span>
                <small>${escapeHtml(p.province_name ?? "")}</small>
            </div>

            <div class="gis-popup-section">
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
                <div class="gis-popup-row">
                    <span>Archived</span>
                    <strong>${number(p.archived)}</strong>
                </div>
            </div>

            <div class="gis-popup-section">
                <h4>Livestock by Species</h4>
                ${speciesMarkup(p.by_species)}
            </div>

            <div class="gis-popup-section">
                <h4>Official Livestock Prices</h4>
                <div data-gis-prices>
                    <p class="gis-empty">Loading official prices...</p>
                </div>
            </div>
        </div>
    `;
}

function priceMarkup(prices) {
    if (!Array.isArray(prices) || !prices.length) {
        return `<p class="gis-empty">No official price available</p>`;
    }

    return prices
        .map(
            (price) => `
        <div class="gis-price-row">
            <div class="gis-price-details">
                <strong>${escapeHtml(price.species_name ?? "Species")}</strong>
                <span>
                    ${escapeHtml(price.sale_purpose ?? "Unspecified purpose")}
                    · ${escapeHtml(price.breed_name ?? "All breeds")}
                </span>
                <small>
                    Effective ${escapeHtml(price.effective_from ?? "N/A")}
                    ${
                        price.effective_to
                            ? ` to ${escapeHtml(price.effective_to)}`
                            : ""
                    }
                </small>
            </div>

            <strong class="gis-price-value">
                ${peso(price.price_per_kg)}/kg
            </strong>
        </div>
    `,
        )
        .join("");
}

async function openBarangay(feature, layer) {
    const barangayId = feature.properties?.barangay_id;
    if (!barangayId) return;

    const requestId = ++state.popupRequestId;
    state.currentBarangayId = barangayId;

    layer
        .bindPopup(popupMarkup(feature), {
            minWidth: 270,
            maxWidth: 360,
            maxHeight: 440,
            autoPan: true,
        })
        .openPopup();

    try {
        const response = await api.get(
            `/lgu/gis-monitoring/barangays/${encodeURIComponent(barangayId)}/prices`,
        );

        if (
            requestId !== state.popupRequestId ||
            state.currentBarangayId !== barangayId ||
            !layer.isPopupOpen()
        ) {
            return;
        }

        const container = layer
            .getPopup()
            ?.getElement()
            ?.querySelector("[data-gis-prices]");

        if (container) {
            container.innerHTML = priceMarkup(
                response.data?.data?.prices ?? [],
            );

            layer.getPopup().update();
        }
    } catch (error) {
        if (requestId !== state.popupRequestId || !layer.isPopupOpen()) {
            return;
        }

        const container = layer
            .getPopup()
            ?.getElement()
            ?.querySelector("[data-gis-prices]");

        if (container) {
            container.textContent = "Unable to load official prices.";
        }

        console.error("GIS price error:", error);
    }
}

function renderGeojson(collection) {
    if (state.layer) {
        state.map.removeLayer(state.layer);
        state.layer = null;
    }

    const features = (collection.features ?? []).filter(
        (feature) => feature.properties?.municipality_is_active !== false,
    );

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

                layer.bindTooltip(
                    `${escapeHtml(p.name ?? "Barangay")} · ${number(p.total_livestock)} livestock`,
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
        `${number(municipalities.size)} municipalities · ` +
        `${number(features.length)} barangay polygons`;

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

async function loadFilters() {
    const [municipalities, species] = await Promise.all([
        api.get("/gis/municipalities"),
        api.get("/reference/species"),
    ]);

    const municipalitySelect = $("gis-municipality");
    const speciesSelect = $("gis-species");

    municipalitySelect.replaceChildren(new Option("All municipalities", ""));

    speciesSelect.replaceChildren(new Option("All species", ""));

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
    if (!state.map) return;

    state.map.fitBounds(state.bounds ?? INITIAL_BOUNDS, {
        padding: [24, 24],
        maxZoom: 12,
    });
}

async function toggleFullscreen() {
    const wrapper = $("gis-map-wrapper");

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
        await loadMap();
    } catch (error) {
        $("gis-error").textContent = getError(error);
        $("gis-error").hidden = false;
    }
}

$("gis-municipality")?.addEventListener("change", loadMap);
$("gis-species")?.addEventListener("change", loadMap);
$("gis-reset")?.addEventListener("click", resetView);
$("gis-fullscreen")?.addEventListener("click", toggleFullscreen);

document.addEventListener("fullscreenchange", () => {
    const fullscreen = document.fullscreenElement === $("gis-map-wrapper");

    $("gis-fullscreen").textContent = fullscreen
        ? "Exit fullscreen"
        : "Fullscreen";

    state.map?.invalidateSize();
});

window.addEventListener("resize", () => {
    state.map?.invalidateSize();
});

initialize();
