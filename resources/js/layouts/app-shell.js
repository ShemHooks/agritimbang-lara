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
        {
            label: "Official Prices",
            icon: "badge-philippine-peso",
            href: "/prices",
        },
        { label: "Calculators", icon: "calculator", href: "/calculator" },
        {
            label: "Transactions",
            icon: "arrow-left-right",
            href: "/transactions/create",
        },
        { label: "Profile Settings", icon: "settings", href: "/profile" },
    ],

    lgu_authority: [
        { label: "Overview", icon: "layout-dashboard", href: "/dashboard" },
        { label: "Livestock Management", icon: "beef", href: "/livestock" },
        { label: "Farmer Directory", icon: "users-round", href: "/farmers" },
        {
            label: "Price Analytics",
            icon: "chart-no-axes-column-increasing",
            href: "/analytics/prices",
        },
        {
            label: "Price Forecast",
            icon: "chart-no-axes-combined",
            href: "/analytics/forecast",
        },
        {
            label: "Transaction Analytics",
            icon: "arrow-left-right",
            href: "/analytics/transactions",
        },
        {
            label: "Valuation Analytics",
            icon: "chart-pie",
            href: "/analytics/valuations",
        },
        {
            label: "Validate Transactions",
            icon: "clipboard-check",
            href: "/transactions/review",
        },
        {
            label: "Encoder Management",
            icon: "user-cog",
            href: "/lgu/encoders",
        },
        {
            label: "Price Management",
            icon: "philippine-peso",
            href: "/prices",
        },
        { label: "Market Monitoring", icon: "map-pinned", href: "/monitoring" },
    ],

    lgu_encoder: [
        { label: "Overview", icon: "layout-dashboard", href: "/dashboard" },
        { label: "Livestock Management", icon: "beef", href: "/livestock" },
        {
            label: "Price Analytics",
            icon: "chart-no-axes-column-increasing",
            href: "/analytics/prices",
        },
        {
            label: "Price Forecast",
            icon: "chart-no-axes-combined",
            href: "/analytics/forecast",
        },
        {
            label: "Transaction Analytics",
            icon: "arrow-left-right",
            href: "/analytics/transactions",
        },
        {
            label: "Valuation Analytics",
            icon: "chart-pie",
            href: "/analytics/valuations",
        },
        {
            label: "Assist Transaction",
            icon: "handshake",
            href: "/transactions/assist",
        },
        { label: "Draft Weekly Price", icon: "file-pen-line", href: "/prices" },
        { label: "Calculators", icon: "calculator", href: "/calculator" },
    ],

    admin: [
        { label: "Overview", icon: "layout-dashboard", href: "/dashboard" },
        { label: "Livestock Management", icon: "beef", href: "/livestock" },
        { label: "Farmer Directory", icon: "users-round", href: "/farmers" },
        {
            label: "Price Analytics",
            icon: "chart-no-axes-column-increasing",
            href: "/analytics/prices",
        },
        {
            label: "Price Forecast",
            icon: "chart-no-axes-combined",
            href: "/analytics/forecast",
        },
        {
            label: "Transaction Analytics",
            icon: "arrow-left-right",
            href: "/analytics/transactions",
        },
        {
            label: "Valuation Analytics",
            icon: "chart-pie",
            href: "/analytics/valuations",
        },
        { label: "User Management", icon: "users-round", href: "/users" },
        { label: "System Settings", icon: "settings", href: "/settings" },
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
    if (!elements.navigation) return;

    const currentPath = getCurrentPath();

    const navigation = NAV[role] ?? [];

    elements.navigation.innerHTML = navigation
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
                    <span class="nav-icon">
                        <i data-lucide="${escapeHtml(item.icon)}"></i>
                    </span>

                    <span>${escapeHtml(item.label)}</span>

                    ${!implemented ? "<small>Soon</small>" : ""}
                </a>
            `;
        })
        .join("");

    elements.navigation.querySelectorAll("[data-planned]").forEach((link) => {
        link.addEventListener("click", (event) => {
            event.preventDefault();
        });
    });

    // IMPORTANT: Initialize after navigation HTML exists.
    initializeIcons();

    updateBreadcrumb(currentPath, navigation);
}

/* ============================================================
   DYNAMIC BREADCRUMBS
============================================================ */

function updateBreadcrumb(currentPath, navigation) {
    const allNavigationItems = Object.values(NAV).flat();

    const currentItem =
        navigation.find((item) => item.href === currentPath) ??
        allNavigationItems.find((item) => item.href === currentPath);

    const pageName = currentItem?.label ?? "Overview";

    setText("current-page", pageName);

    // Keep the existing Blade title for pages that define one.
    // The breadcrumb is updated independently.
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
