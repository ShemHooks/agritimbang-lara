import api from "../api";

/* ============================================================
   AGRITIMBANG — VALUATION ANALYTICS
   API: GET /api/analytics/valuations
   ============================================================ */

const $ = (id) => document.getElementById(id);

const PAGE_SIZE = 10;

const WEIGHT_COLORS = {
    actual: "#16845b",
    estimated: "#e8a23c",
};

const FACTOR_LABELS = {
    breed_factor: "Breed Factor",
    age_factor: "Age Factor",
    reproductive_factor: "Reproductive Factor",
    frame_factor: "Frame Factor",
    condition_factor: "Condition Factor",
};

const state = {
    byWeightType: [],
    bySpecies: [],
    monthly: [],
    filteredMonthly: [],
    adjustments: {},
    calculationCount: 0,
    averageValue: 0,
    inventoryValue: 0,
    municipalities: [],
    year: "",
    chartMetric: "count",
    page: 1,
    requestId: 0,
};

const number = (value) => Number(value) || 0;

const formatNumber = (value) =>
    new Intl.NumberFormat("en-PH").format(number(value));

const formatMoney = (value) =>
    new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(number(value));

const formatCompactMoney = (value) =>
    new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
        notation: "compact",
        maximumFractionDigits: 1,
    }).format(number(value));

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

function formatMonth(value) {
    if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(String(value ?? ""))) {
        return value || "—";
    }

    const [year, month] = value.split("-").map(Number);

    return new Intl.DateTimeFormat("en-PH", {
        month: "long",
        year: "numeric",
    }).format(new Date(year, month - 1, 1));
}

function extractList(payload) {
    const data = payload?.data ?? payload;

    if (Array.isArray(data)) return data;
    if (Array.isArray(data?.data)) return data.data;
    if (Array.isArray(data?.items)) return data.items;
    if (Array.isArray(data?.municipalities)) return data.municipalities;

    return [];
}

function setLoading(loading) {
    $("va-loading").hidden = !loading;
    $("va-refresh").disabled = loading;
}

function showError(message) {
    $("va-error-message").textContent = message;
    $("va-error").hidden = false;
}

function clearError() {
    $("va-error").hidden = true;
}

function sum(rows, key) {
    return rows.reduce((total, row) => total + number(row[key]), 0);
}

/* ============================================================
   MUNICIPALITY FILTER
   ============================================================ */

async function loadMunicipalities() {
    const select = $("va-municipality");

    try {
        const response = await api.get("/gis/municipalities");
        const municipalities = extractList(response.data);

        state.municipalities = municipalities;

        select.replaceChildren(new Option("All authorized municipalities", ""));

        municipalities.forEach((municipality) => {
            if (!municipality.id || !municipality.name) return;

            select.add(new Option(municipality.name, municipality.id));
        });
    } catch (error) {
        console.error("Municipality lookup failed:", error);

        // The analytics API still resolves the user's authorized
        // municipality when no explicit filter is supplied.
        select.disabled = true;
    }
}

/* ============================================================
   LOAD ANALYTICS
   ============================================================ */

async function loadAnalytics() {
    const requestId = ++state.requestId;

    setLoading(true);
    clearError();

    try {
        const params = {};

        const municipalityId = $("va-municipality").value;

        if (municipalityId) {
            params.municipality_id = municipalityId;
        }

        const response = await api.get("/analytics/valuations", { params });

        if (requestId !== state.requestId) return;

        const data = response.data?.data;

        if (
            !data ||
            !Array.isArray(data.by_weight_type) ||
            !Array.isArray(data.by_species) ||
            !Array.isArray(data.monthly)
        ) {
            throw new Error("Unexpected valuation analytics response.");
        }

        state.calculationCount = number(data.calculation_count);

        state.averageValue = number(data.average_estimated_value);

        state.inventoryValue = number(data.latest_inventory_value);

        state.byWeightType = data.by_weight_type.map((item) => ({
            weight_type: String(item.weight_type || "unknown").toLowerCase(),
            total: number(item.total),
        }));

        state.bySpecies = data.by_species.map((item) => ({
            id: item.id,
            name: String(item.name || "Unknown Species"),
            calculation_count: number(item.calculation_count),
            average_value: number(item.average_value),
        }));

        state.monthly = data.monthly
            .map((item) => ({
                month: String(item.month || ""),
                calculation_count: number(item.calculation_count),
                average_value: number(item.average_value),
            }))
            .filter((item) => /^\d{4}-(0[1-9]|1[0-2])$/.test(item.month))
            .sort((a, b) => a.month.localeCompare(b.month));

        state.adjustments = data.average_adjustments || {};

        $("va-content").hidden = false;

        updateScope();
        populateYears();
        applyYearFilter();
    } catch (error) {
        if (requestId !== state.requestId) return;

        console.error("Valuation Analytics Error:", error);

        showError(
            error.response?.data?.message ||
                error.message ||
                "Unable to load valuation analytics.",
        );
    } finally {
        if (requestId === state.requestId) {
            setLoading(false);
        }
    }
}

