<?php
include 'db_connect.php'; // Database connection

// File to track last reset date
$last_reset_date_file = __DIR__ . "/last_dailyPayment_reset_date.txt";
$today = date("Y-m-d");

// Function to reset daily payments
function resetDailyPayments($conn)
{
    // Define backup directory
    $backupDir = __DIR__ . "/daily_payment_backups";

    // Check if directory exists, if not, create it
    if (!is_dir($backupDir)) {
        if (!mkdir($backupDir, 0777, true)) {
            die("Failed to create backup directory. Check folder permissions.");
        }
    }

    // Verify if directory is writable
    if (!is_writable($backupDir)) {
        die("Backup directory is not writable. Please check folder permissions.");
    }

    // Backup `daily_payments` table to CSV before clearing
    $backupFile = $backupDir . "/daily_payments_backup_" . date("Y-m-d") . ".csv";
    $file = fopen($backupFile, "w");

    if (!$file) {
        die("Failed to create backup file. Please check folder permissions.");
    }

    // Add CSV headers
    fputcsv($file, ["id", "customer_name", "amount", "payment_date"]);

    // Fetch and write data
    $query = "SELECT * FROM loan_payments";
    $result = $conn->query($query);
    $totalAmount = 0;

    while ($row = $result->fetch_assoc()) {
        fputcsv($file, $row);
        $totalAmount += $row['amount']; // Calculate total payments
    }

    // Add total row to CSV
    fputcsv($file, ["", "Total", $totalAmount, ""]);

    fclose($file);
    // Backup payments before deleting
    $conn->query("INSERT INTO loan_payments_backup (loan_id, payment_amount, payment_date)
 SELECT loan_id, payment_amount, payment_date FROM loan_payments");

    // Now reset loan_payments (empty only this table)
    $conn->query("TRUNCATE TABLE loan_payments");

    // Ensure that loans table remains unchanged
    echo "Payment history reset, but past data is backed up.";

    // Clear the `daily_payments` table
    $conn->query("DELETE FROM loan_payments");

    // Reset AUTO_INCREMENT
    $conn->query("ALTER TABLE loan_payments AUTO_INCREMENT = 1");

    echo "Daily payments reset successfully! Total payments: $" . number_format($totalAmount, 2);
}

// Check if it's a new day and reset payments
if (!file_exists($last_reset_date_file) || file_get_contents($last_reset_date_file) != $today) {
    resetDailyPayments($conn);
    file_put_contents($last_reset_date_file, $today);
} else {
    echo "Daily payments reset already done for today.";
}

// Handle Add Payment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_payment'])) {
    $customer_name = isset($_POST['customer_name']) ? htmlspecialchars($_POST['customer_name']) : "";
    $payment_amount = isset($_POST['payment_amount']) ? (float) $_POST['payment_amount'] : 0;
    $payment_date = date('Y-m-d'); // Auto-fill current date
    $loan_id = isset($_POST['loan_id']) && !empty($_POST['loan_id']) ? (int) $_POST['loan_id'] : null;

    if (empty($customer_name)) {
        echo "<script>alert('Error: Customer name is required.');</script>";
        exit();
    }

    // If loan_id is not provided, get the latest loan for the customer
    if (!$loan_id) {
        $checkLoan = $conn->prepare("SELECT id, remaining_balance FROM loans WHERE customer_name = ? ORDER BY id DESC LIMIT 1");
        $checkLoan->bind_param("s", $customer_name);
        $checkLoan->execute();
        $result = $checkLoan->get_result();
        $loanRow = $result->fetch_assoc();
        $checkLoan->close();

        if (!$loanRow) {
            echo "<script>alert('Error: No active loan found for this customer.');</script>";
            exit();
        }
        $loan_id = $loanRow['id'];
    }

    // Fetch remaining balance before inserting payment
    $loanCheck = $conn->prepare("SELECT remaining_balance FROM loans WHERE id = ?");
    $loanCheck->bind_param("i", $loan_id);
    $loanCheck->execute();
    $loanResult = $loanCheck->get_result();
    $loanRow = $loanResult->fetch_assoc();
    $loanCheck->close();

    if (!$loanRow || $payment_amount > $loanRow['remaining_balance']) {
        echo "<script>alert('Error: Payment amount exceeds remaining loan balance.');</script>";
        exit();
    }

    // Insert payment record
    $stmt = $conn->prepare("INSERT INTO loan_payments (customer_name, payment_amount, payment_date, loan_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sdsi", $customer_name, $payment_amount, $payment_date, $loan_id);

    if ($stmt->execute()) {
        // Deduct payment amount from remaining balance
        $updateLoan = $conn->prepare("UPDATE loans SET remaining_balance = remaining_balance - ? WHERE id = ?");
        $updateLoan->bind_param("di", $payment_amount, $loan_id);
        $updateLoan->execute();
        $updateLoan->close();

        header("Location: daily_payment.php");
        exit();
    } else {
        echo "<script>alert('Error: " . $stmt->error . "');</script>";
    }
    $stmt->close();
}

