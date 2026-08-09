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

        async function handleSignIn(event) {
            event.preventDefault();
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            try {
                const cred = await signInWithEmailAndPassword(auth, email, password);
                const idToken = await cred.user.getIdToken();
                // Exchange token for PHP session
                const res = await fetch('session_login.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ idToken }) });
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
                    alert(body.error || 'Failed to create session');
                }
            } catch (err) {
                alert(err.message || 'Sign-in failed');
            }
        }
        window.handleSignIn = handleSignIn;
    </script>
</head>

<body>
    <div class="app-shell" style="grid-template-columns:1fr;">
        <main class="main" style="display:flex;align-items:center;justify-content:center;min-height:80vh;padding:40px;">
            <div class="panel" style="max-width:520px;width:100%;padding:28px;border-radius:18px;text-align:left;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;">
                    <div class="brand-mark" style="width:48px;height:48px;border-radius:12px;font-size:20px;">P</div>
                    <div>
                        <div style="font-weight:800;font-size:1.25rem;">Performa</div>
                        <div style="font-size:12px;color:var(--muted);">Sign in to your account</div>
                    </div>
                </div>

                <form onsubmit="handleSignIn(event)">
                    <div style="margin-bottom:12px;">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" required
                            style="width:100%;padding:12px;border-radius:10px;border:1px solid var(--panel-border);margin-top:6px;" />
                    </div>
                    <div style="margin-bottom:18px;">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" required
                            style="width:100%;padding:12px;border-radius:10px;border:1px solid var(--panel-border);margin-top:6px;" />
                    </div>
                    <div style="display:flex;gap:12px;align-items:center;">
                        <button class="primary-button" type="submit"
                            style="background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;padding:10px 16px;border-radius:12px;">Sign
                            in</button>
                        <div style="margin-left:auto;color:var(--muted);font-size:13px;">Need help? Contact your admin
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>

</html>