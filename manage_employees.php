<?php
include 'db_connect.php';

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_employee'])) {
        // Add Employee
        $name = trim($_POST['name']);
        $salary = floatval($_POST['salary']);

        if (empty($name) || $salary <= 0) {
            echo "Error: Invalid input.";
        } else {
            $stmt = $conn->prepare("INSERT INTO employees (name, salary, total_paid) VALUES (?, ?, 0)");
            $stmt->bind_param("sd", $name, $salary);
            if ($stmt->execute()) {
                echo "Employee added successfully.";
            } else {
                echo "Error: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif (isset($_POST['process_payment'])) {
        // Process Salary Payment
        $employee_id = intval($_POST['employee_id']);
        $payment_amount = floatval($_POST['payment_amount']);
        $payment_type = $_POST['payment_type'];

        $stmt = $conn->prepare("SELECT salary, total_paid FROM employees WHERE id = ?");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $employee = $result->fetch_assoc();
        $stmt->close();

        if (!$employee) {
            die("Error: Employee not found.");
        }

        $remaining_salary = $employee['salary'] - $employee['total_paid'];
        if ($payment_amount > $remaining_salary) {
            die("Error: Payment exceeds remaining salary.");
        }

        $new_total_paid = $employee['total_paid'] + $payment_amount;

        $stmt = $conn->prepare("UPDATE employees SET total_paid = ? WHERE id = ?");
        $stmt->bind_param("di", $new_total_paid, $employee_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO employee_payments (employee_id, payment_amount, payment_type, payment_date) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("ids", $employee_id, $payment_amount, $payment_type);
        if ($stmt->execute()) {
            echo "Payment recorded successfully.";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    } elseif (isset($_POST['update_employee'])) {
        // Update Employee Details
        if (isset($_POST['update_employee'])) {
    $employee_id = intval($_POST['employee_id']);
    $name = trim($_POST['name']);
    $salary = floatval($_POST['salary']);

    if (empty($name) || $salary <= 0) {
        echo "<script>alert('Error: Invalid input.');</script>";
    } else {
        $stmt = $conn->prepare("UPDATE employees SET name = ?, salary = ? WHERE id = ?");
        $stmt->bind_param("sdi", $name, $salary, $employee_id);
        if ($stmt->execute()) {
            echo "<script>alert('Employee updated successfully.'); window.location.href='manage_employees.php';</script>";
        } else {
            echo "<script>alert('Error: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    }
}

    } elseif (isset($_POST['delete_employee'])) {
        // Delete Employee
        $employee_id = intval($_POST['employee_id']);
        $stmt = $conn->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->bind_param("i", $employee_id);
        if ($stmt->execute()) {
            echo "Employee deleted successfully.";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch employees
$employees = $conn->query("SELECT *, (salary - total_paid) AS remaining_salary FROM employees");

// Fetch payment history
$payments = $conn->query("SELECT e.name, p.payment_amount, p.payment_type, p.payment_date 
                          FROM employee_payments p 
                          JOIN employees e ON p.employee_id = e.id 
                          ORDER BY p.payment_date DESC");

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Salary Management - KM Management System</title>
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
        
        .btn-success {
            background-color: #10b981;
            color: #ffffff;
            border: none;
        }
        
        .btn-success:hover {
            background-color: #059669;
        }
        
        .btn-danger {
            background-color: var(--destructive);
            color: var(--destructive-foreground);
            border: none;
        }
        
        .btn-danger:hover {
            background-color: #dc2626;
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
        
        .badge-success {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .badge-warning {
            background-color: #fef9c3;
            color: #854d0e;
        }
        
        .amount {
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Fira Mono', 'Droid Sans Mono', 'Source Code Pro', monospace;
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
        
        .employee-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .modal {
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            border: none;
            border-radius: 0.5rem;
        }
        
        .modal-header {
            border-bottom: 1px solid var(--border);
            padding: 1.25rem;
        }
        
        .modal-body {
            padding: 1.25rem;
        }
        
        .modal-footer {
            border-top: 1px solid var(--border);
            padding: 1.25rem;
        }
    </style>
</head>
<body>
    <div class="container mx-auto p-4">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Employee Salary Management</h1>
            <a href="index.php" class="btn btn-outline">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Back to Dashboard
            </a>
        </div>

        <div class="grid md:grid-cols-2 gap-6 mb-6">
            <!-- Add Employee Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i data-lucide="user-plus" class="w-5 h-5 mr-2"></i>
                        Add New Employee
                    </h5>
                    <p class="card-description">Add a new employee to the system</p>
                </div>
                <div class="card-body">
                    <form method="POST" class="space-y-4">
                        <div class="form-group">
                            <label class="form-label" for="name">Employee Name</label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="Enter employee name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="salary">Monthly Salary</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                                <input type="number" id="salary" name="salary" class="form-control pl-8" placeholder="Enter monthly salary" required>
                            </div>
                        </div>
                        <button type="submit" name="add_employee" class="btn btn-success w-full">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            Add Employee
                        </button>
                    </form>
                </div>
            </div>

            <!-- Process Payment Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i data-lucide="credit-card" class="w-5 h-5 mr-2"></i>
                        Process Salary Payment
                    </h5>
                    <p class="card-description">Record a salary payment for an employee</p>
                </div>
                <div class="card-body">
                    <form method="POST" class="space-y-4">
                        <div class="form-group">
                            <label class="form-label" for="employee_id">Select Employee</label>
                            <select id="employee_id" name="employee_id" class="form-control" required>
                                <option value="">Choose an employee...</option>
                                <?php 
                                $employees->data_seek(0);
                                while ($row = $employees->fetch_assoc()) { 
                                ?>
                                    <option value="<?= $row['id']; ?>">
                                        <?= $row['name']; ?> (Remaining: $<?= number_format($row['remaining_salary'], 2); ?>)
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="payment_amount">Payment Amount</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                                <input type="number" id="payment_amount" name="payment_amount" class="form-control pl-8" placeholder="Enter payment amount" required min="1">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="payment_type">Payment Type</label>
                            <select id="payment_type" name="payment_type" class="form-control" required>
                                <option value="Advance">Advance Payment</option>
                                <option value="Full Salary">Full Salary Payment</option>
                            </select>
                        </div>
                        <button type="submit" name="process_payment" class="btn btn-primary w-full">
                            <i data-lucide="check-circle" class="w-4 h-4"></i>
                            Process Payment
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Employee List -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i data-lucide="users" class="w-5 h-5 mr-2"></i>
                    Manage Employees
                </h5>
                <p class="card-description">View and manage employee information</p>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee Name</th>
                                <th class="text-right">Monthly Salary</th>
                                <th class="text-right">Remaining Salary</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $employees->data_seek(0);
                            while ($row = $employees->fetch_assoc()) { 
                            ?>
                                <tr>
                                    <td class="font-medium"><?= htmlspecialchars($row['name']); ?></td>
                                    <td class="text-right amount">$<?= number_format($row['salary'], 2); ?></td>
                                    <td class="text-right">
                                        <span class="amount <?= $row['remaining_salary'] > 0 ? 'amount-negative' : 'amount-positive' ?>">
                                            $<?= number_format($row['remaining_salary'], 2); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex justify-center gap-2">
                                            <button type="button" class="icon-button" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id']; ?>">
                                                <i data-lucide="edit" class="w-4 h-4"></i>
                                            </button>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="employee_id" value="<?= $row['id']; ?>">
                                                <button type="submit" name="delete_employee" class="icon-button text-red-600" onclick="return confirm('Are you sure you want to delete this employee?');">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                        </div>

                                        <!-- Edit Modal -->
                                        <div class="modal fade" id="editModal<?= $row['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Employee</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="employee_id" value="<?= $row['id']; ?>">
                                                            <div class="form-group mb-3">
                                                                <label class="form-label">Employee Name</label>
                                                                <input type="text" name="name" value="<?= htmlspecialchars($row['name']); ?>" class="form-control" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label class="form-label">Monthly Salary</label>
                                                                <div class="relative">
                                                                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                                                                    <input type="number" name="salary" value="<?= $row['salary']; ?>" class="form-control pl-8" required>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="update_employee" class="btn btn-primary">
                                                                <i data-lucide="save" class="w-4 h-4"></i>
                                                                Save Changes
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Payment History -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i data-lucide="history" class="w-5 h-5 mr-2"></i>
                    Payment History
                </h5>
                <p class="card-description">View all salary payment transactions</p>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th class="text-right">Amount</th>
                                <th>Payment Type</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $payments->fetch_assoc()) { ?>
                                <tr>
                                    <td class="font-medium"><?= htmlspecialchars($row['name']); ?></td>
                                    <td class="text-right amount">$<?= number_format($row['payment_amount'], 2); ?></td>
                                    <td>
                                        <span class="badge <?= $row['payment_type'] === 'Advance' ? 'badge-warning' : 'badge-success' ?>">
                                            <?= $row['payment_type']; ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($row['payment_date'])); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html>
