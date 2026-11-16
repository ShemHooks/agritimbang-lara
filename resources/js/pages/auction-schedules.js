import api from "../api";

const $ = (id) => document.getElementById(id);

const state = {
    schedules: [],
    page: 1,
    lastPage: 1,
    municipalityId: null,
    municipalityName: "",
    total: 0,
    venues: [],
    editingId: null,
    selectedId: null
};

function unwrap(response) {
    return response.data?.data ?? response.data;
}

function listOf(response) {
    const data = unwrap(response);

    if (Array.isArray(data)) return data;
    if (Array.isArray(data?.data)) return data.data;

    return [];
}

function esc(value) {
    return String(value ?? "").replace(/[&<>"']/g, (c) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;"
    })[c]);
}

function dateLabel(value) {
    if (!value) return "—";

    return new Date(`${String(value).slice(0, 10)}T00:00:00`).toLocaleDateString("en-PH", {
        month: "short",
        day: "numeric",
        year: "numeric"
    });
}

function timeLabel(value) {
    if (!value) return "—";

    const [h, m] = String(value).slice(0, 5).split(":").map(Number);

    return `${h % 12 || 12}:${String(m).padStart(2, "0")} ${h >= 12 ? "PM" : "AM"}`;
}

function dateTimeLabel(value) {
    if (!value) return "—";

    return new Date(value).toLocaleString("en-PH", {
        timeZone: "Asia/Manila",
        month: "short",
        day: "numeric",
        year: "numeric",
        hour: "numeric",
        minute: "2-digit"
    });
}

function localInput(value) {
    if (!value) return "";

    const d = new Date(value);
    const parts = new Intl.DateTimeFormat("en-CA", {
        timeZone: "Asia/Manila",
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
        hourCycle: "h23"
    }).formatToParts(d);

    const v = Object.fromEntries(parts.map((p) => [p.type, p.value]));

    return `${v.year}-${v.month}-${v.day}T${v.hour}:${v.minute}`;
}

function showAlert(message, kind = "success") {
    const el = $("as-alert");

    el.textContent = message;
    el.className = `auction-alert ${kind === "error" ? "is-error" : "is-success"}`;
    el.hidden = false;
    el.scrollIntoView({
        behavior: "smooth",
        block: "nearest"
    });
}

function hideAlert() {
    $("as-alert").hidden = true;
}

function showFormError(message) {
    const el = $("as-form-error");

    el.textContent = message;
    el.hidden = false;
}

function statusBadge(status) {
    return `<span class="auction-status-badge is-${esc(status)}">${esc(String(status).replaceAll("_", " "))}</span>`;
}

function errorText(error) {
    const errors = error.response?.data?.errors;

    if (errors) return Object.values(errors).flat().join(" ");

    return error.response?.data?.message || error.message || "The request could not be completed.";
}

async function loadContext() {
    const response = await api.get("/auth/me");
    const user = response.data?.data;

    if (!user || !(user.roles ?? []).some((r) => (r.slug ?? r) === "lgu_encoder")) {
        throw new Error("Only LGU Encoders can manage auction schedules.");
    }

    state.municipalityId = user.lgu_officer_profile?.municipality_id ?? null;

    if (!state.municipalityId) {
        throw new Error("Your Encoder account has no assigned municipality.");
    }

    const municipalities = await api.get("/reference/municipalities");
    const found = listOf(municipalities).find((m) => String(m.id) === String(state.municipalityId));

    state.municipalityName = found?.name ?? "Assigned Municipality";
    $("as-municipality").value = state.municipalityName;

    const assignmentResponse = await api.get("/lgu/encoder/auction-house-assignment");
    const assignment = assignmentResponse.data?.data ?? {};
    const assignmentNotice = $("as-assignment-notice");

    if (assignmentNotice) {
        assignmentNotice.hidden = false;
        assignmentNotice.classList.toggle("is-error", !assignment.assigned);
        assignmentNotice.textContent = assignment.assigned
            ? `You are assigned to ${(assignment.auction_houses ?? []).map((house) => house.name).join(", ")}. You can manage schedules only for your assigned auction house(s).`
            : "No auction house is assigned to your account. You can still assist farmers and encode regular LGU transactions. Ask your LGU Authority to assign an auction house if you need to manage auction schedules or encode auction-house sales.";
    }

    const venues = await api.get("/lgu/auction-schedules/venues");

    state.venues = listOf(venues);

    const select = $("as-venue");

    select.replaceChildren(new Option("Select an active Auction House", ""));

    state.venues.forEach((house) => {
        select.add(new Option(
            `${house.name} · ${house.barangay?.name ?? "Barangay not assigned"}`,
            house.id
        ));
    });

    updateVenueBarangay();

    if (!state.venues.length) {
        $("as-create-button").disabled = true;
    }
}

