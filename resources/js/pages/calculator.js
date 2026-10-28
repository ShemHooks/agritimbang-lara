import api from "../api";

const $ = (id) => document.getElementById(id);

// ============================================================
// STATE
// ============================================================

const state = {
    role: null,
    endpoint: null,
    livestock: [],
    activeTab: "weight",

    weight: {
        selectedId: null,
        page: 1,
        lastPage: 1,
        loading: false,
        historyRequest: 0,
    },

    valuation: {
        selectedId: null,
        page: 1,
        lastPage: 1,
        loading: false,
        historyRequest: 0,
        estimationRequest: 0,
    },

    fpc: {
        selectedId: null,
        valuationId: null,
        records: [],
        page: 1,
        lastPage: 1,
        loading: false,
        request: 0,
    },
};

// ============================================================
// SHARED HELPERS
// ============================================================

function unwrap(response) {
    return response.data?.data ?? response.data;
}

function extractList(response) {
    const data = unwrap(response);

    if (Array.isArray(data)) return data;
    if (Array.isArray(data?.data)) return data.data;

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

function showError(error) {
    const element = $("calc-error");
    if (!element) return;

    element.textContent =
        typeof error === "string" ? error : errorMessage(error);

    element.hidden = false;
}

function clearError() {
    const element = $("calc-error");
    if (!element) return;

    element.hidden = true;
    element.textContent = "";
}

function readable(value) {
    if (value === null || value === undefined || value === "") {
        return "—";
    }

    return String(value)
        .replace(/_/g, " ")
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

function formatNumber(value, digits = 2) {
    if (value === null || value === undefined || value === "") {
        return "—";
    }

    const number = Number(value);

    if (!Number.isFinite(number)) return "—";

    return number.toLocaleString("en-PH", {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    });
}

function formatWeight(value) {
    return formatNumber(value, 2);
}

function money(value) {
    if (value === null || value === undefined || value === "") {
        return "—";
    }

    const number = Number(value);

    if (!Number.isFinite(number)) return "—";

    return new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
    }).format(number);
}

function formatDate(value) {
    if (!value) return "—";

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? "—" : date.toLocaleString("en-PH");
}

function parseSnapshot(value) {
    if (!value) return {};

    if (typeof value === "object") return value;

    try {
        const parsed = JSON.parse(value);

        return parsed && typeof parsed === "object" ? parsed : {};
    } catch {
        return {};
    }
}

function livestockName(animal) {
    const identifier =
        animal.livestock_code ??
        animal.tag_number ??
        String(animal.id).slice(0, 8);

    const species = animal.species?.name ?? "Unknown species";
    const breed = animal.breed?.name ?? "Unspecified breed";

    return `${identifier} — ${species} / ${breed}`;
}

function findLivestock(id) {
    return state.livestock.find((animal) => String(animal.id) === String(id));
}

function addLivestock(animal) {
    if (!animal?.id || findLivestock(animal.id)) return;

    state.livestock.push(animal);
}

function fillLivestockSelect(selectId) {
    const select = $(selectId);
    if (!select) return;

    const previousValue = select.value;

    select.replaceChildren(new Option("Select livestock", ""));

    state.livestock.forEach((animal) => {
        select.add(new Option(livestockName(animal), animal.id));
    });

    if (findLivestock(previousValue)) {
        select.value = previousValue;
    }
}

function renderTable(bodyId, records, columns, emptyMessage) {
    const body = $(bodyId);
    if (!body) return;

    body.replaceChildren();

    if (!records.length) {
        const row = body.insertRow();
        const cell = row.insertCell();

        cell.colSpan = columns.length;
        cell.textContent = emptyMessage;
        return;
    }

    records.forEach((record) => {
        const row = body.insertRow();

        columns.forEach((column) => {
            row.insertCell().textContent = String(column(record));
        });
    });
}

function updatePagination(prefix, pagination, selectedId) {
    const previous = $(`${prefix}-prev`);
    const next = $(`${prefix}-next`);

    const label = $(`${prefix}-page-label`) ?? $(`${prefix}-page`);

    if (label) {
        label.textContent = `Page ${pagination.page} of ${pagination.lastPage}`;
    }

    if (previous) {
        previous.disabled = !selectedId || pagination.page <= 1;
    }

    if (next) {
        next.disabled = !selectedId || pagination.page >= pagination.lastPage;
    }
}

