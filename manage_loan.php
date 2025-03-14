<?php
include 'db_connect.php'; // Database connection

// Handle Add Loan
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_loan'])) {
    $customer_name = htmlspecialchars($_POST['customer_name']);
    $total_loan = (float) $_POST['total_loan'];
    $loan_date = htmlspecialchars($_POST['loan_date']);

    // Insert loan with remaining balance same as total loan
    $stmt = $conn->prepare("INSERT INTO loans (customer_name, total_loan, remaining_balance, loan_date) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sdds", $customer_name, $total_loan, $total_loan, $loan_date);

    if ($stmt->execute()) {
        header("Location: manage_loan.php?success=1");
        exit();
    } else {
        echo "<script>alert('Error: " . $stmt->error . "');</script>";
    }
    $stmt->close();
}

// Handle Edit Loan (Fetch Data)
$edit_id = "";
$edit_customer = "";
$edit_amount = "";
$edit_date = "";
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $query = $conn->prepare("SELECT * FROM loans WHERE id=?");
    $query->bind_param("i", $edit_id);
    $query->execute();
    $result = $query->get_result();
    if ($row = $result->fetch_assoc()) {
        $edit_customer = $row['customer_name'];
        $edit_amount = $row['total_loan'];
        $edit_date = $row['loan_date'];
    }
}

// Handle Update Loan
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_loan'])) {
    $id = $_POST['id'];
    $customer_name = htmlspecialchars($_POST['customer_name']);
    $total_loan = (float) $_POST['total_loan'];
    $loan_date = htmlspecialchars($_POST['loan_date']);

    $stmt = $conn->prepare("UPDATE loans SET customer_name=?, total_loan=?, loan_date=? WHERE id=?");
    $stmt->bind_param("sdsi", $customer_name, $total_loan, $loan_date, $id);

    if ($stmt->execute()) {
        header("Location: manage_loan.php?updated=1");
        exit();
    } else {
        echo "<script>alert('Error: " . $stmt->error . "');</script>";
    }
}

// Handle Delete Loan
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM loans WHERE id=?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        header("Location: manage_loan.php?deleted=1");
        exit();
    }
}

// Handle Search (Prepared Statement)
$search_query = "";
if (isset($_POST['search'])) {
    $search_query = "%{$_POST['search_query']}%";
    $stmt = $conn->prepare("SELECT * FROM loans WHERE customer_name LIKE ? ORDER BY loan_date DESC");
    $stmt->bind_param("s", $search_query);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT * FROM loans ORDER BY loan_date DESC");
}

// Calculate Grand Total
$grand_total_result = $conn->query("SELECT SUM(total_loan) AS grand_total FROM loans");
$grand_total_row = $grand_total_result->fetch_assoc();
$grand_total = $grand_total_row['grand_total'] ? $grand_total_row['grand_total'] : 0;

// Calculate total remaining balance
$remaining_balance_result = $conn->query("SELECT SUM(remaining_balance) AS total_remaining FROM loans");
$remaining_balance_row = $remaining_balance_result->fetch_assoc();
$total_remaining = $remaining_balance_row['total_remaining'] ? $remaining_balance_row['total_remaining'] : 0;

// Calculate total paid
$total_paid = $grand_total - $total_remaining;

// Count total loans
$count_result = $conn->query("SELECT COUNT(*) AS loan_count FROM loans");
$count_row = $count_result->fetch_assoc();
$loan_count = $count_row['loan_count'];

// Count active loans (with remaining balance)
$active_result = $conn->query("SELECT COUNT(*) AS active_count FROM loans WHERE remaining_balance > 0");
$active_row = $active_result->fetch_assoc();
$active_count = $active_row['active_count'];

