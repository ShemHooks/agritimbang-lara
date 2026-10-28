import api from "../api";

const $ = (id) => document.getElementById(id);

const state = {
    role: null,
    page: 1,
    lastPage: 1,
    total: 0,
    records: [],
    requestId: 0,
    loading: false,
    submittingId: null,
    filters: {
        status: "",
        species_id: "",
        sale_purpose: "",
    },
};

const MANAGED_ROLES = ["lgu_encoder"];
const VIEW_ROLES = ["farmer", "lgu_encoder", "lgu_authority"];

function unwrap(response) {
    return response.data?.data ?? response.data;
}

function errorMessage(error) {
    const errors = error.response?.data?.errors;

    if (errors) {
        return Object.values(errors).flat().join(" ");
    }

    return (
        error.response?.data?.message ??
        error.message ??
        "An unexpected error occurred."
    );
}

function notify(message, type = "error") {
    const error = $("prices-error");
    const success = $("prices-success");

    error.hidden = true;
    success.hidden = true;

    const element = type === "success" ? success : error;

    element.textContent = message;
    element.hidden = false;
}

function clearNotice() {
    $("prices-error").hidden = true;
    $("prices-success").hidden = true;
}

function readable(value) {
    if (value == null || value === "") return "—";

    return String(value)
        .replace(/_/g, " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function money(value) {
    if (value == null || value === "") return "—";

    const number = Number(value);

    if (!Number.isFinite(number)) return "—";

    return new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(number);
}

function date(value) {
    if (!value) return "—";

    const match = String(value).match(/^\d{4}-\d{2}-\d{2}/);

    return match ? match[0] : String(value);
}

function createCell(row, value) {
    const cell = row.insertCell();
    cell.textContent = value ?? "—";
    return cell;
}

function statusBadge(status) {
    const badge = document.createElement("span");

    const allowed = [
        "draft",
        "pending_review",
        "official",
        "rejected",
        "archived",
    ];

    const safeStatus = allowed.includes(status) ? status : "unknown";

    badge.className = `prices-status prices-status-${safeStatus}`;
    badge.textContent = readable(status);

    return badge;
}

function isEncoder() {
    return MANAGED_ROLES.includes(state.role);
}

function canView() {
    return VIEW_ROLES.includes(state.role);
}

function resolveRole(payload) {
    const user = payload?.user ?? payload;

    const rawRoles = [
        ...(Array.isArray(user?.roles) ? user.roles : []),
        ...(user?.role ? [user.role] : []),
    ];

    const roles = rawRoles
        .map((role) => {
            if (typeof role === "string") {
                return role;
            }

            // Prefer the database slug over display name.
            return role?.slug ?? role?.name ?? null;
        })
        .filter(Boolean)
        .map((role) =>
            String(role)
                .trim()
                .toLowerCase()
                .replace(/[\s-]+/g, "_"),
        );

    if (roles.includes("lgu_encoder")) return "lgu_encoder";
    if (roles.includes("lgu_authority")) return "lgu_authority";
    if (roles.includes("farmer")) return "farmer";

    return null;
}

function getEndpoint() {
    return isEncoder() ? "/lgu/price-references" : "/official-prices";
}

function configureRoleUI() {
    const encoder = isEncoder();

    const authority = state.role?.includes("lgu_authority") ?? false;

    const toggle = (id, visible) => {
        const element = $(id);

        if (element) {
            element.hidden = !visible;
        }
    };

    toggle("prices-create-button", encoder);
    toggle("prices-history-button", encoder);

    toggle("prices-approval-button", authority);

    toggle("prices-status-filter-wrap", encoder);
    toggle("prices-draft-card", encoder);
    toggle("prices-pending-card", encoder);

    $("prices-page-title").textContent = encoder
        ? "Price References"
        : authority
          ? "Price Management"
          : "Official Livestock Prices";

    $("prices-page-description").textContent = encoder
        ? "Manage weekly livestock prices and track their approval status."
        : authority
          ? "Review submitted livestock prices, manage approvals, and view official price references."
          : "View MAO-approved livestock prices for valuation and market reference.";

    $("prices-directory-title").textContent = encoder
        ? "Weekly Price Directory"
        : "Official Price Directory";

    $("prices-directory-description").textContent = encoder
        ? "Price references for your assigned municipality."
        : "Approved livestock price references available for viewing.";

    if (!encoder) {
        state.filters.status = "";

        const statusFilter = $("prices-filter-status");

        if (statusFilter) {
            statusFilter.value = "";
        }
    }
}

function setEmpty(message) {
    const body = $("prices-table-body");
    body.replaceChildren();

    const row = body.insertRow();
    const cell = row.insertCell();

    cell.colSpan = 7;
    cell.className = "prices-empty";
    cell.textContent = message;
}

function updateSummary() {
    const records = state.records;

    $("prices-total").textContent = records.length;

    $("prices-draft").textContent = records.filter(
        (record) => record.status === "draft",
    ).length;

    $("prices-pending").textContent = records.filter(
        (record) => record.status === "pending_review",
    ).length;

    $("prices-official").textContent = records.filter(
        (record) => record.status === "official",
    ).length;
}

function updatePagination() {
    $("prices-page-info").textContent =
        `Page ${state.page} of ${state.lastPage} · ${state.total} records`;

    $("prices-prev").disabled = state.loading || state.page <= 1;

    $("prices-next").disabled = state.loading || state.page >= state.lastPage;
}

function actionButton(label, callback, className = "") {
    const button = document.createElement("button");

    button.type = "button";
    button.className = `prices-action ${className}`.trim();
    button.textContent = label;

    button.addEventListener("click", callback);

    return button;
}

function renderTable() {
    const body = $("prices-table-body");
    body.replaceChildren();

    if (!state.records.length) {
        setEmpty(
            isEncoder()
                ? "No price references match your filters."
                : "No official prices are available for the selected filters.",
        );

        updateSummary();
        updatePagination();
        return;
    }

    state.records.forEach((record) => {
        const row = body.insertRow();

        const animal = row.insertCell();

        const species = document.createElement("strong");
        species.textContent = record.species?.name ?? "Unknown species";

        const breed = document.createElement("small");
        breed.textContent = record.breed?.name ?? "All breeds";

        animal.append(species, breed);

        createCell(
            row,
            record.barangay?.name ??
                record.municipality?.name ??
                "Municipality-wide",
        );

        createCell(row, readable(record.sale_purpose));

        const price = createCell(row, money(record.price_per_kg));

        price.className = "prices-money";

        createCell(
            row,
            `${date(record.effective_from)} — ${
                record.effective_to ? date(record.effective_to) : "Open-ended"
            }`,
        );

        const status = row.insertCell();
        status.append(statusBadge(record.status));

        const actions = row.insertCell();
        const group = document.createElement("div");

        group.className = "prices-actions";

        group.append(actionButton("View", () => showDetails(record.id)));

        if (isEncoder() && record.status === "draft") {
            const edit = document.createElement("a");

            edit.className = "prices-action";
            edit.href = `/prices/${encodeURIComponent(record.id)}/edit`;
            edit.textContent = "Edit";

            group.append(edit);

            group.append(
                actionButton(
                    "Submit",
                    () => submitPrice(record.id),
                    "prices-action-primary",
                ),
            );
        }

        actions.append(group);
    });

    updateSummary();
    updatePagination();
}

async function loadSpecies() {
    try {
        const response = await api.get("/reference/species");
        const payload = unwrap(response);

        const records = Array.isArray(payload)
            ? payload
            : Array.isArray(payload?.data)
              ? payload.data
              : [];

        const select = $("prices-filter-species");

        select.replaceChildren(new Option("All species", ""));

        records.forEach((species) => {
            select.add(new Option(species.name, species.id));
        });
    } catch (error) {
        notify(`Unable to load species: ${errorMessage(error)}`);
    }
}

async function loadPrices() {
    if (!canView()) return;

    const requestId = ++state.requestId;

    state.loading = true;

    updatePagination();
    setEmpty("Loading price references...");

    try {
        const params = {
            page: state.page,
        };

        if (state.filters.species_id) {
            params.species_id = state.filters.species_id;
        }

        if (state.filters.sale_purpose) {
            params.sale_purpose = state.filters.sale_purpose;
        }

        if (isEncoder() && state.filters.status) {
            params.status = state.filters.status;
        }

        const response = await api.get(getEndpoint(), { params });

        if (requestId !== state.requestId) return;

        const paginator = unwrap(response);

        state.records = Array.isArray(paginator?.data) ? paginator.data : [];

        state.page = Number(paginator?.current_page ?? 1);

        state.lastPage = Math.max(1, Number(paginator?.last_page ?? 1));

        state.total = Number(paginator?.total ?? state.records.length);

        renderTable();
    } catch (error) {
        if (requestId !== state.requestId) return;

        state.records = [];
        state.total = 0;

        setEmpty("Unable to load price references.");

        notify(errorMessage(error));
        updateSummary();
    } finally {
        if (requestId === state.requestId) {
            state.loading = false;
            updatePagination();
        }
    }
}

function detailField(container, label, value) {
    const field = document.createElement("div");
    field.className = "prices-detail-field";

    const name = document.createElement("span");
    name.textContent = label;

    const content = document.createElement("strong");
    content.textContent = value ?? "—";

    field.append(name, content);
    container.append(field);
}

function renderDetails(record) {
    const container = $("prices-detail-content");

    container.replaceChildren();

    const fields = [
        ["Species", record.species?.name],
        ["Breed", record.breed?.name ?? "All breeds"],
        ["Barangay", record.barangay?.name ?? "Municipality-wide"],
        ["Municipality", record.municipality?.name],
        ["Sale purpose", readable(record.sale_purpose)],
        ["Price / kg", money(record.price_per_kg)],
        ["Effective from", date(record.effective_from)],
        [
            "Effective to",
            record.effective_to ? date(record.effective_to) : "Open-ended",
        ],
        ["Source", record.source],
        ["Remarks", record.remarks],
        ["Status", readable(record.status)],
    ];

    if (isEncoder()) {
        fields.push(
            ["Submitted at", record.submitted_at],
            ["Submitted by", record.submitter?.name],
        );
    }

    fields.forEach(([label, value]) => {
        detailField(container, label, value);
    });

    $("prices-detail").hidden = false;

    $("prices-detail").scrollIntoView({
        behavior: "smooth",
        block: "start",
    });
}

async function showDetails(id) {
    clearNotice();

    const selected = state.records.find(
        (record) => String(record.id) === String(id),
    );

    if (!selected) {
        notify("Price reference not found.");
        return;
    }

    // Farmer and authority roles only use already loaded
    // official-price data. They never call encoder APIs.
    if (!isEncoder()) {
        renderDetails(selected);
        return;
    }

    try {
        const response = await api.get(
            `/lgu/price-references/${encodeURIComponent(id)}`,
        );

        renderDetails(unwrap(response));
    } catch (error) {
        notify(errorMessage(error));
    }
}

async function submitPrice(id) {
    if (!isEncoder() || state.submittingId) return;

    const record = state.records.find((item) => String(item.id) === String(id));

    if (!record || record.status !== "draft") return;

    const confirmed = window.confirm(
        "Submit this price reference for MAO review? " +
            "Drafts cannot be edited after submission.",
    );

    if (!confirmed) return;

    state.submittingId = id;
    clearNotice();

    try {
        await api.post(
            `/lgu/price-references/${encodeURIComponent(id)}/submit`,
        );

        notify("Price reference submitted for review successfully.", "success");

        await loadPrices();
    } catch (error) {
        notify(errorMessage(error));
    } finally {
        state.submittingId = null;
    }
}

function bindEvents() {
    $("prices-filters").addEventListener("change", () => {
        state.page = 1;

        state.filters.status = isEncoder()
            ? $("prices-filter-status").value
            : "";

        state.filters.species_id = $("prices-filter-species").value;

        state.filters.sale_purpose = $("prices-filter-purpose").value;

        clearNotice();
        loadPrices();
    });

    $("prices-reset").addEventListener("click", () => {
        $("prices-filters").reset();

        state.page = 1;

        state.filters = {
            status: "",
            species_id: "",
            sale_purpose: "",
        };

        clearNotice();
        loadPrices();
    });

    $("prices-refresh").addEventListener("click", () => {
        clearNotice();
        loadPrices();
    });

    $("prices-prev").addEventListener("click", () => {
        if (state.loading || state.page <= 1) return;

        state.page--;
        loadPrices();
    });

    $("prices-next").addEventListener("click", () => {
        if (state.loading || state.page >= state.lastPage) {
            return;
        }

        state.page++;
        loadPrices();
    });

    $("prices-detail-close").addEventListener("click", () => {
        $("prices-detail").hidden = true;
    });
}

async function init() {
    if (!$("price-references-page")) return;

    bindEvents();

    try {
        const response = await api.get("/auth/me");
        const user = unwrap(response);

        state.role = resolveRole(user);

        if (!canView()) {
            $("prices-page-description").textContent =
                "Your account does not have access to this page.";

            setEmpty("Access unavailable.");
            notify("Your account cannot access this page.");
            return;
        }

        configureRoleUI();

        await Promise.all([loadSpecies(), loadPrices()]);
    } catch (error) {
        setEmpty("Unable to initialize the price directory.");
        notify(errorMessage(error));
    }
}

init();
