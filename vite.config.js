import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/css/agritimbang-auth.css",
                "resources/js/app.js",
                "resources/js/pages/login.js",
                "resources/js/pages/dashboard.js",
                "resources/js/pages/register.js",
                "resources/js/pages/verify-email.js",
                "resources/js/pages/forgot-password.js",
                "resources/js/pages/reset-password.js",
                "resources/css/account-verification.css",
                "resources/js/pages/account-verification.js",
                "resources/css/app-shell.css",
                "resources/js/layouts/app-shell.js",
                "resources/css/dashboard-overview.css",
                "resources/css/price-analytics.css",
                "resources/js/pages/price-analytics.js",
                "resources/css/transaction-analytics.css",
                "resources/js/pages/transaction-analytics.js",
                "resources/css/valuation-analytics.css",
                "resources/js/pages/valuation-analytics.js",
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
