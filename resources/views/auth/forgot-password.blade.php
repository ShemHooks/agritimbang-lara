<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password · AgriTimbang</title>
    <link rel="icon" href="{{ asset('assets/logo.png') }}">

    @vite([
        'resources/css/agritimbang-auth.css',
        'resources/js/pages/forgot-password.js'
    ])
</head>

<body>
    <main class="login-page">
        <section class="login-story">
            <div class="logo">
                <div class="logo-mark">
                    <img src="{{ asset('assets/logo.png') }}" class="auth-logo" alt="AgriTimbang">
                </div>
                <div>
                    <div class="logo-name">AgriTimbang</div>
                    <div class="logo-tagline">
                        Presyong patas. Timbang na tapat.
                    </div>
                </div>
            </div>

            <div class="story-content">
                <div class="eyebrow light">Account Recovery</div>
                <h1>Balik sa iyong AgriTimbang account.</h1>
                <p>Recover your account securely and continue
                    accessing livestock information.</p>
            </div>
        </section>

        <section class="login-panel">
            <div class="login-card">
                <div class="eyebrow">Account Recovery</div>
                <h2>Forgot your password?</h2>
                <p class="muted">
                    Enter your registered email address and we'll
                    send you a password reset link.
                </p>

                <div id="success-message" class="auth-notice" role="status" hidden></div>
                <div id="error-message" class="form-error" role="alert" hidden></div>

                <form id="forgot-password-form">
                    <label class="field">
                        <span>Email address</span>
                        <input type="email" name="email" placeholder="Enter your email address" autocomplete="email"
                            required>
                    </label>

                    <button type="submit" class="primary-button">
                        Send reset link
                    </button>
                </form>

                <div class="register-prompt">
                    <div>
                        <strong>Remember your password?</strong>
                        <span>Return to your account.</span>
                    </div>
                    <a href="{{ route('login') }}" class="register-link">Back to login →</a>
                </div>
            </div>
        </section>
    </main>
</body>

</html>