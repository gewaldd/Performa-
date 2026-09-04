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
    <link rel="stylesheet" href="Admin/styles.css" />
    <style>
        body {
            margin: 0;
            background: #f8fafc;
        }

        .top-bar {
            width: 100%;
            height: 78px;
            background: var(--sidebar, #0f172a);
            display: flex;
            align-items: center;
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
            width: 32px;
            height: 32px;
            border-radius: 9px;
            display: grid;
            place-items: center;
            background: linear-gradient(145deg, #3c78ff, #70a2ff);
            color: #fff;
        }

        .top-bar-mark svg {
            width: 18px;
            height: 18px;
        }

        .top-bar-name {
            color: #fff;
            font-weight: 700;
            font-size: 16px;
        }

        .login-wrap {
            min-height: calc(100vh - 78px);
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 56px 20px 40px;
            position: relative;
            z-index: 1;
        }

        .login-header {
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
            margin-bottom: 6px;
        }

        .login-header .brand-mark {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            font-size: 0;
            display: grid;
            place-items: center;
        }

        .login-header .brand-mark svg {
            width: 22px;
            height: 22px;
        }

        .login-header .brand-name {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text);
        }

        .login-subtitle {
            text-align: center;
            color: var(--muted);
            font-size: 14px;
            margin: 0 0 26px;
        }

        .login-card {
            width: 100%;
            max-width: 380px;
        }

        .login-panel {
            background: #ffffff;
            border: 1px solid var(--panel-border);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            padding: 30px;
        }

        .field-group {
            margin-bottom: 16px;
        }

        .field-label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 6px;
        }

        .field-label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text);
        }

        .field-link {
            font-size: 12px;
            font-weight: 600;
            color: var(--primary);
            text-decoration: none;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
        }

        .field-link:hover {
            text-decoration: underline;
        }

        .input-shell {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-shell svg {
            position: absolute;
            left: 12px;
            width: 15px;
            height: 15px;
            color: var(--muted);
            pointer-events: none;
        }

        .input-shell input {
            width: 100%;
            height: 38px;
            padding: 0 14px 0 38px;
            border-radius: 8px;
            border: 1px solid var(--panel-border);
            background: #fff;
            color: var(--text);
            font: inherit;
            font-size: 14px;
            transition: border-color 150ms ease, box-shadow 150ms ease;
        }

        .input-shell input::placeholder {
            color: #9aa5b8;
        }

        .input-shell input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(47, 109, 246, 0.15);
        }

        .input-shell.has-toggle input {
            padding-right: 36px;
        }

        .toggle-visibility {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--muted);
            display: grid;
            place-items: center;
            padding: 2px;
        }

        .toggle-visibility svg {
            position: static;
            width: 17px;
            height: 17px;
        }

        .remember-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .remember-row input[type="checkbox"] {
            width: 15px;
            height: 15px;
            accent-color: var(--primary);
        }

        .remember-row label {
            font-size: 13px;
            color: var(--text);
        }

        .submit-button {
            width: 100%;
            height: 38px;
            border-radius: 9px;
            border: 0;
            background: var(--sidebar, #0f172a);
            color: #fff;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: background 150ms ease, transform 150ms ease;
        }

        .submit-button:hover:not(:disabled) {
            background: #1a2540;
        }

        .submit-button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .login-error {
            display: none;
            align-items: flex-start;
            gap: 8px;
            background: rgba(235, 87, 87, 0.1);
            border: 1px solid rgba(235, 87, 87, 0.28);
            color: #b23b3b;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .login-error.visible {
            display: flex;
        }

        .login-footer-link {
            text-align: center;
            font-size: 13px;
            color: var(--muted);
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid var(--panel-border);
        }

        .login-footer-link a {
            color: var(--text);
            font-weight: 600;
            text-decoration: none;
        }

        .login-footer-link a:hover {
            text-decoration: underline;
        }

        .secure-badge-row {
            text-align: center;
            margin-top: 22px;
        }

        .secure-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            background: #fff;
            border: 1px solid var(--panel-border);
            border-radius: 999px;
            padding: 6px 12px;
        }

        .secure-badge svg {
            width: 13px;
            height: 13px;
        }

        .login-blurb {
            text-align: center;
            font-size: 12px;
            color: var(--muted);
            max-width: 320px;
            margin: 10px auto 0;
            line-height: 1.5;
        }

        @media (max-width: 480px) {
            .login-card {
                max-width: calc(100% - 8px);
            }

            .login-panel {
                padding: 22px;
            }

            .top-bar {
                padding: 0 16px;
            }

            .login-wrap {
                padding: 32px 16px 30px;
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
            showError('Self-service reset isn\'t available yet. Contact your administrator to reset your password.');
        };
    </script>
</head>

<body>
    <div class="top-bar">
        <div class="top-bar-brand">
            <div class="top-bar-mark">
                <svg viewBox="0 0 24 24" fill="none"><path d="M4 19V10M12 19V5M20 19v-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <span class="top-bar-name">Performa</span>
        </div>
    </div>

    <div class="login-wrap">
        <div class="login-card">
            <div class="login-header">
                <div class="brand-mark">
                    <svg viewBox="0 0 24 24" fill="none" style="color:#fff"><path d="M4 19V10M12 19V5M20 19v-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div class="brand-name">Performa</div>
            </div>
            <p class="login-subtitle">Sign in to manage probationary employee performance</p>

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
                            <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required minlength="6" />
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
                    Having trouble signing in? <a href="mailto:support@smallstepslearning.example">Contact IT Support</a>
                </div>
            </div>

            <div class="secure-badge-row">
                <span class="secure-badge">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 2l8 4v6c0 5-3.4 8.4-8 10-4.6-1.6-8-5-8-10V6l8-4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                    Secure Portal
                </span>
                <p class="login-blurb">Manage probationary employees, KPIs, and regularization decisions securely through the Performa platform.</p>
            </div>
        </div>
    </div>
</body>

</html>