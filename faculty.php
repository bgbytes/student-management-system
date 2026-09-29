<?php
// Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once "config/db.php";

// Initialize variables
$faculty_id = $name = $email = $phone = $department = "";
$faculty_id_err = $name_err = $email_err = $phone_err = $department_err = "";
$form_err = "";

// Handle Delete Request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    // Validate CSRF token
    if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Alert: CSRF token verification failed.");
    }
    
    $delete_id = intval($_GET['id']);
    
    // Delete faculty profile (credentials are stored inside the faculty table itself)
    $stmt_del_fac = mysqli_prepare($conn, "DELETE FROM faculty WHERE id = ?");
    mysqli_stmt_bind_param($stmt_del_fac, "i", $delete_id);
    if (mysqli_stmt_execute($stmt_del_fac)) {
        $_SESSION['message'] = "Faculty member profile deleted successfully.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['message'] = "Error deleting faculty record.";
        $_SESSION['msg_type'] = "danger";
    }
    mysqli_stmt_close($stmt_del_fac);
    
    header("Location: faculty.php");
    exit;
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Alert: CSRF token validation failed.");
    }
    
    // Validate Faculty ID
    $input_fac_id = trim($_POST["faculty_id"]);
    if (empty($input_fac_id)) {
        $faculty_id_err = "Please enter the Faculty ID.";
    } elseif (!preg_match('/^[a-zA-Z0-9\-]+$/', $input_fac_id)) {
        $faculty_id_err = "Faculty ID can only contain letters, numbers, and hyphens.";
    } else {
        // Check for duplicate Faculty ID
        $sql = "SELECT id FROM faculty WHERE faculty_id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $param_fac_id);
            $param_fac_id = $input_fac_id;
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $faculty_id_err = "This Faculty ID is already registered.";
                } else {
                    $faculty_id = $input_fac_id;
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Validate Name
    $input_name = trim($_POST["name"]);
    if (empty($input_name)) {
        $name_err = "Please enter full name.";
    } elseif (!preg_match('/^[a-zA-Z\s\.]+$/', $input_name)) {
        $name_err = "Name can only contain letters, spaces, and periods.";
    } else {
        $name = $input_name;
    }
    
    // Validate Email
    $input_email = trim($_POST["email"]);
    if (empty($input_email)) {
        $email_err = "Please enter email.";
    } elseif (!filter_var($input_email, FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email.";
    } else {
        // Check duplicate email in faculty
        $sql_check_email = "SELECT id FROM faculty WHERE email = ?";
        if ($stmt = mysqli_prepare($conn, $sql_check_email)) {
            mysqli_stmt_bind_param($stmt, "s", $input_email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $email_err = "This email is already registered to another faculty member.";
            } else {
                $email = $input_email;
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Validate Phone
    $input_phone = trim($_POST["phone"]);
    if (empty($input_phone)) {
        $phone_err = "Please enter phone number.";
    } else {
        $phone = $input_phone;
    }
    
    // Validate Department
    $input_dept = trim($_POST["department"]);
    if (empty($input_dept)) {
        $department_err = "Please select department.";
    } else {
        $department = $input_dept;
    }
    
    // Check input errors before inserting
    if (empty($faculty_id_err) && empty($name_err) && empty($email_err) && empty($phone_err) && empty($department_err)) {
        
        // Insert Faculty details directly (with hashed default password: faculty123)
        $default_pass_hash = password_hash('faculty123', PASSWORD_DEFAULT);
        $sql_fac = "INSERT INTO faculty (faculty_id, name, email, phone, department, password) VALUES (?, ?, ?, ?, ?, ?)";
        
        if ($stmt_fac = mysqli_prepare($conn, $sql_fac)) {
            mysqli_stmt_bind_param($stmt_fac, "ssssss", $faculty_id, $name, $email, $phone, $department, $default_pass_hash);
            
            if (mysqli_stmt_execute($stmt_fac)) {
                $_SESSION['message'] = "Faculty member onboarded successfully!";
                $_SESSION['msg_type'] = "success";
                
                mysqli_stmt_close($stmt_fac);
                mysqli_close($conn);
                header("Location: faculty.php");
                exit;
            } else {
                $form_err = "Error saving profile details. Please try again.";
            }
            mysqli_stmt_close($stmt_fac);
        }
    }
}

// Fetch all faculty members
$sql_list = "SELECT * FROM faculty ORDER BY id DESC";
$faculty_list = mysqli_query($conn, $sql_list);

$page_title = "Manage Faculty";
$page_description = "Admin panel to view, add, and delete faculty members.";
include 'includes/header.php';
?>

<div class="container mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1">Faculty Registry</h1>
            <p class="text-muted mb-0">Manage and onboard instructional personnel</p>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php 
    if (isset($_SESSION['message'])): 
        $msg_type = $_SESSION['message'] ? $_SESSION['msg_type'] : 'info';
        $icon = $msg_type === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation';
    ?>
        <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
            <i class="fa-solid <?php echo $icon; ?> me-2"></i>
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

    <div class="row g-4">
        <!-- Faculty Directory List (Col-7) -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-12 p-3">
                <h3 class="h5 fw-bold px-2 mb-3">Faculty Roster</h3>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Faculty ID</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Email</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($faculty_list && mysqli_num_rows($faculty_list) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($faculty_list)): ?>
                                    <tr>
                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['faculty_id']); ?></span></td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><span class="badge bg-primary bg-opacity-10 text-primary"><?php echo htmlspecialchars($row['department']); ?></span></td>
                                        <td><span class="small text-secondary"><?php echo htmlspecialchars($row['email']); ?></span></td>
                                        <td class="text-center">
                                            <a href="faculty.php?action=delete&id=<?php echo $row['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($row['name']); ?>? This removes their dashboard access.');"
                                               title="Delete Faculty">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No faculty records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add Faculty Form (Col-5) -->
        <div class="col-lg-5">
            <div class="card glass-card border-0 p-4">
                <h3 class="h5 fw-bold mb-3 border-bottom pb-2"><i class="fa-solid fa-user-plus text-primary me-2"></i>Onboard Faculty</h3>
                
                <?php if (!empty($form_err)): ?>
                    <div class="alert alert-danger py-2" role="alert">
                        <i class="fa-solid fa-circle-exclamation me-1"></i> <?php echo htmlspecialchars($form_err); ?>
                    </div>
                <?php endif; ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="needs-validation" novalidate>
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <!-- Faculty ID -->
                    <div class="mb-3">
                        <label for="faculty_id" class="form-label fw-semibold small text-secondary">Faculty ID</label>
                        <input type="text" name="faculty_id" id="faculty_id" class="form-control <?php echo (!empty($faculty_id_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($faculty_id); ?>" required placeholder="e.g., FAC-2026-002">
                        <?php if (!empty($faculty_id_err)): ?>
                            <div class="invalid-feedback"><?php echo $faculty_id_err; ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Full Name -->
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold small text-secondary">Full Name</label>
                        <input type="text" name="name" id="name" class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($name); ?>" required placeholder="e.g., Prof. Sarah Jenkins">
                        <?php if (!empty($name_err)): ?>
                            <div class="invalid-feedback"><?php echo $name_err; ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Email -->
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold small text-secondary">Email Address</label>
                        <input type="email" name="email" id="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>" required placeholder="e.g., sarah.jenkins@edumanage.com">
                        <?php if (!empty($email_err)): ?>
                            <div class="invalid-feedback"><?php echo $email_err; ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Phone -->
                    <div class="mb-3">
                        <label for="phone" class="form-label fw-semibold small text-secondary">Phone Number</label>
                        <input type="text" name="phone" id="phone" class="form-control <?php echo (!empty($phone_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($phone); ?>" required placeholder="e.g., +1 555-0144">
                        <?php if (!empty($phone_err)): ?>
                            <div class="invalid-feedback"><?php echo $phone_err; ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Department -->
                    <div class="mb-4">
                        <label for="department" class="form-label fw-semibold small text-secondary">Assigned Department</label>
                        <select name="department" id="department" class="form-select <?php echo (!empty($department_err)) ? 'is-invalid' : ''; ?>" required>
                            <option value="" disabled selected>Choose a Department...</option>
                            <option value="Computer Science">Computer Science</option>
                            <option value="Information Technology">Information Technology</option>
                            <option value="Business Administration">Business Administration</option>
                            <option value="Mechanical Engineering">Mechanical Engineering</option>
                            <option value="Electrical Engineering">Electrical Engineering</option>
                            <option value="Civil Engineering">Civil Engineering</option>
                            <option value="Data Science">Data Science</option>
                            <option value="Cyber Security">Cyber Security</option>
                        </select>
                        <?php if (!empty($department_err)): ?>
                            <div class="invalid-feedback"><?php echo $department_err; ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Submit -->
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary-custom">
                            <i class="fa-solid fa-save me-1"></i> Onboard Faculty
                        </button>
                    </div>
                </form>
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

<?php 
mysqli_close($conn);
include 'includes/footer.php'; 
?>
