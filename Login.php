<?php
session_start();

$users = [
    'admin' => [
        'password' => 'admin123',
        'role' => 'admin',
    ],
    'user' => [
        'password' => 'user123',
        'role' => 'regular',
    ],
    'worker' => [
        'password' => 'worker123',
        'role' => 'maintenance',
    ],
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: Index.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (!isset($users[$username]) || $users[$username]['password'] !== $password) {
    $_SESSION['login_error'] = 'Invalid username or password.';
    header('Location: Index.php');
    exit;
}

$_SESSION['user'] = $username;
$_SESSION['role'] = $users[$username]['role'];
unset($_SESSION['login_error']);

$dashboardMap = [
    'admin' => 'Admin/Pages/Dashboard.php',
    'maintenance' => 'Maintenance/Pages/Dashboard.php',
    'regular' => 'Regular/Pages/Dashboard.php',
];

$targetPage = $dashboardMap[$_SESSION['role']] ?? 'Index.php';

header('Location: ' . $targetPage);
exit;
