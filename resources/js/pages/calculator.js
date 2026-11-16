import api from "../api";
import { formatDateTime as sharedFormatDateTime } from "../utils/date-format";

const $ = (id) => document.getElementById(id);

// ============================================================
// STATE
// ============================================================

const state = {
    role: null,
    endpoint: null,
    livestock: [],
    species: [],
    manualBreeds: [],
    activeTab: "weight",
    manualPreviewPayload: null,
    manualPreviewResult: null,
    manualValuationResult: null,
    manualWeightManuallyEntered: false,
    conditionAssessment: { answers: {}, complete: false, total: null, conditionScore: null, result: null },
    livestockWithWeight: [],
    livestockWithValuation: [],

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

    element.classList.remove("calc-success-message");
    element.hidden = false;
}

function clearError() {
    const element = $("calc-error");
    if (!element) return;

    element.hidden = true;
    element.textContent = "";
    element.classList.remove("calc-success-message");
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

function formatDate(value) { return sharedFormatDateTime(value); }

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

function normalizeLivestockSex(value) {
    const sex = String(value ?? "").trim().toLowerCase();
    if (["male", "m"].includes(sex)) return "male";
    if (["female", "f"].includes(sex)) return "female";
    return "unknown";
}

function isAvailableLivestock(animal) {
    return String(animal?.status ?? "").trim().toLowerCase() === "available";
}

function findLivestock(id) {
    return state.livestock.find((animal) => String(animal.id) === String(id));
}

function addLivestock(animal) {
    if (!animal?.id || !isAvailableLivestock(animal)) return;

    const existingIndex = state.livestock.findIndex(
        (item) => String(item.id) === String(animal.id),
    );

    if (existingIndex >= 0) {
        // Replace the paginated/list representation with the full detail
        // response so species, breed, age, sex and related profile fields
        // are never lost when arriving from Livestock Details.
        state.livestock[existingIndex] = {
            ...state.livestock[existingIndex],
            ...animal,
            species: animal.species ?? state.livestock[existingIndex].species,
            breed: animal.breed ?? state.livestock[existingIndex].breed,
        };
        return;
    }

    state.livestock.push(animal);
}

function fillLivestockSelect(selectId) {
    const select = $(selectId);
    if (!select) return;

    const previousValue = select.value;
    const isWeightCalculator = selectId === "calc-livestock";
    const isFpcBreakdown = selectId === "calc-fpc-livestock";
    const source = isFpcBreakdown
        ? state.livestockWithValuation
        : (selectId === "calc-valuation-livestock" ? state.livestock : state.livestock);

    select.replaceChildren(new Option(
        isWeightCalculator || selectId === "calc-valuation-livestock"
            ? "Calculate without a saved livestock record"
            : "Select livestock with a saved valuation",
        "",
    ));

    source.forEach((animal) => {
        select.add(new Option(livestockName(animal), animal.id));
    });

    if (source.some((animal) => String(animal.id) === String(previousValue))) {
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

    const allRecords = extractList(response);
    // The calculators operate on available animals; historical FPC breakdown
    // remains accessible for records that already have saved valuations.
    state.livestock = allRecords.filter(isAvailableLivestock);
    state.livestockWithWeight = state.livestock.filter((animal) =>
        Number(animal.actual_weight_kg) > 0 || Number(animal.weight_estimations_count) > 0
    );
    state.livestockWithValuation = allRecords.filter((animal) =>
        Number(animal.valuations_count) > 0
    );

    const preselected = new URLSearchParams(window.location.search).get(
        "livestock_id",
    );

    // Always fetch the selected record's detail endpoint, even when the
    // index response already contained a lightweight representation. This
    // guarantees the calculator receives the authoritative species, breed,
    // sex, age, status and other saved profile attributes.
    if (preselected) {
        try {
            const detailResponse = await api.get(
                `${state.endpoint}/${encodeURIComponent(preselected)}`,
            );
            const detailedAnimal = unwrap(detailResponse);

            if (!detailedAnimal?.id || String(detailedAnimal.id) !== String(preselected)) {
                throw new Error("The selected livestock details could not be loaded.");
            }

            addLivestock(detailedAnimal);
        } catch (error) {
            state.livestock = state.livestock.filter(
                (animal) => String(animal.id) !== String(preselected),
            );
            console.warn("Unable to load full livestock profile for weight estimation.", error);
        }
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
    } else if (preselected) {
        showError(
            "This livestock is no longer available. Only available livestock can be used in the calculators.",
        );
    }
}

// ============================================================
// MANUAL LIVESTOCK PROFILE
// ============================================================

function invalidateManualPreview() {
    state.manualPreviewPayload = null;
    state.manualPreviewResult = null;
    state.manualValuationResult = null;
    if ($("calc-valuation-farmer-actions")) $("calc-valuation-farmer-actions").hidden = true;
    if ($("calc-result")) $("calc-result").hidden = true;
    if ($("calc-empty")) $("calc-empty").hidden = false;
    if ($("calc-farmer-actions")) $("calc-farmer-actions").hidden = true;
}

const measurementUnits = {
    cm: { centimeters: 1, guide: "1 cm = 1 cm." },
    dupa: { centimeters: 180, guide: "Usa ka dupa kay mga 6 feet, 1.8 meters o 180 cm." },
    tiil: { centimeters: 30.48, guide: "Usa ka tiil kay mga 12 inches o 30.48 cm." },
    bisig: { centimeters: 45.72, guide: "Usa ka bisig/bukton kay mga 18 inches o 45.72 cm." },
    dangaw: { centimeters: 20.32, guide: "Usa ka dangaw kay mga 8 inches o mga 20.32 cm." },
};

async function loadManualSpecies() {
    const response = await api.get("/reference/species");
    state.species = extractList(response).filter((item) => item?.is_active !== false && item?.is_active !== 0 && item?.is_active !== "0");
    const select = $("calc-species");
    select.replaceChildren(new Option("Select species", ""));
    state.species.forEach((item) => select.add(new Option(item.name, item.id)));

    const valuationSpecies = $("calc-valuation-species");
    if (valuationSpecies) {
        valuationSpecies.replaceChildren(new Option("Select species", ""));
        state.species.forEach((item) => valuationSpecies.add(new Option(item.name, item.id)));
    }
}

async function loadManualBreeds(speciesId, selectedBreedId = "") {
    const select = $("calc-breed");
    select.replaceChildren(new Option(speciesId ? "Loading breeds..." : "Select species first", ""));
    select.disabled = true;
    state.manualBreeds = [];
    if (!speciesId) return;

    const response = await api.get(`/reference/species/${encodeURIComponent(speciesId)}/breeds`);
    state.manualBreeds = extractList(response).filter((item) => item?.is_active !== false && item?.is_active !== 0 && item?.is_active !== "0");
    select.replaceChildren(new Option("Select breed", ""));
    state.manualBreeds.forEach((item) => select.add(new Option(item.name, item.id)));
    select.disabled = false;
    select.value = selectedBreedId || "";
    updateBreedRequirement();
}

function selectedSpecies() {
    return state.species.find((item) => String(item.id) === String($("calc-species")?.value));
}

function speciesDescriptor(speciesLike) {
    return [
        speciesLike?.code,
        speciesLike?.name,
        speciesLike?.species_code,
        speciesLike?.species_name,
        speciesLike?.species?.code,
        speciesLike?.species?.name,
    ].filter((value) => value !== null && value !== undefined)
        .join(" ")
        .toLowerCase()
        .replace(/[_-]+/g, " ");
}

function isCarabaoSpecies(speciesLike) {
    const value = speciesDescriptor(speciesLike);
    return /carabao|kalabaw|\bcar\b/.test(value);
}

function isHogSpecies(speciesLike) {
    const value = speciesDescriptor(speciesLike);
    return /hog|pig|baboy|\bpig\b/.test(value);
}

function updateBreedRequirement() {
    const species = selectedSpecies();
    // Hogs have a general fallback formula, so breed must never block
    // weight estimation when the record has no breed assigned.
    const required = isCarabaoSpecies(species);
    const select = $("calc-breed");
    const help = $("calc-breed-help");
    if (select) {
        select.required = required;
        select.disabled = !select.options.length || select.options.length <= 1;
    }
    if (help) {
        help.textContent = required
            ? "Required for this species so the system can select the correct formula."
            : isHogSpecies(species)
                ? "Optional for hogs. An appropriate fallback formula is used when no breed is recorded."
                : "Optional for this species; available breeds are shown when selected.";
    }
}

function isGoatSpecies(speciesLike) {
    const values = [
        speciesLike?.code,
        speciesLike?.name,
        speciesLike?.species_code,
        speciesLike?.species_name,
        speciesLike?.species?.code,
        speciesLike?.species?.name,
    ];
    const descriptor = values
        .filter((value) => value !== null && value !== undefined)
        .join(" ")
        .toLowerCase()
        .replace(/[_-]+/g, " ");

    // Some seeded/API species codes are abbreviated (for example, GOA)
    // rather than the full word "GOAT". Check both code and display name.
    return /goat|kanding|\bgoa\b/.test(descriptor);
}

function updateRumpHeightForSpecies() {
    // When launched from Livestock Details, the manual species selector is
    // hidden and empty. Prefer the selected saved livestock profile so this
    // final page initialization cannot override the correct goat setting.
    const selectedId = $("calc-livestock")?.value || state.weight?.selectedId;
    const savedAnimal = selectedId ? findLivestock(selectedId) : null;
    const speciesSource = savedAnimal ?? selectedSpecies();
    const isGoat = isGoatSpecies(speciesSource);
    const input = $("calc-height");
    const unit = $("calc-height-unit");
    const help = $("calc-height-help");

    // Keep the control visible for all species; only goats can enter a value.
    if (input) input.disabled = !isGoat;
    if (unit) unit.disabled = !isGoat;
    if (!isGoat && input) input.value = "";
    if (help) {
        help.textContent = isGoat
            ? "Required for goats using the goat weight-estimation formula."
            : "Not applicable to this species; the field is disabled.";
    }
}

function updateMeasurementGuide(unitId, guideId) {
    const unit = measurementUnits[$(unitId)?.value] ?? measurementUnits.cm;
    const guide = $(guideId);
    if (guide) guide.textContent = unit.guide;
}

function updateAllMeasurementGuides() {
    updateMeasurementGuide("calc-girth-unit", "calc-girth-guide");
    updateMeasurementGuide("calc-length-unit", "calc-length-guide");
    updateMeasurementGuide("calc-height-unit", "calc-height-guide");
}

async function fillManualProfileFromLivestock(animal) {
    if (!animal) return;
    $("calc-species").value = animal.species_id ?? animal.species?.id ?? "";
    await loadManualBreeds($("calc-species").value, animal.breed_id ?? animal.breed?.id ?? "");
    $("calc-sex").value = animal.sex ?? "unknown";
    $("calc-age-months").value = animal.age_months ?? "";
    updateRumpHeightForSpecies();
}

function buildManualProfilePayload() {
    const species = selectedSpecies();
    if (!species?.id) throw new Error("Select the livestock species.");
    const ageMonthsRaw = $("calc-age-months").value.trim();

    if (isGoatSpecies(species) && ageMonthsRaw === "") {
        throw new Error("Enter the goat's age in months so the correct formula can be selected.");
    }
    if (isCarabaoSpecies(species) && !$("calc-breed").value) {
        throw new Error("Select the livestock breed so the correct formula can be selected.");
    }
    const sex = $("calc-sex").value;
    if (!sex) throw new Error("Select the livestock's sex.");

    return {
        species_id: species.id,
        breed_id: $("calc-breed").value || null,
        sex,
        age_months: ageMonthsRaw === "" ? null : Number(ageMonthsRaw),
    };
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

    if (!id) {
        $("calc-valuation-livestock").value = "";
        await selectValuationLivestock("");
        if (state.manualPreviewResult?.estimated_weight_kg) {
            $("calc-manual-valuation-weight").value = Number(state.manualPreviewResult.estimated_weight_kg).toFixed(2);
            state.manualWeightManuallyEntered = false;
        }
        $("calc-valuation-submit").textContent = "Calculate Valuation Preview";
        return;
    }

    $("calc-valuation-livestock").value = id;
    $("calc-valuation-submit").textContent = "Calculate & Save Valuation";

    if (state.valuation.selectedId !== id) {
        await selectValuationLivestock(id);
    }
    if (state.manualPreviewResult?.estimated_weight_kg) {
        $("calc-manual-valuation-weight").value = Number(state.manualPreviewResult.estimated_weight_kg).toFixed(2);
        state.manualWeightManuallyEntered = false;
    }
}

async function switchToFpc() {
    clearError();
    setTab("fpc");

    const preferredId =
        state.fpc.selectedId ??
        state.valuation.selectedId ??
        state.weight.selectedId;
    const id = state.livestockWithValuation.some(
        (animal) => String(animal.id) === String(preferredId),
    ) ? preferredId : null;

    if (!id) {
        $("calc-fpc-livestock").value = "";
        await selectFpcLivestock("");
        return;
    }

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
        animal.age_months !== null && animal.age_months !== undefined
            ? `Age: ${animal.age_months} months`
            : "Age: Not recorded",
        `Status: ${readable(animal.status)}`,
    ].join(" • ");

    element.hidden = false;
}

function syncRumpHeightField(animal) {
    const input = $("calc-height");
    const unit = $("calc-height-unit");
    const selectedLabel = $("calc-livestock")?.selectedOptions?.[0]?.textContent ?? "";
    const isGoat = isGoatSpecies(animal) ||
        isGoatSpecies({ name: selectedLabel });

    // Do not hide the control: keep the layout stable and disable it for
    // non-goat species (including carabao).
    if (input) {
        input.disabled = !isGoat;
        if (!isGoat) input.value = "";
    }
    if (unit) unit.disabled = !isGoat;
    const help = $("calc-height-help");
    if (help) {
        help.textContent = isGoat
            ? "Required for goats using the goat weight-estimation formula."
            : "Not applicable to this species; the field is disabled.";
    }
}

function clearWeightMeasurementInputs() {
    ["calc-girth", "calc-length", "calc-height"].forEach((id) => {
        const input = $(id);
        if (input) input.value = "";
    });
    ["calc-girth-unit", "calc-length-unit", "calc-height-unit"].forEach((id) => {
        const select = $(id);
        if (select) select.value = "cm";
    });
    updateAllMeasurementGuides();
}

async function selectWeightLivestock(id) {
    const weight = state.weight;
    const nextId = id || null;
    const changedRecord = String(weight.selectedId || "") !== String(nextId || "");
    if (changedRecord) {
        clearWeightMeasurementInputs();
        state.manualWeightManuallyEntered = false;
        if ($("calc-manual-valuation-weight")) $("calc-manual-valuation-weight").value = "";
    }

    weight.selectedId = nextId;

    if (nextId) {
        const currentRecord = findLivestock(nextId);
        try {
            const detailResponse = await api.get(
                `${state.endpoint}/${encodeURIComponent(nextId)}`,
            );
            const detailedAnimal = unwrap(detailResponse);

            if (
                !detailedAnimal?.id ||
                String(detailedAnimal.id) !== String(nextId) ||
                !isAvailableLivestock(detailedAnimal)
            ) {
                throw new Error("The selected livestock is no longer available for weight estimation.");
            }

            addLivestock(detailedAnimal);
        } catch (error) {
            if (!currentRecord) throw error;
            console.warn("Could not refresh livestock details; using the available saved profile.", error);
        }
    }

    const manualEntry = $("calc-manual-entry-fields");
    if (manualEntry) manualEntry.hidden = Boolean(id);

    // Species and sex are required only for manual calculations. When an
    // existing record is selected, those controls are hidden and their values
    // come from the record; leaving them required would cause the browser to
    // block form submission before calculateWeight() can run.
    const speciesInput = $("calc-species");
    const sexInput = $("calc-sex");
    if (speciesInput) speciesInput.required = !id;
    if (sexInput) sexInput.required = !id;
    // Keep measurement inputs and the Calculate button available for existing records.
    weight.page = 1;
    weight.lastPage = 1;
    weight.historyRequest++;

    $("calc-result").hidden = true;
    $("calc-empty").hidden = false;
    const farmerActions = $("calc-farmer-actions");
    if (farmerActions) {
        farmerActions.hidden = !(id && state.role === "farmer");
        if ($("calc-save-livestock")) $("calc-save-livestock").hidden = true;
        if ($("calc-register-auction")) $("calc-register-auction").hidden = !id || state.role !== "farmer";
    }

    const selectedAnimal = findLivestock(id);
    renderWeightAnimal(selectedAnimal);
    syncRumpHeightField(selectedAnimal);

    if (!id) {
        renderWeightHistory([]);
        return;
    }

    await loadWeightHistory();
    // Re-apply after the asynchronous history request so a preselected
    // livestock record always keeps its species-specific measurement fields.
    syncRumpHeightField(findLivestock(weight.selectedId));
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

        const records = paginator.data ?? [];
        renderWeightHistory(records);
        const latest = records[0];
        if (!latest) clearWeightMeasurementInputs();
        if (latest) {
            // Reuse the latest saved measurement inputs when recalculating an existing animal.
            $("calc-girth").value = latest.heart_girth_cm ?? "";
            $("calc-length").value = latest.body_length_cm ?? "";
            $("calc-height").value = latest.rump_height_cm ?? "";
            $("calc-girth-unit").value = "cm";
            $("calc-length-unit").value = "cm";
            $("calc-height-unit").value = "cm";
            updateAllMeasurementGuides();
        }
    } catch (error) {
        if (requestId === weight.historyRequest) {
            showError(error);
        }
    }
}

