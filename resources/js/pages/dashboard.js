import api from "../api";
import { formatDate as sharedFormatDate } from "../utils/date-format";

const $ = (id) => document.getElementById(id);

const formatNumber = (value) =>
    new Intl.NumberFormat("en-PH").format(Number(value) || 0);

const formatPeso = (value) =>
    new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value) || 0);

const formatDate = (value) => sharedFormatDate(value);

const normalizeRole = (role) =>
    String(role ?? "")
        .toLowerCase()
        .replace(/[-\s]+/g, "_");

const STATUS_CONFIG = {
    available: {
        label: "Available",
        color: "#76944c",
    },
    in_transaction: {
        label: "In transaction",
        color: "#e0b45d",
    },
    sold: {
        label: "Sold",
        color: "#a4bba0",
    },
    archived: {
        label: "Archived",
        color: "#d5dbcf",
    },
};

const loading = $("dashboard-loading");
const errorBox = $("dashboard-error");
const errorMessage = $("dashboard-error-message");
const managementDashboard = $("management-dashboard");
const farmerDashboard = $("farmer-dashboard");

function setLoading(isLoading) {
    if (loading) loading.hidden = !isLoading;
}

function showError(message) {
    if (errorMessage) errorMessage.textContent = message;
    if (errorBox) errorBox.hidden = false;
}

function hideError() {
    if (errorBox) errorBox.hidden = true;
}

function setText(id, value) {
    const element = $(id);

    if (element) {
        element.textContent = value;
    }
}

function renderLivestockStatus(statuses = {}) {
    const chart = $("livestock-status-chart");
    const slices = $("livestock-status-slices");
    const legend = $("livestock-status-legend");
    const empty = $("livestock-empty");
    const totalElement = $("livestock-status-total");

    if (!chart || !slices || !legend || !empty || !totalElement) return;

    slices.replaceChildren();
    legend.replaceChildren();

    const total = Object.keys(STATUS_CONFIG).reduce(
        (sum, status) => sum + (Number(statuses[status]) || 0),
        0,
    );

    totalElement.textContent = formatNumber(total);

    if (total === 0) {
        empty.hidden = false;
        chart.hidden = true;
        legend.hidden = true;
        return;
    }

    empty.hidden = true;
    chart.hidden = false;
    legend.hidden = false;

    let offset = 0;
    const descriptions = [];

    for (const [status, config] of Object.entries(STATUS_CONFIG)) {
        const count = Math.max(0, Number(statuses[status]) || 0);
        const percentage = (count / total) * 100;
        descriptions.push(`${config.label}: ${formatNumber(count)}`);

        if (count > 0) {
            const slice = document.createElementNS("http://www.w3.org/2000/svg", "circle");
            slice.setAttribute("cx", "60");
            slice.setAttribute("cy", "60");
            slice.setAttribute("r", "44");
            slice.setAttribute("pathLength", "100");
            slice.setAttribute("fill", "none");
            slice.setAttribute("stroke", config.color);
            slice.setAttribute("stroke-width", "16");
            slice.setAttribute("stroke-dasharray", `${percentage} ${100 - percentage}`);
            slice.setAttribute("stroke-dashoffset", String(-offset));
            slice.setAttribute("stroke-linecap", "butt");
            slice.setAttribute("class", "inventory-donut-slice");
            slices.appendChild(slice);
            offset += percentage;
        }

        const item = document.createElement("div");
        item.className = "inventory-legend-item";

        const dot = document.createElement("span");
        dot.className = "inventory-legend-dot";
        dot.style.backgroundColor = config.color;

        const label = document.createElement("span");
        label.textContent = config.label;

        const value = document.createElement("strong");
        value.textContent = `${formatNumber(count)} (${percentage.toFixed(1)}%)`;

        item.append(dot, label, value);
        legend.appendChild(item);
    }

    chart.setAttribute(
        "aria-label",
        `Livestock inventory status distribution. ${descriptions.join(", ")}`,
    );
}

