import api from "../api";

const $ = (id) => document.getElementById(id);

const state = {
    authorized: false,
    loading: false,
    requestId: 0,
    page: 1,
    lastPage: 1,
    total: 0,
    records: [],
    filters: {
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
        "Unable to load price approvals."
    );
}

function notify(message) {
    const element = $("approvals-error");

    element.textContent = message;
    element.hidden = false;
}

function clearNotice() {
    $("approvals-error").hidden = true;
}

function rolesFrom(payload) {
    const user = payload?.user ?? payload;

    return (user?.roles ?? [])
        .map((role) =>
            typeof role === "string" ? role : (role.slug ?? role.name),
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

function dateTime(value) {
    if (!value) return "—";

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) return String(value);

    return new Intl.DateTimeFormat("en-PH", {
        dateStyle: "medium",
        timeStyle: "short",
        timeZone: "Asia/Manila",
    }).format(parsed);
}

function personName(person) {
    if (!person) return "—";

    if (person.full_name) return person.full_name;
    if (person.name) return person.name;

    const name = [person.first_name, person.middle_name, person.last_name]
        .filter(Boolean)
        .join(" ");

    return name || "—";
}

function setEmpty(message) {
    const body = $("approvals-table-body");
    body.replaceChildren();

    const row = body.insertRow();
    const cell = row.insertCell();

    cell.colSpan = 8;
    cell.className = "price-approvals-empty";
    cell.textContent = message;
}

function createCell(row, text) {
    const cell = row.insertCell();
    cell.textContent = text ?? "—";
    return cell;
}

function renderTable() {
    const body = $("approvals-table-body");
    body.replaceChildren();

    if (!state.records.length) {
        setEmpty("No pending price references found.");
        updateSummary();
        updatePagination();
        return;
    }

    state.records.forEach((record) => {
        const row = body.insertRow();

        const livestock = row.insertCell();

        const species = document.createElement("strong");
        species.textContent = record.species?.name ?? "Unknown species";

        const breed = document.createElement("small");
        breed.textContent = record.breed?.name ?? "All breeds";

        livestock.append(species, breed);

        createCell(row, record.barangay?.name ?? "Municipality-wide");

        createCell(row, readable(record.sale_purpose));

        const price = createCell(row, money(record.price_per_kg));
        price.className = "price-approvals-money";

        createCell(row, personName(record.submitter));

        createCell(row, dateTime(record.submitted_at));

        const statusCell = row.insertCell();

        const badge = document.createElement("span");
        badge.className = "price-approvals-status";
        badge.textContent = readable(record.status);

        statusCell.append(badge);

        const actionCell = row.insertCell();

        const link = document.createElement("a");

        link.href = `/prices/approvals/${encodeURIComponent(record.id)}`;

        link.className = "price-approvals-review";
        link.textContent = "Review";

        actionCell.append(link);
    });

    updateSummary();
    updatePagination();
}

function updateSummary() {
    $("approvals-total").textContent = state.total;

    $("approvals-pending").textContent = state.records.filter(
        (record) => record.status === "pending_review",
    ).length;
}

function updatePagination() {
    $("approvals-page-info").textContent =
        `Page ${state.page} of ${state.lastPage} · ${state.total} records`;

    $("approvals-prev").disabled = state.loading || state.page <= 1;

    $("approvals-next").disabled =
        state.loading || state.page >= state.lastPage;
}

async function loadSpecies() {
    try {
        const response = await api.get("/reference/species");

        const species = normalizeRecords(unwrap(response));

        const select = $("approvals-species");

        select.replaceChildren(new Option("All species", ""));

        species.forEach((item) => {
            select.add(new Option(item.name, item.id));
        });
    } catch (error) {
        notify(errorMessage(error));
    }
}

async function loadApprovals() {
    if (!state.authorized) return;

    const requestId = ++state.requestId;

    state.loading = true;
    updatePagination();

    setEmpty("Loading pending references...");

    try {
        const params = {
            page: state.page,
            status: "pending_review",
        };

        if (state.filters.species_id) {
            params.species_id = state.filters.species_id;
        }

        if (state.filters.sale_purpose) {
            params.sale_purpose = state.filters.sale_purpose;
        }

        const response = await api.get("/lgu/price-validations", { params });

        if (requestId !== state.requestId) return;

        const paginator = unwrap(response);

        state.records = normalizeRecords(paginator).filter(
            (record) => record.status === "pending_review",
        );

        state.page = Number(paginator?.current_page ?? 1);

        state.lastPage = Math.max(1, Number(paginator?.last_page ?? 1));

        state.total = Number(paginator?.total ?? state.records.length);

        renderTable();
    } catch (error) {
        if (requestId !== state.requestId) return;

        state.records = [];
        state.total = 0;
        state.lastPage = 1;

        setEmpty("Unable to load approval queue.");
        updateSummary();
        updatePagination();

        notify(errorMessage(error));
    } finally {
        if (requestId === state.requestId) {
            state.loading = false;
            updatePagination();
        }
    }
}

function bindEvents() {
    $("approvals-filters").addEventListener("change", () => {
        state.page = 1;

        state.filters.species_id = $("approvals-species").value;

        state.filters.sale_purpose = $("approvals-purpose").value;

        clearNotice();
        loadApprovals();
    });

    $("approvals-reset").addEventListener("click", () => {
        $("approvals-filters").reset();

        state.page = 1;

        state.filters = {
            species_id: "",
            sale_purpose: "",
        };

        clearNotice();
        loadApprovals();
    });

    $("approvals-refresh").addEventListener("click", () => {
        clearNotice();
        loadApprovals();
    });

    $("approvals-prev").addEventListener("click", () => {
        if (state.loading || state.page <= 1) return;

        state.page--;
        loadApprovals();
    });

    $("approvals-next").addEventListener("click", () => {
        if (state.loading || state.page >= state.lastPage) {
            return;
        }

        state.page++;
        loadApprovals();
    });
}

async function init() {
    if (!$("price-approvals-page")) return;

    bindEvents();

    try {
        const response = await api.get("/auth/me");
        const user = unwrap(response);

        if (!rolesFrom(user).includes("lgu_authority")) {
            notify("Only LGU Authorities can access the approval queue.");

            setEmpty("Access unavailable.");
            return;
        }

        state.authorized = true;

        await Promise.all([loadSpecies(), loadApprovals()]);
    } catch (error) {
        notify(errorMessage(error));
        setEmpty("Unable to initialize approval queue.");
    }
}

init();
