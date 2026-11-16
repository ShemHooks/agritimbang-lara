import api from "../api";

const $ = (id) => document.getElementById(id);

const state = {
    schedules: [],
    registrationPage: 1,
    registrationLastPage: 1
};

const esc = (v) => String(v ?? "").replace(/[&<>"']/g, (c) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;"
})[c]);

const unwrap = (r) => r.data?.data ?? r.data;

function dateLabel(v) {
    if (!v) return "—";

    return new Date(`${String(v).slice(0, 10)}T00:00:00+08:00`).toLocaleDateString("en-PH", {
        timeZone: "Asia/Manila",
        month: "short",
        day: "numeric",
        year: "numeric"
    });
}

function dateTimeLabel(v) {
    return v
        ? new Date(v).toLocaleString("en-PH", {
            timeZone: "Asia/Manila",
            month: "short",
            day: "numeric",
            year: "numeric",
            hour: "numeric",
            minute: "2-digit"
        })
        : "—";
}

function timeLabel(v) {
    if (!v) return "—";

    const [h, m] = String(v).slice(0, 5).split(":").map(Number);

    return `${h % 12 || 12}:${String(m).padStart(2, "0")} ${h >= 12 ? "PM" : "AM"}`;
}

function statusBadge(status) {
    return `<span class="auction-status-badge is-${esc(status)}">${esc(String(status ?? "").replaceAll("_", " "))}</span>`;
}

function showAlert(message, error = false) {
    const el = $("fa-alert");

    el.textContent = message;
    el.className = `auction-alert${error ? " is-error" : ""}`;
    el.hidden = false;
}

function scheduleCard(s, open) {
    const registrationLabel = open
        ? "Registration Open"
        : `Opens ${dateTimeLabel(s.registration_opens_at)}`;

    return `<article class="auction-schedule-card">
        <div class="auction-card-top">
            <span class="auction-eyebrow">${esc(s.municipality?.name ?? "Municipal Auction")}</span>
            ${statusBadge(open ? "open" : "soon")}
        </div>
        <h3>${esc(s.title)}</h3>
        <p class="auction-card-date">${dateLabel(s.auction_date)} · ${timeLabel(s.start_time)}${s.expected_end_time ? ` – ${timeLabel(s.expected_end_time)}` : ""}</p>
        <p>${esc(s.venue)} · ${esc(s.barangay?.name ?? "")}</p>
        <div class="auction-card-meta">
            <span>${Number(s.registrations_count ?? 0)} livestock registered</span>
            <span class="auction-window-label ${open ? "is-open" : ""}">${registrationLabel}</span>
        </div>
        <a class="auction-primary-button" href="/auctions/${encodeURIComponent(s.id)}">View Schedule</a>
    </article>`;
}

function renderSchedules() {
    const open = state.schedules.filter((s) => s.registration_open);
    const soon = state.schedules.filter((s) => s.registration_has_not_opened);

    $("fa-open-count").textContent = `${open.length} open`;
    $("fa-soon-count").textContent = `${soon.length} soon`;

    $("fa-open-list").innerHTML = open.length
        ? open.map((s) => scheduleCard(s, true)).join("")
        : `<p class="auction-muted auction-empty-note">There are no auctions accepting registrations right now.</p>`;

    $("fa-soon-list").innerHTML = soon.length
        ? soon.map((s) => scheduleCard(s, false)).join("")
        : `<p class="auction-muted auction-empty-note">There are no upcoming registration periods at the moment.</p>`;
}

async function loadSchedules() {
    const response = await api.get("/farmer/auction-schedules");
    const data = unwrap(response);

    state.schedules = Array.isArray(data) ? data : [];

    renderSchedules();
}

