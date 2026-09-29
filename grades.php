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

$faculty_dept_id = 0;
if ($_SESSION['user_role'] === 'faculty') {
    // Retrieve department and map to course ID if department name matches course name
    $ref_id = $_SESSION['ref_id'];
    $stmt = mysqli_prepare($conn, "SELECT department FROM faculty WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $ref_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        $dept_name = $row['department'];
        // Lookup course ID
        $stmt_c = mysqli_prepare($conn, "SELECT id FROM courses WHERE course_name = ?");
        mysqli_stmt_bind_param($stmt_c, "s", $dept_name);
        mysqli_stmt_execute($stmt_c);
        $res_c = mysqli_stmt_get_result($stmt_c);
        if ($row_c = mysqli_fetch_assoc($res_c)) {
            $faculty_dept_id = $row_c['id'];
        }
        mysqli_stmt_close($stmt_c);
    }
    mysqli_stmt_close($stmt);
}

// Filters
$selected_course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : $faculty_dept_id;
$selected_term = isset($_GET['term']) ? trim($_GET['term']) : 'Term 1';

$form_success = $form_err = "";

// Helper function to calculate grade points
function get_grade_point($score) {
    if ($score >= 90) return 4.00;
    if ($score >= 80) return 3.00;
    if ($score >= 70) return 2.00;
    if ($score >= 60) return 1.00;
    return 0.00;
}

// Handle Saving Marks
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_grades'])) {
    // CSRF Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Alert: CSRF token verification failed.");
    }
    
    $post_course_id = intval($_POST['course_id']);
    $post_term = trim($_POST['term']);
    $scores = isset($_POST['scores']) ? $_POST['scores'] : []; // student_id => score
    
    if ($post_course_id <= 0 || empty($post_term)) {
        $form_err = "Course selection and term are required.";
    } else {
        mysqli_begin_transaction($conn);
        try {
            foreach ($scores as $sid => $score_val) {
                if ($score_val === "") continue; // skip unentered
                
                $score = floatval($score_val);
                if ($score < 0 || $score > 100) {
                    throw new Exception("Marks must be between 0 and 100. Student ID: $sid has invalid mark '$score_val'.");
                }
                
                $gp = get_grade_point($score);
                
                $sql_ins = "INSERT INTO marks (student_id, course_id, term, score, grade_point) 
                            VALUES (?, ?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE score = VALUES(score), grade_point = VALUES(grade_point)";
                $stmt_ins = mysqli_prepare($conn, $sql_ins);
                if (!$stmt_ins) {
                    throw new Exception("Failed to prepare grades database statement: " . mysqli_error($conn));
                }

                // Explicitly cast to ensure type match (integer, double, double)
                $course_id_int = (int) $post_course_id;
                $score_double = (float) $score;
                $gp_double = (float) $gp;

                if (!mysqli_stmt_bind_param($stmt_ins, "sisdd", $sid, $course_id_int, $post_term, $score_double, $gp_double)) {
                    throw new Exception("Failed to bind grade parameters for student: " . htmlspecialchars($sid));
                }

                if (!mysqli_stmt_execute($stmt_ins)) {
                    throw new Exception("Failed to execute grade save operation for student: " . htmlspecialchars($sid));
                }

                mysqli_stmt_close($stmt_ins);
            }
            mysqli_commit($conn);
            $form_success = "Marks registry updated successfully for $post_term.";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $form_err = $e->getMessage();
        }
    }
}

// Fetch all courses for selection dropdown
$courses_list = [];
$res_c = mysqli_query($conn, "SELECT id, course_name FROM courses ORDER BY course_name ASC");
while ($row = mysqli_fetch_assoc($res_c)) {
    $courses_list[] = $row;
}

if ($selected_course_id <= 0 && count($courses_list) > 0) {
    $selected_course_id = $courses_list[0]['id'];
}

// Fetch students with existing marks for selected course/term
$students = [];
if ($selected_course_id > 0) {
    $sql_stud = "SELECT s.*, m.score 
                 FROM students s 
                 LEFT JOIN marks m ON s.student_id = m.student_id AND m.course_id = ? AND m.term = ? 
                 WHERE s.course_id = ? 
                 ORDER BY s.name ASC";
    if ($stmt_stud = mysqli_prepare($conn, $sql_stud)) {
        mysqli_stmt_bind_param($stmt_stud, "isi", $selected_course_id, $selected_term, $selected_course_id);
        if (mysqli_stmt_execute($stmt_stud)) {
            $res_stud = mysqli_stmt_get_result($stmt_stud);
            while ($row = mysqli_fetch_assoc($res_stud)) {
                $students[] = $row;
            }
        }
        mysqli_stmt_close($stmt_stud);
    }
}

