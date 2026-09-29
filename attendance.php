<?php
// Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security: Allow Faculty (primary logging) and Admins (viewing/logging)
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || !in_array($_SESSION['user_role'], ['admin', 'faculty'])) {
    header("Location: login.php");
    exit;
}

require_once "config/db.php";

$logged_faculty_id = "";
if ($_SESSION['user_role'] === 'faculty') {
    $logged_faculty_id = $_SESSION['username']; // Faculty ID
} else {
    // Admins default to the seeded faculty ID or first faculty in table
    $res_fac = mysqli_query($conn, "SELECT faculty_id FROM faculty LIMIT 1");
    if ($row_fac = mysqli_fetch_assoc($res_fac)) {
        $logged_faculty_id = $row_fac['faculty_id'];
    } else {
        $logged_faculty_id = "FAC-2026-001";
    }
}

// Filters
$selected_course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$selected_date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');

$form_success = $form_err = "";

// Handle Saving Attendance
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_attendance'])) {
    // CSRF Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Alert: CSRF token verification failed.");
    }
    
    $post_course_id = intval($_POST['course_id']);
    $post_date = trim($_POST['date']);
    $statuses = isset($_POST['status']) ? $_POST['status'] : []; // student_id => Present/Absent/Leave
    
    if ($post_course_id <= 0 || empty($post_date)) {
        $form_err = "Course selection and Date are required.";
    } else {
        mysqli_begin_transaction($conn);
        try {
            // First select all students in this course to process attendance
            $sql_st = "SELECT student_id FROM students WHERE course_id = ?";
            $stmt_st = mysqli_prepare($conn, $sql_st);
            mysqli_stmt_bind_param($stmt_st, "i", $post_course_id);
            mysqli_stmt_execute($stmt_st);
            $res_st = mysqli_stmt_get_result($stmt_st);
            
            while ($st_row = mysqli_fetch_assoc($res_st)) {
                $sid = $st_row['student_id'];
                
                // Determine status (Present, Absent, Leave). Default to 'Absent' if not set
                $status = isset($statuses[$sid]) && in_array($statuses[$sid], ['Present', 'Absent', 'Leave']) ? $statuses[$sid] : 'Absent';
                
                $sql_ins = "INSERT INTO attendance (student_id, faculty_id, course_id, date, status) 
                            VALUES (?, ?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE status = VALUES(status)";
                $stmt_ins = mysqli_prepare($conn, $sql_ins);
                mysqli_stmt_bind_param($stmt_ins, "ssiss", $sid, $logged_faculty_id, $post_course_id, $post_date, $status);
                mysqli_stmt_execute($stmt_ins);
                mysqli_stmt_close($stmt_ins);
            }
            mysqli_stmt_close($stmt_st);
            mysqli_commit($conn);
            $form_success = "Attendance roster logged successfully.";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $form_err = "Error logging attendance: " . $e->getMessage();
        }
    }
}

// Fetch all courses for the dropdown select menu
$courses_list = [];
$res_c = mysqli_query($conn, "SELECT id, course_name FROM courses ORDER BY course_name ASC");
while ($row = mysqli_fetch_assoc($res_c)) {
    $courses_list[] = $row;
}

// Auto-select first course if none is selected
if ($selected_course_id <= 0 && count($courses_list) > 0) {
    $selected_course_id = $courses_list[0]['id'];
}

// Fetch students with existing attendance for the selected course & date
$students = [];
if ($selected_course_id > 0) {
    $sql_stud = "SELECT s.*, a.status as attendance_status 
                 FROM students s 
                 LEFT JOIN attendance a ON s.student_id = a.student_id AND a.course_id = ? AND a.date = ? 
                 WHERE s.course_id = ? 
                 ORDER BY s.name ASC";
    if ($stmt_stud = mysqli_prepare($conn, $sql_stud)) {
        mysqli_stmt_bind_param($stmt_stud, "isi", $selected_course_id, $selected_date, $selected_course_id);
        if (mysqli_stmt_execute($stmt_stud)) {
            $res_stud = mysqli_stmt_get_result($stmt_stud);
            while ($row = mysqli_fetch_assoc($res_stud)) {
                $students[] = $row;
            }
        }
        mysqli_stmt_close($stmt_stud);
    }
}

$page_title = "Course-wise Attendance Tracker";
$page_description = "Track student presence, absence, and leaves per course dynamically.";
include 'includes/header.php';
?>

