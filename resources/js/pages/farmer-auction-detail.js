import api from "../api";

const $ = (id) => document.getElementById(id);

const root = $("farmer-auction-detail-page");
const id = root?.dataset.scheduleId;

const esc = (v) => String(v ?? "").replace(/[&<>"']/g, (c) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;"
})[c]);

const unwrap = (r) => r.data?.data ?? r.data;

function dateLabel(v) {
    return v
        ? new Date(`${String(v).slice(0, 10)}T00:00:00+08:00`).toLocaleDateString("en-PH", {
            timeZone: "Asia/Manila",
            month: "long",
            day: "numeric",
            year: "numeric"
        })
        : "—";
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

function alert(message, error = false) {
    const el = $("fad-alert");

    el.textContent = message;
    el.className = `auction-alert${error ? " is-error" : ""}`;
    el.hidden = false;
}

async function init() {
    if (!id) return alert("Auction schedule was not specified.", true);

    try {
        const me = await api.get("/auth/me");
        const user = me.data?.data;

        if (!(user?.roles ?? []).some((r) => (r.slug ?? r) === "farmer")) {
            throw new Error("Only Farmers can view auction schedules.");
        }

        const response = await api.get(`/farmer/auction-schedules/${encodeURIComponent(id)}`);
        const data = unwrap(response);
        const s = data.schedule;

        $("fad-title").textContent = s.title;

        $("fad-subtitle").textContent = `${dateLabel(s.auction_date)} · ${s.venue} · ${s.barangay?.name ?? ""}, ${s.municipality?.name ?? ""}`;

        $("fad-status").innerHTML = `<span class="auction-status-badge is-${esc(s.status)}">${esc(s.status)}</span>`;

        $("fad-details").innerHTML = `<div class="auction-detail-summary auction-schedule-metadata">
            <div>
                <span>Auction Date</span>
                <strong>${dateLabel(s.auction_date)}</strong>
            </div>
            <div>
                <span>Auction Time</span>
                <strong>${timeLabel(s.start_time)}${s.expected_end_time ? ` – ${timeLabel(s.expected_end_time)}` : ""}</strong>
            </div>
            <div>
                <span>Venue</span>
                <strong>${esc(s.venue)}</strong>
            </div>
            <div>
                <span>Barangay</span>
                <strong>${esc(s.barangay?.name ?? "—")}</strong>
            </div>
            <div>
                <span>Registration Opens</span>
                <strong>${dateTimeLabel(s.registration_opens_at)}</strong>
            </div>
            <div>
                <span>Registration Closes</span>
                <strong>${dateTimeLabel(s.registration_closes_at)}</strong>
            </div>
        </div>${s.description ? `<div class="auction-description">${esc(s.description)}</div>` : ""}`;

        if (data.can_register) {
            $("fad-register").href = `/auctions/${encodeURIComponent(id)}/register`;
            $("fad-actions").hidden = false;

            $("fad-registration-note").textContent = "Registration is open. You may select multiple eligible livestock records.";
        } else {
            $("fad-actions").hidden = true;

            $("fad-registration-note").textContent = !data.farmer_verified
                ? "Your Farmer account must be verified before you can register livestock."
                : data.registration_open
                    ? "You cannot register livestock for this schedule."
                    : (s.registration_has_not_opened
                        ? `Registration opens on ${dateTimeLabel(s.registration_opens_at)}.`
                        : "Registration is not currently open for this schedule.");
        }

        if (new URLSearchParams(location.search).get("registered") === "1") {
            alert("Your livestock registration was submitted successfully.");
        }
    } catch (e) {
        alert(e.response?.data?.message ?? e.message ?? "Unable to load auction details.", true);
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}