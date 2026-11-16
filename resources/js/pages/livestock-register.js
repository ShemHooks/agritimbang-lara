import api from "../api";

const $ = (id) => document.getElementById(id);

const state = {
    submitting: false,
    breedRequestId: 0,
    calculatorPrefill: null,
};

/* =========================================================
   CONDITION ASSESSMENT CONFIGURATION
========================================================= */

const conditionQuestions = [
    {
        id: "currently_sick",
        category: "Kahimsog sa Hayop (Animal Health)",
        question: "Maysakit ba karon ang hayop?",
        translation: "Is the animal currently sick?",
        options: [
            ["No / Wala", 2],
            ["Not sure / Dili sigurado", 1],
            ["Yes / Oo", 0],
        ],
    },
    {
        id: "recently_sick",
        question: "Nakasakit ba ang hayop bag-o lang?",
        translation: "Has the animal been sick recently?",
        options: [
            ["No / Wala", 2],
            ["Yes, but recovered / Oo, pero naayo na", 1],
            ["Yes, still recovering / Oo, nagpaayo pa", 0],
        ],
    },
    {
        id: "eating_normally",
        question: "Normal ba ang pagkaon sa hayop?",
        translation: "Is the animal eating normally?",
        options: [
            ["Yes / Oo", 2],
            ["Less than usual / Gamay ra kaysa naandan", 1],
            ["Not eating / Dili mokaon", 0],
        ],
    },
    {
        id: "visible_injuries",
        question: "Aduna bay samad o pilas ang hayop?",
        translation: "Does the animal have visible wounds or injuries?",
        options: [
            ["None / Wala", 2],
            ["Minor wounds / Gamay nga samad", 1],
            ["Serious wounds / Grabe nga samad", 0],
        ],
    },
    {
        id: "prominent_bones",
        category: "Kahimtang sa Lawas (Body Condition)",
        question: "Klaro kaayo ba ang buto-buto sa hayop?",
        translation: "Are the animal's bones visibly prominent?",
        options: [
            ["Very prominent / Klaro kaayo ang buto-buto", 0],
            ["Somewhat prominent / Medyo klaro", 1],
            ["Not prominent / Dili kaayo klaro", 2],
        ],
    },
    {
        id: "overall_body",
        question: "Unsa ang kinatibuk-ang hitsura sa lawas sa hayop?",
        translation: "How does the animal's overall body look?",
        options: [
            ["Very thin / Niwang kaayo", 0],
            ["Moderately filled / Sakto-sakto ang lawas", 1],
            ["Well-filled / Puno ug maayong pagkabuo", 2],
        ],
    },
    {
        id: "tail_base",
        question: "Lubog ba ang dapit sa punoan sa ikog?",
        translation: "Does the area around the base of the tail look sunken?",
        options: [
            ["Deep hollow / Lawom ug lubog", 0],
            ["Slight hollow / Medyo lubog", 1],
            ["Filled / Puno ang hitsura", 2],
        ],
    },
];

/*
 * Questionnaire interpretation:
 * 0–4   Poor condition
 * 5–8   Needs attention
 * 9–11  Fair condition
 * 12–14 Good condition
 */
function interpretConditionScore(total) {
    if (total <= 4) return "Poor condition";
    if (total <= 8) return "Needs attention";
    if (total <= 11) return "Fair condition";
    return "Good condition";
}

/*
 * Proposed mapping to the existing Laravel 1–5 score.
 * Confirm this mapping with the project stakeholders.
 *
 * 0–4   -> 1
 * 5–8   -> 2
 * 9–11  -> 3
 * 12–13 -> 4
 * 14    -> 5
 */
function mapAssessmentToConditionScore(total) {
    if (total <= 4) return 1;
    if (total <= 8) return 2;
    if (total <= 11) return 3;
    if (total <= 13) return 4;
    return 5;
}

/* =========================================================
   GENERAL HELPERS
========================================================= */

function getError(error) {
    const data = error?.response?.data;
    const first = Object.values(data?.errors ?? {})[0];

    if (Array.isArray(first)) {
        return first[0] ?? "Validation failed.";
    }

    if (typeof first === "string") {
        return first;
    }

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
    if (!element) return;

    element.textContent = message;
    element.hidden = false;
}

