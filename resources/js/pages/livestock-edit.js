import {
    api,
    $,
    context,
    unwrap,
    list,
    readonly,
    showError,
} from "./livestock-common";

const editable = [
    "species_id",
    "breed_id",
    "sex",
    "age_months",
    "reproductive_status",
    "parity",
    "sale_purpose",
    "condition_score",
    "actual_weight_kg",
];

const conditionQuestions = [
    { id: "currently_sick", category: "Kahimsog sa Hayop (Animal Health)", question: "Maysakit ba karon ang hayop?", translation: "Is the animal currently sick?", options: [["No / Wala", 2], ["Not sure / Dili sigurado", 1], ["Yes / Oo", 0]] },
    { id: "recently_sick", question: "Nakasakit ba ang hayop bag-o lang?", translation: "Has the animal been sick recently?", options: [["No / Wala", 2], ["Yes, but recovered / Oo, pero naayo na", 1], ["Yes, still recovering / Oo, nagpaayo pa", 0]] },
    { id: "eating_normally", question: "Normal ba ang pagkaon sa hayop?", translation: "Is the animal eating normally?", options: [["Yes / Oo", 2], ["Less than usual / Gamay ra kaysa naandan", 1], ["Not eating / Dili mokaon", 0]] },
    { id: "visible_injuries", question: "Aduna bay samad o pilas ang hayop?", translation: "Does the animal have visible wounds or injuries?", options: [["None / Wala", 2], ["Minor wounds / Gamay nga samad", 1], ["Serious wounds / Grabe nga samad", 0]] },
    { id: "prominent_bones", category: "Kahimtang sa Lawas (Body Condition)", question: "Klaro kaayo ba ang buto-buto sa hayop?", translation: "Are the animal's bones visibly prominent?", options: [["Very prominent / Klaro kaayo ang buto-buto", 0], ["Somewhat prominent / Medyo klaro", 1], ["Not prominent / Dili kaayo klaro", 2]] },
    { id: "overall_body", question: "Unsa ang kinatibuk-ang hitsura sa lawas sa hayop?", translation: "How does the animal's overall body look?", options: [["Very thin / Niwang kaayo", 0], ["Moderately filled / Sakto-sakto ang lawas", 1], ["Well-filled / Puno ug maayong pagkabuo", 2]] },
    { id: "tail_base", question: "Lubog ba ang dapit sa punoan sa ikog?", translation: "Does the area around the base of the tail look sunken?", options: [["Deep hollow / Lawom ug lubog", 0], ["Slight hollow / Medyo lubog", 1], ["Filled / Puno ang hitsura", 2]] },
];

let conditionAnswers = {};

function renderConditionChecklist() {
    const container = $("l55-condition-questions");
    if (!container || container.dataset.rendered === "1") return;
    container.innerHTML = conditionQuestions.map((question, index) => `
        ${question.category ? `<h4 class="l55-assessment-category">${question.category}</h4>` : ""}
        <fieldset class="l55-assessment-question">
            <legend><strong>${index + 1}. ${question.question}</strong><small>${question.translation}</small></legend>
            <div class="l55-assessment-options">${question.options.map(([label, points]) => `
                <label class="l55-assessment-option"><input type="radio" name="l55_assessment_${question.id}" value="${points}"><span>${label}</span></label>
            `).join("")}</div>
        </fieldset>
    `).join("");
    container.dataset.rendered = "1";
}

function assessCondition() {
    let total = 0;
    conditionAnswers = {};
    for (const question of conditionQuestions) {
        const selected = document.querySelector(`input[name="l55_assessment_${question.id}"]:checked`);
        if (!selected) continue;
        conditionAnswers[question.id] = Number(selected.value);
        total += Number(selected.value);
    }
    const complete = Object.keys(conditionAnswers).length === conditionQuestions.length;
    const score = complete ? (total <= 4 ? 1 : total <= 8 ? 2 : total <= 11 ? 3 : total <= 13 ? 4 : 5) : null;
    const result = complete ? (total <= 4 ? "Poor condition" : total <= 8 ? "Needs attention" : total <= 11 ? "Fair condition" : "Good condition") : null;
    $("l55-condition-total").textContent = complete ? `${total} / 14` : `${Object.keys(conditionAnswers).length}/7 answered`;
    $("l55-condition-mapped").textContent = complete ? `${score} / 5 — ${result}` : "Awaiting assessment";
    $("l55-condition-apply").disabled = !complete;
    return { complete, score, total, result };
}

