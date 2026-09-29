<?php
// Session check at the very top of protected admin pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Include database configuration
require_once "config/db.php";

// Define variables and initialize with empty values
$student_id = $name = $email = $phone = $course_id_val = $admission_date = "";
$student_id_err = $name_err = $email_err = $phone_err = $course_err = $admission_date_err = "";
$form_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Alert: CSRF token validation failed.");
    }
    
    // Validate Student ID
    $input_student_id = trim($_POST["student_id"]);
    if (empty($input_student_id)) {
        $student_id_err = "Please enter the Student ID.";
    } elseif (!preg_match('/^[a-zA-Z0-9\-]+$/', $input_student_id)) {
        $student_id_err = "Student ID can only contain letters, numbers, and hyphens.";
    } else {
        // Check for duplicate Student ID in database
        $sql = "SELECT id FROM students WHERE student_id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $param_student_id);
            $param_student_id = $input_student_id;
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $student_id_err = "This Student ID is already registered.";
                } else {
                    $student_id = $input_student_id;
                }
            } else {
                $form_err = "Oops! Something went wrong checking Student ID. Please try again.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Validate Name
    $input_name = trim($_POST["name"]);
    if (empty($input_name)) {
        $name_err = "Please enter the student's full name.";
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $input_name)) {
        $name_err = "Name can only contain letters and spaces.";
    } else {
        $name = $input_name;
    }
    
    // Validate Email
    $input_email = trim($_POST["email"]);
    if (empty($input_email)) {
        $email_err = "Please enter the email address.";
    } elseif (!filter_var($input_email, FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        // Check duplicate email in students
        $sql_check_email = "SELECT id FROM students WHERE email = ?";
        if ($stmt = mysqli_prepare($conn, $sql_check_email)) {
            mysqli_stmt_bind_param($stmt, "s", $input_email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $email_err = "This email address is already registered.";
            } else {
                $email = $input_email;
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Validate Phone
    $input_phone = trim($_POST["phone"]);
    if (empty($input_phone)) {
        $phone_err = "Please enter the phone number.";
    } elseif (!preg_match('/^[0-9\-\+\s\(\)]+$/', $input_phone)) {
        $phone_err = "Please enter a valid phone number.";
    } else {
        $phone = $input_phone;
    }
    
    // Validate Course ID
    $input_course_id = isset($_POST["course_id"]) ? intval($_POST["course_id"]) : 0;
    if ($input_course_id <= 0) {
        $course_err = "Please select a course.";
    } else {
        $course_id_val = $input_course_id;
    }
    
    // Validate Admission Date
    $input_date = trim($_POST["admission_date"]);
    if (empty($input_date)) {
        $admission_date_err = "Please select the admission date.";
    } else {
        $admission_date = $input_date;
    }
    
    // Check input errors before inserting into database
    if (empty($student_id_err) && empty($name_err) && empty($email_err) && empty($phone_err) && empty($course_err) && empty($admission_date_err)) {
        
        // Start Transaction to register Student and Invoice atomically
        mysqli_begin_transaction($conn);
        
        try {
            // 1. Prepare and Insert Student Registry Record (with hashed default password: student123)
            $default_pass_hash = password_hash('student123', PASSWORD_DEFAULT);
            $sql_student = "INSERT INTO students (student_id, name, email, phone, course_id, admission_date, password) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_student = mysqli_prepare($conn, $sql_student);
            mysqli_stmt_bind_param($stmt_student, "ssssiss", $student_id, $name, $email, $phone, $course_id_val, $admission_date, $default_pass_hash);
            mysqli_stmt_execute($stmt_student);
            mysqli_stmt_close($stmt_student);
            
            // 2. Retrieve Course Base Fee in INR
            $course_fee_inr = 0.00;
            $sql_fee = "SELECT base_fee_inr FROM courses WHERE id = ?";
            $stmt_fee = mysqli_prepare($conn, $sql_fee);
            mysqli_stmt_bind_param($stmt_fee, "i", $course_id_val);
            mysqli_stmt_execute($stmt_fee);
            $res_fee = mysqli_stmt_get_result($stmt_fee);
            if ($row_fee = mysqli_fetch_assoc($res_fee)) {
                $course_fee_inr = $row_fee['base_fee_inr'];
            }
            mysqli_stmt_close($stmt_fee);
            
            // 3. Create billing invoice in fee_ledger with Pending status, due in 30 days
            $due_date = date('Y-m-d', strtotime('+30 days', strtotime($admission_date)));
            $sql_inv = "INSERT INTO fee_ledger (student_id, course_id, amount_due_inr, status, due_date) VALUES (?, ?, ?, 'Pending', ?)";
            $stmt_inv = mysqli_prepare($conn, $sql_inv);
            mysqli_stmt_bind_param($stmt_inv, "sids", $student_id, $course_id_val, $course_fee_inr, $due_date);
            mysqli_stmt_execute($stmt_inv);
            mysqli_stmt_close($stmt_inv);
            
            // Commit all transactions
            mysqli_commit($conn);
            
            $_SESSION['message'] = "Student registered, portal access synchronized, and base fee ledger invoice generated successfully!";
            $_SESSION['msg_type'] = "success";
            
            mysqli_close($conn);
            header("Location: students.php");
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $form_err = "An error occurred while saving the records: " . $e->getMessage();
        }
    }
}

// Fetch all courses for selector
$courses_list = [];
$res_courses = mysqli_query($conn, "SELECT id, course_name, base_fee_inr FROM courses ORDER BY course_name ASC");
while ($row = mysqli_fetch_assoc($res_courses)) {
    $courses_list[] = $row;
}

$page_title = "Register New Student";
$page_description = "Add a new student profile to the administrative registry.";
include 'includes/header.php';
?>

<div class="container mb-5">
    <!-- Back Trigger -->
    <div class="mb-4">
        <a href="students.php" class="text-decoration-none text-muted small">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Student Directory
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card glass-card border-0 p-4">
                <div class="card-body">
                    <!-- Form Title -->
                    <div class="mb-4 border-bottom pb-3">
                        <h2 class="fw-bold mb-1"><i class="fa-solid fa-user-plus text-primary me-2"></i>Register Student</h2>
                        <p class="text-muted small">All fields are mandatory. Student ID and Email must be unique. Roster bills will be generated in ₹ INR.</p>
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
                        <!-- CSRF Token Input -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="row g-3">
                            <!-- Student ID -->
                            <div class="col-md-6">
                                <label for="student_id" class="form-label fw-semibold small text-secondary">Student ID</label>
                                <input type="text" name="student_id" id="student_id" class="form-control <?php echo (!empty($student_id_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($student_id); ?>" required placeholder="e.g., STU-2026-001">
                                <?php if (!empty($student_id_err)): ?>
                                    <div class="invalid-feedback"><?php echo $student_id_err; ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Full Name -->
                            <div class="col-md-6">
                                <label for="name" class="form-label fw-semibold small text-secondary">Full Name</label>
                                <input type="text" name="name" id="name" class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($name); ?>" required placeholder="e.g., John Doe">
                                <?php if (!empty($name_err)): ?>
                                    <div class="invalid-feedback"><?php echo $name_err; ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Email Address -->
                            <div class="col-md-6">
                                <label for="email" class="form-label fw-semibold small text-secondary">Email Address</label>
                                <input type="email" name="email" id="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>" required placeholder="e.g., john.doe@example.com">
                                <?php if (!empty($email_err)): ?>
                                    <div class="invalid-feedback"><?php echo $email_err; ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Phone Number -->
                            <div class="col-md-6">
                                <label for="phone" class="form-label fw-semibold small text-secondary">Phone Number</label>
                                <input type="text" name="phone" id="phone" class="form-control <?php echo (!empty($phone_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($phone); ?>" required placeholder="e.g., +1 234-567-890">
                                <?php if (!empty($phone_err)): ?>
                                    <div class="invalid-feedback"><?php echo $phone_err; ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Course Dropdown -->
                            <div class="col-md-6">
                                <label for="course_id" class="form-label fw-semibold small text-secondary">Course Enrolled</label>
                                <select name="course_id" id="course_id" class="form-select <?php echo (!empty($course_err)) ? 'is-invalid' : ''; ?>" required>
                                    <option value="" disabled <?php echo empty($course_id_val) ? 'selected' : ''; ?>>Choose a Course...</option>
                                    <?php foreach ($courses_list as $c): ?>
                                        <option value="<?php echo $c['id']; ?>" <?php echo ($course_id_val == $c['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['course_name']); ?> (₹<?php echo number_format($c['base_fee_inr'], 2); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!empty($course_err)): ?>
                                    <div class="invalid-feedback"><?php echo $course_err; ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Admission Date -->
                            <div class="col-md-6">
                                <label for="admission_date" class="form-label fw-semibold small text-secondary">Admission Date</label>
                                <input type="date" name="admission_date" id="admission_date" class="form-control <?php echo (!empty($admission_date_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($admission_date); ?>" required>
                                <?php if (!empty($admission_date_err)): ?>
                                    <div class="invalid-feedback"><?php echo $admission_date_err; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Submit and Reset Buttons -->
                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                            <button type="reset" class="btn btn-light border rounded-pill px-4">Reset Form</button>
                            <button type="submit" class="btn btn-primary-custom rounded-pill px-4">
                                <i class="fa-solid fa-save me-1"></i> Register Student
                            </button>
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
