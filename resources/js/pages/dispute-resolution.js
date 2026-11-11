import api from "../api";

const $ = (id) => document.getElementById(id);

const rows = $("dispute-rows");
const dialog = $("dispute-dialog");
const alertBox = $("dispute-alert");
const dialogAlert = $("dispute-dialog-alert");
const reviewButton = $("dispute-start-review");
const resolutionForm = $("dispute-resolution-form");
const resolveButton = $("dispute-resolve");

let disputes = [];
let selectedDispute = null;
let currentPage = 1;
let lastPage = 1;
let listRequest = 0;
let detailRequest = 0;
let busy = false;

const escapeHtml = (value) =>
    String(value ?? "").replace(
        /[&<>"']/g,
        (character) =>
            ({
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': "&quot;",
                "'": "&#39;",
            })[character],
    );

const label = (value) =>
    String(value ?? "Unknown")
        .replaceAll("_", " ")
        .replace(/\b\w/g, (character) => character.toUpperCase());

const formatDate = (value) => {
    if (!value) return "—";

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? String(value)
        : new Intl.DateTimeFormat("en-PH", {
              dateStyle: "medium",
              timeStyle: "short",
              timeZone: "Asia/Manila",
          }).format(date);
};

const errorMessage = (error) => {
    const response = error.response?.data;

    return response?.errors
        ? Object.values(response.errors).flat().join(" ")
        : response?.message || error.message || "Something went wrong.";
};

const showAlert = (element, message) => {
    if (!element) return;

    element.textContent = message;
    element.hidden = false;
};

const clearAlert = (element) => {
    if (!element) return;

    element.textContent = "";
    element.hidden = true;
};

function normalizeList(payload) {
    const body = payload?.data ?? payload;
    const collection = body?.data ?? body;

    if (!Array.isArray(collection)) {
        throw new Error("Unexpected dispute list response format.");
    }

    const pagination = Array.isArray(body) ? payload : body;

    return {
        items: collection,
        currentPage: Number(pagination?.current_page ?? 1),
        lastPage: Number(pagination?.last_page ?? 1),
        stats:
            payload?.stats ??
            body?.stats ??
            payload?.counts ??
            body?.counts ??
            null,
    };
}

function updateStats(stats = null) {
    const count = (status) =>
        disputes.filter((item) => item.status === status).length;

    const values = {
        "dispute-open": stats?.open ?? count("open"),

        "dispute-reviewing":
            stats?.under_review ?? stats?.reviewing ?? count("under_review"),

        "dispute-resolved": stats?.resolved ?? count("resolved"),

        "dispute-dismissed":
            stats?.dismissed ??
            disputes.filter((item) => item.resolution === "dismissed").length,
    };

    Object.entries(values).forEach(([id, value]) => {
        if ($(id)) {
            $(id).textContent = value;
        }
    });

    // Without API-wide stats, these counts
    // reflect only the currently loaded page.
}

function renderDisputes() {
    const search = $("dispute-search")?.value.trim().toLowerCase() ?? "";

    const status = $("dispute-status")?.value ?? "";

    const filtered = disputes.filter((item) => {
        const searchable = [
            item.id,
            item.transaction_id,
            item.reason,
            item.resolution,
        ]
            .join(" ")
            .toLowerCase();

        const statusMatch =
            !status ||
            (status === "dismissed"
                ? item.resolution === "dismissed"
                : item.status === status);

        return (!search || searchable.includes(search)) && statusMatch;
    });

    if (!filtered.length) {
        rows.innerHTML = `
            <tr>
                <td colspan="6" class="dispute-empty">
                    No disputes found.
                </td>
            </tr>
        `;
        return;
    }

    rows.innerHTML = filtered
        .map(
            (item) => `
            <tr>
                <td>${escapeHtml(item.id)}</td>

                <td>
                    ${escapeHtml(item.transaction_id ?? "—")}
                </td>

                <td>
                    ${escapeHtml(item.reason ?? "—")}
                </td>

                <td>
                    <span class="dispute-badge ${escapeHtml(
                        item.resolution === "dismissed"
                            ? "dismissed"
                            : item.status,
                    )}">
                        ${escapeHtml(
                            item.resolution === "dismissed"
                                ? "Dismissed"
                                : label(item.status),
                        )}
                    </span>
                </td>

                <td>
                    ${escapeHtml(formatDate(item.created_at))}
                </td>

                <td>
                    <button
                        type="button"
                        class="dispute-btn dispute-outline"
                        data-dispute="${escapeHtml(item.id)}"
                    >
                        ${item.status === "resolved" ? "View" : "Review"}
                    </button>
                </td>
            </tr>
        `,
        )
        .join("");
}

function updatePagination() {
    const pageLabel = $("dispute-page-label");
    const previous = $("dispute-previous");
    const next = $("dispute-next");

    if (pageLabel) {
        pageLabel.textContent =
            lastPage > 1 ? `Page ${currentPage} of ${lastPage}` : "";
    }

    if (previous) {
        previous.hidden = currentPage <= 1;
        previous.disabled = currentPage <= 1;
    }

    if (next) {
        next.hidden = currentPage >= lastPage;
        next.disabled = currentPage >= lastPage;
    }
}

async function loadDisputes(page = 1) {
    const request = ++listRequest;

    clearAlert(alertBox);

    rows.innerHTML = `
        <tr>
            <td colspan="6" class="dispute-empty">
                Loading disputes…
            </td>
        </tr>
    `;

    try {
        const response = await api.get("/lgu/transaction-disputes", {
            params: { page },
        });

        if (request !== listRequest) return;

        const result = normalizeList(response.data);

        disputes = result.items;
        currentPage = result.currentPage;
        lastPage = result.lastPage;

        updateStats(result.stats);
        renderDisputes();
        updatePagination();
    } catch (error) {
        if (request !== listRequest) return;

        disputes = [];

        [
            "dispute-open",
            "dispute-reviewing",
            "dispute-resolved",
            "dispute-dismissed",
        ].forEach((id) => {
            if ($(id)) {
                $(id).textContent = "—";
            }
        });

        rows.innerHTML = `
            <tr>
                <td colspan="6" class="dispute-empty">
                    Unable to load disputes.
                </td>
            </tr>
        `;

        showAlert(alertBox, errorMessage(error));
    }
}

function detailRow(name, value) {
    return `
        <div class="dispute-detail">
            <span>${escapeHtml(name)}</span>
            <strong>
                ${escapeHtml(value ?? "—")}
            </strong>
        </div>
    `;
}

function updateResolutionWarning() {
    const value = $("dispute-resolution")?.value;

    const warning =
        $("dispute-resolution-warning") ??
        document.querySelector("[data-resolution-warning]");

    if (!warning) return;

    if (value === "upheld") {
        warning.textContent =
            "Upholding this dispute confirms the complaint. Review the case evidence before submitting.";

        warning.hidden = false;
    } else if (value === "dismissed") {
        warning.textContent =
            "Dismissing this dispute rejects the complaint. Provide clear reasons in the resolution notes.";

        warning.hidden = false;
    } else {
        warning.textContent = "";
        warning.hidden = true;
    }
}

async function openDispute(id) {
    const request = ++detailRequest;

    selectedDispute = null;
    clearAlert(dialogAlert);

    $("dispute-dialog-title").textContent = "Loading dispute…";

    $("dispute-details").textContent = "Loading case details…";

    reviewButton.hidden = true;
    resolutionForm.hidden = true;

    if (!dialog.open) {
        dialog.showModal();
    }

    try {
        const response = await api.get(
            `/lgu/transaction-disputes/${encodeURIComponent(id)}`,
        );

        if (request !== detailRequest || !dialog.open) {
            return;
        }

        const dispute = response.data?.data ?? response.data;

        if (!dispute?.id) {
            throw new Error("Invalid dispute detail response.");
        }

        selectedDispute = dispute;

        $("dispute-dialog-title").textContent = `Dispute ${dispute.id}`;

        const fields = [
            ["Dispute ID", dispute.id],
            ["Transaction ID", dispute.transaction_id],
            ["Status", label(dispute.status)],
            ["Reason", dispute.reason],
            ["Submitted", formatDate(dispute.created_at)],
            [
                "Resolution",
                dispute.resolution ? label(dispute.resolution) : "—",
            ],
            ["Resolution Notes", dispute.resolution_notes],
        ];

        $("dispute-details").innerHTML = fields
            .map(([name, value]) => detailRow(name, value))
            .join("");

        reviewButton.hidden = dispute.status !== "open";

        resolutionForm.hidden = dispute.status !== "under_review";

        $("dispute-resolution").value = "";
        $("dispute-resolution-notes").value = "";

        updateResolutionWarning();
    } catch (error) {
        if (request !== detailRequest) return;

        showAlert(dialogAlert, errorMessage(error));

        $("dispute-details").textContent = "Could not load this dispute.";
    }
}

async function startReview() {
    if (busy || !selectedDispute || selectedDispute.status !== "open") {
        return;
    }

    busy = true;
    reviewButton.disabled = true;

    clearAlert(dialogAlert);

    const id = selectedDispute.id;

    try {
        await api.post(
            `/lgu/transaction-disputes/${encodeURIComponent(id)}/review`,
        );

        await loadDisputes(currentPage);
        await openDispute(id);
    } catch (error) {
        showAlert(dialogAlert, errorMessage(error));
    } finally {
        busy = false;
        reviewButton.disabled = false;
    }
}

async function resolveDispute(event) {
    event.preventDefault();

    if (busy || !selectedDispute || selectedDispute.status !== "under_review") {
        return;
    }

    const resolution = $("dispute-resolution").value;

    const resolution_notes = $("dispute-resolution-notes").value.trim();

    if (!["upheld", "dismissed"].includes(resolution)) {
        showAlert(dialogAlert, "Select a valid resolution.");
        return;
    }

    if (resolution_notes.length < 10 || resolution_notes.length > 2000) {
        showAlert(
            dialogAlert,
            "Resolution notes must contain 10 to 2000 characters.",
        );
        return;
    }

    const confirmed = window.confirm(
        `Submit the "${label(resolution)}" resolution? This decision may be final.`,
    );

    if (!confirmed) return;

    busy = true;
    resolveButton.disabled = true;

    clearAlert(dialogAlert);

    try {
        await api.post(
            `/lgu/transaction-disputes/${encodeURIComponent(
                selectedDispute.id,
            )}/resolve`,
            {
                resolution,
                resolution_notes,
            },
        );

        dialog.close();
        selectedDispute = null;

        await loadDisputes(currentPage);
    } catch (error) {
        showAlert(dialogAlert, errorMessage(error));
    } finally {
        busy = false;
        resolveButton.disabled = false;
    }
}

// Event listeners

rows.addEventListener("click", (event) => {
    const button = event.target.closest("[data-dispute]");

    if (button) {
        openDispute(button.dataset.dispute);
    }
});

$("dispute-search")?.addEventListener("input", renderDisputes);

$("dispute-status")?.addEventListener("change", renderDisputes);

$("dispute-refresh")?.addEventListener("click", () =>
    loadDisputes(currentPage),
);

$("dispute-previous")?.addEventListener("click", () => {
    if (currentPage > 1) {
        loadDisputes(currentPage - 1);
    }
});

$("dispute-next")?.addEventListener("click", () => {
    if (currentPage < lastPage) {
        loadDisputes(currentPage + 1);
    }
});

$("dispute-close")?.addEventListener("click", () => dialog.close());

dialog.addEventListener("close", () => {
    ++detailRequest;
    selectedDispute = null;
});

$("dispute-resolution")?.addEventListener("change", updateResolutionWarning);

reviewButton.addEventListener("click", startReview);

resolutionForm.addEventListener("submit", resolveDispute);

loadDisputes();
