import api from "../api";

const $ = (id) => document.getElementById(id);

const state = {
    authorized: false,
    page: 1,
    lastPage: 1,
    total: 0,
    records: [],
    loading: false,
    requestId: 0,
    submittingId: null,
    filters: {
        status: "",
        species_id: "",
        sale_purpose: "",
    },
};

function unwrap(response) {
    return response.data?.data ?? response.data;
}

function normalizeRecords(payload) {
    if (Array.isArray(payload)) return payload;
    if (Array.isArray(payload?.data)) return payload.data;
    return [];
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
    $("history-error").hidden = true;
    $("history-success").hidden = true;

    const target =
        type === "success" ? $("history-success") : $("history-error");

    target.textContent = message;
    target.hidden = false;
}

function clearNotice() {
    $("history-error").hidden = true;
    $("history-success").hidden = true;
}

function resolveRoles(payload) {
    const user = payload?.user ?? payload;

    const rawRoles = [
        ...(Array.isArray(user?.roles) ? user.roles : []),
        ...(user?.role ? [user.role] : []),
    ];

    return rawRoles
        .map((role) =>
            typeof role === "string" ? role : (role?.slug ?? role?.name),
        )
        .filter(Boolean)
        .map((role) =>
            String(role)
                .trim()
                .toLowerCase()
                .replace(/[\s-]+/g, "_"),
        );
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
    }).format(number);
}

function date(value) {
    if (!value) return "—";

    const match = String(value).match(/^\d{4}-\d{2}-\d{2}/);

    return match ? match[0] : String(value);
}

function dateTime(value) {
    if (!value) return "Not yet submitted";

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return String(value);
    }

    return new Intl.DateTimeFormat("en-PH", {
        dateStyle: "medium",
        timeStyle: "short",
        timeZone: "Asia/Manila",
    }).format(parsed);
}

function createCell(row, value) {
    const cell = row.insertCell();
    cell.textContent = value ?? "—";
    return cell;
}

function setEmpty(message) {
    const body = $("history-table-body");

    body.replaceChildren();

    const row = body.insertRow();
    const cell = row.insertCell();

    cell.colSpan = 8;
    cell.className = "price-history-empty";
    cell.textContent = message;
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

    const safe = allowed.includes(status) ? status : "unknown";

    badge.className = `price-history-status price-history-status-${safe}`;

    badge.textContent = readable(status);

    return badge;
}

function actionButton(label, callback, primary = false) {
    const button = document.createElement("button");

    button.type = "button";

    button.className = primary
        ? "price-history-action price-history-action-primary"
        : "price-history-action";

    button.textContent = label;

    button.addEventListener("click", callback);

    return button;
}

function updateSummary() {
    $("history-total").textContent = state.total;

    $("history-drafts").textContent = state.records.filter(
        (record) => record.status === "draft",
    ).length;

    $("history-pending").textContent = state.records.filter(
        (record) => record.status === "pending_review",
    ).length;

    $("history-official").textContent = state.records.filter(
        (record) => record.status === "official",
    ).length;
}

function updatePagination() {
    $("history-page-info").textContent =
        `Page ${state.page} of ${state.lastPage} · ${state.total} records`;

    $("history-prev").disabled = state.loading || state.page <= 1;

    $("history-next").disabled = state.loading || state.page >= state.lastPage;
}

