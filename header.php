<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Global CSRF Token Initialization
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Global Currency Helper Inclusion
require_once __DIR__ . "/../config/currency_helper.php";

// Define fallback page titles and descriptions
$title = isset($page_title) ? $page_title . " - Student Management System" : "Student Management System";
$description = isset($page_description) ? $page_description : "A comprehensive, full-stack Student Management System for administrative and student tracking purposes.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($description); ?>">
    
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <!-- Font Awesome CDN for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Chart.js CDN for interactive charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<!-- Responsive Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <i class="fa-solid fa-graduation-cap me-2"></i>
            <span>EduManage</span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <?php if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true): ?>
                    
                    <!-- ROLE: ADMIN NAVIGATION -->
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                                <i class="fa-solid fa-chart-line me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'students.php') ? 'active' : ''; ?>" href="students.php">
                                <i class="fa-solid fa-users me-1"></i> Students
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'faculty.php') ? 'active' : ''; ?>" href="faculty.php">
                                <i class="fa-solid fa-chalkboard-user me-1"></i> Faculty
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'financials.php') ? 'active' : ''; ?>" href="financials.php">
                                <i class="fa-solid fa-wallet me-1"></i> Financials
                            </a>
                        </li>
                        <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                            <span class="text-light me-3 d-block d-lg-inline mb-2 mb-lg-0">
                                <i class="fa-solid fa-circle-user me-1 text-info"></i> Admin: <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </span>
                            <a class="btn btn-outline-danger btn-sm rounded-pill" href="logout.php">
                                <i class="fa-solid fa-sign-out-alt me-1"></i> Logout
                            </a>
                        </li>

                    <!-- ROLE: FACULTY NAVIGATION -->
                    <?php elseif ($_SESSION['user_role'] === 'faculty'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'faculty_dashboard.php') ? 'active' : ''; ?>" href="faculty_dashboard.php">
                                <i class="fa-solid fa-chart-line me-1"></i> Faculty Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'students.php') ? 'active' : ''; ?>" href="students.php">
                                <i class="fa-solid fa-users me-1"></i> Students Directory
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'attendance.php') ? 'active' : ''; ?>" href="attendance.php">
                                <i class="fa-solid fa-clipboard-user me-1"></i> Attendance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'grades.php') ? 'active' : ''; ?>" href="grades.php">
                                <i class="fa-solid fa-award me-1"></i> Grades
                            </a>
                        </li>
                        <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                            <span class="text-light me-3 d-block d-lg-inline mb-2 mb-lg-0">
                                <i class="fa-solid fa-circle-user me-1 text-success"></i> Faculty: <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </span>
                            <a class="btn btn-outline-danger btn-sm rounded-pill" href="logout.php">
                                <i class="fa-solid fa-sign-out-alt me-1"></i> Logout
                            </a>
                        </li>

                    <!-- ROLE: STUDENT NAVIGATION -->
                    <?php elseif ($_SESSION['user_role'] === 'student'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'student_dashboard.php') ? 'active' : ''; ?>" href="student_dashboard.php">
                                <i class="fa-solid fa-user me-1"></i> Student Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'report-card.php') ? 'active' : ''; ?>" href="report-card.php">
                                <i class="fa-solid fa-file-invoice me-1"></i> Report Card
                            </a>
                        </li>
                        <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                            <span class="text-light me-3 d-block d-lg-inline mb-2 mb-lg-0">
                                <i class="fa-solid fa-circle-user me-1 text-warning"></i> Student: <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </span>
                            <a class="btn btn-outline-danger btn-sm rounded-pill" href="logout.php">
                                <i class="fa-solid fa-sign-out-alt me-1"></i> Logout
                            </a>
                        </li>
                    <?php endif; ?>

                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" href="index.php">
                            <i class="fa-solid fa-house me-1"></i> Home
                        </a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-primary-custom rounded-pill" href="login.php">
                            <i class="fa-solid fa-lock me-1"></i> Portal Login
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="py-4">
