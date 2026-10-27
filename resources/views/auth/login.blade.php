@vite(['resources/css/agritimbang-auth.css', 'resources/js/pages/login.js'])
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · AgriTimbang</title>
    <link rel="icon" href="{{ asset('assets/logo.png') }}">
</head>

<body>
    <main class="login-page">
        <section class="login-story">
            <div class="logo">
                <div class="logo-mark" aria-hidden="true"><img src="{{ asset('assets/logo.png') }}"
                        alt="AgriTimbang Logo" class="auth-logo"></div>
                <div>
                    <div class="logo-name">AgriTimbang</div>
                    <div class="logo-tagline">Presyong patas. Timbang na tapat.</div>
                </div>
            </div>
            <div class="story-content">
                <div class="eyebrow light">Municipal livestock intelligence</div>
                <h1>Mas malinaw na presyo para sa bawat alaga.</h1>
                <p>Local livestock prices and practical tools for farmers, buyers, and municipal agriculture teams.</p>
                <div class="story-stats">
                    <div><strong>4</strong><span>Species tracked</span></div>
                    <div><strong>LGU</strong><span>Local price data</span></div>
                    <div><strong>Weekly</strong><span>Price updates</span></div>
                </div>
            </div>
            <div class="login-story-footer">
                <div class="story-note"><span class="note-mark">i</span>
                    <p>Valuations are decision-support references. Final selling prices remain negotiated by the
                        parties.</p>
                </div><a class="photo-credit" href="https://images.unsplash.com/photo-1548550023-2bdb3c5beed7"
                    target="_blank" rel="noopener noreferrer">Farm photography via Unsplash</a>
            </div>
        </section>
        <section class="login-panel">
            <div class="mobile-logo">
                <div class="logo">
                    <div class="logo-mark" aria-hidden="true"><svg viewBox="0 0 64 64" focusable="false">
                            <path class="scale-beam" d="M11 17Q32 7 53 17" />
                            <path class="scale-stem" d="M32 16v35" />
                            <path class="scale-chain" d="M12 18 5 40m7-22 8 22m32-22-8 22m8-22 7 22" />
                            <path class="scale-pan" d="M3 40q9 13 19 0H3Zm28 0q9 13 19 0H31Z" />
                            <path class="scale-base" d="M24 51h16l5 5H19l5-5Z" />
                            <circle class="scale-pivot" cx="32" cy="15" r="3.5" />
                        </svg></div>
                    <div>
                        <div class="logo-name">AgriTimbang</div>
                        <div class="logo-tagline">Presyong patas. Timbang na tapat.</div>
                    </div>
                </div>
            </div>
            <div class="mobile-login-context">
                <div class="portal-status"><span class="portal-status-indicator"></span><span
                        class="portal-status-copy"><strong>Municipal portal</strong><small>Cebu</small></span>
                </div>
                <div class="mobile-livestock-stats"><span><strong>4</strong> species</span><span><strong>LGU</strong>
                        local prices</span><span><strong>Weekly</strong> updates</span></div>
            </div>
            <div class="login-card">
                <div class="portal-status desktop-portal-status"><span class="portal-status-indicator"></span><span
                        class="portal-status-copy"><strong>Municipal portal</strong><small>Cebu · livestock
                            services</small></span></div>
                <div class="eyebrow">Welcome back</div>
                <h2>Sign in to your account</h2>
                <p class="muted">Access your AgriTimbang workspace.</p>
                @if (session('status'))
                <div class="auth-notice" role="status">{{ session('status') }}</div>@endif
                <form id="login-form" method="POST">
                    @csrf <label class="field">
                        <span>Email address</span>
                        <input name="email" type="email" placeholder="Enter your email address" autocomplete="email"
                            required autofocus>
                    </label>
                    <label class="field"><span>Password</span>
                        <div class="password-wrap"><input id="login-password" name="password" type="password"
                                placeholder="Enter your password" autocomplete="current-password" required><button
                                type="button" class="show-password" id="password-toggle" aria-controls="login-password"
                                aria-pressed="false">Show</button></div>
                    </label>
                    <div class="forgot-password-row">
                        <a href="{{ route('password.request') }}" class="forgot-password">
                            Forgot password?
                        </a>
                    </div>

                    <div id="login-error" class="form-error" role="alert" aria-live="polite"></div>
                    <button type="submit" class="primary-button">Sign in</button>
                </form>
                <div class="register-prompt">
                    <div><strong>New to AgriTimbang?</strong><span>Create a farmer account and submit your verification
                            document.</span></div>@if (Route::has('register'))<a class="register-link"
                            href="{{ route('register') }}">Register account →</a>@else<span class="register-link"
                        title="Registration screen coming soon">Registration coming soon</span>@endif
                </div>
            </div>
            <p class="copyright"> Agritimbang Research and Development Team</p>
        </section>
    </main>
    <script>document.getElementById('password-toggle').addEventListener('click', function () { const field = document.getElementById('login-password'); const visible = field.type === 'password'; field.type = visible ? 'text' : 'password'; this.textContent = visible ? 'Hide' : 'Show'; this.setAttribute('aria-pressed', String(visible)); });</script>
</body>

</html>