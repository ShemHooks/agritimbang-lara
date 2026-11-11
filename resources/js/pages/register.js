import api from "../api";

// ==========================================================
// ELEMENTS
// ==========================================================

const form = document.querySelector("#register-form");

if (form) {
    const submitButton = form.querySelector('button[type="submit"]');

    const errorBox = document.querySelector("#register-error");

    // ======================================================
    // HELPERS
    // ======================================================

    function clearErrors() {
        errorBox.textContent = "";

        form.querySelectorAll("[data-error]").forEach((element) => {
            element.textContent = "";
        });
    }

    function displayErrors(error) {
        const errors = error.response?.data?.errors;

        if (errors && typeof errors === "object") {
            Object.entries(errors).forEach(([field, messages]) => {
                const element = form.querySelector(`[data-error="${field}"]`);

                if (element) {
                    element.textContent = Array.isArray(messages)
                        ? messages[0]
                        : String(messages);

                    element.style.color = "#b91c1c";
                }
            });

            return;
        }

        errorBox.textContent =
            error.response?.data?.message ??
            error.message ??
            "Unable to create your account.";
    }

    function setSubmitting(submitting) {
        submitButton.disabled = submitting;

        submitButton.textContent = submitting
            ? "Creating account..."
            : "Create account";
    }

    // ======================================================
    // SHOW / HIDE PASSWORD
    // ======================================================

    form.querySelectorAll("[data-password-toggle]").forEach((button) => {
        const inputId = button.dataset.passwordToggle;
        const input = document.getElementById(inputId);

        if (!input) return;

        button.addEventListener("click", () => {
            const isHidden = input.type === "password";

            input.type = isHidden ? "text" : "password";

            button.textContent = isHidden ? "Hide" : "Show";

            button.setAttribute("aria-pressed", String(isHidden));

            button.setAttribute(
                "aria-label",
                isHidden ? "Hide password" : "Show password",
            );
        });
    });

    // ======================================================
    // FORM SUBMISSION
    // ======================================================

    form.addEventListener("submit", async (event) => {
        event.preventDefault();

        clearErrors();

        const data = Object.fromEntries(new FormData(form).entries());

        data.middle_name = data.middle_name?.trim() || null;
        data.phone_number = data.phone_number?.trim() || null;

        // Client-side password confirmation
        if (data.password !== data.password_confirmation) {
            const element = form.querySelector(
                '[data-error="password_confirmation"]',
            );

            if (element) {
                element.textContent =
                    "The password confirmation does not match.";

                element.style.color = "#b91c1c";
            }

            return;
        }

        setSubmitting(true);

        try {
            const response = await api.post("/auth/register/farmer", data);

            if (!response.data?.success) {
                throw new Error("Registration failed.");
            }

            const token = response.data?.data?.token;

            if (!token) {
                throw new Error("Registration token was not returned.");
            }

            sessionStorage.setItem("agritimbang_token", token);

            window.location.replace("/verify-email");
        } catch (error) {
            displayErrors(error);
        } finally {
            setSubmitting(false);
        }
    });
}
