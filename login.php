<?php
// Session check at the very top
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect if already logged in
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    if ($_SESSION['user_role'] === 'admin') {
        header("Location: dashboard.php");
    } elseif ($_SESSION['user_role'] === 'faculty') {
        header("Location: faculty_dashboard.php");
    } elseif ($_SESSION['user_role'] === 'student') {
        header("Location: student_dashboard.php");
    }
    exit;
}

// Include database configuration
require_once "config/db.php";

$username = $password = "";
$username_err = $password_err = $login_err = "";

// Process data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Alert: CSRF token validation failed.");
    }
    
    // Check if username is empty
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter username.";
    } else {
        $username = trim($_POST["username"]);
    }
    
    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if (empty($username_err) && empty($password_err)) {
        
        // 1. Authenticate contextually against Admin table
        $sql_admin = "SELECT id, username, password FROM admin WHERE username = ?";
        if ($stmt = mysqli_prepare($conn, $sql_admin)) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                if (mysqli_num_rows($result) == 1) {
                    $row = mysqli_fetch_assoc($result);
                    if (password_verify($password, $row['password'])) {
                        $_SESSION["user_logged_in"] = true;
                        $_SESSION["user_id"] = $row['id'];
                        $_SESSION["username"] = $row['username'];
                        $_SESSION["user_role"] = 'admin';
                        $_SESSION["ref_id"] = null;
                        
                        $_SESSION["admin_logged_in"] = true;
                        $_SESSION["admin_id"] = $row['id'];
                        $_SESSION["admin_username"] = $row['username'];
                        
                        mysqli_stmt_close($stmt);
                        mysqli_close($conn);
                        header("Location: dashboard.php");
                        exit;
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }
        
        // 2. Authenticate contextually against Faculty table (by faculty_id or email)
        $sql_faculty = "SELECT id, faculty_id, password FROM faculty WHERE faculty_id = ? OR email = ?";
        if ($stmt = mysqli_prepare($conn, $sql_faculty)) {
            mysqli_stmt_bind_param($stmt, "ss", $username, $username);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                if (mysqli_num_rows($result) == 1) {
                    $row = mysqli_fetch_assoc($result);
                    if (password_verify($password, $row['password'])) {
                        $_SESSION["user_logged_in"] = true;
                        $_SESSION["user_id"] = $row['id'];
                        $_SESSION["username"] = $row['faculty_id'];
                        $_SESSION["user_role"] = 'faculty';
                        $_SESSION["ref_id"] = $row['id'];
                        
                        $_SESSION["faculty_logged_in"] = true;
                        $_SESSION["faculty_id"] = $row['id'];
                        $_SESSION["faculty_username"] = $row['faculty_id'];
                        
                        mysqli_stmt_close($stmt);
                        mysqli_close($conn);
                        header("Location: faculty_dashboard.php");
                        exit;
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }

        // 3. Authenticate contextually against Students table (by student_id or email)
        $sql_student = "SELECT id, student_id, password FROM students WHERE student_id = ? OR email = ?";
        if ($stmt = mysqli_prepare($conn, $sql_student)) {
            mysqli_stmt_bind_param($stmt, "ss", $username, $username);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                if (mysqli_num_rows($result) == 1) {
                    $row = mysqli_fetch_assoc($result);
                    if (password_verify($password, $row['password'])) {
                        $_SESSION["user_logged_in"] = true;
                        $_SESSION["user_id"] = $row['id'];
                        $_SESSION["username"] = $row['student_id'];
                        $_SESSION["user_role"] = 'student';
                        $_SESSION["ref_id"] = $row['id'];
                        
                        $_SESSION["student_logged_in"] = true;
                        $_SESSION["student_id"] = $row['id'];
                        $_SESSION["student_username"] = $row['student_id'];
                        
                        mysqli_stmt_close($stmt);
                        mysqli_close($conn);
                        header("Location: student_dashboard.php");
                        exit;
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }

        // If none matched, auth fails
        $login_err = "Invalid username/ID or password.";
    }
    mysqli_close($conn);
}

$page_title = "Portal Login";
$page_description = "Secure multi-tenant portal login for Students, Faculty, and Administrators.";
include 'includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card glass-card border-0 p-4">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-3" style="width: 60px; height: 60px;">
                            <i class="fa-solid fa-graduation-cap fa-xl"></i>
                        </div>
                        <h2 class="fw-bold mb-1">EduManage Portal</h2>
                        <p class="text-muted small">Access Student, Faculty, or Admin Views</p>
                    </div>

                    <?php 
                    if (!empty($login_err)) {
                        echo '<div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="fa-solid fa-triangle-exclamation me-2"></i>
                            <div>' . htmlspecialchars($login_err) . '</div>
                        </div>';
                    }        
                    ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="needs-validation" novalidate>
                        <!-- CSRF Token Input -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <!-- Username Field -->
                        <div class="mb-3">
                            <label for="username" class="form-label fw-semibold small text-secondary">Username (ID, Admin or Email)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-regular fa-user"></i></span>
                                <input type="text" name="username" id="username" class="form-control border-start-0 ps-0 <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username); ?>" required placeholder="e.g., admin or STU-2026-001">
                                <?php if (!empty($username_err)): ?>
                                    <div class="invalid-feedback"><?php echo $username_err; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>    

                        <!-- Password Field -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <label for="password" class="form-label fw-semibold small text-secondary mb-0">Password</label>
                                <a href="forgot-password.php" class="small text-decoration-none text-primary">Forgot Password?</a>
                            </div>
                            <div class="input-group mt-1">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-key"></i></span>
                                <input type="password" name="password" id="password" class="form-control border-start-0 ps-0 <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" required placeholder="e.g., ••••••••">
                                <?php if (!empty($password_err)): ?>
                                    <div class="invalid-feedback"><?php echo $password_err; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Login Button -->
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary-custom py-2">
                                <i class="fa-solid fa-arrow-right-to-bracket me-2"></i> Sign In
                            </button>
                        </div>
                    </form>
                    
                    <div class="alert alert-info py-2 px-3 mb-0" style="font-size: 0.8rem;">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        <strong>Default Logins:</strong><br>
                        • Admin: <code>admin</code> / <code>admin123</code><br>
                        • Faculty: <code>FAC-2026-001</code> / <code>faculty123</code><br>
                        • Student: <code>STU-2026-001</code> / <code>student123</code>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <a href="index.php" class="text-decoration-none text-muted small">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Homepage
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Bootstrap validation script
(function () {
  'use strict'
  var forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add('was-validated')
      }, false)
    })
})()
</script>

<?php include 'includes/footer.php'; ?>
