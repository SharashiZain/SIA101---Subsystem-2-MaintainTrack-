<?php
session_start();

$errorMessage = $_SESSION['login_error'] ?? 'Please enter your username and password.';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MaintainTrack | Login</title>
    <link rel="stylesheet" href="Components/css/Style.css">
</head>

<body class="login-page">

    <main class="login-card">

        <div class="brand">
            <img src="Components/img/Logo.png" alt="GULOD Logo" class="brand-logo">
            <div class="brand-subtitle">BARANGAY MANAGEMENT SYSTEM</div>
        </div>

        <h1 class="welcome-title">Welcome Back!</h1>
        <p class="welcome-text">Sign in to continue to your account.</p>

        <div class="error-message" id="errorMessage">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>

        <form id="loginForm" method="POST" action="login.php">

            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <div class="input-wrap">
                    <span class="input-icon">◉</span>
                    <input
                        class="form-input"
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Enter your username"
                        autocomplete="username"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-wrap">
                    <span class="input-icon">●</span>
                    <input
                        class="form-input"
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >
                    <button
                        type="button"
                        class="toggle-password"
                        id="togglePassword"
                        aria-label="Show password"
                    >
                        <svg class="eye-icon eye-open" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2.2 12s3.4-5.5 9.8-5.5S21.8 12 21.8 12 18.4 17.5 12 17.5 2.2 12 2.2 12Z"></path>
                            <circle cx="12" cy="12" r="2.7"></circle>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="login-options">
                <label class="remember">
                    <input type="checkbox" name="remember" value="1">
                    <span>Remember me</span>
                </label>
                <a href="#" class="forgot-link" id="forgotPassword">Forgot password?</a>
            </div>

            <button type="submit" class="login-button">Log In</button>

        </form>

        <div class="login-footer">
            Barangay GULOD Management System<br>
            <strong>Better Services for a Stronger Barangay</strong>
        </div>

    </main>

    <script src="Components/js/Script.js"></script>

</body>
</html>