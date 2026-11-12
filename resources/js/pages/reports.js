import api from "../api";

const $ = (id) => document.getElementById(id);

// Set this to the URI registered for ReportController.
// Example only: "/reports/export"
const REPORT_ENDPOINT = "/analytics/reports/export";

const state = {
    exporting: false,
};

function getError(error) {
    const data = error?.response?.data;

    if (data?.errors) {
        const first = Object.values(data.errors)[0];
        if (Array.isArray(first)) return first[0];
        if (typeof first === "string") return first;
    }

    return data?.message ?? error?.message ?? "Unable to generate report.";
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

function alertMessage(type, message) {
    const element = $(type === "error" ? "rp-error" : "rp-success");
    if (!element) return;

    element.textContent = message;
    element.hidden = false;
}

function clearAlerts() {
    for (const id of ["rp-error", "rp-success"]) {
        const element = $(id);
        if (!element) continue;

        element.textContent = "";
        element.hidden = true;
    }
}

function fillSelect(select, records, placeholder) {
    select.replaceChildren(new Option(placeholder, ""));

    for (const record of records) {
        if (
            !record?.id ||
            record.is_active === false ||
            record.is_active === 0
        ) {
            continue;
        }

        select.add(new Option(record.name ?? record.id, record.id));
    }
}

async function loadSpecies() {
    const select = $("rp-species");
    select.disabled = true;

    try {
        const response = await api.get("/reference/species");
        const species = asArray(unwrap(response));

        fillSelect(select, species, "All species");
        select.disabled = false;
    } catch (error) {
        select.replaceChildren(new Option("Unable to load species", ""));
        alertMessage("error", getError(error));
    }
}

/*
 * Municipality selection is optional for users whose
 * municipality is automatically resolved by the backend.
 *
 * Do not populate this selector with unrestricted municipality
 * data unless your existing API and access rules permit it.
 */
async function loadMunicipalities() {
    const select = $("rp-municipality");

    // Replace this with your existing authorized municipality
    // endpoint if you have one.
    const endpoint = document.querySelector(
        'meta[name="reports-municipalities-endpoint"]',
    )?.content;

    if (!endpoint) {
        select.replaceChildren(new Option("Use my assigned municipality", ""));
        select.disabled = true;
        return;
    }

    select.disabled = true;

    try {
        const response = await api.get(endpoint);
        const municipalities = asArray(unwrap(response));

        fillSelect(select, municipalities, "Use my assigned municipality");

        select.disabled = false;
    } catch (error) {
        select.replaceChildren(new Option("Use my assigned municipality", ""));
        select.disabled = true;
        alertMessage("error", getError(error));
    }
}

function syncReportFields() {
    const type = $("rp-type").value;
    const isForecast = type === "forecast";

    $("rp-date-section").hidden = isForecast;
    $("rp-forecast-section").hidden = !isForecast;

    $("rp-purpose-field").hidden = !["prices", "forecast"].includes(type);

    $("rp-species").required = isForecast;

    $("rp-date-from").disabled = isForecast;
    $("rp-date-to").disabled = isForecast;
    $("rp-weeks").disabled = !isForecast;

    $("rp-purpose").disabled = !["prices", "forecast"].includes(type);
}

function buildFilters(form) {
    const formData = new FormData(form);
    const type = String(formData.get("type") ?? "");
    const format = String(formData.get("format") ?? "pdf");

    const filters = { type, format };

    const optional = ["municipality_id", "species_id", "sale_purpose"];

    if (type !== "forecast") {
        optional.push("date_from", "date_to");
    } else {
        filters.weeks = Number(formData.get("weeks") ?? 4);
    }

    for (const key of optional) {
        const value = String(formData.get(key) ?? "").trim();

        if (value) {
            filters[key] = value;
        }
    }

    if (type === "forecast" && !filters.species_id) {
        throw new Error("Please select a species for the forecast.");
    }

    if (
        filters.date_from &&
        filters.date_to &&
        filters.date_to < filters.date_from
    ) {
        throw new Error("End date cannot be earlier than start date.");
    }

    return filters;
}

async function extractBlobError(error) {
    const blob = error?.response?.data;

    if (!(blob instanceof Blob)) {
        return getError(error);
    }

    try {
        const text = await blob.text();
        const json = JSON.parse(text);

        const first = Object.values(json.errors ?? {})[0];

        if (Array.isArray(first)) {
            return first[0] ?? json.message ?? "Validation failed.";
        }

        if (typeof first === "string") {
            return first;
        }

        return json.message ?? "Unable to generate report.";
    } catch {
        return getError(error);
    }
}

function getFilename(headers, filters) {
    const disposition = headers?.["content-disposition"] ?? "";

    const encoded = disposition.match(/filename\*=UTF-8''([^;]+)/i);

    if (encoded) {
        try {
            return decodeURIComponent(encoded[1].trim());
        } catch {
            // Fall back to the standard filename parameter.
        }
    }

    const regular = disposition.match(
        /filename\s*=\s*"([^"]+)"|filename\s*=\s*([^;]+)/i,
    );

    if (regular) {
        return (regular[1] ?? regular[2]).trim();
    }

    return `agritimbang_${filters.type}.${filters.format}`;
}

function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");

    link.href = url;
    link.download = filename;
    link.style.display = "none";

    document.body.appendChild(link);
    link.click();
    link.remove();

    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

async function submitReport(event) {
    event.preventDefault();

    if (state.exporting) return;

    clearAlerts();

    const form = $("rp-form");
    const button = $("rp-submit");

    if (!form.reportValidity()) return;

    let filters;

    try {
        filters = buildFilters(form);
    } catch (error) {
        alertMessage("error", getError(error));
        return;
    }

    state.exporting = true;
    button.disabled = true;
    button.textContent = "Generating report...";

    try {
        const response = await api.get(REPORT_ENDPOINT, {
            params: filters,
            responseType: "blob",
            headers: {
                Accept:
                    filters.format === "pdf"
                        ? "application/pdf"
                        : "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            },
        });

        const contentType = response.headers?.["content-type"] ?? "";

        if (
            contentType.includes("application/json") ||
            contentType.includes("text/html")
        ) {
            throw new Error(
                "The server returned an unexpected response instead of a report.",
            );
        }

        const filename = getFilename(response.headers, filters);

        downloadBlob(response.data, filename);

        alertMessage(
            "success",
            `${filters.type.charAt(0).toUpperCase() + filters.type.slice(1)} report generated successfully.`,
        );
    } catch (error) {
        const message = await extractBlobError(error);
        alertMessage("error", message);
    } finally {
        state.exporting = false;
        button.disabled = false;
        button.textContent = "Generate & Download";
    }
}

function init() {
    const form = $("rp-form");
    if (!form) return;

    $("rp-type").addEventListener("change", syncReportFields);

    form.addEventListener("submit", submitReport);

    form.addEventListener("reset", () => {
        setTimeout(() => {
            clearAlerts();
            syncReportFields();
        }, 0);
    });

    syncReportFields();
    loadSpecies();
    loadMunicipalities();
}

document.addEventListener("DOMContentLoaded", init);
