<?php
include 'db_connect.php';

// Start session if not already started (for flash messages)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Handle search and filtering
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build the WHERE clause for filtering
$where_clauses = [];
$params = [];
$param_types = "";

if (!empty($search_query)) {
    $where_clauses[] = "l.customer_name LIKE ?";
    $params[] = "%$search_query%";
    $param_types .= "s";
}

if (!empty($filter_status)) {
    if ($filter_status == 'paid') {
        $where_clauses[] = "l.remaining_balance = 0";
    } elseif ($filter_status == 'partial') {
        $where_clauses[] = "l.remaining_balance > 0 AND l.remaining_balance < l.total_loan";
    } elseif ($filter_status == 'unpaid') {
        $where_clauses[] = "l.remaining_balance = l.total_loan";
    }
}

if (!empty($date_from)) {
    $where_clauses[] = "l.loan_date >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if (!empty($date_to)) {
    $where_clauses[] = "l.loan_date <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

// Build the WHERE SQL
$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Fetch all loans with payments
$query = "
    SELECT 
        l.id AS loan_id, 
        l.customer_name, 
        l.total_loan, 
        l.remaining_balance,
        l.loan_date,
        lp.id AS payment_id,
        lp.payment_amount, 
        lp.payment_date 
    FROM loans l 
    LEFT JOIN loan_payments lp ON l.id = lp.loan_id 
    $where_sql
    ORDER BY l.customer_name, l.id, lp.payment_date ASC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

// Store loan details in an associative array
$loans = [];
$total_loan_amount = 0;
$total_remaining = 0;
$total_paid = 0;
$total_loans = 0;
$loans_with_payments = 0;

while ($row = $result->fetch_assoc()) {
    $loanId = $row['loan_id'];

    if (!isset($loans[$loanId])) {
        $loans[$loanId] = [
            'customer_name' => $row['customer_name'],
            'total_loan' => $row['total_loan'],
            'remaining_balance' => $row['remaining_balance'],
            'loan_date' => $row['loan_date'],
            'payments' => [],
            'total_paid' => 0,
            'payment_count' => 0
        ];
        
        $total_loan_amount += $row['total_loan'];
        $total_remaining += $row['remaining_balance'];
        $total_loans++;
    }

    // Check if the payment details exist before adding them
    if (!empty($row['payment_id']) && !empty($row['payment_amount']) && !empty($row['payment_date'])) {
        $loans[$loanId]['payments'][] = [
            'id' => $row['payment_id'],
            'amount' => $row['payment_amount'],
            'date' => $row['payment_date']
        ];
        $loans[$loanId]['total_paid'] += $row['payment_amount'];
        $loans[$loanId]['payment_count']++;
        
        $total_paid += $row['payment_amount'];
    }
}

// Count loans with payments
foreach ($loans as $loan) {
    if (count($loan['payments']) > 0) {
        $loans_with_payments++;
    }
}

// Calculate statistics
$avg_loan_amount = $total_loans > 0 ? $total_loan_amount / $total_loans : 0;
$payment_percentage = $total_loans > 0 ? ($loans_with_payments / $total_loans) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detailed Loan Records</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: rgba(37, 99, 235, 0.1);
            --primary-border: rgba(37, 99, 235, 0.2);
            --secondary: #9333ea;
            --secondary-dark: #7e22ce;
            --destructive: #ef4444;
            --destructive-dark: #dc2626;
            --success: #10b981;
            --success-dark: #059669;
            --warning: #f59e0b;
            --warning-dark: #d97706;
            --muted: #e5e7eb;
            --muted-foreground: #6b7280;
            --accent: #f3f4f6;
            --accent-foreground: #1f2937;
            --background: #ffffff;
            --foreground: #1f2937;
            --card: #ffffff;
            --card-foreground: #1f2937;
            --border: #e5e7eb;
            --input: #e5e7eb;
            --ring: #2563eb;
            --radius: 0.5rem;
            --header-height: 4rem;
            --transition-duration: 0.2s;
        }

        .dark {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --primary-light: rgba(59, 130, 246, 0.1);
            --primary-border: rgba(59, 130, 246, 0.2);
            --secondary: #a855f7;
            --secondary-dark: #9333ea;
            --destructive: #f87171;
            --destructive-dark: #ef4444;
            --success: #34d399;
            --success-dark: #10b981;
            --warning: #fbbf24;
            --warning-dark: #f59e0b;
            --muted: #374151;
            --muted-foreground: #9ca3af;
            --accent: #1f2937;
            --accent-foreground: #f9fafb;
            --background: #111827;
            --foreground: #f9fafb;
            --card: #1f2937;
            --card-foreground: #f9fafb;
            --border: #374151;
            --input: #374151;
            --ring: #3b82f6;
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
            transition: background-color var(--transition-duration), color var(--transition-duration);
        }

        .dark body {
            background: linear-gradient(to bottom right, #0f172a, #1e293b);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: var(--header-height);
            padding: 0 1.5rem;
            background-color: var(--background);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            padding: 0.5rem 0;
            font-size: 0.875rem;
        }

        .breadcrumb a {
            color: var(--muted-foreground);
            text-decoration: none;
            transition: color 0.2s;
        }

        .breadcrumb a:hover {
            color: var(--foreground);
        }

        .breadcrumb-separator {
            color: var(--muted-foreground);
        }

        .breadcrumb-current {
            font-weight: 500;
            color: var(--foreground);
        }

        h1, h2, h3 {
            color: var(--foreground);
        }

        h1 {
            font-size: 1.875rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }

        .stat-title {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--muted-foreground);
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

        .stat-footer {
            margin-top: 0.5rem;
            font-size: 0.75rem;
            color: var(--muted-foreground);
        }

        .card {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
            overflow: hidden;
            transition: box-shadow 0.2s;
            margin-bottom: 2rem;
        }

        .card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            padding: 1rem 1.5rem;
            background: var(--primary-light);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--foreground);
        }

        .card-content {
            padding: 1.5rem;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background-color: var(--accent);
            border-radius: var(--radius);
            border: 1px solid var(--border);
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .filter-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--muted-foreground);
        }

        .filter-select {
            padding: 0.375rem 0.5rem;
            font-size: 0.75rem;
            border-radius: var(--radius);
            border: 1px solid var(--input);
            background-color: var(--background);
            color: var(--foreground);
        }

        .filter-date {
            padding: 0.375rem 0.5rem;
            font-size: 0.75rem;
            border-radius: var(--radius);
            border: 1px solid var(--input);
            background-color: var(--background);
            color: var(--foreground);
            width: 8rem;
        }

        .filter-input {
            padding: 0.375rem 0.5rem;
            font-size: 0.75rem;
            border-radius: var(--radius);
            border: 1px solid var(--input);
            background-color: var(--background);
            color: var(--foreground);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1.5;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            cursor: pointer;
            user-select: none;
            border: 1px solid transparent;
            border-radius: var(--radius);
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            text-decoration: none;
        }

        .btn-primary {
            color: white;
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-outline {
            color: var(--foreground);
            background-color: transparent;
            border-color: var(--border);
        }

        .btn-outline:hover {
            background-color: var(--accent);
            color: var(--accent-foreground);
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .loan-card {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: box-shadow 0.2s;
        }

        .loan-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }

        .loan-header {
            padding: 1rem 1.5rem;
            background: var(--primary-light);
            border-bottom: 1px solid var(--border);
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: space-between;
        }

        .loan-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--foreground);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .loan-details {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .loan-detail {
            display: flex;
            flex-direction: column;
        }

        .loan-detail-label {
            font-size: 0.75rem;
            color: var(--muted-foreground);
            margin-bottom: 0.25rem;
        }

        .loan-detail-value {
            font-weight: 600;
            color: var(--foreground);
        }

        .loan-content {
            padding: 1.5rem;
        }

        .payment-timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline-line {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0.5rem;
            width: 2px;
            background-color: var(--border);
        }

        .payment-item {
            position: relative;
            padding-bottom: 1.5rem;
        }

        .payment-item:last-child {
            padding-bottom: 0;
        }

        .payment-dot {
            position: absolute;
            left: -2rem;
            top: 0;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background-color: var(--primary);
            border: 2px solid var(--background);
            z-index: 1;
        }

        .payment-content {
            background-color: var(--accent);
            border-radius: var(--radius);
            padding: 1rem;
            border: 1px solid var(--border);
        }

        .payment-date {
            font-size: 0.875rem;
            color: var(--muted-foreground);
            margin-bottom: 0.5rem;
        }

        .payment-amount {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--foreground);
        }

        .no-payment {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: var(--muted-foreground);
            font-style: italic;
            text-align: center;
            background-color: var(--accent);
            border-radius: var(--radius);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 500;
            line-height: 1;
            border-radius: 9999px;
            white-space: nowrap;
        }

        .badge-secondary {
            background-color: var(--secondary);
            color: white;
        }

        .badge-outline {
            background-color: transparent;
            color: var(--foreground);
            border: 1px solid var(--border);
        }

        .badge-success {
            background-color: var(--success);
            color: white;
        }

        .badge-warning {
            background-color: var(--warning);
            color: white;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 4rem 2rem;
            text-align: center;
            background-color: var(--card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
        }

        .empty-icon {
            font-size: 3rem;
            color: var(--muted);
            margin-bottom: 1rem;
        }

        .empty-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--foreground);
            margin-bottom: 0.5rem;
        }

        .empty-description {
            color: var(--muted-foreground);
            margin-bottom: 1.5rem;
        }

        .theme-toggle {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--foreground);
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 9999px;
            transition: background-color 0.2s;
        }

        .theme-toggle:hover {
            background-color: var(--accent);
        }

        .progress-container {
            width: 100%;
            height: 0.5rem;
            background-color: var(--muted);
            border-radius: 9999px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-bar {
            height: 100%;
            background-color: var(--primary);
            border-radius: 9999px;
        }

        @media (max-width: 768px) {
            .header {
                padding: 0 1rem;
            }

            .header-title span {
                display: none;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            }

            .loan-header {
                flex-direction: column;
                gap: 0.75rem;
            }

            .loan-details {
                flex-direction: column;
                gap: 0.75rem;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.3s ease-out forwards;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-title">
            <i class="fas fa-file-invoice-dollar" style="color: var(--primary);"></i>
            <span>Loan Management System</span>
        </div>
        <div class="header-actions">
            <a href="index.php" class="btn btn-outline">
                <i class="fas fa-tachometer-alt"></i>
                <span>Back to Dashboard</span>
            </a>
            <a href="manage_loan.php" class="btn btn-outline">
                <i class="fas fa-money-bill-wave"></i>
                <span>Manage Loans</span>
            </a>
            <button id="theme-toggle" class="theme-toggle" aria-label="Toggle theme">
                <i class="fas fa-moon"></i>
            </button>
        </div>
    </header>

    <div class="container">
        <div class="breadcrumb">
            <a href="index.php">Dashboard</a>
            <span class="breadcrumb-separator">/</span>
            <span class="breadcrumb-current">Detailed Loan Records</span>
        </div>

        <h1>Loan Details & Payment History</h1>

        <!-- Statistics Section -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-title">
                    <i class="fas fa-file-invoice-dollar" style="color: var(--primary);"></i>
                    Total Loans
                </div>
                <div class="stat-value"><?php echo number_format($total_loans); ?></div>
                <div class="stat-footer">Active loans</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">
                    <i class="fas fa-dollar-sign" style="color: var(--success);"></i>
                    Total Amount
                </div>
                <div class="stat-value">$<?php echo number_format($total_loan_amount, 2); ?></div>
                <div class="stat-footer">Loan value</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">
                    <i class="fas fa-hand-holding-usd" style="color: var(--warning);"></i>
                    Total Paid
                </div>
                <div class="stat-value">$<?php echo number_format($total_paid, 2); ?></div>
                <div class="stat-footer"><?php echo number_format(($total_paid / $total_loan_amount) * 100, 1); ?>% of total</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">
                    <i class="fas fa-chart-line" style="color: var(--secondary);"></i>
                    Average Loan
                </div>
                <div class="stat-value">$<?php echo number_format($avg_loan_amount, 2); ?></div>
                <div class="stat-footer"><?php echo number_format($payment_percentage, 1); ?>% with payments</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-filter"></i> Filter Loans
                </h2>
            </div>
            <div class="card-content">
                <form method="get" class="filters">
                    <div class="filter-group">
                        <label class="filter-label">Customer Name</label>
                        <input type="text" name="search" class="filter-input" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search...">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="filter-select">
                            <option value="" <?php echo $filter_status === '' ? 'selected' : ''; ?>>All Status</option>
                            <option value="paid" <?php echo $filter_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="partial" <?php echo $filter_status === 'partial' ? 'selected' : ''; ?>>Partial</option>
                            <option value="unpaid" <?php echo $filter_status === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">From Date</label>
                        <input type="text" name="date_from" id="date-from" class="filter-date" value="<?php echo $date_from; ?>" placeholder="From">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">To Date</label>
                        <input type="text" name="date_to" id="date-to" class="filter-date" value="<?php echo $date_to; ?>" placeholder="To">
                    </div>
                    
                    <div class="filter-group" style="align-self: flex-end;">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="<?php echo basename($_SERVER['PHP_SELF']); ?>" class="btn btn-sm btn-outline">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($loans)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3 class="empty-title">No loan records found</h3>
                <p class="empty-description">
                    <?php if (!empty($search_query) || !empty($filter_status) || !empty($date_from) || !empty($date_to)): ?>
                        Try adjusting your search filters to find what you're looking for.
                    <?php else: ?>
                        There are no loans in the system yet. Start by adding a new loan.
                    <?php endif; ?>
                </p>
                <a href="manage_loan.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Loan
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($loans as $loanId => $loan): ?>
                <?php
                    // Determine status badge
                    $badgeClass = "badge-secondary";
                    $statusText = "Unpaid";
                    if ($loan['remaining_balance'] == 0) {
                        $badgeClass = "badge-success";
                        $statusText = "Paid";
                    } elseif ($loan['remaining_balance'] < $loan['total_loan']) {
                        $badgeClass = "badge-warning";
                        $statusText = "Partial";
                    }
                    
                    // Calculate payment progress percentage
                    $paymentProgress = 0;
                    if ($loan['total_loan'] > 0) {
                        $paymentProgress = (($loan['total_loan'] - $loan['remaining_balance']) / $loan['total_loan']) * 100;
                    }
                ?>
                <div class="loan-card fade-in">
                    <div class="loan-header">
                        <div class="loan-title">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($loan['customer_name']); ?>
                            <span class="badge <?php echo $badgeClass; ?>"><?php echo $statusText; ?></span>
                        </div>
                        <div class="loan-details">
                            <div class="loan-detail">
                                <span class="loan-detail-label">Loan Amount</span>
                                <span class="loan-detail-value">$<?php echo number_format($loan['total_loan'], 2); ?></span>
                            </div>
                            <div class="loan-detail">
                                <span class="loan-detail-label">Remaining Balance</span>
                                <span class="loan-detail-value">$<?php echo number_format($loan['remaining_balance'], 2); ?></span>
                            </div>
                            <div class="loan-detail">
                                <span class="loan-detail-label">Loan Date</span>
                                <span class="loan-detail-value"><?php echo date('M d, Y', strtotime($loan['loan_date'])); ?></span>
                            </div>
                            <div class="loan-detail">
                                <span class="loan-detail-label">Payment Progress</span>
                                <div class="progress-container">
                                    <div class="progress-bar" style="width: <?php echo $paymentProgress; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="loan-content">
                        <h3>
                            <i class="fas fa-history"></i> 
                            Payment History
                            <?php if (count($loan['payments']) > 0): ?>
                                <small>(<?php echo count($loan['payments']); ?> payments)</small>
                            <?php endif; ?>
                        </h3>
                        
                        <?php if (count($loan['payments']) > 0): ?>
                            <div class="payment-timeline">
                                <div class="timeline-line"></div>
                                <?php foreach ($loan['payments'] as $payment): ?>
                                    <div class="payment-item">
                                        <div class="payment-dot"></div>
                                        <div class="payment-content">
                                            <div class="payment-date">
                                                <i class="fas fa-calendar-alt"></i> 
                                                <?php echo date('F d, Y', strtotime($payment['date'])); ?>
                                            </div>
                                            <div class="payment-amount">
                                                <i class="fas fa-dollar-sign"></i> 
                                                <?php echo number_format($payment['amount'], 2); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-payment">
                                <div>
                                    <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                                    <p>No payments have been made for this loan yet.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div style="text-align: right; margin-top: 1rem;">
                            <a href="daily_payment.php?loan_id=<?php echo $loanId; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Add Payment
                            </a>
                            <a href="manage_loan.php?edit=<?php echo $loanId; ?>" class="btn btn-outline btn-sm">
                                <i class="fas fa-edit"></i> Edit Loan
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        // Initialize date pickers
        flatpickr("#date-from", {
            dateFormat: "Y-m-d",
            onChange: function(selectedDates, dateStr, instance) {
                dateTo.set('minDate', dateStr);
            }
        });
        
        const dateTo = flatpickr("#date-to", {
            dateFormat: "Y-m-d"
        });

        // Dark mode toggle
        const themeToggle = document.getElementById('theme-toggle');
        const htmlElement = document.documentElement;
        
        // Check for saved theme preference or respect OS preference
        const savedTheme = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
            htmlElement.classList.add('dark');
            themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        }
        
        themeToggle.addEventListener('click', function() {
            htmlElement.classList.toggle('dark');
            
            if (htmlElement.classList.contains('dark')) {
                localStorage.setItem('theme', 'dark');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            } else {
                localStorage.setItem('theme', 'light');
                themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
            }
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>