// Handle Edit Payment (Fetch Data)
$edit_id = $edit_customer = $edit_amount = $edit_date = $edit_loan_id = "";
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];
    $query = $conn->prepare("SELECT * FROM loan_payments WHERE id=?");
    $query->bind_param("i", $edit_id);
    $query->execute();
    $result = $query->get_result();
    if ($row = $result->fetch_assoc()) {
        $edit_customer = $row['customer_name'];
        $edit_amount = $row['payment_amount'];
        $edit_date = $row['payment_date'];
        $edit_loan_id = $row['loan_id'];
    }
    $query->close();
}

// Handle Delete Payment
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int) $_GET['delete'];

    // Get payment details before deleting
    $paymentQuery = $conn->prepare("SELECT payment_amount, loan_id FROM loan_payments WHERE id=?");
    $paymentQuery->bind_param("i", $delete_id);
    $paymentQuery->execute();
    $paymentResult = $paymentQuery->get_result();
    $payment = $paymentResult->fetch_assoc();
    $paymentQuery->close();

    if ($payment) {
        // Delete payment
        $stmt = $conn->prepare("DELETE FROM loan_payments WHERE id=?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            // Restore the loan balance
            $restoreLoan = $conn->prepare("UPDATE loans SET remaining_balance = remaining_balance + ? WHERE id = ?");
            $restoreLoan->bind_param("di", $payment['payment_amount'], $payment['loan_id']);
            $restoreLoan->execute();
            $restoreLoan->close();
        }
        $stmt->close();
    }
    header("Location: daily_payment.php");
    exit();
}

// Fetch Payments Data
$result = $conn->query("SELECT * FROM loan_payments ORDER BY payment_date DESC");

// Calculate Grand Total
$total_result = $conn->query("SELECT SUM(payment_amount) AS grand_total FROM loan_payments");
$grand_total = $total_result->fetch_assoc()['grand_total'] ?? 0;

// Get customer list for dropdown
$customers_query = $conn->query("SELECT DISTINCT customer_name FROM loans ORDER BY customer_name");
$customers = [];
while ($row = $customers_query->fetch_assoc()) {
    $customers[] = $row['customer_name'];
}

// Get today's total
$today_total_query = $conn->query("SELECT SUM(payment_amount) AS today_total FROM loan_payments WHERE payment_date = CURDATE()");
$today_total = $today_total_query->fetch_assoc()['today_total'] ?? 0;