function renderManagementDashboard(stats) {
    setText("metric-farmers", formatNumber(stats.registered_farmers));
    setText("metric-livestock", formatNumber(stats.monthly_livestock_count));
    const livestockPeriodLabel = stats.monthly_period_label ?? "Current month";
    setText("metric-livestock-caption", `Livestock records created during ${livestockPeriodLabel}`);
    setText("metric-transactions", formatNumber(stats.verified_transactions));
    setText("metric-sales", formatPeso(stats.verified_sales_total));
    const periodLabel = stats.monthly_period_label ?? "Current month";
    setText("metric-transactions-caption", `Verified this month · ${periodLabel}`);
    setText("metric-sales-caption", `Verified sales this month · ${periodLabel}`);
    setText(
        "metric-inventory-value",
        formatPeso(stats.current_estimated_inventory_value),
    );
    setText(
        "metric-available",
        formatNumber(stats.livestock_by_status?.available),
    );

    setText("financial-sales", formatPeso(stats.yearly_verified_sales_total ?? stats.verified_sales_total));
    setText(
        "financial-inventory",
        formatPeso(stats.current_estimated_inventory_value),
    );

    renderLivestockStatus(stats.livestock_by_status ?? {});

    managementDashboard.hidden = false;
}

async function renderFarmerDashboard(user) {
    setText("dashboard-subtitle", "Imong uma ug mga hayop — a quick overview of your registered livestock and sales.");

    const status = String(user.verification_status ?? "unverified");
    const statusLabels = {
        verified: "Verified",
        pending: "Pending",
        rejected: "Rejected",
        unverified: "Unverified",
    };

    setText("verification-label", statusLabels[status] ?? "Unknown");
    setText(
        "verification-caption",
        status === "verified"
            ? "LGU verification complete"
            : "Account verification is not yet complete",
    );

    farmerDashboard.hidden = false;

    const monthInput = $("farmer-dashboard-month");
    const selectedMonth = monthInput?.value || new Date().toISOString().slice(0, 7);

    async function loadMonthlyResults(month) {
        try {
            const response = await api.get("/farmer/dashboard-summary", { params: { month } });
            const summary = response.data?.data ?? null;
            if (!summary) throw new Error("Monthly summary is unavailable.");

            const monthLabel = summary.month_label || month;
            setText("farmer-livestock-count", formatNumber(summary.livestock_this_month));
            setText("farmer-transactions-count", formatNumber(summary.transactions_this_month));
            setText("farmer-livestock-caption", `Hayop nga narehistro sa ${monthLabel}`);
            renderFarmerLivestockSummary(summary.livestock_status_counts || {});
            setText("farmer-transactions-caption", `Mga transaksyon sa ${monthLabel}`);
            setText("farmer-transactions-value", `Recorded selling value: ${formatPeso(summary.selling_value_this_month)}`);
            setText("farmer-dashboard-period", `${summary.period_start} – ${summary.period_end}`);

            const counts = summary.transaction_status_counts || {};
            const statusText = Object.entries(counts)
                .map(([key, count]) => `${key.replaceAll("_", " ")}: ${formatNumber(count)}`)
                .join(" · ");
            setText("farmer-transactions-status", statusText || "No transactions recorded for this month.");
        } catch (error) {
            console.warn("Farmer monthly dashboard summary unavailable.", error);
            setText("farmer-livestock-caption", "Unable to load monthly livestock results.");
            setText("farmer-transactions-caption", "Unable to load monthly transaction results.");
            setText("farmer-transactions-value", "Recorded selling value: —");
            setText("farmer-transactions-status", "Please refresh the dashboard to try again.");
        }
    }

    await loadMonthlyResults(selectedMonth);
    if (monthInput && !monthInput.dataset.bound) {
        monthInput.dataset.bound = "true";
        monthInput.addEventListener("change", () => {
            if (monthInput.value) loadMonthlyResults(monthInput.value);
        });
    }

}