<div class="container mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1"><i class="fa-solid fa-clipboard-user text-primary me-2"></i>Attendance Logging</h1>
            <p class="text-muted mb-0">Select course and date to update class roster attendance</p>
        </div>
    </div>

    <!-- Alert Dialogues -->
    <?php if (!empty($form_success)): ?>
        <div class="alert alert-success d-flex align-items-center" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>
            <div><?php echo htmlspecialchars($form_success); ?></div>
        </div>
    <?php endif; ?>
    <?php if (!empty($form_err)): ?>
        <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            <div><?php echo htmlspecialchars($form_err); ?></div>
        </div>
    <?php endif; ?>

    <!-- dynamic dropdown menu -->
    <div class="card border-0 shadow-sm rounded-12 p-3 mb-4">
        <form method="GET" action="attendance.php" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="course_id" class="form-label fw-semibold small text-secondary">Course Selection</label>
                <select name="course_id" id="course_id" class="form-select" required>
                    <option value="" disabled>Choose a course...</option>
                    <?php foreach ($courses_list as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($selected_course_id == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label for="date" class="form-label fw-semibold small text-secondary">Academic Date</label>
                <input type="date" name="date" id="date" class="form-control" value="<?php echo htmlspecialchars($selected_date); ?>" required>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary-custom">
                    <i class="fa-solid fa-filter me-1"></i> Retrieve Batch
                </button>
            </div>
        </form>
    </div>

    <?php if ($selected_course_id > 0): ?>
        <!-- Roster / Logging Area -->
        <div class="card border-0 shadow-sm rounded-12 p-3">
            <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                <?php 
                $c_name = "";
                foreach ($courses_list as $cl) {
                    if ($cl['id'] == $selected_course_id) {
                        $c_name = $cl['course_name'];
                        break;
                    }
                }
                ?>
                <h3 class="h5 fw-bold mb-0">Roster for <span class="text-indigo"><?php echo htmlspecialchars($c_name); ?></span></h3>
                <span class="badge bg-indigo-subtle text-indigo px-2 py-1"><?php echo date('F d, Y', strtotime($selected_date)); ?></span>
            </div>
            
            <form method="POST" action="attendance.php?course_id=<?php echo $selected_course_id; ?>&date=<?php echo urlencode($selected_date); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="course_id" value="<?php echo $selected_course_id; ?>">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($selected_date); ?>">

                <div class="table-responsive mb-3">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width: 150px;">Student ID</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th class="text-center" style="width: 300px;">Attendance State</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($students) > 0): ?>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td class="ps-3"><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($student['student_id']); ?></span></td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td><span class="small text-secondary"><?php echo htmlspecialchars($student['email']); ?></span></td>
                                        <td class="text-center">
                                            <div class="btn-group w-100" role="group">
                                                <!-- Present -->
                                                <input type="radio" 
                                                       class="btn-check" 
                                                       name="status[<?php echo $student['student_id']; ?>]" 
                                                       id="pres_<?php echo $student['student_id']; ?>" 
                                                       value="Present" 
                                                       autocomplete="off" 
                                                       <?php echo ($student['attendance_status'] === 'Present' || is_null($student['attendance_status'])) ? 'checked' : ''; ?>>
                                                <label class="btn btn-outline-success btn-sm py-1" for="pres_<?php echo $student['student_id']; ?>">
                                                    Present
                                                </label>

                                                <!-- Absent -->
                                                <input type="radio" 
                                                       class="btn-check" 
                                                       name="status[<?php echo $student['student_id']; ?>]" 
                                                       id="abs_<?php echo $student['student_id']; ?>" 
                                                       value="Absent" 
                                                       autocomplete="off" 
                                                       <?php echo ($student['attendance_status'] === 'Absent') ? 'checked' : ''; ?>>
                                                <label class="btn btn-outline-danger btn-sm py-1" for="abs_<?php echo $student['student_id']; ?>">
                                                    Absent
                                                </label>

                                                <!-- Leave -->
                                                <input type="radio" 
                                                       class="btn-check" 
                                                       name="status[<?php echo $student['student_id']; ?>]" 
                                                       id="leave_<?php echo $student['student_id']; ?>" 
                                                       value="Leave" 
                                                       autocomplete="off" 
                                                       <?php echo ($student['attendance_status'] === 'Leave') ? 'checked' : ''; ?>>
                                                <label class="btn btn-outline-warning btn-sm py-1" for="leave_<?php echo $student['student_id']; ?>">
                                                    Leave
                                                </label>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No students are currently registered in this course track.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (count($students) > 0): ?>
                    <div class="d-flex justify-content-end gap-2 border-top pt-3 px-2">
                        <a href="export.php?type=attendance" class="btn btn-light border rounded-pill px-3">
                            <i class="fa-solid fa-file-csv"></i> Export Attendance
                        </a>
                        <button type="submit" name="save_attendance" class="btn btn-primary-custom px-4">
                            <i class="fa-solid fa-cloud-arrow-up me-1"></i> Save Attendance States
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php 
mysqli_close($conn);
include 'includes/footer.php'; 
?>
