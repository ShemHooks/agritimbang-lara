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

let transactions = [];
let selectedTransaction = null;

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

function showAlert(message) {
    alertBox.textContent = message;
    alertBox.hidden = false;
}

function formatMoney(value) {
    const number = Number(value);
    if (!Number.isFinite(number)) return "—";

    return new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
    }).format(number);
}

function statusLabel(status) {
    return String(status ?? "Unknown")
        .replaceAll("_", " ")
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

function updateStats() {
    const count = (status) =>
        transactions.filter((item) => item.status === status).length;

    $("stat-pending").textContent = count("pending_lgu_verification");
    $("stat-verified").textContent = count("verified");
    $("stat-rejected").textContent = count("rejected");
    $("stat-disputed").textContent = count("disputed");
}

function renderTransactions() {
    const search = searchInput.value.trim().toLowerCase();
    const status = statusInput.value;

    const filtered = transactions.filter((item) => {
        const searchable = [item.id, item.farmer?.name, item.buyer_name]
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
            <td>${escapeHtml(item.id)}</td>
            <td>${escapeHtml(item.farmer?.name ?? "—")}</td>
            <td>${escapeHtml(item.buyer_name ?? "—")}</td>
            <td>${escapeHtml(statusLabel(item.source))}</td>
            <td>${escapeHtml(formatMoney(item.total_selling_price))}</td>
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

function openTransaction(transaction) {
    selectedTransaction = transaction;

    $("verification-dialog-title").textContent =
        `Transaction ${transaction.id}`;

    const details = [
        ["Transaction ID", transaction.id],
        ["Farmer", transaction.farmer?.name],
        ["Buyer", transaction.buyer_name],
        ["Source", statusLabel(transaction.source)],
        ["Municipality", transaction.municipality?.name],
        ["Amount", formatMoney(transaction.total_selling_price)],
        ["Status", statusLabel(transaction.status)],
    ];

    $("verification-details").innerHTML = details
        .map(
            ([label, value]) => `
        <div class="verification-detail">
            <span>${escapeHtml(label)}</span>
            <strong>${escapeHtml(value ?? "—")}</strong>
        </div>
    `,
        )
        .join("");

    $("verification-reason").value = "";
    $("verification-dialog-alert").hidden = true;

    // A complete detail API is needed before enabling decisions.
    approveButton.disabled = true;
    rejectButton.disabled = true;

    dialog.showModal();
}

async function loadTransactions() {
    transactions = [];

    rows.innerHTML = `
        <tr>
            <td colspan="7" class="verification-empty">
                The transaction verification queue is not connected yet.
                A municipality-scoped GET endpoint is required to load
                transaction records and safely enable review actions.
            </td>
        </tr>
    `;

    ["stat-pending", "stat-verified", "stat-rejected", "stat-disputed"].forEach(
        (id) => {
            $(id).textContent = "—";
        },
    );
}

rows.addEventListener("click", (event) => {
    const button = event.target.closest("[data-review]");
    if (!button) return;

    const transaction = transactions.find(
        (item) => String(item.id) === button.dataset.review,
    );

    if (transaction) openTransaction(transaction);
});

searchInput.addEventListener("input", () => {
    if (transactions.length) renderTransactions();
});

statusInput.addEventListener("change", () => {
    if (transactions.length) renderTransactions();
});

refreshButton.addEventListener("click", loadTransactions);
closeButton.addEventListener("click", () => dialog.close());

approveButton.addEventListener("click", async () => {
    if (!selectedTransaction || approveButton.disabled) return;

    // Will be enabled after verified transaction detail integration.
    await api.post(
        `/lgu/transactions/${encodeURIComponent(selectedTransaction.id)}/approve`,
    );
});

rejectButton.addEventListener("click", async () => {
    if (!selectedTransaction || rejectButton.disabled) return;

    const reason = $("verification-reason").value.trim();
    if (reason.length < 5 || reason.length > 2000) {
        $("verification-dialog-alert").textContent =
            "Enter a rejection reason between 5 and 2000 characters.";
        $("verification-dialog-alert").hidden = false;
        return;
    }

    await api.post(
        `/lgu/transactions/${encodeURIComponent(selectedTransaction.id)}/reject`,
        { reason },
    );
});

loadTransactions();
