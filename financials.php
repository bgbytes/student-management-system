<?php
// Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once "config/db.php";
require_once "config/currency_helper.php";

$form_success = $form_err = "";

// Handle Recording Payment (Directly Updates fee_ledger)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['record_payment'])) {
    // CSRF Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Alert: CSRF token verification failed.");
    }
    
    $invoice_id = intval($_POST['invoice_id']);
    $input_amount = trim($_POST['payment_amount']);
    $currency = isset($_POST['currency']) ? trim($_POST['currency']) : "INR";
    
    // Check if input_amount contains '$' indicator
    $force_usd = ($currency === 'USD');
    $pay_amount_inr = convert_usd_to_inr($input_amount, $force_usd ? 85.00 : false);
    
    if ($invoice_id <= 0 || $pay_amount_inr <= 0) {
        $form_err = "Invalid payment parameters. Amount must be positive.";
    } else {
        mysqli_begin_transaction($conn);
        try {
            // Retrieve invoice details
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
            
            $max_allowed_inr = $invoice['amount_due_inr'] - $invoice['amount_paid_inr'];
            if ($pay_amount_inr > ($max_allowed_inr + 0.01)) { // allow small float buffer
                throw new Exception("Payment amount (" . format_inr($pay_amount_inr) . ") exceeds outstanding due balance (" . format_inr($max_allowed_inr) . ").");
            }
            
            // Calculate new balances
            $new_amount_paid_inr = $invoice['amount_paid_inr'] + $pay_amount_inr;
            if ($new_amount_paid_inr > $invoice['amount_due_inr']) {
                $new_amount_paid_inr = $invoice['amount_due_inr']; // cap
            }
            
            $new_status = 'Pending';
            if ($new_amount_paid_inr >= $invoice['amount_due_inr']) {
                $new_status = 'Paid';
            } elseif ($new_amount_paid_inr > 0) {
                $new_status = 'Partially Paid';
            }
            
            // Update fee_ledger status (no payments table exists in 7-table schema, we update ledger directly)
            $sql_up = "UPDATE fee_ledger SET amount_paid_inr = ?, status = ? WHERE id = ?";
            $stmt_up = mysqli_prepare($conn, $sql_up);
            mysqli_stmt_bind_param($stmt_up, "dsi", $new_amount_paid_inr, $new_status, $invoice_id);
            mysqli_stmt_execute($stmt_up);
            mysqli_stmt_close($stmt_up);
            
            mysqli_commit($conn);
            $form_success = "Payment of " . format_inr($pay_amount_inr) . " successfully posted against Invoice #" . $invoice_id;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $form_err = $e->getMessage();
        }
    }
}

// Fetch aggregate statistics (in INR)
$total_revenue_inr = 0.00;
$total_outstanding_inr = 0.00;
$paid_count = 0;
$pending_count = 0;

$res_stats = mysqli_query($conn, "
    SELECT 
        SUM(amount_paid_inr) as revenue,
        SUM(amount_due_inr - amount_paid_inr) as outstanding,
        SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) as paid_cnt,
        SUM(CASE WHEN status = 'Pending' OR status = 'Partially Paid' THEN 1 ELSE 0 END) as pending_cnt
    FROM fee_ledger
");
if ($res_stats && $row = mysqli_fetch_assoc($res_stats)) {
    $total_revenue_inr = $row['revenue'] ? floatval($row['revenue']) : 0.00;
    $total_outstanding_inr = $row['outstanding'] ? floatval($row['outstanding']) : 0.00;
    $paid_count = intval($row['paid_cnt']);
    $pending_count = intval($row['pending_cnt']);
}

// Search and Filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : "";

// Build Invoice query joining students and courses
$sql_invoices = "
    SELECT l.*, s.name as student_name, c.course_name 
    FROM fee_ledger l 
    JOIN students s ON l.student_id = s.student_id
    JOIN courses c ON l.course_id = c.id
