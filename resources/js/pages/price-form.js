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
    modalRequestId: 0,
    recommendations: [],
    modalPreviousFocus: null,
};

const isEditing = () => Boolean(state.priceId);

const currency = new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: "PHP",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

// ---------------------------------------------------------------------
// API and utility helpers
// ---------------------------------------------------------------------

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

function normalizeRecords(payload) {
    if (Array.isArray(payload)) return payload;
    if (Array.isArray(payload?.data)) return payload.data;
    return [];
}

function normalizeDate(value) {
    if (!value) return "";

    return String(value).match(/^\d{4}-\d{2}-\d{2}/)?.[0] ?? "";
}

function localDateString() {
    const date = new Date();

    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, "0"),
        String(date.getDate()).padStart(2, "0"),
    ].join("-");
}

function resolveRoles(payload) {
    const user = payload?.user ?? payload;

    const roles = [
        ...(Array.isArray(user?.roles) ? user.roles : []),
        ...(user?.role ? [user.role] : []),
    ];

    return roles
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

// ---------------------------------------------------------------------
// Notifications and validation
// ---------------------------------------------------------------------

function clearNotice() {
    $("price-form-error").hidden = true;
    $("price-form-success").hidden = true;
}

function notify(message, type = "error") {
    clearNotice();

    const target =
        type === "success" ? $("price-form-success") : $("price-form-error");

    target.textContent = message;
    target.hidden = false;
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

function setFormDisabled(disabled) {
    $("price-reference-form")
        .querySelectorAll("input, select, textarea, button")
        .forEach((element) => {
            element.disabled = disabled;
        });

    const recommendationButton = $("open-price-recommendations");

    if (recommendationButton) {
        recommendationButton.disabled = disabled;
    }
}

// ---------------------------------------------------------------------
// Reference dropdowns
// ---------------------------------------------------------------------

function populateSelect(id, records, placeholder) {
    const select = $(id);

    select.replaceChildren(new Option(placeholder, ""));

    records.forEach((record) => {
        select.add(new Option(record.name, record.id));
    });
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
    $("price-breed").disabled = true;

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

// ---------------------------------------------------------------------
// Existing draft
// ---------------------------------------------------------------------

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

    $("price-effective-to").min = $("price-effective-from").value;

    $("price-source").value = record.source ?? "";
    $("price-remarks").value = record.remarks ?? "";
}

// ---------------------------------------------------------------------
// Form data
// ---------------------------------------------------------------------

function collectPayload() {
    const data = new FormData($("price-reference-form"));

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

// ---------------------------------------------------------------------
// Save draft
// ---------------------------------------------------------------------

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

// ---------------------------------------------------------------------
// Recommended prices modal
// ---------------------------------------------------------------------

function modal() {
    return $("price-recommendations-modal");
}

function isModalOpen() {
    return Boolean(modal() && !modal().hidden);
}

function setModalVisible(visible) {
    const element = modal();

    if (!element) return;

    element.hidden = !visible;
    element.setAttribute("aria-hidden", String(!visible));

    document.body.classList.toggle("price-modal-open", visible);

    if (visible) {
        $("close-price-recommendations").focus();
    } else {
        state.modalPreviousFocus?.focus?.();
    }
}

function closePriceRecommendations() {
    ++state.modalRequestId;
    setModalVisible(false);
}

function createTextElement(tag, className, text) {
    const element = document.createElement(tag);

    if (className) {
        element.className = className;
    }

    element.textContent = text;

    return element;
}

function useRecommendedPrice(record) {
    if (!record.available) return;

    const price = Number(record.average_price_per_kg);

    if (!Number.isFinite(price) || price <= 0) return;

    const species = $("price-species");

    if (
        !Array.from(species.options).some(
            (option) => option.value === record.species_id,
        )
    ) {
        notify("The selected species is not available in the form.");
        closePriceRecommendations();
        return;
    }

    const speciesChanged = species.value !== record.species_id;

    species.value = record.species_id;

    $("price-amount").value = price.toFixed(2);

    if (speciesChanged) {
        species.dispatchEvent(new Event("change", { bubbles: true }));
    }

    clearFieldErrors();
    closePriceRecommendations();
}

function createRecommendationRow(record) {
    const row = document.createElement("div");
    row.className = "price-modal-item";

    const info = document.createElement("div");
    info.className = "price-modal-item-info";

    info.append(
        createTextElement("strong", "", record.species_name),
        createTextElement(
            "span",
            "",
            record.available
                ? `${record.record_count} livestock sold`
                : "No verified sales for this period",
        ),
    );

    const priceContainer = document.createElement("div");
    priceContainer.className = "price-modal-item-price";

    const price = Number(record.average_price_per_kg);

    const available = record.available && Number.isFinite(price) && price > 0;

    priceContainer.append(
        createTextElement(
            "strong",
            "",
            available ? `${currency.format(price)}/kg` : "No data",
        ),
    );

    if (available) {
        const button = document.createElement("button");

        button.type = "button";
        button.className = "price-modal-use";
        button.textContent = "Use this price";

        button.addEventListener("click", () => {
            useRecommendedPrice(record);
        });

        priceContainer.append(button);
    }

    row.append(info, priceContainer);

    return row;
}

function renderRecommendations() {
    const container = $("price-modal-list");

    const query = $("price-modal-search").value.trim().toLowerCase();

    const records = state.recommendations.filter((record) =>
        String(record.species_name).toLowerCase().includes(query),
    );

    container.replaceChildren();

    if (!records.length) {
        container.append(
            createTextElement(
                "p",
                "price-modal-state",
                state.recommendations.length
                    ? "No matching livestock species."
                    : "No active species available.",
            ),
        );

        return;
    }

    const fragment = document.createDocumentFragment();

    records.forEach((record) => {
        fragment.append(createRecommendationRow(record));
    });

    container.append(fragment);
}

function updateRecommendationSummary(payload) {
    const records = state.recommendations;

    const available = records.filter(
        (record) => record.available && Number(record.average_price_per_kg) > 0,
    );

    const livestockCount = available.reduce(
        (total, record) => total + Number(record.record_count || 0),
        0,
    );

    $("price-modal-species-count").textContent =
        `${available.length} / ${records.length}`;

    $("price-modal-livestock-count").textContent = String(livestockCount);

    if (payload.week_start && payload.week_end) {
        $("price-modal-period").textContent =
            `Verified sales: ${payload.week_start} to ${payload.week_end}`;
    }
}

async function openPriceRecommendations() {
    if (state.loading || state.saving) return;

    state.modalPreviousFocus = document.activeElement;

    setModalVisible(true);

    state.recommendations = [];

    $("price-modal-search").value = "";
    $("price-modal-list").replaceChildren();

    $("price-modal-error").hidden = true;
    $("price-modal-loading").hidden = false;

    $("price-modal-species-count").textContent = "—";
    $("price-modal-livestock-count").textContent = "—";

    $("price-modal-period").textContent = "Previous completed week";

    const requestId = ++state.modalRequestId;

    try {
        const effectiveFrom =
            $("price-effective-from").value || localDateString();

        const response = await api.get(
            "/lgu/price-references/recommendations",
            {
                params: { effective_from: effectiveFrom },
            },
        );

        if (requestId !== state.modalRequestId || !isModalOpen()) {
            return;
        }

        const payload = unwrap(response);

        state.recommendations = Array.isArray(payload.recommendations)
            ? payload.recommendations
            : [];

        updateRecommendationSummary(payload);
        renderRecommendations();
    } catch (error) {
        if (requestId !== state.modalRequestId || !isModalOpen()) {
            return;
        }

        const target = $("price-modal-error");

        target.textContent = errorMessage(error);
        target.hidden = false;
    } finally {
        if (requestId === state.modalRequestId) {
            $("price-modal-loading").hidden = true;
        }
    }
}

function handleModalKeyboard(event) {
    if (!isModalOpen()) return;

    if (event.key === "Escape") {
        event.preventDefault();
        closePriceRecommendations();
        return;
    }

    if (event.key !== "Tab") return;

    const focusable = Array.from(
        modal().querySelectorAll(
            "button:not([disabled]), input:not([disabled])",
        ),
    ).filter((element) => element.getClientRects().length > 0);

    if (!focusable.length) return;

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

function bindRecommendationModal() {
    $("open-price-recommendations").addEventListener(
        "click",
        openPriceRecommendations,
    );

    $("close-price-recommendations").addEventListener(
        "click",
        closePriceRecommendations,
    );

    $("price-modal-done").addEventListener("click", closePriceRecommendations);

    modal()
        .querySelectorAll("[data-close-price-modal]")
        .forEach((element) => {
            element.addEventListener("click", closePriceRecommendations);
        });

    $("price-modal-search").addEventListener("input", renderRecommendations);

    document.addEventListener("keydown", handleModalKeyboard);
}

// ---------------------------------------------------------------------
// Events
// ---------------------------------------------------------------------

function bindEvents() {
    $("price-species").addEventListener("change", async () => {
        // Invalidate pending breed requests immediately.
        ++state.breedRequestId;

        populateSelect("price-breed", [], "All breeds");

        $("price-breed").disabled = true;

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

    bindRecommendationModal();
}

// ---------------------------------------------------------------------
// Initialization
// ---------------------------------------------------------------------

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

        $("price-breed").disabled = !$("price-species").value;
    } catch (error) {
        notify(errorMessage(error));
    } finally {
        state.loading = false;
    }
}

init();
