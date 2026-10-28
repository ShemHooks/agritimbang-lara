import api from "../api";

const $ = (id) => document.getElementById(id);

const state = {
    tab: "all",
    page: 1,
    lastPage: 1,
    search: "",
    status: "",
    municipality: "",
    verification: "",
    users: [],
    municipalities: [],
    loading: false,
    requestId: 0,
};

const labels = {
    all: ["All User Accounts", "Browse all registered platform accounts."],
    lgu_authority: ["LGU Authorities", "Manage municipal authority accounts."],
    lgu_encoder: ["LGU Encoders", "View registered LGU encoder accounts."],
    farmer: ["Registered Farmers", "Monitor farmer accounts and verification."],
    verification: [
        "Farmer Verification",
        "Review pending identity verification applications.",
    ],
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
    const first = Object.values(data?.errors ?? {})[0];
    return first?.[0] ?? data?.message ?? "An unexpected error occurred.";
}

function unwrap(response) {
    return response.data?.data ?? response.data;
}

function userName(user) {
    return [user.first_name, user.middle_name, user.last_name]
        .filter(Boolean)
        .join(" ");
}

function userRole(user) {
    return user.roles?.map((role) => role.slug) ?? [];
}

function roleLabel(slug) {
    return (
        {
            admin: "System Admin",
            lgu_authority: "LGU Authority",
            lgu_encoder: "LGU Encoder",
            farmer: "Farmer",
        }[slug] ?? slug
    );
}

function municipalityName(user) {
    return (
        user.farmer_profile?.municipality?.name ??
        user.lgu_officer_profile?.municipality?.name ??
        "—"
    );
}

function badge(value) {
    const safe = escapeHtml(value || "—");
    const type = ["active", "verified", "approved"].includes(value)
        ? "good"
        : ["pending", "inactive"].includes(value)
          ? "warning"
          : ["rejected", "suspended"].includes(value)
            ? "danger"
            : "neutral";

    return `<span class="um-badge um-${type}">${safe.replaceAll("_", " ")}</span>`;
}

function setLoading(loading) {
    state.loading = loading;
    $("um-loading").hidden = !loading;
}

function setError(message = "") {
    $("um-error").hidden = !message;
    $("um-error").textContent = message;
}

function openModal(title, html) {
    $("um-modal-title").textContent = title;
    $("um-modal-content").innerHTML = html;
    $("um-modal").hidden = false;
    document.body.classList.add("um-modal-open");
    $("um-modal-close").focus();
}

function closeModal() {
    $("um-modal").hidden = true;
    $("um-modal-content").innerHTML = "";
    document.body.classList.remove("um-modal-open");
}

function formField(name, label, type = "text", required = false) {
    return `
        <label class="um-field">
            <span>${escapeHtml(label)}</span>
            <input name="${name}" type="${type}" ${required ? "required" : ""}>
        </label>
    `;
}

function municipalityOptions(selected = "") {
    return state.municipalities
        .map(
            (m) => `
        <option value="${escapeHtml(m.id)}"
            ${String(m.id) === String(selected) ? "selected" : ""}>
            ${escapeHtml(m.name)}
        </option>
    `,
        )
        .join("");
}

async function loadMunicipalities() {
    try {
        const response = await api.get("/reference/municipalities");
        const result = unwrap(response);
        state.municipalities = Array.isArray(result)
            ? result
            : (result.data ?? []);

        $("um-municipality").innerHTML = `
            <option value="">All municipalities</option>
            ${municipalityOptions()}
        `;
    } catch (error) {
        console.error("Unable to load municipalities:", error);
    }
}

async function loadSummary() {
    try {
        const result = unwrap(await api.get("/admin/users/summary"));

        $("um-total").textContent = result.total_users ?? 0;
        $("um-active").textContent = result.active_users ?? 0;
        $("um-pending").textContent = result.pending_verifications ?? 0;
        $("um-suspended").textContent = result.suspended_users ?? 0;
    } catch (error) {
        console.error("Unable to load user summary:", error);
    }
}

