<?php
/**
 * Search Students API Endpoint
 * EduManage Enterprise Portal
 * 
 * Searches student profiles using keyword parameters and returns JSON.
 */

header("Content-Type: application/json; charset=UTF-8");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authorization
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized access."]);
    exit;
}

require_once "../config/db.php";

$search = isset($_GET['search']) ? trim($_GET['search']) : "";

if (!empty($search)) {
    $sql = "SELECT s.*, c.course_name, c.course_name as course 
            FROM students s 
            LEFT JOIN courses c ON s.course_id = c.id 
            WHERE s.name LIKE ? OR s.student_id LIKE ? 
            ORDER BY s.id DESC";
    $param = "%" . $search . "%";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ss", $param, $param);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            $students = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $students[] = $row;
            }
            echo json_encode($students);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Database execution failed."]);
        }
        mysqli_stmt_close($stmt);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to prepare search statement."]);
    }
} else {
    // If search is empty, return all students
    $sql = "SELECT s.*, c.course_name, c.course_name as course 
            FROM students s 
            LEFT JOIN courses c ON s.course_id = c.id 
            ORDER BY s.id DESC";
    $res = mysqli_query($conn, $sql);
    $students = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $students[] = $row;
    }
    echo json_encode($students);
}

mysqli_close($conn);
exit;
?>
