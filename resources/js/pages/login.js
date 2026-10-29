import api from "../api";

const form = document.querySelector("#login-form");

if (form) {
    const button = form.querySelector('button[type="submit"]');
    const errorBox = document.querySelector("#login-error");

    form.addEventListener("submit", async (event) => {
        event.preventDefault();

        errorBox.textContent = "";
        button.disabled = true;

        const originalText = button.textContent;
        button.textContent = "Signing in...";

        try {
            const response = await api.post("/auth/login", {
                email: form.elements.email.value.trim(),
                password: form.elements.password.value,
            });

            console.log("Login response:", {
                success: response.data?.success,
                dataKeys: Object.keys(response.data?.data ?? {}),
                hasToken: Boolean(response.data?.data?.token),
                hasUser: Boolean(response.data?.data?.user),
            });

            const token = response.data?.data?.token;
            const user = response.data?.data?.user;

            if (!response.data?.success || !token || !user) {
                throw new Error("Invalid authentication response from server.");
            }

            sessionStorage.setItem("agritimbang_token", token);

            const roles = user.roles ?? [];

            const isFarmer = roles.some((role) => role.slug === "farmer");

            if (!isFarmer) {
                window.location.replace("/dashboard");
                return;
            }

            if (!user.email_verified_at) {
                window.location.replace("/verify-email");
                return;
            }

            if (user.verification_status !== "verified") {
                window.location.replace("/account-verification");
                return;
            }

            window.location.replace("/dashboard");
        } catch (error) {
            errorBox.textContent =
                error.response?.data?.errors?.email?.[0] ??
                error.response?.data?.errors?.password?.[0] ??
                error.response?.data?.message ??
                error.message ??
                "Unable to sign in. Please try again.";
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    });
}

const toggle = document.querySelector("#password-toggle");
const password = document.querySelector("#login-password");

if (toggle && password) {
    toggle.addEventListener("click", () => {
        const visible = password.type === "password";

        password.type = visible ? "text" : "password";
        toggle.textContent = visible ? "Hide" : "Show";
        toggle.setAttribute("aria-pressed", String(visible));
    });
}