function buildParams() {
    const params = { page: state.page };

    if (state.search) params.search = state.search;
    if (state.status) params.status = state.status;
    if (state.municipality) params.municipality_id = state.municipality;

    if (state.tab === "verification") {
        params.role = "farmer";
        params.verification_status = "pending";
    } else {
        if (state.tab !== "all") params.role = state.tab;
        if (state.verification) {
            params.verification_status = state.verification;
        }
    }

    return params;
}

async function loadUsers() {
    const requestId = ++state.requestId;

    setLoading(true);
    setError("");

    try {
        const result = unwrap(
            await api.get("/admin/users", {
                params: buildParams(),
            }),
        );

        if (requestId !== state.requestId) return;

        state.users = result.data ?? [];
        state.lastPage = result.last_page ?? 1;

        renderTable();

        $("um-result-count").textContent =
            `${result.total ?? state.users.length} matching account(s)`;

        $("um-page-info").textContent =
            `Page ${result.current_page ?? state.page} of ${state.lastPage}`;

        $("um-prev").disabled = state.page <= 1;
        $("um-next").disabled = state.page >= state.lastPage;
    } catch (error) {
        if (requestId !== state.requestId) return;
        state.users = [];
        renderTable();
        setError(getError(error));
    } finally {
        if (requestId === state.requestId) setLoading(false);
    }
}

function renderTable() {
    const tbody = $("um-table-body");

    if (!state.users.length) {
        tbody.innerHTML = `
            <tr><td colspan="6" class="um-empty">
                No user records found.
            </td></tr>
        `;
        return;
    }

    tbody.innerHTML = state.users
        .map((user) => {
            const roles = userRole(user);
            const isAdmin = roles.includes("admin");

            return `
            <tr>
                <td>
                    <div class="um-person">
                        <span class="um-avatar">
                            ${escapeHtml((user.first_name ?? "U")[0].toUpperCase())}
                        </span>
                        <span>
                            <strong>${escapeHtml(userName(user))}</strong>
                            <small>${escapeHtml(user.email)}</small>
                        </span>
                    </div>
                </td>
                <td>${roles.map(roleLabel).map(escapeHtml).join(", ") || "—"}</td>
                <td>${escapeHtml(municipalityName(user))}</td>
                <td>${
                    roles.includes("farmer")
                        ? badge(user.verification_status)
                        : "—"
                }</td>
                <td>${badge(user.status)}</td>
                <td>
                    <div class="um-row-actions">
                        <button data-action="view" data-id="${escapeHtml(user.id)}">
                            View
                        </button>
                        ${
                            !isAdmin
                                ? `
                            <button data-action="status" data-id="${escapeHtml(user.id)}">
                                Status
                            </button>
                        `
                                : ""
                        }
                        ${
                            roles.includes("farmer") &&
                            user.verification_status === "pending"
                                ? `<button data-action="review" data-id="${escapeHtml(user.id)}">
                                Review
                            </button>`
                                : ""
                        }
                    </div>
                </td>
            </tr>
        `;
        })
        .join("");
}

async function viewUser(id) {
    try {
        const user = unwrap(
            await api.get(`/admin/users/${encodeURIComponent(id)}`),
        );

        openModal(
            "User Details",
            `
            <div class="um-detail">
                <div><span>Full Name</span><strong>${escapeHtml(userName(user))}</strong></div>
                <div><span>Email</span><strong>${escapeHtml(user.email)}</strong></div>
                <div><span>Phone</span><strong>${escapeHtml(user.phone_number || "—")}</strong></div>
                <div><span>Role</span><strong>${userRole(user).map(roleLabel).map(escapeHtml).join(", ")}</strong></div>
                <div><span>Municipality</span><strong>${escapeHtml(municipalityName(user))}</strong></div>
                <div><span>Account Status</span>${badge(user.status)}</div>
                <div><span>Verification</span>${badge(user.verification_status)}</div>
            </div>
        `,
        );
    } catch (error) {
        setError(getError(error));
    }
}