function measurement(id, unitId, label, maximumCm) {
    const raw = $(id).value.trim();
    if (raw === "") return null;

    const number = Number(raw);
    const unit = measurementUnits[$(unitId)?.value] ?? measurementUnits.cm;
    const centimeters = number * unit.centimeters;

    if (!Number.isFinite(number) || number <= 0 || !Number.isFinite(centimeters) || centimeters > maximumCm) {
        throw new Error(`${label} must convert to a value greater than zero and at most ${maximumCm} cm.`);
    }
    return Number(centimeters.toFixed(2));
}

const calculatorConditionQuestions = [
    { id: "currently_sick", category: "Kahimsog sa Hayop (Animal Health)", question: "Maysakit ba karon ang hayop?", translation: "Is the animal currently sick?", options: [["No / Wala", 2], ["Not sure / Dili sigurado", 1], ["Yes / Oo", 0]] },
    { id: "recently_sick", question: "Nakasakit ba ang hayop bag-o lang?", translation: "Has the animal been sick recently?", options: [["No / Wala", 2], ["Yes, but recovered / Oo, pero naayo na", 1], ["Yes, still recovering / Oo, nagpaayo pa", 0]] },
    { id: "eating_normally", question: "Normal ba ang pagkaon sa hayop?", translation: "Is the animal eating normally?", options: [["Yes / Oo", 2], ["Less than usual / Gamay ra kaysa naandan", 1], ["Not eating / Dili mokaon", 0]] },
    { id: "visible_injuries", question: "Aduna bay samad o pilas ang hayop?", translation: "Does the animal have visible wounds or injuries?", options: [["None / Wala", 2], ["Minor wounds / Gamay nga samad", 1], ["Serious wounds / Grabe nga samad", 0]] },
    { id: "prominent_bones", category: "Kahimtang sa Lawas (Body Condition)", question: "Klaro kaayo ba ang buto-buto sa hayop?", translation: "Are the animal's bones visibly prominent?", options: [["Very prominent / Klaro kaayo ang buto-buto", 0], ["Somewhat prominent / Medyo klaro", 1], ["Not prominent / Dili kaayo klaro", 2]] },
    { id: "overall_body", question: "Unsa ang kinatibuk-ang hitsura sa lawas sa hayop?", translation: "How does the animal's overall body look?", options: [["Very thin / Niwang kaayo", 0], ["Moderately filled / Sakto-sakto ang lawas", 1], ["Well-filled / Puno ug maayong pagkabuo", 2]] },
    { id: "tail_base", question: "Lubog ba ang dapit sa punoan sa ikog?", translation: "Does the area around the base of the tail look sunken?", options: [["Deep hollow / Lawom ug lubog", 0], ["Slight hollow / Medyo lubog", 1], ["Filled / Puno ang hitsura", 2]] },
];

