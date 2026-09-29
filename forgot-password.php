<?php
// Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include files
require_once "config/db.php";
require_once "includes/mail_helper.php";

$username = "";
$username_err = $form_err = $success_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Alert: CSRF token validation failed.");
    }
    
    // Check if username is empty
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter your username.";
    } else {
        $username = trim($_POST["username"]);
    }
    
    if (empty($username_err)) {
        // Query users table for the email
        $sql = "SELECT id, email FROM users WHERE username = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            $param_username = $username;
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    mysqli_stmt_bind_result($stmt, $db_id, $db_email);
                    if (mysqli_stmt_fetch($stmt)) {
                        // Generate a 6-digit numeric OTP
                        $otp_code = strval(random_int(100000, 999999));
                        
                        // Set expiry to 15 minutes from now
                        $otp_expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));
                        
                        // Update users table with OTP details
                        $sql_update = "UPDATE users SET otp_code = ?, otp_expiry = ? WHERE id = ?";
                        if ($stmt_up = mysqli_prepare($conn, $sql_update)) {
                            mysqli_stmt_bind_param($stmt_up, "ssi", $otp_code, $otp_expiry, $db_id);
                            
                            if (mysqli_stmt_execute($stmt_up)) {
                                // Send OTP Email
                                send_otp_email($db_email, $otp_code);
                                
                                $_SESSION['message'] = "A time-constrained OTP code has been sent to your registered email address.";
                                $_SESSION['msg_type'] = "success";
                                
                                // Redirect to reset page
                                header("Location: reset-password.php?username=" . urlencode($username));
                                exit;
                            } else {
                                $form_err = "Failed to generate security code. Please try again.";
                            }
                            mysqli_stmt_close($stmt_up);
                        }
                    }
                } else {
                    // For security, display a generic message or handle user exist check
                    // But standard enterprise practice can be "If the username exists, a code was sent" 
                    // or explicit error. Let's do a user-friendly explicit error since it is a portal.
                    $username_err = "Username not found in registry.";
                }
            } else {
                $form_err = "Oops! Something went wrong. Please try again.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    mysqli_close($conn);
}

$page_title = "Forgot Password";
$page_description = "Initiate password recovery using a time-constrained OTP verification code.";
include 'includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card glass-card border-0 p-4">
                <div class="card-body">
                    <!-- Title -->
                    <div class="text-center mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-3" style="width: 60px; height: 60px;">
                            <i class="fa-solid fa-key fa-xl"></i>
                        </div>
                        <h2 class="fw-bold mb-1">Recover Password</h2>
                        <p class="text-muted small">Enter your username to receive a security OTP code</p>
                    </div>

                    <?php 
                    if (!empty($form_err)) {
                        echo '<div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="fa-solid fa-triangle-exclamation me-2"></i>
                            <div>' . htmlspecialchars($form_err) . '</div>
                        </div>';
                    }
                    ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="needs-validation" novalidate>
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <!-- Username -->
                        <div class="mb-4">
                            <label for="username" class="form-label fw-semibold small text-secondary">Username (Portal ID)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-regular fa-user"></i></span>
                                <input type="text" name="username" id="username" class="form-control border-start-0 ps-0 <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username); ?>" required placeholder="e.g., admin or STU-2026-001">
                                <?php if (!empty($username_err)): ?>
                                    <div class="invalid-feedback"><?php echo $username_err; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Reset Button -->
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary-custom py-2">
                                <i class="fa-solid fa-paper-plane me-2"></i> Send OTP Code
                            </button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-3">
                        <a href="login.php" class="text-decoration-none small text-secondary">
                            <i class="fa-solid fa-arrow-left me-1"></i> Back to Login Portal
                        </a>
                    </div>
                </div>
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
