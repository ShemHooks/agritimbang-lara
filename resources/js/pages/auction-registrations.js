import api from "../api";

const $ = (id) => document.getElementById(id);

const root = $("auction-registrations-page");
const scheduleId = root?.dataset.scheduleId;

const state = {
    page: 1,
    lastPage: 1,
    total: 0
};

function unwrap(response) {
    return response.data?.data ?? response.data;
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

function dateTime(value) {
    return value
        ? new Date(value).toLocaleString("en-PH", {
            timeZone: "Asia/Manila",
            month: "short",
            day: "numeric",
            year: "numeric",
            hour: "numeric",
            minute: "2-digit"
        })
        : "—";
}

function status(value) {
    const safe = esc(value ?? "unknown");

    return `<span class="auction-status-badge is-${safe}">${safe.replaceAll("_", " ")}</span>`;
}

function alert(message, error = false) {
    const el = $("ar-alert");

    el.textContent = message;
    el.className = `auction-alert${error ? " is-error" : ""}`;
    el.hidden = false;
}

function fullName(user) {
    return [
        user?.first_name,
        user?.middle_name,
        user?.last_name
    ].filter(Boolean).join(" ") || "Farmer";
}

async function load(page = state.page) {
    const response = await api.get(
        `/lgu/auction-schedules/${encodeURIComponent(scheduleId)}/registrations`,
        {
            params: {
                page
            }
        }
    );

    const data = unwrap(response);
    const schedule = data.schedule;
    const paginator = data.registrations;
    const rows = paginator?.data ?? [];
    state.registrations = rows;

    state.page = Number(paginator?.current_page ?? 1);
    state.lastPage = Number(paginator?.last_page ?? 1);
    state.total = Number(paginator?.total ?? 0);

    $("ar-title").textContent = `${schedule.title} — Registrations`;

    $("ar-subtitle").textContent = `${new Date(`${String(schedule.auction_date).slice(0, 10)}T00:00:00`).toLocaleDateString("en-PH", {
        timeZone: "Asia/Manila",
        month: "short",
        day: "numeric",
        year: "numeric"
    })} · ${schedule.venue} · ${schedule.barangay?.name ?? ""}, ${schedule.municipality?.name ?? ""}`;

    $("ar-total").textContent = state.total;

    const counts = data.status_counts ?? {};

    $("ar-registered").textContent = Number(counts.registered ?? 0);
    $("ar-withdrawn").textContent = Number(counts.withdrawn ?? 0);
    $("ar-completed").textContent = Number(counts.completed ?? 0);

    $("ar-body").innerHTML = rows.length
        ? rows.map((r) => {
            const farmer = r.farmer?.user;
            const animal = r.livestock;

            return `<tr>
                <td>
                    <strong>${esc(fullName(farmer))}</strong>
                    <small>${esc(r.farmer?.farmer_code ?? "")}</small>
                </td>
                <td>
                    ${esc(farmer?.phone_number ?? "—")}
                    <small>${esc(farmer?.email ?? "")}</small>
                </td>
                <td>
                    <strong>${esc(animal?.species?.name ?? "Livestock")}</strong>
                    <small>${esc(animal?.breed?.name ?? "Unspecified Breed")}</small>
                </td>
                <td>${esc(animal?.sex ?? "—")}</td>
                <td>${esc(String(animal?.sale_purpose ?? "—").replaceAll("_", " "))}</td>
                <td>${dateTime(r.registered_at)}</td>
                <td>${esc(fullName(r.registered_by))}</td>
                <td>${status(r.status)}</td>
                <td><button type="button" class="auction-secondary-button ar-view-livestock" data-livestock-id="${esc(animal?.id ?? "")}">View</button></td>
            </tr>`;
        }).join("")
        : `<tr>
            <td colspan="9" class="auction-empty-cell">No livestock registrations are recorded for this auction.</td>
        </tr>`;

    $("ar-page-info").textContent = `Page ${state.page} of ${state.lastPage} · ${state.total} registration(s)`;

    $("ar-prev").disabled = state.page <= 1;
    $("ar-next").disabled = state.page >= state.lastPage;
}


function detailValue(value) {
    if (value === null || value === undefined || value === "") return "—";
    if (typeof value === "object") return value.name ?? value.label ?? "—";
    return String(value).replaceAll("_", " ");
}

function renderLivestockDetails(animal, registration) {
    const farmer = registration?.farmer?.user;
    const rows = [
        ["Livestock ID", animal.livestock_code ?? animal.tag_number ?? animal.id],
        ["Species", animal.species?.name], ["Breed", animal.breed?.name],
        ["Sex", animal.sex], ["Age", animal.age_months != null ? `${animal.age_months} month(s)` : animal.age_group],
        ["Sale purpose", animal.sale_purpose], ["Reproductive status", animal.reproductive_status],
        ["Parity", animal.parity], ["Condition score", animal.condition_score != null ? `${animal.condition_score}/5` : null],
        ["Actual weight", animal.actual_weight_kg != null ? `${animal.actual_weight_kg} kg` : null],
        ["Status", animal.status], ["Farmer", fullName(farmer)], ["Contact", farmer?.phone_number],
        ["Email", farmer?.email], ["Municipality", animal.municipality?.name], ["Barangay", animal.barangay?.name],
        ["Registered at", dateTime(registration?.registered_at ?? animal.created_at)],
    ];
    const photo = animal.photo_url ?? animal.photo_path_url ?? null;
    return `${photo ? `<div class="ar-livestock-photo"><img src="${esc(photo)}" alt="Livestock photo"></div>` : ""}<dl class="ar-livestock-detail-grid">${rows.map(([label, value]) => `<div><dt>${esc(label)}</dt><dd>${esc(detailValue(value))}</dd></div>`).join("")}</dl>`;
}

async function openLivestockDetails(registration) {
    const dialog = $("ar-livestock-dialog"); const body = $("ar-livestock-details");
    const id = registration?.livestock?.id; if (!dialog || !body || !id) return;
    body.innerHTML = "<p>Loading livestock details...</p>"; if (!dialog.open) dialog.showModal();
    try {
        const response = await api.get(`/lgu/livestock/${encodeURIComponent(id)}`);
        body.innerHTML = renderLivestockDetails(unwrap(response), registration);
    } catch { body.innerHTML = renderLivestockDetails(registration.livestock, registration); }
}

async function init() {
    if (!scheduleId) {
        alert("Auction schedule was not specified.", true);
        return;
    }

    try {
        const me = await api.get("/auth/me");
        const user = me.data?.data;

        if (!(user?.roles ?? []).some((r) => (r.slug ?? r) === "lgu_encoder")) {
            throw new Error("Only LGU Encoders can view auction registrations.");
        }

        await load();
    } catch (e) {
        alert(
            e.response?.data?.message ?? e.message ?? "Unable to load auction registrations.",
            true
        );

        $("ar-body").innerHTML = `<tr>
            <td colspan="9">Unable to load registrations.</td>
        </tr>`;
    }
}

$("ar-refresh")?.addEventListener("click", () => {
    load().catch((e) => alert(e.message, true));
});

$("ar-prev")?.addEventListener("click", () => {
    load(Math.max(1, state.page - 1)).catch((e) => alert(e.message, true));
});

$("ar-next")?.addEventListener("click", () => {
    load(Math.min(state.lastPage, state.page + 1)).catch((e) => alert(e.message, true));
});


$("ar-body")?.addEventListener("click", (event) => {
    const button = event.target.closest(".ar-view-livestock"); if (!button) return;
    const registration = (state.registrations ?? []).find((row) => String(row.livestock?.id) === String(button.dataset.livestockId));
    if (registration) openLivestockDetails(registration);
});
function closeLivestockDialog() { const dialog = $("ar-livestock-dialog"); if (dialog?.open) dialog.close(); }
$("ar-livestock-close")?.addEventListener("click", closeLivestockDialog);
$("ar-livestock-close-footer")?.addEventListener("click", closeLivestockDialog);

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}