function switchTab(tab) {
    document.querySelectorAll("[data-fa-tab]").forEach((b) => {
        const active = b.dataset.faTab === tab;

        b.classList.toggle("is-active", active);
        b.setAttribute("aria-selected", String(active));
    });

    $("fa-available-panel").hidden = tab !== "available";
    $("fa-registrations-panel").hidden = tab !== "registrations";

    if (tab === "registrations") {
        loadRegistrations().catch((e) => showAlert(e.response?.data?.message ?? e.message, true));
    }
}

async function loadRegistrations(page = state.registrationPage) {
    const response = await api.get("/farmer/auction-registrations", {
        params: {
            page
        }
    });

    const payload = response.data;
    const records = payload?.data ?? [];

    state.registrationPage = Number(payload?.current_page ?? 1);
    state.registrationLastPage = Number(payload?.last_page ?? 1);

    $("fa-registration-body").innerHTML = records.length
        ? records.map((r) => `<tr>
            <td>
                <strong>${esc(r.livestock?.species?.name ?? "Livestock")}</strong>
                <small>${esc(r.livestock?.breed?.name ?? "Unspecified Breed")}</small>
            </td>
            <td>
                <strong>${esc(r.schedule?.title ?? "Auction")}</strong>
                <small>${dateLabel(r.schedule?.auction_date)} · ${timeLabel(r.schedule?.start_time)}</small>
            </td>
            <td>${esc(r.schedule?.venue ?? "—")}</td>
            <td>${dateTimeLabel(r.registered_at)}</td>
            <td>${statusBadge(r.status)}</td>
            <td>${r.status === "registered" && r.schedule?.status === "published" && r.schedule?.registration_closes_at && new Date() < new Date(r.schedule.registration_closes_at)
                ? `<button type="button" class="auction-link-button is-danger" data-fa-withdraw="${esc(r.id)}">Withdraw</button>`
                : "—"}
            </td>
        </tr>`).join("")
        : `<tr>
            <td colspan="6" class="auction-empty-cell">You have not registered livestock for an auction yet.</td>
        </tr>`;

    $("fa-registration-page-info").textContent = `Page ${state.registrationPage} of ${state.registrationLastPage}`;

    $("fa-registration-prev").disabled = state.registrationPage <= 1;
    $("fa-registration-next").disabled = state.registrationPage >= state.registrationLastPage;
}

async function withdraw(id) {
    if (!confirm("Withdraw this livestock from the auction? You can register it again before the deadline if it remains eligible.")) {
        return;
    }

    try {
        const r = await api.post(`/farmer/auction-registrations/${encodeURIComponent(id)}/withdraw`);

        showAlert(r.data?.message || "Registration withdrawn.");

        await loadRegistrations();
        await loadSchedules();
    } catch (e) {
        showAlert(e.response?.data?.message ?? e.message ?? "Unable to withdraw registration.", true);
    }
}

function bind() {
    document.querySelectorAll("[data-fa-tab]").forEach((b) => {
        b.addEventListener("click", () => switchTab(b.dataset.faTab));
    });

    $("fa-refresh").addEventListener("click", () => {
        loadSchedules().catch((e) => showAlert(e.response?.data?.message ?? e.message, true));
    });

    $("fa-registration-body").addEventListener("click", (e) => {
        const b = e.target.closest("[data-fa-withdraw]");

        if (b) withdraw(b.dataset.faWithdraw);
    });

    $("fa-registration-prev").addEventListener("click", () => {
        loadRegistrations(Math.max(1, state.registrationPage - 1));
    });

    $("fa-registration-next").addEventListener("click", () => {
        loadRegistrations(Math.min(state.registrationLastPage, state.registrationPage + 1));
    });
}

async function init() {
    bind();

    try {
        const me = await api.get("/auth/me");
        const user = me.data?.data;

        if (!(user?.roles ?? []).some((r) => (r.slug ?? r) === "farmer")) {
            throw new Error("Only Farmers can access auction schedules.");
        }

        await loadSchedules();
    } catch (e) {
        showAlert(e.response?.data?.message ?? e.message ?? "Unable to load auction schedules.", true);
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}