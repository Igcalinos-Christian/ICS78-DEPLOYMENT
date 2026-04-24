<?php
session_start();
include "connect.php";

$error = '';
$success = '';

// Hardcoded fallback (optional)
$hardcoded_manager_user = 'manager';
$hardcoded_manager_pass = 'password123';
$hardcoded_admin_user = 'admin';
$hardcoded_admin_pass = 'admin123';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // LOGIN
    if (isset($_POST['login'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        // Hardcoded check
        if ($username === $hardcoded_manager_user && $password === $hardcoded_manager_pass) {
            $_SESSION['user_id'] = 1;
            $_SESSION['role'] = 'manager';
            header("Location: managerDash.php");
            exit;
        }
        if ($username === $hardcoded_admin_user && $password === $hardcoded_admin_pass) {
            $_SESSION['user_id'] = 2;
            $_SESSION['role'] = 'admin';
            header("Location: adminDash.php");
            exit;
        }

        // Database check
        $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $db_user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($db_user && password_verify($password, $db_user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $db_user['id'];
            $_SESSION['username'] = $db_user['username'];
            $_SESSION['role'] = $db_user['role'];
            
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $page = $_SERVER['PHP_SELF'] ?? '';
            
            // Log login
            $log = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address, user_agent, page) VALUES (?, ?, ?, ?, ?)");
            $log->execute([$db_user['id'], "User logged in", $ip, $user_agent, $page]);
            
            if ($db_user['role'] === 'admin') {
                $log->execute([$db_user['id'], "Accessed Admin Dashboard", $ip, $user_agent, $_SERVER['REQUEST_URI']]);
                header("Location: adminDash.php");
            } else {
                $log->execute([$db_user['id'], "Accessed Manager Dashboard", $ip, $user_agent, $_SERVER['REQUEST_URI']]);
                header("Location: managerDash.php");
            }
            exit;
        } else {
            $error = "Invalid username or password";
        }
    }
    
    // SIGNUP
    if (isset($_POST['signup'])) {
        $username = trim($_POST['username']);
        $role = $_POST['role'];
        $password = $_POST['password'];
        
        if (empty($username) || empty($password) || empty($role)) {
            $error = "All fields are required";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, role, password_hash) VALUES (?, ?, ?)");
                $stmt->execute([$username, $role, $hashed_password]);
                $success = "Account created successfully! Please login.";
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $error = "Username already exists. Choose another.";
                } else {
                    $error = "Registration failed: " . $e->getMessage();
                }
            }
        }
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

<div id="loginForm">
<h2>Log-in</h2>
<?php if($error) echo "<div style='color:red; margin-bottom:10px;'>$error</div>"; ?>
<?php if($success) echo "<div style='color:green; margin-bottom:10px;'>$success</div>"; ?>
<form method="POST">
<input type="text" name="username" placeholder="Username" required>
<input type="password" name="password" placeholder="Password" required>
<button type="submit" name="login">Login</button>
</form>
<div class="toggle">Don't have an account? <a onclick="showSignup()">Sign up</a></div>
</div>

<div id="signupForm" class="hidden">
<h2>Sign Up</h2>
<form method="POST">
<input type="text" name="username" placeholder="Username" required>
<select name="role" required>
    <option value="manager">Manager</option>
    <option value="admin">Admin</option>
    <option value="device">Device</option>
</select>
<input type="password" name="password" placeholder="Password" required>
<button type="submit" name="signup">Sign Up</button>
</form>
<div class="toggle">Already have an account? <a onclick="showLogin()">Login</a></div>
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