<?php
include 'db_connect.php';

// Add or Update stock
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_stock'])) {
    $category = $_POST['category'];
    $item_name = $_POST['item_name'];
    $quantity = $_POST['quantity'];
    $date = date("Y-m-d"); // Auto-generated current date

    // Check if item already exists
    $check_sql = "SELECT * FROM stock WHERE category='$category' AND item_name='$item_name'";
    $result = $conn->query($check_sql);

    if ($result->num_rows > 0) {
        // Item exists, update quantity only
        $row = $result->fetch_assoc();
        $new_quantity = $row['quantity'] + $quantity;
        $update_sql = "UPDATE stock SET quantity='$new_quantity', date='$date' WHERE id='{$row['id']}'";

        if ($conn->query($update_sql) === TRUE) {
            $success_message = "Stock Updated Successfully (New Arrival Highlighted)";
        } else {
            $error_message = "Error: " . $conn->error;
        }
    } else {
        // Item doesn't exist, insert new record
        $insert_sql = "INSERT INTO stock (category, item_name, quantity, date) VALUES ('$category', '$item_name', '$quantity', '$date')";
        
        if ($conn->query($insert_sql) === TRUE) {
            $success_message = "New Stock Added Successfully";
        } else {
            $error_message = "Error: " . $conn->error;
        }
    }
}

// Search stock
$search_result = null;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search_stock'])) {
    $search = $_POST['search'];
    $sql = "SELECT * FROM stock WHERE category LIKE '%$search%' OR item_name LIKE '%$search%'";
    $search_result = $conn->query($sql);
}

// View all stock
$all_stock = null;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['view_all_stock']) || isset($_GET['view_all'])) {
    $sql = "SELECT * FROM stock";
    $all_stock = $conn->query($sql);
}

// Update stock
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_stock'])) {
    $id = $_POST['id'];
    $category = $_POST['category'];
    $item_name = $_POST['item_name'];
    $quantity = $_POST['quantity'];
    $date = $_POST['date'];

    $sql = "UPDATE stock SET category='$category', item_name='$item_name', quantity='$quantity', date='$date' WHERE id='$id'";
    if ($conn->query($sql) === TRUE) {
        $success_message = "Stock Updated Successfully";
    } else {
        $error_message = "Error: " . $conn->error;
    }
}

// Delete stock
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_stock'])) {
    $id = $_POST['id'];
    
    $sql = "DELETE FROM stock WHERE id='$id'";
    if ($conn->query($sql) === TRUE) {
        $success_message = "Stock Deleted Successfully";
    } else {
        $error_message = "Error: " . $conn->error;
    }
}

