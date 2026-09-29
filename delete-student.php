<?php
// Session check at the very top of protected admin pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Check CSRF token for deletion GET request
if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Security Alert: CSRF token verification failed.");
}

// Check if GET parameter id exists
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    // Include database connection
    require_once "config/db.php";
    
    $student_ref_id = trim($_GET["id"]);
    
    // Begin Database Transaction to maintain integrity
    mysqli_begin_transaction($conn);
    
    try {
        // 1. Retrieve the student ID first to delete corresponding dependent records
        $sql_select = "SELECT student_id FROM students WHERE id = ?";
        $stmt_sel = mysqli_prepare($conn, $sql_select);
        if (!$stmt_sel) {
            throw new Exception("Failed to prepare student lookup: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt_sel, "i", $student_ref_id);
        if (!mysqli_stmt_execute($stmt_sel)) {
            throw new Exception("Failed to execute student lookup: " . mysqli_stmt_error($stmt_sel));
        }
        
        $res_sel = mysqli_stmt_get_result($stmt_sel);
        $student_id = "";
        if ($row = mysqli_fetch_assoc($res_sel)) {
            $student_id = $row['student_id'];
        }
        mysqli_stmt_close($stmt_sel);
        
        if (!empty($student_id)) {
            // Delete dependent records first to satisfy foreign key constraints safely
            // A. Delete from attendance
            $sql_del_att = "DELETE FROM attendance WHERE student_id = ?";
            if ($stmt_att = mysqli_prepare($conn, $sql_del_att)) {
                mysqli_stmt_bind_param($stmt_att, "s", $student_id);
                if (!mysqli_stmt_execute($stmt_att)) {
                    throw new Exception("Failed to delete attendance logs: " . mysqli_stmt_error($stmt_att));
                }
                mysqli_stmt_close($stmt_att);
            } else {
                throw new Exception("Failed to prepare attendance delete statement: " . mysqli_error($conn));
            }
            
            // B. Delete from marks
            $sql_del_marks = "DELETE FROM marks WHERE student_id = ?";
            if ($stmt_marks = mysqli_prepare($conn, $sql_del_marks)) {
                mysqli_stmt_bind_param($stmt_marks, "s", $student_id);
                if (!mysqli_stmt_execute($stmt_marks)) {
                    throw new Exception("Failed to delete marks: " . mysqli_stmt_error($stmt_marks));
                }
                mysqli_stmt_close($stmt_marks);
            } else {
                throw new Exception("Failed to prepare marks delete statement: " . mysqli_error($conn));
            }
            
            // C. Delete from fee_ledger
            $sql_del_fees = "DELETE FROM fee_ledger WHERE student_id = ?";
            if ($stmt_fees = mysqli_prepare($conn, $sql_del_fees)) {
                mysqli_stmt_bind_param($stmt_fees, "s", $student_id);
                if (!mysqli_stmt_execute($stmt_fees)) {
                    throw new Exception("Failed to delete fee ledger records: " . mysqli_stmt_error($stmt_fees));
                }
                mysqli_stmt_close($stmt_fees);
            } else {
                throw new Exception("Failed to prepare fee ledger delete statement: " . mysqli_error($conn));
            }
            
            // D. Delete student profile (main table)
            $sql_del_stud = "DELETE FROM students WHERE id = ?";
            if ($stmt_del_stud = mysqli_prepare($conn, $sql_del_stud)) {
                mysqli_stmt_bind_param($stmt_del_stud, "i", $student_ref_id);
                if (!mysqli_stmt_execute($stmt_del_stud)) {
                    throw new Exception("Failed to delete student profile: " . mysqli_stmt_error($stmt_del_stud));
                }
                mysqli_stmt_close($stmt_del_stud);
            } else {
                throw new Exception("Failed to prepare student delete statement: " . mysqli_error($conn));
            }
            
            mysqli_commit($conn);
            
            // Set success flash session message
            $_SESSION['message'] = "Student record deleted successfully.";
            $_SESSION['msg_type'] = "success";
        } else {
            throw new Exception("Student record not found.");
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['message'] = "Error: Could not execute profile deletion: " . $e->getMessage();
        $_SESSION['msg_type'] = "danger";
    }
    
    // Close connection
    mysqli_close($conn);
} else {
    // ID parameter is missing or empty
    $_SESSION['message'] = "Error: Invalid request. Student ID is missing.";
    $_SESSION['msg_type'] = "danger";
}

// Redirect back to students registry directory
header("Location: students.php");
exit();
?>