function updateVenueBarangay() {
    const selected = state.venues.find((house) => String(house.id) === String($("as-venue").value));

    $("as-barangay").value = selected?.barangay?.name ?? "";
}

function filteredSchedules() {
    const search = $("as-search").value.trim().toLowerCase();
    const status = $("as-status-filter").value;

    return state.schedules.filter((s) => {
        const matchesStatus = status === "all" || s.status === status;
        const haystack = `${s.title} ${s.venue} ${s.barangay?.name ?? ""}`.toLowerCase();

        return matchesStatus && (!search || haystack.includes(search));
    });
}

function renderSchedules() {
    const rows = filteredSchedules();

    $("as-table-body").innerHTML = rows.length
        ? rows.map((s) => {
            const actions = [
                `<button type="button" class="auction-action-button" data-as-details="${esc(s.id)}">View Details</button>`,
                `<a class="auction-action-button" href="/lgu/auction-schedules/${encodeURIComponent(s.id)}/registrations">View Registrations</a>`
            ].join(" ");

            return `<tr>
                <td>
                    <strong>${esc(s.title)}</strong>
                    <small>${esc(s.barangay?.name ?? "")}, ${esc(s.municipality?.name ?? state.municipalityName)}</small>
                </td>
                <td>
                    ${dateLabel(s.auction_date)}
                    <small>${timeLabel(s.start_time)}${s.expected_end_time ? ` – ${timeLabel(s.expected_end_time)}` : ""}</small>
                </td>
                <td>${esc(s.venue)}</td>
                <td>
                    ${dateTimeLabel(s.registration_opens_at)}
                    <small>Until ${dateTimeLabel(s.registration_closes_at)}</small>
                </td>
                <td>${Number(s.registrations_count ?? 0)}</td>
                <td>${statusBadge(s.status)}</td>
                <td>
                    <div class="auction-row-actions">${actions}</div>
                </td>
            </tr>`;
        }).join("")
        : `<tr><td colspan="7" class="auction-empty-cell">No auction schedules found.</td></tr>`;

    $("as-page-info").textContent = `Showing ${state.schedules.length} of ${state.total} schedule(s)`;

    $("as-prev").disabled = state.page <= 1;
    $("as-next").disabled = state.page >= state.lastPage;

    const total = state.total;

    $("as-count-total").textContent = total;
    $("as-count-published").textContent = state.schedules.filter((s) => s.status === "published").length;
    $("as-count-ongoing").textContent = state.schedules.filter((s) => s.status === "ongoing").length;
    $("as-count-registrations").textContent = state.schedules.reduce((n, s) => n + Number(s.registrations_count ?? 0), 0);
}

async function loadSchedules() {
    $("as-table-body").innerHTML = `<tr><td colspan="7">Loading auction schedules...</td></tr>`;

    const response = await api.get("/lgu/auction-schedules", {
        params: {
            page: state.page
        }
    });

    const payload = response.data;

    state.schedules = Array.isArray(payload?.data) ? payload.data : [];
    state.total = Number(payload?.total ?? state.schedules.length);
    state.lastPage = Number(payload?.last_page ?? 1);

    renderSchedules();
}

function openCreate() {
    state.editingId = null;
    state.editingStatus = null;

    $("as-form").reset();
    $("as-form-title").textContent = "Create Auction Schedule";
    $("as-municipality").value = state.municipalityName;
    $("as-form-error").hidden = true;

    $("as-draft-button").textContent = "Save as Draft";
    $("as-draft-button").dataset.asSubmit = "draft";
    $("as-draft-button").hidden = false;

    $("as-publish-button").textContent = "Publish Schedule";
    $("as-publish-button").hidden = false;

    $("as-form-dialog").showModal();
}

