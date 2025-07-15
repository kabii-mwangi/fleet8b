<?php
require_once 'config.php';
requireAuth();
requirePermission('products_view');

$success = '';
$error = '';

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
            $success = "Product record added successfully!";
        } catch(PDOException $e) {
            $error = "Error adding product record: " . $e->getMessage();
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
            $success = "Product record updated successfully!";
        } catch(PDOException $e) {
            $error = "Error updating product record: " . $e->getMessage();
        }
    }
    
    if ($action === 'delete' && hasPermission('products_delete')) {
        try {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([(int)$_POST['product_id']]);
            $success = "Product record deleted successfully!";
        } catch(PDOException $e) {
            $error = "Error deleting product record: " . $e->getMessage();
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
    <title>Product Management - Fleet Management</title>
    <meta name="description" content="Manage product purchases including engine oils, spare parts and other automotive products">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Product Management</h1>
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
                            <input type="text" id="order_number" name="order_number" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="units_purchased">Units Purchased</label>
                            <input type="number" id="units_purchased" name="units_purchased" class="form-control" min="1" required onchange="calculateTotal()">
                        </div>
                        
                        <div class="form-group">
                            <label for="cost_per_unit">Cost per Unit (KSH)</label>
                            <input type="number" id="cost_per_unit" name="cost_per_unit" class="form-control" step="0.01" min="0" required onchange="calculateTotal()">
                        </div>
                        
                        <div class="form-group">
                            <label for="total_cost_display">Total Cost (KSH)</label>
                            <input type="text" id="total_cost_display" class="form-control" readonly style="background-color: #f5f5f5;">
                        </div>
                        
                        <div class="form-group">
                            <label for="supplier_name">Supplier Name</label>
                            <input type="text" id="supplier_name" name="supplier_name" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Add Product</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Products List -->
        <div class="section">
            <h2>Product Purchases</h2>
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
                            <th>Created By</th>
                            <?php if (hasPermission('products_edit') || hasPermission('products_delete')): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="<?php echo hasPermission('products_edit') || hasPermission('products_delete') ? '10' : '9'; ?>" style="text-align: center; color: #666;">
                                    No product purchases found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($product['purchase_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                        <?php if ($product['description']): ?>
                                            <br><small style="color: #666;"><?php echo htmlspecialchars($product['description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['order_number'] ?: '-'); ?></td>
                                    <td><?php echo number_format($product['units_purchased']); ?></td>
                                    <td>KSH <?php echo number_format($product['cost_per_unit'], 2); ?></td>
                                    <td><strong>KSH <?php echo number_format($product['total_cost'], 2); ?></strong></td>
                                    <td><?php echo htmlspecialchars($product['supplier_name'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($product['created_by_name'] ?: 'Unknown'); ?></td>
                                    <?php if (hasPermission('products_edit') || hasPermission('products_delete')): ?>
                                        <td>
                                            <?php if (hasPermission('products_edit')): ?>
                                                <button class="btn btn-small btn-secondary" onclick="editProduct(<?php echo $product['id']; ?>)">Edit</button>
                                            <?php endif; ?>
                                            <?php if (hasPermission('products_delete')): ?>
                                                <button class="btn btn-small btn-danger" onclick="deleteProduct(<?php echo $product['id']; ?>)">Delete</button>
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
    <div id="editModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Edit Product</h2>
            <form id="editForm" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="product_id" id="edit_product_id">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="edit_category_id">Product Category</label>
                        <select id="edit_category_id" name="category_id" class="form-control" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_product_name">Product Name</label>
                        <input type="text" id="edit_product_name" name="product_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_purchase_date">Purchase Date</label>
                        <input type="date" id="edit_purchase_date" name="purchase_date" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_order_number">Order Number</label>
                        <input type="text" id="edit_order_number" name="order_number" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_units_purchased">Units Purchased</label>
                        <input type="number" id="edit_units_purchased" name="units_purchased" class="form-control" min="1" required onchange="calculateEditTotal()">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_cost_per_unit">Cost per Unit (KSH)</label>
                        <input type="number" id="edit_cost_per_unit" name="cost_per_unit" class="form-control" step="0.01" min="0" required onchange="calculateEditTotal()">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_total_cost_display">Total Cost (KSH)</label>
                        <input type="text" id="edit_total_cost_display" class="form-control" readonly style="background-color: #f5f5f5;">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_supplier_name">Supplier Name</label>
                        <input type="text" id="edit_supplier_name" name="supplier_name" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_notes">Notes</label>
                    <textarea id="edit_notes" name="notes" class="form-control" rows="3"></textarea>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Product</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const products = <?php echo json_encode($products); ?>;
        
        function calculateTotal() {
            const units = parseFloat(document.getElementById('units_purchased').value) || 0;
            const costPerUnit = parseFloat(document.getElementById('cost_per_unit').value) || 0;
            const total = units * costPerUnit;
            document.getElementById('total_cost_display').value = 'KSH ' + total.toFixed(2);
        }
        
        function calculateEditTotal() {
            const units = parseFloat(document.getElementById('edit_units_purchased').value) || 0;
            const costPerUnit = parseFloat(document.getElementById('edit_cost_per_unit').value) || 0;
            const total = units * costPerUnit;
            document.getElementById('edit_total_cost_display').value = 'KSH ' + total.toFixed(2);
        }
        
        function editProduct(id) {
            const product = products.find(p => p.id == id);
            if (!product) return;
            
            document.getElementById('edit_product_id').value = product.id;
            document.getElementById('edit_category_id').value = product.category_id;
            document.getElementById('edit_product_name').value = product.product_name;
            document.getElementById('edit_purchase_date').value = product.purchase_date;
            document.getElementById('edit_order_number').value = product.order_number || '';
            document.getElementById('edit_units_purchased').value = product.units_purchased;
            document.getElementById('edit_cost_per_unit').value = product.cost_per_unit;
            document.getElementById('edit_supplier_name').value = product.supplier_name || '';
            document.getElementById('edit_description').value = product.description || '';
            document.getElementById('edit_notes').value = product.notes || '';
            
            calculateEditTotal();
            document.getElementById('editModal').style.display = 'block';
        }
        
        function deleteProduct(id) {
            if (confirm('Are you sure you want to delete this product record?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="product_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>