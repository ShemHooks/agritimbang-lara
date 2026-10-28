import api from "../api";

/* ============================================================
   AGRITIMBANG — FARMER DIRECTORY
   ============================================================ */

const $ = (id) => document.getElementById(id);

const state = {
    page: 1,
    lastPage: 1,
    perPage: 15,
    total: 0,
    search: "",
    municipalityId: "",
    barangayId: "",
    verificationStatus: "",
    municipalities: [],
    requestId: 0,
};

let searchTimer = null;
let barangayRequestId = 0;

const formatNumber = (value) =>
    new Intl.NumberFormat("en-PH").format(Number(value) || 0);

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

function displayName(user) {
    if (!user) return "Unknown Farmer";

    return (
        [user.first_name, user.middle_name, user.last_name]
            .filter(Boolean)
            .join(" ") || "Unknown Farmer"
    );
}

function initials(user) {
    const first = String(user?.first_name || "").charAt(0);
    const last = String(user?.last_name || "").charAt(0);

    return (first + last).toUpperCase() || "F";
}

function normalizeList(payload, keys = []) {
    const data = payload?.data ?? payload;

    if (Array.isArray(data)) return data;
    if (Array.isArray(data?.data)) return data.data;

    for (const key of keys) {
        if (Array.isArray(data?.[key])) return data[key];
    }

    return [];
}

/* ============================================================
   STATUS
   ============================================================ */

function statusBadge(status, type = "verification") {
    const normalized = String(status || "unknown").toLowerCase();

    const allowed = [
        "verified",
        "pending",
        "unverified",
        "rejected",
        "active",
        "inactive",
        "suspended",
    ];

    const safeStatus = allowed.includes(normalized) ? normalized : "unknown";

    const label = safeStatus.replace(/_/g, " ");

    return `
        <span class="fd-badge fd-badge-${safeStatus}"
              data-status-type="${escapeHtml(type)}">
            ${escapeHtml(label.charAt(0).toUpperCase() + label.slice(1))}
        </span>
    `;
}

/* ============================================================
   LOADING & ERRORS
   ============================================================ */

function setLoading(value) {
    $("fd-loading").hidden = !value;
    $("fd-refresh").disabled = value;
}

function clearError() {
    $("fd-error").hidden = true;
}

function showError(message) {
    $("fd-error-message").textContent = message;
    $("fd-error").hidden = false;
}

/* ============================================================
   MUNICIPALITIES
   ============================================================ */

async function loadMunicipalities() {
    const select = $("fd-municipality");

    try {
        const response = await api.get("/gis/municipalities");

        const municipalities = normalizeList(response.data, ["municipalities"]);

        state.municipalities = municipalities;

        select.replaceChildren(new Option("All authorized municipalities", ""));

        municipalities.forEach((municipality) => {
            if (!municipality.id || !municipality.name) return;

            select.add(new Option(municipality.name, municipality.id));
        });
    } catch (error) {
        console.error("Municipality lookup error:", error);
        select.disabled = true;
    }
}

/* ============================================================
   BARANGAYS
   ============================================================ */

async function loadBarangays() {
    const select = $("fd-barangay");
    const requestId = ++barangayRequestId;

    select.replaceChildren(new Option("All barangays", ""));

    select.disabled = true;
    state.barangayId = "";

    // The public GIS barangay lookup is used here.
    // The farmer directory endpoint independently enforces
    // the authorized municipality on the server.
    try {
        const params = {};

        if (state.municipalityId) {
            params.municipality_id = state.municipalityId;
        }

        const response = await api.get("/gis/barangays", {
            params,
        });

        if (requestId !== barangayRequestId) return;

        const barangays = normalizeList(response.data, ["barangays"]);

        barangays.forEach((barangay) => {
            if (!barangay.id || !barangay.name) return;

            select.add(new Option(barangay.name, barangay.id));
        });

        select.disabled = barangays.length === 0;
    } catch (error) {
        if (requestId !== barangayRequestId) return;

        console.error("Barangay lookup error:", error);
        select.disabled = true;
    }
}

/* ============================================================
   DIRECTORY API
   ============================================================ */

async function loadFarmers() {
    const requestId = ++state.requestId;

    setLoading(true);
    clearError();

    try {
        const params = {
            page: state.page,
            per_page: state.perPage,
        };

        if (state.search) {
            params.search = state.search;
        }

        if (state.municipalityId) {
            params.municipality_id = state.municipalityId;
        }

        if (state.barangayId) {
            params.barangay_id = state.barangayId;
        }

        if (state.verificationStatus) {
            params.verification_status = state.verificationStatus;
        }

        const response = await api.get("/farmers", {
            params,
        });

        if (requestId !== state.requestId) return;

        const payload = response.data;

        if (
            !Array.isArray(payload?.data) ||
            !payload?.meta ||
            !payload?.summary
        ) {
            throw new Error("Unexpected Farmer Directory API response.");
        }

        state.page = Number(payload.meta.current_page) || 1;
        state.lastPage = Number(payload.meta.last_page) || 1;
        state.total = Number(payload.meta.total) || 0;

        renderSummary(payload.summary);
        renderTable(payload.data);
        renderPagination(payload.meta);
        updateScope();
    } catch (error) {
        if (requestId !== state.requestId) return;

        console.error("Farmer Directory Error:", error);

        showError(
            error.response?.data?.message ||
                error.message ||
                "Unable to load farmer records.",
        );

        $("fd-table-body").innerHTML = `
            <tr>
                <td colspan="6" class="fd-empty">
                    Unable to load farmer records.
                </td>
            </tr>
        `;
    } finally {
        if (requestId === state.requestId) {
            setLoading(false);
        }
    }
}