function setButtonLoading(button, loading, busyText, idleText) {
    if (!button) return;

    button.disabled = loading;
    button.textContent = loading ? busyText : idleText;
}

function estimationUrl(id) {
    return `${state.endpoint}/${encodeURIComponent(id)}/weight-estimations`;
}

function valuationUrl(id) {
    return `${state.endpoint}/${encodeURIComponent(id)}/valuations`;
}

// ============================================================
// AUTHENTICATION
// ============================================================

async function resolveRole() {
    const user = unwrap(await api.get("/auth/me"));

    const roles = (user.roles ?? []).map((role) =>
        String(typeof role === "string" ? role : (role.slug ?? role.name ?? ""))
            .toLowerCase()
            .replace(/[-\s]+/g, "_"),
    );

    if (roles.includes("farmer")) {
        state.role = "farmer";
        state.endpoint = "/farmer/livestock";
        return;
    }

    if (roles.includes("lgu_encoder")) {
        state.role = "lgu_encoder";
        state.endpoint = "/lgu/livestock";
        return;
    }

    throw new Error(
        "The Calculator is currently available to Farmers and LGU Encoders only.",
    );
}

// ============================================================
// LIVESTOCK DIRECTORY
// ============================================================

async function loadLivestock() {
    $("calc-livestock").replaceChildren(new Option("Loading livestock...", ""));

    const response = await api.get(state.endpoint, {
        params: { per_page: 100 },
    });

    state.livestock = extractList(response);

    const preselected = new URLSearchParams(window.location.search).get(
        "livestock_id",
    );

    if (preselected && !findLivestock(preselected)) {
        const detailResponse = await api.get(
            `${state.endpoint}/${encodeURIComponent(preselected)}`,
        );

        addLivestock(unwrap(detailResponse));
    }

    [
        "calc-livestock",
        "calc-valuation-livestock",
        "calc-fpc-livestock",
    ].forEach(fillLivestockSelect);

    if (preselected && findLivestock(preselected)) {
        $("calc-livestock").value = preselected;
        $("calc-valuation-livestock").value = preselected;
        $("calc-fpc-livestock").value = preselected;

        await Promise.all([
            selectWeightLivestock(preselected),
            selectValuationLivestock(preselected),
        ]);
    }
}

// ============================================================
// TAB NAVIGATION
// ============================================================

function setTab(tab) {
    const tabs = {
        weight: {
            panel: "calc-weight-panel",
            button: "calc-tab-weight",
        },
        valuation: {
            panel: "calc-valuation-panel",
            button: "calc-tab-valuation",
        },
        fpc: {
            panel: "calc-fpc-panel",
            button: "calc-tab-fpc",
        },
    };

    if (!tabs[tab]) return;

    state.activeTab = tab;

    Object.entries(tabs).forEach(([name, config]) => {
        const active = name === tab;

        const panel = $(config.panel);
        const button = $(config.button);

        if (panel) panel.hidden = !active;

        if (button) {
            button.classList.toggle("active", active);
            button.setAttribute("aria-selected", String(active));
        }
    });
}

async function switchToValuation() {
    clearError();
    setTab("valuation");

    const id = state.valuation.selectedId ?? state.weight.selectedId;

    if (!id) return;

    $("calc-valuation-livestock").value = id;

    if (state.valuation.selectedId !== id) {
        await selectValuationLivestock(id);
    }
}

async function switchToFpc() {
    clearError();
    setTab("fpc");

    const id =
        state.fpc.selectedId ??
        state.valuation.selectedId ??
        state.weight.selectedId;

    if (!id) return;

    $("calc-fpc-livestock").value = id;

    if (state.fpc.selectedId !== id) {
        await selectFpcLivestock(id);
    }
}

// ============================================================
// WEIGHT ESTIMATION
// ============================================================

function renderWeightAnimal(animal) {
    const element = $("calc-animal-info");

    if (!animal) {
        element.hidden = true;
        return;
    }

    element.textContent = [
        animal.species?.name ?? "Unknown species",
        animal.breed?.name ?? "Unspecified breed",
        readable(animal.sex),
        `Status: ${readable(animal.status)}`,
    ].join(" • ");

    element.hidden = false;
}

