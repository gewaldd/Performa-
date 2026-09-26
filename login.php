<?php
// The login page now uses Firebase Web SDK to authenticate on the client,
// exchanges the ID token with `session_login.php`, and then redirects.
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Performa — Login</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%23245fba'/%3E%3Cpath d='M16 53.3v-18.7M32 53.3V24M49.3 53.3V13.3' stroke='white' stroke-width='7' stroke-linecap='round' fill='none'/%3E%3C/svg%3E" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="Admin/styles.css" />
    <style>
        :root {
            --font-sans: -apple-system, BlinkMacSystemFont, "SF Pro Text", "SF Pro Display", "IBM Plex Sans", "Segoe UI", sans-serif;
            --font-mono: "JetBrains Mono", "Cascadia Code", monospace;
            --apple-blue: #0071e3;
            --apple-blue-dark: #0068d1;
            --apple-link: #0066cc;
            --apple-hairline: rgba(0, 0, 0, 0.08);
            --apple-ring: 0 0 0 4px rgba(0, 113, 227, 0.15);
        }

        body {
            margin: 0;
            font-family: var(--font-sans);
            background: radial-gradient(1200px 620px at 50% -10%, #e2ebff 0%, #f2f6fb 55%, #e4eaf3 100%);
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        .top-bar {
            width: 100%;
            height: 64px;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 28px;
            position: relative;
            z-index: 2;
        }

        .top-bar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .top-bar-mark {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: grid;
            place-items: center;
            background: linear-gradient(145deg, #0071e3, #42a1ff);
            color: #fff;
            box-shadow: 0 4px 14px rgba(0, 113, 227, 0.30);
        }

        .top-bar-mark svg {
            width: 19px;
            height: 19px;
        }

        .top-bar-name {
            color: var(--text);
            font-weight: 800;
            font-size: 18px;
            letter-spacing: -0.02em;
        }

        .top-bar::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(0, 113, 227, 0.25), transparent);
            pointer-events: none;
        }

        .login-wrap {
            min-height: calc(100vh - 64px);
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 32px 20px 48px;
            position: relative;
            z-index: 1;
        }

        .login-header {
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
            margin-bottom: 8px;
        }

        .login-header .brand-mark,
        .login-header .brand-name {
            display: none;
        }

        .login-subtitle {
            text-align: center;
            color: #42506a;
            font-size: 14px;
            font-weight: 500;
            margin: 0 0 28px;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
        }

        .login-panel {
            background: #ffffff;
            border: 1px solid rgba(15, 23, 42, 0.10);
            border-radius: 20px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.12), 0 2px 8px rgba(15, 23, 42, 0.07);
            padding: 36px 36px 32px;
        }

        .field-group {
            margin-bottom: 18px;
        }

        .field-label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 7px;
        }

        .field-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
        }

        .field-link {
            font-size: 12px;
            font-weight: 600;
            color: var(--apple-link);
            text-decoration: none;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
        }

        .field-link:hover {
            text-decoration: underline;
        }

        .field-link:focus-visible {
            outline: none;
            box-shadow: var(--apple-ring);
            border-radius: 4px;
        }

        .input-shell {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-shell svg {
            position: absolute;
            left: 14px;
            width: 16px;
            height: 16px;
            color: var(--muted);
            pointer-events: none;
        }

        .input-shell input {
            width: 100%;
            height: 48px;
            padding: 0 14px 0 42px;
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.12);
            background: #fff;
            color: var(--text);
            font: inherit;
            font-size: 15px;
            transition: border-color 150ms ease, box-shadow 150ms ease;
        }

        .input-shell input::placeholder {
            color: #9aa5b8;
        }

        .input-shell input:focus {
            outline: none;
            border-color: var(--apple-blue);
            box-shadow: var(--apple-ring);
        }

        .input-shell input:focus-visible {
            outline: none;
            border-color: var(--apple-blue);
            box-shadow: var(--apple-ring);
        }

        .input-shell.has-toggle input {
            padding-right: 40px;
        }

        .input-shell input:-webkit-autofill,
        .input-shell input:-webkit-autofill:hover,
        .input-shell input:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 100px #fff inset;
            -webkit-text-fill-color: var(--text);
            caret-color: var(--text);
            transition: background-color 9999s ease-in-out 0s;
        }

        .toggle-visibility {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--muted);
            display: grid;
            place-items: center;
            padding: 4px;
            border-radius: 6px;
        }

        .toggle-visibility:hover {
            color: var(--text);
        }

        .toggle-visibility:focus-visible {
            outline: none;
            box-shadow: var(--apple-ring);
        }

        .toggle-visibility svg {
            position: static;
            width: 18px;
            height: 18px;
        }

        .remember-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 22px;
        }

        .remember-row input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--apple-blue);
        }

        .remember-row label {
            font-size: 13px;
            color: var(--text);
        }

        .submit-button {
            width: 100%;
            height: 48px;
            border-radius: 12px;
            border: 0;
            background: var(--apple-blue);
            color: #fff;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: background 150ms ease, transform 150ms ease, opacity 150ms ease;
        }

        .submit-button:hover:not(:disabled) {
            background: var(--apple-blue-dark);
        }

        .submit-button:active:not(:disabled) {
            transform: scale(0.99);
        }

        .submit-button:focus-visible {
            outline: none;
            box-shadow: var(--apple-ring);
        }

        .submit-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .login-error {
            display: none;
            align-items: flex-start;
            gap: 8px;
            background: rgba(235, 87, 87, 0.1);
            border: 1px solid rgba(235, 87, 87, 0.28);
            color: #b23b3b;
            border-radius: 12px;
            padding: 11px 13px;
            font-size: 13px;
            margin-bottom: 18px;
        }

        .login-error.visible {
            display: flex;
        }

        .login-footer-link {
            text-align: center;
            font-size: 13px;
            color: var(--muted);
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid var(--apple-hairline);
        }

        .login-footer-link a {
            color: var(--apple-link);
            font-weight: 600;
            text-decoration: none;
        }

        .login-footer-link a:hover {
            text-decoration: underline;
        }

        .login-footer-link a:focus-visible {
            outline: none;
            box-shadow: var(--apple-ring);
            border-radius: 4px;
        }

        .secure-badge-row {
            text-align: center;
            margin-top: 26px;
        }

        .secure-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid var(--apple-hairline);
            border-radius: 999px;
            padding: 6px 13px;
        }

        .secure-badge svg {
            width: 13px;
            height: 13px;
        }

        .login-blurb {
            text-align: center;
            font-size: 12.5px;
            color: var(--muted);
            max-width: 330px;
            margin: 12px auto 0;
            line-height: 1.55;
        }

        @media (max-width: 480px) {
            .login-card {
                max-width: calc(100% - 8px);
            }

            .login-panel {
                padding: 28px 24px;
            }

            .top-bar {
                height: 56px;
                padding: 0 16px;
            }

            .login-wrap {
                min-height: calc(100vh - 56px);
                padding: 32px 16px 40px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .submit-button,
            .input-shell input,
            .field-link,
            .toggle-visibility,
            .login-footer-link a {
                transition: none;
            }

            .submit-button:active:not(:disabled) {
                transform: none;
            }
        }
    </style>
    <script type="module">
        import { initializeApp } from 'https://www.gstatic.com/firebasejs/9.22.0/firebase-app.js';
        import { getAuth, signInWithEmailAndPassword } from 'https://www.gstatic.com/firebasejs/9.22.0/firebase-auth.js';

        const firebaseConfig = {
            apiKey: "AIzaSyD44yfH2zeaGMh8icQol4XamDJGQ_h0XBE",
            authDomain: "performa-36cc9.firebaseapp.com",
            projectId: "performa-36cc9",
            storageBucket: "performa-36cc9.firebasestorage.app",
            messagingSenderId: "349595710839",
            appId: "1:349595710839:web:6839edb20b31fd760a9d72",
            measurementId: "G-37TDGM7XE8",
        };

        const app = initializeApp(firebaseConfig);
        const auth = getAuth(app);

        function showError(message) {
            const box = document.getElementById('login-error');
            box.querySelector('span').textContent = message;
            box.classList.add('visible');
        }

        function hideError() {
            document.getElementById('login-error').classList.remove('visible');
        }

        function setLoading(isLoading) {
            const btn = document.getElementById('submit-btn');
            btn.disabled = isLoading;
            btn.textContent = isLoading ? 'Signing in…' : 'Sign In';
        }

        async function handleSignIn(event) {
            event.preventDefault();
            hideError();
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            setLoading(true);
            try {
                const cred = await signInWithEmailAndPassword(auth, email, password);
                // Force a fresh token so other machines/browsers do not reuse a stale one.
                const idToken = await cred.user.getIdToken(true);
                // Exchange token for PHP session
                const res = await fetch('session_login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ idToken, email: cred.user.email || email })
                });
                const body = await res.json();
                if (res.ok) {
                    // Redirect based on role stored in session response
                    const role = body.role || 'probationary_employee';
                    // Temporary-password accounts go straight to Settings so
                    // the forced-reset gate is satisfied on first login.
                    if (body.mustChangePassword && role === 'employer') {
                        window.location.href = 'Employer/settings.php?force_reset=1';
                        return;
                    }
                    switch (role) {
                        case 'admin': window.location.href = 'Admin/admin_dashboard.php'; break;
                        case 'employer': window.location.href = 'Employer/employer_dashboard.php'; break;
                        case 'supervisor': window.location.href = 'Supervisor/supervisor_dashboard.php'; break;
                        default: window.location.href = 'ProbationaryEmployee/probationary_employee_dashboard.php'; break;
                    }
                } else {
                    showError(body.error || 'Failed to create session. Please try again.');
                    setLoading(false);
                }
            } catch (err) {
                showError(err.message || 'Sign-in failed. Check your email and password.');
                setLoading(false);
            }
        }
        window.handleSignIn = handleSignIn;

        window.togglePasswordVisibility = function () {
            const input = document.getElementById('password');
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            document.getElementById('toggle-icon').innerHTML = isHidden
                ? '<path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-5.5 0-9.5-3.5-11-7 1.09-2.36 2.86-4.34 5-5.65M9.9 4.24A10.94 10.94 0 0 1 12 4c5.5 0 9.5 3.5 11 7-.6 1.3-1.44 2.5-2.47 3.53M14.12 14.12a3 3 0 1 1-4.24-4.24" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/><path d="M1 1l22 22" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>'
                : '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6" fill="none"/>';
        };

        window.showForgotPasswordNote = function (event) {
            event.preventDefault();
            showError('Password resets are handled by your administrator — please ask your supervisor or employer.');
        };
    </script>