function updateScope() {
    const id = $("va-municipality").value;

    if (!id) {
        $("va-scope").textContent =
            "Valuation analytics for your authorized geographic scope.";
        return;
    }

    const municipality = state.municipalities.find(
        (item) => String(item.id) === id,
    );

    $("va-scope").textContent = municipality
        ? `Valuation analytics for ${municipality.name}.`
        : "Valuation analytics for the selected municipality.";
}

/* ============================================================
   YEAR FILTER
   ============================================================ */

function populateYears() {
    const select = $("va-year");
    const previousYear = state.year;

    const years = [
        ...new Set(state.monthly.map((item) => item.month.substring(0, 4))),
    ].sort((a, b) => b.localeCompare(a));

    select.replaceChildren(new Option("All Years", ""));

    years.forEach((year) => {
        select.add(new Option(year, year));
    });

    if (years.includes(previousYear)) {
        select.value = previousYear;
    } else {
        select.value = "";
        state.year = "";
    }
}

function applyYearFilter() {
    state.year = $("va-year").value;

    state.filteredMonthly = state.year
        ? state.monthly.filter((item) =>
              item.month.startsWith(`${state.year}-`),
          )
        : [...state.monthly];

    state.page = 1;

    render();
}

/* ============================================================
   RENDER
   ============================================================ */

function render() {
    renderKPIs();
    renderWeightMethods();
    renderAdjustments();
    renderMonthlyChart();
    renderSpecies();
    renderMonthlyTable();
}

/* ============================================================
   KPI CARDS
   ============================================================ */

function renderKPIs() {
    const actualCount = state.byWeightType
        .filter((item) => item.weight_type === "actual")
        .reduce((total, item) => total + item.total, 0);

    const actualRate =
        state.calculationCount > 0
            ? (actualCount / state.calculationCount) * 100
            : 0;

    $("va-total-calculations").textContent = formatNumber(
        state.calculationCount,
    );

    $("va-average-value").textContent = formatMoney(state.averageValue);

    $("va-inventory-value").textContent = formatMoney(state.inventoryValue);

    $("va-actual-rate").textContent = `${actualRate.toFixed(1)}%`;
}

/* ============================================================
   WEIGHT METHOD DISTRIBUTION
   ============================================================ */

function renderWeightMethods() {
    const chart = $("va-weight-chart");
    const legend = $("va-weight-legend");

    chart.replaceChildren();
    legend.replaceChildren();

    const total = sum(state.byWeightType, "total");

    if (!total) {
        chart.innerHTML = `
            <div class="va-empty">
                No weight method data available yet.
            </div>
        `;
        return;
    }

    const bar = document.createElement("div");
    bar.className = "va-weight-bar";

    state.byWeightType.forEach((item) => {
        if (item.total <= 0) return;

        const percentage = (item.total / total) * 100;
        const color = WEIGHT_COLORS[item.weight_type] || "#94a3b8";

        const segment = document.createElement("div");

        segment.className = "va-weight-segment";
        segment.style.width = `${percentage}%`;
        segment.style.background = color;

        segment.title =
            `${item.weight_type}: ${formatNumber(item.total)} ` +
            `(${percentage.toFixed(1)}%)`;

        bar.appendChild(segment);

        const legendItem = document.createElement("div");
        legendItem.className = "va-weight-legend-item";

        legendItem.innerHTML = `
            <span class="va-weight-dot"
                  style="background:${color}"></span>

            <span class="va-weight-name">
                ${escapeHtml(
                    item.weight_type === "actual"
                        ? "Actual Weight"
                        : item.weight_type === "estimated"
                          ? "Estimated Weight"
                          : item.weight_type,
                )}
            </span>

            <strong>${formatNumber(item.total)}</strong>

            <small>${percentage.toFixed(1)}%</small>
        `;

        legend.appendChild(legendItem);
    });

    chart.appendChild(bar);

    const summary = document.createElement("div");
    summary.className = "va-weight-total";

    summary.innerHTML = `
        <strong>${formatNumber(total)}</strong>
        <span>Total weight-based calculations</span>
    `;

    chart.appendChild(summary);
}

