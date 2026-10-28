import {
    api,
    $,
    context,
    unwrap,
    label,
    readonly,
    showError,
    detail,
} from "./livestock-common";

async function init() {
    const root = $("l55-show");
    if (!root) return;

    try {
        // Get both endpoint and authenticated user role
        const { endpoint, role } = await context();

        const record = unwrap(
            await api.get(`${endpoint}/${encodeURIComponent(root.dataset.id)}`),
        );

        if (!record?.id) {
            throw new Error("Invalid livestock details response.");
        }

        $("l55-subtitle").textContent = `Record ID: ${record.id}`;
        $("l55-status").textContent = label(record.status);

        // Animal information
        const animal = $("l55-animal");

        [
            ["Species", record.species?.name],
            ["Breed", record.breed?.name],
            ["Sex", label(record.sex)],
            ["Age (months)", record.age_months],
            ["Reproductive status", label(record.reproductive_status)],
            ["Parity", record.parity],
            ["Sale purpose", label(record.sale_purpose)],
            ["Condition score", record.condition_score],
            [
                "Actual weight",
                record.actual_weight_kg == null
                    ? null
                    : `${record.actual_weight_kg} kg`,
            ],
        ].forEach(([name, value]) => detail(animal, name, value));

        // Ownership and location
        const location = $("l55-location");
        const user = record.farmer?.user;

        const farmerName = user
            ? [user.first_name, user.middle_name, user.last_name]
                  .filter(Boolean)
                  .join(" ") || user.name
            : null;

        [
            ["Farmer", farmerName],
            ["Farmer code", record.farmer?.farmer_code],
            ["Municipality", record.municipality?.name],
            ["Barangay", record.barangay?.name],
        ].forEach(([name, value]) => detail(location, name, value));

        // Record information
        [
            ["Record ID", record.id],
            ["Status", label(record.status)],
            [
                "Created",
                record.created_at
                    ? new Date(record.created_at).toLocaleString("en-PH")
                    : null,
            ],
            [
                "Last updated",
                record.updated_at
                    ? new Date(record.updated_at).toLocaleString("en-PH")
                    : null,
            ],
        ].forEach(([name, value]) => detail($("l55-record"), name, value));

        // Edit button
        if (!readonly(record)) {
            const editLink = $("l55-edit-link");

            if (editLink) {
                editLink.href = `/livestock/${encodeURIComponent(record.id)}/edit`;

                editLink.hidden = false;
            }
        }

        // Phase 5.6: Weight Estimation shortcut
        const estimateLink = $("l55-estimate-link");

        if (estimateLink && ["farmer", "lgu_encoder"].includes(role)) {
            estimateLink.href = `/calculator?livestock_id=${encodeURIComponent(record.id)}`;

            estimateLink.hidden = false;
        }

        // Show details
        $("l55-details").hidden = false;
    } catch (error) {
        showError(error);
    } finally {
        $("l55-loading").hidden = true;
    }
}

init();