function changeStatus(id) {
    const user = state.users.find((item) => String(item.id) === String(id));
    if (!user) return;

    openModal(
        "Change Account Status",
        `
        <p class="um-modal-description">
            Update the account status for ${escapeHtml(userName(user))}.
        </p>
        <form id="um-status-form">
            <label class="um-field">
                <span>Account Status</span>
                <select name="status" required>
                    ${["active", "inactive", "suspended"]
                        .map(
                            (status) => `
                        <option value="${status}" ${user.status === status ? "selected" : ""}>
                            ${status.charAt(0).toUpperCase() + status.slice(1)}
                        </option>
                    `,
                        )
                        .join("")}
                </select>
            </label>
            <p class="um-form-error" id="um-form-error" role="alert" hidden></p>
            <button class="um-btn" type="submit">Save Changes</button>
        </form>
    `,
    );

    $("um-status-form").addEventListener("submit", async (event) => {
        event.preventDefault();

        const form = event.currentTarget;
        const button = form.querySelector('[type="submit"]');
        const status = new FormData(form).get("status");

        button.disabled = true;

        try {
            await api.patch(`/admin/users/${encodeURIComponent(id)}/status`, {
                status,
            });

            closeModal();
            await Promise.all([loadUsers(), loadSummary()]);
        } catch (error) {
            $("um-form-error").textContent = getError(error);
            $("um-form-error").hidden = false;
        } finally {
            button.disabled = false;
        }
    });
}

function createAuthority() {
    openModal(
        "Create LGU Authority",
        `
        <p class="um-modal-description">
            Create an LGU Authority account and assign it to a municipality.
            Login credentials will be generated automatically.
        </p>

        <form id="um-create-form" class="um-form">
            <div class="um-form-grid">
                ${formField("first_name", "First Name", "text", true)}
                ${formField("last_name", "Last Name", "text", true)}
                ${formField("middle_name", "Middle Name")}
                ${formField("phone_number", "Phone Number", "tel")}
                ${formField("email", "Email Address", "email", true)}

                <label class="um-field">
                    <span>Municipality</span>
                    <select name="municipality_id" required>
                        <option value="">Select municipality</option>
                        ${municipalityOptions()}
                    </select>
                </label>

                ${formField("station", "Station / Office")}
                ${formField("designation", "Designation")}
            </div>

            <div class="um-credentials-notice">
                <div class="um-credentials-notice__icon">
                    ✉
                </div>

                <div>
                    <strong>Automatic Account Credentials</strong>
                    <p>
                        A secure temporary password will be generated
                        by the system and emailed to the new LGU Authority.
                        The user should change their password after
                        their first login.
                    </p>
                </div>
            </div>

            <p
                class="um-form-error"
                id="um-form-error"
                role="alert"
                hidden
            ></p>

            <button type="submit" class="um-btn">
                Create Authority
            </button>
        </form>
        `,
    );

    const form = $("um-create-form");
    const errorElement = $("um-form-error");
    const submitButton = form.querySelector('[type="submit"]');

    form.addEventListener("submit", async (event) => {
        event.preventDefault();

        if (submitButton.disabled) return;

        errorElement.hidden = true;
        errorElement.textContent = "";

        const formData = new FormData(form);

        const data = {
            first_name: String(formData.get("first_name") ?? "").trim(),
            last_name: String(formData.get("last_name") ?? "").trim(),
            middle_name:
                String(formData.get("middle_name") ?? "").trim() || null,
            phone_number:
                String(formData.get("phone_number") ?? "").trim() || null,
            email: String(formData.get("email") ?? "")
                .trim()
                .toLowerCase(),
            municipality_id: String(formData.get("municipality_id") ?? ""),
            station: String(formData.get("station") ?? "").trim() || null,
            designation:
                String(formData.get("designation") ?? "").trim() || null,
        };

        submitButton.disabled = true;
        submitButton.textContent = "Creating Account...";

        try {
            const response = await api.post("/admin/lgu-authorities", data);

            const result = unwrap(response);

            closeModal();

            state.tab = "lgu_authority";
            state.page = 1;

            updateTabs();

            await Promise.all([loadUsers(), loadSummary()]);

            // Only report confirmed email delivery if the
            // backend explicitly returns that information.
            if (result?.email_delivery_status === "sent") {
                alert(
                    "LGU Authority account created successfully. " +
                        "The temporary password email was sent.",
                );
            } else if (result?.email_delivery_status === "failed") {
                alert(
                    "LGU Authority account created successfully, " +
                        "but the credentials email could not be delivered. " +
                        "Please contact the administrator.",
                );
            } else {
                alert(
                    "LGU Authority account created successfully. " +
                        "Email delivery has not been confirmed.",
                );
            }
        } catch (error) {
            errorElement.textContent = getError(error);
            errorElement.hidden = false;
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = "Create Authority";
        }
    });
}

