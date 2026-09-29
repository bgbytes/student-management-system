<?php
$page_title = "Welcome to EduManage";
$page_description = "Welcome to the EduManage Student Management System. A modern administrative tool for tracking records, reporting, and management.";
include 'includes/header.php';
?>

<!-- Hero Section -->
<div class="hero-section text-center mb-5">
    <div class="container py-3">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h1 class="display-4 fw-bold text-white mb-3 animate__animated animate__fadeInDown">
                    Empowering School Operations
                </h1>
                <p class="lead text-light-50 mb-4 fs-5 opacity-75">
                    Simplify admin workflows, organize student profiles, and generate reports instantly using our modern dashboard interface.
                </p>
                <div class="d-flex justify-content-center gap-3">
                    <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
                        <a href="dashboard.php" class="btn btn-primary-custom rounded-pill btn-lg px-4">
                            <i class="fa-solid fa-chart-line me-2"></i> Go to Dashboard
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary-custom rounded-pill btn-lg px-4">
                            <i class="fa-solid fa-lock me-2"></i> Admin Login Portal
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Features Section -->
<div class="container mb-5">
    <div class="row text-center mb-4">
        <div class="col-12">
            <span class="text-uppercase text-primary fw-bold tracking-wider" style="font-size: 0.85rem; letter-spacing: 1.5px;">Core Modules</span>
            <h2 class="h1 fw-bold mt-2">Engineered for Efficiency</h2>
            <p class="text-muted">A look at the functional elements included within our platform.</p>
        </div>
    </div>
    
    <div class="row g-4">
        <!-- Feature 1: Secure Login -->
        <div class="col-md-4">
            <div class="card h-100 glass-card p-4 border-0 text-center">
                <div class="card-body">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-4" style="width: 70px; height: 70px;">
                        <i class="fa-solid fa-shield-halved fa-2x"></i>
                    </div>
                    <h3 class="card-title h4 fw-bold mb-3">Secure Access</h3>
                    <p class="card-text text-muted">
                        Administrative access protected by modern password hashing algorithms, authenticated session management, and URL guards on all operational files.
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Feature 2: Student Records -->
        <div class="col-md-4">
            <div class="card h-100 glass-card p-4 border-0 text-center">
                <div class="card-body">
                    <div class="d-inline-flex align-items-center justify-content-center bg-info bg-opacity-10 text-info rounded-circle mb-4" style="width: 70px; height: 70px;">
                        <i class="fa-solid fa-address-book fa-2x"></i>
                    </div>
                    <h3 class="card-title h4 fw-bold mb-3">Student Profiles</h3>
                    <p class="card-text text-muted">
                        Add, retrieve, edit, and delete student data seamlessly. Query parameters are handled using prepared statements to avoid malicious inputs.
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Feature 3: Analytics -->
        <div class="col-md-4">
            <div class="card h-100 glass-card p-4 border-0 text-center">
                <div class="card-body">
                    <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle mb-4" style="width: 70px; height: 70px;">
                        <i class="fa-solid fa-gauge-high fa-2x"></i>
                    </div>
                    <h3 class="card-title h4 fw-bold mb-3">Interactive Overview</h3>
                    <p class="card-text text-muted">
                        Visual dashboards summarizing enrolment records, real-time statistics, search queries, filters, and immediate activity summaries.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CTA Section -->
<div class="bg-light py-5 border-top border-bottom">
    <div class="container text-center py-3">
        <h2 class="fw-bold mb-3">Ready to Manage Student Records?</h2>
        <p class="lead text-muted mb-4">Log in securely as an administrator to access the database directory.</p>
        <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
            <a href="dashboard.php" class="btn btn-secondary-custom rounded-pill px-4 btn-lg">
                <i class="fa-solid fa-chart-line me-2"></i> Administrative Area
            </a>
        <?php else: ?>
            <a href="login.php" class="btn btn-secondary-custom rounded-pill px-4 btn-lg">
                <i class="fa-solid fa-sign-in-alt me-2"></i> Enter Login Portal
            </a>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
