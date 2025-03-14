<?php
include 'db_connect.php';

// Function to backup sales and reset daily sales
function resetDailySales($conn)
{
    $backup_dir = __DIR__ . "/backups/";

    // Ensure backup directory exists
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }

    $backup_file = "sales_backup_" . date("Y-m-d") . ".csv";
    $file_path = $backup_dir . $backup_file;

    // Fetch all sales records
    $sales_data = $conn->query("SELECT * FROM daily_sales");

    // Fetch totals
    $total_query = $conn->query("SELECT SUM(amount) AS total_amount, SUM(paid) AS total_paid, SUM(loan) AS total_loan FROM daily_sales");
    $totals = $total_query->fetch_assoc();

    // Open file for writing
    $file = fopen($file_path, "w");

    // Write column headers
    fputcsv($file, ["ID", "Customer", "Item", "Quantity", "Price", "Amount", "Paid", "Loan", "Date"]);

    // Write sales data
    while ($row = $sales_data->fetch_assoc()) {
        fputcsv($file, $row);
    }

    // Write totals at the end of the file
    fputcsv($file, ["", "TOTALS", "", "", "", $totals['total_amount'], $totals['total_paid'], $totals['total_loan'], ""]);

    // Close file
    fclose($file);

    // Clear the sales table
    $conn->query("DELETE FROM daily_sales");

    // Reset auto-increment ID (Optional)
    $conn->query("ALTER TABLE daily_sales AUTO_INCREMENT = 1");
}

// Check if it's a new day
$last_reset_date_file = __DIR__ . "/last_reset_date.txt";

if (!file_exists($last_reset_date_file) || file_get_contents($last_reset_date_file) != date("Y-m-d")) {
    resetDailySales($conn);
    file_put_contents($last_reset_date_file, date("Y-m-d"));
}

// Handle adding a new sale
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $customer_name = htmlspecialchars($_POST['customer_name']);
    $item_name = htmlspecialchars($_POST['item_name']);
    $quantity = (int) $_POST['quantity'];
    $price = (float) $_POST['price'];
    $amount = (float) $_POST['amount'];
    $paid = (float) $_POST['paid'];
    $loan = $amount - $paid; // Calculate loan amount
    $sale_date = htmlspecialchars($_POST['date']);

    // Insert sale into daily_sales table
    $stmt = $conn->prepare("INSERT INTO daily_sales (customer_name, item_name, quantity, price, amount, paid, loan, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssidddds", $customer_name, $item_name, $quantity, $price, $amount, $paid, $loan, $sale_date);
    $stmt->execute();

    if ($loan > 0) { // If there is a loan, update the loans table
        // Check if customer already has a loan
        $check_stmt = $conn->prepare("SELECT * FROM loans WHERE customer_name = ?");
        $check_stmt->bind_param("s", $customer_name);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Update existing loan record
            $new_total_loan = $row['total_loan'] + $loan;
            $new_remaining_balance = $row['remaining_balance'] + $loan;

            $update_stmt = $conn->prepare("UPDATE loans SET total_loan = ?, remaining_balance = ?, loan_date = ? WHERE customer_name = ?");
            $update_stmt->bind_param("ddss", $new_total_loan, $new_remaining_balance, $sale_date, $customer_name);
            $update_stmt->execute();
        } else {
            // Insert new loan record
            $insert_stmt = $conn->prepare("INSERT INTO loans (customer_name, total_loan, remaining_balance, loan_date) VALUES (?, ?, ?, ?)");
            $insert_stmt->bind_param("sdds", $customer_name, $loan, $loan, $sale_date);
            $insert_stmt->execute();
        }
    }

    header("Location: manage_loan.php");
    exit();
}

// Handle updating a sale
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_sale'])) {
    $id = $_POST['id'];
    $customer_name = $_POST['customer_name'];
    $item_name = $_POST['item_name'];
    $new_quantity = $_POST['quantity'];
    $price = $_POST['price'];
    $amount = $new_quantity * $price;
    $paid = $_POST['paid'];
    $loan = $amount - $paid;
    $date = $_POST['date'];

    // Get the old quantity before updating
    $old_sale = $conn->query("SELECT quantity FROM daily_sales WHERE id = $id")->fetch_assoc();
    $old_quantity = $old_sale['quantity'];

    // Update stock: Add back old quantity, then subtract the new quantity
    $conn->query("UPDATE stock SET quantity = quantity + $old_quantity WHERE item_name = '$item_name'");
    $conn->query("UPDATE stock SET quantity = quantity - $new_quantity WHERE item_name = '$item_name'");

    // Update sales record
    $conn->query("UPDATE daily_sales SET 
                  customer_name='$customer_name', item_name='$item_name', quantity='$new_quantity', 
                  price='$price', amount='$amount', paid='$paid', loan='$loan', date='$date' 
                  WHERE id=$id");

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle deleting a sale
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    // Get the item details before deletion
    $sale = $conn->query("SELECT item_name, quantity FROM daily_sales WHERE id = $id")->fetch_assoc();
    $item_name = $sale['item_name'];
    $quantity = $sale['quantity'];

    // Add the quantity back to stock
    $conn->query("UPDATE stock SET quantity = quantity + $quantity WHERE item_name = '$item_name'");

    // Delete sale entry
    $conn->query("DELETE FROM daily_sales WHERE id=$id");

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch sales data
$search = "";
$query = "SELECT * FROM daily_sales ORDER BY id DESC";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $_GET['search'];
    $query = "SELECT * FROM daily_sales WHERE 
              customer_name LIKE '%$search%' OR 
              item_name LIKE '%$search%' 
              ORDER BY id DESC";
}

