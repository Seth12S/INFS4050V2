<?php 
$basePath = '../../';
include '../../templates/session/private_session.php'; 

// Check if user has permission to view this page
if (!in_array($security_clearance, [1, 2, 3])) { // System Admin, VP, SVP
    header("Location: ../unauthorized.php");
    exit();
}

// Include PhpSpreadsheet
require_once '../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// Variables for messages
$message = '';
$messageType = '';

// Function to load Excel file
function loadBonusExcel() {
    $excelFile = "../../assets/excel/PerformanceBonus.xlsx";
    
    // Check if file exists
    if (!file_exists($excelFile)) {
        return ["error" => "Excel file not found"];
    }
    
    try {
        // Load the spreadsheet
        $spreadsheet = IOFactory::load($excelFile);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Get worksheet data
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        
        // Load headers
        $headers = [];
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $headers[] = $worksheet->getCellByColumnAndRow($col, 1)->getValue();
        }
        
        // Load data
        $data = [];
        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $rowData[] = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
            }
            $data[] = $rowData;
        }
        
        return [
            'headers' => $headers,
            'data' => $data
        ];
    } catch (Exception $e) {
        return ["error" => "Error loading Excel file: " . $e->getMessage()];
    }
}

// Function to save data to database
function saveBonusData($conn, $data) {
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // First, clear existing bonus data
        $clearQuery = "TRUNCATE TABLE FedEx_Performance_Bonus";
        $conn->query($clearQuery);
        
        // Prepare insert statement
        $insertQuery = "INSERT INTO FedEx_Performance_Bonus (e_id, performance_rating, bonus_percentage, bonus_amount) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insertQuery);
        
        // Insert each row
        foreach ($data as $row) {
            $e_id = $row[0]; // Assuming employee ID is in first column
            $rating = $row[1]; // Assuming performance rating is in second column
            $percentage = $row[2]; // Assuming bonus percentage is in third column
            $amount = $row[3]; // Assuming bonus amount is in fourth column
            
            $stmt->bind_param("sddd", $e_id, $rating, $percentage, $amount);
            $stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        return ["success" => "Bonus data saved successfully to database"];
    } catch (Exception $e) {
        // Rollback in case of error
        $conn->rollback();
        return ["error" => "Error saving data to database: " . $e->getMessage()];
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_bonus'])) {
    // Get data from form
    $postData = json_decode($_POST['bonus_data'], true);
    
    if ($postData) {
        // Save to database
        $result = saveBonusData($conn, $postData);
        
        if (isset($result['success'])) {
            $message = $result['success'];
            $messageType = 'success';
        } else {
            $message = $result['error'];
            $messageType = 'error';
        }
    } else {
        $message = "Invalid data format";
        $messageType = 'error';
    }
}

// Load Excel data
$excelData = loadBonusExcel();
?>

<!DOCTYPE html>
<html lang="en">

<?php 
    $pageTitle = 'Bonus Editor';
    include '../../templates/layouts/head.php'; 
?>

<body>
    <!-- Header -->
    <?php include '../../templates/layouts/header.php'; ?>

    <!-- Main content -->
    <main class="reports-container">
        <div class="reports-content">
            <h1>Performance Bonus Editor</h1>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($excelData['error'])): ?>
                <div class="alert alert-error">
                    <?php echo $excelData['error']; ?>
                </div>
            <?php else: ?>
                <div class="bonus-editor-container">
                    <form method="POST" action="">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="bonusTable">
                                <thead>
                                    <tr>
                                        <?php foreach ($excelData['headers'] as $header): ?>
                                            <th><?php echo htmlspecialchars($header); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($excelData['data'] as $rowIndex => $row): ?>
                                        <tr>
                                            <?php foreach ($row as $colIndex => $cell): ?>
                                                <td>
                                                    <input 
                                                        type="text" 
                                                        class="bonus-cell" 
                                                        data-row="<?php echo $rowIndex; ?>" 
                                                        data-col="<?php echo $colIndex; ?>" 
                                                        value="<?php echo htmlspecialchars($cell); ?>"
                                                        <?php echo $colIndex == 0 ? 'readonly' : ''; ?> 
                                                    >
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <input type="hidden" name="bonus_data" id="bonus_data">
                        <button type="submit" name="save_bonus" class="btn btn-primary" onclick="prepareData()">
                            Save Changes to Database
                        </button>
                    </form>
                </div>
                
                <script>
                    function prepareData() {
                        const table = document.getElementById('bonusTable');
                        const rows = table.querySelectorAll('tbody tr');
                        const data = [];
                        
                        rows.forEach(row => {
                            const inputs = row.querySelectorAll('input.bonus-cell');
                            const rowData = [];
                            
                            inputs.forEach(input => {
                                rowData.push(input.value);
                            });
                            
                            data.push(rowData);
                        });
                        
                        document.getElementById('bonus_data').value = JSON.stringify(data);
                    }
                </script>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <?php include '../../templates/layouts/footer.php'; ?>
</body>
</html> 