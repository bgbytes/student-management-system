<?php
// Session check at the very top of protected pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['faculty_logged_in']) || $_SESSION['faculty_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Include database configuration
require_once "config/db.php";

$faculty_id = $_SESSION['ref_id'];
$faculty_name = "";
$faculty_dept = "";
$error_msg = "";

// Retrieve Faculty Profile
try {
    $sql_profile = "SELECT * FROM faculty WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql_profile)) {
        mysqli_stmt_bind_param($stmt, "i", $faculty_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $faculty_name = $row['name'];
                $faculty_dept = $row['department'];
            }
        } else {
            $error_msg = "Error executing profile retrieval: " . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    } else {
        $error_msg = "Failed to prepare faculty profile query.";
    }
} catch (Exception $e) {
    $error_msg = "Profile Query Exception: " . $e->getMessage();
}

// Resolve Department to Course ID (relational structure)
$faculty_course_id = 0;
if (!empty($faculty_dept) && empty($error_msg)) {
    try {
        $sql_course = "SELECT id FROM courses WHERE course_name = ?";
        if ($stmt = mysqli_prepare($conn, $sql_course)) {
            mysqli_stmt_bind_param($stmt, "s", $faculty_dept);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                if ($row = mysqli_fetch_assoc($result)) {
                    $faculty_course_id = $row['id'];
                }
            } else {
                $error_msg = "Error executing course mapping: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_msg = "Failed to prepare course mapping query.";
        }
    } catch (Exception $e) {
        $error_msg = "Course Mapping Exception: " . $e->getMessage();
    }
}

// Handle fallback warning if course ID couldn't be resolved but department exists
if (empty($error_msg) && $faculty_course_id === 0 && !empty($faculty_dept)) {
    $error_msg = "Unable to find a course track matching department: '" . htmlspecialchars($faculty_dept) . "'. Stats and roster could not be loaded.";
}

// Initialize departmental stats
$dept_students_count = 0;
$dept_avg_grade = 0.00;
$dept_attendance_count = 0;
$students_result = null;