async function editAuthority(id) {
    try {
        const response = await api.get(
            `/admin/lgu-authorities/${encodeURIComponent(id)}`,
        );

        const authority = unwrap(response);

        const profile = authority.lgu_officer_profile ?? {};

        const currentMunicipalityId = profile.municipality_id ?? "";

        const field = (
            name,
            label,
            value = "",
            type = "text",
            required = false,
        ) => `
            <label class="um-field">
                <span>${escapeHtml(label)}</span>
                <input
                    name="${escapeHtml(name)}"
                    type="${type}"
                    value="${escapeHtml(value)}"
                    ${required ? "required" : ""}
                >
            </label>
        `;

        openModal(
            "Edit LGU Authority",
            `
            <p class="um-modal-description">
                Update the authority's account information
                and municipality assignment.
            </p>

            <form id="um-edit-authority-form" class="um-form">

                <div class="um-form-grid">
                    ${field(
                        "first_name",
                        "First Name",
                        authority.first_name,
                        "text",
                        true,
                    )}

                    ${field(
                        "last_name",
                        "Last Name",
                        authority.last_name,
                        "text",
                        true,
                    )}

                    ${field(
                        "middle_name",
                        "Middle Name",
                        authority.middle_name,
                    )}

                    ${field(
                        "phone_number",
                        "Phone Number",
                        authority.phone_number,
                        "tel",
                    )}

                    ${field(
                        "email",
                        "Email Address",
                        authority.email,
                        "email",
                        true,
                    )}

                    <label class="um-field">
                        <span>Municipality</span>
                        <select name="municipality_id" required>
                            <option value="">
                                Select municipality
                            </option>
                            ${municipalityOptions(currentMunicipalityId)}
                        </select>
                    </label>

                    ${field("station", "Station / Office", profile.station)}

                    ${field("designation", "Designation", profile.designation)}
                </div>

                <div class="um-password-section">
                    <h3>Change Password</h3>
                    <p class="um-modal-description">
                        Leave both password fields blank
                        to keep the current password.
                    </p>

                    <div class="um-form-grid">
                        ${field("password", "New Password", "", "password")}

                        ${field(
                            "password_confirmation",
                            "Confirm New Password",
                            "",
                            "password",
                        )}
                    </div>
                </div>

                <p
                    id="um-form-error"
                    class="um-form-error"
                    role="alert"
                    hidden
                ></p>

                <div class="um-actions">
                    <button
                        type="button"
                        class="um-btn-secondary"
                        id="um-edit-cancel">
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="um-btn">
                        Save Changes
                    </button>
                </div>
            </form>
            `,
        );

        $("um-edit-cancel").addEventListener("click", closeModal);

        $("um-edit-authority-form").addEventListener(
            "submit",
            async (event) => {
                event.preventDefault();

                const form = event.currentTarget;

                const button = form.querySelector('[type="submit"]');

                const data = Object.fromEntries(new FormData(form).entries());

                if (
                    data.password &&
                    data.password !== data.password_confirmation
                ) {
                    $("um-form-error").textContent =
                        "Password confirmation does not match.";

                    $("um-form-error").hidden = false;
                    return;
                }

                if (!data.password) {
                    delete data.password;
                    delete data.password_confirmation;
                }

                // Empty optional fields should be null
                // to satisfy Laravel nullable validation.
                for (const key of [
                    "middle_name",
                    "phone_number",
                    "station",
                    "designation",
                ]) {
                    if (data[key] === "") {
                        data[key] = null;
                    }
                }

                button.disabled = true;

                try {
                    await api.patch(
                        `/admin/lgu-authorities/${encodeURIComponent(id)}`,
                        data,
                    );

                    closeModal();

                    await Promise.all([loadUsers(), loadSummary()]);
                } catch (error) {
                    $("um-form-error").textContent = getError(error);

                    $("um-form-error").hidden = false;
                } finally {
                    button.disabled = false;
                }
            },
        );
    } catch (error) {
        setError(getError(error));
    }
}