function mapAssessmentScore(total) {
    if (total <= 4) return 1;
    if (total <= 8) return 2;
    if (total <= 11) return 3;
    if (total <= 13) return 4;
    return 5;
}

function renderCalculatorConditionChecklist() {
    const container = $("calc-condition-questions");
    if (!container || container.dataset.rendered === "1") return;
    container.innerHTML = calculatorConditionQuestions.map((question, index) => `
        ${question.category ? `<h4 class="lr-assessment-category">${question.category}</h4>` : ""}
        <fieldset class="lr-assessment-question">
            <legend><strong>${index + 1}. ${question.question}</strong><small>${question.translation}</small></legend>
            <div class="lr-assessment-options">${question.options.map(([label, points]) => `
                <label class="lr-assessment-option"><input type="radio" name="calc_assessment_${question.id}" value="${points}"><span>${label}</span></label>
            `).join("")}</div>
        </fieldset>
    `).join("");
    container.dataset.rendered = "1";
}

function calculateCalculatorCondition() {
    const answers = {};
    let total = 0;
    for (const question of calculatorConditionQuestions) {
        const selected = document.querySelector(`input[name="calc_assessment_${question.id}"]:checked`);
        if (!selected) continue;
        answers[question.id] = Number(selected.value);
        total += Number(selected.value);
    }
    const complete = Object.keys(answers).length === calculatorConditionQuestions.length;
    state.conditionAssessment = {
        answers,
        complete,
        total: complete ? total : null,
        result: complete ? (total <= 4 ? "Poor condition" : total <= 8 ? "Needs attention" : total <= 11 ? "Fair condition" : "Good condition") : null,
        conditionScore: complete ? mapAssessmentScore(total) : null,
    };
    $("calc-condition-assessment-score").textContent = complete ? `${total} / 14` : `${Object.keys(answers).length}/7 answered`;
    $("calc-condition-score").textContent = complete ? `${state.conditionAssessment.conditionScore} / 5 — ${state.conditionAssessment.result}` : "Awaiting assessment";
    $("calc-condition-result").textContent = complete
        ? `Assessment: ${total}/14 · Condition score: ${state.conditionAssessment.conditionScore}/5 (${state.conditionAssessment.result})`
        : "Not yet assessed. Complete all seven questions.";
    return state.conditionAssessment;
}

