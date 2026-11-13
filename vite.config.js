import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // ==========================================
                // CSS STYLESHEETS
                // ==========================================

                // Global & Layout
                "resources/css/app.css",
                "resources/css/app-shell.css",
                "resources/css/agritimbang-auth.css",
                "resources/css/dashboard.css",

                // Authentication & Accounts
                "resources/css/account-verification.css",
                "resources/css/admin-users.css",
                "resources/css/farmer-profile.css",
                "resources/css/lgu-encoders.css",
                "resources/css/lgu-farmer-verifications.css",

                // Livestock
                "resources/css/livestock-directory.css",
                "resources/css/livestock-management.css",
                "resources/css/livestock-register.css",
                "resources/css/livestock-details.css",
                "resources/css/livestock-monitoring.css",
                "resources/css/farmer-directory.css",

                // Pricing
                "resources/css/prices.css",
                "resources/css/price-form.css",
                "resources/css/price-history.css",
                "resources/css/price-approvals.css",
                "resources/css/price-review.css",

                // Analytics
                "resources/css/price-analytics.css",
                "resources/css/transaction-analytics.css",
                "resources/css/valuation-analytics.css",
                "resources/css/forecast.css",

                // Transactions
                "resources/css/transaction-create.css",
                "resources/css/buyer-confirmation.css",
                "resources/css/lgu-transaction-verification.css",
                "resources/css/dispute-resolution.css",

                // Tools & Administration
                "resources/css/calculator.css",
                "resources/css/audit-logs.css",

                // location management
                "resources/css/location-management.css",

                // species and breed
                "resources/css/species-breeds.css",

                // auction house
                "resources/css/auction-houses.css",

                // GIS
                "resources/css/lgu-gis-monitoring.css",

                // Profile
                "resources/css/profile.css",

                // ==========================================
                // JAVASCRIPT ENTRY POINTS
                // ==========================================

                // Core & Layout
                "resources/js/App.js",
                "resources/js/layouts/app-shell.js",

                // Authentication
                "resources/js/pages/login.js",
                "resources/js/pages/register.js",
                "resources/js/pages/verify-email.js",
                "resources/js/pages/forgot-password.js",
                "resources/js/pages/reset-password.js",
                "resources/js/pages/account-verification.js",
                "resources/js/pages/lgu-farmer-verifications.js",

                // Dashboard & Accounts
                "resources/js/pages/dashboard.js",
                "resources/js/pages/admin-users.js",
                "resources/js/pages/farmer-profile.js",
                "resources/js/pages/lgu-encoders.js",

                // Livestock
                "resources/js/pages/farmer-directory.js",
                "resources/js/pages/livestock-directory.js",
                "resources/js/pages/livestock-management.js",
                "resources/js/pages/livestock-register.js",
                "resources/js/pages/livestock-common.js",
                "resources/js/pages/livestock-show.js",
                "resources/js/pages/livestock-edit.js",
                "resources/js/pages/livestock-monitoring.js",

                // Pricing
                "resources/js/pages/prices.js",
                "resources/js/pages/price-form.js",
                "resources/js/pages/price-history.js",
                "resources/js/pages/price-approvals.js",
                "resources/js/pages/price-review.js",

                // Analytics
                "resources/js/pages/price-analytics.js",
                "resources/js/pages/transaction-analytics.js",
                "resources/js/pages/valuation-analytics.js",
                "resources/js/pages/forecast.js",

                // Transactions
                "resources/js/pages/transaction-create.js",
                "resources/js/pages/buyer-confirmation.js",
                "resources/js/pages/lgu-transaction-verification.js",
                "resources/js/pages/dispute-resolution.js",

                // Tools & Administration
                "resources/js/pages/calculator.js",
                "resources/js/pages/audit-logs.js",

                // location management
                "resources/js/pages/location-management.js",

                // species and breed
                "resources/js/pages/species-breeds.js",

                // auction house
                "resources/js/pages/auction-houses.js",

                // GIS
                "resources/js/pages/lgu-gis-monitoring.js",

                // Profile
                "resources/js/pages/profile.js",

                "resources/css/transaction-assist.css",
                "resources/js/pages/transaction-assist.js",

                "resources/css/reports.css",
                "resources/js/pages/reports.js",
            ],

            refresh: true,
        }),

        tailwindcss(),
    ],
});
