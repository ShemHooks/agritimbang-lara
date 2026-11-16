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


const weeklyState = {
    records: [],
    species: "carabao",
    weekStart: "",
};

function speciesKey(record) {
    return String(record.species?.name ?? "")
        .trim()
        .toLowerCase()
        .replace(/[^a-z]/g, "");
}

function renderWeeklySpecies() {
    const body = $("weekly-species-body");
    body.replaceChildren();

    const species = weeklyState.species;
    const purposes = ["slaughter", "breeding", "fattening", "work"];

    const matches = weeklyState.records
        .filter((record) => speciesKey(record) === species)
        .filter(
            (record) =>
                !weeklyState.weekStart ||
                String(record.week_start).slice(0, 10) === weeklyState.weekStart,
        );

    const byPurpose = new Map();

    matches.forEach((record) => {
        const key = String(record.sale_purpose || "").toLowerCase();

        if (!byPurpose.has(key)) {
            byPurpose.set(key, record);
        }
    });

    purposes.forEach((purpose) => {
        const record = byPurpose.get(purpose);
        const row = body.insertRow();

        createCell(row, readable(purpose));
        createCell(row, weeklyState.weekStart || "—");

        if (!record) {
            createCell(row, "0");
            createCell(row, "0 / 0");
            createCell(row, "—");

            const status = row.insertCell();
            const badge = document.createElement("span");

            badge.className = "weekly-status-badge is-insufficient";
            badge.textContent = "No verified observations";

            status.append(badge);

            const action = row.insertCell();

            action.textContent =
                "No recommendation: no verified transaction observations for this purpose during the selected week.";

            action.className = "weekly-no-recommendation-message";

            return;
        }

        createCell(row, record.observation_count ?? 0);

        createCell(
            row,
            `${record.included_count ?? 0} / ${record.excluded_count ?? 0}`,
        );

        const priceCell = createCell(
            row,
            record.recommended_price != null
                ? `${money(record.recommended_price)} / kg`
                : "—",
        );

        priceCell.className = "price-approvals-money";

        const status = row.insertCell();
        const badge = document.createElement("span");

        const statusClass =
            record.status === "available"
                ? "is-available"
                : record.status === "used"
                  ? "is-used"
                  : "is-insufficient";

        badge.className = `weekly-status-badge ${statusClass}`;

        badge.textContent =
            record.status === "available"
                ? "Available"
                : record.status === "used"
                  ? "Already used by an Encoder"
                  : Number(record.observation_count ?? 0) === 0
                    ? "No verified observations"
                    : `Insufficient data (${record.included_count ?? 0}/4 included)`;

        status.append(badge);

        const action = row.insertCell();
        const button = document.createElement("button");

        button.type = "button";
        button.className = "price-approvals-secondary weekly-view-button";
        button.textContent = "View calculation";

        button.addEventListener("click", () => showWeeklyDetail(record));

        action.append(button);
    });
}

function selectWeeklySpecies(species) {
    weeklyState.species = species;

    document
        .querySelectorAll("#weekly-species-tabs [data-species]")
        .forEach((button) => {
            const active = button.dataset.species === species;

            button.classList.toggle("is-active", active);
            button.setAttribute("aria-selected", active ? "true" : "false");
        });

    renderWeeklySpecies();
}

function getSelectedWeekStart() {
    const value = $("weekly-engine-date")?.value;

    if (!value) return "";

    const date = new Date(`${value}T12:00:00`);
    const day = (date.getDay() + 6) % 7;

    date.setDate(date.getDate() - day);

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const dayOfMonth = String(date.getDate()).padStart(2, "0");

    return `${year}-${month}-${dayOfMonth}`;
}

async function loadWeeklyEngine() {
    const body = $("weekly-species-body");

    if (!body) return;

    body.innerHTML =
        '<tr><td colspan="7" class="price-approvals-empty">Loading recommendations…</td></tr>';

    try {
        weeklyState.weekStart = getSelectedWeekStart();

        const query = weeklyState.weekStart
            ? `?week_start=${encodeURIComponent(weeklyState.weekStart)}`
            : "";

        const response = await api.get(`/weekly-price-recommendations${query}`);

        weeklyState.records = normalizeRecords(unwrap(response));

        renderWeeklySpecies();
    } catch (error) {
        body.innerHTML =
            '<tr><td colspan="7" class="price-approvals-empty">Unable to load recommendations.</td></tr>';

        notify(errorMessage(error));
    }
}