function openEdit(schedule) {
    if (!["draft", "published"].includes(schedule.status)) return;

    state.editingId = schedule.id;
    state.editingStatus = schedule.status;

    $("as-form").reset();
    $("as-form-title").textContent = schedule.status === "published"
        ? "Edit Published Auction Schedule"
        : "Edit Draft Auction Schedule";

    $("as-title").value = schedule.title ?? "";
    $("as-date").value = String(schedule.auction_date ?? "").slice(0, 10);
    $("as-start").value = String(schedule.start_time ?? "").slice(0, 5);
    $("as-end").value = String(schedule.expected_end_time ?? "").slice(0, 5);
    $("as-venue").value = schedule.auction_house_id ?? "";

    updateVenueBarangay();

    $("as-opens").value = localInput(schedule.registration_opens_at);
    $("as-closes").value = localInput(schedule.registration_closes_at);
    $("as-description").value = schedule.description ?? "";
    $("as-municipality").value = state.municipalityName;
    $("as-form-error").hidden = true;

    $("as-draft-button").textContent = "Save Changes";
    $("as-draft-button").dataset.asSubmit = "draft";
    $("as-draft-button").hidden = false;

    $("as-publish-button").hidden = schedule.status === "published";
    $("as-publish-button").textContent = "Publish Schedule";

    $("as-form-dialog").showModal();
}

function closeForm() {
    $("as-form-dialog").close();
}

function collectPayload(intent) {
    return {
        title: $("as-title").value.trim(),
        auction_date: $("as-date").value,
        start_time: $("as-start").value,
        expected_end_time: $("as-end").value,
        auction_house_id: $("as-venue").value,
        registration_opens_at: $("as-opens").value,
        registration_closes_at: $("as-closes").value,
        description: $("as-description").value.trim() || null,
        status: intent
    };
}

async function saveSchedule(event) {
    event.preventDefault();

    if (!$("as-form").reportValidity()) return;

    $("as-form-error").hidden = true;

    const intent = event.submitter?.dataset.asSubmit ?? "draft";
    const button = event.submitter;
    const buttons = [$("as-draft-button"), $("as-publish-button")];

    buttons.forEach((item) => {
        item.disabled = true;
    });

    try {
        const payload = collectPayload(intent);

        if (state.editingId) {
            await api.patch(`/lgu/auction-schedules/${encodeURIComponent(state.editingId)}`, payload);

            if (intent === "published" && state.editingStatus === "draft") {
                await api.patch(`/lgu/auction-schedules/${encodeURIComponent(state.editingId)}/status`, {
                    status: "published"
                });

                showAlert("Auction schedule published. Verified farmers in your municipality have been notified.");
            } else {
                showAlert(state.editingStatus === "published"
                    ? "Published auction schedule updated."
                    : "Draft auction schedule updated.");
            }
        } else {
            await api.post("/lgu/auction-schedules", payload);

            showAlert(intent === "published"
                ? "Auction schedule published. Verified farmers in your municipality have been notified."
                : "Auction schedule saved as draft.");
        }

        closeForm();
        await loadSchedules();

        if (state.selectedId) await viewSchedule(state.selectedId);
    } catch (error) {
        showFormError(errorText(error));
    } finally {
        buttons.forEach((item) => {
            item.disabled = false;
        });
    }
}

