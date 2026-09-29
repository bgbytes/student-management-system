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

// Initialize counts
$total_students = 0;
$recent_students = 0;
$unique_courses = 0;

// Query 1: Total Students Count
$sql_total = "SELECT COUNT(*) as total FROM students";
if ($result = mysqli_query($conn, $sql_total)) {
    $row = mysqli_fetch_assoc($result);
    $total_students = $row['total'];
}

// Query 2: Recent Students Count (Admitted in last 30 days)
$sql_recent = "SELECT COUNT(*) as total_recent FROM students WHERE admission_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
if ($result_recent = mysqli_query($conn, $sql_recent)) {
    $row_recent = mysqli_fetch_assoc($result_recent);
    $recent_students = $row_recent['total_recent'];
}

// Query 3: Unique Courses Count
$sql_courses = "SELECT COUNT(*) as total_courses FROM courses";
if ($result_courses = mysqli_query($conn, $sql_courses)) {
    $row_courses = mysqli_fetch_assoc($result_courses);
    $unique_courses = $row_courses['total_courses'];
}

// Query 4: Retrieve latest 5 student admissions
$sql_latest = "SELECT s.*, c.course_name FROM students s JOIN courses c ON s.course_id = c.id ORDER BY s.created_at DESC, s.id DESC LIMIT 5";
$latest_result = mysqli_query($conn, $sql_latest);

// Query 5: Course enrollment distributions for Chart 1
$courses_dist = [];
$courses_counts = [];
$sql_dist1 = "SELECT c.course_name as course, COUNT(s.id) as count FROM courses c LEFT JOIN students s ON s.course_id = c.id GROUP BY c.id ORDER BY count DESC";
if ($res_dist1 = mysqli_query($conn, $sql_dist1)) {
    while ($row = mysqli_fetch_assoc($res_dist1)) {
        $courses_dist[] = $row['course'];
        $courses_counts[] = intval($row['count']);
    }
}

// Query 6: Invoice statuses count for Chart 2 (from fee_ledger table)
$status_labels = [];
$status_counts = [];
$sql_dist2 = "SELECT status, COUNT(*) as count FROM fee_ledger GROUP BY status";
if ($res_dist2 = mysqli_query($conn, $sql_dist2)) {
    while ($row = mysqli_fetch_assoc($res_dist2)) {
        $status_labels[] = $row['status'];
        $status_counts[] = intval($row['count']);
    }
}

$page_title = "Admin Dashboard";
$page_description = "Overview of school statistics, student logs, and administration panels.";
include 'includes/header.php';
?>