function clearAlerts() {
    for (const id of ["lr-page-error", "lr-page-success"]) {
        const element = $(id);
        if (!element) continue;

        element.textContent = "";
        element.hidden = true;
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

function nullableString(value) {
    const trimmed = String(value ?? "").trim();
    return trimmed === "" ? null : trimmed;
}

function fillSelect(select, items, placeholder) {
    select.replaceChildren(new Option(placeholder, ""));

    for (const item of items) {
        if (!item?.id || item.is_active === false || item.is_active === 0) {
            continue;
        }

        select.add(new Option(item.name ?? item.id, item.id));
    }
}

/* =========================================================
   SPECIES AND BREED
========================================================= */

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

async function applyCalculatorPrefill() {
    const fromCalculator = new URLSearchParams(window.location.search).get("from_calculator") === "1";
    let prefill = null;
    try {
        const raw = sessionStorage.getItem("agritimbang_calculator_prefill");
        if (raw) prefill = JSON.parse(raw);
    } catch (_) {
        prefill = null;
    }

    if (!fromCalculator || !prefill || !prefill.species_id) {
        try { sessionStorage.removeItem("agritimbang_calculator_prefill"); } catch (_) {}
        return;
    }

    state.calculatorPrefill = prefill;
    $("lr-species").value = prefill.species_id;
    await loadBreeds(prefill.species_id);

    if (prefill.breed_id) $("lr-breed").value = prefill.breed_id;
    if (prefill.sex) $("lr-sex").value = prefill.sex;
    if (prefill.age_months !== null && prefill.age_months !== undefined) {
        const ageInput = document.querySelector('#lr-form [name="age_months"]');
        if (ageInput) ageInput.value = prefill.age_months;
    }

    const salePurposeInput = document.querySelector('#lr-form [name="sale_purpose"]');
    if (salePurposeInput && prefill.sale_purpose) salePurposeInput.value = prefill.sale_purpose;
    if (prefill.reproductive_status) $("lr-reproductive").value = prefill.reproductive_status;
    if (prefill.parity !== null && prefill.parity !== undefined) $("lr-parity").value = prefill.parity;
    syncReproductiveFields();

    // Restore the calculator's seven-question condition checklist so the
    // registration form retains the actual assessment, not only its score.
    if (prefill.condition_answers && typeof prefill.condition_answers === "object") {
        for (const [questionId, points] of Object.entries(prefill.condition_answers)) {
            const radio = document.querySelector(
                `input[name="assessment_${questionId}"][value="${Number(points)}"]`,
            );
            if (radio) radio.checked = true;
        }
        calculateConditionAssessment();
    }

    const summary = $("lr-calculator-summary");
    if (summary) {
        summary.hidden = false;
        $("lr-calculator-summary-weight").textContent = prefill.estimated_weight_kg
            ? `Estimated weight: ${Number(prefill.estimated_weight_kg).toLocaleString("en-PH", { maximumFractionDigits: 2 })} kg`
            : (prefill.manual_weight_kg ? `Manual weight: ${Number(prefill.manual_weight_kg).toLocaleString("en-PH", { maximumFractionDigits: 2 })} kg` : "Weight estimate will be saved after the livestock record is registered.");
        const estimateInput = $("lr-calculator-estimated-weight");
        if (estimateInput) estimateInput.value = prefill.estimated_weight_kg ?? "";
        const estimatedWeightInput = $("lr-estimated-weight");
        const estimatedWeightField = $("lr-estimated-weight-field");
        if (estimatedWeightInput && prefill.estimated_weight_kg != null) {
            estimatedWeightInput.value = Number(prefill.estimated_weight_kg).toFixed(2);
            if (estimatedWeightField) estimatedWeightField.hidden = false;
        }
        const actualWeightInput = document.querySelector('#lr-form [name="actual_weight_kg"]');
        if (actualWeightInput && prefill.manual_weight_kg) actualWeightInput.value = Number(prefill.manual_weight_kg).toFixed(2);
        const valuationField = $("lr-valuation-estimate-field");
        const valuationInput = $("lr-valuation-estimated-value");
        const valuationDetails = $("lr-valuation-estimate-details");
        if (prefill.estimated_value != null && valuationField && valuationInput) {
            valuationField.hidden = false;
            valuationInput.value = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" }).format(Number(prefill.estimated_value));
            if (valuationDetails) {
                const price = prefill.final_price_per_kg != null ? ` · ${Number(prefill.final_price_per_kg).toFixed(2)} per kg` : "";
                valuationDetails.textContent = `FPC valuation preview${price}. The valuation will be saved after livestock registration.`;
            }
        }
        const labels = [
            ["Heart girth", prefill.measurements?.heart_girth_cm],
            ["Body length", prefill.measurements?.body_length_cm],
            ["Rump height", prefill.measurements?.rump_height_cm],
        ].filter(([, value]) => value !== null && value !== undefined && value !== "")
         .map(([label, value]) => `${label}: ${Number(value).toLocaleString("en-PH", { maximumFractionDigits: 2 })} cm`);
        $("lr-calculator-summary-measurements").textContent = labels.length
            ? labels.join(" · ")
            : "No measurement details were transferred.";
        $("lr-calculator-summary-method").textContent = [prefill.formula_code, prefill.formula_version, prefill.estimate_confidence ? `Confidence: ${prefill.estimate_confidence}` : null, prefill.frame_category ? `Frame: ${prefill.frame_category}` : null].filter(Boolean).join(" · ");
    }

    if (prefill.condition_answers && typeof prefill.condition_answers === "object") {
        for (const [questionId, points] of Object.entries(prefill.condition_answers)) {
            const radio = document.querySelector(`input[name="assessment_${questionId}"][value="${points}"]`);
            if (radio) radio.checked = true;
        }
        calculateConditionAssessment();
    }

    showAlert(
        "lr-page-success",
        "Calculator details were carried over. Complete the remaining livestock information to save this animal.",
    );
}

/* =========================================================
   REPRODUCTIVE INFORMATION
========================================================= */

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

/* =========================================================
   CONDITION ASSESSMENT UI
========================================================= */

function renderConditionQuestions() {
    const container = $("lr-assessment-questions");
    if (!container) return;

    container.innerHTML = conditionQuestions
        .map(
            (question, index) => `
            ${
                question.category
                    ? `<h4 class="lr-assessment-category">
                        ${question.category}
                       </h4>`
                    : ""
            }

            <fieldset class="lr-assessment-question">
                <legend>
                    <strong>${index + 1}. ${question.question}</strong>
                    <small>${question.translation}</small>
                </legend>

                <div class="lr-assessment-options">
                    ${question.options
                        .map(
                            ([label, points]) => `
                            <label class="lr-assessment-option">
                                <input
                                    type="radio"
                                    name="assessment_${question.id}"
                                    value="${points}"
                                    required
                                >
                                <span>${label}</span>
                            </label>
                        `,
                        )
                        .join("")}
                </div>
            </fieldset>
        `,
        )
        .join("");
}

/* =========================================================
   CONDITION CALCULATION
========================================================= */

function calculateConditionAssessment() {
    const answers = {};
    let total = 0;
    let answered = 0;

    for (const question of conditionQuestions) {
        const selected = document.querySelector(
            `input[name="assessment_${question.id}"]:checked`,
        );

        if (!selected) continue;

        const points = Number(selected.value);

        if (![0, 1, 2].includes(points)) continue;

        answers[question.id] = points;
        total += points;
        answered++;
    }

    const complete = answered === conditionQuestions.length;

    const scoreElement = $("lr-assessment-score");
    const progressElement = $("lr-assessment-progress");
    const labelElement = $("lr-assessment-label");
    const descriptionElement = $("lr-assessment-description");

    if (scoreElement) {
        scoreElement.textContent = complete ? `${total} / 14` : "— / 14";
    }

    if (progressElement) {
        progressElement.textContent = `${answered} of ${conditionQuestions.length} questions answered`;
    }

    if (labelElement) {
        labelElement.textContent = complete
            ? `${mapAssessmentToConditionScore(total)} / 5`
            : "Awaiting assessment";
    }

    if (descriptionElement) {
        descriptionElement.textContent = complete
            ? interpretConditionScore(total)
            : "Complete all questions to see the result.";
    }

    return {
        complete,
        answers,
        total: complete ? total : null,
        result: complete ? interpretConditionScore(total) : null,
        conditionScore: complete ? mapAssessmentToConditionScore(total) : null,
    };
}

/* =========================================================
   BUILD REGISTRATION PAYLOAD
========================================================= */

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

    const assessment = calculateConditionAssessment();

    if (!assessment.complete) {
        throw new Error(
            "Please complete all seven condition assessment questions.",
        );
    }

    return {
        species_id: formData.get("species_id"),
        breed_id: nullableString(formData.get("breed_id")),
        sex,
        age_months: nullableString(formData.get("age_months")),
        reproductive_status: reproductiveStatus,
        parity,
        sale_purpose: formData.get("sale_purpose"),

        // Existing Laravel API field.
        // Automatically derived from the seven questions.
        condition_score: assessment.conditionScore,

        actual_weight_kg: nullableString(formData.get("actual_weight_kg")),
        photo: formData.get("photo"),
    };
}

/* =========================================================
   SUBMIT REGISTRATION
========================================================= */

async function submitRegistration(event) {
    event.preventDefault();

    if (state.submitting) return;

    clearAlerts();
    clearFieldErrors();

    const form = $("lr-form");
    const button = $("lr-submit");

    if (!form.reportValidity()) return;

    let payload;

    try {
        payload = buildPayload(form);
    } catch (error) {
        showAlert("lr-page-error", getError(error));
        return;
    }

    state.submitting = true;
    button.disabled = true;
    button.textContent = "Registering...";

    try {
        const multipart = new FormData();
        Object.entries(payload).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== "") multipart.append(key, value);
        });
        const response = await api.post("/farmer/livestock", multipart);
        const createdLivestock = unwrap(response);
        let estimationSaved = false;
        let estimationSaveFailed = false;
        let valuationSaved = false;
        let valuationSaveFailed = false;
        let savedEstimation = null;

        if (state.calculatorPrefill?.measurements && createdLivestock?.id) {
            try {
                const estimationResponse = await api.post(
                    `/farmer/livestock/${encodeURIComponent(createdLivestock.id)}/weight-estimations`,
                    state.calculatorPrefill.measurements,
                );
                savedEstimation = unwrap(estimationResponse);
                estimationSaved = true;
            } catch (_) {
                estimationSaveFailed = true;
            }
        }

        if (state.calculatorPrefill?.valuation_requested && createdLivestock?.id) {
            try {
                const valuationPayload = savedEstimation?.id
                    ? { weight_estimation_id: savedEstimation.id }
                    : {};
                await api.post(`/farmer/livestock/${encodeURIComponent(createdLivestock.id)}/valuations`, valuationPayload);
                valuationSaved = true;
            } catch (_) {
                valuationSaveFailed = true;
            }
        }

        form.reset();
        syncReproductiveFields();
        calculateConditionAssessment();

        state.breedRequestId++;

        $("lr-breed").replaceChildren(new Option("Select a species first", ""));
        $("lr-breed").disabled = true;

        // Keep the confirmation across the redirect to the livestock directory.
        const flashParts = [];
        if (estimationSaved) flashParts.push("the calculated weight estimate was saved");
        else if (estimationSaveFailed) flashParts.push("the calculated weight estimate could not be saved");
        if (valuationSaved) flashParts.push("the valuation was saved");
        else if (valuationSaveFailed) flashParts.push("the valuation could not be saved");
        if (state.calculatorPrefill?.action === "auction") {
            flashParts.push("auction schedule registration is not yet available; please coordinate with your LGU Encoder");
        }
        const flashMessage = flashParts.length
            ? `Livestock successfully registered; ${flashParts.join("; ")}.`
            : "Livestock successfully registered!";
        const flashType = estimationSaveFailed || valuationSaveFailed ? "warning" : "success";
        try {
            sessionStorage.setItem(
                "agritimbang_livestock_registration_flash",
                JSON.stringify({ type: flashType, message: flashMessage }),
            );
        } catch (_) {
            showAlert(flashType === "warning" ? "lr-page-error" : "lr-page-success", flashMessage);
            try { sessionStorage.removeItem("agritimbang_calculator_prefill"); } catch (_) {}
            window.scrollTo({ top: 0, behavior: "smooth" });
            return;
        }

        try { sessionStorage.removeItem("agritimbang_calculator_prefill"); } catch (_) {}
        window.location.assign("/livestock");
    } catch (error) {
        const data = error?.response?.data;

        if (error?.response?.status === 422) {
            showValidationErrors(data?.errors);
        }

        showAlert("lr-page-error", `Livestock registration failed: ${getError(error)}`);

        window.scrollTo({
            top: 0,
            behavior: "smooth",
        });
    } finally {
        state.submitting = false;
        button.disabled = false;
        button.textContent = "Register Livestock";
    }
}


