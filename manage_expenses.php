<?php
include 'db_connect.php';

// File to track last reset date
$last_reset_date_file = __DIR__ . "/last_expense_reset_date.txt";
$today = date("Y-m-d");

// Function to reset daily expenses
function resetDailyExpenses($conn)
{
    // Define backup directory
    $backupDir = __DIR__ . "/daily_expense_backups";

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

    // Backup `expenses` table to CSV before clearing
    $backupFile = $backupDir . "/expenses_backup_" . date("Y-m-d") . ".csv";
    $file = fopen($backupFile, "w");

    if (!$file) {
        die("Failed to create backup file. Please check folder permissions.");
    }

    // Add CSV headers
    fputcsv($file, ["id", "category", "amount", "description", "date"]);

    // Fetch and write data
    $query = "SELECT * FROM expenses";
    $result = $conn->query($query);
    $totalAmount = 0;

    while ($row = $result->fetch_assoc()) {
        fputcsv($file, $row);
        $totalAmount += $row['amount']; // Calculate total expenses
    }

    // Add total row to CSV
    fputcsv($file, ["", "Total", $totalAmount, "", ""]);

    fclose($file);

    // Clear the `expenses` table
    $conn->query("DELETE FROM expenses");

    // Reset AUTO_INCREMENT
    $conn->query("ALTER TABLE expenses AUTO_INCREMENT = 1");

    echo "Daily expenses reset successfully! Total expenses: $" . number_format($totalAmount, 2);
}

// Check if it's a new day and reset expenses
if (!file_exists($last_reset_date_file) || file_get_contents($last_reset_date_file) != $today) {
    resetDailyExpenses($conn);
    file_put_contents($last_reset_date_file, $today);
} else {
    echo "Daily expenses reset already done for today.";
}




