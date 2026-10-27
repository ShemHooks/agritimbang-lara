import api from "../api";

/* ============================================================
   AGRITIMBANG — TRANSACTION ANALYTICS
   Endpoint: GET /api/analytics/transactions
   Roles: admin, lgu_authority, lgu_encoder
   ============================================================ */

const PAGE_SIZE = 10;

const STATUS_COLORS = {
    draft: "#94a3b8",
    pending: "#e8a23c",
    verified: "#16845b",
    rejected: "#dc5252",
    cancelled: "#64748b",
};

const STATUS_LABELS = {
    draft: "Draft",
    pending: "Pending",
    verified: "Verified",
    rejected: "Rejected",
    cancelled: "Cancelled",
};

const $ = (id) => document.getElementById(id);

const state = {
    byStatus: [],
    verifiedMonthly: [],
    filteredMonthly: [],
    municipalities: [],
    year: "",
    chartMetric: "sales",
    page: 1,
    requestId: 0,
};

/* ============================================================
   FORMATTERS
   ============================================================ */

const formatNumber = (value) =>
    new Intl.NumberFormat("en-PH").format(Number(value) || 0);

const formatMoney = (value) =>
    new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value) || 0);

const formatCompactMoney = (value) =>
    new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
        notation: "compact",
        maximumFractionDigits: 1,
    }).format(Number(value) || 0);

const escapeHtml = (value) =>
    String(value ?? "").replace(/[&<>"']/g, (character) => {
        const replacements = {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;",
        };

        return replacements[character];
    });

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

function sum(items, key) {
    return items.reduce((total, item) => total + (Number(item[key]) || 0), 0);
}

function setLoading(loading) {
    $("ta-loading").hidden = !loading;
    $("ta-refresh").disabled = loading;
}

function showError(message) {
    $("ta-error-message").textContent = message;
    $("ta-error").hidden = false;
}

function clearError() {
    $("ta-error").hidden = true;
}

/* ============================================================
   MUNICIPALITY REFERENCES
   ============================================================ */

async function loadMunicipalities() {
    const select = $("ta-municipality");

    try {
        const response = await api.get("/gis/municipalities");
        const municipalities = extractList(response.data);

        state.municipalities = municipalities;

        select.replaceChildren(new Option("All authorized municipalities", ""));

        for (const municipality of municipalities) {
            if (!municipality.id || !municipality.name) continue;

            select.add(new Option(municipality.name, municipality.id));
        }
    } catch (error) {
        console.error("Unable to load municipalities:", error);

        // Analytics can still load using the backend-resolved
        // municipality scope even when lookup data is unavailable.
        select.disabled = true;
    }
}

/* ============================================================
   LOAD TRANSACTION ANALYTICS
   ============================================================ */

async function loadTransactions() {
    const requestId = ++state.requestId;

    setLoading(true);
    clearError();

    try {
        const params = {};

        const municipalityId = $("ta-municipality").value;

        if (municipalityId) {
            params.municipality_id = municipalityId;
        }

        const response = await api.get("/analytics/transactions", { params });

        if (requestId !== state.requestId) return;

        const data = response.data?.data;

        if (
            !data ||
            !Array.isArray(data.by_status) ||
            !Array.isArray(data.verified_monthly)
        ) {
            throw new Error("Unexpected transaction analytics API response.");
        }

        state.byStatus = data.by_status.map((item) => ({
            status: String(item.status || "").toLowerCase(),
            total: Number(item.total) || 0,
        }));

        state.verifiedMonthly = data.verified_monthly
            .map((item) => ({
                month: String(item.month || ""),
                transaction_count: Number(item.transaction_count) || 0,
                selling_total: Number(item.selling_total) || 0,
            }))
            .filter((item) => /^\d{4}-(0[1-9]|1[0-2])$/.test(item.month))
            .sort((a, b) => a.month.localeCompare(b.month));

        $("ta-content").hidden = false;

        updateScope();
        populateYears();
        applyYearFilter();
    } catch (error) {
        if (requestId !== state.requestId) return;

        console.error("Transaction Analytics Error:", error);

        showError(
            error.response?.data?.message ||
                error.message ||
                "Unable to load transaction analytics.",
        );
    } finally {
        if (requestId === state.requestId) {
            setLoading(false);
        }
    }
}

function updateScope() {
    const municipalityId = $("ta-municipality").value;

    if (!municipalityId) {
        $("ta-scope").textContent =
            "Transaction analytics for your authorized geographic scope.";
        return;
    }

    const municipality = state.municipalities.find(
        (item) => String(item.id) === municipalityId,
    );

    $("ta-scope").textContent = municipality
        ? `Transaction analytics for ${municipality.name}.`
        : "Transaction analytics for the selected municipality.";
}

/* ============================================================
   YEAR FILTER
   ============================================================ */

