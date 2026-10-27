import api from "../api";

const form = document.querySelector("#forgot-password-form");
const button = form.querySelector('button[type="submit"]');
const success = document.querySelector("#success-message");
const errorBox = document.querySelector("#error-message");

form.addEventListener("submit", async (event) => {
    event.preventDefault();

    success.hidden = true;
    errorBox.hidden = true;
    button.disabled = true;
    button.textContent = "Sending...";

    try {
        const response = await api.post("/auth/forgot-password", {
            email: form.elements.email.value.trim(),
        });

        success.textContent = response.data.message;
        success.hidden = false;
    } catch (error) {
        errorBox.textContent =
            error.response?.data?.errors?.email?.[0] ??
            error.response?.data?.message ??
            "Unable to process your request.";
        errorBox.hidden = false;
    } finally {
        button.disabled = false;
        button.textContent = "Send reset link";
    }
});
