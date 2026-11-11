import api from "../api";

const $ = (id) => document.getElementById(id);

const state = {
    page: 1,
    lastPage: 1,
    farmer: null,
    document: null,
    busy: false,
    previewUrl: null,
    requestId: 0,
};

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

function getError(error) {
    const data = error.response?.data;
    const firstError = Object.values(data?.errors ?? {})[0];

    return (
        (Array.isArray(firstError) ? firstError[0] : firstError) ||
        data?.message ||
        error.message ||
        "Something went wrong."
    );
}

function unwrap(response) {
    return response.data?.data ?? response.data;
}

function fullName(user) {
    return (
        [user?.first_name, user?.middle_name, user?.last_name, user?.suffix]
            .filter(Boolean)
            .join(" ") || "Unnamed farmer"
    );
}

function notify(message, type = "success") {
    const alert = $("fv-alert");
    alert.textContent = message;
    alert.dataset.type = type;
    alert.hidden = false;
}

function setBusy(busy) {
    state.busy = busy;

    for (const id of [
        "fv-refresh",
        "fv-prev",
        "fv-next",
        "fv-approve",
        "fv-reject",
    ]) {
        const button = $(id);
        if (button) button.disabled = busy;
    }
}

function renderRows(farmers) {
    const body = $("fv-table-body");

    if (!farmers.length) {
        body.innerHTML = `
            <tr>
                <td colspan="5" class="fv-empty">
                    No pending farmer verifications.
                </td>
            </tr>
        `;
        return;
    }

    body.innerHTML = farmers
        .map((farmer) => {
            const profile = farmer.farmer_profile ?? {};

            return `
            <tr>
                <td>
                    <div class="fv-person">
                        <strong>${escapeHtml(fullName(farmer))}</strong>
                        <small>${escapeHtml(farmer.email ?? "")}</small>
                    </div>
                </td>
                <td>${escapeHtml(profile.municipality?.name ?? "—")}</td>
                <td>${escapeHtml(profile.barangay?.name ?? "—")}</td>
                <td><span class="fv-status">Pending</span></td>
                <td>
                    <button
                        type="button"
                        class="fv-btn fv-btn-outline"
                        data-review="${escapeHtml(farmer.id)}"
                    >
                        Review
                    </button>
                </td>
            </tr>
        `;
        })
        .join("");
}

async function loadFarmers(page = 1) {
    if (state.busy) return;

    const requestId = ++state.requestId;

    $("fv-table-body").innerHTML = `
        <tr><td colspan="5" class="fv-empty">
            Loading applications...
        </td></tr>
    `;

    try {
        const response = await api.get("/lgu/farmer-verifications", {
            params: { page },
        });

        if (requestId !== state.requestId) return;

        const payload = unwrap(response);

        // Laravel paginator, optionally nested in the API response.
        const paginator = Array.isArray(payload?.data)
            ? payload
            : Array.isArray(payload?.data?.data)
              ? payload.data
              : payload;

        const farmers = Array.isArray(paginator?.data)
            ? paginator.data
            : Array.isArray(paginator)
              ? paginator
              : [];

        state.page = Number(paginator?.current_page ?? page);
        state.lastPage = Number(paginator?.last_page ?? 1);

        renderRows(farmers);

        $("fv-count").textContent =
            `${Number(paginator?.total ?? farmers.length)} pending applications`;

        $("fv-page-info").textContent =
            `Page ${state.page} of ${state.lastPage}`;

        $("fv-prev").disabled = state.page <= 1;
        $("fv-next").disabled = state.page >= state.lastPage;
    } catch (error) {
        $("fv-table-body").innerHTML = `
            <tr><td colspan="5" class="fv-empty">
                Unable to load applications.
            </td></tr>
        `;
        notify(getError(error), "error");
    }
}

function clearPreview() {
    if (state.previewUrl) {
        URL.revokeObjectURL(state.previewUrl);
        state.previewUrl = null;
    }
}

function documentRows(documents) {
    if (!documents.length) {
        return `<p class="fv-muted">No verification documents found.</p>`;
    }

    return documents
        .map(
            (doc) => `
        <div class="fv-document">
            <div>
                <strong>${escapeHtml(doc.document_type ?? "Document")}</strong>
                <small>Status: ${escapeHtml(doc.status ?? "Unknown")}</small>
                ${
                    doc.document_number
                        ? `<small>Number: ${escapeHtml(doc.document_number)}</small>`
                        : ""
                }
            </div>
            <button type="button"
                class="fv-btn fv-btn-outline"
                data-document="${escapeHtml(doc.id)}">
                View File
            </button>
        </div>
    `,
        )
        .join("");
}

function renderFarmer(farmer) {
    const profile = farmer.farmer_profile ?? {};
    const documents = farmer.verification_documents ?? [];

    $("fv-dialog-title").textContent = fullName(farmer);

    $("fv-review-body").innerHTML = `
        <div class="fv-detail-grid">
            <div>
                <span>Full Name</span>
                <strong>${escapeHtml(fullName(farmer))}</strong>
            </div>
            <div>
                <span>Email</span>
                <strong>${escapeHtml(farmer.email ?? "—")}</strong>
            </div>
            <div>
                <span>Municipality</span>
                <strong>${escapeHtml(profile.municipality?.name ?? "—")}</strong>
            </div>
            <div>
                <span>Barangay</span>
                <strong>${escapeHtml(profile.barangay?.name ?? "—")}</strong>
            </div>
            <div>
                <span>Verification Status</span>
                <strong>${escapeHtml(farmer.verification_status ?? "—")}</strong>
            </div>
        </div>

        <h3 class="fv-subheading">Submitted Documents</h3>
        <div class="fv-documents">
            ${documentRows(documents)}
        </div>

        <div id="fv-preview" class="fv-preview" hidden></div>

        <div id="fv-rejection-box" class="fv-rejection-box" hidden>
            <label for="fv-rejection-reason">
                Reason for Rejection
            </label>
            <textarea id="fv-rejection-reason"
                rows="3"
                placeholder="Explain why the document was rejected..."></textarea>
            <button type="button"
                id="fv-confirm-reject"
                class="fv-btn fv-btn-danger">
                Confirm Rejection
            </button>
        </div>
    `;

    const pending = documents.some((doc) => doc.status === "pending");

    $("fv-approve").hidden = !pending;
    $("fv-reject").hidden = !pending;
}

