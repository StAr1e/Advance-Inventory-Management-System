<?php
include 'db_connect.php'; // Include database connection

// Fetch loan history from sales table
$query = "SELECT customer_name, loan AS loan_amount, date FROM daily_sales WHERE loan > 0 ORDER BY date DESC";
$result = $conn->query($query);

// Calculate statistics
$total_loans = 0;
$total_amount = 0;
$customers = [];

// First pass to calculate statistics
if ($result->num_rows > 0) {
    $temp_result = $result;
    while ($row = $temp_result->fetch_assoc()) {
        $total_loans++;
        $total_amount += $row['loan_amount'];
        if (!in_array($row['customer_name'], $customers)) {
            $customers[] = $row['customer_name'];
        }
    }
    // Reset result pointer
    $result->data_seek(0);
}

$unique_customers = count($customers);
$avg_loan = $total_loans > 0 ? $total_amount / $total_loans : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan History</title>
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
            color: var(--primary);
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
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-success {
            background-color: var(--success);
            color: white;
            border: none;
        }

        .btn-success:hover {
            background-color: #0d9668;
        }

        .btn-warning {
            background-color: var(--warning);
            color: white;
            border: none;
        }

        .btn-warning:hover {
            background-color: #d97706;
        }

        .btn-outline {
            background-color: transparent;
            color: var(--foreground);
            border: 1px solid var(--border);
        }

        .btn-outline:hover {
            background-color: #f8fafc;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.5rem;
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
            <i class="fas fa-file-invoice-dollar"></i>
            <span>Loan Management System</span>
        </div>
        <div class="header-actions">
            <a href="index.php" class="btn btn-outline">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="manage_loan.php" class="btn btn-outline">
                <i class="fas fa-money-bill-wave"></i>
                <span>Manage Loans</span>
            </a>
            <a href="daily_payment.php" class="btn btn-outline">
                <i class="fas fa-calendar-check"></i>
                <span>Daily Payments</span>
            </a>
        </div>
    </header>

    <div class="container">
        <h1 class="page-title">
            <i class="fas fa-history"></i>
            Loan History
        </h1>

        <!-- Statistics Summary -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-title">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Total Loans
                </div>
                <div class="stat-value"><?php echo number_format($total_loans); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">
                    <i class="fas fa-dollar-sign"></i>
                    Total Amount
                </div>
                <div class="stat-value">$<?php echo number_format($total_amount, 2); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">
                    <i class="fas fa-users"></i>
                    Unique Customers
                </div>
                <div class="stat-value"><?php echo number_format($unique_customers); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">
                    <i class="fas fa-calculator"></i>
                    Average Loan
                </div>
                <div class="stat-value">$<?php echo number_format($avg_loan, 2); ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-list"></i>
                    Loan Records
                    <span class="badge badge-primary"><?php echo $total_loans; ?> entries</span>
                </div>
            </div>
            <div class="card-content">
                <?php if ($result->num_rows > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer Name</th>
                                    <th>Loan Amount</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-user"></i>
                                            <?php echo htmlspecialchars($row['customer_name']); ?>
                                        </td>
                                        <td class="amount">
                                            <i class="fas fa-dollar-sign"></i>
                                            <?php echo number_format($row['loan_amount'], 2); ?>
                                        </td>
                                        <td class="date">
                                            <i class="fas fa-calendar-alt"></i>
                                            <?php echo date('F d, Y', strtotime($row['date'])); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="empty-title">No loan history found</div>
                        <div class="empty-description">There are no loan records in the system yet.</div>
                    </div>
                <?php endif; ?>

                <div class="action-buttons">
                    <a href="manage_loan.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Loans
                    </a>
                    <a href="index.php" class="btn btn-outline">
                        <i class="fas fa-home"></i> Back to Dashboard
                    </a>
                    <a href="export_history.php" class="btn btn-success">
                        <i class="fas fa-file-export"></i> Export to Excel
                    </a>
                    <a href="print_history.php" class="btn btn-warning">
                        <i class="fas fa-print"></i> Print Report
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
?>