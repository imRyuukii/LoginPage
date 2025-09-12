<?php
session_start();
require_once '../models/user-functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $confirmPassword = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    if ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $result = registerUser($username, $password, $name, $email);
        if ($result['success']) {
            $success = $result['message'];
            // Redirect to log in after 2 seconds
            header('refresh:2;url=./login.php');
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="icon" type="image/png" href="../../public/images/logo.png">
    <link rel="stylesheet" href="../../public/css/style.css">
</head>
<body>
    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"></button>
    <img src="../../public/images/logo.png" alt="Logo" class="logo-website-top-left">
    <div class="logo-shadow"></div>
    <div class="container page">
        <div class="card">
            <h1>Register</h1>
            <?php if (!empty($error)): ?>
                <p class="alert error mt-3"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <p class="alert success mt-3"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <form method="post" action="">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">

                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">

                <label for="email">Email</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">

                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>

                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>

                <div class="link-row centered mt-4">
                    <button class="button primary" type="submit">Register</button>
                    <a class="button" href="./login.php">Back to Login</a>
                </div>
            </form>
            <p class="footer mt-6">Already have an account? <a href="./login.php">Login here</a></p>
        </div>
    </div>
    <div class="demo-warning">*This is a demo version of the website</div>
    <script>
    (function() {
        const root = document.documentElement;
        const stored = localStorage.getItem('theme');
        const prefersLight = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
        const initial = stored || (prefersLight ? 'light' : 'dark');
        if (initial === 'light') {
            root.setAttribute('data-theme', 'light');
        } else {
            root.removeAttribute('data-theme');
        }
        const btn = document.getElementById('themeToggle');

        function setIcon() {
            const isLight = root.getAttribute('data-theme') === 'light';
            btn.textContent = isLight ? '☀️' : '🌙';
            btn.title = isLight ? 'Switch to dark mode' : 'Switch to light mode';
        }
        setIcon();
        btn.addEventListener('click', function() {
            document.body.classList.add('theme-transition');
            const isLight = root.getAttribute('data-theme') === 'light';
            if (isLight) {
                root.removeAttribute('data-theme');
                localStorage.setItem('theme', 'dark');
            } else {
                root.setAttribute('data-theme', 'light');
                localStorage.setItem('theme', 'light');
            }
            setIcon();
            window.setTimeout(function(){
                document.body.classList.remove('theme-transition');
            }, 320);
        });
    })();
    </script>
</body>
</html>
