<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/flash.php';
if (!isset($conn)) {
    die("DB connection not loaded!");
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $allowedRoles = ['student', 'admin'];
    if (!in_array($role, $allowedRoles, true)) {
        $role = 'student';
    }

    if ($full_name === '' || $username === '' || $email === '' || $password === '' || $role === '') {
        $errors[] = 'All fields are required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if (empty($errors)) {
       
        $sqlCheck = "SELECT user_id FROM users WHERE username = ? OR email = ? LIMIT 1";
        if ($stmtCheck = $conn->prepare($sqlCheck)) {
            $stmtCheck->bind_param('ss', $username, $email);
            $stmtCheck->execute();
            $stmtCheck->store_result();
    
            if ($stmtCheck->num_rows > 0) {
                $errors[] = 'Username or email already exists. Please choose another.';
            }
    
            $stmtCheck->close();
        } else {
            $errors[] = 'Registration query error: ' . $conn->error;
        }
    }

    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $role = 'student';

        $sqlInsert = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
        $stmtInsert = mysqli_prepare($conn, $sqlInsert);

        if ($stmtInsert) {
            mysqli_stmt_bind_param($stmtInsert, "ssss", $username, $email, $passwordHash, $role);

            if (mysqli_stmt_execute($stmtInsert)) {
                $newUserId = mysqli_insert_id($conn);
                mysqli_stmt_close($stmtInsert);

                $sqlStudent = "INSERT INTO students (user_id, full_name) VALUES (?, ?)";
                $stmtStudent = mysqli_prepare($conn, $sqlStudent);

                if ($stmtStudent) {
                    mysqli_stmt_bind_param($stmtStudent, "is", $newUserId, $full_name);

                    if (mysqli_stmt_execute($stmtStudent)) {
                        mysqli_stmt_close($stmtStudent);
                        flash_put('success', 'Your account is ready. Sign in with your username and password.');
                        header('Location: login.php');
                        exit();
                    } else {
                        $errors[] = 'Failed to save student details: ' . mysqli_stmt_error($stmtStudent);
                        mysqli_stmt_close($stmtStudent);
                    }
                } else {
                    $errors[] = 'Student insert error: ' . mysqli_error($conn);
                }
            } else {
                $errors[] = 'Failed to create account: ' . mysqli_stmt_error($stmtInsert);
                mysqli_stmt_close($stmtInsert);
            }
        } else {
            $errors[] = 'Registration insert error: ' . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudySmart - Register</title>
    <link rel="stylesheet" href="styles.css?v=6">
</head>
<body class="login-page login-page-dark">
    <div class="login-shell">
        <div class="login-panel-image">
            <img src="assets/login.png" alt="Student registration visual">
        </div>

        <main class="login-panel-form">
            <div class="login-brand">StudySmart</div>
            <h2 class="login-title">Create your account</h2>
            <p class="login-subtitle">Fill in your details to get started.</p>

            <?php if (!empty($errors)): ?>
                <?php render_auth_notice('error', 'Could not create account', $errors); ?>
            <?php endif; ?>

            <form method="post" action="register.php" novalidate autocomplete="off">
                <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
                    <input type="text" name="fake_username" autocomplete="username" tabindex="-1">
                    <input type="password" name="fake_password" autocomplete="current-password" tabindex="-1">
                </div>
                <div class="form-group">
                    <label for="full_name" class="form-label">Full name</label>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        class="form-control"
                        autocomplete="name"
                        placeholder="e.g. John Doe"
                        value="<?php echo isset($full_name) ? htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') : ''; ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control"
                        autocomplete="new-username"
                        readonly
                        placeholder="Choose a unique username"
                        value="<?php echo isset($username) ? htmlspecialchars($username, ENT_QUOTES, 'UTF-8') : ''; ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        autocomplete="email"
                        placeholder="you@example.com"
                        value="<?php echo isset($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : ''; ?>"
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
                        autocomplete="new-password"
                        readonly
                        placeholder="At least 8 characters"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="role" class="form-label">Role</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="student" <?php echo (isset($role) && $role === 'student') ? 'selected' : ''; ?>>Student</option>
                        <option value="admin" <?php echo (isset($role) && $role === 'admin') ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>

                <button type="submit" class="btn-primary">Create account</button>
            </form>

            <div class="form-footer">
                Already have an account?
                <a href="login.php">Back to login</a>
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

        ['username', 'password'].forEach(function (id) {
            var input = document.getElementById(id);
            if (!input) return;
            var unlock = function () {
                input.removeAttribute('readonly');
            };
            input.addEventListener('focus', unlock);
            input.addEventListener('pointerdown', unlock);
        });
    })();
    </script>
</body>
</html>

