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
import { formatDateTime as sharedFormatDateTime } from "../utils/date-format";

function reproductiveStatusLabel(value) {
    const labels = {
        has_given_birth: "Nakapanganak na",
        never_given_birth: "Wala pa makapanganak",
        not_applicable: "Dili magamit",
        unknown: "Wala mahibal-i",
    };

    return labels[value] ?? "Wala mahibal-i";
}

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
        if (record.photo_path) {
            const photoUrl = `/storage/${String(record.photo_path).split("/").map(encodeURIComponent).join("/")}`;
            const photoLink = document.createElement("a");
            photoLink.href = photoUrl;
            photoLink.target = "_blank";
            photoLink.rel = "noopener noreferrer";
            photoLink.className = "l55-photo-link";
            photoLink.setAttribute("aria-label", "Open livestock photo in a new tab");
            const photo = document.createElement("img");
            photo.src = photoUrl;
            photo.alt = "Registered livestock — click to view full image";
            photo.className = "l55-photo-preview";
            photoLink.append(photo);
            animal.parentElement.prepend(photoLink);
        }

        const actualWeight = Number(record.actual_weight_kg);
        const estimatedWeight = Number(record.latest_weight_estimation?.estimated_weight_kg);
        const weightDisplay = record.actual_weight_kg !== null && record.actual_weight_kg !== undefined && record.actual_weight_kg !== "" && Number.isFinite(actualWeight) && actualWeight > 0
            ? `${actualWeight.toFixed(2)} kg (Actual)`
            : record.latest_weight_estimation?.estimated_weight_kg !== null && record.latest_weight_estimation?.estimated_weight_kg !== undefined && record.latest_weight_estimation?.estimated_weight_kg !== "" && Number.isFinite(estimatedWeight) && estimatedWeight > 0
                ? `${estimatedWeight.toFixed(2)} kg (Estimated)`
                : "Not recorded";

        [
            ["Species", record.species?.name],
            ["Breed", record.breed?.name],
            ["Sex", label(record.sex)],
            ["Age (months)", record.age_months],
            ["Kahimtang sa pagpanganak", reproductiveStatusLabel(record.reproductive_status)],
            ["Kadaghan sa pagpanganak", record.parity],
            ["Sale purpose", label(record.sale_purpose)],
            ["Condition score", record.condition_score],
            ["Weight", weightDisplay],
            [
                "Measured heart girth",
                record.latest_weight_estimation?.heart_girth_cm == null ? null : `${record.latest_weight_estimation.heart_girth_cm} cm`,
            ],
            [
                "Measured body length",
                record.latest_weight_estimation?.body_length_cm == null ? null : `${record.latest_weight_estimation.body_length_cm} cm`,
            ],
            [
                "Measured rump height",
                record.latest_weight_estimation?.rump_height_cm == null ? null : `${record.latest_weight_estimation.rump_height_cm} cm`,
            ],
            [
                "Latest estimated livestock value (FPC)",
                record.latest_valuation?.estimated_value == null
                    ? null
                    : new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" }).format(Number(record.latest_valuation.estimated_value)),
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
                    ? sharedFormatDateTime(record.created_at)
                    : null,
            ],
            [
                "Last updated",
                record.updated_at
                    ? sharedFormatDateTime(record.updated_at)
                    : null,
            ],
        ].forEach(([name, value]) => detail($("l55-record"), name, value));

        // Edit button
        if (role === 'farmer' && !readonly(record)) {
            const editLink = $("l55-edit-link");

            if (editLink) {
                editLink.href = `/livestock/${encodeURIComponent(record.id)}/edit`;

                editLink.hidden = false;
            }
        }

        // Calculator shortcuts for the selected existing livestock.
        const estimateLink = $("l55-estimate-link");
        if (estimateLink && ["farmer", "lgu_encoder"].includes(role)) {
            estimateLink.href = `/calculator?livestock_id=${encodeURIComponent(record.id)}`;
            estimateLink.hidden = false;
        }
        const valuationLink = $("l55-valuation-link");
        if (valuationLink && ["farmer", "lgu_encoder"].includes(role)) {
            valuationLink.href = `/calculator?livestock_id=${encodeURIComponent(record.id)}&tab=valuation`;
            valuationLink.hidden = false;
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
