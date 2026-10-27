import api from "../api";

const form = document.querySelector("#otp-form");
const codeInput = document.querySelector("#otp-code");
const verifyButton = document.querySelector("#verify-button");
const resendButton = document.querySelector("#resend-button");
const resendStatus = document.querySelector("#resend-status");
const notice = document.querySelector("#otp-notice");
const errorBox = document.querySelector("#otp-error");
const emailLabel = document.querySelector("#verification-email");

const PURPOSE = "email_verification";
const COOLDOWN_MS = 60_000;

let resendTimer = null;
let resendUntil = 0;

function showMessage(element, message) {
    notice.hidden = true;
    errorBox.hidden = true;

    element.textContent = message;
    element.hidden = false;
}

function errorMessage(error, field = "code") {
    return (
        error.response?.data?.errors?.[field]?.[0] ??
        error.response?.data?.errors?.otp?.[0] ??
        error.response?.data?.message ??
        "Something went wrong. Please try again."
    );
}

function startCooldown() {
    resendUntil = Date.now() + COOLDOWN_MS;

    clearInterval(resendTimer);

    function update() {
        const remaining = Math.max(
            0,
            Math.ceil((resendUntil - Date.now()) / 1000),
        );

        resendButton.disabled = remaining > 0;

        resendStatus.textContent =
            remaining > 0
                ? `Resend available in ${remaining}s`
                : "You can request another code.";

        if (remaining === 0) {
            clearInterval(resendTimer);
        }
    }

    update();
    resendTimer = setInterval(update, 1000);
}

async function sendOtp() {
    resendButton.disabled = true;

    try {
        await api.post("/otp/send", {
            purpose: PURPOSE,
        });

        startCooldown();

        showMessage(
            notice,
            "If eligible, a verification code has been sent to your email.",
        );
    } catch (error) {
        showMessage(errorBox, errorMessage(error, "otp"));

        // If the backend reports cooldown, avoid immediately
        // repeating the request.
        if (error.response?.status === 422) {
            startCooldown();
        } else {
            resendButton.disabled = false;
        }
    }
}

async function initialize() {
    const token = sessionStorage.getItem("agritimbang_token");

    if (!token) {
        window.location.replace("/login");
        return;
    }

    try {
        const response = await api.get("/auth/me");
        const user = response.data.data;

        emailLabel.textContent = user.email;

        if (user.email_verified_at) {
            window.location.replace("/account-verification");
            return;
        }

        // First request is triggered after authentication is confirmed.
        await sendOtp();
    } catch (error) {
        if (error.response?.status === 401) {
            sessionStorage.removeItem("agritimbang_token");
            window.location.replace("/login");
            return;
        }

        showMessage(
            errorBox,
            "Unable to load your account. Please refresh and try again.",
        );
    }
}

form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const code = codeInput.value.trim();

    if (!/^\d{6}$/.test(code)) {
        showMessage(errorBox, "Enter a valid six-digit code.");
        return;
    }

    verifyButton.disabled = true;
    verifyButton.textContent = "Verifying...";

    try {
        await api.post("/otp/verify", {
            purpose: PURPOSE,
            code,
        });

        showMessage(notice, "Email verified successfully!");

        window.location.replace("/account-verification");
    } catch (error) {
        showMessage(errorBox, errorMessage(error));
    } finally {
        verifyButton.disabled = false;
        verifyButton.textContent = "Verify email";
    }
});

resendButton.addEventListener("click", async () => {
    if (Date.now() < resendUntil) return;
    await sendOtp();
});

initialize();
