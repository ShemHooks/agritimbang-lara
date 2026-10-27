import api from "../api";

const form = document.querySelector("#register-form");

if (form) {
    const button = form.querySelector('button[type="submit"]');
    const errorBox = document.querySelector("#register-error");

    form.addEventListener("submit", async (event) => {
        event.preventDefault();

        errorBox.textContent = "";

        form.querySelectorAll("[data-error]").forEach((element) => {
            element.textContent = "";
        });

        button.disabled = true;
        const originalText = button.textContent;
        button.textContent = "Creating account...";

        const data = Object.fromEntries(new FormData(form).entries());

        data.middle_name = data.middle_name || null;
        data.phone_number = data.phone_number || null;

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
            const errors = error.response?.data?.errors;

            if (errors) {
                Object.entries(errors).forEach(([field, messages]) => {
                    const element = form.querySelector(
                        `[data-error="${field}"]`,
                    );

                    if (element) {
                        element.textContent = messages[0];
                        element.style.color = "#b91c1c";
                    }
                });
            } else {
                errorBox.textContent =
                    error.response?.data?.message ??
                    error.message ??
                    "Unable to create your account.";
            }
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    });
}