/* ============================================================
   AVERAGE FPC ADJUSTMENT FACTORS
   ============================================================ */

function renderAdjustments() {
    const container = $("va-adjustments");

    if (state.calculationCount === 0) {
        container.innerHTML = `
            <div class="va-empty">
                No FPC adjustment data available yet.
            </div>
        `;
        return;
    }

    container.innerHTML = Object.entries(FACTOR_LABELS)
        .map(([key, label]) => {
            const value = number(state.adjustments[key]);

            return `
                <div class="va-adjustment-item">
                    <div class="va-adjustment-heading">
                        <span>${escapeHtml(label)}</span>
                        <strong>${value.toFixed(4)}×</strong>
                    </div>

                    <div class="va-adjustment-track">
                        <div
                            class="va-adjustment-fill"
                            style="width:${Math.min(
                                100,
                                Math.max(0, (value / 2) * 100),
                            )}%"
                        ></div>
                    </div>
                </div>
            `;
        })
        .join("");
}

/* ============================================================
   MONTHLY VALUATION CHART
   ============================================================ */

function renderMonthlyChart() {
    const container = $("va-monthly-chart");
    const rows = state.filteredMonthly;

    if (!rows.length) {
        container.innerHTML = `
            <div class="va-empty">
                No valuation activity found
                for the selected reporting period.
            </div>
        `;
        return;
    }

    const metric = state.chartMetric;

    const values = rows.map((row) =>
        metric === "average" ? row.average_value : row.calculation_count,
    );

    const width = 1000;
    const height = 370;

    const margin = {
        top: 30,
        right: 30,
        bottom: 65,
        left: 90,
    };

    const chartWidth = width - margin.left - margin.right;
    const chartHeight = height - margin.top - margin.bottom;

    const maxValue = Math.max(...values, 1);
    const yMax = maxValue * 1.15;

    const yPosition = (value) =>
        margin.top + chartHeight - (value / yMax) * chartHeight;

    const bandWidth = chartWidth / rows.length;
    const barWidth = Math.min(55, bandWidth * 0.62);

    const svg = [];

    svg.push(`
        <svg viewBox="0 0 ${width} ${height}"
             class="va-svg"
             role="img"
             aria-label="Monthly valuation activity chart">
    `);

    for (let i = 0; i <= 4; i++) {
        const value = (yMax * i) / 4;
        const y = yPosition(value);

        const label =
            metric === "average"
                ? formatCompactMoney(value)
                : formatNumber(Math.round(value));

        svg.push(`
            <line
                x1="${margin.left}"
                y1="${y}"
                x2="${width - margin.right}"
                y2="${y}"
                class="va-grid-line"
            />

            <text
                x="${margin.left - 12}"
                y="${y + 4}"
                text-anchor="end"
                class="va-axis-label"
            >
                ${escapeHtml(label)}
            </text>
        `);
    }

    const labelStep = Math.max(1, Math.ceil(rows.length / 10));

    rows.forEach((row, index) => {
        const value = values[index];

        const centerX = margin.left + index * bandWidth + bandWidth / 2;

        const x = centerX - barWidth / 2;
        const y = yPosition(value);

        const barHeight = margin.top + chartHeight - y;

        const tooltip =
            metric === "average"
                ? formatMoney(value)
                : `${formatNumber(value)} calculations`;

        svg.push(`
            <rect
                x="${x}"
                y="${y}"
                width="${barWidth}"
                height="${Math.max(0, barHeight)}"
                rx="5"
                fill="#16845b"
                class="va-chart-bar"
            >
                <title>
                    ${escapeHtml(formatMonth(row.month))}:
                    ${escapeHtml(tooltip)}
                </title>
            </rect>
        `);

        if (index % labelStep === 0 || index === rows.length - 1) {
            svg.push(`
                <text
                    x="${centerX}"
                    y="${height - 22}"
                    text-anchor="middle"
                    class="va-axis-label"
                >
                    ${escapeHtml(row.month)}
                </text>
            `);
        }
    });

    svg.push("</svg>");

    container.innerHTML = svg.join("");
}

