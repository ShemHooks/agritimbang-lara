<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password · AgriTimbang</title>
    <link rel="icon" href="{{ asset('assets/logo.png') }}">

    @vite([
        'resources/css/agritimbang-auth.css',
        'resources/js/pages/reset-password.js'
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
                <div class="eyebrow light">Account Security</div>
                <h1>Panibagong password, ligtas na account.</h1>
                <p>Set a new password to regain access
                    to your AgriTimbang workspace.</p>
            </div>
        </section>

        <section class="login-panel">
            <div class="login-card">
                <div class="eyebrow">Password Recovery</div>
                <h2>Create a new password</h2>
                <p class="muted">
                    Your new password must contain at least
                    eight characters.
                </p>

                <div id="reset-error" class="form-error" role="alert" hidden></div>

                <form id="reset-password-form">
                    <input type="hidden" name="token" value="{{ $token }}">

                    <label class="field">
                        <span>Email address</span>
                        <input type="email" name="email" value="{{ $email }}" autocomplete="email" required>
                    </label>

                    <label class="field">
                        <span>New password</span>
                        <input type="password" name="password" minlength="8" autocomplete="new-password" required>
                    </label>

                    <label class="field">
                        <span>Confirm new password</span>
                        <input type="password" name="password_confirmation" minlength="8" autocomplete="new-password"
                            required>
                    </label>

                    <button type="submit" class="primary-button">
                        Reset password
                    </button>
                </form>

                <div class="register-prompt">
                    <a href="{{ route('login') }}" class="register-link">Back to login →</a>
                </div>
            </div>
        </section>
    </main>
</body>

</html>