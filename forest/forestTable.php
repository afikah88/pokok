<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "forestdb";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Pagination setup
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Get list of available block coordinates
$blockQuery = "SELECT DISTINCT block_x, block_y FROM tree_data ORDER BY block_x, block_y";
$blockResult = $conn->query($blockQuery);

// Prepare the main query
if (isset($_GET['block']) && $_GET['block'] != '') {
    list($block_x, $block_y) = explode(',', $_GET['block']);
    $block_x = $conn->real_escape_string($block_x);
    $block_y = $conn->real_escape_string($block_y);
    
    // Count total records for pagination
    $countSql = "SELECT COUNT(*) as total FROM tree_data WHERE block_x = ? AND block_y = ?";
    $stmt = $conn->prepare($countSql);
    $stmt->bind_param("ss", $block_x, $block_y);
    $stmt->execute();
    $total_result = $stmt->get_result();
    $total_row = $total_result->fetch_assoc();
    $total_records = $total_row['total'];
    
    // Main data query with pagination
    $sql = "SELECT 
            treeNum,
            species_code,
            species_group,
            diameter,
            height,
            volume,
            status,
            diameter30,
            volume30
        FROM tree_data
        WHERE block_x = ? AND block_y = ?
        ORDER BY treeNum
        LIMIT ? OFFSET ?";
        
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $block_x, $block_y, $records_per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $total_records = 0;
    $result = null;
}

$total_pages = ceil($total_records / $records_per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tree Data</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f4e8;
            padding: 2rem;
            margin: 0;
            color: #2c3e50;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 0rem;
        }
        h3 {
            color: #2d5a27;
            font-size: 1.5rem;
            margin: 0 1rem 1rem;
            border-bottom: 3px solid #4a7c59;
            padding-bottom: 0.75rem;
            text-align: left;
        }
        .block-select {
            width: 100%;
            max-width: 200px;
            margin: 0 auto 0.3rem;
        }
        select {
            width: 100%;
            padding: 12px 20px;
            font-size: 1rem;
            border: 2px solid #4a7c59;
            border-radius: 8px;
            background-color: white;
            color: #2d5a27;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }
        th {
            background-color: #4a7c59;
            color: white;
            padding: 1rem;
            text-align: center;
            font-weight: 600;
            text-transform: uppercase;
        }
        td {
            padding: 0.75rem;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        tr:nth-child(even) {
            background-color: #f8faf9;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        .pagination a, .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid #4a7c59;
            border-radius: 4px;
            color: #4a7c59;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .pagination a:hover {
            background-color: #4a7c59;
            color: white;
        }
        .pagination .active {
            background-color: #4a7c59;
            color: white;
        }
        .records-info {
            text-align: right;
            color: #666;
            font-size: 0.9rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        
        <!-- Block selector -->
        <form method="GET" class="block-select">
            <select name="block" onchange="this.form.submit()">
                <?php while ($blockRow = $blockResult->fetch_assoc()): ?>
                    <?php 
                    $blockValue = $blockRow['block_x'] . ',' . $blockRow['block_y'];
                    $selected = (isset($_GET['block']) && $_GET['block'] == $blockValue) ? 'selected' : '';
                    ?>
                    <option value="<?php echo htmlspecialchars($blockValue); ?>" <?php echo $selected; ?>>
                        Block (<?php echo htmlspecialchars($blockRow['block_x']); ?>, <?php echo htmlspecialchars($blockRow['block_y']); ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </form>
        <h3>Forest Tree Data</h3>
        <!-- Tree data table -->
        <table>
            <thead>
                <tr>
                    <th>Tree Number</th>
                    <th>Species Code</th>
                    <th>Species Group</th>
                    <th>Diameter (cm)</th>
                    <th>Height (m)</th>
                    <th>Volume (m³)</th>
                    <th>Status</th>
                    <th>Diameter30 (cm)</th>
                    <th>Volume30 (m³)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['treeNum']); ?></td>
                            <td><?php echo htmlspecialchars($row['species_code']); ?></td>
                            <td><?php echo htmlspecialchars($row['species_group']); ?></td>
                            <td><?php echo number_format($row['diameter'], 1); ?></td>
                            <td><?php echo number_format($row['height'], 1); ?></td>
                            <td><?php echo number_format($row['volume'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['status']); ?></td>
                            <td><?php echo number_format($row['diameter30'], 1); ?></td>
                            <td><?php echo number_format($row['volume30'], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10">No trees found in selected block</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_records > 0): ?>
            <div class="records-info">
                Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $records_per_page, $total_records); ?> 
                of <?php echo $total_records; ?> trees
            </div>

            <!-- Pagination -->
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?block=<?php echo urlencode($_GET['block']); ?>&page=1">&laquo; First</a>
                    <a href="?block=<?php echo urlencode($_GET['block']); ?>&page=<?php echo ($page - 1); ?>">&lsaquo; Prev</a>
                <?php endif; ?>

                <?php 
                $pagination_range = 2;
                for ($i = max(1, $page - $pagination_range); $i <= min($total_pages, $page + $pagination_range); $i++): 
                ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?block=<?php echo urlencode($_GET['block']); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?block=<?php echo urlencode($_GET['block']); ?>&page=<?php echo ($page + 1); ?>">Next &rsaquo;</a>
                    <a href="?block=<?php echo urlencode($_GET['block']); ?>&page=<?php echo $total_pages; ?>">Last &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>