function openConditionChecklist() {
    renderCalculatorConditionChecklist();
    $("calc-condition-modal").hidden = false;
    document.body.classList.add("calc-condition-open");
}

function closeConditionChecklist() {
    $("calc-condition-modal").hidden = true;
    document.body.classList.remove("calc-condition-open");
}

function weightPayload() {
    const selectedId = $("calc-livestock")?.value;
    const selectedAnimal = selectedId ? findLivestock(selectedId) : null;
    let payload;

    if (selectedAnimal) {
        // Existing-record calculations must use the saved profile, not the
        // hidden manual profile controls. Older records may legitimately have
        // no breed or age value, so do not apply new-registration requirements.
        payload = {
            species_id: selectedAnimal.species_id ?? selectedAnimal.species?.id ?? null,
            breed_id: selectedAnimal.breed_id ?? selectedAnimal.breed?.id ?? null,
            sex: normalizeLivestockSex(selectedAnimal.sex),
            age_months: selectedAnimal.age_months ?? null,
            age_group: selectedAnimal.age_group ?? null,
        };
        if (!payload.species_id) throw new Error("The selected livestock record has no species assigned.");
    } else {
        payload = buildManualProfilePayload();
    }

    const fields = [
        ["calc-girth", "calc-girth-unit", "heart_girth_cm", "Heart girth", 500],
        ["calc-length", "calc-length-unit", "body_length_cm", "Body length", 500],
        ["calc-height", "calc-height-unit", "rump_height_cm", "Rump height", 300],
    ];

    fields.forEach(([id, unitId, field, label, maximum]) => {
        const input = $(id);
        if (input?.disabled) {
            delete payload[field];
            return;
        }
        const value = measurement(id, unitId, label, maximum);
        if (value !== null) payload[field] = value;
    });

    if (!payload.heart_girth_cm && !payload.body_length_cm && !payload.rump_height_cm) {
        throw new Error("Enter the required livestock measurements.");
    }
    return payload;
}

