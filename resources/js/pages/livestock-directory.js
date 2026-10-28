import api from "../api";

/* ============================================================
   AGRITIMBANG — LIVESTOCK DIRECTORY
   Phase 5.3 — Admin / LGU
   ============================================================ */

const LIVESTOCK_ENDPOINT = "/lgu/livestock";

const REFERENCE_ENDPOINTS = {
    municipalities: "/reference/municipalities",
    species: "/reference/species",
};

const $ = (id) => document.getElementById(id);

const state = {
    role: null,
    municipalityId: null,
    page: 1,
    lastPage: 1,
    total: 0,
    filters: {},
    requestId: 0,
    records: [],
    breedRequestId: 0,
    barangayRequestId: 0,
};

/* ============================================================
   HELPERS
   ============================================================ */

function setText(id, value) {
    const element = $(id);
    if (element) element.textContent = value ?? "—";
}

function escapeHtml(value) {
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function normalizeRole(value) {
    return String(value ?? "")
        .toLowerCase()
        .replace(/[-\s]+/g, "_");
}

function formatStatus(value) {
    if (!value) return "Unknown";

    return String(value)
        .replace(/_/g, " ")
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

function statusClass(status) {
    switch (status) {
        case "available":
            return "ld-badge-success";
        case "in_transaction":
            return "ld-badge-warning";
        case "sold":
            return "ld-badge-neutral";
        case "archived":
            return "ld-badge-danger";
        default:
            return "ld-badge-neutral";
    }
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

function formatWeight(value) {
    if (value === null || value === undefined || value === "") {
        return "Not recorded";
    }

    const weight = Number(value);

    if (!Number.isFinite(weight)) return "Not recorded";

    return `${weight.toLocaleString("en-PH", {
        maximumFractionDigits: 2,
    })} kg`;
}

function formatAge(months) {
    if (months === null || months === undefined) {
        return "Age not recorded";
    }

    const age = Number(months);

    if (!Number.isFinite(age)) return "Age not recorded";

    if (age < 12) {
        return `${age} month${age === 1 ? "" : "s"}`;
    }

    const years = Math.floor(age / 12);
    const remainingMonths = age % 12;

    return remainingMonths === 0
        ? `${years} year${years === 1 ? "" : "s"}`
        : `${years}y ${remainingMonths}m`;
}

function shortId(value) {
    if (!value) return "—";

    const id = String(value);
    return id.length > 12 ? `${id.slice(0, 8)}…` : id;
}

function unwrapCollection(response) {
    const payload = response.data?.data ?? response.data;

    if (Array.isArray(payload)) return payload;
    if (Array.isArray(payload?.data)) return payload.data;

    throw new Error("Unexpected reference API response format.");
}

function errorMessage(error, fallback) {
    return error.response?.data?.message || error.message || fallback;
}

/* ============================================================
   LOADING / ERROR STATES
   ============================================================ */

function setLoading(loading) {
    $("ld-loading").hidden = !loading;
    $("ld-refresh").disabled = loading;
}

function clearError() {
    $("ld-error").hidden = true;
}

function showError(message) {
    setText("ld-error-message", message);
    $("ld-error").hidden = false;
}

function hideResults() {
    $("ld-table-container").hidden = true;
    $("ld-empty").hidden = true;
    $("ld-pagination").hidden = true;
}

/* ============================================================
   TABLE
   ============================================================ */

function renderRow(livestock) {
    const farmer = livestock.farmer || {};
    const user = farmer.user || {};

    const farmerName =
        state.role === "farmer" ? "My Livestock" : formatName(user);

    const farmerCode =
        state.role === "farmer"
            ? "Registered to your account"
            : farmer.farmer_code || "—";

    const species = livestock.species?.name || "Unknown Species";
    const breed = livestock.breed?.name || "Unspecified Breed";

    const municipality = livestock.municipality?.name || "—";
    const barangay = livestock.barangay?.name || "—";

    const sex = formatStatus(livestock.sex);
    const age = formatAge(livestock.age_months);
    const status = livestock.status || "unknown";

    return `
        <tr>
            <td>
                <div class="ld-primary-cell">
                    <strong title="${escapeHtml(livestock.id)}">
                        ${escapeHtml(shortId(livestock.id))}
                    </strong>
                    <small>
                        ${escapeHtml(formatStatus(livestock.sale_purpose))}
                    </small>
                </div>
            </td>

            <td>
                <div class="ld-primary-cell">
                    <strong>${escapeHtml(farmerName)}</strong>
                    <small>${escapeHtml(farmerCode)}</small>
                </div>
            </td>

            <td>
                <div class="ld-primary-cell">
                    <strong>${escapeHtml(species)}</strong>
                    <small>${escapeHtml(breed)}</small>
                </div>
            </td>

            <td>
                <div class="ld-primary-cell">
                    <strong>${escapeHtml(sex)}</strong>
                    <small>${escapeHtml(age)}</small>
                </div>
            </td>

            <td>
                <span class="ld-weight">
                    ${escapeHtml(formatWeight(livestock.actual_weight_kg))}
                </span>
            </td>

            <td>
                <div class="ld-primary-cell">
                    <strong>${escapeHtml(barangay)}</strong>
                    <small>${escapeHtml(municipality)}</small>
                </div>
            </td>

            <td>
                <span class="ld-badge ${statusClass(status)}">
                    ${escapeHtml(formatStatus(status))}
                </span>
            </td>

            <td class="ld-actions-cell">
                <td class="ld-actions-cell">
    <a
        href="/livestock/${encodeURIComponent(livestock.id)}"
        class="ld-view-button"
        title="View livestock details"
    >
        View
    </a>
</td>
            </td>
        </tr>
    `;
}

function renderTable(records) {
    $("ld-table-body").innerHTML = records.map(renderRow).join("");

    $("ld-table-container").hidden = records.length === 0;
    $("ld-empty").hidden = records.length !== 0;
}

/* ============================================================
   PAGINATION
   ============================================================ */

function renderPagination(meta) {
    const currentPage = Number(meta.current_page || 1);
    const lastPage = Number(meta.last_page || 1);
    const total = Number(meta.total || 0);
    const from = Number(meta.from || 0);
    const to = Number(meta.to || 0);

    state.page = currentPage;
    state.lastPage = lastPage;
    state.total = total;

    setText("ld-result-count", `${total} Records`);

    setText(
        "ld-result-description",
        total === 0
            ? "No livestock records match the selected filters."
            : `Showing ${from}–${to} of ${total} livestock records.`,
    );

    setText("ld-pagination-info", `Showing ${from}–${to} of ${total} records`);
    setText("ld-page-indicator", `Page ${currentPage} of ${lastPage}`);

    $("ld-prev-page").disabled = currentPage <= 1;
    $("ld-next-page").disabled = currentPage >= lastPage;

    $("ld-pagination").hidden = total === 0;
}

function extractPaginator(response) {
    const payload = response.data;

    const paginator =
        payload?.data && Array.isArray(payload.data.data)
            ? payload.data
            : payload;

    if (!paginator || !Array.isArray(paginator.data)) {
        throw new Error("Unexpected livestock API response format.");
    }

    return {
        records: paginator.data,
        meta: {
            current_page: paginator.current_page,
            last_page: paginator.last_page,
            total: paginator.total,
            from: paginator.from,
            to: paginator.to,
        },
    };
}

/* ============================================================
   SELECT HELPERS
   ============================================================ */

function resetSelect(id, placeholder) {
    const select = $(id);
    if (!select) return;

    select.replaceChildren(new Option(placeholder, ""));
    select.disabled = true;
}

function fillSelect(id, records, placeholder) {
    const select = $(id);
    if (!select) return;

    select.replaceChildren(new Option(placeholder, ""));

    records.forEach((record) => {
        if (record.id && record.name) {
            select.add(new Option(record.name, record.id));
        }
    });

    select.disabled = false;
}

/* ============================================================
   AUTHENTICATED ACCOUNT CONTEXT
   ============================================================ */

async function loadAccountContext() {
    const response = await api.get("/auth/me");
    const user = response.data?.data;

    if (!user) {
        throw new Error("Unable to identify your account.");
    }

    const roleSlugs = (user.roles ?? []).map((role) =>
        normalizeRole(typeof role === "string" ? role : role.slug),
    );

    state.role =
        ["admin", "lgu_authority", "lgu_encoder", "farmer"].find((role) =>
            roleSlugs.includes(role),
        ) ?? null;

    if (!state.role) {
        throw new Error(
            "Your account does not have permission to view livestock records.",
        );
    }

    const isAdmin = state.role === "admin";
    const isFarmer = state.role === "farmer";

    $("ld-municipality-field").hidden = !isAdmin;

    $("ld-search").closest(".ld-field").hidden = isFarmer;
    $("ld-barangay").closest(".ld-field").hidden = isFarmer;
    $("ld-per-page").closest(".ld-field").hidden = isFarmer;

    const profile = user.lgu_officer_profile ?? user.lguOfficerProfile ?? null;

    state.municipalityId = profile?.municipality_id ?? null;

    return user;
}

/* ============================================================
   REFERENCE DATA
   ============================================================ */

async function loadMunicipalities() {
    if (state.role !== "admin") return;

    const response = await api.get(REFERENCE_ENDPOINTS.municipalities);

    const records = unwrapCollection(response);

    fillSelect("ld-municipality", records, "All Municipalities");
}

async function loadSpecies() {
    const response = await api.get(REFERENCE_ENDPOINTS.species);

    const records = unwrapCollection(response);

    fillSelect("ld-species", records, "All Species");
}

async function loadBreeds(speciesId) {
    const requestId = ++state.breedRequestId;

    resetSelect("ld-breed", "All Breeds");

    if (!speciesId) return;

    const response = await api.get(
        `/reference/species/${encodeURIComponent(speciesId)}/breeds`,
    );

    if (requestId !== state.breedRequestId) return;

    fillSelect("ld-breed", unwrapCollection(response), "All Breeds");
}

async function loadBarangays(municipalityId) {
    const requestId = ++state.barangayRequestId;

    resetSelect("ld-barangay", "All Barangays");

    if (!municipalityId) return;

    const response = await api.get(
        `/reference/municipalities/${encodeURIComponent(municipalityId)}/barangays`,
    );

    if (requestId !== state.barangayRequestId) return;

    fillSelect("ld-barangay", unwrapCollection(response), "All Barangays");
}

async function initializeReferences() {
    const requests = [loadSpecies()];

    if (state.role === "admin") {
        requests.push(loadMunicipalities());
    } else if (state.municipalityId) {
        requests.push(loadBarangays(state.municipalityId));
    }

    const results = await Promise.allSettled(requests);

    results.forEach((result) => {
        if (result.status === "rejected") {
            console.error("Reference loading failed:", result.reason);
            showError(
                "Some filter options could not be loaded. Please refresh.",
            );
        }
    });
}

/* ============================================================
   FILTERS
   ============================================================ */

function collectFilters() {
    const filters = {
        search: $("ld-search")?.value.trim(),
        status: $("ld-status")?.value,
        species_id: $("ld-species")?.value,
        breed_id: $("ld-breed")?.value,
        barangay_id: $("ld-barangay")?.value,
    };

    if (state.role === "admin") {
        filters.municipality_id = $("ld-municipality")?.value;
    }

    return Object.fromEntries(
        Object.entries(filters).filter(
            ([, value]) => value !== "" && value != null,
        ),
    );
}

async function resetFilters() {
    $("ld-filter-form").reset();

    // Invalidate any pending dependent-dropdown requests.
    state.breedRequestId++;
    state.barangayRequestId++;

    resetSelect("ld-breed", "All Breeds");
    resetSelect("ld-barangay", "All Barangays");

    state.page = 1;
    state.filters = {};

    if (state.role !== "admin" && state.municipalityId) {
        try {
            await loadBarangays(state.municipalityId);
        } catch (error) {
            console.error("Unable to reload barangays:", error);
        }
    }

    await loadLivestock();
}

/* ============================================================
   LOAD LIVESTOCK
   ============================================================ */

async function loadLivestock() {
    const requestId = ++state.requestId;

    setLoading(true);
    clearError();
    hideResults();

    try {
        const isFarmer = state.role === "farmer";

        // Select API endpoint based on authenticated role.
        const endpoint = isFarmer ? "/farmer/livestock" : "/lgu/livestock";

        // Farmer API supports a smaller set of filters.
        const params = isFarmer
            ? {
                  status: state.filters.status,
                  species_id: state.filters.species_id,
                  breed_id: state.filters.breed_id,
                  page: state.page,
              }
            : {
                  ...state.filters,
                  page: state.page,
                  per_page: Number($("ld-per-page").value),
              };

        // Remove empty or undefined parameters.
        const cleanParams = Object.fromEntries(
            Object.entries(params).filter(
                ([, value]) =>
                    value !== undefined && value !== null && value !== "",
            ),
        );

        const response = await api.get(endpoint, {
            params: cleanParams,
        });

        // Ignore outdated responses.
        if (requestId !== state.requestId) return;

        const { records, meta } = extractPaginator(response);

        state.records = records;

        renderTable(records);
        renderPagination(meta);
    } catch (error) {
        if (requestId !== state.requestId) return;

        console.error("Livestock Directory Error:", error);

        hideResults();

        const status = error.response?.status;

        if (status === 401) {
            showError("Your session has expired. Please log in again.");
        } else if (status === 403) {
            showError("You do not have permission to view livestock records.");
        } else if (status === 422) {
            showError(errorMessage(error, "One or more filters are invalid."));
        } else {
            showError(errorMessage(error, "Unable to load livestock records."));
        }

        setText("ld-result-description", "Unable to load records.");

        setText("ld-result-count", "— Records");
    } finally {
        if (requestId === state.requestId) {
            setLoading(false);
        }
    }
}

/* ============================================================
   EVENT LISTENERS
   ============================================================ */

function initEvents() {
    $("ld-filter-form").addEventListener("submit", (event) => {
        event.preventDefault();

        state.page = 1;
        state.filters = collectFilters();

        loadLivestock();
    });

    $("ld-reset-filters").addEventListener("click", resetFilters);
    $("ld-refresh").addEventListener("click", loadLivestock);
    $("ld-retry").addEventListener("click", loadLivestock);

    $("ld-species").addEventListener("change", async (event) => {
        try {
            await loadBreeds(event.target.value);
        } catch (error) {
            console.error("Unable to load breeds:", error);
            showError(errorMessage(error, "Unable to load breeds."));
        }
    });

    $("ld-municipality").addEventListener("change", async (event) => {
        if (state.role !== "admin") return;

        try {
            await loadBarangays(event.target.value);
        } catch (error) {
            console.error("Unable to load barangays:", error);
            showError(errorMessage(error, "Unable to load barangays."));
        }
    });

    $("ld-prev-page").addEventListener("click", () => {
        if (state.page <= 1) return;

        state.page--;
        loadLivestock();
    });

    $("ld-next-page").addEventListener("click", () => {
        if (state.page >= state.lastPage) return;

        state.page++;
        loadLivestock();
    });
}

/* ============================================================
   INITIALIZATION
   ============================================================ */

async function init() {
    if (!$("ld-filter-form")) return;

    initEvents();
    setLoading(true);
    hideResults();

    try {
        await loadAccountContext();
        await initializeReferences();
        await loadLivestock();
    } catch (error) {
        console.error("Livestock Directory Initialization:", error);

        showError(
            errorMessage(error, "Unable to initialize livestock directory."),
        );

        setLoading(false);
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}