async function openReview(farmerId) {
    if (state.busy) return;

    closeReview();
    $("fv-modal").hidden = false;
    $("fv-review-body").textContent = "Loading farmer details...";
    $("fv-approve").hidden = true;
    $("fv-reject").hidden = true;

    try {
        const response = await api.get(
            `/lgu/farmer-verifications/${encodeURIComponent(farmerId)}`,
        );

        if ($("fv-modal").hidden) return;

        state.farmer = unwrap(response);
        state.document =
            (state.farmer.verification_documents ?? []).find(
                (doc) => doc.status === "pending",
            ) ?? null;

        renderFarmer(state.farmer);
    } catch (error) {
        notify(getError(error), "error");
        closeReview();
    }
}

function closeReview() {
    clearPreview();
    state.farmer = null;
    state.document = null;
    $("fv-modal").hidden = true;
}

async function viewDocument(documentId) {
    if (state.busy) return;

    const preview = $("fv-preview");
    if (!preview) return;

    clearPreview();
    preview.hidden = false;
    preview.textContent = "Loading private document...";

    try {
        const response = await api.get(
            `/lgu/verification-documents/${encodeURIComponent(documentId)}/file`,
            { responseType: "blob" },
        );

        if ($("fv-modal").hidden) return;

        const blob = response.data;
        const mime = blob.type || "application/octet-stream";
        state.previewUrl = URL.createObjectURL(blob);

        preview.replaceChildren();

        if (mime.startsWith("image/")) {
            const image = document.createElement("img");
            image.src = state.previewUrl;
            image.alt = "Farmer verification document";
            preview.appendChild(image);
        } else if (mime === "application/pdf") {
            const frame = document.createElement("iframe");
            frame.src = state.previewUrl;
            frame.title = "Farmer verification document";
            preview.appendChild(frame);
        } else {
            const link = document.createElement("a");
            link.href = state.previewUrl;
            link.download = "verification-document";
            link.textContent = "Open document";
            preview.appendChild(link);
        }
    } catch (error) {
        preview.textContent = getError(error);
    }
}

async function approveFarmer() {
    if (!state.document || state.busy) return;

    const confirmed = window.confirm(
        `Approve the verification of ${fullName(state.farmer)}?`,
    );

    if (!confirmed) return;

    setBusy(true);

    try {
        await api.post(
            `/lgu/verification-documents/${encodeURIComponent(state.document.id)}/approve`,
        );

        closeReview();
        notify("Farmer verification approved successfully.");
    } catch (error) {
        notify(getError(error), "error");
    } finally {
        setBusy(false);
    }

    await loadFarmers(state.page);
}

function showRejectForm() {
    const box = $("fv-rejection-box");
    if (!box || !state.document) return;

    box.hidden = false;
    $("fv-rejection-reason").focus();
}

async function rejectFarmer() {
    if (!state.document || state.busy) return;

    const reason = $("fv-rejection-reason")?.value.trim();

    if (!reason) {
        notify("Please provide a rejection reason.", "error");
        return;
    }

    setBusy(true);

    try {
        await api.post(
            `/lgu/verification-documents/${encodeURIComponent(state.document.id)}/reject`,
            { rejection_reason: reason },
        );

        closeReview();
        notify("Farmer verification rejected.");
    } catch (error) {
        notify(getError(error), "error");
    } finally {
        setBusy(false);
    }

    await loadFarmers(state.page);
}

$("fv-table-body")?.addEventListener("click", (event) => {
    const button = event.target.closest("[data-review]");
    if (button) openReview(button.dataset.review);
});

$("fv-review-body")?.addEventListener("click", (event) => {
    const button = event.target.closest("[data-document]");
    if (button) viewDocument(button.dataset.document);
});

$("fv-review-body")?.addEventListener("click", (event) => {
    if (event.target.closest("#fv-confirm-reject")) {
        rejectFarmer();
    }
});

$("fv-refresh")?.addEventListener("click", () => {
    loadFarmers(state.page);
});

$("fv-prev")?.addEventListener("click", () => {
    if (state.page > 1) loadFarmers(state.page - 1);
});

$("fv-next")?.addEventListener("click", () => {
    if (state.page < state.lastPage) {
        loadFarmers(state.page + 1);
    }
});

$("fv-approve")?.addEventListener("click", approveFarmer);
$("fv-reject")?.addEventListener("click", showRejectForm);
$("fv-close")?.addEventListener("click", closeReview);
$("fv-cancel")?.addEventListener("click", closeReview);

$("fv-modal")?.addEventListener("click", (event) => {
    if (event.target.closest("[data-fv-close]")) closeReview();
});

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !$("fv-modal").hidden) {
        closeReview();
    }
});

loadFarmers();
