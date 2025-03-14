<?php
session_start();
include 'db_connect.php'; // Ensure database connection

// Fixed Shop Details
$shop_name = "Shop Name";
$shop_address = "Shop Address, City, Country";
$shop_contact = "Shop Contact";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_name = $_POST['customer_name'];
    $payment_status = $_POST['payment_status'];
    $date = date("Y-m-d");

    $_SESSION['order_details'] = $_POST; // Store order details for invoice

    $conn->begin_transaction(); // Start transaction for stock and order updates

    foreach ($_POST['products'] as $product) {
        $item_name = $product['name'];
        $quantity = (int) $product['quantity'];
        $price = (float) $product['price'];
        $amount = $quantity * $price;
        $paid = ($payment_status == "Paid") ? $amount : 0;
        $loan = $amount - $paid;

        // **Check stock availability**
        $stock_check = $conn->query("SELECT quantity FROM stock WHERE item_name = '$item_name'");
        if ($stock_check->num_rows > 0) {
            $row = $stock_check->fetch_assoc();
            $available_stock = $row['quantity'];

            if ($available_stock < $quantity) {
                echo "<script>alert('Not enough stock for $item_name. Available: $available_stock');</script>";
                $conn->rollback(); // Rollback if stock is insufficient
                exit;
            }

            // **Reduce stock quantity**
            $new_stock = $available_stock - $quantity;
            $conn->query("UPDATE stock SET quantity = $new_stock WHERE item_name = '$item_name'");
        } else {
            echo "<script>alert('Item $item_name not found in stock!');</script>";
            $conn->rollback();
            exit;
        }

        // **Insert into daily_sales**
        $sql = "INSERT INTO daily_sales (customer_name, item_name, quantity, price, amount, paid, loan, date) 
                VALUES ('$customer_name', '$item_name', '$quantity', '$price', '$amount', '$paid', '$loan', '$date')";

        if (!$conn->query($sql)) {
            echo "Error: " . $conn->error;
            $conn->rollback();
            exit;
        }
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
    }

    $conn->commit(); // Commit transaction if everything is successful
    header("Location: invoice.php");
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Management - KM Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
        }

        .card-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border);
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .form-control {
            border-radius: 0.375rem;
            border: 1px solid var(--border);
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
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

        .product-item {
            position: relative;
            padding: 1rem;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
        }

        .product-item:hover {
            border-color: var(--primary);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .delete-product {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            padding: 0.25rem;
            border-radius: 9999px;
            background: none;
            border: none;
            color: var(--destructive);
            opacity: 0;
            transition: all 0.2s;
        }

        .product-item:hover .delete-product {
            opacity: 1;
        }

        .amount {
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Fira Mono', 'Droid Sans Mono', 'Source Code Pro', monospace;
        }

        .shop-info {
            text-align: center;
            margin-bottom: 2rem;
        }

        .shop-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
        }

        .shop-details {
            color: var(--muted-foreground);
            font-size: 0.875rem;
        }
    </style>
</head>

<body>
    <div class="container max-w-4xl mx-auto p-4">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Create Order</h1>
            <a href="index.php" class="btn btn-outline">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Back to Dashboard
            </a>
        </div>

        <!-- Shop Info -->
        <div class="shop-info mb-6">
            <div class="shop-name"><?= $shop_name ?></div>
            <div class="shop-details"><?= $shop_address ?></div>
            <div class="shop-details"><?= $shop_contact ?></div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="text-lg font-semibold flex items-center">
                    <i data-lucide="shopping-cart" class="w-5 h-5 mr-2"></i>
                    Order Details
                </h5>
            </div>
            <div class="card-body p-6">
                <form method="POST" action="">
                    <div class="grid md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="form-label">Customer Name</label>
                            <input type="text" name="customer_name" class="form-control" required
                                placeholder="Enter customer name">
                        </div>
                        <div>
                            <label class="form-label">Payment Status</label>
                            <select name="payment_status" class="form-control">
                                <option value="Paid">Paid</option>
                                <option value="Unpaid">Unpaid</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h6 class="font-semibold">Products</h6>
                            <button type="button" class="btn btn-outline" onclick="addProduct()">
                                <i data-lucide="plus" class="w-4 h-4"></i>
                                Add Product
                            </button>
                        </div>

                        <div id="products">
                            <div class="product-item">
                                <button type="button" class="delete-product">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                                <div class="grid md:grid-cols-4 gap-4">
                                    <div>
                                        <label class="form-label">Product Name</label>
                                        <input type="text" name="products[0][name]" class="form-control" required
                                            placeholder="Enter product name">
                                    </div>
                                    <div>
                                        <label class="form-label">Quantity</label>
                                        <input type="number" name="products[0][quantity]" class="form-control quantity"
                                            required min="1" placeholder="0" oninput="updateTotal(this)">
                                    </div>
                                    <div>
                                        <label class="form-label">Price</label>
                                        <div class="relative">
                                            <span
                                                class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                                            <input type="number" step="0.01" name="products[0][price]"
                                                class="form-control price pl-8" required min="0" placeholder="0.00"
                                                oninput="updateTotal(this)">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="form-label">Total</label>
                                        <div class="relative">
                                            <span
                                                class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                                            <input type="text" class="form-control total pl-8" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-4">
                        <button type="button" class="btn btn-outline" onclick="window.location.href='index.php'">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="file-text" class="w-4 h-4"></i>
                            Generate Invoice
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        function addProduct() {
            const productContainer = document.getElementById("products");
            const productItems = document.querySelectorAll(".product-item");
            const newIndex = productItems.length;

            const newProduct = document.createElement("div");
            newProduct.classList.add("product-item");

            newProduct.innerHTML = `
                <button type="button" class="delete-product">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
                <div class="grid md:grid-cols-4 gap-4">
                    <div>
                        <label class="form-label">Product Name</label>
                        <input type="text" name="products[${newIndex}][name]" class="form-control" required 
                               placeholder="Enter product name">
                    </div>
                    <div>
                        <label class="form-label">Quantity</label>
                        <input type="number" name="products[${newIndex}][quantity]" class="form-control quantity" 
                               required min="1" placeholder="0" oninput="updateTotal(this)">
                    </div>
                    <div>
                        <label class="form-label">Price</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                            <input type="number" step="0.01" name="products[${newIndex}][price]" 
                                   class="form-control price pl-8" required min="0" 
                                   placeholder="0.00" oninput="updateTotal(this)">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Total</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                            <input type="text" class="form-control total pl-8" readonly>
                        </div>
                    </div>
                </div>
            `;

            productContainer.appendChild(newProduct);
            lucide.createIcons(); // Reinitialize icons for new elements
            attachDeleteEvents();
        }

        function attachDeleteEvents() {
            document.querySelectorAll(".delete-product").forEach(button => {
                button.onclick = function () {
                    if (document.querySelectorAll(".product-item").length > 1) {
                        this.closest(".product-item").remove();
                    } else {
                        alert("You must have at least one product.");
                    }
                };
            });
        }

        function updateTotal(input) {
            const row = input.closest(".product-item");
            const quantity = parseFloat(row.querySelector(".quantity").value) || 0;
            const price = parseFloat(row.querySelector(".price").value) || 0;
            row.querySelector(".total").value = (quantity * price).toFixed(2);
        }

        attachDeleteEvents();
    </script>
</body>

</html>