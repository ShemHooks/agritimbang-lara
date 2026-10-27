import api from "../api";

const form = document.querySelector("#reset-password-form");
const button = form.querySelector('button[type="submit"]');
const errorBox = document.querySelector("#reset-error");

form.addEventListener("submit", async (event) => {
    event.preventDefault();

    errorBox.hidden = true;
    button.disabled = true;
    button.textContent = "Resetting...";

    try {
        await api.post("/auth/reset-password", {
            token: form.elements.token.value,
            email: form.elements.email.value.trim(),
            password: form.elements.password.value,
            password_confirmation: form.elements.password_confirmation.value,
        });

        sessionStorage.removeItem("agritimbang_token");

        window.location.replace("/login?reset=success");
    } catch (error) {
        const errors = error.response?.data?.errors;

        errorBox.textContent =
            errors?.token?.[0] ??
            errors?.email?.[0] ??
            errors?.password?.[0] ??
            error.response?.data?.message ??
            "Unable to reset password.";

        errorBox.hidden = false;
    } finally {
        button.disabled = false;
        button.textContent = "Reset password";
    }
});
