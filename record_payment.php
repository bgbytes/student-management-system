<?php
/**
 * Record Payment API Endpoint
 * EduManage Enterprise Portal
 * 
 * Secure backend API returning JSON responses for recorded payments in INR.
 */

header("Content-Type: application/json; charset=UTF-8");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Authentication Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit;
}

// 2. HTTP Method Check
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed. Use POST."]);
    exit;
}

// 3. CSRF Verification
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Security Alert: Invalid CSRF verification token."]);
    exit;
}

require_once "../config/db.php";
require_once "../config/currency_helper.php";

$invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
$input_amount = isset($_POST['payment_amount']) ? trim($_POST['payment_amount']) : "";
$currency = isset($_POST['currency']) ? trim($_POST['currency']) : "INR";

$force_usd = ($currency === 'USD');
$pay_amount_inr = convert_usd_to_inr($input_amount, $force_usd ? 85.00 : false);

if ($invoice_id <= 0 || $pay_amount_inr <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Bad Request. Invoice ID and valid payment amount are required."]);
    mysqli_close($conn);
    exit;
}

// Begin Database transaction
mysqli_begin_transaction($conn);

try {
    // Lock row for update
    $sql_inv = "SELECT amount_due_inr, amount_paid_inr FROM fee_ledger WHERE id = ? FOR UPDATE";
    $stmt_inv = mysqli_prepare($conn, $sql_inv);
    mysqli_stmt_bind_param($stmt_inv, "i", $invoice_id);
    mysqli_stmt_execute($stmt_inv);
    $res = mysqli_stmt_get_result($stmt_inv);
    $invoice = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt_inv);
    
    if (!$invoice) {
        throw new Exception("Invoice ID not found.");
    }
    
    $outstanding = $invoice['amount_due_inr'] - $invoice['amount_paid_inr'];
    if ($pay_amount_inr > ($outstanding + 0.01)) {
        throw new Exception("Payment amount exceeds outstanding dues.");
    }
    
    // Calculate new balances
    $new_amount_paid_inr = $invoice['amount_paid_inr'] + $pay_amount_inr;
    if ($new_amount_paid_inr > $invoice['amount_due_inr']) {
        $new_amount_paid_inr = $invoice['amount_due_inr'];
    }
    
    $new_status = 'Pending';
    if ($new_amount_paid_inr >= $invoice['amount_due_inr']) {
        $new_status = 'Paid';
    } elseif ($new_amount_paid_inr > 0) {
        $new_status = 'Partially Paid';
    }
    
    // Update Ledger (No payments table in 7-table layout, updated directly)
    $sql_up = "UPDATE fee_ledger SET amount_paid_inr = ?, status = ? WHERE id = ?";
    $stmt_up = mysqli_prepare($conn, $sql_up);
    mysqli_stmt_bind_param($stmt_up, "dsi", $new_amount_paid_inr, $new_status, $invoice_id);
    mysqli_stmt_execute($stmt_up);
    mysqli_stmt_close($stmt_up);
    
    mysqli_commit($conn);
    
    echo json_encode([
        "success" => true, 
        "message" => "Payment of " . format_inr($pay_amount_inr) . " successfully posted.",
        "invoice_id" => $invoice_id,
        "new_status" => $new_status,
        "new_amount_paid_inr" => $new_amount_paid_inr
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Transaction failed: " . $e->getMessage()]);
}

mysqli_close($conn);
exit;
?>
