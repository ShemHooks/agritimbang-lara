import api from "../api";

const $ = (id) => document.getElementById(id);

const rows = $("verification-rows");
const alertBox = $("verification-alert");
const refreshButton = $("verification-refresh");
const searchInput = $("verification-search");
const statusInput = $("verification-status");

const dialog = $("verification-dialog");
const closeButton = $("verification-close");
const approveButton = $("verification-approve");
const rejectButton = $("verification-reject");
const dialogAlert = $("verification-dialog-alert");
const detailsContainer = $("verification-details");
const reasonInput = $("verification-reason");

const state = {
    transactions: [],
    selected: null,
    loading: false,
    processing: false,
    requestId: 0,
    detailRequestId: 0,
    page: 1,
    lastPage: 1,
    perPage: 50,
};

const REVIEWABLE_STATUS = "pending_lgu_verification";

const escapeHtml = (value) =>
    String(value ?? "").replace(
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

function formatMoney(value) {
    const number = Number(value);

    if (!Number.isFinite(number)) return "—";

    return new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(number);
}

function formatDate(value) {
    if (!value) return "—";

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return "—";

    return new Intl.DateTimeFormat("en-PH", {
        year: "numeric",
        month: "short",
        day: "numeric",
    }).format(date);
}

function statusLabel(value) {
    const labels = {
        draft: "Draft",
        pending: "Awaiting Buyer Confirmation",
        pending_lgu_verification: "Pending LGU Verification",
        verified: "Verified",
        rejected: "Rejected",
        disputed: "Disputed",
        cancelled: "Cancelled",
        farmer: "Farmer",
        lgu_encoder: "LGU Encoder",
        auction_house: "Auction House",
    };

    return (
        labels[value] ??
        String(value ?? "Unknown")
            .replaceAll("_", " ")
            .replace(/\b\w/g, (character) => character.toUpperCase())
    );
}

function farmerName(transaction) {
    const farmer = transaction?.farmer;
    const user = farmer?.user;

    if (user) {
        const fullName = [user.first_name, user.middle_name, user.last_name]
            .filter(Boolean)
            .join(" ");

        if (fullName) return fullName;
    }

    return farmer?.name || farmer?.farmer_code || "—";
}

function encoderName(transaction) {
    const encoder = transaction?.encoder;

    if (!encoder) return "—";

    return (
        [encoder.first_name, encoder.last_name].filter(Boolean).join(" ") || "—"
    );
}

function sourceLabel(transaction) {
    switch (transaction.source) {
        case "farmer":
            return "Farmer";
        case "lgu_encoder":
            return "LGU Encoder";
        case "auction_house":
            return "Auction House";
        default:
            return statusLabel(transaction.source);
    }
}

function apiError(error) {
    const errors = error.response?.data?.errors;

    if (errors) {
        const first = Object.values(errors).flat()[0];

        if (first) return String(first);
    }

    return (
        error.response?.data?.message ||
        error.message ||
        "An unexpected error occurred."
    );
}

function showAlert(message, type = "error") {
    if (!alertBox) return;

    alertBox.hidden = false;
    alertBox.textContent = message;
    alertBox.dataset.type = type;
}

function hideAlert() {
    if (alertBox) alertBox.hidden = true;
}

function showDialogAlert(message) {
    dialogAlert.textContent = message;
    dialogAlert.hidden = false;
}

function hideDialogAlert() {
    dialogAlert.hidden = true;
}

function setProcessing(processing) {
    state.processing = processing;

    approveButton.disabled =
        processing ||
        !state.selected ||
        state.selected.status !== REVIEWABLE_STATUS;

    rejectButton.disabled =
        processing ||
        !state.selected ||
        state.selected.status !== REVIEWABLE_STATUS;

    closeButton.disabled = processing;

    approveButton.textContent = processing ? "Processing..." : "Approve";

    rejectButton.textContent = processing ? "Processing..." : "Reject";
}

function updateStats() {
    const count = (status) =>
        state.transactions.filter(
            (transaction) => transaction.status === status,
        ).length;

    $("stat-pending").textContent = count("pending_lgu_verification");

    $("stat-verified").textContent = count("verified");
    $("stat-rejected").textContent = count("rejected");
    $("stat-disputed").textContent = count("disputed");
}

function renderTransactions() {
    const search = searchInput.value.trim().toLowerCase();
    const status = statusInput.value;

    const filtered = state.transactions.filter((item) => {
        const searchable = [
            item.id,
            farmerName(item),
            item.farmer?.farmer_code,
            item.buyer_name,
            item.source,
            encoderName(item),
        ]
            .join(" ")
            .toLowerCase();

        return (
            (!search || searchable.includes(search)) &&
            (!status || item.status === status)
        );
    });

    if (!filtered.length) {
        rows.innerHTML = `
            <tr>
                <td colspan="7" class="verification-empty">
                    No transactions found.
                </td>
            </tr>
        `;
        return;
    }

    rows.innerHTML = filtered
        .map(
            (item) => `
            <tr>
                <td title="${escapeHtml(item.id)}">
                    ${escapeHtml(item.id)}
                </td>

                <td>
                    <strong>${escapeHtml(farmerName(item))}</strong>
                    <div class="verification-muted">
                        ${escapeHtml(item.farmer?.farmer_code ?? "")}
                    </div>
                </td>

                <td>${escapeHtml(item.buyer_name ?? "—")}</td>

                <td>${escapeHtml(sourceLabel(item))}</td>

                <td>
                    ${escapeHtml(formatMoney(item.total_selling_price))}
                </td>

                <td>
                    <span class="verification-badge ${escapeHtml(item.status)}">
                        ${escapeHtml(statusLabel(item.status))}
                    </span>
                </td>

                <td>
                    <button
                        type="button"
                        class="verification-btn verification-outline"
                        data-review="${escapeHtml(item.id)}"
                    >
                        Review
                    </button>
                </td>
            </tr>
        `,
        )
        .join("");
}

function renderDetail(label, value) {
    return `
        <div class="verification-detail">
            <span>${escapeHtml(label)}</span>
            <strong>${escapeHtml(value ?? "—")}</strong>
        </div>
    `;
}

function renderLivestockItems(transaction) {
    const items = transaction.items ?? [];

    if (!items.length) {
        return `
            <p class="verification-muted">
                No livestock items available.
            </p>
        `;
    }

    return `
        <div class="verification-items">
            <h3>Livestock & Valuation Details</h3>

            ${items
                .map((item, index) => {
                    const livestock = item.livestock ?? {};

                    const species = livestock.species?.name ?? "Livestock";

                    const breed = livestock.breed?.name ?? "Unspecified";

                    return `
                        <div class="verification-item">
                            <div class="verification-item-header">
                                <strong>
                                    ${index + 1}.
                                    ${escapeHtml(species)}
                                    —
                                    ${escapeHtml(breed)}
                                </strong>
                            </div>

                            <div class="verification-item-grid">
                                ${renderDetail(
                                    "Livestock ID",
                                    item.livestock_id,
                                )}

                                ${renderDetail(
                                    "Weight Used",
                                    `${item.weight_used_kg ?? "—"} kg`,
                                )}

                                ${renderDetail(
                                    "Reference Value",
                                    formatMoney(item.reference_value),
                                )}

                                ${renderDetail(
                                    "Actual Selling Price",
                                    formatMoney(item.actual_selling_price),
                                )}

                                ${renderDetail(
                                    "Price Difference",
                                    formatMoney(item.price_difference),
                                )}

                                ${renderDetail(
                                    "Deviation",
                                    `${item.percentage_deviation ?? "—"}%`,
                                )}

                                ${renderDetail(
                                    "Valuation ID",
                                    item.valuation_id,
                                )}

                                ${renderDetail(
                                    "Livestock Status",
                                    statusLabel(livestock.status),
                                )}
                            </div>
                        </div>
                    `;
                })
                .join("")}
        </div>
    `;
}

function renderTransactionDetails(transaction) {
    const details = [
        ["Transaction ID", transaction.id],
        ["Farmer", farmerName(transaction)],
        ["Farmer Code", transaction.farmer?.farmer_code],
        ["Buyer", transaction.buyer_name],
        ["Buyer Phone", transaction.buyer_phone],
        ["Source", sourceLabel(transaction)],
        ["Encoded By", encoderName(transaction)],
        ["Municipality", transaction.municipality?.name],
        ["Barangay", transaction.barangay?.name],
        ["Transaction Date", formatDate(transaction.transaction_date)],
        ["Reference Value", formatMoney(transaction.total_reference_value)],
        ["Total Selling Price", formatMoney(transaction.total_selling_price)],
        ["Status", statusLabel(transaction.status)],
    ];

    detailsContainer.innerHTML = `
        <div class="verification-detail-grid">
            ${details
                .map(([label, value]) => renderDetail(label, value))
                .join("")}
        </div>

        ${renderLivestockItems(transaction)}

        <div class="verification-detail">
            <span>Transaction Remarks</span>
            <strong>${escapeHtml(transaction.remarks || "—")}</strong>
        </div>
    `;
}

async function openTransaction(transaction) {
    state.selected = null;
    const requestId = ++state.detailRequestId;

    $("verification-dialog-title").textContent =
        `Transaction ${transaction.id}`;

    detailsContainer.innerHTML = `
        <p class="verification-muted">
            Loading transaction details...
        </p>
    `;

    reasonInput.value = "";
    hideDialogAlert();

    setProcessing(false);

    dialog.showModal();

    try {
        const response = await api.get(
            `/lgu/transactions/${encodeURIComponent(transaction.id)}`,
        );

        if (requestId !== state.detailRequestId) return;

        const detail = response.data?.data;

        if (!detail || !detail.id) {
            throw new Error("Invalid transaction details returned.");
        }

        state.selected = detail;

        renderTransactionDetails(detail);
        setProcessing(false);

        if (detail.status !== REVIEWABLE_STATUS) {
            showDialogAlert(
                "This transaction is not awaiting LGU verification. Review only.",
            );
        }
    } catch (error) {
        if (requestId !== state.detailRequestId) return;

        detailsContainer.innerHTML = "";

        showDialogAlert(apiError(error));

        approveButton.disabled = true;
        rejectButton.disabled = true;
    }
}

async function loadTransactions() {
    const requestId = ++state.requestId;

    state.loading = true;
    refreshButton.disabled = true;
    hideAlert();

    rows.innerHTML = `
        <tr>
            <td colspan="7" class="verification-empty">
                Loading transactions...
            </td>
        </tr>
    `;

    try {
        const allTransactions = [];
        let page = 1;
        let lastPage = 1;

        do {
            const response = await api.get("/lgu/transactions", {
                params: {
                    page,
                    per_page: state.perPage,
                },
            });

            if (requestId !== state.requestId) return;

            const payload = response.data?.data;

            if (!payload || !Array.isArray(payload.data)) {
                throw new Error(
                    "The transaction API returned an unexpected response.",
                );
            }

            allTransactions.push(...payload.data);

            lastPage = Number(payload.last_page) || 1;
            page += 1;
        } while (page <= lastPage);

        if (requestId !== state.requestId) return;

        state.transactions = allTransactions;
        state.page = 1;
        state.lastPage = lastPage;

        renderTransactions();
        updateStats();
    } catch (error) {
        if (requestId !== state.requestId) return;

        state.transactions = [];

        rows.innerHTML = `
            <tr>
                <td colspan="7" class="verification-empty">
                    Unable to load transactions.
                </td>
            </tr>
        `;

        updateStats();
        showAlert(apiError(error));
    } finally {
        if (requestId === state.requestId) {
            state.loading = false;
            refreshButton.disabled = false;
        }
    }
}

async function processDecision(action) {
    const transaction = state.selected;

    if (
        !transaction ||
        state.processing ||
        transaction.status !== REVIEWABLE_STATUS
    ) {
        return;
    }

    const isApprove = action === "approve";
    const reason = reasonInput.value.trim();

    if (!isApprove && (reason.length < 5 || reason.length > 2000)) {
        showDialogAlert(
            "Enter a rejection reason between 5 and 2000 characters.",
        );
        return;
    }

    const confirmed = window.confirm(
        isApprove
            ? "Approve this transaction and mark its livestock as sold?"
            : "Reject this transaction and release its reserved livestock?",
    );

    if (!confirmed) return;

    hideDialogAlert();
    setProcessing(true);

    try {
        const url = `/lgu/transactions/${encodeURIComponent(
            transaction.id,
        )}/${action}`;

        const payload = isApprove ? {} : { reason };

        const response = await api.post(url, payload);

        state.selected = null;
        state.detailRequestId++;

        dialog.close();

        await loadTransactions();

        showAlert(
            response.data?.message ||
                (isApprove
                    ? "Transaction verified successfully."
                    : "Transaction rejected successfully."),
            "success",
        );
    } catch (error) {
        showDialogAlert(apiError(error));

        // Refresh details if the transaction changed on the server.
        if ([409, 422].includes(error.response?.status)) {
            const previous = state.selected;
            if (previous) {
                await openTransaction(previous);
            }
        }
    } finally {
        setProcessing(false);
    }
}

if (
    rows &&
    refreshButton &&
    searchInput &&
    statusInput &&
    dialog &&
    closeButton &&
    approveButton &&
    rejectButton
) {
    rows.addEventListener("click", (event) => {
        const button = event.target.closest("[data-review]");
        if (!button) return;

        const transaction = state.transactions.find(
            (item) => String(item.id) === button.dataset.review,
        );

        if (transaction) openTransaction(transaction);
    });

    searchInput.addEventListener("input", renderTransactions);

    statusInput.addEventListener("change", renderTransactions);

    refreshButton.addEventListener("click", loadTransactions);

    closeButton.addEventListener("click", () => {
        state.detailRequestId++;
        state.selected = null;
        dialog.close();
    });

    dialog.addEventListener("close", () => {
        state.detailRequestId++;
        state.selected = null;
    });

    approveButton.addEventListener("click", () => processDecision("approve"));

    rejectButton.addEventListener("click", () => processDecision("reject"));

    loadTransactions();
}