async function viewSchedule(id) {
    try {
        const response = await api.get(`/lgu/auction-schedules/${encodeURIComponent(id)}`);
        const schedule = unwrap(response);

        state.selectedId = id;
        state.selectedSchedule = schedule;

        $("as-detail-title").textContent = schedule.title;

        $("as-detail-subtitle").textContent = `${dateLabel(schedule.auction_date)} · ${timeLabel(schedule.start_time)} – ${timeLabel(schedule.expected_end_time)} · ${schedule.venue}`;

        const creator = schedule.creator
            ? [schedule.creator.first_name, schedule.creator.middle_name, schedule.creator.last_name].filter(Boolean).join(" ")
            : "Unknown";

        const actionButtons = [
            ["draft", "published"].includes(schedule.status)
                ? `<button type="button" class="auction-action-button" data-as-detail-edit="${esc(schedule.id)}">Edit Schedule</button>`
                : "",
            schedule.status === "draft"
                ? `<button type="button" class="auction-action-button" data-as-detail-status="published" data-as-id="${esc(schedule.id)}">Publish Schedule</button>`
                : "",
            ["draft", "published", "ongoing"].includes(schedule.status)
                ? `<button type="button" class="auction-action-button is-danger" data-as-detail-status="cancelled" data-as-id="${esc(schedule.id)}">Cancel Auction</button>`
                : ""
        ].filter(Boolean).join("");

        const detailHtml = `<div class="auction-detail-summary auction-schedule-metadata">
            <div><span>Status</span><strong>${statusBadge(schedule.status)}</strong></div>
            <div><span>Created By</span><strong>${esc(creator)}</strong></div>
            <div><span>Created On</span><strong>${dateTimeLabel(schedule.created_at)}</strong></div>
            <div><span>Last Updated</span><strong>${dateTimeLabel(schedule.updated_at)}</strong></div>
            <div><span>Municipality</span><strong>${esc(schedule.municipality?.name ?? "—")}</strong></div>
            <div><span>Barangay</span><strong>${esc(schedule.barangay?.name ?? "—")}</strong></div>
            <div><span>Venue</span><strong>${esc(schedule.venue ?? "—")}</strong></div>
            <div><span>Registration Opens</span><strong>${dateTimeLabel(schedule.registration_opens_at)}</strong></div>
            <div><span>Registration Closes</span><strong>${dateTimeLabel(schedule.registration_closes_at)}</strong></div>
            <div><span>Published On</span><strong>${dateTimeLabel(schedule.published_at)}</strong></div>
        </div>${schedule.description ? `<p class="auction-description">${esc(schedule.description)}</p>` : ""}
        <div class="auction-detail-actions">${actionButtons}<a class="auction-action-button" href="/lgu/auction-schedules/${encodeURIComponent(schedule.id)}/registrations">View Registrations</a></div>`;

        $("as-detail-content").innerHTML = `<section class="auction-schedule-details"><h3>Auction Details</h3>${detailHtml}</section>`;

        $("as-detail-panel").hidden = false;
        $("as-detail-panel").scrollIntoView({
            behavior: "smooth",
            block: "start"
        });
    } catch (error) {
        showAlert(errorText(error), "error");
    }
}

async function changeStatus(id, status) {
    const messages = {
        published: "Publish this schedule and notify verified farmers in your municipality?",
        cancelled: "Cancel this auction schedule? Registered livestock will be released."
    };

    if (!window.confirm(messages[status] ?? "Change schedule status?")) return;

    try {
        await api.patch(`/lgu/auction-schedules/${encodeURIComponent(id)}/status`, {
            status
        });

        showAlert(status === "published"
            ? "Auction published. Verified farmers have been notified."
            : "Auction schedule cancelled.");

        await loadSchedules();

        if (state.selectedId === id) await viewSchedule(id);
    } catch (error) {
        showAlert(errorText(error), "error");
    }
}

function bind() {
    $("as-create-button").addEventListener("click", openCreate);
    $("as-venue").addEventListener("change", updateVenueBarangay);
    $("as-form").addEventListener("submit", saveSchedule);

    document.querySelectorAll("[data-as-close]").forEach((el) => {
        el.addEventListener("click", closeForm);
    });

    $("as-refresh").addEventListener("click", () => {
        loadSchedules().catch((e) => showAlert(errorText(e), "error"));
    });

    $("as-search").addEventListener("input", renderSchedules);
    $("as-status-filter").addEventListener("change", renderSchedules);

    $("as-prev").addEventListener("click", () => {
        if (state.page > 1) {
            state.page--;
            loadSchedules().catch((e) => showAlert(errorText(e), "error"));
        }
    });

    $("as-next").addEventListener("click", () => {
        if (state.page < state.lastPage) {
            state.page++;
            loadSchedules().catch((e) => showAlert(errorText(e), "error"));
        }
    });

    $("as-table-body").addEventListener("click", (event) => {
        const details = event.target.closest("[data-as-details]");

        if (details) viewSchedule(details.dataset.asDetails);
    });

    $("as-detail-content").addEventListener("click", (event) => {
        const edit = event.target.closest("[data-as-detail-edit]");

        if (edit && state.selectedSchedule) {
            openEdit(state.selectedSchedule);
            return;
        }

        const action = event.target.closest("[data-as-detail-status]");

        if (action) changeStatus(action.dataset.asId, action.dataset.asDetailStatus);
    });

    $("as-close-detail").addEventListener("click", () => {
        $("as-detail-panel").hidden = true;
    });
}

async function init() {
    bind();

    try {
        await loadContext();
        await loadSchedules();
    } catch (error) {
        showAlert(errorText(error), "error");

        $("as-table-body").innerHTML = `<tr><td colspan="7">Unable to load auction schedules.</td></tr>`;
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}