function populateYears() {
    const select = $("ta-year");
    const previousYear = state.year;

    const years = [
        ...new Set(
            state.verifiedMonthly.map((item) => item.month.substring(0, 4)),
        ),
    ].sort((a, b) => b.localeCompare(a));

    select.replaceChildren(new Option("All years", ""));

    for (const year of years) {
        select.add(new Option(year, year));
    }

    if (years.includes(previousYear)) {
        select.value = previousYear;
    } else {
        select.value = "";
        state.year = "";
    }
}

function applyYearFilter() {
    state.year = $("ta-year").value;

    state.filteredMonthly = state.year
        ? state.verifiedMonthly.filter((item) =>
              item.month.startsWith(`${state.year}-`),
          )
        : [...state.verifiedMonthly];

    state.page = 1;

    render();
}

/* ============================================================
   MAIN RENDER
   ============================================================ */

function render() {
    renderKPIs();
    renderStatusChart();
    renderMonthlySummary();
    renderMonthlyChart();
    renderMonthlyTable();
}

/* ============================================================
   KPI CARDS
   IMPORTANT: These are ALL-TIME metrics for the municipality.
   The year filter only affects monthly data.
   ============================================================ */

function renderKPIs() {
    const totalTransactions = sum(state.byStatus, "total");

    const verifiedTransactions = state.byStatus
        .filter((item) => item.status === "verified")
        .reduce((total, item) => total + item.total, 0);

    const verifiedSales = sum(state.verifiedMonthly, "selling_total");

    const verificationRate =
        totalTransactions > 0
            ? (verifiedTransactions / totalTransactions) * 100
            : 0;

    $("ta-total-transactions").textContent = formatNumber(totalTransactions);

    $("ta-verified-transactions").textContent =
        formatNumber(verifiedTransactions);

    $("ta-verified-sales").textContent = formatMoney(verifiedSales);

    $("ta-verification-rate").textContent = `${verificationRate.toFixed(1)}%`;
}

/* ============================================================
   STATUS DISTRIBUTION
   ============================================================ */

function renderStatusChart() {
    const chart = $("ta-status-chart");
    const legend = $("ta-status-legend");

    const total = sum(state.byStatus, "total");

    chart.replaceChildren();
    legend.replaceChildren();

    if (!total) {
        chart.innerHTML = `
            <div class="ta-empty">
                No transaction status data available.
            </div>
        `;
        return;
    }

    const statusMap = new Map(
        state.byStatus.map((item) => [item.status, item.total]),
    );

    const knownStatuses = Object.keys(STATUS_LABELS);

    const otherStatuses = [...statusMap.keys()].filter(
        (status) => !knownStatuses.includes(status),
    );

    const statuses = [...knownStatuses, ...otherStatuses];

    const segments = [];

    for (const status of statuses) {
        const count = statusMap.get(status) || 0;

        if (!count) continue;

        const percentage = (count / total) * 100;
        const color = STATUS_COLORS[status] || "#8b93a0";

        segments.push({
            status,
            count,
            percentage,
            color,
        });
    }

    // Horizontal stacked status bar.
    const bar = document.createElement("div");
    bar.className = "ta-status-bar";

    for (const segment of segments) {
        const piece = document.createElement("div");

        piece.className = "ta-status-segment";
        piece.style.width = `${segment.percentage}%`;
        piece.style.background = segment.color;

        piece.title =
            `${STATUS_LABELS[segment.status] || segment.status}: ` +
            `${segment.count} (${segment.percentage.toFixed(1)}%)`;

        bar.appendChild(piece);
    }

    chart.appendChild(bar);

    const totalLabel = document.createElement("div");
    totalLabel.className = "ta-status-total";

    totalLabel.innerHTML = `
        <strong>${formatNumber(total)}</strong>
        <span>Total recorded transactions</span>
    `;

    chart.appendChild(totalLabel);

    for (const segment of segments) {
        const item = document.createElement("div");

        item.className = "ta-status-legend-item";

        item.innerHTML = `
            <span
                class="ta-status-dot"
                style="background:${segment.color}"
            ></span>

            <span class="ta-status-name">
                ${escapeHtml(
                    STATUS_LABELS[segment.status] ||
                        segment.status.replaceAll("_", " "),
                )}
            </span>

            <strong>${formatNumber(segment.count)}</strong>

            <small>${segment.percentage.toFixed(1)}%</small>
        `;

        legend.appendChild(item);
    }
}

/* ============================================================
   MONTHLY SUMMARY
   ============================================================ */

function renderMonthlySummary() {
    const months = state.filteredMonthly;

    const verified = sum(months, "transaction_count");
    const sales = sum(months, "selling_total");

    const averageSale = verified > 0 ? sales / verified : 0;

    const activeMonths = months.filter(
        (item) => item.transaction_count > 0,
    ).length;

    $("ta-period-verified").textContent = formatNumber(verified);

    $("ta-period-sales").textContent = formatMoney(sales);

    $("ta-average-sale").textContent =
        verified > 0 ? formatMoney(averageSale) : "—";

    $("ta-active-months").textContent = formatNumber(activeMonths);
}

