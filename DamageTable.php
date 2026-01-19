<?php
include 'php/navbar.php';
set_include_path('../');
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

// Get records per page from dropdown selection - MODIFIED OPTIONS
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10; // Default to 10
if (!in_array($records_per_page, [1, 5, 10])) { // Only allow 1, 5, or 10
    $records_per_page = 10;
}

// Get current page number from URL parameter
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Get total number of unique cut trees
$countSql = "SELECT COUNT(DISTINCT cut_tree_num) as total FROM tree_victims";
$countResult = $conn->query($countSql);
$totalTrees = $countResult->fetch_assoc()['total'];

// Calculate total pages
$total_pages = ceil($totalTrees / $records_per_page);

// Ensure current page is within valid range
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages) $current_page = $total_pages;

// Get all unique cut tree numbers with LIMIT and OFFSET
$sql = "SELECT DISTINCT cut_tree_num FROM tree_victims ORDER BY cut_tree_num LIMIT $records_per_page OFFSET " . ($current_page - 1) * $records_per_page;
$cutTreeResult = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tree Damage Data</title>
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
        h2, h3 {
            color: #2d5a27;
            font-size: 1.5rem;
            margin: 0 0 1rem;
            border-bottom: 3px solid #4a7c59;
            padding-bottom: 0.75rem;
            text-align: left;
        }
        .records-select {
            text-align: right;
            margin-bottom: 1rem;
        }
        select {
            padding: 8px 16px;
            font-size: 0.9rem;
            border: 2px solid #4a7c59;
            border-radius: 8px;
            background-color: white;
            color: #2d5a27;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .tables-container {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .table-section {
            flex: 1;
            min-width: 300px;
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
        .damage-info {
            background-color: #f8faf9;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 2rem;
        }
        .damage-info p {
            margin: 0.5rem 0;
            color: #2c3e50;
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

        nav {
            background: #444;
            color: #fff;
            padding: 10px;
            text-align: center;
            position: relative;
        }

        nav a {
            color: #fff;
            text-decoration: none;
            margin: 0 15px;
            padding: 10px;
            display: inline-block;
        }

        nav a:hover {
            text-decoration: underline;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #555;
            min-width: 160px;
            box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.2);
            z-index: 1;
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        /* Show only the hovered dropdown's content */
        .dropdown:hover > .dropdown-content {
            display: block;
            visibility: visible;
            opacity: 1;
        }

        .dropdown-content .dropdown {
            position: relative;
        }

        /* Show nested dropdown on hover */
        .dropdown-content .dropdown-content {
            top: 0;
            left: 100%;
            display: none;
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .dropdown-content .dropdown:hover > .dropdown-content {
            display: block;
            visibility: visible;
            opacity: 1;
        }

        .dropdown-content a {
            color: white;
            padding: 10px;
            text-decoration: none;
            display: block;
        }

        .dropdown-content a:hover {
            background-color: #666;
        }


        footer {
            background: #333;
            color: #fff;
            text-align: center;
            padding: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Tree Damage Analysis</h2>
        
        <!-- Records per page selector - MODIFIED OPTIONS -->
        <div class="records-select">
            <form style="display: inline-block;">
                <label for="per_page">Records per page: </label>
                <select name="per_page" id="per_page" onchange="this.form.submit()">
                    <?php foreach ([1, 5, 10] as $value): ?>
                        <option value="<?php echo $value; ?>" <?php echo ($value == $records_per_page) ? 'selected' : ''; ?>>
                            <?php echo $value; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="page" value="<?php echo $current_page; ?>">
            </form>
        </div>

        <?php if ($cutTreeResult->num_rows > 0): ?>
            <div class="tables-container">
                <!-- Left table - Damage - Cut tree -->
                <div class="table-section">
                    <h3>Damage - Cut Tree</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Cut Tree</th>
                                <th>Victim</th>
                                <th>Category Damage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            while ($cutTree = $cutTreeResult->fetch_assoc()) {
                                $cutTreeNum = $cutTree['cut_tree_num'];
                                $victimSql = "SELECT victim_tree_num, damage_category 
                                            FROM tree_victims 
                                            WHERE cut_tree_num = '$cutTreeNum' 
                                            ORDER BY damage_category";
                                $victimResult = $conn->query($victimSql);
                                
                                $firstRow = true;
                                while ($victim = $victimResult->fetch_assoc()) {
                                    echo "<tr>";
                                    if ($firstRow) {
                                        echo "<td rowspan='" . $victimResult->num_rows . "'>" . $cutTreeNum . "</td>";
                                        $firstRow = false;
                                    }
                                    echo "<td>" . $victim['victim_tree_num'] . "</td>";
                                    echo "<td>" . $victim['damage_category'] . "</td>";
                                    echo "</tr>";
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Right table - Victim Trees -->
                <div class="table-section">
                    <h3>Victim Trees</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Victim</th>
                                <th>Cut Tree</th>
                                <th>Category Damage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $cutTreeResult->data_seek(0);
                            while ($cutTree = $cutTreeResult->fetch_assoc()) {
                                $cutTreeNum = $cutTree['cut_tree_num'];
                                $victimSql = "SELECT victim_tree_num, damage_category 
                                            FROM tree_victims 
                                            WHERE cut_tree_num = '$cutTreeNum' 
                                            ORDER BY damage_category";
                                $victimResult = $conn->query($victimSql);
                                
                                while ($victim = $victimResult->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . $victim['victim_tree_num'] . "</td>";
                                    echo "<td>" . $cutTreeNum . "</td>";
                                    echo "<td>" . $victim['damage_category'] . "</td>";
                                    echo "</tr>";
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Damage category explanation -->
            <div class="damage-info">
                <h3>Damage Categories</h3>
                <p>Category 1 = Fatal. Damage due to stem. Damage is based on volume</p>
                <p>Category 2 = Can survive - 50% damage. Due to crown</p>
            </div>

            <!-- Records info -->
            <div class="records-info">
                Showing <?php echo (($current_page - 1) * $records_per_page + 1); ?>-<?php 
                echo min($current_page * $records_per_page, $totalTrees); ?> 
                of <?php echo $totalTrees; ?> trees
            </div>

            <!-- Pagination -->
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="?page=1&per_page=<?php echo $records_per_page; ?>">&laquo; First</a>
                    <a href="?page=<?php echo ($current_page - 1); ?>&per_page=<?php echo $records_per_page; ?>">&lsaquo; Prev</a>
                <?php endif; ?>

                <?php 
                $window = 2;
                for ($i = max(1, $current_page - $window); $i <= min($total_pages, $current_page + $window); $i++): 
                    if ($i == $current_page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&per_page=<?php echo $records_per_page; ?>"><?php echo $i; ?></a>
                    <?php endif;
                endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?php echo ($current_page + 1); ?>&per_page=<?php echo $records_per_page; ?>">Next &rsaquo;</a>
                    <a href="?page=<?php echo $total_pages; ?>&per_page=<?php echo $records_per_page; ?>">Last &raquo;</a>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <p>No damage relationships found in the database.</p>
        <?php endif; ?>
    </div>
    <footer>
        <p>&copy; 2025 Forestry Management System</p>
    </footer>
</body>
</html>
<?php $conn->close(); ?>