async function selectWeightLivestock(id) {
    const weight = state.weight;

    weight.selectedId = id || null;
    weight.page = 1;
    weight.lastPage = 1;
    weight.historyRequest++;

    $("calc-result").hidden = true;
    $("calc-empty").hidden = false;

    renderWeightAnimal(findLivestock(id));

    if (!id) {
        renderWeightHistory([]);
        return;
    }

    await loadWeightHistory();
}

function renderWeightResult(estimation) {
    $("calc-empty").hidden = true;
    $("calc-result").hidden = false;

    $("calc-weight-value").textContent = formatWeight(
        estimation.estimated_weight_kg,
    );

    $("calc-confidence").textContent = readable(estimation.estimate_confidence);

    $("calc-frame").textContent = readable(estimation.frame_category);

    $("calc-formula").textContent = estimation.formula_code ?? "—";

    $("calc-version").textContent = estimation.formula_version ?? "—";

    const container = $("calc-warnings");
    container.replaceChildren();

    let warnings = estimation.warnings ?? [];

    if (typeof warnings === "string") {
        try {
            warnings = JSON.parse(warnings);
        } catch {
            warnings = [warnings];
        }
    }

    if (!Array.isArray(warnings)) {
        warnings = warnings ? [String(warnings)] : [];
    }

    if (!warnings.length) {
        container.hidden = true;
        return;
    }

    const heading = document.createElement("strong");
    heading.textContent = "Estimation warnings";

    const list = document.createElement("ul");

    warnings.forEach((warning) => {
        const item = document.createElement("li");
        item.textContent = String(warning);
        list.append(item);
    });

    container.append(heading, list);
    container.hidden = false;
}

function renderWeightHistory(records) {
    renderTable(
        "calc-history-body",
        records,
        [
            (record) => formatDate(record.created_at),
            (record) => `${formatWeight(record.estimated_weight_kg)} kg`,
            (record) => record.formula_code ?? "—",
            (record) => readable(record.estimate_confidence),
            (record) => readable(record.frame_category),
        ],
        "No weight estimations found.",
    );

    updatePagination("calc", state.weight, state.weight.selectedId);
}

async function loadWeightHistory() {
    const weight = state.weight;

    if (!weight.selectedId) return;

    const requestId = ++weight.historyRequest;
    const livestockId = weight.selectedId;

    try {
        const response = await api.get(estimationUrl(livestockId), {
            params: { page: weight.page },
        });

        if (
            requestId !== weight.historyRequest ||
            livestockId !== weight.selectedId
        ) {
            return;
        }

        const paginator = unwrap(response);

        weight.lastPage = Number(paginator.last_page ?? 1);

        renderWeightHistory(paginator.data ?? []);
    } catch (error) {
        if (requestId === weight.historyRequest) {
            showError(error);
        }
    }
}

function measurement(id, label, maximum) {
    const raw = $(id).value.trim();

    if (raw === "") return null;

    const number = Number(raw);

    if (!Number.isFinite(number) || number <= 0 || number > maximum) {
        throw new Error(
            `${label} must be greater than zero and at most ${maximum} cm.`,
        );
    }

    return number;
}

function weightPayload() {
    const fields = [
        ["calc-girth", "heart_girth_cm", "Heart girth", 500],
        ["calc-length", "body_length_cm", "Body length", 500],
        ["calc-height", "rump_height_cm", "Rump height", 300],
    ];

    const payload = {};

    fields.forEach(([id, field, label, maximum]) => {
        const value = measurement(id, label, maximum);

        if (value !== null) {
            payload[field] = value;
        }
    });

    if (!Object.keys(payload).length) {
        throw new Error(
            "Enter at least one measurement. The selected formula may require additional measurements.",
        );
    }

    return payload;
}