if ($faculty_course_id > 0 && empty($error_msg)) {
    // Query 1: Departmental Students Count (Relational via course_id)
    try {
        $sql_count = "SELECT COUNT(*) as total FROM students WHERE course_id = ?";
        if ($stmt = mysqli_prepare($conn, $sql_count)) {
            mysqli_stmt_bind_param($stmt, "i", $faculty_course_id);
            if (mysqli_stmt_execute($stmt)) {
                $res = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($res);
                $dept_students_count = $row['total'];
            } else {
                $error_msg = "Error executing student count: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_msg = "Failed to prepare student count query.";
        }
    } catch (Exception $e) {
        $error_msg = "Student Count Exception: " . $e->getMessage();
    }

    // Query 2: Departmental Average grade score (Relational via course_id on marks table)
    try {
        $sql_avg = "SELECT AVG(score) as avg_score FROM marks WHERE course_id = ?";
        if ($stmt = mysqli_prepare($conn, $sql_avg)) {
            mysqli_stmt_bind_param($stmt, "i", $faculty_course_id);
            if (mysqli_stmt_execute($stmt)) {
                $res = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($res);
                $dept_avg_grade = $row['avg_score'] ? round($row['avg_score'], 2) : 0.00;
            } else {
                $error_msg = "Error executing average grade: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_msg = "Failed to prepare average grade query.";
        }
    } catch (Exception $e) {
        $error_msg = "Average Grade Exception: " . $e->getMessage();
    }

    // Query 3: Departmental Attendance Log Count (Relational via course_id)
    try {
        $sql_attendance = "SELECT COUNT(*) as total FROM attendance WHERE course_id = ?";
        if ($stmt = mysqli_prepare($conn, $sql_attendance)) {
            mysqli_stmt_bind_param($stmt, "i", $faculty_course_id);
            if (mysqli_stmt_execute($stmt)) {
                $res = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($res);
                $dept_attendance_count = $row['total'];
            } else {
                $error_msg = "Error executing attendance count: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_msg = "Failed to prepare attendance count query.";
        }
    } catch (Exception $e) {
        $error_msg = "Attendance Count Exception: " . $e->getMessage();
    }

    // Query 4: List students in the faculty's department (Relational via course_id)
    try {
        $sql_stud_list = "SELECT * FROM students WHERE course_id = ? ORDER BY name ASC LIMIT 5";
        if ($stmt = mysqli_prepare($conn, $sql_stud_list)) {
            mysqli_stmt_bind_param($stmt, "i", $faculty_course_id);
            if (mysqli_stmt_execute($stmt)) {
                $students_result = mysqli_stmt_get_result($stmt);
            } else {
                $error_msg = "Error executing student roster: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_msg = "Failed to prepare student roster query.";
        }
    } catch (Exception $e) {
        $error_msg = "Student Roster Exception: " . $e->getMessage();
    }
}

$page_title = "Faculty Dashboard";
$page_description = "Overview of departmental metrics, grades, and attendance logging.";
include 'includes/header.php';
?>

<div class="container mb-5">
    <!-- Status/Error Alerts -->
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-12 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-triangle-exclamation me-3 fa-lg"></i>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">System Alert</h6>
                    <p class="mb-0 small"><?php echo htmlspecialchars($error_msg); ?></p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Welcome Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h1 class="fw-bold mb-1">Faculty Portal</h1>
            <p class="text-muted mb-0">Welcome back, <strong><?php echo htmlspecialchars($faculty_name); ?></strong>! Department: <strong><?php echo htmlspecialchars($faculty_dept); ?></strong></p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="attendance.php" class="btn btn-primary-custom rounded-pill">
                <i class="fa-solid fa-calendar-check me-1"></i> Log Attendance
            </a>
            <a href="grades.php" class="btn btn-secondary-custom rounded-pill">
                <i class="fa-solid fa-graduation-cap me-1"></i> Enter Exam Grades
            </a>
        </div>
    </div>

    <!-- Quick Stats Cards Row -->
    <div class="row g-4 mb-5">
        <!-- Stat Card: Department Students -->
        <div class="col-md-6 col-lg-3">
            <div class="card stat-card text-white border-0 bg-primary h-100" style="background: var(--primary-gradient) !important;">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="card-title text-uppercase mb-2 text-white-50 small fw-bold">Department Students</h5>
                            <span class="display-5 fw-bold"><?php echo $dept_students_count; ?></span>
                        </div>
                        <div class="stat-icon text-white-50 fs-2">
                            <i class="fa-solid fa-user-graduate"></i>
                        </div>
                    </div>
                    <div class="mt-auto text-white-50 small">
                        <i class="fa-solid fa-circle-info me-1"></i> Registered in <?php echo htmlspecialchars($faculty_dept ? $faculty_dept : "Department"); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stat Card: Average Score -->
        <div class="col-md-6 col-lg-3">
            <div class="card stat-card text-white border-0 bg-success h-100" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="card-title text-uppercase mb-2 text-white-50 small fw-bold">Class Average</h5>
                            <span class="display-5 fw-bold"><?php echo $dept_avg_grade; ?>%</span>
                        </div>
                        <div class="stat-icon text-white-50 fs-2">
                            <i class="fa-solid fa-chart-bar"></i>
                        </div>
                    </div>
                    <div class="mt-auto text-white-50 small">
                        <i class="fa-solid fa-chart-line me-1"></i> Term examination average
                    </div>
                </div>
            </div>
        </div>

        <!-- Stat Card: Attendance Records -->
        <div class="col-md-6 col-lg-3">
            <div class="card stat-card text-white border-0 bg-warning h-100" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="card-title text-uppercase mb-2 text-white-50 small fw-bold">Attendance Logs</h5>
                            <span class="display-5 fw-bold"><?php echo $dept_attendance_count; ?></span>
                        </div>
                        <div class="stat-icon text-white-50 fs-2">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="mt-auto text-white-50 small">
                        <i class="fa-solid fa-clipboard-user me-1"></i> Total logged sessions
                    </div>
                </div>
            </div>
        </div>

        <!-- Stat Card: Quick Shortcut -->
        <div class="col-md-6 col-lg-3">
            <div class="card stat-card text-white border-0 bg-secondary h-100" style="background: var(--secondary-gradient) !important;">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="card-title text-uppercase mb-2 text-white-50 small fw-bold">Actions Center</h5>
                            <h4 class="fw-bold mt-2">Manage Classes</h4>
                        </div>
                        <div class="stat-icon text-white-50 fs-2">
                            <i class="fa-solid fa-book-open-reader"></i>
                        </div>
                    </div>
                    <div class="mt-auto small">
                        <a href="students.php" class="text-white text-decoration-underline"><i class="fa-solid fa-search me-1"></i> Search registry roster</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Roster Overview -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-12 p-3">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center px-2">
                    <h3 class="h5 fw-bold mb-0">Departmental Student Roster (Preview)</h3>
                    <a href="students.php" class="btn btn-link text-decoration-none text-primary fw-semibold p-0 small">
                        Full Roster Directory <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body px-0 pb-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Student ID</th>
                                    <th>Full Name</th>
                                    <th>Email Address</th>
                                    <th>Phone</th>
                                    <th>Admission Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($students_result && mysqli_num_rows($students_result) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($students_result)): ?>
                                        <tr>
                                            <td class="ps-3"><span class="badge bg-light text-dark border fw-medium"><?php echo htmlspecialchars($row['student_id']); ?></span></td>
                                            <td class="fw-semibold text-slate"><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                                            <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($row['admission_date'])); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            <i class="fa-regular fa-folder-open fa-2x mb-2 d-block text-secondary opacity-50"></i>
                                            No student records found in your department.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
mysqli_close($conn);

include 'includes/footer.php'; 
?>