// Get payment count
$payment_count_query = $conn->query("SELECT COUNT(*) AS count FROM loan_payments");
$payment_count = $payment_count_query->fetch_assoc()['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Loan Payments</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: rgba(37, 99, 235, 0.1);
            --secondary: #9333ea;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --background: #f8fafc;
            --foreground: #1e293b;
            --card: #ffffff;
            --border: #e2e8f0;
            --muted: #94a3b8;
            --radius: 0.5rem;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(to bottom right, #f8fafc, #f1f5f9);
            color: var(--foreground);
            min-height: 100vh;
            padding: 0;
        }

        .header {
            background-color: var(--card);
            padding: 1rem 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
        }

        .header-actions {
            display: flex;
            gap: 0.75rem;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 2rem auto;
        }

        .page-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--foreground);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-title {
            color: var(--muted);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--foreground);
        }

        .grid {
            display: grid;
            gap: 2rem;
        }

        @media (min-width: 1024px) {
            .grid {
                grid-template-columns: 1fr 2fr;
            }
        }

        .card {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .card-header {
            background-color: var(--primary-light);
            padding: 1.25rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--foreground);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-content {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--foreground);
        }

        .form-control {
            width: 100%;
            padding: 0.625rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background-color: var(--card);
            color: var(--foreground);
            font-size: 0.875rem;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
        }

        .form-select {
            width: 100%;
            padding: 0.625rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background-color: var(--card);
            color: var(--foreground);
            font-size: 0.875rem;
            transition: border-color 0.2s;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%231e293b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.625rem center;
            background-size: 1rem;
        }

        .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
        }

        .table-container {
            overflow-x: auto;
            border-radius: var(--radius);
            border: 1px solid var(--border);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background-color: var(--primary-light);
            font-weight: 600;
            color: var(--foreground);
            position: sticky;
            top: 0;
        }

        tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        .amount {
            font-weight: 600;
            color: var(--success);
        }

        .date {
            color: var(--muted);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: var(--radius);
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            gap: 0.5rem;
            border: none;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-success {
            background-color: var(--success);
            color: white;
        }

        .btn-success:hover {
            background-color: #0d9668;
        }

        .btn-warning {
            background-color: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background-color: #d97706;
        }

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        .btn-outline {
            background-color: transparent;
            color: var(--foreground);
            border: 1px solid var(--border);
        }

        .btn-outline:hover {
            background-color: #f8fafc;
        }

        .btn-block {
            width: 100%;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .button-group {
            display: flex;
            gap: 0.5rem;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }

        .badge-primary {
            background-color: var(--primary-light);
            color: var(--primary);
        }

        .badge-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .empty-state {
            padding: 3rem;
            text-align: center;
            background-color: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .empty-icon {
            font-size: 3rem;
            color: var(--muted);
            margin-bottom: 1rem;
        }

        .empty-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .empty-description {
            color: var(--muted);
            margin-bottom: 1.5rem;
        }

        .grand-total-row {
            background-color: var(--primary-light);
            font-weight: 700;
        }

        .grand-total-row td {
            padding: 1rem;
        }

        @media (max-width: 768px) {
            .container {
                width: 95%;
            }
            
            .header-actions {
                flex-wrap: wrap;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="header-title">
            <i class="fas fa-money-bill-wave"></i>
            <span>Loan Management System</span>
        </div>
        <div class="header-actions">
            <a href="index.php" class="btn btn-outline">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="manage_loan.php" class="btn btn-outline">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Manage Loans</span>
            </a>
            <a href="loan_history.php" class="btn btn-outline">
                <i class="fas fa-history"></i>
                <span>Loan History</span>
            </a>
        </div>
    </header>

    <div class="container">
        <h1 class="page-title">
            <i class="fas fa-hand-holding-usd"></i>
            Daily Loan Payments
        </h1>

        <!-- Statistics Summary -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-title">
                    <i class="fas fa-calendar-day"></i>
                    Today's Payments
                </div>
                <div class="stat-value">$<?php echo number_format($today_total, 2); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">
                    <i class="fas fa-dollar-sign"></i>
                    Total Collected
                </div>
                <div class="stat-value">$<?php echo number_format($grand_total, 2); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">
                    <i class="fas fa-receipt"></i>
                    Payment Count
                </div>
                <div class="stat-value"><?php echo number_format($payment_count); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">
                    <i class="fas fa-calendar-check"></i>
                    Payment Date
                </div>
                <div class="stat-value"><?php echo date('M d, Y'); ?></div>
            </div>
        </div>

        <div class="grid">
            <!-- Payment Form -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-plus-circle"></i>
                        <?php echo $edit_id ? 'Edit Payment' : 'Add Payment'; ?>
                    </div>
                </div>
                <div class="card-content">
                    <form method="post">
                        <?php if ($edit_id): ?>
                            <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label class="form-label">Customer Name</label>
                            <select name="customer_name" class="form-select" required>
                                <option value="">Select Customer</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo htmlspecialchars($customer); ?>" <?php echo ($customer == $edit_customer) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Amount</label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 10px; top: 9px; color: var(--muted);">$</span>
                                <input type="number" name="payment_amount" step="0.01" min="0.01" class="form-control" style="padding-left: 25px;" value="<?php echo htmlspecialchars($edit_amount); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Date</label>
                            <input type="date" name="payment_date" class="form-control" value="<?php echo htmlspecialchars($edit_date ?: date('Y-m-d')); ?>" required>
                        </div>

                        <button type="submit" name="add_payment" class="btn btn-primary btn-block">
                            <i class="fas fa-<?php echo $edit_id ? 'save' : 'plus'; ?>"></i>
                            <?php echo $edit_id ? 'Update Payment' : 'Add Payment'; ?>
                        </button>
                    </form>

                    <div class="action-buttons">
                        <a href="manage_loan.php" class="btn btn-outline btn-block">
                            <i class="fas fa-file-invoice-dollar"></i> Manage Loans
                        </a>
                        <a href="loan_details.php" class="btn btn-outline btn-block">
                            <i class="fas fa-info-circle"></i> Check Loan Details
                        </a>
                        <a href="payment_report.php" class="btn btn-outline btn-block">
                            <i class="fas fa-chart-bar"></i> Payment Reports
                        </a>
                    </div>
                </div>
            </div>

            <!-- Payment List -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-list"></i>
                        Payment Records
                        <span class="badge badge-primary"><?php echo $payment_count; ?> payments</span>
                    </div>
                    <div>
                        <a href="export_payments.php" class="btn btn-outline">
                            <i class="fas fa-file-export"></i> Export
                        </a>
                    </div>
                </div>
                <div class="card-content" style="padding: 0;">
                    <?php if ($result->num_rows > 0): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Loan ID</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($payment = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($payment['loan_id']); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($payment['customer_name']); ?></strong>
                                            </td>
                                            <td class="amount">
                                                $<?php echo number_format($payment['payment_amount'], 2); ?>
                                            </td>
                                            <td class="date">
                                                <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                            </td>
                                            <td>
                                                <div class="button-group">
                                                    <a href="daily_payment.php?edit=<?php echo $payment['id']; ?>" class="btn btn-warning">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="daily_payment.php?delete=<?php echo $payment['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this payment? This will restore the loan balance.');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    <a href="print_receipt.php?id=<?php echo $payment['id']; ?>" class="btn btn-outline">
                                                        <i class="fas fa-print"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                    <tr class="grand-total-row">
                                        <td colspan="2" style="text-align: right;">Grand Total:</td>
                                        <td colspan="3">$<?php echo number_format($grand_total, 2); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="empty-title">No payments recorded today</div>
                            <div class="empty-description">Add a new payment to get started.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="action-buttons" style="justify-content: center;">
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-tachometer-alt"></i> Back to Dashboard
            </a>
            <a href="manage_loan.php" class="btn btn-success">
                <i class="fas fa-file-invoice-dollar"></i> Back to Loans
            </a>
            <a href="manual_reset.php" class="btn btn-danger" onclick="return confirm('Are you sure you want to reset all daily payments? This action cannot be undone.');">
                <i class="fas fa-sync-alt"></i> Reset Payments
            </a>
            <a href="payment_history.php" class="btn btn-outline">
                <i class="fas fa-history"></i> Payment History
            </a>
        </div>
    </div>

    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
  {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>

    <!-- Customer search script -->
    <script>
        // Enable customer search in dropdown
        document.addEventListener('DOMContentLoaded', function() {
            const customerSelect = document.querySelector('select[name="customer_name"]');
            
            // Add search functionality to customer dropdown
            customerSelect.addEventListener('keydown', function(e) {
                if (/^[a-z0-9]$/i.test(e.key)) {
                    const options = Array.from(this.options);
                    const searchTerm = e.key.toLowerCase();
                    
                    const matchingOption = options.find(option => 
                        option.text.toLowerCase().startsWith(searchTerm)
                    );
                    
                    if (matchingOption) {
                        this.value = matchingOption.value;
                    }
                }
            });
        });
    </script>
</body>

</html>
<?php $conn->close(); ?>