function renderTable() {
    const body = $("history-table-body");
    body.replaceChildren();

    if (!state.records.length) {
        setEmpty("No submission records match your filters.");
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

        createCell(row, record.barangay?.name ?? "Municipality-wide");

        createCell(row, readable(record.sale_purpose));

        const price = createCell(row, money(record.price_per_kg));
        price.className = "price-history-money";

        createCell(
            row,
            record.submitter?.name ??
                record.submitter?.full_name ??
                "Unknown encoder",
        );

        createCell(row, dateTime(record.submitted_at));

        const status = row.insertCell();
        status.append(statusBadge(record.status));

        const actions = row.insertCell();
        const group = document.createElement("div");

        group.className = "price-history-actions";

        group.append(actionButton("View", () => showDetails(record.id)));

        if (record.status === "draft") {
            const edit = document.createElement("a");

            edit.href = `/prices/${encodeURIComponent(record.id)}/edit`;

            edit.className = "price-history-action";
            edit.textContent = "Edit";

            group.append(edit);

            group.append(
                actionButton("Submit", () => submitPrice(record.id), true),
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

        const records = normalizeRecords(unwrap(response));
        const select = $("history-filter-species");

        select.replaceChildren(new Option("All species", ""));

        records.forEach((record) => {
            select.add(new Option(record.name, record.id));
        });
    } catch (error) {
        notify(`Unable to load species: ${errorMessage(error)}`);
    }
}

async function loadHistory() {
    if (!state.authorized) return;

    const requestId = ++state.requestId;

    state.loading = true;
    updatePagination();
    setEmpty("Loading submission history...");

    try {
        const params = {
            page: state.page,
        };

        Object.entries(state.filters).forEach(([key, value]) => {
            if (value) params[key] = value;
        });

        const response = await api.get("/lgu/price-references", { params });

        if (requestId !== state.requestId) return;

        const paginator = unwrap(response);

        state.records = normalizeRecords(paginator);
        state.page = Number(paginator?.current_page ?? 1);
        state.lastPage = Math.max(1, Number(paginator?.last_page ?? 1));
        state.total = Number(paginator?.total ?? state.records.length);

        renderTable();
    } catch (error) {
        if (requestId !== state.requestId) return;

        state.records = [];
        state.total = 0;

        setEmpty("Unable to load submission history.");
        updateSummary();

        notify(errorMessage(error));
    } finally {
        if (requestId === state.requestId) {
            state.loading = false;
            updatePagination();
        }
    }
}

function detailField(container, label, value) {
    const field = document.createElement("div");
    field.className = "price-history-detail-field";

    const title = document.createElement("span");
    title.textContent = label;

    const content = document.createElement("strong");
    content.textContent = value ?? "—";

    field.append(title, content);
    container.append(field);
}

function renderValidationLogs(logs) {
    const section = $("history-validation-section");
    const container = $("history-validation-list");

    container.replaceChildren();

    if (!Array.isArray(logs) || !logs.length) {
        section.hidden = true;
        return;
    }

    section.hidden = false;

    logs.forEach((log) => {
        const item = document.createElement("div");
        item.className = "price-history-log";

        const title = document.createElement("strong");
        title.textContent = readable(
            log.action ?? log.to_status ?? "Status update",
        );

        const meta = document.createElement("span");

        const performer =
            log.performer?.name ?? log.performer?.full_name ?? "System";

        meta.textContent = `${performer} · ${dateTime(log.created_at)}`;

        item.append(title, meta);

        if (log.remarks || log.reason) {
            const note = document.createElement("p");
            note.textContent = log.remarks ?? log.reason;
            item.append(note);
        }

        container.append(item);
    });
}

function renderDetails(record) {
    const container = $("history-detail-content");
    container.replaceChildren();

    const fields = [
        ["Species", record.species?.name],
        ["Breed", record.breed?.name ?? "All breeds"],
        ["Municipality", record.municipality?.name],
        ["Barangay", record.barangay?.name ?? "Municipality-wide"],
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
        ["Submitted by", record.submitter?.name ?? record.submitter?.full_name],
        ["Submitted at", dateTime(record.submitted_at)],
        ["Reviewed by", record.reviewer?.name ?? record.reviewer?.full_name],
        ["Approved by", record.approver?.name ?? record.approver?.full_name],
        ["Approved at", dateTime(record.approved_at)],
    ];

    fields.forEach(([label, value]) => {
        detailField(container, label, value);
    });

    renderValidationLogs(record.validation_logs ?? record.validationLogs ?? []);

    $("history-detail").hidden = false;

    $("history-detail").scrollIntoView({
        behavior: "smooth",
        block: "start",
    });
}

async function showDetails(id) {
    clearNotice();

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
    if (!state.authorized || state.submittingId) return;

    const record = state.records.find((item) => String(item.id) === String(id));

    if (!record || record.status !== "draft") return;

    const confirmed = window.confirm(
        "Submit this draft for MAO review? " +
            "It cannot be edited after submission.",
    );

    if (!confirmed) return;

    state.submittingId = id;
    clearNotice();

    try {
        await api.post(
            `/lgu/price-references/${encodeURIComponent(id)}/submit`,
        );

        notify("Price reference submitted for MAO review.", "success");

        $("history-detail").hidden = true;

        await loadHistory();
    } catch (error) {
        notify(errorMessage(error));
    } finally {
        state.submittingId = null;
    }
}

function bindEvents() {
    $("history-filters").addEventListener("change", () => {
        state.page = 1;

        state.filters.status = $("history-filter-status").value;

        state.filters.species_id = $("history-filter-species").value;

        state.filters.sale_purpose = $("history-filter-purpose").value;

        clearNotice();
        loadHistory();
    });

    $("history-reset").addEventListener("click", () => {
        $("history-filters").reset();

        state.page = 1;

        state.filters = {
            status: "",
            species_id: "",
            sale_purpose: "",
        };

        clearNotice();
        loadHistory();
    });

    $("history-refresh").addEventListener("click", () => {
        clearNotice();
        loadHistory();
    });

    $("history-prev").addEventListener("click", () => {
        if (state.loading || state.page <= 1) return;

        state.page--;
        loadHistory();
    });

    $("history-next").addEventListener("click", () => {
        if (state.loading || state.page >= state.lastPage) {
            return;
        }

        state.page++;
        loadHistory();
    });

    $("history-detail-close").addEventListener("click", () => {
        $("history-detail").hidden = true;
    });
}

async function init() {
    if (!$("price-history-page")) return;

    bindEvents();

    try {
        const response = await api.get("/auth/me");
        const payload = unwrap(response);

        const roles = resolveRoles(payload);

        if (!roles.includes("lgu_encoder")) {
            notify("Only LGU Encoders can access price submission history.");

            setEmpty("Access unavailable.");
            return;
        }

        state.authorized = true;

        await Promise.all([loadSpecies(), loadHistory()]);
    } catch (error) {
        notify(errorMessage(error));
        setEmpty("Unable to initialize submission history.");
    }
}

init();