function openConditionModal() {
    renderConditionChecklist();
    $("l55-condition-modal").hidden = false;
    document.body.classList.add("l55-condition-open");
}

function closeConditionModal() {
    $("l55-condition-modal").hidden = true;
    document.body.classList.remove("l55-condition-open");
}


let endpoint;
let original;
let breedRequest = 0;

async function loadBreeds(speciesId, selected = "") {
    const request = ++breedRequest;
    const select = $("l55-breed");

    select.replaceChildren(new Option("Unspecified", ""));

    if (!speciesId) return;

    const breeds = list(
        await api.get(
            `/reference/species/${encodeURIComponent(speciesId)}/breeds`,
        ),
    );

    if (request !== breedRequest) return;

    breeds.forEach((breed) => {
        select.add(new Option(breed.name, breed.id));
    });

    select.value = selected || "";
}

function syncReproductive() {
    const sex = $("l55-sex").value;
    const status = $("l55-reproductive");
    const parity = $("l55-parity");
    const hint = $("l55-parity-hint");
    const male = sex === "male";
    const allowedStatuses = {
        male: ["not_applicable"],
        female: ["unknown", "has_given_birth", "never_given_birth"],
        unknown: ["unknown", "has_given_birth", "never_given_birth", "not_applicable"],
    };
    const allowed = allowedStatuses[sex] ?? ["unknown"];

    for (const option of status.options) {
        option.disabled = !allowed.includes(option.value);
        option.hidden = option.disabled;
    }

    if (male) {
        status.value = "not_applicable";
        parity.value = "";
        status.disabled = true;
        parity.disabled = true;
        parity.required = false;
        hint.textContent = "Dili kini magamit sa laki nga hayop.";
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
        hint.textContent = "Ang kadaghan sa pagpanganak para lamang sa baye nga hayop.";
        return;
    }

    if (status.value === "has_given_birth") {
        parity.disabled = false;
        parity.required = true;
        parity.min = "1";
        hint.textContent = "Ibutang ang gidaghanon sa pagpanganak (labing menos 1).";
    } else if (status.value === "never_given_birth") {
        parity.value = "0";
        parity.disabled = true;
        parity.required = false;
        hint.textContent = "Awtomatikong 0 kay wala pa kini makapanganak.";
    } else {
        parity.value = "";
        parity.disabled = true;
        parity.required = false;
        hint.textContent = "Dili kinahanglan pun-on ang kadaghan sa pagpanganak niini nga kahimtang.";
    }
}