async function calculateWeight(event) {
    event.preventDefault();

    const weight = state.weight;

    if (weight.loading) return;

    clearError();

    const livestockId = weight.selectedId;

    if (!livestockId) {
        showError("Please select a livestock record.");
        return;
    }

    let payload;

    try {
        payload = weightPayload();
    } catch (error) {
        showError(error);
        return;
    }

    const button = $("calc-submit");

    weight.loading = true;

    setButtonLoading(
        button,
        true,
        "Calculating...",
        "Calculate & Save Estimation",
    );

    try {
        const response = await api.post(estimationUrl(livestockId), payload);

        const estimation = unwrap(response);

        if (weight.selectedId !== livestockId) return;

        renderWeightResult(estimation);

        weight.page = 1;
        await loadWeightHistory();

        if (state.valuation.selectedId === livestockId) {
            await loadValuationEstimations(livestockId);
        }
    } catch (error) {
        showError(error);
    } finally {
        weight.loading = false;

        setButtonLoading(
            button,
            false,
            "Calculating...",
            "Calculate & Save Estimation",
        );
    }
}

// ============================================================
// LIVESTOCK VALUATION
// ============================================================

function renderValuationAnimal(animal) {
    const element = $("calc-valuation-animal");

    if (!animal) {
        element.hidden = true;
        return;
    }

    element.textContent = [
        animal.species?.name ?? "Unknown species",
        animal.breed?.name ?? "Unspecified breed",
        `Status: ${readable(animal.status)}`,
        animal.actual_weight_kg != null
            ? `Actual weight: ${formatWeight(animal.actual_weight_kg)} kg`
            : "No actual weight recorded",
    ].join(" • ");

    element.hidden = false;
}

async function selectValuationLivestock(id) {
    const valuation = state.valuation;

    valuation.selectedId = id || null;
    valuation.page = 1;
    valuation.lastPage = 1;

    ++valuation.historyRequest;
    ++valuation.estimationRequest;

    $("calc-valuation-result").hidden = true;
    $("calc-valuation-empty").hidden = false;

    renderValuationAnimal(findLivestock(id));

    $("calc-valuation-estimation").replaceChildren(
        new Option("Automatic — use latest estimation", ""),
    );

    if (!id) {
        renderValuationHistory([]);
        return;
    }

    await Promise.all([loadValuationEstimations(id), loadValuationHistory()]);
}

async function loadValuationEstimations(id) {
    const valuation = state.valuation;
    const requestId = ++valuation.estimationRequest;

    try {
        const response = await api.get(estimationUrl(id), {
            params: { page: 1 },
        });

        if (
            requestId !== valuation.estimationRequest ||
            id !== valuation.selectedId
        ) {
            return;
        }

        const records = extractList(response);
        const select = $("calc-valuation-estimation");
        const previousValue = select.value;

        select.replaceChildren(
            new Option("Automatic — use latest estimation", ""),
        );

        records.forEach((record) => {
            select.add(
                new Option(
                    `${formatWeight(record.estimated_weight_kg)} kg — ${formatDate(record.created_at)}`,
                    record.id,
                ),
            );
        });

        if (
            [...select.options].some((option) => option.value === previousValue)
        ) {
            select.value = previousValue;
        }
    } catch (error) {
        if (requestId === valuation.estimationRequest) {
            showError(error);
        }
    }
}

function renderValuationResult(valuation) {
    $("calc-valuation-empty").hidden = true;
    $("calc-valuation-result").hidden = false;

    $("calc-fair-value").textContent = money(valuation.estimated_value);

    $("calc-weight-source").textContent = readable(valuation.weight_type);

    $("calc-weight-used").textContent =
        `${formatWeight(valuation.weight_used_kg)} kg`;

    $("calc-base-price").textContent = money(valuation.base_price_per_kg);

    $("calc-adjusted-price").textContent = money(valuation.final_price_per_kg);

    $("calc-valuation-version").textContent =
        valuation.calculation_version ?? "—";

    const factors = [
        ["Breed factor", valuation.breed_factor],
        ["Age factor", valuation.age_factor],
        ["Reproductive factor", valuation.reproductive_factor],
        ["Frame factor", valuation.frame_factor],
        ["Condition factor", valuation.condition_factor],
    ];

    const container = $("calc-adjustment-list");
    container.replaceChildren();

    factors.forEach(([name, value]) => {
        const row = document.createElement("div");
        row.className = "calc-factor-row";

        const title = document.createElement("span");
        title.textContent = name;

        const amount = document.createElement("strong");
        amount.textContent = value == null ? "—" : `${formatNumber(value, 4)}×`;

        row.append(title, amount);
        container.append(row);
    });

    const snapshot = valuation.calculation_snapshot ?? {};

    $("calc-snapshot-json").textContent =
        typeof snapshot === "string"
            ? snapshot
            : JSON.stringify(snapshot, null, 2);
}

