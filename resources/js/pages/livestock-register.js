import api from "../api";

const $ = (id) => document.getElementById(id);

const state = {
    submitting: false,
    breedRequestId: 0,
};

/* =========================================================
   CONDITION ASSESSMENT CONFIGURATION
========================================================= */

const conditionQuestions = [
    {
        id: "currently_sick",
        category: "Health History & Current Condition",
        question: "Is the animal currently sick?",
        translation: "Maysakit ba karon ang hayop?",
        options: [
            ["No / Wala", 2],
            ["Not sure / Dili sigurado", 1],
            ["Yes / Oo", 0],
        ],
    },
    {
        id: "recently_sick",
        question: "Has the animal been sick recently?",
        translation: "Nakasakit ba ang hayop bag-o lang?",
        options: [
            ["No / Wala", 2],
            ["Yes, but recovered / Oo, pero naayo na", 1],
            ["Yes, still recovering / Oo, nagpaayo pa", 0],
        ],
    },
    {
        id: "eating_normally",
        question: "Is the animal eating normally?",
        translation: "Normal ba ang pagkaon sa hayop?",
        options: [
            ["Yes / Oo", 2],
            ["Less than usual / Gamay ra kaysa naandan", 1],
            ["Not eating / Dili mokaon", 0],
        ],
    },
    {
        id: "visible_injuries",
        question: "Does the animal have visible wounds or injuries?",
        translation: "Aduna bay samad o pilas ang hayop?",
        options: [
            ["None / Wala", 2],
            ["Minor wounds / Gamay nga samad", 1],
            ["Serious wounds / Grabe nga samad", 0],
        ],
    },
    {
        id: "prominent_bones",
        category: "Body Condition",
        question: "Are the animal's bones visibly prominent?",
        translation: "Klaro kaayo ba ang buto-buto sa hayop?",
        options: [
            ["Very prominent / Klaro kaayo ang buto-buto", 0],
            ["Somewhat prominent / Medyo klaro", 1],
            ["Not prominent / Dili kaayo klaro", 2],
        ],
    },
    {
        id: "overall_body",
        question: "How does the animal's overall body look?",
        translation: "Unsa ang kinatibuk-ang hitsura sa lawas sa hayop?",
        options: [
            ["Very thin / Niwang kaayo", 0],
            ["Moderately filled / Sakto-sakto ang lawas", 1],
            ["Well-filled / Puno ug maayong pagkabuo", 2],
        ],
    },
    {
        id: "tail_base",
        question: "Does the area around the base of the tail look sunken?",
        translation: "Lubog ba ang dapit sa punoan sa ikog?",
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
            ? interpretConditionScore(total)
            : "Awaiting assessment";
    }

    if (descriptionElement) {
        descriptionElement.textContent = complete
            ? `Calculated condition score: ${mapAssessmentToConditionScore(total)} / 5`
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
        await api.post("/farmer/livestock", payload);

        form.reset();
        syncReproductiveFields();
        calculateConditionAssessment();

        state.breedRequestId++;

        $("lr-breed").replaceChildren(new Option("Select a species first", ""));
        $("lr-breed").disabled = true;

        showAlert(
            "lr-page-success",
            "Livestock registered successfully! " +
                "The record is now available in your livestock directory.",
        );

        window.scrollTo({
            top: 0,
            behavior: "smooth",
        });
    } catch (error) {
        const data = error?.response?.data;

        if (error?.response?.status === 422) {
            showValidationErrors(data?.errors);
        }

        showAlert("lr-page-error", getError(error));

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

/* =========================================================
   INITIALIZATION
========================================================= */

function init() {
    const form = $("lr-form");

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
    loadSpecies();
}

document.addEventListener("DOMContentLoaded", init);
