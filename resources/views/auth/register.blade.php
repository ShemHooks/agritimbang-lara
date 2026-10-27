<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Create Account · AgriTimbang</title>
    <link rel="icon" href="{{ asset('assets/logo.png') }}">

    @vite([
        'resources/css/agritimbang-auth.css',
        'resources/js/pages/register.js'
    ])
</head>

<body>
    <main class="login-page">
        <section class="login-story">
            <div class="logo">
                <div class="logo-mark">
                    <img src="{{ asset('assets/logo.png') }}" alt="AgriTimbang Logo" class="auth-logo">
                </div>
                <div>
                    <div class="logo-name">AgriTimbang</div>
                    <div class="logo-tagline">
                        Presyong patas. Timbang na tapat.
                    </div>
                </div>
            </div>

            <div class="story-content">
                <div class="eyebrow light">Farmer Registration</div>
                <h1>Mas malinaw na presyo para sa bawat alaga.</h1>
                <p>
                    Create your farmer account to access livestock
                    monitoring, valuation, and local price information.
                </p>
            </div>
        </section>

        <section class="login-panel">
            <div class="login-card">
                <div class="eyebrow">Join AgriTimbang</div>
                <h2>Create your farmer account</h2>
                <p class="muted">
                    Enter your information to get started.
                </p>

                <form id="register-form">
                    <div id="register-error" class="form-error" role="alert" aria-live="polite"></div>

                    <label class="field">
                        <span>First name</span>
                        <input name="first_name" required maxlength="255" autocomplete="given-name">
                        <small data-error="first_name"></small>
                    </label>

                    <label class="field">
                        <span>Middle name (optional)</span>
                        <input name="middle_name" maxlength="255">
                        <small data-error="middle_name"></small>
                    </label>

                    <label class="field">
                        <span>Last name</span>
                        <input name="last_name" required maxlength="255" autocomplete="family-name">
                        <small data-error="last_name"></small>
                    </label>

                    <label class="field">
                        <span>Email address</span>
                        <input name="email" type="email" required maxlength="255" autocomplete="email">
                        <small data-error="email"></small>
                    </label>

                    <label class="field">
                        <span>Phone number (optional)</span>
                        <input name="phone_number" type="tel" maxlength="20" autocomplete="tel">
                        <small data-error="phone_number"></small>
                    </label>

                    <label class="field">
                        <span>Password</span>
                        <input name="password" type="password" minlength="8" required autocomplete="new-password">

                        <small data-error="password"></small>
                    </label>

                    <label class="field">
                        <span>Confirm password</span>
                        <input name="password_confirmation" type="password" minlength="8" required
                            autocomplete="new-password">
                    </label>

                    <button type="submit" class="primary-button">
                        Create account
                    </button>
                </form>

                <div class="register-prompt">
                    <div>
                        <strong>Already have an account?</strong>
                        <span>Sign in to your workspace.</span>
                    </div>
                    <a class="register-link" href="{{ route('login') }}">
                        Sign in →
                    </a>
                </div>
            </div>

            <p class="copyright">
                AgriTimbang Research and Development Team
            </p>
        </section>
    </main>
</body>

</html>