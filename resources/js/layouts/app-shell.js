import api from "../api";
import "./notification-center";
import { createIcons, icons } from "lucide";

/**
 * AgriTimbang Application Shell
 * Handles authentication, navigation, layout interactions,
 * role-based menus, and Lucide icons.
 */

const $ = (id) => document.getElementById(id);

const elements = {
    shell: $("app-shell"),
    sidebar: $("app-sidebar"),
    backdrop: $("sidebar-backdrop"),
    menuToggle: $("menu-toggle"),
    dropdown: $("user-dropdown"),
    menuButton: $("user-menu-button"),
    navigation: $("app-nav"),
    loading: $("app-loading"),
    error: $("app-error"),
    currentPage: $("current-page"),
};

/* ============================================================
   NAVIGATION CONFIGURATION
============================================================ */

const NAV = {
    farmer: [
        { label: "Overview", icon: "layout-dashboard", href: "/dashboard" },
        { label: "My Livestock", icon: "beef", href: "/livestock" },
        { label: "Official Prices", icon: "philippine-peso", href: "/prices" },
        {
            label: "Transactions",
            icon: "arrow-left-right",
            href: "/transactions/create",
        },
        { label: "Calculators", icon: "calculator", href: "/calculator" },
        { label: "Profile Settings", icon: "settings", href: "/profile" },
    ],

    lgu_authority: [
        { label: "Overview", icon: "layout-dashboard", href: "/dashboard" },
        { label: "Livestock Management", icon: "beef", href: "/livestock" },
        { label: "Farmer Directory", icon: "users-round", href: "/farmers" },
        {
            label: "Farmer Verification",
            href: "/lgu/farmer-verifications",
            icon: "user-check",
            roles: ["lgu_authority"],
        },
        {
            label: "Auction Houses",
            icon: "warehouse",
            href: "/lgu/auction-houses",
        },

        { label: "Price Management", icon: "philippine-peso", href: "/prices" },
        {
            label: "Market Monitoring",
            href: "/lgu/gis-monitoring",
            icon: "map",
            roles: ["lgu_authority"],
        },

        {
            label: "Analytics",
            icon: "chart-no-axes-combined",
            children: [
                { label: "Price Analytics", href: "/analytics/prices" },
                { label: "Price Forecast", href: "/analytics/forecast" },
                {
                    label: "Transaction Analytics",
                    href: "/analytics/transactions",
                },
                { label: "Valuation Analytics", href: "/analytics/valuations" },
            ],
        },

        {
            label: "Transactions",
            icon: "arrow-left-right",
            children: [
                {
                    label: "Transaction Verification",
                    href: "/transactions/review",
                },
                { label: "Dispute Resolution", href: "/transactions/disputes" },
            ],
        },

        {
            label: "Administration",
            icon: "settings",
            children: [
                { label: "Encoder Management", href: "/lgu/encoders" },
                { label: "Audit Logs", href: "/audit-logs" },
            ],
        },

        { label: "Profile Settings", icon: "settings", href: "/profile" },
    ],

    lgu_encoder: [
        { label: "Overview", icon: "layout-dashboard", href: "/dashboard" },
        { label: "Livestock Management", icon: "beef", href: "/livestock" },
        { label: "Draft Weekly Price", icon: "file-pen-line", href: "/prices" },
        { label: "Calculators", icon: "calculator", href: "/calculator" },

        {
            label: "Analytics",
            icon: "chart-no-axes-combined",
            children: [
                { label: "Price Analytics", href: "/analytics/prices" },
                { label: "Price Forecast", href: "/analytics/forecast" },
                {
                    label: "Transaction Analytics",
                    href: "/analytics/transactions",
                },
                { label: "Valuation Analytics", href: "/analytics/valuations" },
            ],
        },

        {
            label: "Transactions",
            icon: "arrow-left-right",
            children: [
                { label: "Assist Transaction", href: "/transactions/assist" },
            ],
        },
        { label: "Profile Settings", icon: "settings", href: "/profile" },
    ],

    admin: [
        { label: "Overview", icon: "layout-dashboard", href: "/dashboard" },
        { label: "Livestock Management", icon: "beef", href: "/livestock" },
        { label: "Farmer Directory", icon: "users-round", href: "/farmers" },
        {
            label: "Species & Breeds",
            icon: "beef",
            href: "/admin/species-breeds",
        },

        {
            label: "Analytics",
            icon: "chart-no-axes-combined",
            children: [
                { label: "Price Analytics", href: "/analytics/prices" },
                { label: "Price Forecast", href: "/analytics/forecast" },
                {
                    label: "Transaction Analytics",
                    href: "/analytics/transactions",
                },
                { label: "Valuation Analytics", href: "/analytics/valuations" },
            ],
        },

        {
            label: "Administration",
            icon: "shield",
            children: [
                { label: "User Management", href: "/users" },
                { label: "Location Management", href: "/admin/locations" },
                { label: "Audit Logs", href: "/audit-logs" },
                { label: "System Settings", href: "/settings" },
            ],
        },
        { label: "Profile Settings", icon: "settings", href: "/profile" },
    ],
};

