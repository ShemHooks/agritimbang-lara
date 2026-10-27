import api from "../api";

const PAGE_SIZE = 10;

const COLORS = [
    "#16845b",
    "#2563eb",
    "#d97706",
    "#9333ea",
    "#dc2626",
    "#0891b2",
    "#65a30d",
    "#db2777",
];

const state = {
    points: [],
    filteredPoints: [],
    municipalities: [],
    species: [],
    breeds: [],
    page: 1,
    search: "",
    chartGroup: "species",
    requestId: 0,
};

const $ = (id) => document.getElementById(id);

const money = (value) =>
    Number.isFinite(Number(value))
        ? new Intl.NumberFormat("en-PH", {
              style: "currency",
              currency: "PHP",
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          }).format(Number(value))
        : "—";

const number = (value) => new Intl.NumberFormat("en-PH").format(value || 0);

const dateText = (value) => {
    if (!value) return "—";

    const date = new Date(`${value}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? "—"
        : date.toLocaleDateString("en-PH", {
              year: "numeric",
              month: "short",
              day: "numeric",
          });
};

const average = (values) => {
    const valid = values.filter(Number.isFinite);

    if (!valid.length) return null;

    return valid.reduce((sum, value) => sum + value, 0) / valid.length;
};

const escapeHtml = (value) =>
    String(value ?? "").replace(/[&<>"']/g, (character) => {
        const replacements = {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;",
        };

        return replacements[character];
    });

function extractList(payload) {
    const data = payload?.data ?? payload;

    if (Array.isArray(data)) return data;
    if (Array.isArray(data?.data)) return data.data;
    if (Array.isArray(data?.items)) return data.items;
    if (Array.isArray(data?.municipalities)) return data.municipalities;
    if (Array.isArray(data?.species)) return data.species;
    if (Array.isArray(data?.breeds)) return data.breeds;

    return [];
}

function setLoading(loading) {
    $("pa-loading").hidden = !loading;
    $("pa-refresh").disabled = loading;
}

function showError(message) {
    $("pa-error-message").textContent = message;
    $("pa-error").hidden = false;
}

function clearError() {
    $("pa-error").hidden = true;
}

function setOptions(element, items, placeholder) {
    const previousValue = element.value;

    element.replaceChildren(new Option(placeholder, ""));

    for (const item of items) {
        element.add(new Option(item.name, item.id));
    }

    if ([...element.options].some((option) => option.value === previousValue)) {
        element.value = previousValue;
    }
}

async function loadReferences() {
    const results = await Promise.allSettled([
        api.get("/reference/species"),
        api.get("/gis/municipalities"),
    ]);

    if (results[0].status === "fulfilled") {
        state.species = extractList(results[0].value.data);

        setOptions($("pa-species"), state.species, "All species");
    }

    if (results[1].status === "fulfilled") {
        state.municipalities = extractList(results[1].value.data);

        setOptions(
            $("pa-municipality"),
            state.municipalities,
            "All authorized municipalities",
        );
    }
}

async function loadBreeds() {
    const speciesId = $("pa-species").value;
    const breedSelect = $("pa-breed");

    breedSelect.replaceChildren(new Option("All breeds", ""));
    breedSelect.disabled = true;
    state.breeds = [];

    if (!speciesId) return;

    try {
        const response = await api.get(
            `/reference/species/${encodeURIComponent(speciesId)}/breeds`,
        );

        // Ignore responses for a species the user has already changed.
        if ($("pa-species").value !== speciesId) return;

        state.breeds = extractList(response.data);

        setOptions(breedSelect, state.breeds, "All breeds");
        breedSelect.disabled = false;
    } catch (error) {
        console.error("Unable to load breeds:", error);
    }
}

async function loadPrices() {
    const requestId = ++state.requestId;

    setLoading(true);
    clearError();

    try {
        const params = {};

        const municipalityId = $("pa-municipality").value;
        const speciesId = $("pa-species").value;
        const breedId = $("pa-breed").value;

        if (municipalityId) params.municipality_id = municipalityId;
        if (speciesId) params.species_id = speciesId;
        if (breedId) params.breed_id = breedId;

        const response = await api.get("/analytics/prices", { params });

        if (requestId !== state.requestId) return;

        const data = response.data?.data;

        if (!data || !Array.isArray(data.points)) {
            throw new Error("Unexpected price analytics API response.");
        }

        state.points = data.points
            .map((point) => ({
                ...point,
                price_per_kg: Number(point.price_per_kg),
            }))
            .filter(
                (point) =>
                    Number.isFinite(point.price_per_kg) &&
                    point.price_per_kg >= 0,
            );

        $("pa-content").hidden = false;

        applyFilters();
    } catch (error) {
        if (requestId !== state.requestId) return;

        console.error("Price analytics error:", error);

        showError(
            error.response?.data?.message ||
                error.message ||
                "Unable to load official price references.",
        );
    } finally {
        if (requestId === state.requestId) {
            setLoading(false);
        }
    }
}

function applyFilters() {
    const from = $("pa-date-from").value;
    const to = $("pa-date-to").value;

    state.filteredPoints = state.points.filter((point) => {
        const effectiveDate = point.effective_from;

        if (from && (!effectiveDate || effectiveDate < from)) return false;
        if (to && (!effectiveDate || effectiveDate > to)) return false;

        return true;
    });

    state.page = 1;

    render();
}

function render() {
    renderStats();
    renderTrendChart();
    renderComparisonChart();
    renderTable();
}

function comparableKey(point) {
    return [
        point.municipality_id || "",
        point.barangay_id || "",
        point.species_id || "",
        point.breed_id || "",
        point.sale_purpose || "",
    ].join("|");
}

function renderStats() {
    const points = state.filteredPoints;

    $("pa-reference-count").textContent = number(points.length);

    if (!points.length) {
        $("pa-average-price").textContent = "—";
        $("pa-latest-price").textContent = "—";
        $("pa-price-change").textContent = "—";
        $("pa-latest-description").textContent = "No official references";
        $("pa-change-description").textContent = "No comparable records";
        return;
    }

    const prices = points.map((point) => point.price_per_kg);

    $("pa-average-price").textContent = money(average(prices));

    const chronological = [...points].sort(
        (a, b) =>
            String(a.effective_from || "").localeCompare(
                String(b.effective_from || ""),
            ) || String(a.id).localeCompare(String(b.id)),
    );

    const latest = chronological.at(-1);

    $("pa-latest-price").textContent = money(latest.price_per_kg);

    $("pa-latest-description").textContent =
        `${latest.species_name || "Livestock"} · ${dateText(latest.effective_from)}`;

    // Compare only records matching the latest reference's
    // municipality, barangay, species, breed and sale purpose.
    const comparable = chronological.filter(
        (point) => comparableKey(point) === comparableKey(latest),
    );

    // Aggregate multiple references on the same effective date.
    const byDate = new Map();

    for (const point of comparable) {
        const date = point.effective_from;

        if (!date) continue;

        if (!byDate.has(date)) byDate.set(date, []);

        byDate.get(date).push(point.price_per_kg);
    }

    const dates = [...byDate.keys()].sort();

    if (dates.length < 2) {
        $("pa-price-change").textContent = "—";
        $("pa-change-description").textContent =
            "Requires two comparable dates";
        return;
    }

    const previousPrice = average(byDate.get(dates.at(-2)));
    const currentPrice = average(byDate.get(dates.at(-1)));

    if (!previousPrice || previousPrice <= 0) {
        $("pa-price-change").textContent = "—";
        $("pa-change-description").textContent = "Previous price unavailable";
        return;
    }

    const change = ((currentPrice - previousPrice) / previousPrice) * 100;

    const element = $("pa-price-change");

    element.textContent = `${change > 0 ? "+" : ""}${change.toFixed(2)}%`;

    element.classList.toggle("pa-positive", change > 0);
    element.classList.toggle("pa-negative", change < 0);

    $("pa-change-description").textContent =
        `${dateText(dates.at(-2))} to ${dateText(dates.at(-1))}`;
}

function buildSeries(points) {
    const grouped = new Map();

    for (const point of points) {
        if (!point.effective_from) continue;

        const key =
            state.chartGroup === "breed"
                ? `${point.species_id}|${point.breed_id || "none"}`
                : point.species_id || "unknown";

        const label =
            state.chartGroup === "breed"
                ? `${point.species_name || "Unknown"} · ${point.breed_name || "Unspecified"}`
                : point.species_name || "Unknown";

        if (!grouped.has(key)) {
            grouped.set(key, {
                label,
                dates: new Map(),
            });
        }

        const series = grouped.get(key);

        if (!series.dates.has(point.effective_from)) {
            series.dates.set(point.effective_from, []);
        }

        series.dates.get(point.effective_from).push(point.price_per_kg);
    }

    return [...grouped.values()].map((series) => ({
        label: series.label,
        points: [...series.dates.entries()]
            .map(([date, prices]) => ({
                date,
                value: average(prices),
            }))
            .sort((a, b) => a.date.localeCompare(b.date)),
    }));
}

function renderTrendChart() {
    const container = $("pa-price-chart");
    const legend = $("pa-chart-legend");

    const series = buildSeries(state.filteredPoints);

    legend.replaceChildren();

    if (!series.length) {
        container.innerHTML =
            '<div class="pa-empty">No price history available for the selected filters.</div>';
        return;
    }

    const allPoints = series.flatMap((item) => item.points);
    const dates = [...new Set(allPoints.map((point) => point.date))].sort();
    const values = allPoints.map((point) => point.value);

    const width = 1000;
    const height = 350;

    const margin = {
        top: 25,
        right: 35,
        bottom: 55,
        left: 90,
    };

    const chartWidth = width - margin.left - margin.right;
    const chartHeight = height - margin.top - margin.bottom;

    const minValue = Math.min(...values);
    const maxValue = Math.max(...values);

    const padding = Math.max((maxValue - minValue) * 0.12, 1);

    const yMin = Math.max(0, minValue - padding);
    const yMax = maxValue + padding;

    const xPosition = (date) => {
        const index = dates.indexOf(date);

        return (
            margin.left +
            (dates.length === 1
                ? chartWidth / 2
                : (index / (dates.length - 1)) * chartWidth)
        );
    };

    const yPosition = (value) =>
        margin.top +
        chartHeight -
        ((value - yMin) / (yMax - yMin)) * chartHeight;

    const svg = [];

    svg.push(
        `<svg viewBox="0 0 ${width} ${height}" class="pa-svg" role="img" aria-label="Historical price trend">`,
    );

    for (let i = 0; i <= 4; i++) {
        const value = yMin + ((yMax - yMin) * i) / 4;
        const y = yPosition(value);

        svg.push(`
            <line x1="${margin.left}" y1="${y}"
                x2="${width - margin.right}" y2="${y}"
                class="pa-grid-line" />
            <text x="${margin.left - 12}" y="${y + 4}"
                text-anchor="end" class="pa-axis-label">
                ${money(value)}
            </text>
        `);
    }

    const labelStep = Math.max(1, Math.ceil(dates.length / 6));

    dates.forEach((date, index) => {
        if (index % labelStep !== 0 && index !== dates.length - 1) return;

        svg.push(`
            <text x="${xPosition(date)}" y="${height - 18}"
                text-anchor="middle" class="pa-axis-label">
                ${escapeHtml(dateText(date))}
            </text>
        `);
    });

    series.forEach((item, index) => {
        const color = COLORS[index % COLORS.length];

        const path = item.points
            .map(
                (point, pointIndex) =>
                    `${pointIndex === 0 ? "M" : "L"} ${xPosition(point.date)} ${yPosition(point.value)}`,
            )
            .join(" ");

        if (item.points.length > 1) {
            svg.push(`
                <path d="${path}" fill="none"
                    stroke="${color}" stroke-width="3"
                    stroke-linecap="round"
                    stroke-linejoin="round" />
            `);
        }

        for (const point of item.points) {
            svg.push(`
                <circle cx="${xPosition(point.date)}"
                    cy="${yPosition(point.value)}"
                    r="5" fill="${color}">
                    <title>${escapeHtml(item.label)} — ${escapeHtml(point.date)}: ${money(point.value)}</title>
                </circle>
            `);
        }

        const legendItem = document.createElement("span");

        legendItem.className = "pa-legend-item";
        legendItem.innerHTML = `
            <span class="pa-legend-dot" style="background:${color}"></span>
            <span>${escapeHtml(item.label)}</span>
        `;

        legend.appendChild(legendItem);
    });

    svg.push("</svg>");

    container.innerHTML = svg.join("");
}

function renderComparisonChart() {
    const container = $("pa-comparison-chart");

    const grouped = new Map();

    for (const point of state.filteredPoints) {
        const key = point.species_id || "unknown";

        if (!grouped.has(key)) {
            grouped.set(key, {
                label: point.species_name || "Unknown",
                prices: [],
            });
        }

        grouped.get(key).prices.push(point.price_per_kg);
    }

    const comparisons = [...grouped.values()]
        .map((item) => ({
            label: item.label,
            price: average(item.prices),
        }))
        .sort((a, b) => b.price - a.price);

    if (!comparisons.length) {
        container.innerHTML =
            '<div class="pa-empty">No price comparison data available.</div>';
        return;
    }

    const maximum = Math.max(...comparisons.map((item) => item.price), 1);

    container.innerHTML = comparisons
        .map(
            (item, index) => `
                <div class="pa-comparison-row">
                    <div class="pa-comparison-meta">
                        <span>${escapeHtml(item.label)}</span>
                        <strong>${money(item.price)}</strong>
                    </div>

                    <div class="pa-comparison-track">
                        <div
                            class="pa-comparison-fill"
                            style="width:${Math.max(0, (item.price / maximum) * 100)}%;
                                   background:${COLORS[index % COLORS.length]}"
                        ></div>
                    </div>
                </div>
            `,
        )
        .join("");
}

function renderTable() {
    const search = state.search.trim().toLowerCase();

    const rows = state.filteredPoints
        .filter((point) => {
            if (!search) return true;

            return [
                point.species_name,
                point.breed_name,
                point.sale_purpose,
            ].some((value) =>
                String(value || "")
                    .toLowerCase()
                    .includes(search),
            );
        })
        .sort(
            (a, b) =>
                String(b.effective_from || "").localeCompare(
                    String(a.effective_from || ""),
                ) || String(b.id).localeCompare(String(a.id)),
        );

    const totalPages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));

    state.page = Math.min(Math.max(1, state.page), totalPages);

    const start = (state.page - 1) * PAGE_SIZE;
    const visibleRows = rows.slice(start, start + PAGE_SIZE);

    const body = $("pa-history-body");

    if (!visibleRows.length) {
        body.innerHTML = `
            <tr>
                <td colspan="7" class="pa-empty-cell">
                    No official price references found.
                </td>
            </tr>
        `;
    } else {
        body.innerHTML = visibleRows
            .map(
                (point) => `
                    <tr>
                        <td>${escapeHtml(point.species_name || "—")}</td>
                        <td>${escapeHtml(point.breed_name || "—")}</td>
                        <td>${escapeHtml(
                            String(point.sale_purpose || "—").replaceAll(
                                "_",
                                " ",
                            ),
                        )}</td>
                        <td class="pa-price-cell">
                            ${money(point.price_per_kg)}
                        </td>
                        <td>${dateText(point.effective_from)}</td>
                        <td>${dateText(point.effective_to)}</td>
                        <td>
                            <span class="pa-status">Official</span>
                        </td>
                    </tr>
                `,
            )
            .join("");
    }

    $("pa-page-info").textContent = rows.length
        ? `Showing ${start + 1}–${Math.min(start + PAGE_SIZE, rows.length)} of ${number(rows.length)} references`
        : "0 references";

    $("pa-prev-page").disabled = state.page <= 1;
    $("pa-next-page").disabled = state.page >= totalPages;
}

function bindEvents() {
    $("pa-refresh").addEventListener("click", loadPrices);
    $("pa-retry").addEventListener("click", loadPrices);

    $("pa-municipality").addEventListener("change", loadPrices);

    $("pa-species").addEventListener("change", async () => {
        await loadBreeds();
        await loadPrices();
    });

    $("pa-breed").addEventListener("change", loadPrices);

    $("pa-date-from").addEventListener("change", applyFilters);
    $("pa-date-to").addEventListener("change", applyFilters);

    $("pa-chart-group").addEventListener("change", (event) => {
        state.chartGroup = event.target.value;
        renderTrendChart();
    });

    $("pa-search").addEventListener("input", (event) => {
        state.search = event.target.value;
        state.page = 1;
        renderTable();
    });

    $("pa-prev-page").addEventListener("click", () => {
        state.page--;
        renderTable();
    });

    $("pa-next-page").addEventListener("click", () => {
        state.page++;
        renderTable();
    });

    $("pa-clear-filters").addEventListener("click", async () => {
        $("pa-municipality").value = "";
        $("pa-species").value = "";
        $("pa-breed").value = "";
        $("pa-date-from").value = "";
        $("pa-date-to").value = "";
        $("pa-search").value = "";

        state.search = "";

        await loadBreeds();
        await loadPrices();
    });
}

async function init() {
    if (!$("pa-content")) return;

    bindEvents();

    await loadReferences();
    await loadPrices();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}
