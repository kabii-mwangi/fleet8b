<?php
require_once 'config.php';
requireAuth();
requirePermission('products_view');

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' && hasPermission('products_edit')) {
        try {
            // Calculate total cost
            $units = (int)$_POST['units_purchased'];
            $costPerUnit = (float)$_POST['cost_per_unit'];
            $totalCost = $units * $costPerUnit;
            
            $stmt = $pdo->prepare("
                INSERT INTO products (category_id, product_name, description, purchase_date, order_number, 
                                    units_purchased, cost_per_unit, total_cost, supplier_name, notes, office_id, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                (int)$_POST['category_id'],
                $_POST['product_name'],
                $_POST['description'],
                $_POST['purchase_date'],
                $_POST['order_number'],
                $units,
                $costPerUnit,
                $totalCost,
                $_POST['supplier_name'],
                $_POST['notes'],
                getUserOfficeId(),
                $_SESSION['user_id']
            ]);
            $success = "Product purchase added successfully!";
        } catch(PDOException $e) {
            $error = "Error adding product purchase: " . $e->getMessage();
        }
    }
    
    if ($action === 'edit' && hasPermission('products_edit')) {
        try {
            // Calculate total cost
            $units = (int)$_POST['units_purchased'];
            $costPerUnit = (float)$_POST['cost_per_unit'];
            $totalCost = $units * $costPerUnit;
            
            $stmt = $pdo->prepare("
                UPDATE products 
                SET category_id = ?, product_name = ?, description = ?, purchase_date = ?, order_number = ?, 
                    units_purchased = ?, cost_per_unit = ?, total_cost = ?, supplier_name = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->execute([
                (int)$_POST['category_id'],
                $_POST['product_name'],
                $_POST['description'],
                $_POST['purchase_date'],
                $_POST['order_number'],
                $units,
                $costPerUnit,
                $totalCost,
                $_POST['supplier_name'],
                $_POST['notes'],
                (int)$_POST['product_id']
            ]);
            $success = "Product purchase updated successfully!";
        } catch(PDOException $e) {
            $error = "Error updating product purchase: " . $e->getMessage();
        }
    }
    
    if ($action === 'delete' && hasPermission('products_delete')) {
        try {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([(int)$_POST['product_id']]);
            $success = "Product purchase deleted successfully!";
        } catch(PDOException $e) {
            $error = "Error deleting product purchase: " . $e->getMessage();
        }
    }
}

