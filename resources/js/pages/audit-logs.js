import api from "../api";

const $ = (id) => document.getElementById(id);

const rows = $("audit-rows");
const alertBox = $("audit-alert");

let currentPage = 1;
let lastPage = 1;
let requestSequence = 0;

function escapeHtml(value) {
    return String(value ?? "").replace(
        /[&<>"']/g,
        (char) =>
            ({
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': "&quot;",
                "'": "&#39;",
            })[char],
    );
}

function formatDate(value) {
    if (!value) return "—";

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return new Intl.DateTimeFormat("en-PH", {
        dateStyle: "medium",
        timeStyle: "short",
        timeZone: "Asia/Manila",
    }).format(date);
}

function displayName(user) {
    if (!user) return "System / Unknown";

    const name = [user.first_name, user.last_name].filter(Boolean).join(" ");

    return name || "Unknown User";
}

function getFilters() {
    const fields = {
        user_id: $("audit-user").value.trim(),
        action: $("audit-action").value.trim(),
        entity_type: $("audit-entity-type").value.trim(),
        entity_id: $("audit-entity-id").value.trim(),
        from: $("audit-from").value,
        to: $("audit-to").value,
        per_page: $("audit-per-page").value,
    };

    return Object.fromEntries(
        Object.entries(fields).filter(([, value]) => value !== ""),
    );
}

function showError(error) {
    const data = error.response?.data;

    const message = data?.errors
        ? Object.values(data.errors).flat().join(" ")
        : data?.message || error.message || "Unable to load audit logs.";

    alertBox.textContent = message;
    alertBox.hidden = false;
}

function renderRows(items) {
    if (!items.length) {
        rows.innerHTML = `
            <tr>
                <td colspan="6">
                    No audit logs found.
                </td>
            </tr>
        `;
        return;
    }

    rows.innerHTML = items
        .map(
            (item) => `
        <tr>
            <td>
                ${escapeHtml(formatDate(item.created_at))}
            </td>

            <td>
                ${escapeHtml(displayName(item.user))}
            </td>

            <td>
                <span class="audit-badge">
                    ${escapeHtml(item.action ?? "Unknown")}
                </span>
            </td>

            <td>
                ${escapeHtml(item.entity_type ?? "—")}
            </td>

            <td>
                ${escapeHtml(item.entity_id ?? "—")}
            </td>

            <td>
                ${escapeHtml(item.municipality_id ?? "—")}
            </td>
        </tr>
    `,
        )
        .join("");
}

function updatePagination() {
    $("audit-page-label").textContent = `Page ${currentPage} of ${lastPage}`;

    $("audit-previous").disabled = currentPage <= 1;

    $("audit-next").disabled = currentPage >= lastPage;
}

async function loadAuditLogs(page = 1) {
    const requestId = ++requestSequence;

    alertBox.hidden = true;
    alertBox.textContent = "";

    rows.innerHTML = `
        <tr>
            <td colspan="6">Loading audit logs...</td>
        </tr>
    `;

    try {
        const response = await api.get("/audit-logs", {
            params: {
                ...getFilters(),
                page,
            },
        });

        if (requestId !== requestSequence) return;

        // Controller response:
        // { data: { data: [], current_page, last_page } }
        const paginator = response.data?.data;

        if (!paginator || !Array.isArray(paginator.data)) {
            throw new Error("Unexpected audit log response format.");
        }

        currentPage = Number(paginator.current_page ?? 1);

        lastPage = Math.max(1, Number(paginator.last_page ?? 1));

        renderRows(paginator.data);
        updatePagination();
    } catch (error) {
        if (requestId !== requestSequence) return;

        rows.innerHTML = `
            <tr>
                <td colspan="6">
                    Unable to load audit logs.
                </td>
            </tr>
        `;

        showError(error);
    }
}

$("audit-filter-form").addEventListener("submit", (event) => {
    event.preventDefault();

    const from = $("audit-from").value;
    const to = $("audit-to").value;

    if (from && to && from > to) {
        alertBox.textContent =
            "The end date cannot be earlier than the start date.";
        alertBox.hidden = false;
        return;
    }

    loadAuditLogs(1);
});

$("audit-reset").addEventListener("click", () => {
    $("audit-filter-form").reset();
    loadAuditLogs(1);
});

$("audit-refresh").addEventListener("click", () => loadAuditLogs(currentPage));

$("audit-previous").addEventListener("click", () => {
    if (currentPage > 1) {
        loadAuditLogs(currentPage - 1);
    }
});

$("audit-next").addEventListener("click", () => {
    if (currentPage < lastPage) {
        loadAuditLogs(currentPage + 1);
    }
});

loadAuditLogs();
