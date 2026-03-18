<?php
// Start output buffering at the very beginning
ob_start();
require_once "includes/header.php";

// Function to generate CSV
function generateCSV($filename, $headers, $data) {
    // Clean any output that might have been sent
    ob_clean();
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, $headers);
    
    // Add data
    foreach($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

// Function to generate PDF
function generatePDF($filename, $title, $headers, $data) {
    // Clean any output that might have been sent
    ob_clean();
    
    // Generate HTML content
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($title) . '</title>
        <style>
            @media print {
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #2d3436; text-align: center; margin-bottom: 20px; }
                .date { text-align: right; color: #666; margin-bottom: 30px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th { background-color: #f8f9fa !important; padding: 10px; border: 1px solid #dee2e6; }
                td { padding: 8px; border: 1px solid #dee2e6; }
                tr:nth-child(even) { background-color: #f8f9fa !important; }
                @page { size: A4; margin: 2cm; }
            }
        </style>
    </head>
    <body>
        <h1>' . htmlspecialchars($title) . '</h1>
        <div class="date">Generated on: ' . date('Y-m-d H:i:s') . '</div>
        <table>
            <thead>
                <tr>';
    
    // Add headers
    foreach($headers as $header) {
        $html .= '<th>' . htmlspecialchars($header) . '</th>';
    }
    
    $html .= '</tr>
            </thead>
            <tbody>';
    
    // Add data rows
    foreach($data as $row) {
        $html .= '<tr>';
        foreach($row as $cell) {
            $html .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    
    $html .= '</tbody>
        </table>
        <script>
            window.onload = function() {
                window.print();
            };
        </script>
    </body>
    </html>';
    
    // Output the HTML
    echo $html;
    exit();
}

// Handle report generation
if(isset($_POST['generate_report'])) {
    // Clean output buffer before processing report
    ob_clean();
    
    $report_type = $_POST['report_type'];
    $report_format = $_POST['report_format'] ?? 'csv';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    
    switch($report_type) {
        case 'movement_history':
            $sql = "SELECT m.created_at, i.name as item_name, m.movement_type, 
                    m.delivery_type, m.quantity, m.notes, u.fullName as created_by 
                    FROM inventory_movements m 
                    JOIN inventory_items i ON m.item_id = i.id 
                    JOIN users u ON m.user_id = u.id";
            
            if(!empty($start_date) && !empty($end_date)) {
                $sql .= " WHERE m.created_at BETWEEN ? AND ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
            } else {
                $stmt = mysqli_prepare($conn, $sql);
            }
            
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $data = [];
            while($row = mysqli_fetch_assoc($result)) {
                $data[] = [
                    date('Y-m-d H:i', strtotime($row['created_at'])),
                    $row['item_name'],
                    $row['movement_type'],
                    $row['delivery_type'],
                    $row['quantity'],
                    $row['notes'],
                    $row['created_by']
                ];
            }
            
            $headers = ['Date', 'Item', 'Movement Type', 'Delivery Type', 'Quantity', 'Notes', 'Created By'];
            $filename = 'Movement_History_Report_' . date('Y-m-d');
            $title = 'Movement History Report';
            
            if($report_format === 'pdf') {
                generatePDF($filename, $title, $headers, $data);
            } else {
                generateCSV($filename, $headers, $data);
            }
            break;
            
        case 'inventory_items':
            $sql = "SELECT i.sku, i.name, i.category, i.quantity, i.unit, 
                    w.name as warehouse_name, s.name as supplier_name 
                    FROM inventory_items i 
                    LEFT JOIN warehouses w ON i.warehouse_id = w.id 
                    LEFT JOIN suppliers s ON i.supplier_id = s.id";
            $result = mysqli_query($conn, $sql);
            
            $data = [];
            while($row = mysqli_fetch_assoc($result)) {
                $data[] = [
                    $row['sku'],
                    $row['name'],
                    $row['category'],
                    $row['quantity'] . ' ' . $row['unit'],
                    $row['warehouse_name'],
                    $row['supplier_name']
                ];
            }
            
            $headers = ['SKU', 'Name', 'Category', 'Quantity', 'Warehouse', 'Supplier'];
            $filename = 'Inventory_Items_Report_' . date('Y-m-d');
            $title = 'Inventory Items Report';
            
            if($report_format === 'pdf') {
                generatePDF($filename, $title, $headers, $data);
            } else {
                generateCSV($filename, $headers, $data);
            }
            break;
            
        case 'low_stock':
            $sql = "SELECT i.sku, i.name, i.category, i.quantity, i.minimum_stock, i.unit, 
                    w.name as warehouse_name 
                    FROM inventory_items i 
                    LEFT JOIN warehouses w ON i.warehouse_id = w.id 
                    WHERE i.quantity <= i.minimum_stock";
            $result = mysqli_query($conn, $sql);
            
            $data = [];
            while($row = mysqli_fetch_assoc($result)) {
                $data[] = [
                    $row['sku'],
                    $row['name'],
                    $row['category'],
                    $row['quantity'] . ' ' . $row['unit'],
                    $row['minimum_stock'] . ' ' . $row['unit'],
                    $row['warehouse_name']
                ];
            }
            
            $headers = ['SKU', 'Name', 'Category', 'Current Quantity', 'Minimum Stock', 'Warehouse'];
            $filename = 'Low_Stock_Report_' . date('Y-m-d');
            $title = 'Low Stock Items Report';
            
            if($report_format === 'pdf') {
                generatePDF($filename, $title, $headers, $data);
            } else {
                generateCSV($filename, $headers, $data);
            }
            break;
            
        case 'supplier_list':
            $sql = "SELECT name, contact_person, email, phone, address 
                    FROM suppliers 
                    ORDER BY name";
            $result = mysqli_query($conn, $sql);
            
            $data = [];
            while($row = mysqli_fetch_assoc($result)) {
                $data[] = [
                    $row['name'],
                    $row['contact_person'] ?? '-',
                    $row['email'] ?? '-',
                    $row['phone'] ?? '-',
                    $row['address'] ?? '-'
                ];
            }
            
            $headers = ['Supplier Name', 'Contact Person', 'Email', 'Phone', 'Address'];
            $filename = 'Supplier_List_Report_' . date('Y-m-d');
            $title = 'Supplier List Report';
            
            if($report_format === 'pdf') {
                generatePDF($filename, $title, $headers, $data);
            } else {
                generateCSV($filename, $headers, $data);
            }
            break;
    }
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="page-title">Reports</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Reports</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Generate Report</h5>
                            
                            <form method="post" target="_blank">
                                <div class="mb-3">
                                    <label class="form-label">Report Type</label>
                                    <select class="form-select" name="report_type" id="reportType" required>
                                        <option value="">Select Report Type</option>
                                        <option value="movement_history">Movement History</option>
                                        <option value="inventory_items">Inventory Items</option>
                                        <option value="low_stock">Low Stock Items</option>
                                        <option value="supplier_list">Supplier List</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3 date-range">
                                    <label class="form-label">Date Range</label>
                                    <input type="date" class="form-control mb-2" name="start_date">
                                    <input type="date" class="form-control" name="end_date">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Report Format</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="report_format" id="formatCSV" value="csv" checked>
                                        <label class="form-check-label" for="formatCSV">
                                            <i class='bx bx-file'></i> CSV (Excel)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="report_format" id="formatPDF" value="pdf">
                                        <label class="form-check-label" for="formatPDF">
                                            <i class='bx bx-file-pdf'></i> PDF
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" name="generate_report" class="btn btn-primary">
                                    <i class='bx bx-download me-1'></i> Generate Report
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Report Information</h5>
                            
                            <div class="alert alert-info">
                                <h6 class="alert-heading">Available Reports:</h6>
                                <ul class="mb-0">
                                    <li><strong>Movement History Report</strong> - Shows all movement records with delivery details</li>
                                    <li><strong>Inventory Items Report</strong> - Shows current inventory status for all items</li>
                                    <li><strong>Low Stock Report</strong> - Shows items that are at or below minimum stock level</li>
                                    <li><strong>Supplier List Report</strong> - Shows complete list of suppliers with their contact details</li>
                                </ul>
                            </div>
                            
                            <p class="text-muted">Reports can be downloaded in either CSV format (for Excel) or PDF format for easy viewing and printing.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.content-wrapper {
    padding: 20px;
    min-height: calc(100vh - 60px);
    background: #f8f9fa;
}

.content-header {
    margin-bottom: 2rem;
}

.page-title {
    font-size: 1.75rem;
    font-weight: 600;
    color: #2d3436;
    margin-bottom: 0.5rem;
}

.breadcrumb {
    margin-bottom: 0;
    background: transparent;
    padding: 0;
}

.breadcrumb-item a {
    color: #6c757d;
    text-decoration: none;
}

.breadcrumb-item.active {
    color: #343a40;
}

.card {
    border: none;
    box-shadow: 0 0 20px rgba(0,0,0,0.05);
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.card-title {
    color: #2d3436;
    font-weight: 600;
    margin-bottom: 1.5rem;
}

.alert-info {
    background-color: #e3f2fd;
    border-color: #90caf9;
    color: #0d47a1;
}

.alert-info ul {
    padding-left: 1.2rem;
}

.alert-info li {
    margin-bottom: 0.5rem;
}

.alert-info li:last-child {
    margin-bottom: 0;
}

@media (min-width: 992px) {
    .content-wrapper {
        padding: 30px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const reportType = document.querySelector('#reportType');
    const dateRange = document.querySelector('.date-range');
    
    reportType.addEventListener('change', function() {
        // Show/hide date range based on report type
        if(this.value === 'movement_history') {
            dateRange.style.display = 'block';
        } else {
            dateRange.style.display = 'none';
            dateRange.querySelectorAll('input').forEach(input => input.value = '');
        }
    });
});
</script>

<?php require_once "includes/footer.php"; ?> 