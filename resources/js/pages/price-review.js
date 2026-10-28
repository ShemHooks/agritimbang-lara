import api from "../api";

const $ = (id) => document.getElementById(id);

const page = $("price-review-page");

const state = {
    priceId: page?.dataset.priceId ?? null,
    authorized: false,
    record: null,
    submitting: false,
};

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
    $("review-error").hidden = true;
    $("review-success").hidden = true;

    const element =
        type === "success" ? $("review-success") : $("review-error");

    element.textContent = message;
    element.hidden = false;
}

function clearNotice() {
    $("review-error").hidden = true;
    $("review-success").hidden = true;
    $("review-remarks-error").textContent = "";
}

function normalizeRoles(payload) {
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

function readable(value) {
    if (value == null || value === "") return "—";

    return String(value)
        .replace(/_/g, " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function money(value) {
    if (value == null || value === "") return "—";

    const number = Number(value);

    if (!Number.isFinite(number)) return "—";

    return new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
    }).format(number);
}

function date(value) {
    if (!value) return "—";

    const match = String(value).match(/^\d{4}-\d{2}-\d{2}/);

    return match ? match[0] : String(value);
}

function dateTime(value) {
    if (!value) return "—";

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return String(value);
    }

    return new Intl.DateTimeFormat("en-PH", {
        dateStyle: "medium",
        timeStyle: "short",
        timeZone: "Asia/Manila",
    }).format(parsed);
}

function personName(person) {
    if (!person) return "—";

    if (person.full_name) return person.full_name;
    if (person.name) return person.name;

    const name = [person.first_name, person.middle_name, person.last_name]
        .filter(Boolean)
        .join(" ");

    return name || "—";
}

function addField(container, label, value) {
    const field = document.createElement("div");
    field.className = "price-review-detail-field";

    const name = document.createElement("span");
    name.textContent = label;

    const content = document.createElement("strong");
    content.textContent = value ?? "—";

    field.append(name, content);
    container.append(field);
}

function renderFields(containerId, fields) {
    const container = $(containerId);
    container.replaceChildren();

    fields.forEach(([label, value]) => {
        addField(container, label, value);
    });
}

function renderTimeline(logs) {
    const container = $("review-validation-list");
    container.replaceChildren();

    if (!Array.isArray(logs) || !logs.length) {
        const empty = document.createElement("p");
        empty.className = "price-review-empty";
        empty.textContent = "No validation history has been recorded yet.";

        container.append(empty);
        return;
    }

    logs.forEach((log) => {
        const item = document.createElement("div");
        item.className = "price-review-log";

        const title = document.createElement("strong");
        title.textContent = readable(
            log.action ?? log.to_status ?? "Status update",
        );

        const meta = document.createElement("span");
        meta.textContent = `${personName(log.performer)} · ${dateTime(log.created_at)}`;

        item.append(title, meta);

        const note = log.remarks ?? log.reason;

        if (note) {
            const paragraph = document.createElement("p");
            paragraph.textContent = note;
            item.append(paragraph);
        }

        container.append(item);
    });
}

function renderRecord(record) {
    state.record = record;

    const status = $("review-status");
    status.textContent = readable(record.status);
    status.className = `price-review-status price-review-status-${record.status}`;

    renderFields("review-details", [
        ["Species", record.species?.name],
        ["Breed", record.breed?.name ?? "All breeds"],
        ["Municipality", record.municipality?.name],
        ["Barangay", record.barangay?.name ?? "Municipality-wide"],
        ["Sale Purpose", readable(record.sale_purpose)],
        ["Price / kg", money(record.price_per_kg)],
        ["Effective From", date(record.effective_from)],
        [
            "Effective To",
            record.effective_to ? date(record.effective_to) : "Open-ended",
        ],
        ["Source", record.source],
        ["Remarks", record.remarks],
    ]);

    renderFields("review-accountability", [
        ["Submitted By", personName(record.submitter)],
        ["Submitted At", dateTime(record.submitted_at)],
        ["Reviewed By", personName(record.reviewer)],
        ["Reviewed At", dateTime(record.reviewed_at)],
        ["Approved By", personName(record.approver)],
        ["Approved At", dateTime(record.approved_at)],
    ]);

    renderTimeline(record.validation_logs ?? record.validationLogs ?? []);

    $("review-decision-card").hidden = record.status !== "pending_review";

    $("review-content").hidden = false;
}

async function loadRecord() {
    const response = await api.get(
        `/lgu/price-validations/${encodeURIComponent(state.priceId)}`,
    );

    renderRecord(unwrap(response));
}

function setSubmitting(value) {
    state.submitting = value;

    $("review-approve").disabled = value;
    $("review-reject").disabled = value;

    $("review-approve").textContent = value
        ? "Processing..."
        : "Approve Reference";

    $("review-reject").textContent = value
        ? "Processing..."
        : "Reject Reference";
}

async function makeDecision(action) {
    if (!state.authorized || state.submitting) return;

    if (state.record?.status !== "pending_review") {
        notify("This price reference is no longer pending review.");
        return;
    }

    clearNotice();

    const remarks = $("review-remarks").value.trim();

    if (action === "reject" && !remarks) {
        $("review-remarks-error").textContent =
            "Please provide a reason for rejection.";

        return;
    }

    const confirmed = window.confirm(
        action === "approve"
            ? "Approve this livestock price reference as official?"
            : "Reject this price reference with the provided reason?",
    );

    if (!confirmed) return;

    setSubmitting(true);

    try {
        const endpoint = `/lgu/price-validations/${encodeURIComponent(state.priceId)}/${action}`;

        const payload = remarks ? { remarks } : {};

        await api.post(endpoint, payload);

        await loadRecord();

        $("review-remarks").value = "";

        notify(
            action === "approve"
                ? "Price reference approved successfully."
                : "Price reference rejected successfully.",
            "success",
        );
    } catch (error) {
        if (error.response?.status === 422) {
            const errors = error.response.data?.errors ?? {};

            $("review-remarks-error").textContent =
                errors.remarks?.join(" ") ?? errors.reason?.join(" ") ?? "";
        }

        notify(errorMessage(error));
    } finally {
        setSubmitting(false);
    }
}

function bindEvents() {
    $("review-approve").addEventListener("click", () => {
        makeDecision("approve");
    });

    $("review-reject").addEventListener("click", () => {
        makeDecision("reject");
    });
}

async function init() {
    if (!page) return;

    bindEvents();

    try {
        if (!state.priceId) {
            throw new Error("Missing price reference ID.");
        }

        const response = await api.get("/auth/me");
        const payload = unwrap(response);

        const roles = normalizeRoles(payload);

        if (!roles.includes("lgu_authority")) {
            throw new Error(
                "Only LGU Authorities can review price references.",
            );
        }

        state.authorized = true;

        await loadRecord();

        $("review-loading").hidden = true;
    } catch (error) {
        $("review-loading").hidden = true;
        notify(errorMessage(error));
    }
}

init();