// Active page detection for dynamic navigation
$current_page = basename($_SERVER['PHP_SELF']);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management - KM Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- Add Tailwind CSS for additional styling -->
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
        
        .sidebar {
            background-color: #ffffff;
            border-right: 1px solid var(--border);
            height: 100vh;
            position: fixed;
            width: 250px;
            z-index: 30;
            transition: transform 0.2s ease-in-out;
        }
        
        .sidebar-hidden {
            transform: translateX(-100%);
        }
        
        .main-content {
            margin-left: 0;
            transition: margin-left 0.2s ease-in-out;
        }
        
        @media (min-width: 768px) {
            .main-content-with-sidebar {
                margin-left: 250px;
            }
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            color: #334155;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .nav-link:hover {
            background-color: #f1f5f9;
        }
        
        .nav-link.active {
            background-color: var(--primary);
            color: var(--primary-foreground);
        }
        
        .nav-link i {
            margin-right: 0.75rem;
        }
        
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: transparent;
            border-bottom: none;
            padding: 1.25rem 1.25rem 0;
        }
        
        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
        }
        
        .card-title i {
            margin-right: 0.5rem;
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
        }
        
        .form-control {
            border-radius: 0.375rem;
            border: 1px solid var(--border);
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
        }
        
        .btn {
            font-weight: 500;
            border-radius: 0.375rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: #2563eb;
            border-color: #2563eb;
        }
        
        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary);
            color: var(--primary-foreground);
        }
        
        .btn-danger {
            background-color: var(--destructive);
            border-color: var(--destructive);
            color: var(--destructive-foreground);
        }
        
        .btn-danger:hover {
            background-color: #dc2626;
            border-color: #dc2626;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        
        .table {
            font-size: 0.875rem;
        }
        
        .table th {
            font-weight: 600;
            color: #1e293b;
            border-bottom-width: 1px;
            padding: 0.75rem 1rem;
        }
        
        .table td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
        }
        
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f8fafc;
        }
        
        .badge {
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }
        
        .badge-new {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        .alert {
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
            border-width: 1px;
        }
        
        .alert-success {
            background-color: #f0fdf4;
            border-color: #bbf7d0;
            color: #166534;
        }
        
        .alert-danger {
            background-color: #fef2f2;
            border-color: #fecaca;
            color: #b91c1c;
        }
        
        .modal-content {
            border: none;
            border-radius: 0.5rem;
        }
        
        .modal-header {
            border-bottom: none;
            padding: 1.25rem 1.25rem 0.5rem;
        }
        
        .modal-title {
            font-size: 1.125rem;
            font-weight: 600;
        }
        
        .modal-body {
            padding: 1rem 1.25rem;
        }
        
        .modal-footer {
            border-top: none;
            padding: 0.75rem 1.25rem 1.25rem;
        }
        
        .search-input {
            position: relative;
        }
        
        .search-input i {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted-foreground);
            pointer-events: none;
        }
        
        .search-input .form-control {
            padding-left: 2.5rem;
        }
        
        .new-item {
            background-color: #fee2e2 !important;
        }
    </style>
