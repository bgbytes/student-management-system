<?php
// Session check at the very top of protected admin pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Include database configuration
require_once "config/db.php";

$search = "";
$sql = "SELECT s.*, c.course_name 
        FROM students s 
        LEFT JOIN courses c ON s.course_id = c.id 
        ORDER BY s.id DESC";

// Initial load queries (standard synchronous query for fallback/initial loading)
if ($stmt = mysqli_prepare($conn, $sql)) {
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
    }
}

$page_title = "Students Directory";
$page_description = "Manage, search, edit, and delete student registry entries.";
include 'includes/header.php';
?>

<div class="container mb-5">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h1 class="fw-bold mb-1">Student Directory</h1>
            <p class="text-muted mb-0">Browse, search, and manage student enrollment records.</p>
        </div>
        <?php if ($_SESSION['user_role'] === 'admin'): ?>
            <div class="mt-3 mt-md-0">
                <a href="add-student.php" class="btn btn-primary-custom rounded-pill">
                    <i class="fa-solid fa-plus me-1"></i> Add New Student
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Alert Messages (Flash Sessions) -->
    <?php 
    if (isset($_SESSION['message'])): 
        $msg_type = isset($_SESSION['msg_type']) ? $_SESSION['msg_type'] : 'info';
        $icon = 'fa-circle-info';
        if ($msg_type === 'success') $icon = 'fa-circle-check';
        if ($msg_type === 'danger') $icon = 'fa-triangle-exclamation';
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

    <!-- Search Card (Refactored for Fetch AJAX) -->
    <div class="card glass-card border-0 p-3 mb-4">
        <form id="searchForm" onsubmit="return false;">
            <div class="row g-2 align-items-center">
                <div class="col-md-9 col-lg-10">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" id="searchInput" class="form-control border-start-0 ps-0" placeholder="Search dynamically by name or Student ID..." value="">
                    </div>
                </div>
                <div class="col-md-3 col-lg-2 d-grid gap-2 d-flex">
                    <button type="button" id="clearBtn" class="btn btn-light border w-100 py-2" title="Reset Search">
                        <i class="fa-solid fa-rotate-left me-1"></i> Reset
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Students Directory Table -->
    <div class="table-custom-container">
        <div class="table-responsive">
            <table class="table table-custom mb-0" id="studentsTable">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Email Address</th>
                        <th>Phone Number</th>
                        <th>Selected Course</th>
                        <th>Admission Date</th>
                        <th class="text-center" style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="studentsTableBody">
                    <!-- Initial data rendered server-side for SEO & robustness -->
                    <?php if (isset($result) && mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border fw-medium py-1 px-2">
                                        <?php echo htmlspecialchars($row['student_id']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-slate"><?php echo htmlspecialchars($row['name']); ?></div>
                                </td>
                                <td>
                                    <a href="mailto:<?php echo htmlspecialchars($row['email']); ?>" class="text-decoration-none text-secondary small">
                                        <i class="fa-regular fa-envelope me-1 text-muted"></i><?php echo htmlspecialchars($row['email']); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="small text-secondary">
                                        <i class="fa-solid fa-phone me-1 text-muted"></i><?php echo htmlspecialchars($row['phone']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge px-2 py-1" style="color: #4f46e5; background-color: #e0e7ff;">
                                        <?php echo htmlspecialchars($row['course_name'] ?? 'Not Assigned'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="small text-secondary">
                                        <i class="fa-regular fa-calendar me-1 text-muted"></i><?php echo date('M d, Y', strtotime($row['admission_date'])); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                            <a href="edit-student.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit Profile">
                                                <i class="fa-solid fa-user-gear"></i>
                                            </a>
                                            <a href="delete-student.php?id=<?php echo $row['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete student: <?php echo htmlspecialchars($row['name']); ?>?');" title="Delete Record">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </a>
                                        <?php elseif ($_SESSION['user_role'] === 'faculty'): ?>
                                            <a href="report-card.php?student_id=<?php echo urlencode($row['student_id']); ?>" class="btn btn-sm btn-outline-info" title="View Grades Report">
                                                <i class="fa-solid fa-file-invoice"></i> Grades
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-regular fa-folder-open fa-3x mb-3 d-block text-secondary opacity-50"></i>
                                <span class="d-block mb-1 fw-semibold">No records match your query.</span>
                                <span class="small text-muted">Check your spelling or register a new record.</span>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- AJAX Fetch Implementation -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById("searchInput");
    const clearBtn = document.getElementById("clearBtn");
    const tableBody = document.getElementById("studentsTableBody");
    
    // Inject user credentials dynamically into JS context safely
    const userRole = '<?php echo $_SESSION['user_role']; ?>';
    const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
    
    let debounceTimer;

    // Debounced search input listener
    searchInput.addEventListener("input", function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            performSearch(this.value);
        }, 250); // 250ms debounce
    });

    clearBtn.addEventListener("click", function() {
        searchInput.value = "";
        performSearch("");
    });

    function performSearch(query) {
        // Show lightweight loading state
        tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-circle-notch fa-spin me-2"></i>Searching registry roster...</td></tr>`;

        fetch(`api/search_students.php?search=${encodeURIComponent(query)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error("HTTP error " + response.status);
                }
                return response.json();
            })
            .then(data => {
                renderTable(data);
            })
            .catch(error => {
                console.error("AJAX Search Error:", error);
                tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Failed to fetch registry data. Please try again.</td></tr>`;
            });
    }

    function renderTable(students) {
        if (students.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="fa-regular fa-folder-open fa-3x mb-3 d-block text-secondary opacity-50"></i>
                        <span class="d-block mb-1 fw-semibold">No records match your query.</span>
                        <span class="small text-muted">Check your spelling or reset the filter.</span>
                    </td>
                </tr>`;
            return;
        }

        let html = "";
        students.forEach(student => {
            // Format Date (JS equivalents of PHP date formats)
            const dateObj = new Date(student.admission_date);
            const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
            
            // Build action buttons contextually
            let actionHtml = "";
            if (userRole === 'admin') {
                actionHtml = `
                    <div class="btn-group" role="group">
                        <a href="edit-student.php?id=${student.id}" class="btn btn-sm btn-outline-primary" title="Edit Profile">
                            <i class="fa-solid fa-user-gear"></i>
                        </a>
                        <a href="delete-student.php?id=${student.id}&csrf_token=${csrfToken}" class="btn btn-sm btn-outline-danger" 
                           onclick="return confirm('Are you sure you want to delete student: ${escapeHtml(student.name)}?');" title="Delete Record">
                            <i class="fa-solid fa-trash-can"></i>
                        </a>
                    </div>`;
            } else if (userRole === 'faculty') {
                actionHtml = `
                    <div class="btn-group" role="group">
                        <a href="report-card.php?student_id=${encodeURIComponent(student.student_id)}" class="btn btn-sm btn-outline-info" title="View Grades Report">
                            <i class="fa-solid fa-file-invoice"></i> Grades
                        </a>
                    </div>`;
            }

            html += `
                <tr>
                    <td>
                        <span class="badge bg-light text-dark border fw-medium py-1 px-2">
                            ${escapeHtml(student.student_id)}
                        </span>
                    </td>
                    <td>
                        <div class="fw-semibold text-slate">${escapeHtml(student.name)}</div>
                    </td>
                    <td>
                        <a href="mailto:${escapeHtml(student.email)}" class="text-decoration-none text-secondary small">
                            <i class="fa-regular fa-envelope me-1 text-muted"></i>${escapeHtml(student.email)}
                        </a>
                    </td>
                    <td>
                        <span class="small text-secondary">
                            <i class="fa-solid fa-phone me-1 text-muted"></i>${escapeHtml(student.phone)}
                        </span>
                    </td>
                    <td>
                        <span class="badge px-2 py-1" style="color: #4f46e5; background-color: #e0e7ff;">
                            ${escapeHtml(student.course_name ?? 'Not Assigned')}
                        </span>
                    </td>
                    <td>
                        <span class="small text-secondary">
                            <i class="fa-regular fa-calendar me-1 text-muted"></i>${formattedDate}
                        </span>
                    </td>
                    <td class="text-center">
                        ${actionHtml}
                    </td>
                </tr>`;
        });

        tableBody.innerHTML = html;
    }

    // Helper function to escape HTML output in JS
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
</script>

<?php
// Close open statement & connection
if (isset($stmt)) {
    mysqli_stmt_close($stmt);
}
mysqli_close($conn);

include 'includes/footer.php';
?>
