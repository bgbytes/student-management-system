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
$id = $student_id = $name = $email = $phone = $course_id_val = $admission_date = "";
$student_id_err = $name_err = $email_err = $phone_err = $course_err = $admission_date_err = "";
$form_err = "";

// Check if GET request contains id parameter
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $id = trim($_GET["id"]);
    
    // Fetch student details from database
    $sql = "SELECT * FROM students WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $param_id);
        $param_id = $id;
        
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) == 1) {
                $row = mysqli_fetch_assoc($result);
                
                // Retrieve individual field values
                $student_id = $row["student_id"];
                $name = $row["name"];
                $email = $row["email"];
                $phone = $row["phone"];
                $course_id_val = $row["course_id"];
                $admission_date = $row["admission_date"];
            } else {
                $_SESSION['message'] = "Student record not found.";
                $_SESSION['msg_type'] = "danger";
                header("Location: students.php");
                exit();
            }
        } else {
            $form_err = "Oops! Something went wrong retrieving student details.";
        }
        mysqli_stmt_close($stmt);
    }
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get hidden input value
    $id = trim($_POST["id"]);
    
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
        // Check for duplicate Student ID excluding current record
        $sql = "SELECT id FROM students WHERE student_id = ? AND id != ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "si", $param_student_id, $param_id);
            $param_student_id = $input_student_id;
            $param_id = $id;
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $student_id_err = "This Student ID is already registered to another student.";
                } else {
                    $student_id = $input_student_id;
                }
            } else {
                $form_err = "Oops! Something went wrong checking Student ID duplication. Please try again.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Validate Name
    $input_name = trim($_POST["name"]);
    if (empty($input_name)) {
        $name_err = "Please enter the student's name.";
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
        // Check duplicate email
        $sql_check_email = "SELECT id FROM students WHERE email = ? AND id != ?";
        if ($stmt = mysqli_prepare($conn, $sql_check_email)) {
            mysqli_stmt_bind_param($stmt, "si", $input_email, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $email_err = "This email address is already linked to another student.";
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
    
    // Check input errors before updating in database
    if (empty($student_id_err) && empty($name_err) && empty($email_err) && empty($phone_err) && empty($course_err) && empty($admission_date_err)) {
        
        // Fetch old student_id to verify if we need to sync invoices or attendance (relational FK keys handle this, but let's update student)
        mysqli_begin_transaction($conn);
        try {
            // Update Student Profile Details
            $sql_update = "UPDATE students SET student_id = ?, name = ?, email = ?, phone = ?, course_id = ?, admission_date = ? WHERE id = ?";
            $stmt_update = mysqli_prepare($conn, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "ssssssi", $student_id, $name, $email, $phone, $course_id_val, $admission_date, $id);
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);
            
            mysqli_commit($conn);
            
            $_SESSION['message'] = "Student record updated successfully!";
            $_SESSION['msg_type'] = "success";
            
            header("Location: students.php");
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $form_err = "An error occurred while updating the record: " . $e->getMessage();
        }
    }
    
    // Close connection
    mysqli_close($conn);
} else {
    $_SESSION['message'] = "Invalid access to update student record.";
    $_SESSION['msg_type'] = "danger";
    header("Location: students.php");
    exit();
}

// Fetch all courses for selector
$courses_list = [];
$res_courses = mysqli_query($conn, "SELECT id, course_name FROM courses ORDER BY course_name ASC");
while ($row = mysqli_fetch_assoc($res_courses)) {
    $courses_list[] = $row;
}

$page_title = "Edit Student Profile";
$page_description = "Update student information inside the registry.";
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
                        <h2 class="fw-bold mb-1"><i class="fa-solid fa-user-pen text-primary me-2"></i>Edit Student Profile</h2>
                        <p class="text-muted small">Modify student parameters and click save changes.</p>
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
                        <!-- Hidden input to submit the ID -->
                        <input type="hidden" name="id" value="<?php echo $id; ?>"/>
                        
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
                                    <option value="" disabled>Choose a Course...</option>
                                    <?php foreach ($courses_list as $c): ?>
                                        <option value="<?php echo $c['id']; ?>" <?php echo ($course_id_val == $c['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['course_name']); ?>
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

                        <!-- Submit and Cancel Buttons -->
                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                            <a href="students.php" class="btn btn-light border rounded-pill px-4">Cancel</a>
                            <button type="submit" class="btn btn-primary-custom rounded-pill px-4">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
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