async function reviewFarmer(id) {
    try {
        const farmer = unwrap(
            await api.get(
                `/admin/farmer-verifications/${encodeURIComponent(id)}`,
            ),
        );

        const documents = farmer.verification_documents ?? [];

        openModal(
            "Farmer Verification",
            `
            <div class="um-review">
                <h3>${escapeHtml(userName(farmer))}</h3>
                <p>${escapeHtml(farmer.email)}</p>
                <p>Municipality: ${escapeHtml(municipalityName(farmer))}</p>
                <div class="um-document-list">
                    ${
                        documents
                            .map(
                                (doc) => `
                        <article class="um-document">
                            <div>
                                <strong>${escapeHtml(doc.document_type)}</strong>
                                <small>${badge(doc.status)}</small>
                            </div>
                            <div class="um-row-actions">
                                <button type="button"
                                    data-doc-action="file"
                                    data-id="${escapeHtml(doc.id)}">
                                    View File
                                </button>
                                ${
                                    doc.status === "pending"
                                        ? `
                                    <button type="button"
                                        data-doc-action="approve"
                                        data-id="${escapeHtml(doc.id)}">
                                        Approve
                                    </button>
                                    <button type="button"
                                        data-doc-action="reject"
                                        data-id="${escapeHtml(doc.id)}">
                                        Reject
                                    </button>
                                `
                                        : ""
                                }
                            </div>
                        </article>
                    `,
                            )
                            .join("") ||
                        "<p>No verification documents found.</p>"
                    }
                </div>
                <p id="um-review-error" class="um-form-error" role="alert" hidden></p>
            </div>
        `,
        );
    } catch (error) {
        setError(getError(error));
    }
}