/* ============================================================
   IMPLEMENTED ROUTES
   Routes not listed here appear as "Soon".
============================================================ */

const IMPLEMENTED_ROUTES = new Set([
    "/dashboard",
    "/analytics/prices",
    "/analytics/transactions",
    "/analytics/valuations",
    "/analytics/forecast",
    "/farmers",
    "/livestock",
    "/users",
    "/lgu/encoders",
    "/calculator",
    "/prices",
    "/transactions/create",
    "/transactions/review",
    "/transactions/disputes",
    "/audit-logs",
    "/admin/locations",
    "/admin/species-breeds",
    "/lgu/auction-houses",
    "/lgu/gis-monitoring",
    "/lgu/farmer-verifications",
    "/profile",
]);

/* ============================================================
   HELPERS
============================================================ */

function normalizeRole(role) {
    return String(role ?? "")
        .toLowerCase()
        .replace(/[-\s]+/g, "_");
}

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (char) => {
        const entities = {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;",
        };

        return entities[char];
    });
}

function getCurrentPath() {
    return window.location.pathname.replace(/\/+$/, "") || "/";
}

function getPrimaryRole(user) {
    const roles = (user.roles ?? []).map((role) => normalizeRole(role.slug));

    const priority = ["admin", "lgu_authority", "lgu_encoder", "farmer"];

    return priority.find((role) => roles.includes(role)) ?? null;
}

function setText(id, value) {
    const element = $(id);

    if (element) {
        element.textContent = value ?? "";
    }
}

function initializeIcons() {
    createIcons({
        icons,
        attrs: {
            width: 19,
            height: 19,
            "stroke-width": 1.8,
            "aria-hidden": "true",
        },
    });
}

/* ============================================================
   SIDEBAR
============================================================ */

function closeSidebar() {
    if (!elements.sidebar || !elements.backdrop) return;

    elements.sidebar.classList.remove("sidebar-open");
    elements.backdrop.hidden = true;

    elements.menuToggle?.setAttribute("aria-expanded", "false");
}

function toggleSidebar() {
    if (!elements.sidebar || !elements.backdrop) return;

    const isOpen = elements.sidebar.classList.toggle("sidebar-open");

    elements.backdrop.hidden = !isOpen;
    elements.menuToggle?.setAttribute("aria-expanded", String(isOpen));
}

/* ============================================================
   USER DROPDOWN
============================================================ */

function closeDropdown() {
    if (!elements.dropdown) return;

    elements.dropdown.hidden = true;
    elements.menuButton?.setAttribute("aria-expanded", "false");
}

