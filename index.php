<?php
session_start();

include "connect.php";

// Hardcoded Accounts
$hardcoded_manager_user = 'manager';
$hardcoded_manager_pass = 'password123';

$hardcoded_admin_user = 'admin';
$hardcoded_admin_pass = 'admin123';

ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Login Function
    if (isset($_POST['login'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        if (!$db_user) {
            echo "User not found";
        } elseif (!password_verify($password, $db_user['password_hash'])) {
            echo "Wrong password";
        }
        /*
        // Check Hardcoded Manager
        if ($username === $hardcoded_manager_user && $password === $hardcoded_manager_pass) {
            $_SESSION['user_id'] = 1; // Assign a fixed ID for testing (must exist in users table)
            header("Location: managerDash.php");
            exit;
        }

        // Check Hardcoded Admin
        if ($username === $hardcoded_admin_user && $password === $hardcoded_admin_pass) {
            $_SESSION['user_id'] = 2; // Assign a fixed ID for testing (must exist in users table)
            header("Location: adminDash.php");
            exit;
        }
        */

        // Check Database Users - Added 'id' to the SELECT statement
        $stmt = $pdo->prepare("SELECT id, password_hash, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $db_user = $stmt->fetch();
        var_dump($db_user);

        if ($db_user && hash('sha256', $password) === $db_user['password_hash']) {
            $_SESSION['user_id'] = $db_user['id']; // ✅ set session
    // --- LOGGING ACTIVITY ---
            $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
            $log_stmt->execute([$db_user['id'], 'User logged in']);
            // -------------------------

            if ($db_user['role'] === 'admin') {
                $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
                $action = 'User in Admin Page | IP: ' . $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT'] . $_SERVER['PHP_SELF'];
                $log_stmt->execute([$db_user['id'], $action]);

                header("Location: adminDash.php");

            } else {
                header("Location: managerDash.php");

                $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
                $action = 'User in Dashboard | IP: ' . $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT'] . $_SERVER['PHP_SELF'];
                $log_stmt->execute([$db_user['id'], $action]);
            }
            exit;
        }
    }

    // Sign-up Function
    if (isset($_POST['signup'])) {
        $username = $_POST['username'];
        $role = $_POST['role']; 
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (username, role, password_hash) VALUES (?, ?, ?)");
        $stmt->execute([$username, $role, $password]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="login.css">
<title>Login / Sign-Up</title>

</head>

<body>

<div class="card">

<!-- LOGIN FORM -->
<div id="loginForm">
<h2>Log-in</h2>

<form method="POST">
<input type="text" name="username" placeholder="Username" required>
<input type="password" name="password" placeholder="Password" required>
<button type="submit" name="login">Login</button>
</form>

<div class="toggle">
Don't have an account? 
<a onclick="showSignup()">Sign up</a>
</div>
</div>

<!-- SIGNUP FORM -->
<div id="signupForm" class="hidden">
<h2>Sign Up</h2>

<form method="POST">
<input type="text" name="username" placeholder="Username" required>
<input type="text" name="role" placeholder="Role" required>
<input type="password" name="password" placeholder="Password" required>
<button type="submit" name="signup">Sign Up</button>
</form>

<div class="toggle">
Already have an account? 
<a onclick="showLogin()">Login</a>
</div>
</div>

</div>

<script>
function showSignup(){
document.getElementById("loginForm").classList.add("hidden");
document.getElementById("signupForm").classList.remove("hidden");
}

function showLogin(){
document.getElementById("signupForm").classList.add("hidden");
document.getElementById("loginForm").classList.remove("hidden");
}
</script>

</body>
</html>