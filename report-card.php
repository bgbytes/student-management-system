<?php
// Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security: Allow logged-in users only
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once "config/db.php";

$student_id = "";

// Role Authorization check
if ($_SESSION['user_role'] === 'student') {
    // Students can only see their own report card
    $student_id = $_SESSION['username']; // Student username is their student_id
} else {
    // Admins and Faculty can search and request student report cards via GET
    $student_id = isset($_GET['student_id']) ? trim($_GET['student_id']) : "";
}

$student = null;
$grades = [];
$error_msg = "";

if (!empty($student_id)) {
    // 1. Fetch Student Profile and Course details
    $sql_stud = "SELECT s.*, c.course_name, c.base_fee_inr 
                 FROM students s 
                 JOIN courses c ON s.course_id = c.id 
                 WHERE s.student_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql_stud)) {
        mysqli_stmt_bind_param($stmt, "s", $student_id);
        if (mysqli_stmt_execute($stmt)) {
            $res = mysqli_stmt_get_result($stmt);
            $student = mysqli_fetch_assoc($res);
        }
        mysqli_stmt_close($stmt);
    }
    
    if (!$student) {
        $error_msg = "Student record not found in system directory.";
    } else {
        // 2. Fetch Marks for this Student
        $sql_grades = "SELECT m.*, c.course_name 
                       FROM marks m 
                       JOIN courses c ON m.course_id = c.id 
                       WHERE m.student_id = ? 
                       ORDER BY m.term ASC, c.course_name ASC";
        if ($stmt_g = mysqli_prepare($conn, $sql_grades)) {
            mysqli_stmt_bind_param($stmt_g, "s", $student_id);
            if (mysqli_stmt_execute($stmt_g)) {
                $res_g = mysqli_stmt_get_result($stmt_g);
                while ($grow = mysqli_fetch_assoc($res_g)) {
                    $grades[] = $grow;
                }
            }
            mysqli_stmt_close($stmt_g);
        }
    }
} else {
    $error_msg = "Please specify a valid Student ID.";
}

// Helper to determine letter grade
function get_letter_grade($score) {
    if ($score >= 90) return 'A';
    if ($score >= 80) return 'B';
    if ($score >= 70) return 'C';
    if ($score >= 60) return 'D';
    return 'F';
}

// Calculate metrics
$total_marks = 0;
$total_gps = 0;
$graded_count = count($grades);
$cgpa = 0.00;
$average_score = 0.00;

if ($graded_count > 0) {
    foreach ($grades as $g) {
        $total_marks += $g['score'];
        $total_gps += $g['grade_point'];
    }
    $average_score = round($total_marks / $graded_count, 2);
    $cgpa = round($total_gps / $graded_count, 2);
}

// Determine CGPA honours
$honours = "Pass";
$badge_class = "bg-secondary";
if ($cgpa >= 3.5) {
    $honours = "First Class Honours";
    $badge_class = "bg-success";
} elseif ($cgpa >= 3.0) {
    $honours = "Second Class Upper";
    $badge_class = "bg-primary";
} elseif ($cgpa >= 2.0) {
    $honours = "Second Class Lower";
    $badge_class = "bg-info";
} elseif ($cgpa < 1.0 && $graded_count > 0) {
    $honours = "Academic Probation / Fail";
    $badge_class = "bg-danger";
}

$page_title = "Academic Report Card";
$page_description = "Printable academic evaluation summary containing grades, term totals, and CGPA metrics.";
include 'includes/header.php';
?>

<style>
/* CSS Hooks for Print-Ready Report Card */
@media print {
    .navbar, footer, .btn, .no-print {
        display: none !important;
    }
    body {
        background-color: #ffffff !important;
        color: #000000 !important;
        margin: 0;
        padding: 0;
        font-size: 12pt;
    }
    main {
        padding: 0 !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
        padding: 0 !important;
    }
    .print-card-header {
        border-bottom: 3px double #333 !important;
        margin-bottom: 20px !important;
    }
    .table-custom-container {
        border: 1px solid #ccc !important;
        box-shadow: none !important;
    }
    .table th {
        background-color: #f0f0f0 !important;
        color: #000 !important;
        border-bottom: 2px solid #333 !important;
    }
    .table td {
        border-bottom: 1px solid #ddd !important;
    }
    .badge {
        color: #000 !important;
        background: transparent !important;
        border: 1px solid #000 !important;
        padding: 2px 4px !important;
    }
}
</style>

