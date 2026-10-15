<?php
session_start();

$errorMessage = (string) ($_SESSION['login_error'] ?? '');
unset($_SESSION['login_error']);

$fontSize = (string) ($_COOKIE['font_size'] ?? 'medium');
$contentSize = (string) ($_COOKIE['content_size'] ?? 'medium');

$allowedSizes = ['small', 'medium', 'large'];
if (!in_array($fontSize, $allowedSizes, true)) {
    $fontSize = 'medium';
}
if (!in_array($contentSize, $allowedSizes, true)) {
    $contentSize = 'medium';
}
?>
<!DOCTYPE html>
<html lang="en"
      data-font-size="<?= htmlspecialchars($fontSize, ENT_QUOTES, 'UTF-8') ?>"
      data-content-size="<?= htmlspecialchars($contentSize, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MaintainTrack | Login</title>
    <link rel="stylesheet" href="Components/css/Style.css">
    <style>
        /* Login error modal - visually consistent with the SystemModal on Users.php */
        .login-system-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 35, 55, .42);
            backdrop-filter: blur(3px);
        }
        .login-system-modal {
            width: 100%;
            max-width: 360px;
            padding: 26px 22px 20px;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 18px 50px rgba(15, 35, 55, .22);
            text-align: center;
            animation: loginModalIn .18s ease-out;
        }
        .login-system-modal-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 15px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff2c7;
            color: #c75b00;
        }
        .login-system-modal-icon svg {
            width: 24px;
            height: 24px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .login-system-modal-title {
            margin: 0 0 8px;
            color: #153c66;
            font-size: 17px;
            font-weight: 800;
        }
        .login-system-modal-text {
            margin: 0 0 20px;
            color: #687b8d;
            font-size: 12px;
            line-height: 1.55;
            overflow-wrap: anywhere;
        }
        .login-system-modal-button {
            width: 100%;
            min-height: 39px;
            border: 0;
            border-radius: 10px;
            background: #1769aa;
            color: #fff;
            font: inherit;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 5px 12px rgba(23, 105, 170, .2);
        }
        .login-system-modal-button:hover { background: #125789; }
        @keyframes loginModalIn {
            from { opacity: 0; transform: translateY(5px) scale(.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
    </style>
</head>

<body class="login-page">
    <main class="login-card">
        <div class="brand">
            <img src="Components/img/Logo.png" alt="GULOD Logo" class="brand-logo">
            <div class="brand-subtitle">BARANGAY MANAGEMENT SYSTEM</div>
        </div>

        <h1 class="welcome-title">Welcome Back!</h1>
        <p class="welcome-text">Sign in to continue to your account.</p>

        <form id="loginForm" method="POST" action="Login.php">
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <div class="input-wrap">
                    <span class="input-icon">◉</span>
                    <input class="form-input" type="text" id="username" name="username"
                           placeholder="Enter your username" autocomplete="username" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-wrap">
                    <span class="input-icon">●</span>
                    <input class="form-input" type="password" id="password" name="password"
                           placeholder="Enter your password" autocomplete="current-password" required>
                    <button type="button" class="toggle-password" id="togglePassword"
                            aria-label="Show password">
                        <svg class="eye-icon eye-open" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2.2 12s3.4-5.5 9.8-5.5S21.8 12 21.8 12 18.4 17.5 12 17.5 2.2 12 2.2 12Z"></path>
                            <circle cx="12" cy="12" r="2.7"></circle>
                        </svg>
                        <svg class="eye-icon eye-closed" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="login-options">
                <a href="#" class="forgot-link" id="forgotPassword">Forgot password?</a>
            </div>

            <button type="submit" class="login-button">Log In</button>
        </form>

        <div class="login-footer">
            Barangay GULOD Management System<br>
            <strong>Better Services for a Stronger Barangay</strong>
        </div>
    </main>

    <!-- Same single-button warning modal style as the Users page SystemModal -->
    <div class="login-system-modal-overlay" id="loginErrorModal" role="dialog"
         aria-modal="true" aria-labelledby="loginModalTitle" aria-describedby="loginModalText">
        <div class="login-system-modal">
            <div class="login-system-modal-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            </div>
            <h2 class="login-system-modal-title" id="loginModalTitle">Unable to log in</h2>
            <p class="login-system-modal-text" id="loginModalText"></p>
            <button type="button" class="login-system-modal-button" id="loginModalOk">OK</button>
        </div>
    </div>

    <script src="Components/js/RoleNavigation.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.getElementById('togglePassword');
            const modal = document.getElementById('loginErrorModal');
            const modalText = document.getElementById('loginModalText');
            const modalOk = document.getElementById('loginModalOk');
            const forgotPassword = document.getElementById('forgotPassword');

            if (passwordInput && toggleBtn) {
                const openEye = toggleBtn.querySelector('.eye-open');
                const closedEye = toggleBtn.querySelector('.eye-closed');
                if (closedEye) closedEye.style.display = 'none';

                toggleBtn.addEventListener('click', function () {
                    const show = passwordInput.type === 'password';
                    passwordInput.type = show ? 'text' : 'password';
                    toggleBtn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                    if (openEye) openEye.style.display = show ? 'none' : '';
                    if (closedEye) closedEye.style.display = show ? '' : 'none';
                });
            }

            const serverError = <?= json_encode($errorMessage, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            if (serverError) {
                modalText.textContent = serverError;
                modal.style.display = 'flex';
                document.getElementById('loginModalOk').focus();
            }

            function closeModal() {
                modal.style.display = 'none';
            }
            modalOk.addEventListener('click', closeModal);
            modal.addEventListener('click', function (event) {
                if (event.target === modal) closeModal();
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && modal.style.display === 'flex') closeModal();
            });

            if (forgotPassword) {
                forgotPassword.addEventListener('click', function (event) {
                    event.preventDefault();
                    modalText.textContent = 'Password recovery is not connected yet. Please contact your administrator.';
                    modal.style.display = 'flex';
                });
            }
        });
    </script>
</body>
</html>
