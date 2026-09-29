<?php
/**
 * Data Export Engine
 * EduManage Enterprise Portal
 * 
 * Safely fetches active records and uses native PHP output buffering
 * to generate and stream CSV file downloads of school registers.
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Guard: Only Admins and Faculty can export roster data
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || !in_array($_SESSION['user_role'], ['admin', 'faculty'])) {
    die("Security Error: Unauthorized access to data export tools.");
}

require_once "config/db.php";

$type = isset($_GET['type']) ? trim($_GET['type']) : "students";

// Clean output buffer to prevent corrupted character downloads
if (ob_get_level()) {
    ob_end_clean();
}

// Open output stream
$output = fopen('php://output', 'w');

if ($type === 'students') {
    // 1. Export Students Register
    $filename = "students_directory_" . date("Ymd") . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    // Header Row
    fputcsv($output, ['Student ID', 'Full Name', 'Email', 'Phone', 'Course Enrolled', 'Admission Date', 'Created At']);
    
    // Fetch and Write Data
    $sql = "SELECT s.student_id, s.name, s.email, s.phone, c.course_name, s.admission_date, s.created_at 
            FROM students s 
            JOIN courses c ON s.course_id = c.id 
            ORDER BY s.name ASC";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $row);
    }

} elseif ($type === 'financials') {
    // Only Admin can view/export financials
    if ($_SESSION['user_role'] !== 'admin') {
        die("Security Error: Unauthorized access to financial registry data.");
    }
    
    // 2. Export Invoices & Financials Ledger (in INR ₹)
    $filename = "billing_ledger_" . date("Ymd") . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    fputcsv($output, ['Invoice ID', 'Student ID', 'Course Track', 'Amount Due (INR ₹)', 'Amount Paid (INR ₹)', 'Outstanding Balance (INR ₹)', 'Status', 'Due Date', 'Created At']);
    
    $sql = "SELECT l.id, l.student_id, c.course_name, l.amount_due_inr, l.amount_paid_inr, (l.amount_due_inr - l.amount_paid_inr) as balance, l.status, l.due_date, l.created_at 
            FROM fee_ledger l 
            JOIN courses c ON l.course_id = c.id 
            ORDER BY l.id DESC";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $row);
    }

} elseif ($type === 'attendance') {
    // 3. Export Attendance History
    $filename = "attendance_roster_" . date("Ymd") . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    fputcsv($output, ['Attendance ID', 'Student ID', 'Student Name', 'Course Track', 'Date', 'Status']);
    
    $sql = "SELECT a.id, a.student_id, s.name, c.course_name, a.date, a.status 
            FROM attendance a 
            JOIN students s ON a.student_id = s.student_id 
            JOIN courses c ON a.course_id = c.id 
            ORDER BY a.date DESC, s.name ASC";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $row);
    }

} elseif ($type === 'grades') {
    // 4. Export Examination Grades
    $filename = "examination_grades_" . date("Ymd") . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    fputcsv($output, ['Grade ID', 'Student ID', 'Student Name', 'Course Track', 'Academic Term', 'Score (%)', 'Grade Points (GPA)']);
    
    $sql = "SELECT m.id, m.student_id, s.name, c.course_name, m.term, m.score, m.grade_point 
            FROM marks m 
            JOIN students s ON m.student_id = s.student_id 
            JOIN courses c ON m.course_id = c.id 
            ORDER BY c.course_name ASC, m.term ASC, s.name ASC";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $row);
    }
} else {
    die("Error: Invalid export category request.");
}

fclose($output);
mysqli_close($conn);
exit;
?>