function renderFarmerLivestockSummary(statuses = {}) {
    const chart = $("farmer-livestock-chart");
    const slices = $("farmer-livestock-slices");
    const legend = $("farmer-livestock-legend");
    const totalElement = $("farmer-livestock-chart-total");
    const headlineTotal = $("farmer-livestock-total");
    if (!chart || !slices || !legend || !totalElement) return;

    const config = [
        { key: "available", label: "Available", color: "#76944c" },
        { key: "in_transaction", label: "In transaction", color: "#e0b45d" },
        { key: "sold", label: "Sold", color: "#a4bba0" },
        { key: "archived", label: "Archived", color: "#d5dbcf" },
    ];
    const total = config.reduce((sum, item) => sum + Math.max(0, Number(statuses[item.key]) || 0), 0);
    totalElement.textContent = formatNumber(total);
    if (headlineTotal) headlineTotal.textContent = formatNumber(total);
    slices.replaceChildren();
    legend.replaceChildren();

    if (!total) {
        chart.hidden = true;
        const empty = document.createElement("p");
        empty.className = "dashboard-empty";
        empty.textContent = "Wala pa kay nairehistro nga hayop.";
        legend.append(empty);
        return;
    }

    chart.hidden = false;
    let offset = 0;
    for (const item of config) {
        const count = Math.max(0, Number(statuses[item.key]) || 0);
        const percentage = (count / total) * 100;
        if (count > 0) {
            const slice = document.createElementNS("http://www.w3.org/2000/svg", "circle");
            slice.setAttribute("cx", "60");
            slice.setAttribute("cy", "60");
            slice.setAttribute("r", "44");
            slice.setAttribute("pathLength", "100");
            slice.setAttribute("fill", "none");
            slice.setAttribute("stroke", item.color);
            slice.setAttribute("stroke-width", "16");
            slice.setAttribute("stroke-dasharray", `${percentage} ${100 - percentage}`);
            slice.setAttribute("stroke-dashoffset", String(-offset));
            slice.setAttribute("class", "inventory-donut-slice");
            slices.append(slice);
            offset += percentage;
        }

        const row = document.createElement("div");
        row.className = "inventory-legend-item";
        const dot = document.createElement("span");
        dot.className = "inventory-legend-dot";
        dot.style.backgroundColor = item.color;
        const label = document.createElement("span");
        label.textContent = item.label;
        const value = document.createElement("strong");
        value.textContent = `${formatNumber(count)} (${percentage.toFixed(1)}%)`;
        row.append(dot, label, value);
        legend.append(row);
    }
    chart.setAttribute("aria-label", `Livestock inventory status. ${config.map(item => `${item.label}: ${formatNumber(statuses[item.key])}`).join(", ")}`);
}

async function loadFarmerLivestockStatus() {
    const statuses = ["available", "in_transaction", "sold", "archived"];

    const results = await Promise.all(
        statuses.map(async (status) => {
            try {
                const response = await api.get("/farmer/livestock", {
                    params: { status },
                });

                return {
                    status,
                    total: Number(response.data?.data?.total ?? response.data?.total ?? 0),
                };
            } catch (error) {
                console.warn(`Unable to load farmer livestock status: ${status}`, error);
                return { status, total: 0 };
            }
        }),
    );

    return Object.fromEntries(
        results.map(({ status, total }) => [status, total]),
    );
}

const MANAGEMENT_CONFIG = {
    admin: {
        label: "SYSTEM ADMINISTRATOR",
        title: "System-wide agricultural monitoring",
        description:
            "Monitor registered farmers, livestock, verified sales, and inventory values across all municipalities.",
        subtitle: "System-wide agricultural statistics",
        welcome:
            "Monitor agricultural activity and manage your AgriTimbang platform.",
    },
    lgu_authority: {
        label: "LGU AUTHORITY",
        title: "Municipal agricultural overview",
        description:
            "Monitor livestock records, registered farmers, and verified transactions within your assigned municipality.",
        subtitle: "Agricultural statistics for your assigned municipality",
        welcome:
            "Monitor agricultural activity and support farmers in your municipality.",
    },
    lgu_encoder: {
        label: "LGU ENCODER",
        title: "Municipal data overview",
        description:
            "Review livestock records, farmer registrations, and verified transactions within your assigned municipality.",
        subtitle: "Municipal livestock and transaction statistics",
        welcome:
            "Review livestock information and support municipal data management.",
    },
};

let currentManagementRole = null;

function configureManagementDashboard(role) {
    const config = MANAGEMENT_CONFIG[role];

    if (!config) {
        throw new Error("Unsupported management dashboard role.");
    }

    currentManagementRole = role;
    managementDashboard.classList.toggle("is-admin-dashboard", role === "admin");
    managementDashboard.classList.toggle("is-encoder-dashboard", role === "lgu_encoder");
    managementDashboard.classList.toggle("is-authority-dashboard", role === "lgu_authority");

    // Admin and Encoder dashboards omit financial summary cards.
    // Authority retains them for municipal verification and oversight.
    const hideFinancialKpis = role === "admin" || role === "lgu_encoder";
    const salesCard = $("metric-sales-card");
    const inventoryValueCard = $("metric-inventory-value-card");
    if (salesCard) salesCard.hidden = hideFinancialKpis;
    if (inventoryValueCard) inventoryValueCard.hidden = hideFinancialKpis;
    const financialOverview = $("financial-overview-panel");
    if (financialOverview) financialOverview.hidden = false;


    const officialPrices = $("dashboard-official-prices");
    const managementStats = document.querySelector("#management-dashboard .management-stats");
    if (officialPrices && managementStats) {
        managementStats.insertAdjacentElement("afterend", officialPrices);
    }

    setText("management-role-label", config.label);
    setText("management-dashboard-title", config.title);
    setText("management-scope-description", config.description);
    setText("dashboard-subtitle", config.subtitle);
    setText("dashboard-welcome-description", config.welcome);

    setText(
        "management-scope-badge",
        role === "admin" ? "All municipalities" : "Assigned municipality",
    );
}

