<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Verify Email · AgriTimbang</title>
    <link rel="icon" href="{{ asset('assets/logo.png') }}">

    @vite([
        'resources/css/agritimbang-auth.css',
        'resources/js/pages/verify-email.js'
    ])
</head>

<body>
    <main class="login-page">
        <section class="login-story">
            <div class="logo">
                <div class="logo-mark">
                    <img src="{{ asset('assets/logo.png') }}" class="auth-logo" alt="AgriTimbang logo">
                </div>
                <div>
                    <div class="logo-name">AgriTimbang</div>
                    <div class="logo-tagline">
                        Presyong patas. Timbang na tapat.
                    </div>
                </div>
            </div>

            <div class="story-content">
                <div class="eyebrow light">
                    Account Security
                </div>
                <h1>Isang hakbang na lang!</h1>
                <p>
                    Verify your email address to help secure
                    your AgriTimbang farmer account.
                </p>
            </div>
        </section>

        <section class="login-panel">
            <div class="login-card">
                <div class="eyebrow">Email Verification</div>

                <h2>Check your inbox</h2>

                <p class="muted">
                    We'll send a six-digit verification code to
                    <strong id="verification-email">
                        your email address
                    </strong>.
                </p>

                <div id="otp-notice" class="auth-notice" role="status" aria-live="polite" hidden></div>

                <div id="otp-error" class="form-error" role="alert" aria-live="polite" hidden></div>

                <form id="otp-form">
                    <label class="field" for="otp-code">
                        <span>Verification code</span>

                        <input id="otp-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                            pattern="[0-9]{6}" maxlength="6" placeholder="000000" required>
                    </label>

                    <p class="muted">
                        Your code expires after 5 minutes.
                    </p>

                    <button type="submit" id="verify-button" class="primary-button">
                        Verify email
                    </button>
                </form>

                <div class="register-prompt">
                    <div>
                        <strong>Didn't receive a code?</strong>
                        <span id="resend-status">
                            You can request another one.
                        </span>
                    </div>

                    <button type="button" id="resend-button" class="register-link">
                        Resend code
                    </button>
                </div>
            </div>

            <p class="copyright">
                AgriTimbang Research and Development Team
            </p>
        </section>
    </main>
</body>

</html>