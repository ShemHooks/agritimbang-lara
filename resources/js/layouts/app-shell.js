import api from "../api";
import "./notification-center";

const $ = (id) => document.getElementById(id);
const shell = $("app-shell");
const sidebar = $("app-sidebar");
const backdrop = $("sidebar-backdrop");
const menuToggle = $("menu-toggle");
const dropdown = $("user-dropdown");
const menuButton = $("user-menu-button");

const NAV = {
    farmer: [
        {
            label: "Overview",
            icon: "▦",
            href: "/dashboard",
        },
        {
            label: "My Livestock",
            icon: "♧",
            href: "/livestock",
        },
        {
            label: "Official Prices",
            icon: "₱",
            href: "/prices",
        },
        {
            label: "Calculators",
            icon: "▤",
            href: "/calculator",
        },
        {
            label: "Transactions",
            icon: "⇄",
            href: "/transactions",
        },
        {
            label: "Profile Settings",
            icon: "⚙",
            href: "/profile",
        },
    ],

    lgu_authority: [
        {
            label: "Overview",
            icon: "▦",
            href: "/dashboard",
        },
        {
            label: "Livestock Management",
            icon: "♧",
            href: "/livestock",
        },
        {
            label: "Farmer Directory",
            icon: "♧",
            href: "/farmers",
        },
        {
            label: "Price Analytics",
            icon: "↗",
            href: "/analytics/prices",
        },
        {
            label: "Transaction Analytics",
            icon: "⇄",
            href: "/analytics/transactions",
        },
        {
            label: "Valuation Analytics",
            icon: "▤",
            href: "/analytics/valuations",
        },
        {
            label: "Validate Transactions",
            icon: "✓",
            href: "/transactions/review",
        },
        {
            label: "Encoder Management",
            icon: "♙",
            href: "/lgu/encoders",
        },
        {
            label: "Price Management",
            icon: "₱",
            href: "/prices/manage",
        },
        {
            label: "Market Monitoring",
            icon: "⌁",
            href: "/monitoring",
        },
        {
            label: "Calculators",
            icon: "▤",
            href: "/calculator",
        },
    ],

    lgu_encoder: [
        {
            label: "Overview",
            icon: "▦",
            href: "/dashboard",
        },
        {
            label: "Livestock Management",
            icon: "♧",
            href: "/livestock",
        },
        {
            label: "Price Analytics",
            icon: "↗",
            href: "/analytics/prices",
        },
        {
            label: "Transaction Analytics",
            icon: "⇄",
            href: "/analytics/transactions",
        },
        {
            label: "Valuation Analytics",
            icon: "▤",
            href: "/analytics/valuations",
        },
        {
            label: "Assist Transaction",
            icon: "⇄",
            href: "/transactions/assist",
        },
        {
            label: "Draft Weekly Price",
            icon: "₱",
            href: "/prices/drafts",
        },
        {
            label: "Calculators",
            icon: "▤",
            href: "/calculator",
        },
    ],

    admin: [
        {
            label: "Overview",
            icon: "▦",
            href: "/dashboard",
        },
        {
            label: "Livestock Management",
            icon: "♧",
            href: "/livestock",
        },
        {
            label: "Farmer Directory",
            icon: "♧",
            href: "/farmers",
        },
        {
            label: "Price Analytics",
            icon: "↗",
            href: "/analytics/prices",
        },
        {
            label: "Transaction Analytics",
            icon: "⇄",
            href: "/analytics/transactions",
        },
        {
            label: "Valuation Analytics",
            icon: "▤",
            href: "/analytics/valuations",
        },
        {
            label: "User Management",
            icon: "♙",
            href: "/users",
        },
        {
            label: "System Settings",
            icon: "⚙",
            href: "/settings",
        },
    ],
};

const normalizeRole = (role) =>
    String(role ?? "")
        .toLowerCase()
        .replace(/[-\s]+/g, "_");
const esc = (s) =>
    String(s ?? "").replace(
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
const closeSidebar = () => {
    sidebar.classList.remove("sidebar-open");
    backdrop.hidden = true;
    menuToggle.setAttribute("aria-expanded", "false");
};
menuToggle.addEventListener("click", () => {
    const open = sidebar.classList.toggle("sidebar-open");
    backdrop.hidden = !open;
    menuToggle.setAttribute("aria-expanded", String(open));
});
backdrop.addEventListener("click", closeSidebar);
menuButton.addEventListener("click", () => {
    const open = dropdown.hidden;
    dropdown.hidden = !open;
    menuButton.setAttribute("aria-expanded", String(open));
});
document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
        closeSidebar();
        dropdown.hidden = true;
    }
});
document.addEventListener("click", (e) => {
    if (!e.target.closest(".user-menu-wrap")) dropdown.hidden = true;
});

