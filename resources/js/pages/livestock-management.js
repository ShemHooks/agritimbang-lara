import api from "../api";
import "./livestock-directory.js";

const $ = (id) => document.getElementById(id);
const MANAGEMENT_ROLES = ["admin", "lgu_authority", "lgu_encoder"];
const STATUSES = ["available", "in_transaction", "sold", "archived"];
let canMonitor = false;
let monitoringPromise = null;
let currentRoles = [];

function normalizeRole(role) {
    return String(role ?? "")
        .toLowerCase()
        .replace(/[-\s]+/g, "_");
}
function rolesOf(user) {
    return (user?.roles ?? []).map((role) =>
        normalizeRole(typeof role === "string" ? role : role.slug),
    );
}
function setText(id, value) {
    const element = $(id);
    if (element) element.textContent = value;
}
function showAccess(roles) {
    canMonitor = roles.some((role) => MANAGEMENT_ROLES.includes(role));
    const isFarmer = roles.includes("farmer") && !canMonitor;
    if ($("ld-register")) $("ld-register").hidden = !isFarmer;
    if ($("livestock-monitoring-tab")) {
        $("livestock-monitoring-tab").hidden = !canMonitor;
    }
}
async function loadSharedSummary() {
    try {
        if (!currentRoles.length) throw new Error("User roles are not loaded.");
        let total, statuses;
        if (canMonitor) {
            const response = await api.get("/analytics/dashboard");
            const data = response.data?.data ?? {};
            total = Number(data.total_livestock ?? 0);
            statuses = data.livestock_by_status ?? {};
        } else if (currentRoles.includes("farmer")) {
            const responses = await Promise.all(
                STATUSES.map((status) =>
                    api.get("/farmer/livestock", { params: { status } }),
                ),
            );
            statuses = Object.fromEntries(
                STATUSES.map((status, index) => [
                    status,
                    Number(
                        responses[index].data?.data?.total ??
                            responses[index].data?.total ??
                            0,
                    ),
                ]),
            );
            total = Object.values(statuses).reduce(
                (sum, value) => sum + value,
                0,
            );
        } else {
            throw new Error("Unsupported role for livestock summary.");
        }
        const format = (value) => Number(value ?? 0).toLocaleString("en-PH");
        setText("ld-total", format(total));
        setText("ld-available", format(statuses.available));
        setText("ld-in-transaction", format(statuses.in_transaction));
        setText("ld-sold", format(statuses.sold));
    } catch (error) {
        console.error("Livestock summary error:", error);
        ["ld-total", "ld-available", "ld-in-transaction", "ld-sold"].forEach(
            (id) => setText(id, "—"),
        );
    }
}
async function initializeMonitoring() {
    if (!monitoringPromise) {
        monitoringPromise = import("./livestock-monitoring.js").catch(
            (error) => {
                monitoringPromise = null;
                throw error;
            },
        );
    }
    await monitoringPromise;
}
async function switchTab(view) {
    const monitoring = view === "monitoring";
    if (monitoring && !canMonitor) return;
    const recordsView = $("livestock-records-view");
    const monitoringView = $("livestock-monitoring-view");
    if (!recordsView || !monitoringView) return;

    recordsView.hidden = monitoring;
    monitoringView.hidden = !monitoring;
    document.querySelectorAll("[data-livestock-tab]").forEach((tab) => {
        const active = tab.dataset.livestockTab === view;
        tab.classList.toggle("is-active", active);
        tab.setAttribute("aria-selected", String(active));
        tab.tabIndex = active ? 0 : -1;
    });
    if (monitoring) {
        try {
            await initializeMonitoring();
            requestAnimationFrame(() =>
                window.dispatchEvent(new Event("resize")),
            );
        } catch (error) {
            console.error("Monitoring initialization failed:", error);
        }
    }
}
async function init() {
    document.querySelectorAll("[data-livestock-tab]").forEach((tab) => {
        tab.addEventListener("click", () => {
            if (!tab.hidden) switchTab(tab.dataset.livestockTab);
        });
    });
    try {
        const response = await api.get("/auth/me");
        const user = response.data?.data;
        if (!user) throw new Error("Unable to identify authenticated user.");
        currentRoles = rolesOf(user);
        showAccess(currentRoles);
        await loadSharedSummary();
    } catch (error) {
        console.error("Livestock Management initialization error:", error);
    }
    // The Directory module separately refreshes the records on this click.
    $("ld-refresh")?.addEventListener("click", loadSharedSummary);
}
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}
