<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Performa — Admin Login</title>
    <link rel="stylesheet" href="styles.css" />
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

        async function handleAdminSignIn(event) {
            event.preventDefault();
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            try {
                const cred = await signInWithEmailAndPassword(auth, email, password);
                const idToken = await cred.user.getIdToken();
                const res = await fetch('../session_login.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ idToken }) });
                const body = await res.json();
                if (res.ok && body.role === 'admin') {
                    window.location.href = 'admin_dashboard.php';
                } else {
                    alert(body.error || 'Not an admin account');
                }
            } catch (err) {
                alert(err.message || 'Sign-in failed');
            }
        }
        window.handleAdminSignIn = handleAdminSignIn;
    </script>
</head>

<body>
    <div class="app-shell" style="grid-template-columns:1fr;">
        <main class="main" style="display:flex;align-items:center;justify-content:center;min-height:80vh;padding:40px;">
            <div class="panel" style="max-width:420px;width:100%;padding:28px;border-radius:18px;text-align:left;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;">
                    <div class="brand-mark" style="width:48px;height:48px;border-radius:12px;font-size:20px;">P</div>
                    <div>
                        <div style="font-weight:800;font-size:1.1rem;">Performa</div>
                        <div style="font-size:12px;color:var(--muted);">Admin sign in</div>
                    </div>
                </div>

                <form onsubmit="handleAdminSignIn(event)">
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
                            in as admin</button>
                        <a href="../login.php" style="margin-left:auto;color:var(--muted);">Back to user login</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>

</html>