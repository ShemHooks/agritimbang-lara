import api from "../api";

const $ = (id) => document.getElementById(id);

const state = {
    page: 1,
    search: "",
    status: "",
    lastPage: 1,
    encoders: [],
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
    const data = error?.response?.data;
    const first = Object.values(data?.errors ?? {})[0];

    if (Array.isArray(first) && first.length) {
        return first[0];
    }

    if (typeof first === "string") {
        return first;
    }

    return data?.message || error?.message || "Something went wrong.";
}

function unwrap(response) {
    return response?.data?.data ?? response?.data;
}

function userName(user) {
    return (
        [user.first_name, user.middle_name, user.last_name]
            .filter(Boolean)
            .join(" ") || "Unnamed Encoder"
    );
}

function officerProfile(user) {
    return user.lgu_officer_profile ?? user.lguOfficerProfile ?? {};
}

function statusBadge(status) {
    const safeStatus = ["active", "inactive", "suspended"].includes(status)
        ? status
        : "unknown";

    return `
        <span class="le-badge le-badge-${safeStatus}">
            ${escapeHtml(safeStatus)}
        </span>
    `;
}

function showError(message) {
    const element = $("le-error");
    element.textContent = message;
    element.hidden = false;
}

function clearError() {
    $("le-error").textContent = "";
    $("le-error").hidden = true;
}

function setLoading(loading) {
    $("le-loading").hidden = !loading;
}

function openModal(title, content) {
    $("le-modal-title").textContent = title;
    $("le-modal-content").innerHTML = content;
    $("le-modal").hidden = false;
    document.body.classList.add("le-modal-open");
}

function closeModal() {
    $("le-modal").hidden = true;
    $("le-modal-content").innerHTML = "";
    document.body.classList.remove("le-modal-open");
}

function formField(name, label, type = "text", required = false) {
    return `
        <label class="le-field">
            <span>${label}${required ? " *" : ""}</span>
            <input
                type="${type}"
                name="${name}"
                ${required ? "required" : ""}
                ${type === "email" ? 'autocomplete="email"' : ""}
            >
        </label>
    `;
}

function normalizeCollection(payload) {
    // Standard Laravel paginator:
    // { current_page, last_page, total, data: [...] }
    if (Array.isArray(payload?.data)) {
        return {
            items: payload.data,
            currentPage: Number(payload.current_page ?? state.page),
            lastPage: Number(payload.last_page ?? 1),
            total: Number(payload.total ?? payload.data.length),
            paginated: true,
        };
    }

    // Laravel ResourceCollection:
    // { data: [...], meta: { current_page, last_page, total } }
    if (Array.isArray(payload?.data?.data)) {
        return {
            items: payload.data.data,
            currentPage: Number(
                payload.data.meta?.current_page ??
                    payload.data.current_page ??
                    state.page,
            ),
            lastPage: Number(
                payload.data.meta?.last_page ?? payload.data.last_page ?? 1,
            ),
            total: Number(
                payload.data.meta?.total ??
                    payload.data.total ??
                    payload.data.data.length,
            ),
            paginated: true,
        };
    }

    // Unpaginated array
    if (Array.isArray(payload)) {
        return {
            items: payload,
            currentPage: 1,
            lastPage: 1,
            total: payload.length,
            paginated: false,
        };
    }

    return {
        items: [],
        currentPage: 1,
        lastPage: 1,
        total: 0,
        paginated: false,
    };
}

