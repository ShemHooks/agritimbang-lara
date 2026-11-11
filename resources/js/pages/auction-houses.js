import api from "../api";

const $ = (id) => document.getElementById(id);

const endpoint = "/lgu/auction-houses";

const state = {
    page: 1,
    lastPage: 1,
    houses: [],
    editingId: null,
    encoderHouseId: null,
    barangays: [],
    loading: false,
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

function showAlert(message, error = false) {
    const element = $("auction-alert");

    element.textContent = message;
    element.classList.toggle("is-error", error);
    element.hidden = false;
}

function clearErrors() {
    $("auction-form-error").hidden = true;

    document.querySelectorAll(".auction-field-error").forEach((element) => {
        element.textContent = "";
    });
}

function showRequestError(error, form = false) {
    const message =
        error.response?.data?.message ??
        error.message ??
        "Something went wrong. Please try again.";

    if (!form) {
        showAlert(message, true);
        return;
    }

    const element = $("auction-form-error");
    element.textContent = message;
    element.hidden = false;

    const errors = error.response?.data?.errors ?? {};

    Object.entries(errors).forEach(([field, messages]) => {
        const target = document.querySelector(`[data-error-for="${field}"]`);

        if (target) {
            target.textContent = Array.isArray(messages)
                ? messages[0]
                : String(messages);
        }
    });
}

function showEncoderError(error) {
    const element = $("auction-encoder-error");

    element.textContent =
        error.response?.data?.message ??
        error.message ??
        "Something went wrong.";

    element.hidden = false;
}

function clearEncoderError() {
    $("auction-encoder-error").textContent = "";
    $("auction-encoder-error").hidden = true;
}

/*
|--------------------------------------------------------------------------
| Auction Houses
|--------------------------------------------------------------------------
*/

async function loadHouses() {
    const tbody = $("auction-table-body");

    tbody.innerHTML = `
        <tr>
            <td colspan="4">Loading auction houses...</td>
        </tr>
    `;

    try {
        const response = await api.get(endpoint, {
            params: {
                page: state.page,
            },
        });

        const paginator = response.data.data;

        state.houses = paginator.data ?? [];
        state.page = paginator.current_page ?? 1;
        state.lastPage = paginator.last_page ?? 1;

        renderHouses();
    } catch (error) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4">
                    Unable to load auction houses.
                </td>
            </tr>
        `;

        showRequestError(error);
    }
}

function renderHouses() {
    const search = $("auction-search").value.trim().toLowerCase();
    const filter = $("auction-status-filter").value;

    const filtered = state.houses.filter((house) => {
        const text = [house.name, house.address].join(" ").toLowerCase();

        const matchesSearch = text.includes(search);

        const matchesStatus =
            filter === "all" ||
            (filter === "active" && Boolean(house.is_active)) ||
            (filter === "inactive" && !house.is_active);

        return matchesSearch && matchesStatus;
    });

    $("auction-table-body").innerHTML = filtered.length
        ? filtered
              .map(
                  (house) => `
            <tr>
                <td>
                    <strong>${escapeHtml(house.name)}</strong>
                </td>

                <td>
                    ${escapeHtml(house.address || "—")}
                </td>

                <td>
                    <span class="auction-status ${
                        house.is_active ? "active" : "inactive"
                    }">
                        ${house.is_active ? "Active" : "Inactive"}
                    </span>
                </td>

                <td>
                    <div class="auction-actions">
                        <button
                            type="button"
                            data-edit="${escapeHtml(house.id)}"
                            class="auction-btn"
                        >
                            Edit
                        </button>

                        <button
                            type="button"
                            data-encoders="${escapeHtml(house.id)}"
                            class="auction-btn"
                        >
                            Encoders
                        </button>

                        <button
                            type="button"
                            data-status="${escapeHtml(house.id)}"
                            class="auction-btn"
                        >
                            ${house.is_active ? "Deactivate" : "Activate"}
                        </button>
                    </div>
                </td>
            </tr>
        `,
              )
              .join("")
        : `
            <tr>
                <td colspan="4">
                    No auction houses found on this page.
                </td>
            </tr>
        `;

    $("auction-page-info").textContent =
        `Page ${state.page} of ${state.lastPage}`;

    $("auction-prev").disabled = state.page <= 1;
    $("auction-next").disabled = state.page >= state.lastPage;
}

/*
|--------------------------------------------------------------------------
| Barangays
|--------------------------------------------------------------------------
*/

function populateBarangays() {
    const select = $("auction-barangay");

    select.innerHTML = `
        <option value="">No barangay selected</option>
        ${state.barangays
            .map(
                (barangay) => `
            <option value="${escapeHtml(barangay.id)}">
                ${escapeHtml(barangay.name)}
            </option>
        `,
            )
            .join("")}
    `;
}

async function loadBarangays() {
    const select = $("auction-barangay");

    select.disabled = true;
    select.innerHTML = `
        <option value="">Loading barangays...</option>
    `;

    try {
        const meResponse = await api.get("/auth/me");

        const user = meResponse.data.data ?? meResponse.data;

        const municipalityId =
            user.lgu_officer_profile?.municipality_id ??
            user.lguOfficerProfile?.municipality_id;

        if (!municipalityId) {
            throw new Error("Your LGU account has no assigned municipality.");
        }

        const response = await api.get(
            `/reference/municipalities/${municipalityId}/barangays`,
        );

        const result = response.data.data ?? [];

        state.barangays = Array.isArray(result) ? result : (result.data ?? []);

        populateBarangays();
    } catch (error) {
        console.error("Failed to load barangays:", error);

        state.barangays = [];

        select.innerHTML = `
            <option value="">Unable to load barangays</option>
        `;

        showRequestError(error);
    } finally {
        select.disabled = false;
    }
}

/*
|--------------------------------------------------------------------------
| Create / Edit Auction House
|--------------------------------------------------------------------------
*/

function openCreateDialog() {
    state.editingId = null;

    $("auction-form").reset();
    $("auction-form-title").textContent = "Register Auction House";

    clearErrors();

    $("auction-form-dialog").showModal();
}

function openEditDialog(id) {
    const house = state.houses.find((item) => item.id === id);
    if (!house) return;

    state.editingId = id;

    clearErrors();

    $("auction-form-title").textContent = "Edit Auction House";

    $("auction-name").value = house.name ?? "";
    $("auction-address").value = house.address ?? "";
    $("auction-barangay").value = house.barangay_id ?? "";

    $("auction-form-dialog").showModal();
}

async function saveHouse(event) {
    event.preventDefault();
    clearErrors();

    const payload = {
        name: $("auction-name").value.trim(),
        barangay_id: $("auction-barangay").value || null,
        address: $("auction-address").value.trim() || null,
    };

    if (!payload.name) {
        $("auction-form-error").textContent = "Auction house name is required.";

        $("auction-form-error").hidden = false;
        return;
    }

    const button = $("auction-save-btn");
    button.disabled = true;

    try {
        const isEditing = Boolean(state.editingId);

        if (isEditing) {
            await api.patch(`${endpoint}/${state.editingId}`, payload);
        } else {
            await api.post(endpoint, payload);
        }

        $("auction-form-dialog").close();

        showAlert(
            isEditing
                ? "Auction house updated successfully."
                : "Auction house registered successfully.",
        );

        await loadHouses();
    } catch (error) {
        showRequestError(error, true);
    } finally {
        button.disabled = false;
    }
}

/*
|--------------------------------------------------------------------------
| Activate / Deactivate
|--------------------------------------------------------------------------
*/

async function toggleStatus(id) {
    const house = state.houses.find((item) => item.id === id);
    if (!house) return;

    const nextStatus = !Boolean(house.is_active);

    const confirmed = window.confirm(
        `${nextStatus ? "Activate" : "Deactivate"} "${house.name}"?`,
    );

    if (!confirmed) return;

    try {
        await api.patch(`${endpoint}/${id}/status`, {
            is_active: nextStatus,
        });

        showAlert("Auction house status updated successfully.");

        await loadHouses();
    } catch (error) {
        showRequestError(error);
    }
}

/*
|--------------------------------------------------------------------------
| Encoder Assignment Management
|--------------------------------------------------------------------------
*/

async function openEncoderDialog(id) {
    const house = state.houses.find((item) => item.id === id);
    if (!house) return;

    state.encoderHouseId = id;

    $("auction-encoder-house-name").textContent = house.name;

    clearEncoderError();

    $("auction-encoders-dialog").showModal();

    await loadEncoderAssignments();
}

async function loadEncoderAssignments() {
    const houseId = state.encoderHouseId;
    if (!houseId) return;

    const container = $("auction-assigned-encoders");
    const select = $("auction-encoder-select");

    container.textContent = "Loading encoders...";
    select.disabled = true;

    clearEncoderError();

    try {
        const [assignedResponse, eligibleResponse] = await Promise.all([
            api.get(`${endpoint}/${houseId}/encoders`),
            api.get(`${endpoint}/eligible-encoders`),
        ]);

        if (state.encoderHouseId !== houseId) return;

        const assigned = assignedResponse.data.data ?? [];
        const eligible = eligibleResponse.data.data ?? [];

        const assignedIds = new Set(assigned.map((encoder) => encoder.user_id));

        const available = eligible.filter(
            (encoder) => !assignedIds.has(encoder.id),
        );

        select.innerHTML = `
            <option value="">Select an encoder</option>

            ${available
                .map(
                    (encoder) => `
                <option value="${escapeHtml(encoder.id)}">
                    ${escapeHtml(encoder.name)}
                </option>
            `,
                )
                .join("")}
        `;

        container.innerHTML = assigned.length
            ? assigned
                  .map(
                      (encoder) => `
                <div class="auction-encoder-row">
                    <div>
                        <strong>
                            ${escapeHtml(encoder.name)}
                        </strong>

                        <small>
                            ${escapeHtml(encoder.email || "")}
                        </small>
                    </div>

                    <button
                        type="button"
                        class="auction-btn"
                        data-remove-encoder="${escapeHtml(encoder.user_id)}"
                    >
                        Remove
                    </button>
                </div>
            `,
                  )
                  .join("")
            : `
                <p>No encoders assigned yet.</p>
            `;
    } catch (error) {
        if (state.encoderHouseId !== houseId) return;

        container.textContent = "Unable to load encoders.";
        showEncoderError(error);
    } finally {
        if (state.encoderHouseId === houseId) {
            select.disabled = false;
        }
    }
}

async function assignEncoder() {
    const houseId = state.encoderHouseId;
    const encoderId = $("auction-encoder-select").value;

    if (!houseId || !encoderId) {
        showEncoderError(new Error("Please select an encoder."));
        return;
    }

    const button = $("auction-assign-encoder-btn");

    button.disabled = true;
    clearEncoderError();

    try {
        await api.post(`${endpoint}/${houseId}/encoders`, {
            user_id: encoderId,
        });

        showAlert("Encoder assigned successfully.");

        await loadEncoderAssignments();
    } catch (error) {
        showEncoderError(error);
    } finally {
        button.disabled = false;
    }
}

async function removeEncoder(encoderId) {
    const houseId = state.encoderHouseId;
    if (!houseId) return;

    const confirmed = window.confirm("Remove this encoder assignment?");

    if (!confirmed) return;

    clearEncoderError();

    try {
        await api.delete(`${endpoint}/${houseId}/encoders/${encoderId}`);

        showAlert("Encoder assignment removed.");

        await loadEncoderAssignments();
    } catch (error) {
        showEncoderError(error);
    }
}

/*
|--------------------------------------------------------------------------
| Events
|--------------------------------------------------------------------------
*/

function registerEvents() {
    $("auction-create-btn").addEventListener("click", openCreateDialog);

    $("auction-form").addEventListener("submit", saveHouse);

    $("auction-search").addEventListener("input", renderHouses);

    $("auction-status-filter").addEventListener("change", renderHouses);

    $("auction-prev").addEventListener("click", async () => {
        if (state.page > 1) {
            state.page--;
            await loadHouses();
        }
    });

    $("auction-next").addEventListener("click", async () => {
        if (state.page < state.lastPage) {
            state.page++;
            await loadHouses();
        }
    });

    $("auction-table-body").addEventListener("click", (event) => {
        const editButton = event.target.closest("[data-edit]");

        const statusButton = event.target.closest("[data-status]");

        const encoderButton = event.target.closest("[data-encoders]");

        if (editButton) {
            openEditDialog(editButton.dataset.edit);
        }

        if (statusButton) {
            toggleStatus(statusButton.dataset.status);
        }

        if (encoderButton) {
            openEncoderDialog(encoderButton.dataset.encoders);
        }
    });

    $("auction-assign-encoder-btn").addEventListener("click", assignEncoder);

    $("auction-assigned-encoders").addEventListener("click", (event) => {
        const button = event.target.closest("[data-remove-encoder]");

        if (button) {
            removeEncoder(button.dataset.removeEncoder);
        }
    });

    document.querySelectorAll("[data-close-dialog]").forEach((button) => {
        button.addEventListener("click", () => {
            const dialog = $(button.dataset.closeDialog);

            dialog.close();

            if (dialog.id === "auction-encoders-dialog") {
                state.encoderHouseId = null;
            }
        });
    });

    $("auction-encoders-dialog").addEventListener("close", () => {
        state.encoderHouseId = null;
    });
}

/*
|--------------------------------------------------------------------------
| Initialize
|--------------------------------------------------------------------------
*/

document.addEventListener("DOMContentLoaded", async () => {
    if (!$("auction-page")) return;

    registerEvents();

    await Promise.all([loadHouses(), loadBarangays()]);
});