function renderValuationHistory(records) {
    renderTable(
        "calc-valuation-history",
        records,
        [
            (record) => formatDate(record.created_at),
            (record) => `${formatWeight(record.weight_used_kg)} kg`,
            (record) => money(record.final_price_per_kg),
            (record) => money(record.estimated_value),
            (record) => record.calculation_version ?? "—",
        ],
        "No valuations found.",
    );

    updatePagination(
        "calc-valuation",
        state.valuation,
        state.valuation.selectedId,
    );
}

async function loadValuationHistory() {
    const valuation = state.valuation;

    if (!valuation.selectedId) return;

    const requestId = ++valuation.historyRequest;
    const livestockId = valuation.selectedId;

    try {
        const response = await api.get(valuationUrl(livestockId), {
            params: { page: valuation.page },
        });

        if (
            requestId !== valuation.historyRequest ||
            livestockId !== valuation.selectedId
        ) {
            return;
        }

        const paginator = unwrap(response);

        valuation.lastPage = Number(paginator.last_page ?? 1);

        renderValuationHistory(paginator.data ?? []);
    } catch (error) {
        if (requestId === valuation.historyRequest) {
            showError(error);
        }
    }
}

async function calculateValuation(event) {
    event.preventDefault();

    const valuation = state.valuation;

    if (valuation.loading) return;

    clearError();

    const livestockId = valuation.selectedId;

    if (!livestockId) {
        showError("Please select a livestock record.");
        return;
    }

    const estimationId = $("calc-valuation-estimation").value;

    const payload = estimationId ? { weight_estimation_id: estimationId } : {};

    const button = $("calc-valuation-submit");

    valuation.loading = true;

    setButtonLoading(
        button,
        true,
        "Calculating valuation...",
        "Calculate & Save Valuation",
    );

    try {
        const response = await api.post(valuationUrl(livestockId), payload);

        const result = unwrap(response);

        if (valuation.selectedId !== livestockId) return;

        renderValuationResult(result);

        valuation.page = 1;
        await loadValuationHistory();

        // Refresh the FPC breakdown when a new valuation is saved.
        if (state.fpc.selectedId === livestockId) {
            await selectFpcLivestock(livestockId);
        }
    } catch (error) {
        showError(error);
    } finally {
        valuation.loading = false;

        setButtonLoading(
            button,
            false,
            "Calculating valuation...",
            "Calculate & Save Valuation",
        );
    }
}

// ============================================================
// FPC BREAKDOWN
// ============================================================

function fpcField(container, name, value) {
    const row = document.createElement("div");
    row.className = "calc-factor-row";

    const title = document.createElement("span");
    title.textContent = name;

    const result = document.createElement("strong");

    result.textContent =
        value === null || value === undefined || value === ""
            ? "—"
            : String(value);

    row.append(title, result);
    container.append(row);
}

function fpcFactor(value) {
    return value === null || value === undefined || value === ""
        ? "—"
        : `${formatNumber(value, 4)}×`;
}

function clearFpc() {
    $("calc-fpc-content").hidden = true;
    $("calc-fpc-empty").hidden = false;
}