function toggleDropdown() {
    if (!elements.dropdown) return;

    const isOpen = elements.dropdown.hidden;

    elements.dropdown.hidden = !isOpen;
    elements.menuButton?.setAttribute("aria-expanded", String(isOpen));
}

/* ============================================================
   LOGOUT
============================================================ */

async function logout() {
    try {
        await api.post("/auth/logout");
    } catch (error) {
        console.warn("Logout request failed:", error);
    } finally {
        sessionStorage.removeItem("agritimbang_token");
        window.location.replace("/login");
    }
}

/* ============================================================
   EVENT LISTENERS
============================================================ */

function registerEventListeners() {
    elements.menuToggle?.addEventListener("click", toggleSidebar);

    elements.backdrop?.addEventListener("click", closeSidebar);

    elements.menuButton?.addEventListener("click", toggleDropdown);

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeSidebar();
            closeDropdown();
        }
    });

    document.addEventListener("click", (event) => {
        if (!event.target.closest(".user-menu-wrap")) {
            closeDropdown();
        }
    });

    document.querySelectorAll("[data-logout]").forEach((button) => {
        button.addEventListener("click", logout);
    });
}

/* ============================================================
   NAVIGATION RENDERER
============================================================ */

function renderNavigation(role) {
    const nav = elements.navigation;
    if (!nav) return;

    const items = NAV[role] || NAV.farmer;
    const currentPath = getCurrentPath();

    const isActive = (href) =>
        currentPath === href ||
        (href !== "/dashboard" && currentPath.startsWith(href + "/"));

    const renderLink = (item, nested = false) => {
        const active = isActive(item.href);
        const implemented = IMPLEMENTED_ROUTES.has(item.href);

        const classes = [
            "nav-item",
            nested ? "sidebar-sublink" : "",
            active ? "nav-active" : "",
            !implemented ? "nav-planned" : "",
        ]
            .filter(Boolean)
            .join(" ");

        const icon =
            !nested && item.icon
                ? `<span class="nav-icon"><i data-lucide="${item.icon}"></i></span>`
                : "";

        if (!implemented) {
            return `
                <span class="${classes}" aria-disabled="true">
                    ${icon}
                    <span>${escapeHtml(item.label)}</span>
                    <small>Soon</small>
                </span>
            `;
        }

        return `
            <a
                href="${item.href}"
                class="${classes}"
                ${active ? 'aria-current="page"' : ""}
            >
                ${icon}
                <span>${escapeHtml(item.label)}</span>
            </a>
        `;
    };

    nav.innerHTML = items
        .map((item, index) => {
            if (!item.children) {
                return renderLink(item);
            }

            const hasActiveChild = item.children.some((child) =>
                isActive(child.href),
            );

            return `
            <div class="sidebar-group ${hasActiveChild ? "is-open" : ""}">
                <button
                    type="button"
                    class="sidebar-group-toggle"
                    aria-expanded="${hasActiveChild}"
                    aria-controls="sidebar-group-${index}"
                >
                    <span class="nav-icon">
                        <i data-lucide="${item.icon}"></i>
                    </span>

                    <span>${escapeHtml(item.label)}</span>

                    <i
                        data-lucide="chevron-down"
                        class="sidebar-chevron"
                    ></i>
                </button>

                <div
                    id="sidebar-group-${index}"
                    class="sidebar-submenu"
                    ${hasActiveChild ? "" : "hidden"}
                >
                    ${item.children
                        .map((child) => renderLink(child, true))
                        .join("")}
                </div>
            </div>
        `;
        })
        .join("");

    nav.querySelectorAll(".sidebar-group-toggle").forEach((button) => {
        button.addEventListener("click", () => {
            const group = button.closest(".sidebar-group");
            const submenu = group.querySelector(".sidebar-submenu");

            const shouldOpen = submenu.hidden;

            submenu.hidden = !shouldOpen;
            group.classList.toggle("is-open", shouldOpen);

            button.setAttribute("aria-expanded", String(shouldOpen));
        });
    });

    initializeIcons();
}

