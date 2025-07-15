<?php
require_once 'config.php';
requireAuth();
requirePermission('reports_view');

// Determine report type
$reportType = $_GET['type'] ?? 'fuel';

// Handle form submissions for filtering
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$vehicleFilter = $_GET['vehicle_id'] ?? '';
$categoryFilter = $_GET['category_id'] ?? '';
$officeFilter = $_GET['office_id'] ?? '';
$productCategoryFilter = $_GET['product_category_id'] ?? '';

// Initialize variables
$reportData = [];
$totalCost = 0;
$totalRecords = 0;
$summary = [];

try {
    $baseOfficeFilter = getOfficeFilterSQL('v', false);
    
    if ($reportType === 'fuel') {
        // FUEL LOGS REPORT
        $sql = "
            SELECT fl.*, v.registration_number, v.make, v.model, vc.name as category_name, 
                   e.name as employee_name, o.name as office_name
            FROM fuel_logs fl 
            JOIN vehicles v ON fl.vehicle_id = v.id 
            JOIN vehicle_categories vc ON v.category_id = vc.id 
            LEFT JOIN employees e ON v.assigned_employee_id = e.id 
            LEFT JOIN offices o ON v.office_id = o.id 
            WHERE fl.date BETWEEN ? AND ?
        ";
        
        $params = [$startDate, $endDate];
        
        if ($baseOfficeFilter) {
            $sql .= $baseOfficeFilter;
        }
        
        if ($vehicleFilter) {
            $sql .= " AND v.id = ?";
            $params[] = (int)$vehicleFilter;
        }
        
        if ($categoryFilter) {
            $sql .= " AND v.category_id = ?";
            $params[] = (int)$categoryFilter;
        }
        
        if ($officeFilter && isSuperAdmin()) {
            $sql .= " AND v.office_id = ?";
            $params[] = (int)$officeFilter;
        }
        
        $sql .= " ORDER BY fl.date DESC, fl.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();
        
        // Calculate totals for fuel
        $totalCost = array_sum(array_column($reportData, 'cost'));
        $totalFuel = array_sum(array_column($reportData, 'fuel_quantity'));
        $totalRecords = count($reportData);
        $totalVehicles = count(array_unique(array_column($reportData, 'vehicle_id')));
        
        $summary = [
            ['label' => 'Total Fuel Cost', 'value' => 'KSH ' . number_format($totalCost, 2)],
            ['label' => 'Total Fuel Consumed', 'value' => number_format($totalFuel, 1) . 'L'],
            ['label' => 'Total Vehicles', 'value' => $totalVehicles],
            ['label' => 'Total Fuel Logs', 'value' => $totalRecords]
        ];
        
    } elseif ($reportType === 'maintenance') {
        // MAINTENANCE REPORT
        $baseMaintenanceFilter = getOfficeFilterSQL('vm', false);
        
        $sql = "
            SELECT vm.*, v.registration_number, v.make, v.model, vc.name as category_name, 
                   u.full_name as created_by_name, o.name as office_name
            FROM vehicle_maintenance vm 
            JOIN vehicles v ON vm.vehicle_id = v.id 
            JOIN vehicle_categories vc ON v.category_id = vc.id 
            LEFT JOIN users u ON vm.created_by = u.id 
            LEFT JOIN offices o ON v.office_id = o.id 
            WHERE vm.maintenance_date BETWEEN ? AND ?
        ";
        
        $params = [$startDate, $endDate];
        
        if ($baseMaintenanceFilter) {
            $sql .= str_replace('v.office_id', 'vm.office_id', $baseMaintenanceFilter);
        }
        
        if ($vehicleFilter) {
            $sql .= " AND v.id = ?";
            $params[] = (int)$vehicleFilter;
        }
        
        if ($categoryFilter) {
            $sql .= " AND v.category_id = ?";
            $params[] = (int)$categoryFilter;
        }
        
        if ($officeFilter && isSuperAdmin()) {
            $sql .= " AND vm.office_id = ?";
            $params[] = (int)$officeFilter;
        }
        
        $sql .= " ORDER BY vm.maintenance_date DESC, vm.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();
        
        // Calculate totals for maintenance
        $totalCost = array_sum(array_column($reportData, 'cost'));
        $totalRecords = count($reportData);
        $totalVehicles = count(array_unique(array_column($reportData, 'vehicle_id')));
        $scheduledCount = count(array_filter($reportData, fn($r) => $r['maintenance_type'] === 'scheduled'));
        $repairCount = count(array_filter($reportData, fn($r) => $r['maintenance_type'] === 'repair'));
        $emergencyCount = count(array_filter($reportData, fn($r) => $r['maintenance_type'] === 'emergency'));
        
        $summary = [
            ['label' => 'Total Maintenance Cost', 'value' => 'KSH ' . number_format($totalCost, 2)],
            ['label' => 'Total Vehicles', 'value' => $totalVehicles],
            ['label' => 'Scheduled Maintenance', 'value' => $scheduledCount],
            ['label' => 'Repairs & Emergency', 'value' => ($repairCount + $emergencyCount)]
        ];
        
    } elseif ($reportType === 'products') {
        // PRODUCTS REPORT
        $baseProductFilter = getOfficeFilterSQL('p', false);
        
        $sql = "
            SELECT p.*, pc.name as category_name, u.full_name as created_by_name, o.name as office_name
            FROM products p 
            JOIN product_categories pc ON p.category_id = pc.id 
            LEFT JOIN users u ON p.created_by = u.id 
            LEFT JOIN offices o ON p.office_id = o.id 
            WHERE p.purchase_date BETWEEN ? AND ?
        ";
        
        $params = [$startDate, $endDate];
        
        if ($baseProductFilter) {
            $sql .= $baseProductFilter;
        }
        
        if ($productCategoryFilter) {
            $sql .= " AND p.category_id = ?";
            $params[] = (int)$productCategoryFilter;
        }
        
        if ($officeFilter && isSuperAdmin()) {
            $sql .= " AND p.office_id = ?";
            $params[] = (int)$officeFilter;
        }
        
        $sql .= " ORDER BY p.purchase_date DESC, p.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();
        
        // Calculate totals for products
        $totalCost = array_sum(array_column($reportData, 'total_cost'));
        $totalUnits = array_sum(array_column($reportData, 'units_purchased'));
        $totalRecords = count($reportData);
        $totalCategories = count(array_unique(array_column($reportData, 'category_id')));
        
        $summary = [
            ['label' => 'Total Purchase Cost', 'value' => 'KSH ' . number_format($totalCost, 2)],
            ['label' => 'Total Units Purchased', 'value' => number_format($totalUnits)],
            ['label' => 'Total Product Categories', 'value' => $totalCategories],
            ['label' => 'Total Purchase Records', 'value' => $totalRecords]
        ];
    }
    
    // Get filter options
    $stmt = $pdo->query("SELECT * FROM vehicle_categories ORDER BY name");
    $categories = $stmt->fetchAll();
    
    $vehicleQuery = "
        SELECT v.*, vc.name as category_name, o.name as office_name 
        FROM vehicles v 
        JOIN vehicle_categories vc ON v.category_id = vc.id 
        LEFT JOIN offices o ON v.office_id = o.id 
        WHERE v.status = 'active'
    ";
    if (!isSuperAdmin()) {
        $vehicleQuery .= " AND v.office_id = " . getUserOfficeId();
    }
    $vehicleQuery .= " ORDER BY v.registration_number";
    
    $stmt = $pdo->query($vehicleQuery);
    $vehicles = $stmt->fetchAll();
    
    $productCategories = [];
    if ($reportType === 'products') {
        $stmt = $pdo->query("SELECT * FROM product_categories ORDER BY name");
        $productCategories = $stmt->fetchAll();
    }
    
    $offices = [];
    if (isSuperAdmin()) {
        $stmt = $pdo->query("SELECT * FROM offices ORDER BY name");
        $offices = $stmt->fetchAll();
    }
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $reportData = [];
}

