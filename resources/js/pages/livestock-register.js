import api from "../api";

const $ = (id) => document.getElementById(id);

const state = {
    submitting: false,
    breedRequestId: 0,
};

function getError(error) {
    const data = error?.response?.data;
    const first = Object.values(data?.errors ?? {})[0];

    if (Array.isArray(first)) return first[0] ?? "Validation failed.";
    if (typeof first === "string") return first;

    return data?.message ?? error?.message ?? "Something went wrong.";
}

function unwrap(response) {
    return response?.data?.data ?? response?.data;
}

function asArray(payload) {
    if (Array.isArray(payload)) return payload;
    if (Array.isArray(payload?.data)) return payload.data;
    if (Array.isArray(payload?.data?.data)) return payload.data.data;
    return [];
}

function showAlert(id, message) {
    const element = $(id);
    element.textContent = message;
    element.hidden = false;
}

function clearAlerts() {
    for (const id of ["lr-page-error", "lr-page-success"]) {
        $(id).textContent = "";
        $(id).hidden = true;
    }
}

function clearFieldErrors() {
    document.querySelectorAll("[data-error-for]").forEach((element) => {
        element.textContent = "";
    });
}

function showValidationErrors(errors = {}) {
    for (const [field, messages] of Object.entries(errors)) {
        const element = document.querySelector(`[data-error-for="${field}"]`);

        if (!element) continue;

        element.textContent = Array.isArray(messages)
            ? messages.join(" ")
            : String(messages);
    }
}

function fillSelect(select, items, placeholder) {
    select.replaceChildren(new Option(placeholder, ""));

    for (const item of items) {
        if (!item?.id || item.is_active === false || item.is_active === 0) {
            continue;
        }

        // Reference models are expected to expose id and name.
        select.add(new Option(item.name ?? item.id, item.id));
    }
}

async function loadSpecies() {
    const select = $("lr-species");

    select.disabled = true;
    select.replaceChildren(new Option("Loading species...", ""));

    try {
        const response = await api.get("/reference/species");
        const species = asArray(unwrap(response));

        fillSelect(select, species, "Select species");
        select.disabled = false;

        if (!species.length) {
            showAlert(
                "lr-page-error",
                "No species are available. Please contact the administrator.",
            );
        }
    } catch (error) {
        select.replaceChildren(new Option("Unable to load species", ""));
        showAlert("lr-page-error", getError(error));
    }
}

async function loadBreeds(speciesId) {
    const select = $("lr-breed");
    const requestId = ++state.breedRequestId;

    select.disabled = true;

    if (!speciesId) {
        select.replaceChildren(new Option("Select a species first", ""));
        return;
    }

    select.replaceChildren(new Option("Loading breeds...", ""));

    try {
        const response = await api.get(
            `/reference/species/${encodeURIComponent(speciesId)}/breeds`,
        );

        if (requestId !== state.breedRequestId) return;

        const breeds = asArray(unwrap(response));

        fillSelect(select, breeds, "No breed specified");
        select.disabled = false;
    } catch (error) {
        if (requestId !== state.breedRequestId) return;

        select.replaceChildren(new Option("Unable to load breeds", ""));
        showAlert("lr-page-error", getError(error));
    }
}

