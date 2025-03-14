<?php
session_start();

if (!isset($_SESSION['order_details'])) {
    die("No order found.");
}

$order_details = $_SESSION['order_details'];
$customer_name = htmlspecialchars($order_details['customer_name']);
$products = $order_details['products'];
$payment_status = isset($order_details['payment_status']) ? $order_details['payment_status'] : 'Unpaid';
$shop_name = "KM Supermarket";
$shop_address = "123 Market Street, Cityville";
$shop_contact = "+1 234 567 890";

// Handle Deletion
if (isset($_GET['delete'])) {
    $index = $_GET['delete'];
    unset($products[$index]);
    $_SESSION['order_details']['products'] = array_values($products);
    header("Location: invoice.php");
    exit;
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_index'])) {
    $index = $_POST['update_index'];
    $_SESSION['order_details']['products'][$index]['name'] = $_POST['name'];
    $_SESSION['order_details']['products'][$index]['quantity'] = $_POST['quantity'];
    $_SESSION['order_details']['products'][$index]['price'] = $_POST['price'];
    header("Location: invoice.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice - KM Management System</title>
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
        
        .invoice-container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .invoice-header {
            padding: 2rem;
            border-bottom: 1px solid var(--border);
            text-align: center;
        }
        
        .shop-logo {
            width: 80px;
            height: 80px;
            margin-bottom: 1rem;
            background: #f3f4f6;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: auto;
            margin-right: auto;
        }
        
        .shop-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        
        .shop-details {
            color: var(--muted-foreground);
            font-size: 0.875rem;
        }
        
        .invoice-body {
            padding: 2rem;
        }
        
        .customer-details {
            margin-bottom: 2rem;
        }
        
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        
        .invoice-table th {
            background-color: #f8fafc;
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--muted-foreground);
            text-align: left;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
        }
        
        .invoice-table td {
            padding: 1rem;
            font-size: 0.875rem;
            border-bottom: 1px solid var(--border);
        }
        
        .amount {
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Fira Mono', 'Droid Sans Mono', 'Source Code Pro', monospace;
        }
        
        .grand-total {
            font-size: 1.25rem;
            font-weight: 600;
            text-align: right;
            padding: 1rem 0;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-paid {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .badge-unpaid {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                background: white;
            }
            
            .invoice-container {
                box-shadow: none;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Back to Dashboard Button (No Print) -->
        <div class="flex justify-between items-center my-4 no-print">
            <a href="manage_order_bill.php" class="btn btn-outline">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Back to Orders
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <i data-lucide="printer" class="w-4 h-4"></i>
                Print Invoice
            </button>
        </div>

        <!-- Invoice -->
        <div class="invoice-container">
            <div class="invoice-header">
                <div class="shop-logo">
                    <i data-lucide="shopping-bag" class="w-8 h-8 text-primary"></i>
                </div>
                <div class="shop-name"><?= $shop_name ?></div>
                <div class="shop-details"><?= $shop_address ?></div>
                <div class="shop-details"><?= $shop_contact ?></div>
            </div>

            <div class="invoice-body">
                <div class="customer-details">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-sm text-muted-foreground">Customer</div>
                            <div class="font-medium"><?= $customer_name ?></div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-muted-foreground">Invoice Date</div>
                            <div class="font-medium"><?= date('F d, Y') ?></div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">Payment Status</div>
                            <div class="badge <?= ($payment_status == 'Paid') ? 'badge-paid' : 'badge-unpaid' ?>">
                                <?= $payment_status ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-muted-foreground">Invoice Number</div>
                            <div class="font-medium">#<?= date('Ymd') . rand(100, 999) ?></div>
                        </div>
                    </div>
                </div>

                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-right">Quantity</th>
                            <th class="text-right">Price</th>
                            <th class="text-right">Total</th>
                            <th class="no-print"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $grandTotal = 0;
                        foreach ($products as $index => $product):
                            $totalPrice = $product['quantity'] * $product['price'];
                            $grandTotal += $totalPrice;
                        ?>
                            <tr>
                                <td class="font-medium"><?= htmlspecialchars($product['name']) ?></td>
                                <td class="text-right"><?= htmlspecialchars($product['quantity']) ?></td>
                                <td class="text-right amount">$<?= number_format($product['price'], 2) ?></td>
                                <td class="text-right amount">$<?= number_format($totalPrice, 2) ?></td>
                                <td class="no-print">
                                    <div class="flex gap-2 justify-end">
                                        <button class="btn btn-outline" 
                                                onclick="editProduct(<?= $index ?>, '<?= htmlspecialchars($product['name']) ?>', '<?= $product['quantity'] ?>', '<?= $product['price'] ?>')">
                                            <i data-lucide="edit" class="w-4 h-4"></i>
                                        </button>
                                        <a href="?delete=<?= $index ?>" class="btn btn-outline text-red-600 hover:bg-red-50"
                                           onclick="return confirm('Are you sure you want to delete this item?')">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-right font-medium">Grand Total:</td>
                            <td class="text-right amount font-bold">$<?= number_format($grandTotal, 2) ?></td>
                            <td class="no-print"></td>
                        </tr>
                    </tfoot>
                </table>

                <div class="mt-8 pt-8 border-t border-dashed text-center text-sm text-muted-foreground">
                    Thank you for your business!
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title font-semibold">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="update_index" id="update_index">
                        <div>
                            <label class="form-label">Product Name</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div>
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" id="edit_quantity" class="form-control" required>
                        </div>
                        <div>
                            <label class="form-label">Price</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                                <input type="number" step="0.01" name="price" id="edit_price" class="form-control pl-8" required>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Product</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        function editProduct(index, name, quantity, price) {
            document.getElementById("update_index").value = index;
            document.getElementById("edit_name").value = name;
            document.getElementById("edit_quantity").value = quantity;
            document.getElementById("edit_price").value = price;

            var modal = new bootstrap.Modal(document.getElementById("editModal"));
            modal.show();
        }
    </script>
</body>
</html>