function formatCurrency($amount) {
    return 'KSH ' . number_format($amount, 2);
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($reportType); ?> Report - Fleet Management</title>
    <meta name="description" content="Fleet management reports with detailed analytics and filtering options">
    <link rel="stylesheet" href="styles.css">
    <style>
        .report-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .report-tab {
            padding: 10px 20px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-bottom: none;
            text-decoration: none;
            color: #666;
            border-radius: 5px 5px 0 0;
        }
        
        .report-tab.active {
            background: white;
            color: #333;
            border-color: #e0e0e0;
            font-weight: 600;
        }
        
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .summary-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .summary-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><?php echo ucfirst($reportType); ?> Report</h1>
            <p>Detailed analytics and insights for your fleet management</p>
        </div>

        <!-- Report Type Tabs -->
        <div class="report-tabs">
            <a href="reports.php?type=fuel" class="report-tab <?php echo $reportType === 'fuel' ? 'active' : ''; ?>">
                Fuel Logs Report
            </a>
            <a href="reports.php?type=maintenance" class="report-tab <?php echo $reportType === 'maintenance' ? 'active' : ''; ?>">
                Maintenance Report
            </a>
            <a href="reports.php?type=products" class="report-tab <?php echo $reportType === 'products' ? 'active' : ''; ?>">
                Product Report
            </a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Filter Form -->
        <div class="section">
            <h2>Report Filters</h2>
            <form method="GET" class="filter-form">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($reportType); ?>">
                
                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                </div>
                
                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
                
                <?php if ($reportType === 'products'): ?>
                    <div class="form-group">
                        <label for="product_category_id">Product Category</label>
                        <select id="product_category_id" name="product_category_id" class="form-control">
                            <option value="">All Categories</option>
                            <?php foreach ($productCategories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $productCategoryFilter == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label for="category_id">Vehicle Category</label>
                        <select id="category_id" name="category_id" class="form-control">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $categoryFilter == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="vehicle_id">Specific Vehicle</label>
                        <select id="vehicle_id" name="vehicle_id" class="form-control">
                            <option value="">All Vehicles</option>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <option value="<?php echo $vehicle['id']; ?>" <?php echo $vehicleFilter == $vehicle['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vehicle['registration_number'] . ' - ' . $vehicle['make'] . ' ' . $vehicle['model']); ?>
                                    <?php if (isSuperAdmin()): ?>
                                        (<?php echo htmlspecialchars($vehicle['office_name']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <?php if (isSuperAdmin()): ?>
                    <div class="form-group">
                        <label for="office_id">Office/Section</label>
                        <select id="office_id" name="office_id" class="form-control">
                            <option value="">All Offices</option>
                            <?php foreach ($offices as $office): ?>
                                <option value="<?php echo $office['id']; ?>" <?php echo $officeFilter == $office['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($office['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="form-group" style="display: flex; align-items: end; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                    <button type="button" onclick="window.print()" class="btn btn-secondary">Print Report</button>
                </div>
            </form>
        </div>

        <!-- Summary Statistics -->
        <div class="stats-summary">
            <?php foreach ($summary as $stat): ?>
                <div class="summary-card">
                    <div class="summary-value"><?php echo htmlspecialchars($stat['value']); ?></div>
                    <div class="summary-label"><?php echo htmlspecialchars($stat['label']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Detailed Report Data -->
        <div class="section">
            <h2>Detailed <?php echo ucfirst($reportType); ?> Records (<?php echo formatDate($startDate); ?> to <?php echo formatDate($endDate); ?>)</h2>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <?php if ($reportType === 'fuel'): ?>
                                <th>Date</th>
                                <th>Vehicle</th>
                                <th>Category</th>
                                <?php if (isSuperAdmin()): ?><th>Office</th><?php endif; ?>
                                <th>Employee</th>
                                <th>Mileage</th>
                                <th>Fuel (L)</th>
                                <th>Cost</th>
                                <th>Cost/L</th>
                                <th>Notes</th>
                            <?php elseif ($reportType === 'maintenance'): ?>
                                <th>Date</th>
                                <th>Vehicle</th>
                                <th>Category</th>
                                <?php if (isSuperAdmin()): ?><th>Office</th><?php endif; ?>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Cost</th>
                                <th>Mechanic</th>
                                <th>Status</th>
                                <th>Notes</th>
                            <?php elseif ($reportType === 'products'): ?>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Product</th>
                                <th>Order #</th>
                                <th>Units</th>
                                <th>Cost/Unit</th>
                                <th>Total Cost</th>
                                <th>Supplier</th>
                                <?php if (isSuperAdmin()): ?><th>Office</th><?php endif; ?>
                                <th>Created By</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr>
                                <td colspan="<?php echo $reportType === 'products' ? (isSuperAdmin() ? '10' : '9') : (isSuperAdmin() ? '10' : '9'); ?>" style="text-align: center; color: #666;">
                                    No <?php echo $reportType; ?> records found for the selected period
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reportData as $record): ?>
                                <tr>
                                    <?php if ($reportType === 'fuel'): ?>
                                        <td><?php echo formatDate($record['date']); ?></td>
                                        <td>
                                            <div class="vehicle-info">
                                                <span class="registration"><?php echo htmlspecialchars($record['registration_number']); ?></span>
                                                <span class="vehicle-details"><?php echo htmlspecialchars($record['make'] . ' ' . $record['model']); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['category_name']); ?></td>
                                        <?php if (isSuperAdmin()): ?>
                                            <td><?php echo htmlspecialchars($record['office_name']); ?></td>
                                        <?php endif; ?>
                                        <td><?php echo $record['employee_name'] ? htmlspecialchars($record['employee_name']) : '<em>Unassigned</em>'; ?></td>
                                        <td><?php echo number_format($record['mileage']); ?> km</td>
                                        <td><?php echo number_format($record['fuel_quantity'], 1); ?>L</td>
                                        <td><?php echo formatCurrency($record['cost']); ?></td>
                                        <td><?php echo formatCurrency($record['cost'] / $record['fuel_quantity']); ?></td>
                                        <td><?php echo htmlspecialchars($record['notes'] ?: '-'); ?></td>
                                    <?php elseif ($reportType === 'maintenance'): ?>
                                        <td><?php echo formatDate($record['maintenance_date']); ?></td>
                                        <td>
                                            <div class="vehicle-info">
                                                <span class="registration"><?php echo htmlspecialchars($record['registration_number']); ?></span>
                                                <span class="vehicle-details"><?php echo htmlspecialchars($record['make'] . ' ' . $record['model']); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['category_name']); ?></td>
                                        <?php if (isSuperAdmin()): ?>
                                            <td><?php echo htmlspecialchars($record['office_name']); ?></td>
                                        <?php endif; ?>
                                        <td><span class="badge badge-<?php echo $record['maintenance_type']; ?>"><?php echo ucfirst($record['maintenance_type']); ?></span></td>
                                        <td><?php echo htmlspecialchars($record['description']); ?></td>
                                        <td><?php echo formatCurrency($record['cost']); ?></td>
                                        <td><?php echo htmlspecialchars($record['mechanic_name'] ?: '-'); ?></td>
                                        <td><span class="badge badge-<?php echo $record['status']; ?>"><?php echo ucfirst($record['status']); ?></span></td>
                                        <td><?php echo htmlspecialchars($record['notes'] ?: '-'); ?></td>
                                    <?php elseif ($reportType === 'products'): ?>
                                        <td><?php echo formatDate($record['purchase_date']); ?></td>
                                        <td><?php echo htmlspecialchars($record['category_name']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($record['product_name']); ?></strong>
                                            <?php if ($record['description']): ?>
                                                <br><small style="color: #666;"><?php echo htmlspecialchars($record['description']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['order_number'] ?: '-'); ?></td>
                                        <td><?php echo number_format($record['units_purchased']); ?></td>
                                        <td><?php echo formatCurrency($record['cost_per_unit']); ?></td>
                                        <td><strong><?php echo formatCurrency($record['total_cost']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($record['supplier_name'] ?: '-'); ?></td>
                                        <?php if (isSuperAdmin()): ?>
                                            <td><?php echo htmlspecialchars($record['office_name']); ?></td>
                                        <?php endif; ?>
                                        <td><?php echo htmlspecialchars($record['created_by_name'] ?: 'Unknown'); ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            color: white;
        }
        
        .badge-scheduled { background-color: #28a745; }
        .badge-repair { background-color: #ffc107; color: #333; }
        .badge-emergency { background-color: #dc3545; }
        .badge-planned { background-color: #6c757d; }
        .badge-in_progress { background-color: #007bff; }
        .badge-completed { background-color: #28a745; }
        .badge-cancelled { background-color: #dc3545; }
        
        .vehicle-info .registration {
            font-weight: 600;
            display: block;
        }
        
        .vehicle-info .vehicle-details {
            font-size: 0.9rem;
            color: #666;
        }
    </style>
</body>
</html>