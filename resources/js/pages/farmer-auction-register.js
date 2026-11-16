import api from "../api";

const $ = (id) => document.getElementById(id);

const root = $("farmer-auction-register-page");
const scheduleId = root?.dataset.scheduleId;

const selected = new Set();

const esc = (v) => String(v ?? "").replace(/[&<>"']/g, (c) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;"
})[c]);

const unwrap = (r) => r.data?.data ?? r.data;

function alert(message, error = false) {
    const el = $("far-alert");

    el.textContent = message;
    el.className = `auction-alert${error ? " is-error" : ""}`;
    el.hidden = false;
}

function dateLabel(v) {
    return v
        ? new Date(`${String(v).slice(0, 10)}T00:00:00+08:00`).toLocaleDateString("en-PH", {
            timeZone: "Asia/Manila",
            month: "short",
            day: "numeric",
            year: "numeric"
        })
        : "—";
}

function placeholder(species) {
    const key = String(species ?? "").toLowerCase();

    if (key.includes("carabao")) return "/images/livestock-placeholders/carabao.svg";
    if (key.includes("cattle") || key.includes("cow")) return "/images/livestock-placeholders/cattle.svg";
    if (key.includes("goat")) return "/images/livestock-placeholders/goat.svg";

    return "/images/livestock-placeholders/hog.svg";
}

function livestockCard(item) {
    const weight = item.weight_kg == null
        ? "Not recorded"
        : `${Number(item.weight_kg).toFixed(2)} kg`;

    const image = item.photo_url || placeholder(item.species);

    return `<label class="auction-livestock-card auction-livestock-card-photo">
        <input type="checkbox" data-far-livestock="${esc(item.id)}">
        <img class="auction-livestock-photo" src="${esc(image)}" alt="${esc(item.species)} livestock photo" loading="lazy" onerror="this.onerror=null;this.src='${placeholder(item.species)}'">
        <span class="auction-livestock-card-body">
            <strong>${esc(item.species)}</strong>
            <small>${esc(item.breed || "Unspecified breed")} · ${esc(item.sex || "—")}</small>
            <span class="auction-livestock-facts">
                <span>Livestock ID: ${esc(item.id)}</span>
                <span>Age: ${item.age_months == null ? "Not recorded" : `${esc(item.age_months)} months`}</span>
                <span>Weight: ${esc(weight)}</span>
                <span>Sale purpose: ${esc(String(item.sale_purpose ?? "").replaceAll("_", " "))}</span>
                <span>Condition score: ${esc(item.condition_score ?? "—")}/5</span>
            </span>
        </span>
    </label>`;
}

function updateButton() {
    const n = selected.size;

    $("far-submit").disabled = n === 0;
    $("far-submit").textContent = n
        ? `Register ${n} Selected Livestock`
        : "Register Selected Livestock";
}

async function load() {
    const me = await api.get("/auth/me");
    const user = me.data?.data;

    if (!(user?.roles ?? []).some((r) => (r.slug ?? r) === "farmer")) {
        throw new Error("Only Farmers can register livestock for auction.");
    }

    const [scheduleResponse, livestockResponse] = await Promise.all([
        api.get(`/farmer/auction-schedules/${encodeURIComponent(scheduleId)}`),
        api.get(`/farmer/auction-schedules/${encodeURIComponent(scheduleId)}/eligible-livestock`)
    ]);

    const scheduleData = unwrap(scheduleResponse);
    const schedule = scheduleData.schedule;
    const livestock = unwrap(livestockResponse) || [];

    if (!scheduleData.can_register) {
        throw new Error(
            !scheduleData.farmer_verified
                ? "Your Farmer account must be verified before you can register livestock."
                : "Registration for this auction is not currently open."
        );
    }

    $("far-title").textContent = `${schedule.title} · ${dateLabel(schedule.auction_date)} · ${schedule.venue}`;

    $("far-back").href = `/auctions/${encodeURIComponent(scheduleId)}`;

    $("far-count").textContent = `${livestock.length} eligible`;

    $("far-list").innerHTML = livestock.length
        ? livestock.map(livestockCard).join("")
        : `<p class="auction-muted auction-empty-note">You do not have any eligible livestock available for this auction.</p>`;

    updateButton();
}

async function submit() {
    if (!selected.size || !scheduleId) return;

    const button = $("far-submit");

    button.disabled = true;

    try {
        const response = await api.post(
            `/farmer/auction-schedules/${encodeURIComponent(scheduleId)}/registrations`,
            {
                livestock_ids: [...selected]
            }
        );

        const count = response.data?.data?.length ?? selected.size;

        location.href = `/auctions/${encodeURIComponent(scheduleId)}?registered=1&count=${count}`;
    } catch (e) {
        alert(
            e.response?.data?.message
                ?? Object.values(e.response?.data?.errors ?? {}).flat().join(" ")
                ?? e.message
                ?? "Unable to register livestock.",
            true
        );

        button.disabled = false;
        updateButton();
    }
}

async function init() {
    if (!scheduleId) return alert("Auction schedule was not specified.", true);

    $("far-list").addEventListener("change", (e) => {
        const input = e.target.closest("[data-far-livestock]");

        if (!input) return;

        if (input.checked) {
            selected.add(String(input.dataset.farLivestock));
        } else {
            selected.delete(String(input.dataset.farLivestock));
        }

        updateButton();
    });

    $("far-submit").addEventListener("click", submit);

    try {
        await load();
    } catch (e) {
        alert(
            e.response?.data?.message
                ?? e.message
                ?? "Unable to load eligible livestock.",
            true
        );

        $("far-list").innerHTML = `<p class="auction-muted">Eligible livestock could not be loaded.</p>`;
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}