</head>

<body>
    <div class="top-bar">
        <div class="top-bar-brand">
            <div class="top-bar-mark">
                <svg viewBox="0 0 24 24" fill="none"><path d="M6 20v-7M12 20V9M18 20V5" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>
            </div>
            <span class="top-bar-name">Performa</span>
        </div>
    </div>

    <div class="login-wrap">
        <div class="login-card">
            <div class="login-header">
                <div class="brand-mark">
                    <svg viewBox="0 0 24 24" fill="none" style="color:#fff"><path d="M6 20v-7M12 20V9M18 20V5" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>
                </div>
                <div class="brand-name">Performa</div>
            </div>
            <p class="login-subtitle">Sign in to see your progress — or to manage your team.</p>

            <div class="login-panel">
                <div class="login-error" id="login-error" role="alert">
                    <span></span>
                </div>

                <form onsubmit="handleSignIn(event)" novalidate>
                    <div class="field-group">
                        <div class="field-label-row">
                            <label class="field-label" for="email">Email Address</label>
                        </div>
                        <div class="input-shell">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M3 6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6z" stroke="currentColor" stroke-width="1.6"/><path d="M4 6l8 6 8-6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <input id="email" name="email" type="email" autocomplete="username" placeholder="you@company.com" required />
                        </div>
                    </div>

                    <div class="field-group">
                        <div class="field-label-row">
                            <label class="field-label" for="password">Password</label>
                            <button type="button" class="field-link" onclick="showForgotPasswordNote(event)">Forgot password?</button>
                        </div>
                        <div class="input-shell has-toggle">
                            <svg viewBox="0 0 24 24" fill="none"><rect x="4" y="10" width="16" height="10" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                            <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password (8+ characters)" required minlength="8" />
                            <button type="button" class="toggle-visibility" onclick="togglePasswordVisibility()" aria-label="Toggle password visibility">
                                <svg id="toggle-icon" viewBox="0 0 24 24" fill="none"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6" fill="none"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="remember-row">
                        <input id="remember" type="checkbox" name="remember" />
                        <label for="remember">Remember me</label>
                    </div>

                    <button class="submit-button" type="submit" id="submit-btn">Sign In</button>
                </form>

                <div class="login-footer-link">
                    Having trouble signing in? Ask your supervisor or employer for help.
                </div>
            </div>

            <div class="secure-badge-row">
                <span class="secure-badge">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 2l8 4v6c0 5-3.4 8.4-8 10-4.6-1.6-8-5-8-10V6l8-4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                    Secure Portal
                </span>
                <p class="login-blurb">Check your progress — or manage your team's performance — securely through the Performa platform.</p>
            </div>
        </div>
    </div>
</body>

</html>