<?php
include 'db_connect.php';

// Fetch total stock items
$stock_query = $conn->query("SELECT COUNT(*) AS total_items FROM stock");
$stock = $stock_query ? $stock_query->fetch_assoc()['total_items'] ?? 0 : 0;

// Fetch total sales  
$sales_query = $conn->query("SELECT SUM(amount) AS total_sales FROM daily_sales");
$sales = $sales_query ? $sales_query->fetch_assoc()['total_sales'] ?? 0 : 0;

// Fetch total loans
$loan_query = $conn->query("SELECT SUM(loan) AS total_loans FROM daily_sales");
$loans = $loan_query ? $loan_query->fetch_assoc()['total_loans'] ?? 0 : 0;

// Fetch total expenses
$expense_query = $conn->query("SELECT SUM(amount) AS total_expenses FROM expenses");
$expenses = $expense_query ? $expense_query->fetch_assoc()['total_expenses'] ?? 0 : 0;

// Fetch total employees
$employee_query = $conn->query("SELECT COUNT(*) AS total_employees FROM employees");
$employees = $employee_query ? $employee_query->fetch_assoc()['total_employees'] ?? 0 : 0;

// Active page detection for dynamic navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- Add Tailwind CSS for additional styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Add Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-light: #818cf8;
            --primary-dark: #4f46e5;
            --primary-foreground: #ffffff;
            --secondary: #f97316;
            --secondary-light: #fb923c;
            --secondary-dark: #ea580c;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --muted-foreground: #64748b;
            --border: #e2e8f0;
            --card-bg: #ffffff;
            --body-bg: #f1f5f9;
            --header-bg: #ffffff;
            --sidebar-bg: #1e293b;
            --sidebar-text: #e2e8f0;
            --sidebar-hover: #334155;
            --sidebar-active: #6366f1;
        }
        
        body {
            background-color: var(--body-bg);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            color: #0f172a;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
        }
        
        .sidebar {
            background-color: var(--sidebar-bg);
            height: 100vh;
            position: fixed;
            width: 280px;
            z-index: 30;
            transition: transform 0.3s ease-in-out;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .sidebar-hidden {
            transform: translateX(-100%);
        }
        
        .main-content {
            margin-left: 0;
            transition: margin-left 0.3s ease-in-out;
        }
        
        @media (min-width: 768px) {
            .main-content-with-sidebar {
                margin-left: 280px;
            }
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.25rem;
            border-radius: 0.5rem;
            color: var(--sidebar-text);
            font-weight: 500;
            margin-bottom: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .nav-link:hover {
            background-color: var(--sidebar-hover);
            color: #ffffff;
        }
        
        .nav-link.active {
            background-color: var(--sidebar-active);
            color: var(--primary-foreground);
            box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.2), 0 2px 4px -1px rgba(99, 102, 241, 0.1);
        }
        
        .nav-link i {
            margin-right: 0.75rem;
        }
        
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            overflow: hidden;
            background-color: var(--card-bg);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .card-header {
            background-color: transparent;
            border-bottom: none;
            padding: 1.5rem 1.5rem 0.5rem;
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.25rem;
        }
        
        .card-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .badge-trend {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-weight: 600;
            margin-right: 0.5rem;
        }
        
        .badge-trend-up {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .badge-trend-down {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .header {
            background-color: var(--header-bg);
            border-bottom: 1px solid var(--border);
            height: 70px;
            position: sticky;
            top: 0;
            z-index: 20;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--primary-foreground);
            box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.3);
        }
        
        .search-input {
            background-color: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            padding: 0.625rem 1rem 0.625rem 2.5rem;
            transition: all 0.2s ease;
        }
        
        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted-foreground);
        }
        
        .toggle-btn {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--primary-foreground);
            cursor: pointer;
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            z-index: 40;
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3), 0 4px 6px -2px rgba(99, 102, 241, 0.2);
            transition: transform 0.2s ease;
        }
        
        .toggle-btn:hover {
            transform: scale(1.05);
        }
        
        @media (min-width: 768px) {
            .toggle-btn-fixed {
                display: none;
            }
        }
        
        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 0.5rem;
            transition: background-color 0.2s ease;
        }
        
        .activity-item:hover {
            background-color: #f8fafc;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.2);
        }
        
        .activity-icon i {
            color: var(--primary-foreground);
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #334155;
        }
        
        .activity-time {
            font-size: 0.75rem;
            color: var(--muted-foreground);
            display: flex;
            align-items: center;
        }
        
        .activity-time i {
            margin-right: 0.25rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.3);
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3), 0 4px 6px -2px rgba(99, 102, 241, 0.2);
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
        }
        
        .stat-card-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(99, 102, 241, 0.2));
        }
        
        .stat-card-icon i {
            color: var(--primary);
        }
        
        .stat-card-stock .stat-card-icon {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.2));
        }
        
        .stat-card-stock .stat-card-icon i {
            color: var(--success);
        }
        
        .stat-card-stock .card-value {
            background: linear-gradient(135deg, var(--success), #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-card-sales .stat-card-icon {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.2));
        }
        
        .stat-card-sales .stat-card-icon i {
            color: var(--info);
        }
        
        .stat-card-sales .card-value {
            background: linear-gradient(135deg, var(--info), #2563eb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-card-loans .stat-card-icon {
            background: linear-gradient(135deg, rgba(249, 115, 22, 0.1), rgba(249, 115, 22, 0.2));
        }
        
        .stat-card-loans .stat-card-icon i {
            color: var(--secondary);
        }
        
        .stat-card-loans .card-value {
            background: linear-gradient(135deg, var(--secondary), var(--secondary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-card-expenses .stat-card-icon {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.2));
        }
        
        .stat-card-expenses .stat-card-icon i {
            color: var(--danger);
        }
        
        .stat-card-expenses .card-value {
            background: linear-gradient(135deg, var(--danger), #b91c1c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-card-employees .stat-card-icon {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.2));
        }
        
        .stat-card-employees .stat-card-icon i {
            color: var(--warning);
        }
        
        .stat-card-employees .card-value {
            background: linear-gradient(135deg, var(--warning), #d97706);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .chart-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%236366f1' fill-opacity='0.05' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.5;
        }
        
        .logo-text {
            background: linear-gradient(135deg, #f8fafc, #ffffff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        
        .section-title {
            position: relative;
            display: inline-block;
            margin-bottom: 1.5rem;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -0.5rem;
            height: 4px;
            width: 50px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 2px;
        }
    </style>
</head>
<body>
    <!-- Mobile sidebar toggle -->
    <button id="sidebarToggleFixed" class="toggle-btn md:hidden">
        <i data-lucide="menu" class="w-6 h-6"></i>
    </button>

    <!-- Sidebar -->
    <div id="sidebar" class="sidebar sidebar-hidden md:translate-x-0">
        <div class="flex h-full flex-col">
            <!-- Sidebar header -->
            <div class="flex h-16 items-center border-b border-slate-700 px-6">
                <h2 class="text-2xl font-bold logo-text">Inventory Management</h2>
            </div>
            
            <!-- Sidebar navigation -->
            <nav class="flex-1 space-y-1 px-4 py-6">
                <a href="index.php" class="nav-link <?= ($current_page == 'index.php') ? 'active' : ''; ?>">
                    <i data-lucide="home" class="w-5 h-5"></i> Dashboard
                </a>
                
                <a href="add_stock.php" class="nav-link <?= ($current_page == 'add_stock.php') ? 'active' : ''; ?>">
                    <i data-lucide="package" class="w-5 h-5"></i> Stock Management
                </a>
                
                <a href="add_sale.php" class="nav-link <?= ($current_page == 'add_sale.php') ? 'active' : ''; ?>">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i> Daily Sales
                </a>
                
                <a href="manage_loan.php" class="nav-link <?= ($current_page == 'manage_loan.php') ? 'active' : ''; ?>">
                    <i data-lucide="landmark" class="w-5 h-5"></i> Loan Management
                </a>
                
                <a href="manage_expenses.php" class="nav-link <?= ($current_page == 'manage_expenses.php') ? 'active' : ''; ?>">
                    <i data-lucide="dollar-sign" class="w-5 h-5"></i> Manage Expenses
                </a>
                
                <a href="manage_employees.php" class="nav-link <?= ($current_page == 'manage_employees.php') ? 'active' : ''; ?>">
                    <i data-lucide="users" class="w-5 h-5"></i> Manage Employees
                </a>
                
                <a href="manage_order_bill.php" class="nav-link <?= ($current_page == 'manage_order_bill.php') ? 'active' : ''; ?>">
                    <i data-lucide="file-text" class="w-5 h-5"></i> Create Order Bill
                </a>
                
                <div class="pt-6 mt-6 border-t border-slate-700">
                    <a href="#" class="nav-link">
                        <i data-lucide="settings" class="w-5 h-5"></i> Settings
                    </a>
                    
                    <a href="#" class="nav-link">
                        <i data-lucide="help-circle" class="w-5 h-5"></i> Help & Support
                    </a>
                    
                    <a href="#" class="nav-link">
                        <i data-lucide="log-out" class="w-5 h-5"></i> Logout
                    </a>
                </div>
            </nav>
        </div>
    </div>

    <!-- Main content -->
    <div id="mainContent" class="main-content">
        <!-- Header -->
        <header class="header px-4 md:px-6 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <button id="sidebarToggle" class="btn btn-outline-secondary d-md-none">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <h1 class="h4 mb-0 fw-bold">Dashboard</h1>
            </div>
            
            <div class="d-flex align-items-center gap-4">
                <div class="position-relative d-none d-md-block">
                    <i data-lucide="search" class="search-icon w-5 h-5"></i>
                    <input type="search" class="form-control search-input" placeholder="Search...">
                </div>
                
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-outline-secondary position-relative">
                        <i data-lucide="bell" class="w-5 h-5"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            3
                        </span>
                    </button>
                    
                    <div class="avatar">
                        <span>KM</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main content -->
        <main class="p-4 md:p-6">
            <div class="mb-5 d-flex align-items-center justify-content-between">
                <div>
                    <h2 class="h3 fw-bold mb-1 section-title">Business Overview</h2>
                    <p class="text-muted">Welcome back! Here's what's happening with your business today.</p>
                </div>
                <button class="btn btn-primary d-flex align-items-center gap-2">
                    <i data-lucide="plus" class="w-4 h-4"></i> Add New
                </button>
            </div>
            
            <!-- Stats cards -->
            <div class="row g-4 mb-5">
                <div class="col-sm-6 col-lg-4 col-xl-2-4">
                    <div class="card h-100 stat-card-stock position-relative">
                        <div class="stat-card-icon">
                            <i data-lucide="package" class="w-6 h-6"></i>
                        </div>
                        <div class="card-body p-4">
                            <h5 class="card-title">Total Stock</h5>
                            <div class="card-value"><?= $stock; ?></div>
                            <p class="mb-0 d-flex align-items-center" style="font-size: 0.875rem; color: var(--muted-foreground);">
                                <span class="badge-trend badge-trend-up">+12%</span>
                                from last month
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-sm-6 col-lg-4 col-xl-2-4">
                    <div class="card h-100 stat-card-sales position-relative">
                        <div class="stat-card-icon">
                            <i data-lucide="shopping-cart" class="w-6 h-6"></i>
                        </div>
                        <div class="card-body p-4">
                            <h5 class="card-title">Total Sales</h5>
                            <div class="card-value">$<?= number_format($sales, 2); ?></div>
                            <p class="mb-0 d-flex align-items-center" style="font-size: 0.875rem; color: var(--muted-foreground);">
                                <span class="badge-trend badge-trend-up">+18%</span>
                                from last month
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-sm-6 col-lg-4 col-xl-2-4">
                    <div class="card h-100 stat-card-loans position-relative">
                        <div class="stat-card-icon">
                            <i data-lucide="landmark" class="w-6 h-6"></i>
                        </div>
                        <div class="card-body p-4">
                            <h5 class="card-title">Pending Loans</h5>
                            <div class="card-value">$<?= number_format($loans, 2); ?></div>
                            <p class="mb-0 d-flex align-items-center" style="font-size: 0.875rem; color: var(--muted-foreground);">
                                <span class="badge-trend badge-trend-down">-5%</span>
                                from last month
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-sm-6 col-lg-4 col-xl-2-4">
                    <div class="card h-100 stat-card-expenses position-relative">
                        <div class="stat-card-icon">
                            <i data-lucide="dollar-sign" class="w-6 h-6"></i>
                        </div>
                        <div class="card-body p-4">
                            <h5 class="card-title">Total Expenses</h5>
                            <div class="card-value">$<?= number_format($expenses, 2); ?></div>
                            <p class="mb-0 d-flex align-items-center" style="font-size: 0.875rem; color: var(--muted-foreground);">
                                <span class="badge-trend badge-trend-up">+7%</span>
                                from last month
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-sm-6 col-lg-4 col-xl-2-4">
                    <div class="card h-100 stat-card-employees position-relative">
                        <div class="stat-card-icon">
                            <i data-lucide="users" class="w-6 h-6"></i>
                        </div>
                        <div class="card-body p-4">
                            <h5 class="card-title">Employees</h5>
                            <div class="card-value"><?= $employees; ?></div>
                            <p class="mb-0 d-flex align-items-center" style="font-size: 0.875rem; color: var(--muted-foreground);">
                                <span class="badge-trend badge-trend-up">+2</span>
                                new this month
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Additional content -->
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Sales Overview</h5>
                            <p class="mb-0" style="font-size: 0.875rem; color: var(--muted-foreground);">Monthly sales performance</p>
                        </div>
                        <div class="card-body p-4">
                            <div class="chart-container">
                                <div class="d-flex flex-column align-items-center justify-content-center position-relative z-10">
                                    <i data-lucide="bar-chart-3" style="width: 64px; height: 64px; color: var(--primary);"></i>
                                    <p class="mt-3 mb-0 text-center" style="color: var(--muted-foreground); max-width: 300px;">
                                        Sales data visualization would appear here with monthly trends and comparisons
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Recent Activities</h5>
                            <p class="mb-0" style="font-size: 0.875rem; color: var(--muted-foreground);">Latest system activities</p>
                        </div>
                        <div class="card-body p-0">
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i data-lucide="shopping-cart" style="width: 18px; height: 18px;"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="activity-title mb-0">New sale recorded</p>
                                    <p class="activity-time mb-0">
                                        <i data-lucide="clock" style="width: 12px; height: 12px;"></i>
                                        2 hours ago
                                    </p>
                                </div>
                            </div>
                            
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i data-lucide="package" style="width: 18px; height: 18px;"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="activity-title mb-0">Stock updated</p>
                                    <p class="activity-time mb-0">
                                        <i data-lucide="clock" style="width: 12px; height: 12px;"></i>
                                        4 hours ago
                                    </p>
                                </div>
                            </div>
                            
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i data-lucide="dollar-sign" style="width: 18px; height: 18px;"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="activity-title mb-0">New expense added</p>
                                    <p class="activity-time mb-0">
                                        <i data-lucide="clock" style="width: 12px; height: 12px;"></i>
                                        Yesterday
                                    </p>
                                </div>
                            </div>
                            
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i data-lucide="users" style="width: 18px; height: 18px;"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="activity-title mb-0">New employee added</p>
                                    <p class="activity-time mb-0">
                                        <i data-lucide="clock" style="width: 12px; height: 12px;"></i>
                                        3 days ago
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top p-3 text-center">
                            <a href="#" class="text-primary fw-semibold text-decoration-none">View All Activities</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions Section -->
            <div class="mt-5">
                <h2 class="h4 fw-bold mb-4 section-title">Quick Actions</h2>
                <div class="row g-4">
                    <div class="col-md-6 col-lg-3">
                        <div class="card text-center h-100">
                            <div class="card-body p-4">
                                <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" 
                                     style="width: 64px; height: 64px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(99, 102, 241, 0.2));">
                                    <i data-lucide="plus-circle" style="width: 32px; height: 32px; color: var(--primary);"></i>
                                </div>
                                <h5 class="fw-semibold">Add New Stock</h5>
                                <p class="text-muted mb-3">Register new inventory items</p>
                                <a href="add_stock.php" class="btn btn-outline-primary">Add Stock</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-3">
                        <div class="card text-center h-100">
                            <div class="card-body p-4">
                                <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" 
                                     style="width: 64px; height: 64px; background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.2));">
                                    <i data-lucide="shopping-cart" style="width: 32px; height: 32px; color: var(--info);"></i>
                                </div>
                                <h5 class="fw-semibold">Record Sale</h5>
                                <p class="text-muted mb-3">Add new sales transaction</p>
                                <a href="add_sale.php" class="btn btn-outline-primary">Record Sale</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-3">
                        <div class="card text-center h-100">
                            <div class="card-body p-4">
                                <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" 
                                     style="width: 64px; height: 64px; background: linear-gradient(135deg, rgba(249, 115, 22, 0.1), rgba(249, 115, 22, 0.2));">
                                    <i data-lucide="file-text" style="width: 32px; height: 32px; color: var(--secondary);"></i>
                                </div>
                                <h5 class="fw-semibold">Generate Report</h5>
                                <p class="text-muted mb-3">Create business reports</p>
                                <a href="#" class="btn btn-outline-primary">Generate</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-3">
                        <div class="card text-center h-100">
                            <div class="card-body p-4">
                                <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" 
                                     style="width: 64px; height: 64px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.2));">
                                    <i data-lucide="settings" style="width: 32px; height: 32px; color: var(--success);"></i>
                                </div>
                                <h5 class="fw-semibold">System Settings</h5>
                                <p class="text-muted mb-3">Configure your preferences</p>
                                <a href="#" class="btn btn-outline-primary">Settings</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarToggleFixed = document.getElementById('sidebarToggleFixed');
            
            function toggleSidebar() {
                sidebar.classList.toggle('sidebar-hidden');
                if (window.innerWidth >= 768) {
                    mainContent.classList.toggle('main-content-with-sidebar');
                }
            }
            
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
            
            // Toggle sidebar on button click
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', toggleSidebar);
            }
            
            if (sidebarToggleFixed) {
                sidebarToggleFixed.addEventListener('click', toggleSidebar);
            }
            
            // Update on window resize
            window.addEventListener('resize', initSidebar);
        });
    </script>
</body>
</html>