async function loadManagementDashboard() {
    const response = await api.get("/analytics/dashboard");
    const stats = response.data?.data;

    if (!stats) {
        throw new Error("Dashboard analytics data was not returned.");
    }

    renderManagementDashboard(stats);
}


const DASHBOARD_PRICE_PURPOSES = [
    { key: "slaughter", label: "Slaughter" },
    { key: "breeding", label: "Breeding" },
    { key: "fattening", label: "Fattening" },
    { key: "work", label: "Work" },
];

let dashboardOfficialPriceRows = [];
let activeDashboardPricePurpose = "slaughter";

function setDashboardPricePurpose(purpose) {
    const validPurpose = DASHBOARD_PRICE_PURPOSES.some((item) => item.key === purpose);
    if (!validPurpose) return;

    activeDashboardPricePurpose = purpose;
    document.querySelectorAll("[data-dashboard-price-category]").forEach((button) => {
        const active = button.dataset.dashboardPriceCategory === purpose;
        button.classList.toggle("active", active);
        button.setAttribute("aria-selected", active ? "true" : "false");
        button.tabIndex = active ? 0 : -1;
    });

    renderDashboardOfficialPrices();
}

function renderDashboardOfficialPrices() {
    const grid = $("dashboard-price-grid");
    if (!grid) return;

    const speciesConfig = [
        { name: "Carabao", icon: "🐃" },
        { name: "Cattle", icon: "🐄" },
        { name: "Goat", icon: "🐐" },
        { name: "Hog", icon: "🐖" },
    ];
    const purpose = DASHBOARD_PRICE_PURPOSES.find((item) => item.key === activeDashboardPricePurpose)
        ?? DASHBOARD_PRICE_PURPOSES[0];
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const latestBySpecies = new Map();
    dashboardOfficialPriceRows.forEach((row) => {
        const salePurpose = String(row.sale_purpose ?? row.reference_category ?? "slaughter")
            .trim().toLowerCase();
        if (salePurpose !== purpose.key) return;
        if (String(row.status ?? "official").trim().toLowerCase() !== "official") return;

        const speciesName = String(row.species?.name ?? "").trim().toLowerCase();
        const species = speciesConfig.find((item) => item.name.toLowerCase() === speciesName);
        if (!species) return;

        const effectiveDate = row.effective_from
            ? new Date(`${row.effective_from}T00:00:00`)
            : null;
        if (effectiveDate && !Number.isNaN(effectiveDate.getTime()) && effectiveDate > today) return;

        const old = latestBySpecies.get(species.name);
        const newerEffectiveDate = String(row.effective_from ?? "") > String(old?.effective_from ?? "");
        const sameEffectiveDateNewerApproval =
            String(row.effective_from ?? "") === String(old?.effective_from ?? "") &&
            String(row.approved_at ?? "") > String(old?.approved_at ?? "");
        if (!old || newerEffectiveDate || sameEffectiveDateNewerApproval) {
            latestBySpecies.set(species.name, row);
        }
    });

    grid.replaceChildren();
    speciesConfig.forEach((species) => {
        const record = latestBySpecies.get(species.name);
        const card = document.createElement("article");
        card.className = "prices-reference-slide dashboard-price-slide";

        const title = document.createElement("strong");
        title.className = "dashboard-price-title";
        title.textContent = `${species.icon}  ${species.name} — ${purpose.label}`;

        const price = document.createElement("b");
        if (record) {
            const min = Number(record.price_min_per_unit ?? record.price_per_kg);
            const max = Number(record.price_max_per_unit ?? record.price_per_kg);
            price.textContent = Number.isFinite(min) && Number.isFinite(max) && min !== max
                ? `${formatPeso(min)} – ${formatPeso(max)}`
                : formatPeso(record.price_per_kg ?? record.price_min_per_unit ?? 0);
        } else {
            price.textContent = "Walay aprubadong presyo";
        }

        const unit = document.createElement("small");
        unit.textContent = record?.price_unit ?? "PHP/kg";

        const locality = document.createElement("small");
        locality.textContent = record
            ? (record.municipality?.name ?? "Municipality-wide")
            : "Wala pay opisyal nga reference";

        const effective = document.createElement("small");
        effective.textContent = record?.effective_from
            ? `Epektibo sukad ${formatDate(record.effective_from)}`
            : "Pending official reference";

        const source = document.createElement("small");
        source.textContent = record
            ? `Tinubdan: ${record.source ?? record.municipality?.name ?? "Opisyal nga reference"}`
            : "Source: —";

        card.append(title, price, unit, locality, effective, source);
        grid.append(card);
    });

    setText("farmer-price-species-count", `${latestBySpecies.size} ka klase`);
}

