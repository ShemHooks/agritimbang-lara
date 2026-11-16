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

// Return the Sunday that closes the Monday-Sunday week containing dateValue.
function weeklyEffectiveTo(dateValue) {
    if (!dateValue) return "";

    const [year, month, day] = dateValue.split("-").map(Number);
    const date = new Date(Date.UTC(year, month - 1, day));
    const daysUntilSunday = (7 - date.getUTCDay()) % 7;
    date.setUTCDate(date.getUTCDate() + daysUntilSunday);

    return [
        date.getUTCFullYear(),
        String(date.getUTCMonth() + 1).padStart(2, "0"),
        String(date.getUTCDate()).padStart(2, "0"),
    ].join("-");
}

function addDateDays(dateValue, days) {
    if (!dateValue) return "";
    const [year, month, day] = dateValue.split("-").map(Number);
    const date = new Date(Date.UTC(year, month - 1, day));
    date.setUTCDate(date.getUTCDate() + days);
    return [
        date.getUTCFullYear(),
        String(date.getUTCMonth() + 1).padStart(2, "0"),
        String(date.getUTCDate()).padStart(2, "0"),
    ].join("-");
}

function syncWeeklyEffectiveTo() {
    const start = $("price-effective-from").value;
    const end = $("price-effective-to");
    end.value = weeklyEffectiveTo(start);
    end.min = start || "";
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

    // Optional controls may be absent on municipality-wide price forms.
    if (!select) {
        console.warn(`[AgriTimbang] Skipping missing optional select: #${id}`);
        return false;
    }

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

    // Recalculate from the selected start date so old drafts also follow
    // the Monday-Sunday weekly rule.
    syncWeeklyEffectiveTo();

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
        effective_to: weeklyEffectiveTo(data.get("effective_from") || "") || null,
        source: String(data.get("source") || "").trim(),
        remarks: String(data.get("remarks") || "").trim() || null,
        recommendation_id: data.get("recommendation_id") || null,
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

    const action = event.submitter?.dataset?.priceAction || "draft";
    const shouldSubmit = action === "submit";
    const payload = collectPayload();
    const errors = validatePayload(payload);

    if (Object.keys(errors).length) {
        showFieldErrors(errors);
        notify("Please correct the highlighted fields.");
        return;
    }

    state.saving = true;

    const form = $("price-reference-form");
    const actionButtons = Array.from(form.querySelectorAll('button[type="submit"]'));
    const originalLabels = actionButtons.map((button) => button.textContent);

    actionButtons.forEach((button) => {
        button.disabled = true;
        if (button.dataset.priceAction === action) {
            button.textContent = shouldSubmit ? "Submitting..." : "Saving...";
        }
    });

    try {
        let savedRecord = null;

        if (isEditing()) {
            const response = await api.patch(
                `/lgu/price-references/${encodeURIComponent(state.priceId)}`,
                payload,
            );
            savedRecord = unwrap(response);
        } else {
            const response = await api.post("/lgu/price-references", payload);
            savedRecord = unwrap(response);
            state.priceId = savedRecord?.id ?? savedRecord?.price_reference?.id ?? null;
        }

        if (shouldSubmit) {
            const priceId = state.priceId ?? savedRecord?.id ?? savedRecord?.price_reference?.id;

            if (!priceId) {
                throw new Error(
                    "The draft was saved, but its ID was not returned. Open Drafts and submit it from there.",
                );
            }

            await api.post(
                `/lgu/price-references/${encodeURIComponent(priceId)}/submit`,
            );
        }

        window.location.assign("/prices");
    } catch (error) {
        if (error.response?.status === 422) {
            showFieldErrors(error.response.data?.errors);
        }

        notify(errorMessage(error));
    } finally {
        state.saving = false;
        actionButtons.forEach((button, index) => {
            button.disabled = false;
            button.textContent = originalLabels[index];
        });
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

async function useRecommendedPrice(record) {
    // Validate the complete form target BEFORE reserving the recommendation.
    // This prevents a recommendation from being marked used when the UI cannot
    // populate the corresponding price-reference fields.
    const fields = {
        species: $("price-species"),
        purpose: $("price-purpose"),
        effectiveFrom: $("price-effective-from"),
        effectiveTo: $("price-effective-to"),
        recommendationId: $("recommendation-id"),
        source: $("price-source"),
        remarks: $("price-remarks"),
        amount: $("price-amount"),
    };
    const missingField = Object.entries(fields).find(([, element]) => !element);
    if (missingField) {
        notify(`Cannot use this recommendation because the price form is missing its ${missingField[0]} field. Refresh the page and try again.`);
        return;
    }

    const recommendationId = String(record.id ?? "");
    const speciesId = String(record.species_id ?? "");
    const price = Number(record.recommended_price ?? record.average_price_per_kg);

    if (!recommendationId || !speciesId) {
        notify("This recommendation is missing its ID or species. Refresh the recommendations and try again.");
        return;
    }
    if (record.status !== "available" || record.used_at || record.price_reference_id) {
        notify("This recommendation is no longer available. It may already have been used by another encoder.");
        return;
    }
    if (!Number.isFinite(price) || price <= 0) {
        notify("The recommended price is invalid.");
        return;
    }

    const speciesOption = Array.from(fields.species.options).find(
        (option) => String(option.value) === speciesId,
    );
    if (!speciesOption) {
        notify("The selected species is not available in the price-reference form. Refresh the page and try again.");
        return;
    }

    const button = document.activeElement instanceof HTMLButtonElement
        ? document.activeElement
        : null;
    const originalLabel = button?.textContent ?? "Use this price";
    if (button) {
        button.disabled = true;
        button.textContent = "Reserving...";
    }

    try {
        const response = await api.post(
            `/weekly-price-recommendations/${encodeURIComponent(recommendationId)}/use`,
            {},
        );
        const reserved = unwrap(response);

        if (reserved?.status !== "used" || !reserved?.used_at) {
            throw new Error(
                reserved?.message || "The recommendation could not be reserved. Refresh and try again.",
            );
        }

        // Populate the form only after the server confirms the reservation.
        fields.species.value = speciesId;
        fields.purpose.value = record.sale_purpose;
        // Sales observations belong to the completed week. The new official
        // reference takes effect in the immediately following Monday-Sunday week.
        fields.effectiveFrom.value = addDateDays(normalizeDate(record.week_start), 7);
        fields.effectiveTo.value = addDateDays(normalizeDate(record.week_end), 7);
        fields.recommendationId.value = recommendationId;
        fields.source.value = "AgriTimbang Automatic Price Recommendation Engine";
        fields.remarks.value = `Automatically generated from verified sales dated ${normalizeDate(record.week_start)} to ${normalizeDate(record.week_end)}. Effective period: ${fields.effectiveFrom.value} to ${fields.effectiveTo.value}. Recommendation ID: ${recommendationId}`;
        fields.amount.value = price.toFixed(2);

        fields.species.dispatchEvent(new Event("change", { bubbles: true }));
        clearFieldErrors();
        closePriceRecommendations();
        notify("Recommended price reserved and added to your price-reference form. Save the draft or submit it for review to link the reservation.", "success");
    } catch (error) {
        notify(errorMessage(error));
    } finally {
        if (button?.isConnected) {
            button.disabled = false;
            button.textContent = originalLabel;
        }
    }
}

function createRecommendationRow(record) {
    const row = document.createElement("div");
    row.className = "price-modal-item";

    const info = document.createElement("div");
    info.className = "price-modal-item-info";

    info.append(
        createTextElement("strong", "", `${record.species?.name ?? record.species_name ?? "Livestock"} · ${String(record.sale_purpose ?? "").replace(/\b\w/g, (letter) => letter.toUpperCase())}`),
        createTextElement(
            "span",
            "",
            record.status === "available"
                ? `${record.included_count} included observations · ${record.excluded_count} excluded · ${record.sale_purpose}`
                : `Insufficient data · ${record.included_count ?? 0} valid observations · ${record.sale_purpose}`,
        ),
    );

    const priceContainer = document.createElement("div");
    priceContainer.className = "price-modal-item-price";

    const price = Number(record.recommended_price ?? record.average_price_per_kg);

    const available = record.status === "available" && !record.used_at && !record.price_reference_id && Number.isFinite(price) && price > 0;

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
            useRecommendedPrice(record).catch((error) => notify(errorMessage(error)));
        });

        priceContainer.append(button);
    }
    const detailButton = document.createElement("button");
    detailButton.type = "button";
    detailButton.className = "price-modal-use";
    detailButton.textContent = "View calculation";
    detailButton.addEventListener("click", async () => {
        try {
            const response = await api.get(`/weekly-price-recommendations/${encodeURIComponent(record.id)}`);
            const detail = unwrap(response);
            const panel = document.createElement("div");
            panel.className = "price-modal-calculation-detail";

            const unit = "/kg";
            const amount = (value) => value === null || value === undefined || value === ""
                ? "—"
                : `${currency.format(Number(value))}${unit}`;

            const addCalculationRow = (label, value) => {
                const line = document.createElement("div");
                line.className = "price-modal-calculation-row";
                line.append(
                    createTextElement("span", "price-modal-calculation-label", label),
                    createTextElement("strong", "price-modal-calculation-value", amount(value)),
                );
                panel.append(line);
            };

            addCalculationRow("IQR = Q3 − Q1", detail.iqr);
            addCalculationRow("Lower bound = Q1 − 1.5 × IQR", detail.lower_bound);
            addCalculationRow("Upper bound = Q3 + 1.5 × IQR", detail.upper_bound);
            panel.append(
                createTextElement("p", "price-modal-calculation-note", "Minimum required: 4 included observations."),
                createTextElement(
                    "p",
                    "price-modal-calculation-note",
                    `Current result: ${Number(detail.included_count ?? 0)} included, ${Number(detail.excluded_count ?? 0)} excluded;`,
                ),
                createTextElement(
                    "p",
                    "price-modal-calculation-formula",
                    `Recommended price = sum of included observed prices ${unit} ÷ included observations.`,
                ),
                createTextElement(
                    "p",
                    "price-modal-calculation-recommended",
                    `Recommended price: ${amount(detail.recommended_price ?? record.recommended_price)}.`,
                ),
            );
            row.querySelector(".price-modal-calculation-detail")?.remove();
            row.append(panel);
            detailButton.disabled = false;
        } catch (error) { notify(errorMessage(error)); }
    });
    priceContainer.append(detailButton);

    row.append(info, priceContainer);

    return row;
}

