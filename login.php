<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/flash.php';

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
        exit;
    } elseif ($_SESSION['role'] === 'student') {
        header('Location: student/dashboard.php');
        exit;
    }
}

$errors = [];
$successMessage = '';
$infoMessage = '';

// Registration success (session flash or legacy URL)
$regFlash = flash_take('success');
if ($regFlash !== null && $regFlash !== '') {
    $successMessage = $regFlash;
} elseif (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $successMessage = 'Your account is ready. Sign in with your username and password.';
}

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    $infoMessage = 'You have been logged out securely.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if ($identifier === '' || $password === '' || $role === '') {
        $errors[] = 'Please enter your role, username/email, and password.';
    } else {
        $allowedRoles = ['student', 'admin'];
        if (!in_array($role, $allowedRoles, true)) {
            $errors[] = 'Invalid role selected.';
        }
    }

    if (empty($errors)) {
        $sql = "SELECT user_id, username, email, password, role 
                FROM users 
                WHERE (username = ? OR email = ?) AND role = ? 
                LIMIT 1";

        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sss", $identifier, $identifier, $role);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($result && mysqli_num_rows($result) === 1) {
                $user = mysqli_fetch_assoc($result);
                

                if (password_verify($password, $user['password'])) {
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];

                    $welcomeName = $user['username'] !== '' ? $user['username'] : 'there';
                    flash_put('success', 'Welcome back, ' . $welcomeName . '.');

                    if ($user['role'] === 'admin') {
                        header('Location: admin/dashboard.php');
                    } else {
                        header('Location: student/dashboard.php');
                    }
                    exit;
                } else {
                    $errors[] = 'Invalid login credentials. Please try again.';
                }
            } else {
                $errors[] = 'Invalid login credentials. Please try again.';
            }

            mysqli_stmt_close($stmt);
        } else {
            $errors[] = 'Login query error: ' . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudySmart - Login</title>
    <link rel="stylesheet" href="styles.css?v=8">
</head>
<body class="login-page login-page-dark">
    <div class="login-shell">
        <div class="login-panel-image">
            <img src="assets/login.png" alt="Student login visual">
        </div>

        <main class="login-panel-form">
            <div class="login-brand">StudySmart</div>
            <h2 class="login-title">Let the Journey Begin!</h2>
            <p class="login-subtitle">Sign in with your username/email and password.</p>

            <?php if ($successMessage): ?>
                <?php render_auth_notice('success', 'Registration complete', $successMessage); ?>
            <?php endif; ?>

            <?php if ($infoMessage): ?>
                <?php render_auth_notice('info', 'Signed out', $infoMessage); ?>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <?php render_auth_notice('error', 'Sign-in failed', $errors); ?>
            <?php endif; ?>

            <form method="post" action="login.php" novalidate>
                <div class="form-group">
                    <label for="role" class="form-label">Role</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="">Select your role</option>
                        <option value="student" <?php echo (isset($role) && $role === 'student') ? 'selected' : ''; ?>>Student</option>
                        <option value="admin" <?php echo (isset($role) && $role === 'admin') ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="identifier" class="form-label">Username or Email</label>
                    <input
                        type="text"
                        id="identifier"
                        name="identifier"
                        class="form-control"
                        placeholder="Enter username or email"
                        value="<?php echo isset($identifier) ? htmlspecialchars($identifier, ENT_QUOTES, 'UTF-8') : ''; ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter password"
                        required
                    >
                </div>

                <button type="submit" class="btn-primary">Login</button>
            </form>

            <div class="form-footer">
                Don't have an account?
                <a href="register.php">Register here</a>
            </div>
        </main>
    </div>
    <script>
    (function () {
        document.querySelectorAll('.notice__close').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var n = btn.closest('.notice');
                if (n) n.remove();
            });
        });
    })();
    </script>
</body>
</html>