function initializeDashboardPriceTabs() {
    const tabs = [...document.querySelectorAll("[data-dashboard-price-category]")];
    tabs.forEach((button, index) => {
        button.addEventListener("click", () => {
            setDashboardPricePurpose(button.dataset.dashboardPriceCategory);
        });
        button.addEventListener("keydown", (event) => {
            if (!["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key)) return;
            event.preventDefault();
            let nextIndex = index;
            if (event.key === "ArrowRight") nextIndex = (index + 1) % tabs.length;
            if (event.key === "ArrowLeft") nextIndex = (index - 1 + tabs.length) % tabs.length;
            if (event.key === "Home") nextIndex = 0;
            if (event.key === "End") nextIndex = tabs.length - 1;
            tabs[nextIndex]?.focus();
            setDashboardPricePurpose(tabs[nextIndex]?.dataset.dashboardPriceCategory);
        });
    });
}

initializeDashboardPriceTabs();

async function loadDashboardOfficialPrices() {
    const grid = $("dashboard-price-grid");
    if (!grid) return;

    grid.replaceChildren();
    const loadingMessage = document.createElement("p");
    loadingMessage.textContent = "Nagakuha sa opisyal nga presyo...";
    grid.append(loadingMessage);

    try {
        // Fetch all four sale purposes once. The selected tab filters this shared,
        // approved-price dataset locally so every dashboard role behaves identically.
        const response = await api.get("/official-prices", {
            params: { per_page: 100 },
        });
        const payload = response.data?.data;
        const records = Array.isArray(payload)
            ? payload
            : (Array.isArray(payload?.data) ? payload.data : []);
        dashboardOfficialPriceRows = records;
        renderDashboardOfficialPrices();
    } catch (error) {
        dashboardOfficialPriceRows = [];
        grid.replaceChildren();
        const message = document.createElement("p");
        message.textContent = "Dili makuha ang opisyal nga presyo karon. Palihog i-refresh.";
        grid.append(message);
    }
}

async function loadDashboard() {
    if (!sessionStorage.getItem("agritimbang_token")) {
        return;
    }

    hideError();
    setLoading(true);

    managementDashboard.hidden = true;
    farmerDashboard.hidden = true;

    try {
        const meResponse = await api.get("/auth/me");
        const user = meResponse.data?.data;

        if (!user) {
            throw new Error("Unable to retrieve your account information.");
        }

        setText("welcome-name", user.name ?? user.full_name ?? "there");
        setText("today-date", sharedFormatDate(new Date()));

        const roles = (user.roles ?? []).map((role) =>
            normalizeRole(role.slug),
        );

        const managementRole = ["admin", "lgu_authority", "lgu_encoder"].find(
            (role) => roles.includes(role),
        );

        if (managementRole) {
            configureManagementDashboard(managementRole);

            await loadManagementDashboard();
            await loadDashboardOfficialPrices();

            return;
        }

        if (roles.includes("farmer")) {
            setText(
                "dashboard-welcome-description",
                "Here's what's happening in your AgriTimbang workspace.",
            );

            await renderFarmerDashboard(user);
            await loadDashboardOfficialPrices();

            return;
        }

        throw new Error(
            "No supported dashboard role is assigned to your account.",
        );
    } catch (error) {
        if (error.response?.status === 401) {
            return;
        }

        showError(
            error.response?.data?.message ??
                error.message ??
                "Unable to load dashboard information.",
        );
    } finally {
        setLoading(false);
    }
}

$("dashboard-retry")?.addEventListener("click", loadDashboard);

loadDashboard();
