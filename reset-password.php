<?php
// Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "config/db.php";

$username = isset($_GET['username']) ? trim($_GET['username']) : "";
$otp_code = $password = $confirm_password = "";
$username_err = $otp_code_err = $password_err = $confirm_password_err = $form_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Alert: CSRF token validation failed.");
    }
    
    // Validate fields
    $username = trim($_POST["username"]);
    $otp_code = trim($_POST["otp_code"]);
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);
    
    if (empty($username)) {
        $username_err = "Please enter username.";
    }
    if (empty($otp_code)) {
        $otp_code_err = "Please enter the OTP verification code.";
    }
    if (empty($password)) {
        $password_err = "Please enter your new password.";
    } elseif (strlen($password) < 6) {
        $password_err = "Password must have at least 6 characters.";
    }
    if (empty($confirm_password)) {
        $confirm_password_err = "Please confirm the password.";
    } else {
        if (empty($password_err) && ($password !== $confirm_password)) {
            $confirm_password_err = "Password confirmation does not match.";
        }
    }
    
    // Attempt verification and update if no errors
    if (empty($username_err) && empty($otp_code_err) && empty($password_err) && empty($confirm_password_err)) {
        
        // Fetch OTP credentials for user
        $sql = "SELECT id, otp_code, otp_expiry FROM users WHERE username = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            $param_username = $username;
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    mysqli_stmt_bind_result($stmt, $db_id, $db_otp, $db_expiry);
                    if (mysqli_stmt_fetch($stmt)) {
                        
                        $current_time = date("Y-m-d H:i:s");
                        
                        // Check if OTP matches and is not expired
                        if ($db_otp === $otp_code && strtotime($db_expiry) > strtotime($current_time)) {
                            // Valid OTP. Update password
                            $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                            $sql_update = "UPDATE users SET password = ?, otp_code = NULL, otp_expiry = NULL WHERE id = ?";
                            
                            if ($stmt_up = mysqli_prepare($conn, $sql_update)) {
                                mysqli_stmt_bind_param($stmt_up, "si", $hashed_pass, $db_id);
                                
                                if (mysqli_stmt_execute($stmt_up)) {
                                    $_SESSION['message'] = "Password has been reset successfully! Please sign in with your new password.";
                                    $_SESSION['msg_type'] = "success";
                                    
                                    header("Location: login.php");
                                    exit;
                                } else {
                                    $form_err = "Failed to update password. Please try again.";
                                }
                                mysqli_stmt_close($stmt_up);
                            }
                        } else {
                            if ($db_otp !== $otp_code) {
                                $otp_code_err = "Invalid security code. Please double-check the OTP.";
                            } else {
                                $otp_code_err = "This security code has expired. Please request a new OTP.";
                            }
                        }
                    }
                } else {
                    $username_err = "Username not registered.";
                }
            } else {
                $form_err = "Error verifying details. Please try again.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    mysqli_close($conn);
}

$page_title = "Reset Password";
$page_description = "Complete password restoration by verifying your OTP code.";
include 'includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card glass-card border-0 p-4">
                <div class="card-body">
                    <!-- Title -->
                    <div class="text-center mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-3" style="width: 60px; height: 60px;">
                            <i class="fa-solid fa-user-shield fa-xl"></i>
                        </div>
                        <h2 class="fw-bold mb-1">Verify OTP & Reset</h2>
                        <p class="text-muted small">Enter code sent to email to apply new password</p>
                    </div>

                    <!-- Flash Message -->
                    <?php 
                    if (isset($_SESSION['message'])): 
                        $msg_type = isset($_SESSION['msg_type']) ? $_SESSION['msg_type'] : 'info';
                    ?>
                        <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show d-flex align-items-center mb-3" role="alert">
                            <i class="fa-solid fa-circle-check me-2"></i>
                            <div>
                                <?php 
                                echo htmlspecialchars($_SESSION['message']); 
                                unset($_SESSION['message']);
                                unset($_SESSION['msg_type']);
                                ?>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

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
                        <div class="mb-3">
                            <label for="username" class="form-label fw-semibold small text-secondary">Username (Portal ID)</label>
                            <input type="text" name="username" id="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username); ?>" required placeholder="e.g., admin or STU-2026-001">
                            <?php if (!empty($username_err)): ?>
                                <div class="invalid-feedback"><?php echo $username_err; ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- OTP Code -->
                        <div class="mb-3">
                            <label for="otp_code" class="form-label fw-semibold small text-secondary">6-Digit OTP Security Code</label>
                            <input type="text" name="otp_code" id="otp_code" class="form-control text-center fw-bold fs-4 letter-spacing-3 <?php echo (!empty($otp_code_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($otp_code); ?>" required placeholder="e.g., 123456" maxlength="6">
                            <?php if (!empty($otp_code_err)): ?>
                                <div class="invalid-feedback"><?php echo $otp_code_err; ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="row g-2">
                            <!-- New Password -->
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label fw-semibold small text-secondary">New Password</label>
                                <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" required placeholder="Min 6 characters">
                                <?php if (!empty($password_err)): ?>
                                    <div class="invalid-feedback"><?php echo $password_err; ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Confirm Password -->
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label fw-semibold small text-secondary">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" required placeholder="Re-enter password">
                                <?php if (!empty($confirm_password_err)): ?>
                                    <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="d-grid gap-2 mt-2">
                            <button type="submit" class="btn btn-primary-custom py-2">
                                <i class="fa-solid fa-circle-check me-2"></i> Save New Password
                            </button>
                            <a href="forgot-password.php" class="btn btn-light border py-2">
                                <i class="fa-solid fa-arrows-rotate me-2"></i> Request Another OTP
                            </a>
                        </div>
                    </form>
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
