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
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
