<?php
// Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once "config/db.php";

$student_ref_id = $_SESSION['ref_id'];
$student_id = $_SESSION['username'];

$student = null;
$error_msg = "";

// 1. Fetch Student Profile & Enrolled Course name
$sql_profile = "SELECT s.*, c.course_name FROM students s JOIN courses c ON s.course_id = c.id WHERE s.id = ?";
if ($stmt = mysqli_prepare($conn, $sql_profile)) {
    mysqli_stmt_bind_param($stmt, "i", $student_ref_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $student = mysqli_fetch_assoc($result);
    }
    mysqli_stmt_close($stmt);
}

if (!$student) {
    die("Error: Student profile details not found.");
}

// 2. Fetch CGPA from normalized 'marks' table
$cgpa = 0.00;
$average_score = 0.00;
$graded_courses = 0;
$sql_grades = "SELECT score, grade_point FROM marks WHERE student_id = ?";
if ($stmt = mysqli_prepare($conn, $sql_grades)) {
    mysqli_stmt_bind_param($stmt, "s", $student_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $total_score = 0;
        $total_gp = 0;
        $graded_courses = mysqli_num_rows($result);
        while ($row = mysqli_fetch_assoc($result)) {
            $total_score += $row['score'];
            $total_gp += $row['grade_point'];
        }
        if ($graded_courses > 0) {
            $average_score = round($total_score / $graded_courses, 2);
            $cgpa = round($total_gp / $graded_courses, 2);
        }
    }
    mysqli_stmt_close($stmt);
}

// 3. Fetch Attendance Metrics from normalized 'attendance' table
$attendance_percent = 100.00;
$total_sessions = 0;
$present_sessions = 0;
$leave_sessions = 0;

// Fetch present days count
$sql_pres = "SELECT COUNT(*) as total FROM attendance WHERE student_id = ? AND status = 'Present'";
if ($stmt = mysqli_prepare($conn, $sql_pres)) {
    mysqli_stmt_bind_param($stmt, "s", $student_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $present_sessions = $row['total'];
    }
    mysqli_stmt_close($stmt);
}

// Fetch leave days count
$sql_leave = "SELECT COUNT(*) as total FROM attendance WHERE student_id = ? AND status = 'Leave'";
if ($stmt = mysqli_prepare($conn, $sql_leave)) {
    mysqli_stmt_bind_param($stmt, "s", $student_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $leave_sessions = $row['total'];
    }
    mysqli_stmt_close($stmt);
}

// Fetch total days count logged for this course
$sql_tot = "SELECT COUNT(*) as total FROM attendance WHERE course_id = ?";
if ($stmt = mysqli_prepare($conn, $sql_tot)) {
    mysqli_stmt_bind_param($stmt, "i", $student['course_id']);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $total_sessions = $row['total'];
    }
    mysqli_stmt_close($stmt);
}

if ($total_sessions > 0) {
    // Formula: present + leave are considered not absent, or just present percentage. 
    // Let's compute presence rate: (present / total) * 100
    $attendance_percent = round(($present_sessions / $total_sessions) * 100, 2);
} else {
    $attendance_percent = 100.00;
}

// 4. Fetch Fee Ledger details (In INR ₹)
$invoices = [];
$sql_inv = "SELECT l.*, c.course_name 
            FROM fee_ledger l 
            JOIN courses c ON l.course_id = c.id 
            WHERE l.student_id = ? 
            ORDER BY l.id DESC";