";
$conditions = [];
$params = [];
$types = "";

if (!empty($search)) {
    $conditions[] = "(l.student_id LIKE ? OR s.name LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if (!empty($filter_status)) {
    $conditions[] = "l.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if (count($conditions) > 0) {
    $sql_invoices .= " WHERE " . implode(" AND ", $conditions);
}
$sql_invoices .= " ORDER BY l.id DESC";

$invoices_list = [];
if ($stmt = mysqli_prepare($conn, $sql_invoices)) {
    if (count($params) > 0) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    if (mysqli_stmt_execute($stmt)) {
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $invoices_list[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

$page_title = "School Financials Ledger";
$page_description = "Track allocations, course invoice receipts, and student transaction history.";
include 'includes/header.php';
?>

<div class="container mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1">Financial Roster & Billing (INR ₹)</h1>
            <p class="text-muted mb-0">Track course allocations, student invoices, and record payments.</p>
        </div>
    </div>

    <!-- Alert dialogues -->
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

    <!-- Revenue Counters Row (INR ₹) -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card p-3 border-0 shadow-sm rounded-12 bg-white text-center">
                <span class="text-uppercase text-secondary fw-semibold small">Revenue Collected</span>
                <h3 class="display-6 fw-bold text-success mt-1"><?php echo format_inr($total_revenue_inr); ?></h3>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card p-3 border-0 shadow-sm rounded-12 bg-white text-center">
                <span class="text-uppercase text-secondary fw-semibold small">Outstanding Dues</span>
                <h3 class="display-6 fw-bold text-danger mt-1"><?php echo format_inr($total_outstanding_inr); ?></h3>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card p-3 border-0 shadow-sm rounded-12 bg-white text-center">
                <span class="text-uppercase text-secondary fw-semibold small">Fully Paid Accounts</span>
                <h3 class="display-6 fw-bold text-primary mt-1"><?php echo $paid_count; ?></h3>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card p-3 border-0 shadow-sm rounded-12 bg-white text-center">
                <span class="text-uppercase text-secondary fw-semibold small">Pending Invoices</span>
                <h3 class="display-6 fw-bold text-warning mt-1"><?php echo $pending_count; ?></h3>
            </div>
        </div>
    </div>

    <!-- Filter/Search Card -->
    <div class="card border-0 shadow-sm rounded-12 p-3 mb-4">
        <form method="GET" action="financials.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search Student ID or Name..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="Paid" <?php echo ($filter_status === 'Paid') ? 'selected' : ''; ?>>Paid</option>
                    <option value="Partially Paid" <?php echo ($filter_status === 'Partially Paid') ? 'selected' : ''; ?>>Partially Paid</option>
                    <option value="Pending" <?php echo ($filter_status === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                </select>
            </div>
            <div class="col-md-3 d-grid gap-2 d-flex">
                <button type="submit" class="btn btn-primary-custom w-100 py-2">
                    <i class="fa-solid fa-search"></i> Filter
                </button>
                <?php if (!empty($search) || !empty($filter_status)): ?>
                    <a href="financials.php" class="btn btn-light border py-2" title="Reset Filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Invoices Ledger Table -->
    <div class="card border-0 shadow-sm rounded-12 p-3">
        <h3 class="h5 fw-bold mb-3 px-2">Invoices Ledger</h3>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Inv ID</th>
                        <th>Student Name (ID)</th>
                        <th>Course</th>
                        <th>Amount Due</th>
                        <th>Amount Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($invoices_list) > 0): ?>
                        <?php foreach ($invoices_list as $inv): ?>
                            <?php 
                            $bal = $inv['amount_due_inr'] - $inv['amount_paid_inr'];
                            $status_class = "bg-warning-subtle text-warning border-warning";
                            if ($inv['status'] === 'Paid') $status_class = "bg-success-subtle text-success border-success";
                            if ($inv['status'] === 'Partially Paid') $status_class = "bg-info-subtle text-info border-info";
                            ?>
                            <tr>
                                <td class="ps-3">#<?php echo $inv['id']; ?></td>
                                <td>
                                    <div class="fw-semibold text-slate"><?php echo htmlspecialchars($inv['student_name']); ?></div>
                                    <span class="badge bg-light text-dark border small font-monospace" style="font-size: 0.75rem;"><?php echo htmlspecialchars($inv['student_id']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($inv['course_name']); ?></td>
                                <td><?php echo format_inr($inv['amount_due_inr']); ?></td>
                                <td><?php echo format_inr($inv['amount_paid_inr']); ?></td>
                                <td class="fw-semibold <?php echo ($bal > 0) ? 'text-danger' : 'text-success'; ?>"><?php echo format_inr($bal); ?></td>
                                <td><span class="badge <?php echo $status_class; ?> border px-2 py-1"><?php echo htmlspecialchars($inv['status']); ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($inv['due_date'])); ?></td>
                                <td class="text-center">
                                    <?php if ($bal > 0): ?>
                                        <button class="btn btn-sm btn-outline-success rounded-pill px-3" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#paymentModal" 
                                                data-invoice-id="<?php echo $inv['id']; ?>"
                                                data-student-name="<?php echo htmlspecialchars($inv['student_name']); ?>"
                                                data-outstanding="<?php echo $bal; ?>"
                                                title="Record Payment">
                                            <i class="fa-solid fa-dollar-sign"></i> Pay
                                        </button>
                                    <?php else: ?>
                                        <span class="text-success small"><i class="fa-solid fa-check-double"></i> Cleared</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">No matching invoice ledgers found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Dialog for Recording Payment -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 rounded-16 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="paymentModalLabel"><i class="fa-solid fa-cash-register text-success me-2"></i>Record Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="financials.php" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="invoice_id" id="modal_invoice_id">
                    <input type="hidden" name="record_payment" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Student Account</label>
                        <input type="text" id="modal_student_name" class="form-control bg-light" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Outstanding Due Balance (INR)</label>
                        <input type="text" id="modal_outstanding" class="form-control bg-light" readonly>
                    </div>

                    <!-- Currency selector toggle -->
                    <div class="mb-3">
                        <label for="currency" class="form-label text-secondary small fw-semibold">Payment Currency</label>
                        <select name="currency" id="currency" class="form-select" required>
                            <option value="INR" selected>Indian Rupees (₹ INR)</option>
                            <option value="USD">US Dollars ($ USD) - converts 1 USD = 85 INR</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="payment_amount" class="form-label text-secondary small fw-semibold">Payment Amount</label>
                        <div class="input-group">
                            <input type="text" name="payment_amount" id="payment_amount" class="form-control" required placeholder="0.00 or $0.00">
                            <div class="invalid-feedback">Please enter a valid amount. You can prefix USD with '$' symbol.</div>
                        </div>
                        <span class="text-muted small" style="font-size: 0.75rem;">Tip: If USD selected or prefix '$' provided, it will automatically multiply by 85.</span>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light border rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4"><i class="fa-solid fa-file-invoice-dollar me-1"></i> Post Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal data binding
const paymentModal = document.getElementById('paymentModal');
if (paymentModal) {
    paymentModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const invoiceId = button.getAttribute('data-invoice-id');
        const studentName = button.getAttribute('data-student-name');
        const outstanding = parseFloat(button.getAttribute('data-outstanding'));
        
        document.getElementById('modal_invoice_id').value = invoiceId;
        document.getElementById('modal_student_name').value = studentName;
        document.getElementById('modal_outstanding').value = '₹' + outstanding.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        
        const payInput = document.getElementById('payment_amount');
        payInput.value = outstanding.toFixed(2);
    });
}

// Bootstrap form validator
(function () {
  'use strict'
  var forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add('was-validated')
      }, false)
    })
})()
</script>

<?php 
mysqli_close($conn);
include 'includes/footer.php'; 
?>