</head>
<body>
    <!-- Sidebar (same as in dashboard) -->
    <div id="sidebar" class="sidebar sidebar-hidden md:translate-x-0">
        <div class="flex h-full flex-col">
            <!-- Sidebar header -->
            <div class="flex h-16 items-center border-b px-6">
                <h2 class="text-xl font-bold text-primary">KM Management</h2>
            </div>
            
            <!-- Sidebar navigation -->
            <nav class="flex-1 space-y-1 px-3 py-4">
                <a href="index.php" class="nav-link <?= ($current_page == 'index.php') ? 'active' : ''; ?>">
                    <i data-lucide="home"></i> Dashboard
                </a>
                
                <a href="add_stock.php" class="nav-link <?= ($current_page == 'add_stock.php') ? 'active' : ''; ?>">
                    <i data-lucide="package"></i> Stock Management
                </a>
                
                <a href="add_sale.php" class="nav-link <?= ($current_page == 'add_sale.php') ? 'active' : ''; ?>">
                    <i data-lucide="shopping-cart"></i> Daily Sales
                </a>
                
                <a href="manage_loan.php" class="nav-link <?= ($current_page == 'manage_loan.php') ? 'active' : ''; ?>">
                    <i data-lucide="landmark"></i> Loan Management
                </a>
                
                <a href="manage_expenses.php" class="nav-link <?= ($current_page == 'manage_expenses.php') ? 'active' : ''; ?>">
                    <i data-lucide="dollar-sign"></i> Manage Expenses
                </a>
                
                <a href="manage_employees.php" class="nav-link <?= ($current_page == 'manage_employees.php') ? 'active' : ''; ?>">
                    <i data-lucide="users"></i> Manage Employees
                </a>
                
                <a href="manage_order_bill.php" class="nav-link <?= ($current_page == 'manage_order_bill.php') ? 'active' : ''; ?>">
                    <i data-lucide="bar-chart-3"></i> Manage Order Bill
                </a>
            </nav>
        </div>
    </div>

    <!-- Main content -->
    <div id="mainContent" class="main-content">
        <div class="container py-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 fw-bold">Stock Management</h1>
                <a href="index.php" class="btn btn-outline-primary d-flex align-items-center">
                    <i data-lucide="arrow-left" class="me-2" style="width: 16px; height: 16px;"></i> Back to Dashboard
                </a>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success" role="alert">
                    <?= $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <?= $error_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Add Stock Card -->
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i data-lucide="plus" style="width: 18px; height: 18px;"></i> Add New Stock
                            </h5>
                            <p class="card-description">Add new items to your inventory</p>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="category" class="form-label">Category</label>
                                    <input type="text" class="form-control" id="category" name="category" placeholder="e.g. Electronics, Clothing" required>
                                </div>
                                <div class="mb-3">
                                    <label for="item_name" class="form-label">Item Name</label>
                                    <input type="text" class="form-control" id="item_name" name="item_name" placeholder="e.g. Smartphone, T-Shirt" required>
                                </div>
                                <div class="mb-3">
                                    <label for="quantity" class="form-label">Quantity</label>
                                    <input type="number" class="form-control" id="quantity" name="quantity" min="1" placeholder="e.g. 10" required>
                                </div>
                                <button type="submit" name="add_stock" class="btn btn-primary w-100">Add Stock</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Search Stock Card -->
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i data-lucide="search" style="width: 18px; height: 18px;"></i> Search Stock
                            </h5>
                            <p class="card-description">Find items in your inventory</p>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="search" class="form-label">Search Term</label>
                                    <div class="search-input">
                                        <i data-lucide="search" style="width: 16px; height: 16px;"></i>
                                        <input type="text" class="form-control" id="search" name="search" placeholder="Search by category or item name">
                                    </div>
                                </div>
                                <button type="submit" name="search_stock" class="btn btn-primary w-100">Search</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- View All Stock Card -->
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i data-lucide="file-text" style="width: 18px; height: 18px;"></i> View All Stock
                            </h5>
                            <p class="card-description">See your complete inventory</p>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-4">View a complete list of all items currently in your inventory with detailed information.</p>
                            <form method="POST">
                                <button type="submit" name="view_all_stock" class="btn btn-primary w-100">View All Stock</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stock Table -->
            <?php if ($search_result && $search_result->num_rows > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i data-lucide="search" style="width: 18px; height: 18px;"></i> Search Results
                        </h5>
                        <p class="card-description">Found <?= $search_result->num_rows ?> items matching your search</p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Category</th>
                                        <th>Item Name</th>
                                        <th>Quantity</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $search_result->fetch_assoc()): 
                                        $isNew = (strtotime($row['date']) >= strtotime('-1 days'));
                                    ?>
                                        <tr class="<?= $isNew ? 'new-item' : '' ?>">
                                            <td><?= $row['id'] ?></td>
                                            <td><?= $row['category'] ?></td>
                                            <td>
                                                <?= $row['item_name'] ?>
                                                <?php if ($isNew): ?>
                                                    <span class="badge badge-new">New</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $row['quantity'] ?></td>
                                            <td><?= $row['date'] ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>">
                                                    Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $row['id'] ?>)">
                                                    <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                                                </button>
                                                
                                                <!-- Edit Modal -->
                                                <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1" aria-labelledby="editModalLabel<?= $row['id'] ?>" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="editModalLabel<?= $row['id'] ?>">Edit Stock Item</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <form method="POST">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                                    <div class="mb-3">
                                                                        <label for="edit-category-<?= $row['id'] ?>" class="form-label">Category</label>
                                                                        <input type="text" class="form-control" id="edit-category-<?= $row['id'] ?>" name="category" value="<?= $row['category'] ?>" required>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label for="edit-item-name-<?= $row['id'] ?>" class="form-label">Item Name</label>
                                                                        <input type="text" class="form-control" id="edit-item-name-<?= $row['id'] ?>" name="item_name" value="<?= $row['item_name'] ?>" required>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label for="edit-quantity-<?= $row['id'] ?>" class="form-label">Quantity</label>
                                                                        <input type="number" class="form-control" id="edit-quantity-<?= $row['id'] ?>" name="quantity" value="<?= $row['quantity'] ?>" required>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label for="edit-date-<?= $row['id'] ?>" class="form-label">Date</label>
                                                                        <input type="date" class="form-control" id="edit-date-<?= $row['id'] ?>" name="date" value="<?= $row['date'] ?>" required>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" name="update_stock" class="btn btn-primary">Save Changes</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($all_stock && $all_stock->num_rows > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i data-lucide="package" style="width: 18px; height: 18px;"></i> All Stock
                        </h5>
                        <p class="card-description">Manage your complete inventory (<?= $all_stock->num_rows ?> items)</p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Category</th>
                                        <th>Item Name</th>
                                        <th>Quantity</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $all_stock->fetch_assoc()): 
                                        $isNew = (strtotime($row['date']) >= strtotime('-1 days'));
                                    ?>
                                        <tr class="<?= $isNew ? 'new-item' : '' ?>">
                                            <td><?= $row['id'] ?></td>
                                            <td><?= $row['category'] ?></td>
                                            <td>
                                                <?= $row['item_name'] ?>
                                                <?php if ($isNew): ?>
                                                    <span class="badge badge-new">New</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $row['quantity'] ?></td>
                                            <td><?= $row['date'] ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>">
                                                    Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $row['id'] ?>)">
                                                    <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                                                </button>
                                                
                                                <!-- Edit Modal -->
                                                <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1" aria-labelledby="editModalLabel<?= $row['id'] ?>" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="editModalLabel<?= $row['id'] ?>">Edit Stock Item</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <form method="POST">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                                    <div class="mb-3">
                                                                        <label for="edit-category-<?= $row['id'] ?>" class="form-label">Category</label>
                                                                        <input type="text" class="form-control" id="edit-category-<?= $row['id'] ?>" name="category" value="<?= $row['category'] ?>" required>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label for="edit-item-name-<?= $row['id'] ?>" class="form-label">Item Name</label>
                                                                        <input type="text" class="form-control" id="edit-item-name-<?= $row['id'] ?>" name="item_name" value="<?= $row['item_name'] ?>" required>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label for="edit-quantity-<?= $row['id'] ?>" class="form-label">Quantity</label>
                                                                        <input type="number" class="form-control" id="edit-quantity-<?= $row['id'] ?>" name="quantity" value="<?= $row['quantity'] ?>" required>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label for="edit-date-<?= $row['id'] ?>" class="form-label">Date</label>
                                                                        <input type="date" class="form-control" id="edit-date-<?= $row['id'] ?>" name="date" value="<?= $row['date'] ?>" required>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" name="update_stock" class="btn btn-primary">Save Changes</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            // Initialize sidebar state based on screen size
            function initSidebar() {
                if (window.innerWidth >= 768) {
                    sidebar.classList.remove('sidebar-hidden');
                    mainContent.classList.add('main-content-with-sidebar');
                } else {
                    sidebar.classList.add('sidebar-hidden');
                    mainContent.classList.remove('main-content-with-sidebar');
                }
            }
            
            // Initialize on load
            initSidebar();
            
            // Update on window resize
            window.addEventListener('resize', initSidebar);
        });
        
        // Confirm delete function
        function confirmDelete(id) {
            if (confirm("Are you sure you want to delete this stock item?")) {
                let form = document.createElement("form");
                form.method = "POST";
                form.style.display = "none";
                document.body.appendChild(form);

                let input = document.createElement("input");
                input.type = "hidden";
                input.name = "id";
                input.value = id;
                form.appendChild(input);

                let deleteButton = document.createElement("input");
                deleteButton.type = "hidden";
                deleteButton.name = "delete_stock";
                deleteButton.value = "true";
                form.appendChild(deleteButton);

                form.submit();
            }
        }
    </script>
</body>
</html>