/* ============================================================
   SPECIES BREAKDOWN
   ============================================================ */

function renderSpecies() {
    const container = $("va-species-chart");

    if (!state.bySpecies.length) {
        container.innerHTML = `
            <div class="va-empty">
                No species valuation records available yet.
            </div>
        `;
        return;
    }

    const sorted = [...state.bySpecies].sort(
        (a, b) => b.calculation_count - a.calculation_count,
    );

    const max = Math.max(...sorted.map((item) => item.calculation_count), 1);

    container.innerHTML = sorted
        .map((item) => {
            const percentage = (item.calculation_count / max) * 100;

            return `
            <div class="va-species-item">
                <div class="va-species-heading">
                    <div>
                        <strong>${escapeHtml(item.name)}</strong>
                        <span>
                            ${formatNumber(item.calculation_count)}
                            calculations
                        </span>
                    </div>

                    <span class="va-species-value">
                        ${formatMoney(item.average_value)} avg.
                    </span>
                </div>

                <div class="va-species-track">
                    <div
                        class="va-species-fill"
                        style="width:${percentage}%"
                    ></div>
                </div>
            </div>
        `;
        })
        .join("");
}

/* ============================================================
   MONTHLY HISTORY TABLE
   ============================================================ */

function renderMonthlyTable() {
    const rows = [...state.filteredMonthly].sort((a, b) =>
        b.month.localeCompare(a.month),
    );

    const totalPages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));

    state.page = Math.min(Math.max(1, state.page), totalPages);

    const start = (state.page - 1) * PAGE_SIZE;
    const visible = rows.slice(start, start + PAGE_SIZE);

    const body = $("va-monthly-body");

    if (!visible.length) {
        body.innerHTML = `
            <tr>
                <td colspan="3" class="va-empty-cell">
                    No monthly valuation records found.
                </td>
            </tr>
        `;
    } else {
        body.innerHTML = visible
            .map(
                (row) => `
            <tr>
                <td>
                    <strong>
                        ${escapeHtml(formatMonth(row.month))}
                    </strong>
                </td>

                <td>
                    ${formatNumber(row.calculation_count)}
                </td>

                <td class="va-money-cell">
                    ${formatMoney(row.average_value)}
                </td>
            </tr>
        `,
            )
            .join("");
    }

    $("va-page-info").textContent = rows.length
        ? `Showing ${start + 1}–${Math.min(
              start + PAGE_SIZE,
              rows.length,
          )} of ${formatNumber(rows.length)} months`
        : "0 months";

    $("va-prev-page").disabled = state.page <= 1;
    $("va-next-page").disabled = state.page >= totalPages;
}

/* ============================================================
   EVENTS
   ============================================================ */

function bindEvents() {
    $("va-refresh").addEventListener("click", loadAnalytics);

    $("va-retry").addEventListener("click", loadAnalytics);

    $("va-municipality").addEventListener("change", loadAnalytics);

    $("va-year").addEventListener("change", applyYearFilter);

    $("va-chart-metric").addEventListener("change", (event) => {
        state.chartMetric = event.target.value;
        renderMonthlyChart();
    });

    $("va-clear-filters").addEventListener("click", async () => {
        $("va-municipality").value = "";
        $("va-year").value = "";

        state.year = "";
        state.chartMetric = "count";

        $("va-chart-metric").value = "count";

        await loadAnalytics();
    });

    $("va-prev-page").addEventListener("click", () => {
        state.page--;
        renderMonthlyTable();
    });

    $("va-next-page").addEventListener("click", () => {
        state.page++;
        renderMonthlyTable();
    });
}

/* ============================================================
   INITIALIZATION
   ============================================================ */

async function init() {
    if (!$("va-content")) return;

    bindEvents();

    await loadMunicipalities();
    await loadAnalytics();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}