$page_title = "Log Examination Marks";
$page_description = "Manage term-wise academic examination marks and GPA conversions.";
include 'includes/header.php';
?>

<div class="container mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1"><i class="fa-solid fa-award text-primary me-2"></i>Grading Console</h1>
            <p class="text-muted mb-0">Record exam marks and track scholastic performance</p>
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

    <!-- Filter Configuration -->
    <div class="card border-0 shadow-sm rounded-12 p-3 mb-4">
        <form method="GET" action="grades.php" class="row g-3 align-items-end">
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
                <label for="term" class="form-label fw-semibold small text-secondary">Select Academic Term</label>
                <select name="term" id="term" class="form-select" required>
                    <option value="Term 1" <?php echo ($selected_term === 'Term 1') ? 'selected' : ''; ?>>Term 1</option>
                    <option value="Term 2" <?php echo ($selected_term === 'Term 2') ? 'selected' : ''; ?>>Term 2</option>
                    <option value="Final" <?php echo ($selected_term === 'Final') ? 'selected' : ''; ?>>Final Exam</option>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary-custom">
                    <i class="fa-solid fa-filter me-1"></i> Retrieve Matrix
                </button>
            </div>
        </form>
    </div>

    <?php if ($selected_course_id > 0): ?>
        <!-- Grades Matrix Board -->
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
                <h3 class="h5 fw-bold mb-0">Grading Matrix - Course: <span class="text-indigo"><?php echo htmlspecialchars($c_name); ?></span></h3>
                <span class="badge bg-indigo-subtle text-indigo px-2 py-1"><?php echo htmlspecialchars($selected_term); ?></span>
            </div>

            <form method="POST" action="grades.php?course_id=<?php echo $selected_course_id; ?>&term=<?php echo urlencode($selected_term); ?>" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="course_id" value="<?php echo $selected_course_id; ?>">
                <input type="hidden" name="term" value="<?php echo htmlspecialchars($selected_term); ?>">

                <div class="table-responsive mb-3">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width: 150px;">Student ID</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th class="text-center" style="width: 200px;">Exam Score (0-100)</th>
                                <th class="text-center" style="width: 150px;">Equivalent GPA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($students) > 0): ?>
                                <?php foreach ($students as $student): ?>
                                    <?php 
                                    $score = $student['score'];
                                    $gp = !is_null($score) ? get_grade_point($score) : null;
                                    ?>
                                    <tr>
                                        <td class="ps-3"><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($student['student_id']); ?></span></td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td><span class="small text-secondary"><?php echo htmlspecialchars($student['email']); ?></span></td>
                                        <td class="text-center">
                                            <div class="input-group input-group-sm justify-content-center">
                                                <input type="number" 
                                                       name="scores[<?php echo $student['student_id']; ?>]" 
                                                       class="form-control text-center fw-semibold w-50" 
                                                       style="max-width: 100px;" 
                                                       min="0" 
                                                       max="100" 
                                                       step="0.01" 
                                                       value="<?php echo !is_null($score) ? htmlspecialchars($score) : ''; ?>" 
                                                       placeholder="N/A">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </td>
                                        <td class="text-center fw-bold">
                                            <span id="gp_lbl_<?php echo $student['student_id']; ?>" class="text-slate">
                                                <?php echo !is_null($gp) ? number_format($gp, 2) : '--'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No students are currently registered in this course track.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (count($students) > 0): ?>
                    <div class="d-flex justify-content-end gap-2 border-top pt-3 px-2">
                        <button type="submit" name="save_grades" class="btn btn-primary-custom px-4">
                            <i class="fa-solid fa-save me-1"></i> Save Marks Matrix
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
// Dynamic grade point indicator helper on input change
document.querySelectorAll('input[type="number"]').forEach(input => {
    input.addEventListener('input', function() {
        const studentId = this.name.match(/\[(.*?)\]/)[1];
        const gpLabel = document.getElementById('gp_lbl_' + studentId);
        const val = parseFloat(this.value);
        
        if (isNaN(val) || val < 0 || val > 100) {
            gpLabel.textContent = '--';
            return;
        }
        
        let gp = 0.00;
        if (val >= 90) gp = 4.00;
        else if (val >= 80) gp = 3.00;
        else if (val >= 70) gp = 2.00;
        else if (val >= 60) gp = 1.00;
        
        gpLabel.textContent = gp.toFixed(2);
    });
});
</script>

<?php 
mysqli_close($conn);
include 'includes/footer.php'; 
?>