function renderRecommendations() {
    const container = $("price-modal-list");
    const search = $("price-modal-search");

    if (!container || !search) return;

    const query = search.value.trim().toLowerCase();

    const records = state.recommendations.filter((record) =>
        `${record.species?.name ?? record.species_name ?? ""} ${record.sale_purpose ?? ""}`.toLowerCase().includes(query),
    );

    container.replaceChildren();

    if (!records.length) {
        container.append(
            createTextElement(
                "p",
                "price-modal-state",
                state.recommendations.length
                    ? "No matching species or sale purpose."
                    : "No recommendations are available for the previous completed week yet.",
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

    const includedCount = records.reduce(
        (total, record) => total + Number(record.included_count || 0),
        0,
    );

    $("price-modal-species-count").textContent = `${records.length} / 16`;
    $("price-modal-livestock-count").textContent = String(includedCount);

    if (records[0]?.week_start && records[0]?.week_end) {
        $("price-modal-period").textContent =
            `Verified sales: ${normalizeDate(records[0].week_start)} to ${normalizeDate(records[0].week_end)}`;
    }
}

async function openPriceRecommendations() {
    if (state.loading || state.saving) return;

    state.modalPreviousFocus = document.activeElement;

    setModalVisible(true);

    state.recommendations = [];

    const search = $("price-modal-search");
    const list = $("price-modal-list");
    if (search) search.value = "";
    if (list) list.replaceChildren();

    $("price-modal-error").hidden = true;
    $("price-modal-loading").hidden = false;

    $("price-modal-species-count").textContent = "—";
    $("price-modal-livestock-count").textContent = "—";

    $("price-modal-period").textContent = "Previous completed week";

    const requestId = ++state.modalRequestId;

    try {
        const effectiveFrom =
            $("price-effective-from").value || localDateString();

        const response = await api.get("/weekly-price-recommendations");

        if (requestId !== state.modalRequestId || !isModalOpen()) {
            return;
        }

        const payload = unwrap(response);

        state.recommendations = Array.isArray(payload) ? payload : (Array.isArray(payload?.data) ? payload.data : []);

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

    $("price-effective-from").addEventListener("change", syncWeeklyEffectiveTo);

    $("price-reference-form").addEventListener("submit", savePrice);

    syncWeeklyEffectiveTo();
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

        $("price-form-save-draft").textContent = isEditing()
            ? "Save Changes"
            : "Save Draft";
        $("price-form-submit").textContent = "Submit for Review";

        await loadSpecies();

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