function renderFpc(record) {
    const snapshot = parseSnapshot(record.calculation_snapshot);

    const weight = snapshot.weight ?? {};
    const price = snapshot.price_reference ?? {};
    const calculation = snapshot.calculation ?? {};

    $("calc-fpc-empty").hidden = true;
    $("calc-fpc-content").hidden = false;

    // Summary cards

    $("calc-fpc-value").textContent = money(record.estimated_value);

    $("calc-fpc-version").textContent = record.calculation_version ?? "—";

    $("calc-fpc-weight").textContent =
        `${formatWeight(record.weight_used_kg)} kg`;

    $("calc-fpc-weight-source").textContent = readable(record.weight_type);

    $("calc-fpc-base").textContent = money(record.base_price_per_kg);

    $("calc-fpc-adjusted").textContent = money(record.final_price_per_kg);

    $("calc-fpc-final-price").textContent = money(record.final_price_per_kg);

    // Pricing adjustment factors

    const factors = $("calc-fpc-factors");
    factors.replaceChildren();

    [
        ["Breed factor", record.breed_factor],
        ["Age factor", record.age_factor],
        ["Reproductive factor", record.reproductive_factor],
        ["Frame factor", record.frame_factor],
        ["Condition factor", record.condition_factor],
    ].forEach(([name, value]) => {
        fpcField(factors, name, fpcFactor(value));
    });

    // Weight and classification evidence

    const weightDetails = $("calc-fpc-weight-details");
    weightDetails.replaceChildren();

    [
        ["Weight source", readable(record.weight_type)],
        ["Weight used", `${formatWeight(record.weight_used_kg)} kg`],
        [
            "Actual scale weight",
            weight.actual_weight_kg == null
                ? "Not recorded"
                : `${formatWeight(weight.actual_weight_kg)} kg`,
        ],
        [
            "Estimated weight",
            weight.estimated_weight_kg == null
                ? "Not recorded"
                : `${formatWeight(weight.estimated_weight_kg)} kg`,
        ],
        ["WET formula", weight.formula_code],
        ["WET version", weight.formula_version],
        ["Estimation confidence", readable(weight.estimate_confidence)],
        ["Frame category", readable(weight.frame_category)],
        ["Frame Z-score", weight.frame_z],
        ["Frame statistics version", weight.frame_stats_version],
    ].forEach(([name, value]) => {
        fpcField(weightDetails, name, value);
    });

    // Official price reference snapshot

    const priceDetails = $("calc-fpc-price-details");
    priceDetails.replaceChildren();

    if (price && typeof price === "object") {
        Object.entries(price).forEach(([key, value]) => {
            fpcField(
                priceDetails,
                readable(key),
                value && typeof value === "object"
                    ? JSON.stringify(value)
                    : value,
            );
        });
    }

    if (!priceDetails.children.length) {
        fpcField(priceDetails, "Price reference ID", record.price_reference_id);
    }

    // Calculation explanation

    const details = $("calc-fpc-calculation-details");
    details.replaceChildren();

    [
        ["Base price / kg", money(record.base_price_per_kg)],
        ["Breed factor", fpcFactor(record.breed_factor)],
        ["Age factor", fpcFactor(record.age_factor)],
        ["Reproductive factor", fpcFactor(record.reproductive_factor)],
        ["Frame factor", fpcFactor(record.frame_factor)],
        ["Condition factor", fpcFactor(record.condition_factor)],
        ["Final price / kg", money(record.final_price_per_kg)],
        ["Weight used", `${formatWeight(record.weight_used_kg)} kg`],
        ["Fair market value", money(record.estimated_value)],
        ["Currency", calculation.currency ?? "PHP"],
        ["Valuation date", snapshot.valuation_date],
    ].forEach(([name, value]) => {
        fpcField(details, name, value);
    });

    // Immutable backend snapshot

    $("calc-fpc-snapshot").textContent = JSON.stringify(snapshot, null, 2);
}

function renderFpcValuationOptions() {
    const select = $("calc-fpc-valuation");
    const previousValue = state.fpc.valuationId;

    select.replaceChildren(new Option("Select a valuation", ""));

    state.fpc.records.forEach((record) => {
        select.add(
            new Option(
                `${formatDate(record.created_at)} — ${money(record.estimated_value)}`,
                record.id,
            ),
        );
    });

    const selectedRecord = state.fpc.records.find(
        (record) => String(record.id) === String(previousValue),
    );

    if (selectedRecord) {
        select.value = selectedRecord.id;
        renderFpc(selectedRecord);
    } else if (state.fpc.records.length) {
        const latest = state.fpc.records[0];

        state.fpc.valuationId = latest.id;
        select.value = latest.id;
        renderFpc(latest);
    } else {
        state.fpc.valuationId = null;
        clearFpc();
    }

    updatePagination("calc-fpc", state.fpc, state.fpc.selectedId);
}

async function selectFpcLivestock(id) {
    const fpc = state.fpc;

    fpc.selectedId = id || null;
    fpc.valuationId = null;
    fpc.records = [];
    fpc.page = 1;
    fpc.lastPage = 1;

    ++fpc.request;

    clearFpc();

    $("calc-fpc-valuation").replaceChildren(
        new Option("Select a valuation", ""),
    );

    if (!id) {
        updatePagination("calc-fpc", fpc, null);
        return;
    }

    await loadFpcHistory();
}