/* ============================================================
   SUMMARY CARDS
   ============================================================ */

function renderSummary(summary) {
    $("fd-total").textContent = formatNumber(summary.total);

    $("fd-verified").textContent = formatNumber(summary.verified);

    $("fd-pending").textContent = formatNumber(summary.pending);
}

/* ============================================================
   TABLE
   ============================================================ */

function renderTable(farmers) {
    const body = $("fd-table-body");

    if (!farmers.length) {
        body.innerHTML = `
            <tr>
                <td colspan="6" class="fd-empty">
                    No farmers found for the selected filters.
                </td>
            </tr>
        `;

        return;
    }

    body.innerHTML = farmers
        .map((farmer) => {
            const user = farmer.user || {};

            const name = displayName(user);
            const location =
                [farmer.barangay?.name, farmer.municipality?.name]
                    .filter(Boolean)
                    .join(", ") || "Location unavailable";

            return `
            <tr>
                <td>
                    <div class="fd-farmer">
                        <span class="fd-avatar">
                            ${escapeHtml(initials(user))}
                        </span>

                        <div class="fd-farmer-info">
                            <strong>${escapeHtml(name)}</strong>
                            <span>
                                ${escapeHtml(user.email || "No email")}
                            </span>
                        </div>
                    </div>
                </td>

                <td>
                    <span class="fd-code">
                        ${escapeHtml(farmer.farmer_code || "—")}
                    </span>
                </td>

                <td>
                    <span class="fd-location">
                        ${escapeHtml(location)}
                    </span>
                </td>

                <td>
                    ${statusBadge(user.verification_status)}
                </td>

                <td>
                    ${statusBadge(user.status, "account")}
                </td>

                <td>
                    <a
                        class="fd-view-link"
                        href="/farmers/${encodeURIComponent(farmer.id)}"
                    >
                        View Details
                    </a>
                </td>
            </tr>
        `;
        })
        .join("");
}

/* ============================================================
   PAGINATION
   ============================================================ */

function renderPagination(meta) {
    const from = meta.from ?? 0;
    const to = meta.to ?? 0;
    const total = meta.total ?? 0;

    $("fd-page-info").textContent = total
        ? `Showing ${formatNumber(from)}–${formatNumber(to)} of ${formatNumber(total)} farmers`
        : "0 farmers";

    $("fd-result-count").textContent =
        `${formatNumber(total)} matching farmer records`;

    $("fd-prev").disabled = state.page <= 1;
    $("fd-next").disabled = state.page >= state.lastPage;
}

/* ============================================================
   SCOPE LABEL
   ============================================================ */

function updateScope() {
    const municipality = state.municipalities.find(
        (item) => String(item.id) === state.municipalityId,
    );

    $("fd-scope").textContent = municipality
        ? `Farmer records for ${municipality.name}.`
        : "Farmer records within your authorized municipality scope.";
}

/* ============================================================
   EVENTS
   ============================================================ */

function bindEvents() {
    $("fd-refresh").addEventListener("click", loadFarmers);

    $("fd-retry").addEventListener("click", loadFarmers);

    $("fd-search").addEventListener("input", (event) => {
        clearTimeout(searchTimer);

        searchTimer = setTimeout(() => {
            state.search = event.target.value.trim();
            state.page = 1;
            loadFarmers();
        }, 350);
    });

    $("fd-municipality").addEventListener("change", async (event) => {
        state.municipalityId = event.target.value;
        state.barangayId = "";
        state.page = 1;

        // Load directory immediately, then barangay options.
        loadFarmers();
        await loadBarangays();
    });

    $("fd-barangay").addEventListener("change", (event) => {
        state.barangayId = event.target.value;
        state.page = 1;
        loadFarmers();
    });

    $("fd-verification").addEventListener("change", (event) => {
        state.verificationStatus = event.target.value;
        state.page = 1;
        loadFarmers();
    });

    $("fd-reset").addEventListener("click", async () => {
        clearTimeout(searchTimer);

        state.page = 1;
        state.search = "";
        state.municipalityId = "";
        state.barangayId = "";
        state.verificationStatus = "";

        $("fd-search").value = "";
        $("fd-municipality").value = "";
        $("fd-verification").value = "";

        loadFarmers();
        await loadBarangays();
    });

    $("fd-prev").addEventListener("click", () => {
        if (state.page <= 1) return;

        state.page--;
        loadFarmers();
    });

    $("fd-next").addEventListener("click", () => {
        if (state.page >= state.lastPage) return;

        state.page++;
        loadFarmers();
    });
}

/* ============================================================
   INIT
   ============================================================ */

async function init() {
    if (!$("fd-table-body")) return;

    bindEvents();

    await loadMunicipalities();
    await loadBarangays();
    await loadFarmers();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}