if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add'])) {
        $category = $_POST['category'];
        $amount = $_POST['amount'];
        $description = $_POST['description'];
        $date = $_POST['date'];

        $stmt = $conn->prepare("INSERT INTO expenses (category, amount, description, date) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("siss", $category, $amount, $description, $date);
        $stmt->execute();
    }

    if (isset($_POST['update'])) {
        $id = $_POST['id'];
        $category = $_POST['category'];
        $amount = $_POST['amount'];
        $description = $_POST['description'];
        $date = $_POST['date'];

        $stmt = $conn->prepare("UPDATE expenses SET category=?, amount=?, description=?, date=? WHERE id=?");
        $stmt->bind_param("sissi", $category, $amount, $description, $date, $id);
        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM expenses WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$expense_query = $conn->query("SELECT * FROM expenses");
$total_query = $conn->query("SELECT SUM(amount) AS total_expense FROM expenses");
$total_expense = $total_query->fetch_assoc()['total_expense'] ?? 0;

$expense_to_edit = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $expense_result = $conn->query("SELECT * FROM expenses WHERE id=$id");
    $expense_to_edit = $expense_result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Management - KM Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- Add Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Add Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary: #3b82f6;
            --primary-foreground: #ffffff;
            --muted-foreground: #64748b;
            --border: #e2e8f0;
            --destructive: #ef4444;
            --destructive-foreground: #ffffff;
        }

        body {
            background-color: #f8fafc;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        }

        .card {
            background-color: #ffffff;
            border-radius: 0.5rem;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            margin-bottom: 1.5rem;
        }

        .card-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border);
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
            display: flex;
            align-items: center;
        }

        .card-description {
            font-size: 0.875rem;
            color: var(--muted-foreground);
            margin-top: 0.25rem;
        }

        .card-body {
            padding: 1.25rem;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.375rem;
            color: #1e293b;
        }

        .form-control {
            border-radius: 0.375rem;
            border: 1px solid var(--border);
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            width: 100%;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
            outline: none;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            border-radius: 0.375rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            transition: all 0.2s;
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary);
            color: var(--primary-foreground);
            border: none;
        }

        .btn-primary:hover {
            background-color: #2563eb;
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--border);
            color: #1e293b;
        }

        .btn-outline:hover {
            background-color: #f8fafc;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--muted-foreground);
            text-align: left;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
        }

        .table td {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .table tr:hover {
            background-color: #f8fafc;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .amount {
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Fira Mono', 'Droid Sans Mono', 'Source Code Pro', monospace;
        }

        .expense-card {
            background: linear-gradient(to right, #ef4444, #dc2626);
            color: white;
        }

        .expense-card .card-title,
        .expense-card .card-description {
            color: white;
        }

        .category-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
        }

        .category-badge-utilities {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .category-badge-food {
            background-color: #dcfce7;
            color: #166534;
        }

        .category-badge-transport {
            background-color: #fef9c3;
            color: #854d0e;
        }

        .category-badge-office {
            background-color: #f3e8ff;
            color: #6b21a8;
        }

        .category-badge-maintenance {
            background-color: #ffe4e6;
            color: #9f1239;
        }

        .category-badge-default {
            background-color: #f1f5f9;
            color: #475569;
        }
    </style>
</head>

<body>
    <div class="container mx-auto p-4">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Expense Management</h1>
            <a href="index.php" class="btn btn-outline">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Back to Dashboard
            </a>
        </div>

        <div class="grid md:grid-cols-3 gap-6 mb-6">
            <!-- Total Expenses Card -->
            <div class="expense-card card">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="card-description mb-1">Total Expenses</p>
                            <h3 class="text-3xl font-bold">$<?= number_format($total_expense, 2) ?></h3>
                        </div>
                        <div class="p-3 bg-white/10 rounded-full">
                            <i data-lucide="dollar-sign" class="w-6 h-6"></i>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <i data-lucide="calendar" class="w-4 h-4"></i>
                        <span class="text-sm">As of <?= date('F d, Y') ?></span>
                    </div>
                </div>
            </div>

            <!-- Add/Edit Expense Form -->
            <div class="card md:col-span-2">
                <div class="card-header">
                    <h5 class="card-title">
                        <i data-lucide="<?= $expense_to_edit ? 'edit' : 'plus' ?>" class="w-5 h-5 mr-2"></i>
                        <?= $expense_to_edit ? 'Edit Expense' : 'Add New Expense' ?>
                    </h5>
                    <p class="card-description">
                        <?= $expense_to_edit ? 'Update expense details' : 'Record a new expense' ?>
                    </p>
                </div>
                <div class="card-body">
                    <form method="POST" class="grid md:grid-cols-2 gap-4">
                        <input type="hidden" name="id" value="<?= $expense_to_edit['id'] ?? '' ?>">

                        <div class="form-group">
                            <label class="form-label" for="category">Category</label>
                            <input type="text" id="category" name="category" class="form-control"
                                value="<?= $expense_to_edit['category'] ?? '' ?>" placeholder="Enter expense category"
                                required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="amount">Amount</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                                <input type="number" id="amount" name="amount" class="form-control pl-8"
                                    value="<?= $expense_to_edit['amount'] ?? '' ?>" placeholder="Enter amount"
                                    step="0.01" required>
                            </div>
                        </div>

                        <div class="form-group md:col-span-2">
                            <label class="form-label" for="description">Description</label>
                            <input type="text" id="description" name="description" class="form-control"
                                value="<?= $expense_to_edit['description'] ?? '' ?>"
                                placeholder="Enter expense description">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="date">Date</label>
                            <input type="date" id="date" name="date" class="form-control"
                                value="<?= $expense_to_edit['date'] ?? date('Y-m-d') ?>" required>
                        </div>

                        <div class="md:col-span-2">
                            <button type="submit" name="<?= $expense_to_edit ? 'update' : 'add' ?>"
                                class="btn btn-primary w-full">
                                <i data-lucide="<?= $expense_to_edit ? 'save' : 'plus' ?>" class="w-4 h-4"></i>
                                <?= $expense_to_edit ? 'Update Expense' : 'Add Expense' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Expense List -->
        <div class="card">
            <div class="card-header">
                <div class="flex items-center justify-between">
                    <div>
                        <h5 class="card-title">
                            <i data-lucide="list" class="w-5 h-5 mr-2"></i>
                            Expense History
                        </h5>
                        <p class="card-description">View and manage all expenses</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="relative">
                            <i data-lucide="search"
                                class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4"></i>
                            <input type="text" class="form-control pl-9" placeholder="Search expenses..."
                                onkeyup="searchTable(this.value)">
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Category</th>
                                <th class="text-right">Amount</th>
                                <th>Description</th>
                                <th>Date</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $expense_query->fetch_assoc()):
                                // Define category class based on category name
                                $categoryClass = 'category-badge-default';
                                $category = strtolower($row['category']);

                                if (strpos($category, 'utilit') !== false) {
                                    $categoryClass = 'category-badge-utilities';
                                } elseif (strpos($category, 'food') !== false || strpos($category, 'meal') !== false) {
                                    $categoryClass = 'category-badge-food';
                                } elseif (strpos($category, 'transport') !== false || strpos($category, 'travel') !== false) {
                                    $categoryClass = 'category-badge-transport';
                                } elseif (strpos($category, 'office') !== false || strpos($category, 'supplies') !== false) {
                                    $categoryClass = 'category-badge-office';
                                } elseif (strpos($category, 'maintenance') !== false || strpos($category, 'repair') !== false) {
                                    $categoryClass = 'category-badge-maintenance';
                                }
                                ?>
                                <tr>
                                    <td class="font-medium"><?= $row['id'] ?></td>
                                    <td>
                                        <span class="category-badge <?= $categoryClass ?>">
                                            <?= htmlspecialchars($row['category']) ?>
                                        </span>
                                    </td>
                                    <td class="text-right amount">$<?= number_format($row['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($row['description']) ?></td>
                                    <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            <a href="?edit=<?= $row['id'] ?>" class="btn btn-outline btn-sm">
                                                <i data-lucide="edit" class="w-4 h-4"></i>
                                            </a>
                                            <a href="?delete=<?= $row['id'] ?>"
                                                class="btn btn-outline btn-sm text-red-600 hover:bg-red-50"
                                                onclick="return confirm('Are you sure you want to delete this expense?')">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50 font-semibold">
                                <td colspan="2" class="text-right">Total Expenses:</td>
                                <td class="text-right amount">$<?= number_format($total_expense, 2) ?></td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Search functionality
        function searchTable(query) {
            query = query.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        }
    </script>
</body>

</html>