async function calculateWeight(event) {
    event.preventDefault();
    const weight = state.weight;
    if (weight.loading) return;
    clearError();

    let payload;
    try {
        payload = weightPayload();
    } catch (error) {
        showError(error);
        return;
    }

    const button = $("calc-submit");
    weight.loading = true;
    setButtonLoading(button, true, "Calculating...", "Calculate Estimated Weight");

    try {
        const response = await api.post("/calculator/weight-estimation/preview", payload);
        const estimation = unwrap(response);
        state.manualPreviewPayload = payload;
        state.manualPreviewResult = estimation;
        renderWeightResult(estimation);

        const selectedId = $("calc-livestock").value;
        if (!selectedId) {
            state.weight.selectedId = null;
            renderWeightHistory([]);
        }
        // Do not reload history here: doing so would replace the user's newly
        // entered measurements with the previous saved estimation before they
        // have a chance to save this calculation.

        if ($("calc-farmer-actions")) {
            $("calc-farmer-actions").hidden = state.role !== "farmer";
            if ($("calc-save-livestock")) $("calc-save-livestock").hidden = false;
            if ($("calc-register-auction")) $("calc-register-auction").hidden = state.role !== "farmer";
        }
        if ($("calc-valuation-farmer-actions")) $("calc-valuation-farmer-actions").hidden = true;
        renderCalculatorConditionChecklist();
        if ($("calc-save-livestock")) {
            $("calc-save-livestock").textContent = selectedId
                ? "Save Estimation to Existing Livestock"
                : "Save to Livestock";
        }
    } catch (error) {
        showError(error);
    } finally {
        weight.loading = false;
        setButtonLoading(button, false, "Calculating...", "Calculate Estimated Weight");
    }
}