/* ============================================================
   DYNAMIC BREADCRUMBS
============================================================ */

function updateBreadcrumb(currentPath, navigation) {
    const allItems = Object.values(NAV).flatMap((items) =>
        items.flatMap((item) => (item.children ? item.children : [item])),
    );

    const currentItem = allItems
        .filter(
            (item) =>
                currentPath === item.href ||
                (item.href !== "/dashboard" &&
                    currentPath.startsWith(item.href + "/")),
        )
        .sort((a, b) => b.href.length - a.href.length)[0];

    const pageName = currentPath.startsWith("/prices/approvals/")
        ? "Price Review Details"
        : (currentItem?.label ??
          document.title.split("|")[0].trim() ??
          "Overview");

    setText("current-page", pageName);
}

/* ============================================================
   USER INTERFACE
============================================================ */

function populateUserInformation(user, role) {
    const name =
        [user.first_name, user.last_name].filter(Boolean).join(" ") ||
        user.email ||
        "Account";

    const firstName = user.first_name || "there";

    const roleLabels = {
        farmer: "Farmer",
        lgu_authority: "LGU Authority",
        lgu_encoder: "LGU Encoder",
        admin: "Administrator",
    };

    const roleLabel = roleLabels[role] ?? "User";

    setText("sidebar-name", name);
    setText("sidebar-role", roleLabel);

    setText("header-name", firstName);
    setText("dropdown-name", name);
    setText("dropdown-email", user.email || "");

    setText("sidebar-avatar", name.charAt(0).toUpperCase());
    setText("header-avatar", name.charAt(0).toUpperCase());

    setText("welcome-name", firstName);

    setText("verification-label", role === "farmer" ? "Verified" : "Active");

    setText(
        "verification-caption",
        role === "farmer"
            ? "LGU verification complete"
            : `${roleLabel} account`,
    );

    const today = new Intl.DateTimeFormat("en-PH", {
        dateStyle: "medium",
    }).format(new Date());

    setText("today-date", today);
}

/* ============================================================
   AUTHENTICATION
============================================================ */

function redirectToLogin() {
    sessionStorage.removeItem("agritimbang_token");
    window.location.replace("/login");
}

function validateAccount(user, role) {
    if (role !== "farmer") {
        return true;
    }

    if (!user.email_verified_at) {
        window.location.replace("/verify-email");
        return false;
    }

    if (user.verification_status !== "verified") {
        window.location.replace("/account-verification");
        return false;
    }

    return true;
}

/* ============================================================
   APPLICATION INITIALIZATION
============================================================ */

async function initializeApp() {
    const token = sessionStorage.getItem("agritimbang_token");

    if (!token) {
        redirectToLogin();
        return;
    }

    try {
        const response = await api.get("/auth/me");

        const user = response.data?.data;

        if (!user) {
            throw new Error("Unable to load account details.");
        }

        const role = getPrimaryRole(user);

        if (!role) {
            throw new Error("No supported role is assigned to this account.");
        }

        if (!validateAccount(user, role)) {
            return;
        }

        populateUserInformation(user, role);

        renderNavigation(role);

        updateBreadcrumb(getCurrentPath(), NAV[role] || []);

        elements.loading.hidden = true;
        elements.shell.hidden = false;
    } catch (error) {
        if (error.response?.status === 401) {
            redirectToLogin();
            return;
        }

        if (elements.loading) {
            elements.loading.hidden = true;
        }

        if (elements.error) {
            elements.error.hidden = false;
            elements.error.textContent =
                error.response?.data?.message ||
                error.message ||
                "Unable to load workspace.";
        }

        console.error("AgriTimbang initialization failed:", error);
    }
}

/* ============================================================
   BOOTSTRAP
============================================================ */

registerEventListeners();

// Render static Lucide icons from the Blade layout.
initializeIcons();

// Authenticate and render role-based navigation.
initializeApp();