// Get recent payments
$recent_payments = $conn->query("SELECT lp.payment_amount, lp.payment_date, lp.customer_name 
                                FROM loan_payments lp 
                                ORDER BY lp.payment_date DESC LIMIT 5");

// Get current page name for proper redirects
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Management System</title>
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

        .dark {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --primary-light: rgba(59, 130, 246, 0.2);
            --secondary: #a855f7;
            --success: #34d399;
            --warning: #fbbf24;
            --danger: #f87171;
            --background: #0f172a;
            --foreground: #e2e8f0;
            --card: #1e293b;
            --border: #334155;
            --muted: #64748b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(to bottom right, var(--background), #f1f5f9);
            color: var(--foreground);
            min-height: 100vh;
            transition: background-color 0.3s, color 0.3s;
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
            transition: background-color 0.3s;
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
            transition: transform 0.2s, background-color 0.3s;
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
            transition: background-color 0.3s;
        }

        .card-header {
            background-color: var(--primary-light);
            padding: 1.25rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.3s;
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
            transition: border-color 0.2s, background-color 0.3s, color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
        }

        .search-container {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .search-container .form-control {
            flex: 1;
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
            transition: background-color 0.3s;
        }

        tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        .dark tr:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .amount {
            font-weight: 600;
            color: var(--success);
        }

        .remaining {
            font-weight: 600;
        }

        .remaining.paid {
            color: var(--success);
        }

        .remaining.partial {
            color: var(--warning);
        }

        .remaining.unpaid {
            color: var(--danger);
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
            background-color: rgba(0, 0, 0, 0.05);
        }

        .dark .btn-outline:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .btn-block {
            width: 100%;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
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

        .badge-warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning);
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

        .alert {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border-left: 4px solid var(--warning);
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .recent-payments {
            margin-top: 1.5rem;
        }

        .payment-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }

        .payment-item:last-child {
            border-bottom: none;
        }

        .payment-info {
            display: flex;
            flex-direction: column;
        }

        .payment-name {
            font-weight: 500;
        }

        .payment-date {
            font-size: 0.75rem;
            color: var(--muted);
        }

        .payment-amount {
            font-weight: 600;
            color: var(--success);
        }

        .theme-toggle {
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem;
            border-radius: 9999px;
            transition: background-color 0.2s;
        }

        .theme-toggle:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }

        .dark .theme-toggle:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        @media (max-width: 768px) {
            .container {
                width: 95%;
            }
            
            .header-actions {
                display: none;
            }
            
            .mobile-menu-button {
                display: block;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }

        .progress-container {
            width: 100%;
            height: 8px;
            background-color: var(--border);
            border-radius: 9999px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-bar {
            height: 100%;
            background-color: var(--primary);
            border-radius: 9999px;
            transition: width 0.5s ease;
        }

        .mobile-menu {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }

        .mobile-menu.active {
            opacity: 1;
            pointer-events: auto;
        }

        .mobile-menu-content {
            position: absolute;
            top: 0;
            right: 0;
            width: 75%;
            max-width: 300px;
            height: 100%;
            background-color: var(--card);
            padding: 2rem 1rem;
            transform: translateX(100%);
            transition: transform 0.3s;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .mobile-menu.active .mobile-menu-content {
            transform: translateX(0);
        }

        .mobile-menu-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            color: var(--muted);
            font-size: 1.5rem;
            cursor: pointer;
        }

        .mobile-menu-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: var(--radius);
            color: var(--foreground);
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .mobile-menu-item:hover {
            background-color: var(--primary-light);
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
            <a href="daily_payment.php" class="btn btn-outline">
                <i class="fas fa-hand-holding-usd"></i>
                <span>Daily Payments</span>
            </a>
            <a href="loan_history.php" class="btn btn-outline">
                <i class="fas fa-history"></i>
                <span>Loan History</span>
            </a>
            <a href="reports.php" class="btn btn-outline">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <button id="themeToggle" class="theme-toggle">
                <i class="fas fa-moon"></i>
            </button>
        </div>
        <button class="btn btn-outline mobile-menu-button" style="display: none;">
            <i class="fas fa-bars"></i>
        </button>
    </header>

    <div class="mobile-menu">
        <div class="mobile-menu-content">
            <button class="mobile-menu-close">
                <i class="fas fa-times"></i>
            </button>
            <a href="index.php" class="mobile-menu-item">
                <i class="fas fa-tachometer-alt"></i>
               
            </a>
            <a href="daily_payment.php" class="mobile-menu-item">
                <i class="fas fa-hand-holding-usd"></i>
                <span>Daily Payments</span>
            </a>

            <a href="daily_payment.php" class="mobile-menu-item">
                <i class="fas fa-hand-holding-usd"></i>
                <span>Daily Payments</span>
            </a>
            <a href="loan_history.php" class="mobile-menu-item">
                <i class="fas fa-history"></i>
                <span>Loan History</span>
            </a>
            <a href="reports.php" class="mobile-menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong>Success!</strong> New loan has been added successfully.
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong>Success!</strong> Loan has been updated successfully.
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-warning">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Success!</strong> Loan has been deleted successfully.
            </div>
        </div>
        <?php endif; ?>

        <h1 class="page-title">
            <i class="fas fa-file-invoice-dollar"></i>
            Loan Management
        </h1>

        <!-- Statistics Summary -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-title">
                    <i class="fas fa-money-bill-wave"></i>
                    Total Loans
                </div>
                <div class="stat-value">$<?php echo number_format($grand_total, 2); ?></div>
                <div class="progress-container">
                    <div class="progress-bar" style="width: 100%;"></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-title">
                    <i class="fas fa-hand-holding-usd"></i>
                    Total Paid
                </div>
                <div class="stat-value">$<?php echo number_format($total_paid, 2); ?></div>
                <div class="progress-container">
                    <div class="progress-bar" style="width: <?php echo $grand_total > 0 ? ($total_paid / $grand_total * 100) : 0; ?>%;"></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-title">
                    <i class="fas fa-balance-scale"></i>
                    Remaining Balance
                </div>
                <div class="stat-value">$<?php echo number_format($total_remaining, 2); ?></div>
                <div class="progress-container">
                    <div class="progress-bar" style="width: <?php echo $grand_total > 0 ? ($total_remaining / $grand_total * 100) : 0; ?>%;"></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-title">
                    <i class="fas fa-users"></i>
                    Active Loans
                </div>
                <div class="stat-value"><?php echo $active_count; ?> / <?php echo $loan_count; ?></div>
                <div class="progress-container">
                    <div class="progress-bar" style="width: <?php echo $loan_count > 0 ? ($active_count / $loan_count * 100) : 0; ?>%;"></div>
                </div>
            </div>
        </div>

        <div class="grid">
            <!-- Loan Form -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <?php if ($edit_id): ?>
                                <i class="fas fa-edit"></i> Edit Loan
                            <?php else: ?>
                                <i class="fas fa-plus-circle"></i> Add New Loan
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-content">
                        <form method="post" action="<?php echo $current_page; ?>">
                            <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
                            
                            <div class="form-group">
                                <label class="form-label">Customer Name</label>
                                <input type="text" name="customer_name" class="form-control" value="<?php echo $edit_customer; ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Total Loan Amount</label>
                                <div style="position: relative;">
                                    <span style="position: absolute; left: 10px; top: 9px; color: var(--muted);">$</span>
                                    <input type="number" name="total_loan" step="0.01" min="0.01" class="form-control" style="padding-left: 25px;" value="<?php echo $edit_amount; ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Loan Date</label>
                                <input type="date" name="loan_date" class="form-control" value="<?php echo $edit_date ?: date('Y-m-d'); ?>" required>
                            </div>

                            <?php if ($edit_id): ?>
                                <div class="button-group" style="margin-top: 1.5rem;">
                                    <button type="submit" name="update_loan" class="btn btn-primary btn-block">
                                        <i class="fas fa-save"></i> Update Loan
                                    </button>
                                    <a href="<?php echo $current_page; ?>" class="btn btn-outline btn-block">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                            <?php else: ?>
                                <button type="submit" name="add_loan" class="btn btn-primary btn-block" style="margin-top: 1.5rem;">
                                    <i class="fas fa-plus"></i> Add Loan
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Recent Payments -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-history"></i>
                            Recent Payments
                        </div>
                        <a href="daily_payment.php" class="btn btn-outline">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                    <div class="card-content">
                        <?php if ($recent_payments && $recent_payments->num_rows > 0): ?>
                            <div class="recent-payments">
                                <?php while ($payment = $recent_payments->fetch_assoc()): ?>
                                    <div class="payment-item">
                                        <div class="payment-info">
                                            <span class="payment-name"><?php echo htmlspecialchars($payment['customer_name']); ?></span>
                                            <span class="payment-date"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></span>
                                        </div>
                                        <span class="payment-amount">$<?php echo number_format($payment['payment_amount'], 2); ?></span>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state" style="padding: 1.5rem;">
                                <div class="empty-title">No recent payments</div>
                                <div class="empty-description">Payments will appear here as they are made.</div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="action-buttons" style="margin-top: 1rem;">
                            <a href="daily_payment.php" class="btn btn-outline btn-block">
                                <i class="fas fa-hand-holding-usd"></i> Record Payment
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loan List -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-list"></i>
                        Loan Records
                        <span class="badge badge-primary"><?php echo $loan_count; ?> loans</span>
                    </div>
                    <div>
                        <a href="export_loans.php" class="btn btn-outline">
                            <i class="fas fa-file-export"></i> Export
                        </a>
                    </div>
                </div>
                <div class="card-content" style="padding: 1.5rem;">
                    <div class="search-container">
                        <form method="post" style="display: flex; width: 100%; gap: 0.5rem;">
                            <input type="text" name="search_query" class="form-control" placeholder="Search by customer name...">
                            <button type="submit" name="search" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>

                    <?php if ($result->num_rows > 0): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer</th>
                                        <th>Loan Amount</th>
                                        <th>Remaining</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($loan = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($loan['id']); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($loan['customer_name']); ?></strong>
                                            </td>
                                            <td class="amount">
                                                $<?php echo number_format($loan['total_loan'], 2); ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $remaining_percent = $loan['total_loan'] > 0 ? ($loan['remaining_balance'] / $loan['total_loan']) * 100 : 0;
                                                $status_class = 'paid';
                                                if ($remaining_percent > 0) {
                                                    $status_class = $remaining_percent < 50 ? 'partial' : 'unpaid';
                                                }
                                                ?>
                                                <span class="remaining <?php echo $status_class; ?>">
                                                    $<?php echo number_format($loan['remaining_balance'], 2); ?>
                                                </span>
                                                <div class="progress-container" style="margin-top: 0.25rem; height: 4px;">
                                                    <div class="progress-bar" style="width: <?php echo 100 - $remaining_percent; ?>%;"></div>
                                                </div>
                                            </td>
                                            <td class="date">
                                                <?php echo date('M d, Y', strtotime($loan['loan_date'])); ?>
                                            </td>
                                            <td>
                                                <div class="button-group">
                                                    <a href="<?php echo $current_page; ?>?edit=<?php echo $loan['id']; ?>" class="btn btn-warning">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="<?php echo $current_page; ?>?delete=<?php echo $loan['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this loan? This action cannot be undone.');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    <a href="loan_details.php?id=<?php echo $loan['id']; ?>" class="btn btn-outline">
                                                        <i class="fas fa-info-circle"></i>
                                                    </a>
                                                    <a href="daily_payment.php?loan_id=<?php echo $loan['id']; ?>" class="btn btn-success">
                                                        <i class="fas fa-hand-holding-usd"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                    <tr class="grand-total-row">
                                        <td colspan="2" style="text-align: right;">Grand Total:</td>
                                        <td>$<?php echo number_format($grand_total, 2); ?></td>
                                        <td>$<?php echo number_format($total_remaining, 2); ?></td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                            <div class="empty-title">No loans found</div>
                            <div class="empty-description">
                                <?php if (isset($_POST['search'])): ?>
                                    No loans match your search criteria. Try a different search term.
                                <?php else: ?>
                                    Add your first loan to get started.
                                <?php endif; ?>
                            </div>
                            <?php if (isset($_POST['search'])): ?>
                                <a href="<?php echo $current_page; ?>" class="btn btn-outline">
                                    <i class="fas fa-arrow-left"></i> Clear Search
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="action-buttons" style="justify-content: center; margin-top: 2rem;">
            <a href="daily_payment.php" class="btn btn-primary">
                <i class="fas fa-hand-holding-usd"></i> Daily Payments
            </a>
            <a href="loan_history.php" class="btn btn-success">
                <i class="fas fa-history"></i> Loan History
            </a>
            <a href="reports.php" class="btn btn-outline">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
            <a href="index.php" class="btn btn-outline">
                <i class="fas fa-tachometer-alt"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <script>
        
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.display = 'none';
            });
        }, 5000);

       
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const icon = themeToggle.querySelector('i');

        if (localStorage.getItem('theme') === 'dark') {
            body.classList.add('dark');
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        }

        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark');
            
            if (body.classList.contains('dark')) {
                localStorage.setItem('theme', 'dark');
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            } else {
                localStorage.setItem('theme', 'light');
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            }
        });

      
        const mobileMenuButton = document.querySelector('.mobile-menu-button');
        const mobileMenu = document.querySelector('.mobile-menu');
        const mobileMenuClose = document.querySelector('.mobile-menu-close');

        if (mobileMenuButton) {
            mobileMenuButton.addEventListener('click', () => {
                mobileMenu.classList.add('active');
            });
        }

        if (mobileMenuClose) {
            mobileMenuClose.addEventListener('click', () => {
                mobileMenu.classList.remove('active');
            });
        }

        
        mobileMenu.addEventListener('click', (e) => {
            if (e.target === mobileMenu) {
                mobileMenu.classList.remove('active');
            }
        });
    </script>
</body>

</html>
<?php
$conn->close();
?>