if ($stmt = mysqli_prepare($conn, $sql_inv)) {
    mysqli_stmt_bind_param($stmt, "s", $student_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $invoices[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

$page_title = "Student Portal Dashboard";
$page_description = "Student homepage to view profile details, grades, attendance logs, and invoices.";
include 'includes/header.php';
?>

<div class="container mb-5">
    <!-- Welcome Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h1 class="fw-bold mb-1">Student Portal</h1>
            <p class="text-muted mb-0">Welcome back, <strong><?php echo htmlspecialchars($student['name']); ?></strong>!</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="report-card.php" class="btn btn-primary-custom rounded-pill">
                <i class="fa-solid fa-file-invoice me-1"></i> Print Official Report Card
            </a>
        </div>
    </div>

    <!-- Stats Cards Summary -->
    <div class="row g-4 mb-5">
        <!-- CGPA Card -->
        <div class="col-md-4">
            <div class="card stat-card text-white border-0 bg-primary" style="background: var(--primary-gradient) !important;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-uppercase mb-2 text-white-50 small fw-bold">Current CGPA</h5>
                            <span class="display-5 fw-bold"><?php echo number_format($cgpa, 2); ?></span>
                        </div>
                        <div class="stat-icon text-white-50">
                            <i class="fa-solid fa-graduation-cap"></i>
                        </div>
                    </div>
                    <div class="mt-3 text-white-50 small">
                        Based on <?php echo $graded_courses; ?> graded courses
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Card -->
        <div class="col-md-4">
            <div class="card stat-card text-white border-0 bg-success" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-uppercase mb-2 text-white-50 small fw-bold">Class Attendance</h5>
                            <span class="display-5 fw-bold"><?php echo $attendance_percent; ?>%</span>
                        </div>
                        <div class="stat-icon text-white-50">
                            <i class="fa-solid fa-clipboard-user"></i>
                        </div>
                    </div>
                    <div class="mt-3 text-white-50 small">
                        Present: <?php echo $present_sessions; ?> | Leave: <?php echo $leave_sessions; ?> (Total logs: <?php echo $total_sessions; ?>)
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Balance Status Card (In INR ₹) -->
        <div class="col-md-4">
            <?php 
            $total_due_inr = 0.00;
            $total_paid_inr = 0.00;
            $outstanding_inr = 0.00;
            foreach ($invoices as $inv) {
                $total_due_inr += $inv['amount_due_inr'];
                $total_paid_inr += $inv['amount_paid_inr'];
            }
            $outstanding_inr = $total_due_inr - $total_paid_inr;
            
            $fin_color = "bg-success";
            $fin_gradient = "linear-gradient(135deg, #10b981 0%, #059669 100%)";
            if ($outstanding_inr > 0) {
                $fin_color = "bg-warning";
                $fin_gradient = "linear-gradient(135deg, #f59e0b 0%, #d97706 100%)";
            }
            ?>
            <div class="card stat-card text-white border-0 <?php echo $fin_color; ?>" style="background: <?php echo $fin_gradient; ?> !important;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-uppercase mb-2 text-white-50 small fw-bold">Outstanding Dues</h5>
                            <span class="display-6 fw-bold"><?php echo format_inr($outstanding_inr); ?></span>
                        </div>
                        <div class="stat-icon text-white-50">
                            <i class="fa-solid fa-wallet"></i>
                        </div>
                    </div>
                    <div class="mt-3 text-white-50 small">
                        Paid: <?php echo format_inr($total_paid_inr); ?> of <?php echo format_inr($total_due_inr); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Side: Profile Details & Progress Bars -->
        <div class="col-lg-6">
            <!-- Profile Info Card -->
            <div class="card border-0 shadow-sm rounded-12 p-4 mb-4 bg-white">
                <h3 class="h5 fw-bold mb-3 border-bottom pb-2"><i class="fa-regular fa-id-card text-primary me-2"></i>Personal Profile</h3>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <span class="text-secondary small d-block">Full Name</span>
                        <strong class="text-slate"><?php echo htmlspecialchars($student['name']); ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary small d-block">Student ID</span>
                        <strong class="text-slate"><?php echo htmlspecialchars($student['student_id']); ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary small d-block">Email Address</span>
                        <strong class="text-slate"><?php echo htmlspecialchars($student['email']); ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary small d-block">Phone Number</span>
                        <strong class="text-slate"><?php echo htmlspecialchars($student['phone']); ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary small d-block">Enrolled Course</span>
                        <span class="badge bg-indigo-subtle text-indigo px-2 py-1 mt-1" style="color: #4f46e5; background-color: #e0e7ff;">
                            <?php echo htmlspecialchars($student['course_name']); ?>
                        </span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary small d-block">Admission Date</span>
                        <strong class="text-slate"><?php echo date('M d, Y', strtotime($student['admission_date'])); ?></strong>
                    </div>
                </div>
            </div>

            <!-- Attendance & GPA Progress visualizers -->
            <div class="card border-0 shadow-sm rounded-12 p-4 bg-white">
                <h3 class="h5 fw-bold mb-3 border-bottom pb-2"><i class="fa-solid fa-chart-line text-primary me-2"></i>Performance Indicator</h3>
                
                <!-- Attendance progress bar -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small text-secondary fw-semibold">Class Attendance Log Rate</span>
                        <span class="small fw-bold text-success"><?php echo $attendance_percent; ?>%</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $attendance_percent; ?>%;" aria-valuenow="<?php echo $attendance_percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <!-- GPA Progress (relative to 4.0 GPA) -->
                <div>
                    <?php 
                    $gpa_ratio = ($cgpa / 4.0) * 100;
                    ?>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small text-secondary fw-semibold">GPA Ratio (out of 4.00)</span>
                        <span class="small fw-bold text-primary"><?php echo number_format($cgpa, 2); ?> / 4.00</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $gpa_ratio; ?>%;" aria-valuenow="<?php echo $gpa_ratio; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Financials Ledger (INR ₹) -->
        <div class="col-lg-6" id="invoice">
            <div class="card border-0 shadow-sm rounded-12 p-4 bg-white h-100">
                <h3 class="h5 fw-bold mb-3 border-bottom pb-2"><i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>Financials Ledger (₹)</h3>
                
                <?php if (count($invoices) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($invoices as $inv): ?>
                            <?php 
                            $status_class = "bg-warning-subtle text-warning border-warning";
                            if ($inv['status'] === 'Paid') $status_class = "bg-success-subtle text-success border-success";
                            if ($inv['status'] === 'Partially Paid') $status_class = "bg-info-subtle text-info border-info";
                            ?>
                            <div class="list-group-item px-0 py-3 border-bottom">
                                <div class="d-flex w-100 justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0 fw-bold">Course Invoice: <?php echo htmlspecialchars($inv['course_name']); ?></h6>
                                    <span class="badge <?php echo $status_class; ?> border py-1 px-2"><?php echo htmlspecialchars($inv['status']); ?></span>
                                </div>
                                <div class="row g-2 text-secondary small">
                                    <div class="col-6">
                                        Amount Due: <strong class="text-slate"><?php echo format_inr($inv['amount_due_inr']); ?></strong>
                                    </div>
                                    <div class="col-6">
                                        Amount Paid: <strong class="text-slate"><?php echo format_inr($inv['amount_paid_inr']); ?></strong>
                                    </div>
                                    <div class="col-6">
                                        Due Date: <span class="text-slate"><i class="fa-regular fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($inv['due_date'])); ?></span>
                                    </div>
                                    <div class="col-6">
                                        Invoice Date: <span class="text-slate"><i class="fa-regular fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($inv['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-receipt fa-2x mb-2 d-block text-secondary opacity-50"></i>
                        No invoice ledger entries exist in database for your profile.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php 
mysqli_close($conn);
include 'includes/footer.php'; 
?>
