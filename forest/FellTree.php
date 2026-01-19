<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "forestdb";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set records per page
$records_per_page = isset($_POST['records_per_page']) ? (int)$_POST['records_per_page'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Get total number of records
$total_query = "SELECT COUNT(*) as count FROM tree_data WHERE status = 'cut' AND block_x = 1 AND block_y = 1";
$total_result = $conn->query($total_query);
$total_records = $total_result->fetch_assoc()['count'];
$total_pages = ceil($total_records / $records_per_page);

// SQL query to get felled trees with pagination
$sql = "
    SELECT 
        treeNum,
        species_group,
        diameter,
        coord_x,
        coord_y,
        cut_angle
    FROM tree_data
    WHERE status = 'cut'
    AND block_x = 1 AND block_y = 1
    ORDER BY treeNum
    LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $records_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Felled Trees</title>
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
            padding: 2rem;
        }
        h3 {
            color: #2d5a27;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 3px solid #4a7c59;
            padding-bottom: 1rem;
        }
        /* Updated records selector styling */
        .records-select {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 1.5rem;
        }
        .records-select label {
            color: #2c3e50;
            font-size: 0.9rem;
        }
        .records-select select {
            width: 15%;
            padding: 12px 20px;
            font-size: 1rem;
            border: 2px solid #4a7c59;
            border-radius: 8px;
            background-color: white;
            color: #2d5a27;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        /* Rest of your existing styles */
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
        .tree-number {
            text-align: left;
            color: #2d5a27;
            font-weight: 500;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
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
        <h3>Felled Trees</h3>
        
        <!-- Updated records per page selector -->
        <form method="post" class="records-select">
            <label for="records_per_page">Records per page:</label>
            <select name="records_per_page" id="records_per_page" onchange="this.form.submit()">
                <option value="10" <?php echo $records_per_page == 10 ? 'selected' : ''; ?>>10</option>
                <option value="25" <?php echo $records_per_page == 25 ? 'selected' : ''; ?>>25</option>
                <option value="50" <?php echo $records_per_page == 50 ? 'selected' : ''; ?>>50</option>
                <option value="100" <?php echo $records_per_page == 100 ? 'selected' : ''; ?>>100</option>
            </select>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Tree Number</th>
                    <th>Species Group</th>
                    <th>Diameter</th>
                    <th>Coordinate</th>
                    <th>Felling Direction</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td class='tree-number'>" . htmlspecialchars($row['treeNum']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['species_group']) . "</td>";
                        echo "<td>" . number_format($row['diameter'], 2) . " cm</td>";
                        echo "<td>(" . $row['coord_x'] . ", " . $row['coord_y'] . ")</td>";
                        echo "<td>" . $row['cut_angle'] . "°</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='5'>No felled trees found</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <div class="records-info">
            Showing <?php echo ($offset + 1) ?>-<?php echo min($offset + $records_per_page, $total_records) ?> 
            of <?php echo $total_records ?> felled trees
        </div>

        <div class="pagination">
            <?php
            $pagination_range = 2;
            
            if ($page > 1) {
                echo "<a href='?page=1'>&laquo; First</a>";
                echo "<a href='?page=" . ($page - 1) . "'>&lsaquo; Prev</a>";
            }

            for ($i = max(1, $page - $pagination_range); $i <= min($total_pages, $page + $pagination_range); $i++) {
                echo ($i == $page) 
                    ? "<span class='active'>$i</span>" 
                    : "<a href='?page=$i'>$i</a>";
            }

            if ($page < $total_pages) {
                echo "<a href='?page=" . ($page + 1) . "'>Next &rsaquo;</a>";
                echo "<a href='?page=$total_pages'>Last &raquo;</a>";
            }
            ?>
        </div>
    </div>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>