async function loadFpcHistory() {
    const fpc = state.fpc;

    if (!fpc.selectedId || fpc.loading) return;

    const requestId = ++fpc.request;
    const livestockId = fpc.selectedId;

    fpc.loading = true;

    try {
        const response = await api.get(valuationUrl(livestockId), {
            params: { page: fpc.page },
        });

        if (requestId !== fpc.request || livestockId !== fpc.selectedId) {
            return;
        }

        const paginator = unwrap(response);

        fpc.lastPage = Number(paginator.last_page ?? 1);
        fpc.records = paginator.data ?? [];

        renderFpcValuationOptions();
    } catch (error) {
        if (requestId === fpc.request) {
            showError(error);
        }
    } finally {
        fpc.loading = false;
    }
}

function selectFpcValuation(id) {
    state.fpc.valuationId = id || null;

    const record = state.fpc.records.find(
        (item) => String(item.id) === String(id),
    );

    if (!record) {
        clearFpc();
        return;
    }

    renderFpc(record);
}

// ============================================================
// EVENTS
// ============================================================

function bindWeightEvents() {
    $("calc-form").addEventListener("submit", calculateWeight);

    $("calc-livestock").addEventListener("change", (event) => {
        clearError();

        selectWeightLivestock(event.target.value).catch(showError);
    });

    $("calc-prev").addEventListener("click", async () => {
        if (state.weight.page <= 1) return;

        state.weight.page--;
        await loadWeightHistory();
    });

    $("calc-next").addEventListener("click", async () => {
        if (state.weight.page >= state.weight.lastPage) {
            return;
        }

        state.weight.page++;
        await loadWeightHistory();
    });
}

function bindValuationEvents() {
    $("calc-tab-weight").addEventListener("click", () => {
        clearError();
        setTab("weight");
    });

    $("calc-tab-valuation").addEventListener("click", () => {
        switchToValuation().catch(showError);
    });

    $("calc-valuation-livestock").addEventListener("change", (event) => {
        clearError();

        selectValuationLivestock(event.target.value).catch(showError);
    });

    $("calc-valuation-form").addEventListener("submit", calculateValuation);

    $("calc-valuation-prev").addEventListener("click", async () => {
        if (state.valuation.page <= 1) return;

        state.valuation.page--;
        await loadValuationHistory();
    });

    $("calc-valuation-next").addEventListener("click", async () => {
        if (state.valuation.page >= state.valuation.lastPage) {
            return;
        }

        state.valuation.page++;
        await loadValuationHistory();
    });
}

function bindFpcEvents() {
    $("calc-tab-fpc").addEventListener("click", () => {
        switchToFpc().catch(showError);
    });

    $("calc-fpc-livestock").addEventListener("change", (event) => {
        clearError();

        selectFpcLivestock(event.target.value).catch(showError);
    });

    $("calc-fpc-valuation").addEventListener("change", (event) => {
        selectFpcValuation(event.target.value);
    });

    const previous = $("calc-fpc-prev");
    const next = $("calc-fpc-next");

    if (previous) {
        previous.addEventListener("click", async () => {
            if (state.fpc.page <= 1 || state.fpc.loading) {
                return;
            }

            state.fpc.page--;
            await loadFpcHistory();
        });
    }

    if (next) {
        next.addEventListener("click", async () => {
            if (state.fpc.page >= state.fpc.lastPage || state.fpc.loading) {
                return;
            }

            state.fpc.page++;
            await loadFpcHistory();
        });
    }
}

// ============================================================
// INITIALIZATION
// ============================================================

async function init() {
    if (!$("weight-calculator")) return;

    bindWeightEvents();
    bindValuationEvents();
    bindFpcEvents();

    setTab("weight");

    try {
        await resolveRole();
        await loadLivestock();
    } catch (error) {
        showError(error);

        [
            "calc-livestock",
            "calc-valuation-livestock",
            "calc-fpc-livestock",
        ].forEach((id) => {
            const select = $(id);

            if (select) {
                select.replaceChildren(
                    new Option("Unable to load livestock", ""),
                );
            }
        });
    }
}

init();
