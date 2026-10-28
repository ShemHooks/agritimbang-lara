import api from "../api";

const $ = (id) => document.getElementById(id);

const page = $("price-form-page");

const state = {
    priceId: page?.dataset.priceId || null,
    user: null,
    municipalityId: null,
    loading: false,
    saving: false,
    breedRequestId: 0,
};

const isEditing = () => Boolean(state.priceId);

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
    $("price-form-error").hidden = true;
    $("price-form-success").hidden = true;

    const target =
        type === "success" ? $("price-form-success") : $("price-form-error");

    target.textContent = message;
    target.hidden = false;
}

function clearNotice() {
    $("price-form-error").hidden = true;
    $("price-form-success").hidden = true;
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

function setFormDisabled(disabled) {
    $("price-reference-form")
        .querySelectorAll("input, select, textarea, button")
        .forEach((element) => {
            element.disabled = disabled;
        });
}

function clearFieldErrors() {
    document.querySelectorAll("[data-error-for]").forEach((element) => {
        element.textContent = "";
    });

    document.querySelectorAll(".price-form-invalid").forEach((element) => {
        element.classList.remove("price-form-invalid");
    });
}

function showFieldErrors(errors) {
    Object.entries(errors ?? {}).forEach(([field, messages]) => {
        const target = document.querySelector(`[data-error-for="${field}"]`);

        const input = document.querySelector(`[name="${field}"]`);

        if (target) {
            target.textContent = Array.isArray(messages)
                ? messages.join(" ")
                : String(messages);
        }

        input?.classList.add("price-form-invalid");
    });
}

function populateSelect(id, records, placeholder) {
    const select = $(id);

    select.replaceChildren(new Option(placeholder, ""));

    records.forEach((record) => {
        select.add(new Option(record.name, record.id));
    });
}

function normalizeRecords(payload) {
    if (Array.isArray(payload)) return payload;

    if (Array.isArray(payload?.data)) {
        return payload.data;
    }

    return [];
}

function normalizeDate(value) {
    if (!value) return "";

    const match = String(value).match(/^\d{4}-\d{2}-\d{2}/);

    return match ? match[0] : "";
}

async function loadSpecies() {
    const response = await api.get("/reference/species");

    populateSelect(
        "price-species",
        normalizeRecords(unwrap(response)),
        "Select species",
    );
}

async function loadBreeds(speciesId, selectedBreedId = "") {
    const requestId = ++state.breedRequestId;

    populateSelect("price-breed", [], "All breeds");

    $("price-breed").disabled = !speciesId;

    if (!speciesId) return;

    const response = await api.get(
        `/reference/species/${encodeURIComponent(speciesId)}/breeds`,
    );

    if (requestId !== state.breedRequestId) return;

    populateSelect(
        "price-breed",
        normalizeRecords(unwrap(response)),
        "All breeds",
    );

    $("price-breed").value = selectedBreedId || "";
    $("price-breed").disabled = false;
}

async function loadBarangays() {
    if (!state.municipalityId) {
        throw new Error("Your LGU account has no assigned municipality.");
    }

    const response = await api.get(
        `/reference/municipalities/${encodeURIComponent(
            state.municipalityId,
        )}/barangays`,
    );

    populateSelect(
        "price-barangay",
        normalizeRecords(unwrap(response)),
        "Municipality-wide",
    );
}

async function loadExistingPrice() {
    const response = await api.get(
        `/lgu/price-references/${encodeURIComponent(state.priceId)}`,
    );

    const record = unwrap(response);

    if (record.status !== "draft") {
        throw new Error("Only draft price references can be edited.");
    }

    if (
        state.municipalityId &&
        String(record.municipality_id) !== String(state.municipalityId)
    ) {
        throw new Error(
            "This price reference does not belong to your municipality.",
        );
    }

    $("price-species").value = record.species_id ?? "";

    await loadBreeds(record.species_id, record.breed_id ?? "");

    $("price-barangay").value = record.barangay_id ?? "";
    $("price-purpose").value = record.sale_purpose ?? "";
    $("price-amount").value = record.price_per_kg ?? "";
    $("price-effective-from").value = normalizeDate(record.effective_from);

    $("price-effective-to").value = normalizeDate(record.effective_to);

    $("price-source").value = record.source ?? "";
    $("price-remarks").value = record.remarks ?? "";
}

function collectPayload() {
    const form = $("price-reference-form");
    const data = new FormData(form);

    return {
        species_id: data.get("species_id") || null,
        breed_id: data.get("breed_id") || null,
        barangay_id: data.get("barangay_id") || null,
        sale_purpose: data.get("sale_purpose") || null,
        price_per_kg: data.get("price_per_kg") || null,
        effective_from: data.get("effective_from") || null,
        effective_to: data.get("effective_to") || null,
        source: String(data.get("source") || "").trim(),
        remarks: String(data.get("remarks") || "").trim() || null,
    };
}

function validatePayload(payload) {
    const errors = {};

    if (!payload.species_id) {
        errors.species_id = ["Please select a species."];
    }

    if (!payload.sale_purpose) {
        errors.sale_purpose = ["Please select a sale purpose."];
    }

    if (
        !payload.price_per_kg ||
        !Number.isFinite(Number(payload.price_per_kg)) ||
        Number(payload.price_per_kg) <= 0
    ) {
        errors.price_per_kg = ["Enter a valid price greater than zero."];
    }

    if (!payload.effective_from) {
        errors.effective_from = ["Please select the effective start date."];
    }

    if (
        payload.effective_from &&
        payload.effective_to &&
        payload.effective_to < payload.effective_from
    ) {
        errors.effective_to = [
            "The end date cannot be earlier than the start date.",
        ];
    }

    if (!payload.source) {
        errors.source = ["Please provide a price source."];
    }

    return errors;
}

async function savePrice(event) {
    event.preventDefault();

    if (state.saving || state.loading) return;

    clearNotice();
    clearFieldErrors();

    const payload = collectPayload();
    const errors = validatePayload(payload);

    if (Object.keys(errors).length) {
        showFieldErrors(errors);
        notify("Please correct the highlighted fields.");
        return;
    }

    state.saving = true;

    const button = $("price-form-submit");
    const originalText = button.textContent;

    button.disabled = true;
    button.textContent = "Saving...";

    try {
        if (isEditing()) {
            await api.patch(
                `/lgu/price-references/${encodeURIComponent(state.priceId)}`,
                payload,
            );
        } else {
            await api.post("/lgu/price-references", payload);
        }

        window.location.assign("/prices");
    } catch (error) {
        if (error.response?.status === 422) {
            showFieldErrors(error.response.data?.errors);
        }

        notify(errorMessage(error));
    } finally {
        state.saving = false;
        button.disabled = false;
        button.textContent = originalText;
    }
}

function bindEvents() {
    $("price-species").addEventListener("change", async () => {
        try {
            await loadBreeds($("price-species").value);
        } catch (error) {
            notify(`Unable to load breeds: ${errorMessage(error)}`);
        }
    });

    $("price-effective-from").addEventListener("change", () => {
        const start = $("price-effective-from").value;
        const end = $("price-effective-to");

        end.min = start;

        if (end.value && end.value < start) {
            end.value = "";
        }
    });

    $("price-reference-form").addEventListener("submit", savePrice);
}

async function init() {
    if (!page) return;

    state.loading = true;
    setFormDisabled(true);

    try {
        const response = await api.get("/auth/me");
        const payload = unwrap(response);
        const user = payload?.user ?? payload;

        const roles = resolveRoles(payload);

        if (!roles.includes("lgu_encoder")) {
            throw new Error(
                "Only LGU Encoders can create or edit price references.",
            );
        }

        state.user = user;

        // Support common API profile naming conventions.
        state.municipalityId =
            user.lgu_officer_profile?.municipality_id ??
            user.lguOfficerProfile?.municipality_id ??
            null;

        $("price-form-title").textContent = isEditing()
            ? "Edit Price Reference"
            : "Create Price Reference";

        $("price-form-description").textContent = isEditing()
            ? "Update this draft livestock price reference."
            : "Create a new livestock price reference for MAO review.";

        $("price-form-submit").textContent = isEditing()
            ? "Save Changes"
            : "Save Draft";

        await Promise.all([loadSpecies(), loadBarangays()]);

        if (isEditing()) {
            await loadExistingPrice();
        }

        bindEvents();
        setFormDisabled(false);

        // Breed remains disabled until a species is selected.
        $("price-breed").disabled = !$("price-species").value;
    } catch (error) {
        notify(errorMessage(error));
    } finally {
        state.loading = false;
    }
}

init();
