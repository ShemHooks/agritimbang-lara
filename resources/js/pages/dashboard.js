import api from "../api";

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
    loading.hidden = !isLoading;
}

function showError(message) {
    errorMessage.textContent = message;
    errorBox.hidden = false;
}

function hideError() {
    errorBox.hidden = true;
}

function setText(id, value) {
    const element = $(id);

    if (element) {
        element.textContent = value;
    }
}

function renderLivestockStatus(statuses = {}) {
    const bar = $("livestock-status-bar");
    const legend = $("livestock-status-legend");
    const empty = $("livestock-empty");

    bar.replaceChildren();
    legend.replaceChildren();

    const total = Object.keys(STATUS_CONFIG).reduce(
        (sum, status) => sum + (Number(statuses[status]) || 0),
        0,
    );

    if (total === 0) {
        empty.hidden = false;
        bar.hidden = true;
        legend.hidden = true;
        return;
    }

    empty.hidden = true;
    bar.hidden = false;
    legend.hidden = false;

    const descriptions = [];

    for (const [status, config] of Object.entries(STATUS_CONFIG)) {
        const count = Number(statuses[status]) || 0;
        const percentage = (count / total) * 100;

        descriptions.push(`${config.label}: ${formatNumber(count)}`);

        if (count > 0) {
            const segment = document.createElement("div");

            segment.className = "inventory-status-segment";
            segment.style.width = `${percentage}%`;
            segment.style.backgroundColor = config.color;
            segment.title = `${config.label}: ${formatNumber(count)}`;

            bar.appendChild(segment);
        }

        const item = document.createElement("div");
        item.className = "inventory-legend-item";

        const dot = document.createElement("span");
        dot.className = "inventory-legend-dot";
        dot.style.backgroundColor = config.color;

        const label = document.createElement("span");
        label.textContent = config.label;

        const value = document.createElement("strong");
        value.textContent = formatNumber(count);

        item.append(dot, label, value);
        legend.appendChild(item);
    }

    bar.setAttribute(
        "aria-label",
        `Livestock status distribution. ${descriptions.join(", ")}`,
    );
}

function renderManagementDashboard(stats) {
    setText("metric-farmers", formatNumber(stats.registered_farmers));
    setText("metric-livestock", formatNumber(stats.total_livestock));
    setText("metric-transactions", formatNumber(stats.verified_transactions));
    setText("metric-sales", formatPeso(stats.verified_sales_total));
    setText(
        "metric-inventory-value",
        formatPeso(stats.current_estimated_inventory_value),
    );
    setText(
        "metric-available",
        formatNumber(stats.livestock_by_status?.available),
    );

    setText("financial-sales", formatPeso(stats.verified_sales_total));
    setText(
        "financial-inventory",
        formatPeso(stats.current_estimated_inventory_value),
    );

    renderLivestockStatus(stats.livestock_by_status ?? {});

    managementDashboard.hidden = false;
}

async function renderFarmerDashboard(user) {
    setText("dashboard-subtitle", "Your personal farmer workspace at a glance");

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

    const response = await api.get("/farmer/livestock");
    const paginator = response.data?.data;

    if (
        !paginator ||
        !Number.isInteger(paginator.total) ||
        !Array.isArray(paginator.data)
    ) {
        throw new Error("Unexpected livestock API response.");
    }

    setText("farmer-livestock-count", formatNumber(paginator.total));

    setText(
        "farmer-livestock-caption",
        paginator.total === 0
            ? "No livestock registered yet"
            : "Total livestock records registered",
    );

    setText(
        "farmer-livestock-summary",
        paginator.total === 0
            ? "You haven't registered any livestock yet."
            : `You currently have ${formatNumber(paginator.total)} livestock record(s).`,
    );

    const statusCounts = await loadFarmerLivestockStatus();

    const summary = Object.entries(statusCounts)
        .map(([status, total]) => {
            const label = STATUS_CONFIG[status]?.label ?? status;

            return `${label}: ${formatNumber(total)}`;
        })
        .join(" · ");

    setText("farmer-livestock-summary", summary);
}

async function loadFarmerLivestockStatus() {
    const statuses = ["available", "in_transaction", "sold", "archived"];

    const results = await Promise.all(
        statuses.map(async (status) => {
            const response = await api.get("/farmer/livestock", {
                params: { status },
            });

            return {
                status,
                total: response.data?.data?.total ?? 0,
            };
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

        const roles = (user.roles ?? []).map((role) =>
            normalizeRole(role.slug),
        );

        const managementRole = ["admin", "lgu_authority", "lgu_encoder"].find(
            (role) => roles.includes(role),
        );

        if (managementRole) {
            configureManagementDashboard(managementRole);

            await loadManagementDashboard();

            return;
        }

        if (roles.includes("farmer")) {
            setText(
                "dashboard-welcome-description",
                "Here's what's happening in your AgriTimbang workspace.",
            );

            await renderFarmerDashboard(user);

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