$sales = $conn->query($query);
$total_query = $conn->query("SELECT SUM(amount) AS total_amount, SUM(paid) AS total_paid, SUM(loan) AS total_loan FROM daily_sales");
$totals = $total_query->fetch_assoc();

$conn->close();
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Management - KM Management System</title>
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

        .btn-danger {
            background-color: var(--destructive);
            color: var(--destructive-foreground);
            border: none;
        }

        .btn-danger:hover {
            background-color: #dc2626;
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
        }

        .table tr:hover {
            background-color: #f8fafc;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-success {
            background-color: #dcfce7;
            color: #166534;
        }

        .badge-warning {
            background-color: #fef9c3;
            color: #854d0e;
        }

        .search-container {
            position: relative;
            max-width: 300px;
        }

        .search-container i {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted-foreground);
            pointer-events: none;
        }

        .search-input {
            padding-left: 2.5rem !important;
        }

        .summary-value {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .summary-label {
            font-size: 0.875rem;
            color: var(--muted-foreground);
        }

        .amount-positive {
            color: #059669;
        }

        .amount-negative {
            color: #dc2626;
        }

        .icon-button {
            padding: 0.25rem;
            border-radius: 0.375rem;
            border: none;
            background: none;
            cursor: pointer;
            color: var(--muted-foreground);
        }

        .icon-button:hover {
            background-color: #f1f5f9;
            color: #1e293b;
        }
    </style>
</head>

<body>
    <div class="container mx-auto p-4">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Sales Management</h1>
            <a href="index.php" class="btn btn-outline">
                <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                Back to Dashboard
            </a>
        </div>

        <div class="grid md:grid-cols-3 gap-6 mb-6">
            <!-- Sales Form Card -->
            <div class="md:col-span-2">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title flex items-center">
                            <i data-lucide="shopping-cart" class="w-5 h-5 mr-2"></i>
                            Add New Sale
                        </h5>
                        <p class="card-description">Record a new sale transaction</p>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="grid md:grid-cols-2 gap-4">
                            <input type="hidden" name="id">
                            <div class="form-group">
                                <label class="form-label" for="customer_name">Customer Name</label>
                                <input type="text" class="form-control" id="customer_name" name="customer_name"
                                    placeholder="Enter customer name" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="item_name">Item Name</label>
                                <input type="text" class="form-control" id="item_name" name="item_name"
                                    placeholder="Enter item name" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="quantity">Quantity</label>
                                <input type="number" class="form-control" id="quantity" name="quantity"
                                    placeholder="Enter quantity" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="price">Price</label>
                                <input type="number" class="form-control" id="price" name="price" step="0.01"
                                    placeholder="Enter price" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="amount">Amount</label>
                                <input type="number" class="form-control" id="amount" name="amount" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="paid">Paid Amount</label>
                                <input type="number" class="form-control" id="paid" name="paid" step="0.01"
                                    placeholder="Enter paid amount" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="loan">Loan Amount</label>
                                <input type="number" class="form-control" id="loan" name="loan" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="date">Date</label>
                                <input type="date" class="form-control" id="date" name="date"
                                    value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="md:col-span-2">
                                <button type="submit" name="add_sale" id="addBtn" class="btn btn-primary w-full">
                                    <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                                    Add Sale
                                </button>
                                <button type="submit" name="update_sale" id="updateBtn" class="btn btn-primary w-full"
                                    style="display: none;">
                                    <i data-lucide="check" class="w-4 h-4 mr-2"></i>
                                    Update Sale
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Summary Card -->
            <div class="md:col-span-1">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title flex items-center">
                            <i data-lucide="pie-chart" class="w-5 h-5 mr-2"></i>
                            Daily Summary
                        </h5>
                        <p class="card-description">Today's sales overview</p>
                    </div>
                    <div class="card-body">
                        <div class="space-y-4">
                            <div>
                                <div class="summary-value">$<?= number_format($totals['total_amount'], 2) ?></div>
                                <div class="summary-label">Total Sales Amount</div>
                            </div>
                            <div>
                                <div class="summary-value amount-positive">
                                    $<?= number_format($totals['total_paid'], 2) ?></div>
                                <div class="summary-label">Total Paid</div>
                            </div>
                            <div>
                                <div class="summary-value amount-negative">
                                    $<?= number_format($totals['total_loan'], 2) ?></div>
                                <div class="summary-label">Total Loans</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Sales Table -->
        <div class="card">
            <div class="card-header">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h5 class="card-title flex items-center">
                            <i data-lucide="file-text" class="w-5 h-5 mr-2"></i>
                            Sales Records
                        </h5>
                        <p class="card-description">View and manage all sales transactions</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="search-container">
                            <i data-lucide="search" class="w-4 h-4"></i>
                            <input type="text" class="form-control search-input" placeholder="Search sales..."
                                value="<?= htmlspecialchars($search) ?>" onkeyup="searchTable(this.value)">
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
                                <th>Customer</th>
                                <th>Item</th>
                                <th class="text-right">Quantity</th>
                                <th class="text-right">Price</th>
                                <th class="text-right">Amount</th>
                                <th class="text-right">Paid</th>
                                <th class="text-right">Loan</th>
                                <th>Date</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $sales->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $row['id'] ?></td>
                                    <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                    <td><?= htmlspecialchars($row['item_name']) ?></td>
                                    <td class="text-right"><?= $row['quantity'] ?></td>
                                    <td class="text-right">$<?= number_format($row['price'], 2) ?></td>
                                    <td class="text-right">$<?= number_format($row['amount'], 2) ?></td>
                                    <td class="text-right">$<?= number_format($row['paid'], 2) ?></td>
                                    <td class="text-right <?= $row['loan'] > 0 ? 'amount-negative' : '' ?>">
                                        $<?= number_format($row['loan'], 2) ?>
                                    </td>
                                    <td><?= $row['date'] ?></td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <button type="button" class="icon-button"
                                                onclick='editSale(<?= json_encode($row) ?>)'>
                                                <i data-lucide="edit" class="w-4 h-4"></i>
                                            </button>
                                            <a href="?delete=<?= $row['id'] ?>" class="icon-button text-red-600"
                                                onclick="return confirm('Are you sure you want to delete this sale?')">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <!-- Totals row -->
                            <tr class="font-semibold bg-gray-50">
                                <td colspan="5" class="text-right">Totals:</td>
                                <td class="text-right">$<?= number_format($totals['total_amount'], 2) ?></td>
                                <td class="text-right">$<?= number_format($totals['total_paid'], 2) ?></td>
                                <td class="text-right">$<?= number_format($totals['total_loan'], 2) ?></td>
                                <td colspan="2"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Your existing JavaScript for calculations
        document.querySelector("input[name='quantity']").addEventListener("input", calculateAmount);
        document.querySelector("input[name='price']").addEventListener("input", calculateAmount);
        document.querySelector("input[name='paid']").addEventListener("input", calculateLoan);

        function calculateAmount() {
            let quantity = parseFloat(document.querySelector("input[name='quantity']").value) || 0;
            let price = parseFloat(document.querySelector("input[name='price']").value) || 0;
            let amount = quantity * price;
            document.querySelector("input[name='amount']").value = amount.toFixed(2);
            calculateLoan();
        }

        function calculateLoan() {
            let amount = parseFloat(document.querySelector("input[name='amount']").value) || 0;
            let paid = parseFloat(document.querySelector("input[name='paid']").value) || 0;
            let loan = amount - paid;
            document.querySelector("input[name='loan']").value = loan.toFixed(2);
        }

        function editSale(data) {
            document.querySelector("input[name='id']").value = data.id;
            document.querySelector("input[name='customer_name']").value = data.customer_name;
            document.querySelector("input[name='item_name']").value = data.item_name;
            document.querySelector("input[name='quantity']").value = data.quantity;
            document.querySelector("input[name='price']").value = data.price;
            document.querySelector("input[name='amount']").value = data.amount;
            document.querySelector("input[name='paid']").value = data.paid;
            document.querySelector("input[name='loan']").value = data.loan;
            document.querySelector("input[name='date']").value = data.date;

            document.getElementById("addBtn").style.display = "none";
            document.getElementById("updateBtn").style.display = "block";

            // Scroll to form
            document.querySelector('.card').scrollIntoView({ behavior: 'smooth' });
        }

        function searchTable(query) {
            query = query.toLowerCase();
            let rows = document.querySelectorAll('tbody tr');

            rows.forEach(row => {
                if (row.classList.contains('font-semibold')) return; // Skip totals row

                let text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        }
    </script>
</body>

</html>