async function init() {
    const root = $("l55-edit");
    if (!root) return;

    const id = root.dataset.id;

    $("l55-cancel").href = `/livestock/${encodeURIComponent(id)}`;

    try {
        ({ endpoint } = await context());

        original = unwrap(
            await api.get(`${endpoint}/${encodeURIComponent(id)}`),
        );

        if (!original?.id) {
            throw new Error("Invalid livestock details response.");
        }

        if (readonly(original)) {
            throw new Error("Only available livestock can be edited.");
        }

        const form = $("l55-form");

        const species = list(await api.get("/reference/species"));

        $("l55-species").replaceChildren(
            ...species.map((item) => new Option(item.name, item.id)),
        );

        if (
            original.species_id &&
            !species.some(
                (item) => String(item.id) === String(original.species_id),
            )
        ) {
            $("l55-species").add(
                new Option(
                    original.species?.name ?? "Current species",
                    original.species_id,
                ),
            );
        }

        for (const field of editable) {
            const input = form.elements.namedItem(field);
            if (!input) continue;
            input.value = original[field] ?? "";
        }

        const savedScore = Number(original.condition_score || 0);
        if (savedScore >= 1 && savedScore <= 5) {
            $("l55-condition-result").textContent = `Saved condition score: ${savedScore}/5. Open the checklist to reassess.`;
        }
        if (original.photo_path) {
            const preview = $("l55-photo-preview");
            preview.src = `/storage/${String(original.photo_path).split("/").map(encodeURIComponent).join("/")}`;
            preview.hidden = false;
        }

        await loadBreeds(original.species_id, original.breed_id);

        if (original.breed_id && !$("l55-breed").value) {
            $("l55-breed").add(
                new Option(
                    original.breed?.name ?? "Current breed",
                    original.breed_id,
                ),
            );

            $("l55-breed").value = original.breed_id;
        }

        syncReproductive();

        $("l55-species").addEventListener("change", async () => {
            try {
                await loadBreeds($("l55-species").value);
            } catch (error) {
                showError(error);
            }
        });

        $("l55-sex").addEventListener("change", syncReproductive);
        $("l55-reproductive").addEventListener("change", syncReproductive);

        form.addEventListener("submit", save);
        $("l55-condition-open").addEventListener("click", openConditionModal);
        $("l55-condition-close").addEventListener("click", closeConditionModal);
        $("l55-condition-modal").addEventListener("click", (event) => {
            if (event.target?.hasAttribute("data-l55-condition-close")) closeConditionModal();
        });
        $("l55-condition-questions").addEventListener("change", assessCondition);
        $("l55-condition-apply").addEventListener("click", () => {
            const assessment = assessCondition();
            if (!assessment.complete) return;
            $("l55-condition-score").value = String(assessment.score);
            $("l55-condition-result").textContent = `Assessment: ${assessment.total}/14 · Condition score: ${assessment.score}/5 (${assessment.result})`;
            closeConditionModal();
        });
        $("l55-photo").addEventListener("change", (event) => {
            const file = event.target.files?.[0];
            const preview = $("l55-photo-preview");
            if (!file) return;
            if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size > 5 * 1024 * 1024) {
                event.target.value = "";
                showError("Choose a JPG, PNG, or WEBP image no larger than 5 MB.");
                return;
            }
            preview.src = URL.createObjectURL(file);
            preview.hidden = false;
            $("l55-error").hidden = true;
        });
        form.hidden = false;
    } catch (error) {
        showError(error);
    } finally {
        $("l55-loading").hidden = true;
    }
}

async function save(event) {
    event.preventDefault();

    const button = $("l55-save");
    button.disabled = true;
    button.textContent = "Saving…";

    $("l55-error").hidden = true;

    try {
        const form = $("l55-form");
        const conditionScore = form.elements.namedItem("condition_score").value || original.condition_score;
        if (!conditionScore) {
            throw new Error("Complete the Body Condition Checklist before saving this livestock record.");
        }
        const formData = new FormData();
        formData.append("_method", "PATCH");

        for (const field of editable) {
            const input = form.elements.namedItem(field);
            if (!input) continue;
            let value = input.value;
            if (field === "reproductive_status" && (input.disabled || form.elements.namedItem("sex").value === "male")) value = "not_applicable";
            if (field === "parity" && (input.disabled || form.elements.namedItem("sex").value === "male")) value = "";
            if (field === "condition_score" && !value) value = original.condition_score ?? "";
            formData.append(field, value ?? "");
        }

        const photo = $("l55-photo").files?.[0];
        if (photo) formData.append("photo", photo);

        await api.post(
            `${endpoint}/${encodeURIComponent(original.id)}`,
            formData,
        );

        window.location.assign(`/livestock/${encodeURIComponent(original.id)}`);
    } catch (error) {
        showError(error);
        button.disabled = false;
        button.textContent = "Save changes";
    }
}

init();