async function logout() {
    try {
        await api.post("/auth/logout");
    } catch (e) {
        console.warn("Logout request failed", e);
    }
    sessionStorage.removeItem("agritimbang_token");
    window.location.replace("/login");
}
document
    .querySelectorAll("[data-logout]")
    .forEach((button) => button.addEventListener("click", logout));

async function init() {
    if (!sessionStorage.getItem("agritimbang_token")) {
        window.location.replace("/login");
        return;
    }
    try {
        const response = await api.get("/auth/me");
        const user = response.data?.data;
        if (!user) throw new Error("Unable to load account details.");
        const roleSlugs = (user.roles ?? []).map((r) => normalizeRole(r.slug));
        const role =
            ["admin", "lgu_authority", "lgu_encoder", "farmer"].find((r) =>
                roleSlugs.includes(r),
            ) ?? null;
        if (!role)
            throw new Error("No supported role is assigned to this account.");
        if (role === "farmer" && !user.email_verified_at) {
            window.location.replace("/verify-email");
            return;
        }
        if (role === "farmer" && user.verification_status !== "verified") {
            window.location.replace("/account-verification");
            return;
        }
        const name =
            [user.first_name, user.last_name].filter(Boolean).join(" ") ||
            user.email ||
            "Account";
        const firstName = user.first_name || "there";
        const roleLabel = {
            farmer: "Farmer",
            lgu_authority: "LGU Authority",
            lgu_encoder: "LGU Encoder",
            admin: "Administrator",
        }[role];
        $("sidebar-name").textContent = name;
        $("sidebar-role").textContent = roleLabel;
        $("header-name").textContent = firstName;
        $("dropdown-name").textContent = name;
        $("dropdown-email").textContent = user.email || "";
        $("sidebar-avatar").textContent = name.charAt(0).toUpperCase();
        $("header-avatar").textContent = name.charAt(0).toUpperCase();
        if ($("welcome-name")) $("welcome-name").textContent = firstName;
        if ($("verification-label"))
            $("verification-label").textContent =
                role === "farmer" ? "Verified" : "Active";
        if ($("verification-caption"))
            $("verification-caption").textContent =
                role === "farmer"
                    ? "LGU verification complete"
                    : roleLabel + " account";
        if ($("today-date"))
            $("today-date").textContent = new Intl.DateTimeFormat("en-PH", {
                dateStyle: "medium",
            }).format(new Date());

        const currentPath = window.location.pathname.replace(/\/$/, "") || "/";

        const IMPLEMENTED_ROUTES = new Set([
            "/dashboard",
            "/analytics/prices",
            "/analytics/transactions",
            "/analytics/valuations",
            "/farmers",
            "/livestock",
            "/users",
            "/lgu/encoders",
            "/calculator",
        ]);

        $("app-nav").innerHTML = NAV[role]
            .map((item) => {
                const active = currentPath === item.href;
                const implemented = IMPLEMENTED_ROUTES.has(item.href);

                return `
            <a
                class="nav-item ${active ? "nav-active" : ""} ${!implemented ? "nav-planned" : ""}"
                href="${implemented ? item.href : "#"}"
                ${active ? 'aria-current="page"' : ""}
                ${!implemented ? 'data-planned="true" title="Coming in a later phase"' : ""}
            >
                <span class="nav-icon">${esc(item.icon)}</span>
                <span>${esc(item.label)}</span>
                ${!implemented ? "<small>Soon</small>" : ""}
            </a>
        `;
            })
            .join("");

        $("app-nav")
            .querySelectorAll("[data-planned]")
            .forEach((link) =>
                link.addEventListener("click", (e) => e.preventDefault()),
            );

        $("app-loading").hidden = true;
        shell.hidden = false;
    } catch (error) {
        if (error.response?.status === 401) {
            sessionStorage.removeItem("agritimbang_token");
            window.location.replace("/login");
            return;
        }
        $("app-loading").hidden = true;
        $("app-error").hidden = false;
        $("app-error").textContent =
            error.response?.data?.message ||
            error.message ||
            "Unable to load workspace.";
    }
}
init();