/* ============================================================
   MONTHLY VERIFIED SALES / COUNT CHART
   SVG chart with no external dependencies
   ============================================================ */

function renderMonthlyChart() {
    const container = $("ta-monthly-chart");

    const rows = state.filteredMonthly;

    if (!rows.length) {
        container.innerHTML = `
            <div class="ta-empty">
                No verified transaction activity
                for the selected reporting period.
            </div>
        `;
        return;
    }

    const metric = state.chartMetric;

    const values = rows.map((row) =>
        metric === "sales" ? row.selling_total : row.transaction_count,
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
        <svg
            viewBox="0 0 ${width} ${height}"
            class="ta-svg"
            role="img"
            aria-label="Monthly verified transaction chart"
        >
    `);

    // Grid lines and Y-axis labels.
    for (let i = 0; i <= 4; i++) {
        const value = (yMax * i) / 4;
        const y = yPosition(value);

        const label =
            metric === "sales"
                ? formatCompactMoney(value)
                : formatNumber(Math.round(value));

        svg.push(`
            <line
                x1="${margin.left}"
                y1="${y}"
                x2="${width - margin.right}"
                y2="${y}"
                class="ta-grid-line"
            />

            <text
                x="${margin.left - 12}"
                y="${y + 4}"
                text-anchor="end"
                class="ta-axis-label"
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

        const tooltipValue =
            metric === "sales"
                ? formatMoney(value)
                : `${formatNumber(value)} transactions`;

        svg.push(`
            <rect
                x="${x}"
                y="${y}"
                width="${barWidth}"
                height="${Math.max(0, barHeight)}"
                rx="5"
                fill="#16845b"
                class="ta-chart-bar"
            >
                <title>
                    ${escapeHtml(formatMonth(row.month))}:
                    ${escapeHtml(tooltipValue)}
                </title>
            </rect>
        `);

        if (index % labelStep === 0 || index === rows.length - 1) {
            svg.push(`
                <text
                    x="${centerX}"
                    y="${height - 22}"
                    text-anchor="middle"
                    class="ta-axis-label"
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
   MONTHLY HISTORY TABLE
   ============================================================ */

function renderMonthlyTable() {
    const rows = [...state.filteredMonthly].sort((a, b) =>
        b.month.localeCompare(a.month),
    );

    const totalPages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));

    state.page = Math.min(Math.max(1, state.page), totalPages);

    const start = (state.page - 1) * PAGE_SIZE;

    const visibleRows = rows.slice(start, start + PAGE_SIZE);

    const body = $("ta-monthly-body");

    if (!visibleRows.length) {
        body.innerHTML = `
            <tr>
                <td colspan="4" class="ta-empty-cell">
                    No monthly verified transactions found.
                </td>
            </tr>
        `;
    } else {
        body.innerHTML = visibleRows
            .map((row) => {
                const averageSale =
                    row.transaction_count > 0
                        ? row.selling_total / row.transaction_count
                        : 0;

                return `
                    <tr>
                        <td>
                            <strong>
                                ${escapeHtml(formatMonth(row.month))}
                            </strong>
                        </td>

                        <td>
                            ${formatNumber(row.transaction_count)}
                        </td>

                        <td class="ta-money-cell">
                            ${formatMoney(row.selling_total)}
                        </td>

                        <td>
                            ${
                                row.transaction_count > 0
                                    ? formatMoney(averageSale)
                                    : "—"
                            }
                        </td>
                    </tr>
                `;
            })
            .join("");
    }

    $("ta-page-info").textContent = rows.length
        ? `Showing ${start + 1}–${Math.min(
              start + PAGE_SIZE,
              rows.length,
          )} of ${formatNumber(rows.length)} months`
        : "0 months";

    $("ta-prev-page").disabled = state.page <= 1;
    $("ta-next-page").disabled = state.page >= totalPages;
}

/* ============================================================
   EVENT LISTENERS
   ============================================================ */

function bindEvents() {
    $("ta-refresh").addEventListener("click", loadTransactions);

    $("ta-retry").addEventListener("click", loadTransactions);

    $("ta-municipality").addEventListener("change", loadTransactions);

    $("ta-year").addEventListener("change", applyYearFilter);

    $("ta-chart-metric").addEventListener("change", (event) => {
        state.chartMetric = event.target.value;
        renderMonthlyChart();
    });

    $("ta-clear-filters").addEventListener("click", async () => {
        $("ta-municipality").value = "";
        $("ta-year").value = "";

        state.year = "";
        state.chartMetric = "sales";

        $("ta-chart-metric").value = "sales";

        await loadTransactions();
    });

    $("ta-prev-page").addEventListener("click", () => {
        state.page--;
        renderMonthlyTable();
    });

    $("ta-next-page").addEventListener("click", () => {
        state.page++;
        renderMonthlyTable();
    });
}

/* ============================================================
   INITIALIZATION
   ============================================================ */

async function init() {
    if (!$("ta-content")) return;

    bindEvents();

    await loadMunicipalities();
    await loadTransactions();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}
