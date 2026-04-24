<?php
session_start();
include "connect.php";

// Only for development – remove in production
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // -------------------- LOGIN --------------------
    if (isset($_POST['login'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        // Fetch user from database
        $stmt = $pdo->prepare("SELECT id, password_hash, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo "User not found.";
            exit;
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            echo "Wrong password.";
            exit;
        }
        
        // Successful login
        $_SESSION['user_id'] = $user['id'];
        
        // ---- Log the login action with IP, user agent, page ----
        $logStmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, ip_address, user_agent, page) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $logStmt->execute([
            $user['id'],
            'User logged in',
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $_SERVER['PHP_SELF']
        ]);
        
        // ---- Log dashboard access (separate entry) ----
        $dashboardAction = ($user['role'] === 'admin') ? 'Accessed admin dashboard' : 'Accessed manager dashboard';
        $logStmt->execute([
            $user['id'],
            $dashboardAction,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $_SERVER['PHP_SELF']
        ]);
        
        // Redirect based on role
        if ($user['role'] === 'admin') {
            header("Location: adminDash.php");
        } else {
            header("Location: managerDash.php");
        }
        exit;
    }
    
    // -------------------- SIGNUP --------------------
    if (isset($_POST['signup'])) {
        $username = trim($_POST['username']);
        $role = trim($_POST['role']);
        $password = $_POST['password'];
        
        // Validate role – only allow predefined values
        $allowedRoles = ['admin', 'manager', 'device'];
        if (!in_array($role, $allowedRoles)) {
            echo "Invalid role. Allowed: admin, manager, device.";
            exit;
        }
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, role, password_hash) VALUES (?, ?, ?)");
            $stmt->execute([$username, $role, $passwordHash]);
            echo "Signup successful! You can now login.";
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) { // duplicate entry
                echo "Username already exists. Please choose another.";
            } else {
                echo "Signup failed: " . $e->getMessage();
            }
        }
        exit;
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
            <input type="text" name="role" placeholder="Role (admin/manager/device)" required>
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
    function showSignup() {
        document.getElementById("loginForm").classList.add("hidden");
        document.getElementById("signupForm").classList.remove("hidden");
    }
    function showLogin() {
        document.getElementById("signupForm").classList.add("hidden");
        document.getElementById("loginForm").classList.remove("hidden");
    }
</script>
</body>
</html>