function renderTable() {
    const tbody = $("le-table-body");

    if (!state.encoders.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="le-empty">
                    No LGU Encoders found.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = state.encoders
        .map((encoder) => {
            const profile = officerProfile(encoder);

            const station = profile.station || "—";
            const designation = profile.designation || "—";

            return `
            <tr>
                <td>
                    <div class="le-user">
                        <div class="le-avatar">
                            ${escapeHtml(
                                (encoder.first_name || "E")
                                    .charAt(0)
                                    .toUpperCase(),
                            )}
                        </div>
                        <div>
                            <strong>${escapeHtml(userName(encoder))}</strong>
                            <small>LGU Encoder</small>
                        </div>
                    </div>
                </td>

                <td>
                    <div class="le-cell-stack">
                        <span>${escapeHtml(encoder.email)}</span>
                        <small>${escapeHtml(encoder.phone_number || "—")}</small>
                    </div>
                </td>

                <td>
                    <div class="le-cell-stack">
                        <span>${escapeHtml(station)}</span>
                        <small>${escapeHtml(designation)}</small>
                    </div>
                </td>

                <td>${statusBadge(encoder.status)}</td>

                <td>
                    <button
                        type="button"
                        class="le-btn le-btn-outline le-btn-small"
                        data-action="status"
                        data-id="${escapeHtml(encoder.id)}"
                    >
                        Change Status
                    </button>
                </td>
            </tr>
        `;
        })
        .join("");
}

function renderStats(collection) {
    // For paginated responses, the current page cannot provide
    // accurate municipality-wide status totals.
    $("le-total").textContent = collection.paginated
        ? collection.total
        : state.encoders.length;

    const canCalculateAllStatuses =
        !collection.paginated && !state.search && !state.status;

    for (const status of ["active", "inactive", "suspended"]) {
        $(`le-${status}`).textContent = canCalculateAllStatuses
            ? state.encoders.filter((user) => user.status === status).length
            : "—";
    }
}

function renderPagination(collection) {
    state.page = collection.currentPage;
    state.lastPage = collection.lastPage;

    $("le-page-label").textContent = `Page ${state.page} of ${state.lastPage}`;

    $("le-result-count").textContent = `${collection.total} encoder(s) found`;

    $("le-prev").disabled = state.page <= 1;
    $("le-next").disabled = state.page >= state.lastPage;
}

async function loadEncoders() {
    const requestId = ++state.requestId;

    setLoading(true);
    clearError();

    try {
        const response = await api.get("/lgu/encoders", {
            params: {
                page: state.page,
                ...(state.search ? { search: state.search } : {}),
                ...(state.status ? { status: state.status } : {}),
            },
        });

        if (requestId !== state.requestId) return;

        const collection = normalizeCollection(unwrap(response));

        state.encoders = collection.items;

        renderTable();
        renderStats(collection);
        renderPagination(collection);
    } catch (error) {
        if (requestId !== state.requestId) return;

        state.encoders = [];
        renderTable();
        showError(getError(error));
    } finally {
        if (requestId === state.requestId) {
            setLoading(false);
        }
    }
}

function createEncoder() {
    openModal(
        "Create LGU Encoder",
        `
        <p class="le-modal-description">
            Create an encoder account for your assigned municipality.
        </p>

        <form id="le-create-form" class="le-form">
            <div class="le-form-grid">
                ${formField("first_name", "First Name", "text", true)}
                ${formField("last_name", "Last Name", "text", true)}
                ${formField("middle_name", "Middle Name")}
                ${formField("email", "Email Address", "email", true)}
                ${formField("phone_number", "Phone Number", "tel")}
                ${formField("station", "Station / Office")}
                ${formField("designation", "Designation")}
            </div>

            <div class="le-notice">
                <strong>Automatic Account Credentials</strong>
                <p>
                    Laravel will generate a temporary password and
                    attempt to email the login credentials to the
                    encoder. The encoder should change their
                    password after signing in.
                </p>
            </div>

            <p id="le-form-error" class="le-form-error" role="alert" hidden></p>

            <div class="le-form-actions">
                <button
                    type="button"
                    class="le-btn le-btn-outline"
                    data-close-modal
                >
                    Cancel
                </button>
                <button type="submit" class="le-btn le-btn-primary">
                    Create Encoder
                </button>
            </div>
        </form>
        `,
    );

    const form = $("le-create-form");
    const submitButton = form.querySelector('[type="submit"]');
    const errorElement = $("le-form-error");

    form.addEventListener("submit", async (event) => {
        event.preventDefault();

        if (submitButton.disabled) return;

        const formData = new FormData(form);

        const data = {
            first_name: String(formData.get("first_name") ?? "").trim(),
            last_name: String(formData.get("last_name") ?? "").trim(),
            middle_name:
                String(formData.get("middle_name") ?? "").trim() || null,
            email: String(formData.get("email") ?? "")
                .trim()
                .toLowerCase(),
            phone_number:
                String(formData.get("phone_number") ?? "").trim() || null,
            station: String(formData.get("station") ?? "").trim() || null,
            designation:
                String(formData.get("designation") ?? "").trim() || null,
        };

        errorElement.hidden = true;
        errorElement.textContent = "";
        submitButton.disabled = true;
        submitButton.textContent = "Creating Encoder...";

        try {
            const response = await api.post("/lgu/encoders", data);
            const result = unwrap(response);

            closeModal();

            state.page = 1;
            state.search = "";
            state.status = "";

            $("le-search").value = "";
            $("le-status").value = "";

            await loadEncoders();

            if (result?.email_delivery_status === "sent") {
                alert(
                    "Encoder account created successfully. " +
                        "Credentials email sent.",
                );
            } else if (result?.email_delivery_status === "failed") {
                alert(
                    "Encoder account created, but the credentials " +
                        "email could not be delivered.",
                );
            } else {
                alert(
                    "Encoder account created successfully. " +
                        "Email delivery has not been confirmed.",
                );
            }
        } catch (error) {
            errorElement.textContent = getError(error);
            errorElement.hidden = false;
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = "Create Encoder";
        }
    });
}

function changeStatus(id) {
    const encoder = state.encoders.find(
        (item) => String(item.id) === String(id),
    );

    if (!encoder) return;

    openModal(
        "Change Encoder Status",
        `
        <p class="le-modal-description">
            Update the account access for
            <strong>${escapeHtml(userName(encoder))}</strong>.
        </p>

        <form id="le-status-form" class="le-form">
            <label class="le-field">
                <span>Account Status</span>
                <select name="status" required>
                    ${["active", "inactive", "suspended"]
                        .map(
                            (status) => `
                        <option
                            value="${status}"
                            ${encoder.status === status ? "selected" : ""}
                        >
                            ${status.charAt(0).toUpperCase() + status.slice(1)}
                        </option>
                    `,
                        )
                        .join("")}
                </select>
            </label>

            <p id="le-status-error" class="le-form-error" role="alert" hidden></p>

            <div class="le-form-actions">
                <button
                    type="button"
                    class="le-btn le-btn-outline"
                    data-close-modal
                >
                    Cancel
                </button>
                <button type="submit" class="le-btn le-btn-primary">
                    Save Status
                </button>
            </div>
        </form>
        `,
    );

    const form = $("le-status-form");
    const submitButton = form.querySelector('[type="submit"]');
    const errorElement = $("le-status-error");

    form.addEventListener("submit", async (event) => {
        event.preventDefault();

        const status = new FormData(form).get("status");

        if (status === encoder.status) {
            closeModal();
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = "Saving...";

        try {
            await api.patch(`/lgu/encoders/${encodeURIComponent(id)}/status`, {
                status,
            });

            closeModal();
            await loadEncoders();
        } catch (error) {
            errorElement.textContent = getError(error);
            errorElement.hidden = false;
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = "Save Status";
        }
    });
}

function bindEvents() {
    $("le-create").addEventListener("click", createEncoder);
    $("le-refresh").addEventListener("click", loadEncoders);

    let searchTimer;

    $("le-search").addEventListener("input", (event) => {
        clearTimeout(searchTimer);

        searchTimer = setTimeout(() => {
            state.search = event.target.value.trim();
            state.page = 1;
            loadEncoders();
        }, 350);
    });

    $("le-status").addEventListener("change", (event) => {
        state.status = event.target.value;
        state.page = 1;
        loadEncoders();
    });

    $("le-reset").addEventListener("click", () => {
        $("le-search").value = "";
        $("le-status").value = "";

        state.search = "";
        state.status = "";
        state.page = 1;

        clearTimeout(searchTimer);
        loadEncoders();
    });

    $("le-prev").addEventListener("click", () => {
        if (state.page > 1) {
            state.page--;
            loadEncoders();
        }
    });

    $("le-next").addEventListener("click", () => {
        if (state.page < state.lastPage) {
            state.page++;
            loadEncoders();
        }
    });

    $("le-table-body").addEventListener("click", (event) => {
        const button = event.target.closest("[data-action]");

        if (!button) return;

        if (button.dataset.action === "status") {
            changeStatus(button.dataset.id);
        }
    });

    $("le-modal").addEventListener("click", (event) => {
        if (event.target.closest("[data-close-modal]")) {
            closeModal();
        }
    });

    $("le-modal-close").addEventListener("click", closeModal);

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && !$("le-modal").hidden) {
            closeModal();
        }
    });
}

async function init() {
    bindEvents();
    await loadEncoders();
}

document.addEventListener("DOMContentLoaded", init);