async function saveManualCalculation(action = "save") {
    if (state.role !== "farmer") {
        showError("Only Farmers can save livestock or continue to auction registration.");
        return;
    }

    const selectedId = state.activeTab === "valuation"
        ? ($("calc-valuation-livestock")?.value || "")
        : ($("calc-livestock")?.value || "");
    const selectedAnimal = selectedId ? findLivestock(selectedId) : null;
    let payload = state.manualPreviewPayload;

    if (!payload && selectedAnimal) {
        payload = {
            species_id: selectedAnimal.species_id ?? selectedAnimal.species?.id,
            breed_id: selectedAnimal.breed_id ?? selectedAnimal.breed?.id ?? null,
            sex: selectedAnimal.sex ?? "unknown",
            age_months: selectedAnimal.age_months ?? null,
        };
    }
    if (!payload) {
        const speciesId = $("calc-valuation-species")?.value || $("calc-species")?.value;
        if (!speciesId) {
            showError("Select the livestock species before saving the valuation.");
            return;
        }
        // Calculator-only fair-market valuation can start directly from the
        // valuation tab; do not require the hidden Weight Estimation profile.
        payload = {
            species_id: speciesId,
            breed_id: null,
            sex: "unknown",
            age_months: null,
        };
    }

    const manualWeightValue = Number($("calc-manual-valuation-weight")?.value || 0);
    const manualWeight = state.manualWeightManuallyEntered && manualWeightValue > 0
        ? Number(manualWeightValue.toFixed(2))
        : (state.manualValuationResult?.weight_type === "manual" && manualWeightValue > 0 ? Number(manualWeightValue.toFixed(2)) : null);
    const hasEstimate = Boolean(state.manualPreviewResult?.estimated_weight_kg && state.manualPreviewPayload);
    if (!hasEstimate && !manualWeight && !state.manualValuationResult) {
        showError("Calculate an estimated weight or enter a weight before saving.");
        return;
    }

    const measurements = hasEstimate ? {
        heart_girth_cm: payload.heart_girth_cm ?? null,
        body_length_cm: payload.body_length_cm ?? null,
        rump_height_cm: payload.rump_height_cm ?? null,
    } : null;

    if (selectedId) {
        if (action === "auction") {
            showError("Auction schedule registration is not available in this project yet. The existing livestock record has not been changed.");
            return;
        }

        const fromValuation = state.activeTab === "valuation";
        const button = fromValuation ? $("calc-valuation-save-livestock") : $("calc-save-livestock");
        if (button) { button.disabled = true; button.textContent = fromValuation ? "Saving fair price calculation..." : "Saving estimation..."; }
        try {
            let weightEstimationId = null;
            if (measurements && hasEstimate) {
                const estimateResponse = await api.post(estimationUrl(selectedId), measurements);
                const savedEstimation = unwrap(estimateResponse);
                weightEstimationId = savedEstimation?.id ?? null;
                await loadWeightHistory();
            } else if (fromValuation && manualWeight && !(Number(selectedAnimal?.actual_weight_kg) > 0)) {
                // A manually entered scale weight belongs in actual_weight_kg,
                // never in the estimated-weight history.
                await api.patch(`${state.endpoint}/${encodeURIComponent(selectedId)}`, {
                    actual_weight_kg: manualWeight,
                });
            }

            if (!fromValuation) {
                const message = $("calc-error");
                message.textContent = "Estimated weight was saved to the selected livestock record.";
                message.hidden = false;
                message.classList.add("calc-success-message");
                return;
            }

            const valuationPayload = weightEstimationId
                ? { weight_estimation_id: weightEstimationId }
                : {};
            const valuationResponse = await api.post(valuationUrl(selectedId), valuationPayload);
            renderValuationResult(unwrap(valuationResponse));
            state.manualValuationResult = unwrap(valuationResponse);
            await loadValuationHistory();
            if (state.fpc.selectedId === selectedId) await selectFpcLivestock(selectedId);
            const message = $("calc-error");
            message.textContent = "Fair Price Calculation was saved to the selected livestock record.";
            message.hidden = false;
            message.classList.add("calc-success-message");
        } catch (error) {
            showError(error);
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = fromValuation
                    ? "Save Fair Price Calculation to Existing Livestock"
                    : "Save Estimation to Existing Livestock";
            }
        }
        return;
    }

    const prefill = {
        action,
        species_id: payload.species_id,
        breed_id: payload.breed_id ?? null,
        sex: payload.sex ?? "unknown",
        age_months: payload.age_months ?? null,
        measurements,
        estimated_weight_kg: hasEstimate ? state.manualPreviewResult.estimated_weight_kg : null,
        formula_code: hasEstimate ? state.manualPreviewResult.formula_code : null,
        formula_version: hasEstimate ? state.manualPreviewResult.formula_version : null,
        frame_category: hasEstimate ? state.manualPreviewResult.frame_category : null,
        estimate_confidence: hasEstimate ? state.manualPreviewResult.estimate_confidence : null,
        condition_answers: state.conditionAssessment.answers,
        condition_score: state.conditionAssessment.conditionScore,
        condition_assessment_total: state.conditionAssessment.total,
        sale_purpose: $("calc-manual-sale-purpose")?.value || null,
        reproductive_status: $("calc-manual-reproductive-status")?.value || "unknown",
        parity: $("calc-manual-parity")?.value === "" ? null : Number($("calc-manual-parity")?.value),
        // Only an explicitly entered manual scale weight is copied to Actual Weight.
        // An auto-filled calculator estimate stays in the estimated-weight field.
        manual_weight_kg: manualWeight,
        estimated_value: state.manualValuationResult?.estimated_value ?? null,
        final_price_per_kg: state.manualValuationResult?.final_price_per_kg ?? null,
        weight_used_kg: state.manualValuationResult?.weight_used_kg ?? state.manualValuationResult?.estimated_weight_kg ?? null,
        valuation_requested: Boolean(state.manualValuationResult),
    };

    try {
        sessionStorage.setItem("agritimbang_calculator_prefill", JSON.stringify(prefill));
    } catch {
        showError("Your browser could not keep the calculation. Please allow session storage and try again.");
        return;
    }
    window.location.assign("/livestock/register?from_calculator=1");
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
    const nextId = id || null;
    const keepCurrentWeightPreview = Boolean(
        nextId &&
        String(nextId) === String(state.weight.selectedId || "") &&
        state.manualPreviewPayload &&
        state.manualPreviewResult
    );
    if (nextId && String(nextId) !== String(valuation.selectedId || "") && !keepCurrentWeightPreview) {
        state.manualPreviewPayload = null;
        state.manualPreviewResult = null;
        state.manualValuationResult = null;
        if ($("calc-valuation-farmer-actions")) $("calc-valuation-farmer-actions").hidden = true;
        state.conditionAssessment = { answers: {}, complete: false, total: null, conditionScore: null, result: null };
        $("calc-condition-questions")?.querySelectorAll('input[type="radio"]').forEach((radio) => { radio.checked = false; });
        $("calc-condition-result").textContent = "Not yet assessed. Complete all seven questions.";
    }

    valuation.selectedId = nextId;
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

    const animal = id ? findLivestock(id) : null;
    state.manualWeightManuallyEntered = false;
    if ($("calc-manual-valuation-weight")) $("calc-manual-valuation-weight").value = "";
    const hasStoredWeight = Boolean(animal && (Number(animal.actual_weight_kg) > 0 || Number(animal.weight_estimations_count) > 0));
    if ($("calc-valuation-save-livestock")) {
        $("calc-valuation-save-livestock").textContent = id
            ? "Save Fair Price Calculation to Existing Livestock"
            : "Save Livestock";
        $("calc-valuation-save-livestock").disabled = false;
    }
    if ($("calc-valuation-register-auction")) {
        $("calc-valuation-register-auction").textContent = id
            ? "Register Existing Livestock for Auction"
            : "Save Livestock & Join Auction";
    }
    if ($("calc-valuation-farmer-actions")) $("calc-valuation-farmer-actions").hidden = true;
    const manualFields = $("calc-manual-valuation-fields");
    if (manualFields) manualFields.hidden = Boolean(id && hasStoredWeight);
    const savedNote = $("calc-saved-valuation-note");
    if (savedNote) savedNote.hidden = !id || !hasStoredWeight;
    const estimationField = document.querySelector(".calc-valuation-estimation-field");
    if (estimationField) estimationField.hidden = !id || !hasStoredWeight;
    if (manualFields && id && !hasStoredWeight && animal) {
        $("calc-manual-valuation-weight").value = "";
        $("calc-manual-sale-purpose").value = animal.sale_purpose || "";
        $("calc-manual-reproductive-status").value = animal.reproductive_status || "unknown";
        $("calc-manual-parity").value = animal.parity ?? "";
        if (Number(animal.condition_score) > 0 && !state.conditionAssessment.complete) {
            $("calc-condition-result").textContent = `Saved condition score: ${animal.condition_score}/5. Complete the checklist to recalculate it.`;
        }
    }
    if (manualFields && !id && state.manualPreviewResult?.estimated_weight_kg) {
        $("calc-manual-valuation-weight").value = Number(state.manualPreviewResult.estimated_weight_kg).toFixed(2);
    }
    if (manualFields && !id && state.manualPreviewPayload?.species_id) {
        $("calc-valuation-species").value = state.manualPreviewPayload.species_id;
    }
    if (manualFields && id && animal) {
        $("calc-valuation-species").value = animal.species_id || animal.species?.id || "";
    }

    if (!id) {
        renderValuationHistory([]);
        $("calc-valuation-result").hidden = true;
        $("calc-valuation-empty").hidden = false;
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
    const selectedAnimal = livestockId ? findLivestock(livestockId) : null;
    const selectedHasStoredWeight = Boolean(selectedAnimal && (
        Number(selectedAnimal.actual_weight_kg) > 0 || Number(selectedAnimal.weight_estimations_count) > 0
    ));

    if (!livestockId || !selectedHasStoredWeight) {
        let profile = selectedAnimal ? {
            species_id: selectedAnimal.species_id || selectedAnimal.species?.id,
            breed_id: selectedAnimal.breed_id || selectedAnimal.breed?.id || null,
            sex: selectedAnimal.sex,
            age_months: selectedAnimal.age_months ?? null,
        } : state.manualPreviewPayload;
        if (!profile) {
            const speciesId = $("calc-valuation-species")?.value;
            if (!speciesId) {
                showError("Select the livestock species for fair market valuation.");
                return;
            }
            profile = { species_id: speciesId, breed_id: null, sex: "unknown", age_months: null };
        }
        const animal = selectedAnimal;
        const manualWeight = Number($("calc-manual-valuation-weight")?.value || 0);
        const previewMatchesAnimal = !selectedAnimal || String(state.manualPreviewPayload?.species_id || "") === String(selectedAnimal.species_id || selectedAnimal.species?.id || "");
        const calculatedWeight = previewMatchesAnimal && !state.manualWeightManuallyEntered
            ? Number(state.manualPreviewResult?.estimated_weight_kg || 0)
            : 0;
        const rawWeightToUse = calculatedWeight > 0 ? calculatedWeight : manualWeight;
        const weightToUse = Number(rawWeightToUse.toFixed(2));
        if (!(weightToUse > 0)) {
            showError("No weight is available. Enter a manual weight in kilograms or calculate the weight first.");
            return;
        }
        const assessment = calculateCalculatorCondition();
        if (!assessment.complete) {
            showError("Complete all seven condition checklist questions before calculating valuation.");
            openConditionChecklist();
            return;
        }
        const payload = {
            ...profile,
            species_id: profile.species_id || $("calc-valuation-species")?.value || animal?.species_id || animal?.species?.id,
            breed_id: profile.breed_id || animal?.breed_id || animal?.breed?.id || null,
            sex: profile.sex || animal?.sex,
            age_months: profile.age_months ?? animal?.age_months ?? null,
            estimated_weight_kg: weightToUse,
            weight_source: calculatedWeight > 0 ? "estimated" : "manual",
            frame_category: state.manualPreviewResult?.frame_category ?? null,
            sale_purpose: $("calc-manual-sale-purpose").value || animal?.sale_purpose,
            condition_score: assessment.conditionScore,
            reproductive_status: $("calc-manual-reproductive-status").value || animal?.reproductive_status || "unknown",
            parity: $("calc-manual-parity").value === "" ? (animal?.parity ?? null) : Number($("calc-manual-parity").value),
        };
        if (!payload.sale_purpose) {
            showError("Select the animal's sale purpose for valuation.");
            return;
        }
        const button = $("calc-valuation-submit");
        valuation.loading = true;
        setButtonLoading(button, true, "Calculating preview...", "Calculate Valuation Preview");
        try {
            const response = await api.post("/calculator/valuation/preview", payload);
            const result = unwrap(response);
            renderValuationResult(result);
            state.manualValuationResult = result;
            if ($("calc-valuation-farmer-actions")) $("calc-valuation-farmer-actions").hidden = state.role !== "farmer";
            if ($("calc-valuation-register-auction")) {
                $("calc-valuation-register-auction").textContent = livestockId
                    ? "Register Existing Livestock for Auction"
                    : "Save Livestock & Join Auction";
            }
            const snapshot = result.calculation_snapshot ?? {};
            if (snapshot && typeof snapshot === "object") {
                snapshot.preview_only = true;
                snapshot.notice = "Preview only — no livestock record or valuation history was saved.";
                $("calc-snapshot-json").textContent = JSON.stringify(snapshot, null, 2);
            }
            $("calc-valuation-history").replaceChildren();
            const row = $("calc-valuation-history").insertRow();
            const cell = row.insertCell();
            cell.colSpan = 5;
            cell.textContent = "Calculator-only preview is not saved in valuation history.";
            return;
        } catch (error) {
            showError(error);
        } finally {
            valuation.loading = false;
            setButtonLoading(button, false, "Calculating preview...", "Calculate Valuation Preview");
        }
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
        if ($("calc-valuation-register-auction")) {
            $("calc-valuation-register-auction").textContent = "Register Existing Livestock for Auction";
        }
        if ($("calc-valuation-farmer-actions")) {
            $("calc-valuation-farmer-actions").hidden = state.role !== "farmer";
            if (livestockId) {
                $("calc-valuation-save-livestock").textContent = "Fair Price Calculation Saved to This Livestock";
                $("calc-valuation-save-livestock").disabled = true;
            }
        }

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

    $("calc-livestock").addEventListener("change", async (event) => {
        clearError();
        invalidateManualPreview();
        const animal = findLivestock(event.target.value);
        if (animal) {
            try {
                await selectWeightLivestock(event.target.value);
            } catch (error) {
                showError(error);
            }
        } else {
            await selectWeightLivestock("");
            $("calc-animal-info").hidden = true;
            renderWeightHistory([]);
        }
    });

    $("calc-form").addEventListener("input", (event) => {
        if (event.target.id !== "calc-submit") invalidateManualPreview();
    });
    $("calc-form").addEventListener("change", (event) => {
        if (event.target.id !== "calc-submit") invalidateManualPreview();
    });

    const detachExistingRecord = () => {
        if (!$("calc-livestock").value) return;
        $("calc-livestock").value = "";
        state.weight.selectedId = null;
        renderWeightHistory([]);
    };

    $("calc-species").addEventListener("change", async (event) => {
        clearError();
        detachExistingRecord();
        try {
            await loadManualBreeds(event.target.value);
            updateBreedRequirement();
            updateRumpHeightForSpecies();
        } catch (error) {
            showError(error);
        }
    });

    ["calc-breed", "calc-sex"].forEach((id) => {
        $(id)?.addEventListener("change", detachExistingRecord);
    });
    $("calc-age-months")?.addEventListener("change", () => {
        detachExistingRecord();
        updateRumpHeightForSpecies();
    });

    ["calc-girth-unit", "calc-length-unit", "calc-height-unit"].forEach((id) => {
        $(id)?.addEventListener("change", updateAllMeasurementGuides);
    });

    $("calc-save-livestock")?.addEventListener("click", () => saveManualCalculation("save"));
    $("calc-register-auction")?.addEventListener("click", () => saveManualCalculation("auction"));
    $("calc-go-valuation")?.addEventListener("click", () => {
        if (!state.manualPreviewPayload || !state.manualPreviewResult) {
            showError("Calculate the estimated weight first.");
            return;
        }
        switchToValuation().catch(showError);
    });
    $("calc-condition-open")?.addEventListener("click", openConditionChecklist);
    $("calc-condition-close")?.addEventListener("click", closeConditionChecklist);
    $("calc-condition-modal")?.addEventListener("click", (event) => {
        if (event.target?.hasAttribute("data-condition-close")) closeConditionChecklist();
    });
    $("calc-condition-questions")?.addEventListener("change", () => {
        calculateCalculatorCondition();
        state.manualValuationResult = null;
        if ($("calc-valuation-farmer-actions")) $("calc-valuation-farmer-actions").hidden = true;
    });
    $("calc-condition-apply")?.addEventListener("click", () => {
        const assessment = calculateCalculatorCondition();
        if (!assessment.complete) {
            showError("Please answer all seven condition assessment questions.");
            return;
        }
        clearError();
        closeConditionChecklist();
    });
    $("calc-valuation-save-livestock")?.addEventListener("click", () => saveManualCalculation("save"));
    $("calc-valuation-register-auction")?.addEventListener("click", () => saveManualCalculation("auction"));

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
    const manualWeightInput = $("calc-manual-valuation-weight");
    if (manualWeightInput) {
        manualWeightInput.addEventListener("input", () => {
            state.manualWeightManuallyEntered = true;
            let value = String(manualWeightInput.value ?? "").replace(/[^0-9.]/g, "");
            const dot = value.indexOf(".");
            if (dot !== -1) {
                value = value.slice(0, dot + 1) + value.slice(dot + 1).replace(/\./g, "");
                const [whole, decimals = ""] = value.split(".");
                value = `${whole}.${decimals.slice(0, 2)}`;
            }
            if (manualWeightInput.value !== value) manualWeightInput.value = value;
        });
    }

    $("calc-tab-weight").addEventListener("click", () => {
        clearError();
        setTab("weight");
    });

    $("calc-tab-valuation").addEventListener("click", () => {
        switchToValuation().catch(showError);
    });

    $("calc-valuation-livestock").addEventListener("change", (event) => {
        clearError();
        const selected = findLivestock(event.target.value);
        const hasWeight = Boolean(selected && (Number(selected.actual_weight_kg) > 0 || Number(selected.weight_estimations_count) > 0));
        $("calc-valuation-submit").textContent = !event.target.value || !hasWeight ? "Calculate Valuation Preview" : "Calculate & Save Valuation";
        state.manualValuationResult = null;
        if ($("calc-valuation-farmer-actions")) $("calc-valuation-farmer-actions").hidden = true;
        selectValuationLivestock(event.target.value).catch(showError);
    });

    $("calc-valuation-species")?.addEventListener("change", () => {
        state.manualValuationResult = null;
        if ($("calc-valuation-farmer-actions")) $("calc-valuation-farmer-actions").hidden = true;
    });

    $("calc-valuation-form").addEventListener("submit", calculateValuation);
    $("calc-valuation-form").addEventListener("input", (event) => {
        if (event.target.id === "calc-valuation-submit") return;
        state.manualValuationResult = null;
        if ($("calc-valuation-farmer-actions")) $("calc-valuation-farmer-actions").hidden = true;
    });
    $("calc-valuation-form").addEventListener("change", (event) => {
        if (event.target.id === "calc-valuation-submit") return;
        state.manualValuationResult = null;
        if ($("calc-valuation-farmer-actions")) $("calc-valuation-farmer-actions").hidden = true;
    });

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
        await loadManualSpecies();
        updateBreedRequirement();
        updateRumpHeightForSpecies();
        await loadLivestock();
        updateAllMeasurementGuides();
        updateRumpHeightForSpecies();
        const requestedTab = new URLSearchParams(window.location.search).get("tab");
        if (requestedTab === "valuation") setTab("valuation");
        if ($("calc-farmer-actions")) {
            $("calc-farmer-actions").hidden = state.role !== "farmer";
        }
        if (!state.valuation.selectedId) {
            await selectValuationLivestock("");
            $("calc-valuation-submit").textContent = "Calculate Valuation Preview";
        }
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

// ==========================================================
// CALCULATOR HELP & USER GUIDE
// ==========================================================

function initializeCalculatorHelp() {
    const modal = document.getElementById("calc-help-modal");
    const openButton = document.getElementById("calc-help-open");
    const closeButton = document.getElementById("calc-help-close");
    const dialog = modal?.querySelector(".calc-help-dialog");

    if (!modal || !openButton || !dialog) return;

    const tabs = [...modal.querySelectorAll("[data-help-tab]")];

    const panels = [...modal.querySelectorAll("[data-help-panel]")];

    let previousFocus = null;

    function selectTopic(topic) {
        tabs.forEach((button) => {
            const selected = button.dataset.helpTab === topic;

            button.classList.toggle("active", selected);
            button.setAttribute("aria-selected", String(selected));
        });

        panels.forEach((panel) => {
            panel.hidden = panel.dataset.helpPanel !== topic;
        });

        const content = modal.querySelector(".calc-help-content");
        if (content) content.scrollTop = 0;
    }

    function openHelp(topic = "overview") {
        previousFocus = document.activeElement;

        selectTopic(topic);

        modal.hidden = false;
        document.body.classList.add("calc-help-open");

        closeButton?.focus();
    }

    function closeHelp() {
        modal.hidden = true;
        document.body.classList.remove("calc-help-open");

        if (previousFocus instanceof HTMLElement) {
            previousFocus.focus();
        }
    }

    openButton.addEventListener("click", () => {
        openHelp();
    });

    // Wire every close control, including the top-right X button.
    closeButton?.addEventListener("click", closeHelp);

    modal.querySelectorAll("[data-help-close]").forEach((button) => {
        button.addEventListener("click", closeHelp);
    });

    tabs.forEach((button) => {
        button.addEventListener("click", () => {
            selectTopic(button.dataset.helpTab);
        });
    });

    document.addEventListener("keydown", (event) => {
        if (modal.hidden) return;

        if (event.key === "Escape") {
            closeHelp();
            return;
        }

        if (event.key === "Tab") {
            const focusable = [
                ...modal.querySelectorAll(
                    "button:not([disabled]):not([hidden]), " +
                        "a[href], input:not([disabled]), " +
                        "select:not([disabled]), textarea:not([disabled])",
                ),
            ].filter((element) => element.getClientRects().length > 0);

            if (!focusable.length) {
                event.preventDefault();
                dialog.focus();
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    // Optional: Open a specific help topic from other controls.
    window.openCalculatorHelp = openHelp;
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeCalculatorHelp, {
        once: true,
    });
} else {
    initializeCalculatorHelp();
}


// ==========================================================
// MEASUREMENT INSTRUCTIONS MODAL
// ==========================================================
function initializeMeasurementInstructions() {
    const modal = document.getElementById("calc-measure-modal");
    const openButton = document.getElementById("calc-measure-open");
    const closeButton = document.getElementById("calc-measure-close");
    const dialog = modal?.querySelector(".calc-measure-dialog");
    if (!modal || !openButton || !dialog) return;

    let previousFocus = null;
    const backdrop = modal.querySelector(".calc-measure-backdrop");

    function openModal() {
        previousFocus = document.activeElement;
        modal.hidden = false;
        document.body.classList.add("calc-help-open");
        closeButton?.focus();
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove("calc-help-open");
        if (previousFocus instanceof HTMLElement) previousFocus.focus();
    }

    openButton.addEventListener("click", openModal);
    // The top-right X is the primary close control. The backdrop also dismisses the modal.
    closeButton?.addEventListener("click", closeModal);
    backdrop?.addEventListener("click", closeModal);
    document.addEventListener("keydown", (event) => {
        if (modal.hidden) return;
        if (event.key === "Escape") {
            closeModal();
            return;
        }
        if (event.key === "Tab") {
            const focusable = [...modal.querySelectorAll("button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled])")]
                .filter((element) => element.getClientRects().length > 0);
            if (!focusable.length) {
                event.preventDefault();
                dialog.focus();
                return;
            }
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeMeasurementInstructions, { once: true });
} else {
    initializeMeasurementInstructions();
}
