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
    const male = $("l55-sex").value === "male";

    if (male) {
        $("l55-reproductive").value = "not_applicable";
        $("l55-parity").value = "";
    }

    $("l55-reproductive").disabled = male;
    $("l55-parity").disabled = male;
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

            if (input) {
                input.value = original[field] ?? "";
            }
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

        form.addEventListener("submit", save);
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
        const payload = {};

        for (const field of editable) {
            const input = form.elements.namedItem(field);
            if (!input) continue;

            const value =
                input.disabled && field === "reproductive_status"
                    ? "not_applicable"
                    : input.disabled && field === "parity"
                      ? null
                      : input.value;

            payload[field] =
                value === "" &&
                [
                    "age_months",
                    "parity",
                    "actual_weight_kg",
                    "breed_id",
                ].includes(field)
                    ? null
                    : value;
        }

        if (payload.sex === "male") {
            payload.reproductive_status = "not_applicable";
            payload.parity = null;
        }

        await api.patch(
            `${endpoint}/${encodeURIComponent(original.id)}`,
            payload,
        );

        window.location.assign(`/livestock/${encodeURIComponent(original.id)}`);
    } catch (error) {
        showError(error);
        button.disabled = false;
        button.textContent = "Save changes";
    }
}

init();