// Get products with category details
try {
    $officeFilter = getOfficeFilterSQL('p', false);
    $sql = "
        SELECT p.*, pc.name as category_name, u.full_name as created_by_name
        FROM products p 
        JOIN product_categories pc ON p.category_id = pc.id 
        LEFT JOIN users u ON p.created_by = u.id
        WHERE 1=1
    ";
    
    if ($officeFilter) {
        $sql .= $officeFilter;
    }
    
    $sql .= " ORDER BY p.purchase_date DESC, p.id DESC";
    
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll();

    // Get product categories for form
    $stmt = $pdo->query("SELECT * FROM product_categories ORDER BY name");
    $categories = $stmt->fetchAll();

} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $products = [];
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - Fleet Management</title>
    <meta name="description" content="Track and manage automotive product purchases including oils, spare parts, and supplies for fleet management">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Products Management</h1>
            <p>Track product purchases and inventory</p>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (hasPermission('products_edit')): ?>
        <!-- Add Product Form -->
        <div class="section">
            <h2>Add Product Purchase</h2>
            <div class="form-container">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="category_id">Product Category</label>
                            <select id="category_id" name="category_id" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="product_name">Product Name</label>
                            <input type="text" id="product_name" name="product_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="purchase_date">Purchase Date</label>
                            <input type="date" id="purchase_date" name="purchase_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="order_number">Order Number</label>
                            <input type="text" id="order_number" name="order_number" class="form-control" placeholder="e.g., ORD-2024-001">
                        </div>
                        
                        <div class="form-group">
                            <label for="units_purchased">Units Purchased</label>
                            <input type="number" id="units_purchased" name="units_purchased" class="form-control" min="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="cost_per_unit">Cost per Unit (KSH)</label>
                            <input type="number" id="cost_per_unit" name="cost_per_unit" class="form-control" step="0.01" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="supplier_name">Supplier Name</label>
                            <input type="text" id="supplier_name" name="supplier_name" class="form-control" placeholder="e.g., AutoParts Kenya Ltd">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description (Optional)</label>
                            <input type="text" id="description" name="description" class="form-control" placeholder="e.g., 5W-30 Synthetic Oil">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes (Optional)</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Enter additional notes, storage location, etc."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Add Product Purchase</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Products List -->
        <div class="section">
            <h2>Product Purchase History</h2>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Product</th>
                            <th>Order #</th>
                            <th>Units</th>
                            <th>Cost/Unit</th>
                            <th>Total Cost</th>
                            <th>Supplier</th>
                            <th>Notes</th>
                            <?php if (hasPermission('products_edit') || hasPermission('products_delete')): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="<?php echo (hasPermission('products_edit') || hasPermission('products_delete')) ? '10' : '9'; ?>" class="no-data">No product purchases found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo formatDate($product['purchase_date']); ?></td>
                                    <td>
                                        <span class="category-badge" style="background: #e3f2fd; color: #1565c0; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">
                                            <?php echo htmlspecialchars($product['category_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="product-info">
                                            <span class="product-name" style="font-weight: 600;"><?php echo htmlspecialchars($product['product_name']); ?></span>
                                            <?php if ($product['description']): ?>
                                                <span class="product-description" style="display: block; color: #666; font-size: 0.9rem;"><?php echo htmlspecialchars($product['description']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['order_number'] ?: '-'); ?></td>
                                    <td><?php echo number_format($product['units_purchased']); ?></td>
                                    <td>KSH <?php echo number_format($product['cost_per_unit'], 2); ?></td>
                                    <td style="font-weight: 600; color: #28a745;">KSH <?php echo number_format($product['total_cost'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($product['supplier_name'] ?: '-'); ?></td>
                                    <td>
                                        <?php if (!empty($product['notes'])): ?>
                                            <div class="notes-preview">
                                                <?php echo nl2br(htmlspecialchars(substr($product['notes'], 0, 50))); ?>
                                                <?php if (strlen($product['notes']) > 50): ?>
                                                    <span class="more-notes" onclick="showFullNotes('<?php echo htmlspecialchars($product['notes'], ENT_QUOTES); ?>')">... <a href="javascript:void(0)">Show more</a></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <?php if (hasPermission('products_edit') || hasPermission('products_delete')): ?>
                                        <td>
                                            <?php if (hasPermission('products_edit')): ?>
                                                <button onclick="editProduct(<?php echo $product['id']; ?>, <?php echo $product['category_id']; ?>, '<?php echo htmlspecialchars($product['product_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($product['description'], ENT_QUOTES); ?>', '<?php echo $product['purchase_date']; ?>', '<?php echo htmlspecialchars($product['order_number'], ENT_QUOTES); ?>', <?php echo $product['units_purchased']; ?>, <?php echo $product['cost_per_unit']; ?>, '<?php echo htmlspecialchars($product['supplier_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($product['notes'], ENT_QUOTES); ?>')" class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem; margin-right: 0.5rem;">Edit</button>
                                            <?php endif; ?>
                                            <?php if (hasPermission('products_delete')): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this product purchase record?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                    <button type="submit" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 8px; width: 90%; max-width: 600px;">
            <h3>Edit Product Purchase</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="product_id" id="editProductId">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="editCategoryId">Product Category</label>
                        <select id="editCategoryId" name="category_id" class="form-control" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="editProductName">Product Name</label>
                        <input type="text" id="editProductName" name="product_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editPurchaseDate">Purchase Date</label>
                        <input type="date" id="editPurchaseDate" name="purchase_date" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editOrderNumber">Order Number</label>
                        <input type="text" id="editOrderNumber" name="order_number" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="editUnitsPurchased">Units Purchased</label>
                        <input type="number" id="editUnitsPurchased" name="units_purchased" class="form-control" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editCostPerUnit">Cost per Unit (KSH)</label>
                        <input type="number" id="editCostPerUnit" name="cost_per_unit" class="form-control" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editSupplierName">Supplier Name</label>
                        <input type="text" id="editSupplierName" name="supplier_name" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="editDescription">Description</label>
                        <input type="text" id="editDescription" name="description" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="editNotes">Notes</label>
                    <textarea id="editNotes" name="notes" class="form-control" rows="3"></textarea>
                </div>
                
                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">Update Product Purchase</button>
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Calculate total cost automatically
        function calculateTotal() {
            const units = document.getElementById('units_purchased').value;
            const costPerUnit = document.getElementById('cost_per_unit').value;
            const totalDisplay = document.getElementById('total_cost_display');
            
            if (units && costPerUnit) {
                const total = parseFloat(units) * parseFloat(costPerUnit);
                totalDisplay.value = 'KSH ' + total.toFixed(2);
            } else {
                totalDisplay.value = '';
            }
        }

        // Add event listeners for real-time calculation
        document.getElementById('units_purchased').addEventListener('input', calculateTotal);
        document.getElementById('cost_per_unit').addEventListener('input', calculateTotal);

        function editProduct(id, categoryId, productName, description, purchaseDate, orderNumber, units, costPerUnit, supplierName, notes) {
            document.getElementById('editProductId').value = id;
            document.getElementById('editCategoryId').value = categoryId;
            document.getElementById('editProductName').value = productName;
            document.getElementById('editDescription').value = description || '';
            document.getElementById('editPurchaseDate').value = purchaseDate;
            document.getElementById('editOrderNumber').value = orderNumber || '';
            document.getElementById('editUnitsPurchased').value = units;
            document.getElementById('editCostPerUnit').value = costPerUnit;
            document.getElementById('editSupplierName').value = supplierName || '';
            document.getElementById('editNotes').value = notes || '';
            
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
        
        // Show full notes modal
        function showFullNotes(notes) {
            alert(notes); // Simple approach - you can create a proper modal if needed
        }
    </script>
</body>
</html>