function syncReproductiveFields() {
    const sex = $("lr-sex").value;
    const status = $("lr-reproductive");
    const parity = $("lr-parity");
    const hint = $("lr-parity-hint");

    const allowedStatuses = {
        male: ["not_applicable"],
        female: ["unknown", "has_given_birth", "never_given_birth"],
        unknown: [
            "unknown",
            "has_given_birth",
            "never_given_birth",
            "not_applicable",
        ],
    };

    const allowed = allowedStatuses[sex] ?? ["unknown"];

    for (const option of status.options) {
        option.disabled = !allowed.includes(option.value);
        option.hidden = option.disabled;
    }

    if (sex === "male") {
        status.value = "not_applicable";
        status.disabled = true;
        parity.value = "";
        parity.disabled = true;
        parity.required = false;
        hint.textContent = "Not applicable to male livestock.";
        return;
    }

    status.disabled = false;

    if (!allowed.includes(status.value)) {
        status.value = "unknown";
    }

    if (sex !== "female") {
        parity.value = "";
        parity.disabled = true;
        parity.required = false;
        hint.textContent = "Parity is only collected for female livestock.";
        return;
    }

    if (status.value === "has_given_birth") {
        parity.disabled = false;
        parity.required = true;
        parity.min = "1";
        hint.textContent = "Enter at least 1 birth.";
    } else if (status.value === "never_given_birth") {
        parity.value = "0";
        parity.disabled = true;
        parity.required = false;
        hint.textContent = "Parity is automatically set to 0.";
    } else {
        parity.value = "";
        parity.disabled = true;
        parity.required = false;
        hint.textContent = "Parity is not required for this status.";
    }
}

function nullableString(value) {
    const trimmed = String(value ?? "").trim();
    return trimmed === "" ? null : trimmed;
}

function buildPayload(form) {
    const formData = new FormData(form);
    const sex = String(formData.get("sex") ?? "");

    const reproductiveStatus =
        sex === "male"
            ? "not_applicable"
            : String(formData.get("reproductive_status") ?? "unknown");

    let parity = null;

    if (sex === "female" && reproductiveStatus === "has_given_birth") {
        parity = Number(formData.get("parity"));
    } else if (sex === "female" && reproductiveStatus === "never_given_birth") {
        parity = 0;
    }

    return {
        species_id: formData.get("species_id"),
        breed_id: nullableString(formData.get("breed_id")),
        sex,
        age_months: nullableString(formData.get("age_months")),
        reproductive_status: reproductiveStatus,
        parity,
        sale_purpose: formData.get("sale_purpose"),
        condition_score: Number(formData.get("condition_score")),
        actual_weight_kg: nullableString(formData.get("actual_weight_kg")),
    };
}

async function submitRegistration(event) {
    event.preventDefault();

    if (state.submitting) return;

    clearAlerts();
    clearFieldErrors();

    const form = $("lr-form");
    const button = $("lr-submit");

    if (!form.reportValidity()) return;

    const payload = buildPayload(form);

    state.submitting = true;
    button.disabled = true;
    button.textContent = "Registering...";

    try {
        const response = await api.post("/farmer/livestock", payload);
        const livestock = unwrap(response);

        form.reset();
        syncReproductiveFields();

        $("lr-breed").replaceChildren(new Option("Select a species first", ""));
        $("lr-breed").disabled = true;
        state.breedRequestId++;

        showAlert(
            "lr-page-success",
            "Livestock registered successfully! " +
                "The record is now available in your livestock directory.",
        );

        window.scrollTo({ top: 0, behavior: "smooth" });

        // Optional: redirect once your Farmer Livestock
        // Directory web route is confirmed.
        // window.location.href = "/livestock";
    } catch (error) {
        const data = error?.response?.data;

        if (error?.response?.status === 422) {
            showValidationErrors(data?.errors);
        }

        showAlert("lr-page-error", getError(error));
        window.scrollTo({ top: 0, behavior: "smooth" });
    } finally {
        state.submitting = false;
        button.disabled = false;
        button.textContent = "Register Livestock";
    }
}

function init() {
    const form = $("lr-form");

    $("lr-species").addEventListener("change", (event) => {
        clearAlerts();
        loadBreeds(event.target.value);
    });

    $("lr-sex").addEventListener("change", syncReproductiveFields);
    $("lr-reproductive").addEventListener("change", syncReproductiveFields);

    form.addEventListener("submit", submitRegistration);

    form.addEventListener("reset", () => {
        setTimeout(() => {
            clearAlerts();
            clearFieldErrors();
            state.breedRequestId++;
            $("lr-breed").replaceChildren(
                new Option("Select a species first", ""),
            );
            $("lr-breed").disabled = true;
            syncReproductiveFields();
        }, 0);
    });

    syncReproductiveFields();
    loadSpecies();
}

document.addEventListener("DOMContentLoaded", init);
