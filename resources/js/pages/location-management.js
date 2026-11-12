import api from "../api";

const $ = (id) => document.getElementById(id);

const state = {
    tab: "municipalities",
    municipalities: [],
    barangays: [],
    barangayPagination: null,
    page: 1,
    perPage: 15,
    requestId: 0,
    pending: null,
    busy: false,
};

const escapeHtml = (value) =>
    String(value ?? "").replace(
        /[&<>"']/g,
        (c) =>
            ({
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': "&quot;",
                "'": "&#39;",
            })[c],
    );

function notify(message, error = false) {
    const el = $("location-alert");
    el.textContent = message;
    el.classList.toggle("is-error", error);
    el.hidden = false;
}

function clearNotice() {
    $("location-alert").hidden = true;
}

function unpack(response) {
    const payload = response.data?.data;
    if (Array.isArray(payload)) {
        return { items: payload, pagination: null };
    }
    if (payload && Array.isArray(payload.data)) {
        return { items: payload.data, pagination: payload };
    }
    throw new Error("Unexpected API response structure.");
}

function getError(error) {
    const data = error.response?.data;
    if (data?.errors) {
        return Object.values(data.errors).flat().join(" ");
    }
    return data?.message || error.message || "Request failed.";
}

function municipalityName(id) {
    const municipality = state.municipalities.find(
        (item) => String(item.id) === String(id),
    );
    return municipality?.name ?? "Unknown municipality";
}

function refreshMunicipalityOptions() {
    const select = $("location-municipality");
    const selected = select.value;

    select.replaceChildren(new Option("All municipalities", ""));

    state.municipalities.forEach((item) => {
        select.add(
            new Option(`${item.name} (${item.province ?? "—"})`, item.id),
        );
    });

    if ([...select.options].some((o) => o.value === selected)) {
        select.value = selected;
    }
}

async function loadMunicipalities() {
    const response = await api.get("/admin/municipalities");
    const result = unpack(response);
    state.municipalities = result.items;
    refreshMunicipalityOptions();
}

async function loadBarangays() {
    const params = {
        page: state.page,
    };

    const municipality = $("location-municipality").value;
    const status = $("location-status").value;
    const search = $("location-search").value.trim();

    if (municipality) {
        params.municipality_id = municipality;
    }

    if (status) {
        params.is_active = status === "active" ? 1 : 0;
    }

    if (search) {
        params.search = search;
    }

    const response = await api.get("/admin/barangays", {
        params,
    });

    const result = unpack(response);

    state.barangays = result.items;
    state.barangayPagination = result.pagination;

    if (result.pagination) {
        state.page = Number(result.pagination.current_page ?? state.page);
    }
}

function filteredMunicipalities() {
    const search = $("location-search").value.trim().toLowerCase();
    const status = $("location-status").value;

    return state.municipalities.filter((item) => {
        const matchesSearch = [item.name, item.province, item.psgc_code].some(
            (value) =>
                String(value ?? "")
                    .toLowerCase()
                    .includes(search),
        );

        const matchesStatus =
            !status ||
            (status === "active" && Boolean(item.is_active)) ||
            (status === "inactive" && !item.is_active);

        return matchesSearch && matchesStatus;
    });
}

function currentItems() {
    return state.tab === "municipalities"
        ? filteredMunicipalities()
        : state.barangays;
}

function pageData() {
    const items = currentItems();

    if (state.tab === "barangays" && state.barangayPagination) {
        const pagination = state.barangayPagination;
        return {
            items,
            total: Number(pagination.total ?? items.length),
            current: Number(pagination.current_page ?? 1),
            last: Number(pagination.last_page ?? 1),
        };
    }

    const last = Math.max(1, Math.ceil(items.length / state.perPage));
    state.page = Math.min(Math.max(1, state.page), last);

    return {
        items: items.slice(
            (state.page - 1) * state.perPage,
            state.page * state.perPage,
        ),
        total: items.length,
        current: state.page,
        last,
    };
}

function renderStats() {
    const items = currentItems();
    const pagination =
        state.tab === "barangays" ? state.barangayPagination : null;

    // Paginated barangay responses do not contain global status totals.
    if (pagination) {
        $("location-total").textContent = pagination.total ?? "—";
        $("location-active").textContent = "—";
        $("location-inactive").textContent = "—";
        return;
    }

    $("location-total").textContent = items.length;
    $("location-active").textContent = items.filter((item) =>
        Boolean(item.is_active),
    ).length;
    $("location-inactive").textContent = items.filter(
        (item) => !item.is_active,
    ).length;
}

function renderTable() {
    const isMunicipality = state.tab === "municipalities";

    $("location-head").innerHTML = isMunicipality
        ? `<tr>
            <th>Municipality</th><th>Province</th>
            <th>PSGC Code</th><th>Status</th><th>Action</th>
           </tr>`
        : `<tr>
            <th>Barangay</th><th>Municipality</th>
            <th>PSGC Code</th><th>Status</th><th>Action</th>
           </tr>`;

    const page = pageData();

    $("location-rows").innerHTML = page.items.length
        ? page.items
              .map((item) => {
                  const active = Boolean(item.is_active);
                  const location = isMunicipality
                      ? item.province
                      : municipalityName(item.municipality_id);

                  return `
                <tr>
                    <td><strong>${escapeHtml(item.name)}</strong></td>
                    <td>${escapeHtml(location ?? "—")}</td>
                    <td class="location-code">
                        ${escapeHtml(item.psgc_code ?? "—")}
                    </td>
                    <td>
                        <span class="location-status ${active ? "active" : "inactive"}">
                            ${active ? "Active" : "Inactive"}
                        </span>
                    </td>
                    <td>
                        <button type="button"
                            class="location-action ${active ? "deactivate" : "activate"}"
                            data-location-id="${escapeHtml(item.id)}"
                            ${state.busy ? "disabled" : ""}>
                            ${active ? "Deactivate" : "Activate"}
                        </button>
                    </td>
                </tr>`;
              })
              .join("")
        : `<tr><td colspan="5" class="location-empty">
                No matching records found.
           </td></tr>`;

    $("location-count").textContent = `${page.total} records`;
    $("location-page-label").textContent =
        `Page ${page.current} of ${page.last}`;

    $("location-prev").disabled = state.busy || page.current <= 1;
    $("location-next").disabled = state.busy || page.current >= page.last;

    renderStats();
}

async function load() {
    const requestId = ++state.requestId;
    clearNotice();

    $("location-rows").innerHTML =
        `<tr><td colspan="5" class="location-empty">Loading...</td></tr>`;

    try {
        if (state.tab === "municipalities") {
            await loadMunicipalities();
        } else {
            await loadBarangays();
        }

        if (requestId !== state.requestId) return;
        renderTable();
    } catch (error) {
        if (requestId !== state.requestId) return;

        $("location-rows").innerHTML =
            `<tr><td colspan="5" class="location-empty">
                Unable to load records.
            </td></tr>`;

        notify(getError(error), true);
    }
}

function changeTab(tab) {
    state.tab = tab;
    state.page = 1;
    $("location-search").value = "";
    $("location-status").value = "";
    $("location-municipality").value = "";

    document.querySelectorAll("[data-location-tab]").forEach((button) => {
        const active = button.dataset.locationTab === tab;
        button.classList.toggle("is-active", active);
        button.setAttribute("aria-selected", String(active));
    });

    $("location-municipality").hidden = tab !== "barangays";
    load();
}

function requestStatusChange(id) {
    if (state.busy) return;

    const item = currentItems().find((row) => String(row.id) === String(id));
    if (!item) return;

    const activate = !Boolean(item.is_active);

    state.pending = { item, activate, tab: state.tab };

    $("location-confirm-title").textContent =
        `${activate ? "Activate" : "Deactivate"} ${state.tab === "municipalities" ? "municipality" : "barangay"}?`;

    $("location-confirm-message").textContent =
        `Are you sure you want to ${activate ? "activate" : "deactivate"} "${item.name}"? ` +
        (state.tab === "municipalities" && !activate
            ? "This may affect barangay availability in operational forms."
            : "");

    $("location-confirm-submit").textContent = activate
        ? "Activate"
        : "Deactivate";

    $("location-confirm").showModal();
}

async function saveStatus() {
    if (!state.pending || state.busy) return;

    const { item, activate, tab } = state.pending;
    state.busy = true;
    renderTable();

    try {
        let response;

        if (tab === "municipalities") {
            response = await api.patch(
                `/admin/municipalities/${encodeURIComponent(item.id)}/${activate ? "activate" : "deactivate"}`,
            );
        } else {
            response = await api.patch(
                `/admin/barangays/${encodeURIComponent(item.id)}/status`,
                { is_active: activate },
            );
        }

        state.pending = null;
        await load();
        notify(response.data?.message || "Status updated successfully.");
    } catch (error) {
        notify(getError(error), true);
    } finally {
        state.busy = false;
        renderTable();
    }
}

document.querySelectorAll("[data-location-tab]").forEach((button) => {
    button.addEventListener("click", () => {
        changeTab(button.dataset.locationTab);
    });
});

$("location-refresh").addEventListener("click", load);

$("location-search").addEventListener("input", () => {
    state.page = 1;
    if (state.tab === "municipalities") {
        renderTable();
    }
});

$("location-search").addEventListener("change", () => {
    if (state.tab === "barangays") load();
});

$("location-status").addEventListener("change", () => {
    state.page = 1;
    if (state.tab === "municipalities") renderTable();
    else load();
});

$("location-municipality").addEventListener("change", () => {
    state.page = 1;
    load();
});

$("location-prev").addEventListener("click", () => {
    if (state.busy) return;

    const page = pageData();

    if (page.current <= 1) return;

    state.page = page.current - 1;

    if (state.tab === "barangays") {
        load();
    } else {
        renderTable();
    }
});

$("location-next").addEventListener("click", () => {
    if (state.busy) return;

    const page = pageData();

    if (page.current >= page.last) return;

    state.page = page.current + 1;

    if (state.tab === "barangays") {
        load();
    } else {
        renderTable();
    }
});

$("location-rows").addEventListener("click", (event) => {
    const button = event.target.closest("[data-location-id]");
    if (button) requestStatusChange(button.dataset.locationId);
});

$("location-confirm").addEventListener("close", (event) => {
    if (event.target.returnValue === "confirm") {
        saveStatus();
    } else {
        state.pending = null;
    }
});

load();