<div class="container mb-5">
    <!-- Welcome Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h1 class="fw-bold mb-1">Dashboard</h1>
            <p class="text-muted mb-0">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong>! Here is today's overview.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="export.php?type=students" class="btn btn-outline-secondary rounded-pill">
                <i class="fa-solid fa-file-csv me-1"></i> Export Students
            </a>
            <a href="add-student.php" class="btn btn-primary-custom rounded-pill">
                <i class="fa-solid fa-plus me-1"></i> Register Student
            </a>
        </div>
    </div>

    <!-- Quick Stats Cards Row -->
    <div class="row g-4 mb-5">
        <!-- Stat Card: Total Students -->
        <div class="col-md-4">
            <div class="card stat-card text-white border-0 bg-primary" style="background: var(--primary-gradient) !important;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-uppercase mb-2 text-white-50 small fw-bold">Total Students</h5>
                            <span class="display-5 fw-bold"><?php echo $total_students; ?></span>
                        </div>
                        <div class="stat-icon text-white-50">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                    <div class="mt-3 text-white-50 small">
                        <i class="fa-solid fa-circle-info me-1"></i> Active student profiles in registry
                    </div>
                </div>
            </div>
        </div>

        <!-- Stat Card: Recent Enrolments -->
        <div class="col-md-4">
            <div class="card stat-card text-white border-0 bg-success" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-uppercase mb-2 text-white-50 small fw-bold">New Admissions</h5>
                            <span class="display-5 fw-bold"><?php echo $recent_students; ?></span>
                        </div>
                        <div class="stat-icon text-white-50">
                            <i class="fa-solid fa-user-plus"></i>
                        </div>
                    </div>
                    <div class="mt-3 text-white-50 small">
                        <i class="fa-solid fa-calendar-day me-1"></i> Registered in the last 30 days
                    </div>
                </div>
            </div>
        </div>

        <!-- Stat Card: Total Courses -->
        <div class="col-md-4">
            <div class="card stat-card text-white border-0 bg-secondary" style="background: var(--secondary-gradient) !important;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-uppercase mb-2 text-white-50 small fw-bold">Active Courses</h5>
                            <span class="display-5 fw-bold"><?php echo $unique_courses; ?></span>
                        </div>
                        <div class="stat-icon text-white-50">
                            <i class="fa-solid fa-graduation-cap"></i>
                        </div>
                    </div>
                    <div class="mt-3 text-white-50 small">
                        <i class="fa-solid fa-book-bookmark me-1"></i> Unique study courses offered
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charting & Visual Distribution Loops -->
    <div class="row g-4 mb-5">
        <!-- Enrollment Density Doughnut Chart -->
        <div class="col-lg-7 col-md-6">
            <div class="card border-0 shadow-sm rounded-12 p-3 bg-white h-100">
                <h3 class="h5 fw-bold mb-3 px-2">Course Enrollment Distribution</h3>
                <div class="card-body d-flex justify-content-center align-items-center" style="position: relative; height: 300px;">
                    <canvas id="enrollmentChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Invoice Status Pie Chart -->
        <div class="col-lg-5 col-md-6">
            <div class="card border-0 shadow-sm rounded-12 p-3 bg-white h-100">
                <h3 class="h5 fw-bold mb-3 px-2">Invoice Settlement Statuses</h3>
                <div class="card-body d-flex justify-content-center align-items-center" style="position: relative; height: 300px;">
                    <canvas id="billingChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Admissions Table -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-12 p-3">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center px-2">
                    <h3 class="h5 fw-bold mb-0">Recently Admitted Students Roster</h3>
                    <a href="students.php" class="btn btn-link text-decoration-none text-primary fw-semibold p-0 small">
                        View Directory <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body px-0 pb-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Student ID</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Course</th>
                                    <th>Admission Date</th>
                                    <th class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($latest_result && mysqli_num_rows($latest_result) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($latest_result)): ?>
                                        <tr>
                                            <td class="ps-3"><span class="badge bg-light text-dark border fw-medium"><?php echo htmlspecialchars($row['student_id']); ?></span></td>
                                            <td class="fw-semibold text-slate"><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                                            <td><span class="badge bg-indigo-subtle text-indigo px-2 py-1" style="color: #4f46e5; background-color: #e0e7ff;"><?php echo htmlspecialchars($row['course_name']); ?></span></td>
                                            <td><?php echo date('M d, Y', strtotime($row['admission_date'])); ?></td>
                                            <td class="text-end pe-3">
                                                <div class="btn-group" role="group">
                                                    <a href="edit-student.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit Student">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </a>
                                                    <a href="delete-student.php?id=<?php echo $row['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete student: <?php echo htmlspecialchars($row['name']); ?>?');" title="Delete Student">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="fa-regular fa-folder-open fa-2x mb-2 d-block text-secondary opacity-50"></i>
                                            No student records found. Click "Register Student" to add records.
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

<!-- Chart.js Scripts -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Enrollment Distribution Chart
    const ctxEnrollment = document.getElementById('enrollmentChart').getContext('2d');
    const coursesDist = <?php echo json_encode($courses_dist); ?>;
    const coursesCounts = <?php echo json_encode($courses_counts); ?>;
    
    new Chart(ctxEnrollment, {
        type: 'bar',
        data: {
            labels: coursesDist,
            datasets: [{
                label: 'Students Enrolled',
                data: coursesCounts,
                backgroundColor: 'rgba(99, 102, 241, 0.7)',
                borderColor: 'rgba(99, 102, 241, 1)',
                borderWidth: 1.5,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });

    // 2. Billing Status Chart
    const ctxBilling = document.getElementById('billingChart').getContext('2d');
    const statusLabels = <?php echo json_encode($status_labels); ?>;
    const statusCounts = <?php echo json_encode($status_counts); ?>;
    
    // Status colors
    const colorMap = {
        'Paid': '#10b981',
        'Partially Paid': '#3b82f6',
        'Pending': '#f59e0b'
    };
    const bgColors = statusLabels.map(lbl => colorMap[lbl] || '#64748b');

    new Chart(ctxBilling, {
        type: 'pie',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusCounts,
                backgroundColor: bgColors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 15 }
                }
            }
        }
    });
});
</script>

<?php 
// Close connection
mysqli_close($conn);

include 'includes/footer.php'; 
?>