function enforceTwoDecimalWeight(input) {
    if (!input) return;
    input.addEventListener("input", () => {
        let value = String(input.value ?? "");
        value = value.replace(/[^0-9.]/g, "");
        const firstDot = value.indexOf(".");
        if (firstDot !== -1) {
            value = value.slice(0, firstDot + 1) + value.slice(firstDot + 1).replace(/\./g, "");
            const [whole, decimal = ""] = value.split(".");
            value = `${whole}.${decimal.slice(0, 2)}`;
        }
        if (input.value !== value) input.value = value;
    });
}

/* =========================================================
   INITIALIZATION
========================================================= */

async function init() {
    const form = $("lr-form");
    if (!form) return;
    enforceTwoDecimalWeight(form.querySelector('[name="actual_weight_kg"]'));

    $("lr-species").addEventListener("change", (event) => {
        clearAlerts();
        loadBreeds(event.target.value);
    });

    $("lr-sex").addEventListener("change", syncReproductiveFields);

    $("lr-reproductive").addEventListener("change", syncReproductiveFields);

    renderConditionQuestions();

    $("lr-assessment-questions")?.addEventListener(
        "change",
        calculateConditionAssessment,
    );

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
            calculateConditionAssessment();
        }, 0);
    });

    syncReproductiveFields();
    calculateConditionAssessment();
    await loadSpecies();
    await applyCalculatorPrefill();
}

document.addEventListener("DOMContentLoaded", init);