async function viewPrivateFile(id) {
    try {
        const response = await api.get(
            `/admin/verification-documents/${encodeURIComponent(id)}/file`,
            { responseType: "blob" },
        );

        const type =
            response.headers?.["content-type"] || "application/octet-stream";
        const blob = new Blob([response.data], { type });
        const url = URL.createObjectURL(blob);

        window.open(url, "_blank", "noopener,noreferrer");
        setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (error) {
        $("um-review-error").textContent = getError(error);
        $("um-review-error").hidden = false;
    }
}

async function decideVerification(action, id) {
    if (!["approve", "reject"].includes(action)) {
        return;
    }

    const content = $("um-modal-content");

    if (action === "reject") {
        const existing = $("um-rejection-form");

        if (existing) {
            existing.remove();
            return;
        }

        const form = document.createElement("form");
        form.id = "um-rejection-form";
        form.className = "um-form";

        form.innerHTML = `
            <h3>Reject Verification</h3>

            <label class="um-field">
                <span>Reason for Rejection</span>

                <textarea
                    name="rejection_reason"
                    rows="4"
                    maxlength="1000"
                    required
                    placeholder="Explain why this document was rejected..."
                ></textarea>
            </label>

            <p
                class="um-form-error"
                id="um-rejection-error"
                role="alert"
                hidden
            ></p>

            <button type="submit" class="um-btn">
                Confirm Rejection
            </button>
        `;

        content.appendChild(form);

        form.addEventListener("submit", async (event) => {
            event.preventDefault();

            const reason = form
                .querySelector('[name="rejection_reason"]')
                .value.trim();

            if (!reason) return;

            const button = form.querySelector('[type="submit"]');

            button.disabled = true;

            try {
                await api.post(
                    `/admin/verification-documents/${encodeURIComponent(id)}/reject`,
                    { rejection_reason: reason },
                );

                closeModal();

                await Promise.all([loadUsers(), loadSummary()]);
            } catch (error) {
                $("um-rejection-error").textContent = getError(error);

                $("um-rejection-error").hidden = false;
            } finally {
                button.disabled = false;
            }
        });

        form.querySelector("textarea").focus();
        return;
    }

    const confirmed = window.confirm(
        "Approve this farmer verification document?",
    );

    if (!confirmed) return;

    try {
        await api.post(
            `/admin/verification-documents/${encodeURIComponent(id)}/approve`,
        );

        closeModal();

        await Promise.all([loadUsers(), loadSummary()]);
    } catch (error) {
        const errorElement = $("um-review-error");

        if (errorElement) {
            errorElement.textContent = getError(error);
            errorElement.hidden = false;
        }
    }
}

function updateTabs() {
    document.querySelectorAll(".um-tab").forEach((button) => {
        const active = button.dataset.tab === state.tab;
        button.classList.toggle("active", active);
        button.setAttribute("aria-selected", String(active));
    });

    $("um-section-title").textContent = labels[state.tab][0];
    $("um-section-description").textContent = labels[state.tab][1];
    $("um-table-title").textContent = labels[state.tab][0];

    $("um-verification").disabled = state.tab === "verification";
}

function bindEvents() {
    document.querySelectorAll(".um-tab").forEach((button) => {
        button.addEventListener("click", () => {
            state.tab = button.dataset.tab;
            state.page = 1;
            updateTabs();
            loadUsers();
        });
    });

    let debounce;

    $("um-search").addEventListener("input", (event) => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            state.search = event.target.value.trim();
            state.page = 1;
            loadUsers();
        }, 350);
    });

    [
        ["um-status", "status"],
        ["um-municipality", "municipality"],
        ["um-verification", "verification"],
    ].forEach(([id, key]) => {
        $(id).addEventListener("change", (event) => {
            state[key] = event.target.value;
            state.page = 1;
            loadUsers();
        });
    });

    $("um-reset").addEventListener("click", () => {
        state.search = "";
        state.status = "";
        state.municipality = "";
        state.verification = "";
        state.page = 1;

        [
            "um-search",
            "um-status",
            "um-municipality",
            "um-verification",
        ].forEach((id) => {
            $(id).value = "";
        });

        loadUsers();
    });

    $("um-refresh").addEventListener("click", () => {
        loadUsers();
        loadSummary();
    });

    $("um-create").addEventListener("click", createAuthority);

    $("um-prev").addEventListener("click", () => {
        if (state.page > 1) {
            state.page--;
            loadUsers();
        }
    });

    $("um-next").addEventListener("click", () => {
        if (state.page < state.lastPage) {
            state.page++;
            loadUsers();
        }
    });

    $("um-table-body").addEventListener("click", (event) => {
        const button = event.target.closest("[data-action]");
        if (!button) return;

        const { action, id } = button.dataset;

        if (action === "view") viewUser(id);
        if (action === "status") changeStatus(id);
        if (action === "review") reviewFarmer(id);
        if (action === "edit-authority") {
            editAuthority(id);
        }
    });

    $("um-modal-content").addEventListener("click", (event) => {
        const button = event.target.closest("[data-doc-action]");
        if (!button) return;

        const { docAction, id } = button.dataset;

        if (docAction === "file") viewPrivateFile(id);
        if (docAction === "approve" || docAction === "reject") {
            decideVerification(docAction, id);
        }
    });

    $("um-modal-close").addEventListener("click", closeModal);

    $("um-modal").addEventListener("click", (event) => {
        if (event.target === $("um-modal")) closeModal();
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && !$("um-modal").hidden) {
            closeModal();
        }
    });
}

async function init() {
    try {
        const response = await api.get("/auth/me");
        const user = unwrap(response);

        if (!user.roles?.some((role) => role.slug === "admin")) {
            window.location.replace("/dashboard");
            return;
        }
    } catch {
        window.location.replace("/login");
        return;
    }

    bindEvents();
    updateTabs();

    await loadMunicipalities();
    await Promise.all([loadSummary(), loadUsers()]);
}

init();