function showWeeklyDetail(record) {
    $("weekly-modal-title").textContent =
        `${record.species?.name || "Livestock"} · ${readable(record.sale_purpose)}`;

    $("weekly-modal-summary").textContent =
        `${String(record.week_start).slice(0, 10)} to ${String(record.week_end).slice(0, 10)} · ${record.transaction_count ?? 0} verified transactions`;

    const metrics = [
        ["Q1", record.q1],
        ["Q3", record.q3],
        ["IQR", record.iqr],
        ["Lower bound", record.lower_bound],
        ["Upper bound", record.upper_bound],
        [
            "Recommended price",
            record.recommended_price != null
                ? `${money(record.recommended_price)} / kg`
                : "Insufficient data",
        ],
    ];

    const metricContainer = $("weekly-modal-metrics");

    metricContainer.replaceChildren();

    metrics.forEach(([label, value]) => {
        const item = document.createElement("div");

        item.className =
            `weekly-detail-metric${label === "Recommended price" ? " is-recommended" : ""}`;

        const title = document.createElement("span");
        title.textContent = label;

        const content = document.createElement("strong");
        content.textContent = value ?? "—";

        item.append(title, content);
        metricContainer.append(item);
    });

    const unitLabel = "per kg";

    $("weekly-modal-result").textContent =
        `${record.included_count ?? 0} included, ${record.excluded_count ?? 0} excluded; ${record.recommended_price != null ? `${money(record.recommended_price)} ${unitLabel}` : "no recommended price (minimum not met)"}.`;

    const formulaLines = $("weekly-calculation-modal").querySelectorAll(
        ".weekly-formula-panel p",
    );

    if (formulaLines[3]) {
        formulaLines[3].textContent =
            "Recommended price = sum of included observed prices per kg ÷ included observations.";
    }

    const observationHeaders = $("weekly-calculation-modal").querySelectorAll(
        ".weekly-observation-table thead th",
    );

    if (observationHeaders[3]) {
        observationHeaders[3].textContent = "Weight";
    }

    if (observationHeaders[5]) {
        observationHeaders[5].textContent = "Price / kg";
    }

    const tbody = $("weekly-modal-observations");

    tbody.replaceChildren();

    (record.observations ?? []).forEach((observation) => {
        const row = tbody.insertRow();

        const values = [
            String(observation.date ?? "").slice(0, 10),
            observation.farmer_name || "—",
            observation.species_name || record.species?.name || "—",
            observation.weight_kg != null
                ? `${Number(observation.weight_kg).toFixed(2)} kg`
                : "—",
            money(observation.selling_price),
            observation.observed_price != null
                ? money(observation.observed_price)
                : observation.price_per_kg != null
                  ? money(observation.price_per_kg)
                  : "—",
            observation.included ? "Included" : "Excluded",
            observation.included ? "Within IQR bounds" : "IQR outlier",
        ];

        values.forEach((value, index) => {
            const cell = row.insertCell();

            cell.textContent = value;

            if (index === 6) {
                cell.className = observation.included
                    ? "weekly-decision-included"
                    : "weekly-decision-excluded";
            }
        });
    });

    const modal = $("weekly-calculation-modal");

    modal.hidden = false;
    modal.setAttribute("aria-hidden", "false");

    document.body.classList.add("weekly-modal-open");

    $("weekly-modal-close").focus();
}

function closeWeeklyModal() {
    const modal = $("weekly-calculation-modal");

    modal.hidden = true;
    modal.setAttribute("aria-hidden", "true");

    document.body.classList.remove("weekly-modal-open");
}

async function generateWeeklyEngine() {
    const date = $("weekly-engine-date").value;

    if (!date) {
        notify("Select a date within the completed week.");
        return;
    }

    const button = $("weekly-engine-generate");

    button.disabled = true;

    try {
        await api.post("/weekly-price-recommendations/generate", {
            week_date: date,
        });

        await loadWeeklyEngine();

        const message = $("weekly-engine-message");

        const weekRecords = weeklyState.records.filter(
            (record) =>
                String(record.week_start).slice(0, 10) === weeklyState.weekStart,
        );

        const available = weekRecords.filter(
            (record) => record.status === "available",
        ).length;

        const insufficient = weekRecords.filter(
            (record) => record.status !== "available",
        ).length;

        if (!weekRecords.length) {
            message.textContent =
                "No recommendations generated. There are no verified transaction observations for the selected week. Recommendations will appear once verified observations are recorded.";
        } else if (!available) {
            message.textContent =
                `Recommendations were checked, but none met the minimum of 4 included observations after IQR filtering. ${insufficient} purpose group(s) have insufficient data. See each species and sale purpose for details.`;
        } else {
            message.textContent =
                `Weekly recommendations processed: ${available} available and ${insufficient} with insufficient data. Purpose groups without verified observations are also identified in the species tables.`;
        }

        message.hidden = false;
    } catch (error) {
        notify(errorMessage(error));
    } finally {
        button.disabled = false;
    }
}

async function init() {
    if (!$("price-approvals-page")) return;

    bindEvents();

    const today = new Date();
    const mondayOffset = (today.getDay() + 6) % 7;

    today.setDate(today.getDate() - mondayOffset - 7);

    $("weekly-engine-date").value = [
        today.getFullYear(),
        String(today.getMonth() + 1).padStart(2, "0"),
        String(today.getDate()).padStart(2, "0"),
    ].join("-");

    $("weekly-engine-generate").addEventListener(
        "click",
        generateWeeklyEngine,
    );

    $("weekly-engine-refresh").addEventListener("click", loadWeeklyEngine);

    $("weekly-engine-date").addEventListener("change", loadWeeklyEngine);

    $("weekly-species-tabs").addEventListener("click", (event) => {
        const button = event.target.closest("[data-species]");

        if (button) {
            selectWeeklySpecies(button.dataset.species);
        }
    });

    $("weekly-modal-close").addEventListener("click", closeWeeklyModal);

    document
        .querySelectorAll("[data-weekly-modal-close]")
        .forEach((element) =>
            element.addEventListener("click", closeWeeklyModal),
        );

    document.addEventListener("keydown", (event) => {
        if (
            event.key === "Escape" &&
            !$("weekly-calculation-modal").hidden
        ) {
            closeWeeklyModal();
        }
    });

    try {
        const response = await api.get("/auth/me");
        const user = unwrap(response);

        if (!rolesFrom(user).includes("lgu_authority")) {
            notify("Only LGU Authorities can access the approval queue.");

            setEmpty("Access unavailable.");
            return;
        }

        state.authorized = true;

        await Promise.all([
            loadSpecies(),
            loadApprovals(),
            loadWeeklyEngine(),
        ]);
    } catch (error) {
        notify(errorMessage(error));

        setEmpty("Unable to initialize approval queue.");
    }
}

init();