<div class="container mb-5">
    <!-- Utility Buttons (Hide on Print) -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <a href="javascript:history.back();" class="text-decoration-none text-muted small">
                <i class="fa-solid fa-arrow-left me-1"></i> Back
            </a>
        </div>
        <div>
            <button onclick="window.print();" class="btn btn-primary-custom rounded-pill">
                <i class="fa-solid fa-print me-1"></i> Print Report Card
            </button>
        </div>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger no-print" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>
            <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <?php if ($student): ?>
        <div class="card border-0 shadow-sm p-4 rounded-16 bg-white">
            <!-- Institution & Student Header -->
            <div class="row align-items-center print-card-header pb-4 mb-4 border-bottom">
                <div class="col-sm-8">
                    <div class="d-flex align-items-center mb-2">
                        <i class="fa-solid fa-graduation-cap text-primary fa-2x me-3"></i>
                        <div>
                            <h2 class="fw-bold mb-0">EduManage University College</h2>
                            <p class="text-muted small mb-0">Office of the Register • Student Grading Division</p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4 text-sm-end mt-3 mt-sm-0">
                    <h5 class="text-secondary fw-semibold mb-1">Official Transcript</h5>
                    <p class="small text-muted mb-0">Issued: <?php echo date('F d, Y'); ?></p>
                </div>
            </div>

            <!-- Student Profile Metadata -->
            <div class="row g-3 mb-4 bg-light p-3 rounded-8 mx-0">
                <div class="col-md-3 col-sm-6">
                    <span class="text-secondary small d-block">Student Name</span>
                    <strong class="text-slate"><?php echo htmlspecialchars($student['name']); ?></strong>
                </div>
                <div class="col-md-3 col-sm-6">
                    <span class="text-secondary small d-block">Student Registry ID</span>
                    <strong class="text-slate"><?php echo htmlspecialchars($student['student_id']); ?></strong>
                </div>
                <div class="col-md-3 col-sm-6">
                    <span class="text-secondary small d-block">Enrollment Course</span>
                    <strong class="text-slate"><?php echo htmlspecialchars($student['course_name']); ?></strong>
                </div>
                <div class="col-md-3 col-sm-6">
                    <span class="text-secondary small d-block">Admission Date</span>
                    <strong class="text-slate"><?php echo date('M d, Y', strtotime($student['admission_date'])); ?></strong>
                </div>
            </div>

            <!-- Grades Matrix Table -->
            <h4 class="fw-bold mb-3 h5">Academic Grades Breakdown</h4>
            <div class="table-custom-container mb-4">
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Subject / Course Track</th>
                                <th>Academic Term</th>
                                <th class="text-center">Exam Score</th>
                                <th class="text-center">Letter Grade</th>
                                <th class="text-end pe-3">Grade Points (GPA)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($graded_count > 0): ?>
                                <?php foreach ($grades as $g): ?>
                                    <tr>
                                        <td class="ps-3 fw-semibold"><?php echo htmlspecialchars($g['course_name']); ?></td>
                                        <td><?php echo htmlspecialchars($g['term']); ?></td>
                                        <td class="text-center"><?php echo number_format($g['score'], 2); ?>%</td>
                                        <td class="text-center"><span class="badge bg-light text-dark border font-monospace"><?php echo get_letter_grade($g['score']); ?></span></td>
                                        <td class="text-end pe-3 fw-bold"><?php echo number_format($g['grade_point'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        No grades registered in the system database for this student.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Cumulative GPA (CGPA) Aggregate Card -->
            <div class="row justify-content-end">
                <div class="col-md-5">
                    <div class="card bg-slate bg-opacity-10 border-0 p-3 rounded-12 shadow-sm" style="background-color: #f8fafc; border: 1px solid #e2e8f0 !important;">
                        <h4 class="h6 fw-bold text-secondary mb-3 text-uppercase tracking-wider">Evaluation Roster Summary</h4>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total Courses Graded:</span>
                            <span class="fw-semibold"><?php echo $graded_count; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Roster Average Score:</span>
                            <span class="fw-semibold"><?php echo $average_score; ?>%</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Allocated Course Fees:</span>
                            <span class="fw-semibold text-slate"><?php echo format_inr($student['base_fee_inr']); ?></span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-slate">Cumulative CGPA:</span>
                            <div class="text-end">
                                <span class="display-6 fw-bold text-primary block"><?php echo number_format($cgpa, 2); ?></span>
                                <span class="badge <?php echo $badge_class; ?> d-block mt-1"><?php echo $honours; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Signatures Section (For print-ready layout) -->
            <div class="row mt-5 pt-5 d-none d-print-flex justify-content-between">
                <div class="col-4 text-center">
                    <div style="border-top: 1px solid #000; padding-top: 5px; margin-top: 50px;">
                        <strong>Registrar's Signature</strong>
                        <p class="small text-muted">EduManage University College</p>
                    </div>
                </div>
                <div class="col-4 text-center">
                    <div style="border-top: 1px solid #000; padding-top: 5px; margin-top: 50px;">
                        <strong>Date of Issue</strong>
                        <p class="small text-muted"><?php echo date('Y-m-d'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php 
mysqli_close($conn);
include 'includes/footer.php'; 
?>
