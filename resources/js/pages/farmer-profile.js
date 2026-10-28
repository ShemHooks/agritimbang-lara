import api from "../api";

/* ============================================================
   AGRITIMBANG — FARMER PROFILE DETAILS
   Phase 5.2
   ============================================================ */

const $ = (id) => document.getElementById(id);

const content = $("fp-content");

const farmerId = content?.dataset.farmerId;

const STATUS_CLASSES = [
    "fp-badge-neutral",
    "fp-badge-success",
    "fp-badge-warning",
    "fp-badge-danger",
];

function setText(id, value) {
    const element = $(id);

    if (!element) return;

    const text = String(value ?? "").trim();

    element.textContent = text || "—";
}

function formatDate(value) {
    if (!value) return "—";

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return "—";

    return new Intl.DateTimeFormat("en-PH", {
        year: "numeric",
        month: "long",
        day: "numeric",
    }).format(date);
}

function formatName(user) {
    if (!user) return "Unknown Farmer";

    return (
        [user.first_name, user.middle_name, user.last_name]
            .filter(Boolean)
            .join(" ")
            .trim() || "Unknown Farmer"
    );
}

function getInitials(user) {
    if (!user) return "—";

    const first = user.first_name?.charAt(0) || "";
    const last = user.last_name?.charAt(0) || "";

    return `${first}${last}`.toUpperCase() || "—";
}

function formatStatus(value) {
    if (!value) return "Unknown";

    return String(value)
        .replace(/_/g, " ")
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

function setBadge(id, status) {
    const element = $(id);

    if (!element) return;

    element.classList.remove(...STATUS_CLASSES);

    const normalized = String(status || "").toLowerCase();

    let badgeClass = "fp-badge-neutral";

    if (normalized === "verified" || normalized === "active") {
        badgeClass = "fp-badge-success";
    } else if (normalized === "pending" || normalized === "unverified") {
        badgeClass = "fp-badge-warning";
    } else if (
        normalized === "rejected" ||
        normalized === "suspended" ||
        normalized === "inactive"
    ) {
        badgeClass = "fp-badge-danger";
    }

    element.classList.add(badgeClass);
    element.textContent = formatStatus(status);
}

/* ============================================================
   UI STATES
   ============================================================ */

function setLoading(loading) {
    $("fp-loading").hidden = !loading;
    $("fp-refresh").disabled = loading;
}

function showError(message) {
    $("fp-error-message").textContent = message;
    $("fp-error").hidden = false;
}

function clearError() {
    $("fp-error").hidden = true;
}

/* ============================================================
   RENDER FARMER PROFILE
   ============================================================ */

function renderProfile(farmer) {
    const user = farmer.user || {};

    const municipality = farmer.municipality?.name || "—";
    const barangay = farmer.barangay?.name || "—";

    const fullName = formatName(user);

    // Identity header
    setText("fp-avatar", getInitials(user));
    setText("fp-full-name", fullName);
    setText("fp-farmer-code", farmer.farmer_code);

    setText(
        "fp-location-summary",
        [barangay, municipality].filter((value) => value !== "—").join(", ") ||
            "Location unavailable",
    );

    setBadge("fp-verification-badge", user.verification_status);

    // Quick information cards
    setText("fp-account-status", formatStatus(user.status));

    setText("fp-municipality", municipality);
    setText("fp-barangay", barangay);

    setText("fp-registration-date", formatDate(farmer.created_at));

    // Personal information
    setText("fp-first-name", user.first_name);
    setText("fp-middle-name", user.middle_name);
    setText("fp-last-name", user.last_name);

    setText("fp-detail-farmer-code", farmer.farmer_code);

    // Contact information
    setText("fp-email", user.email);
    setText("fp-phone", user.phone_number);

    // Geographic information
    setText("fp-detail-municipality", municipality);
    setText("fp-detail-barangay", barangay);

    // Account information
    setBadge("fp-detail-account-status", user.status);

    setBadge("fp-detail-verification", user.verification_status);

    setText("fp-created-at", formatDate(farmer.created_at));

    setText("fp-updated-at", formatDate(farmer.updated_at));

    // Update browser title
    document.title = `${fullName} | AgriTimbang`;
}

/* ============================================================
   LOAD FARMER DETAILS
   ============================================================ */

async function loadFarmerProfile() {
    if (!farmerId) {
        $("fp-content").hidden = true;

        showError("The farmer identifier is missing from the page.");

        return;
    }

    setLoading(true);
    clearError();

    try {
        const response = await api.get(
            `/farmers/${encodeURIComponent(farmerId)}`,
        );

        const farmer = response.data?.data;

        if (!farmer || !farmer.id) {
            throw new Error("The API returned an invalid farmer profile.");
        }

        renderProfile(farmer);

        $("fp-content").hidden = false;
    } catch (error) {
        console.error("Farmer Profile Error:", error);

        $("fp-content").hidden = true;

        const status = error.response?.status;

        if (status === 404) {
            showError("Farmer profile not found or unavailable.");
        } else if (status === 403) {
            showError(
                "You do not have permission to view this farmer profile.",
            );
        } else if (status === 401) {
            showError("Your session has expired. Please log in again.");
        } else if (status === 422) {
            showError("The requested farmer profile is invalid.");
        } else {
            showError(
                error.response?.data?.message ||
                    error.message ||
                    "Unable to load farmer profile.",
            );
        }
    } finally {
        setLoading(false);
    }
}

/* ============================================================
   INITIALIZATION
   ============================================================ */

function init() {
    if (!content) return;

    $("fp-refresh")?.addEventListener("click", loadFarmerProfile);

    $("fp-retry")?.addEventListener("click", loadFarmerProfile);

    loadFarmerProfile();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}
