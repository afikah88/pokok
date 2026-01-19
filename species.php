<?php
include 'php/navbar.php';
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "forestdb";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Pagination setup - remove duplicate declaration
$records_per_page = isset($_POST['records_per_page']) ? (int)$_POST['records_per_page'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Get list of available block coordinates
$blockQuery = "SELECT DISTINCT block_x, block_y FROM tree_data ORDER BY block_x, block_y";
$blockResult = $conn->query($blockQuery);

// Prepare the main query
$sql = "SELECT 
        No,
        SPECODE,
        Local_name,
        'Spec-Gr',
        ROY_CLASS,
        COMM_Gr,
        `Dip/NonDip`
    FROM SPECIES
    ORDER BY No
    LIMIT ? OFFSET ?";

// Count total records for pagination
$countSql = "SELECT COUNT(*) as total FROM SPECIES";
$total_result = $conn->query($countSql);
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $records_per_page);

// Execute main query with pagination
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
    <title>Species Data</title>
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

        .records-select {
            margin-bottom: 2rem;
            max-width: 200px;
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
        /* Add records per page selector styling */
        .records-per-page {
            margin-bottom: 1rem;
        }
        .records-per-page select {
            max-width: 100px;
            margin-left: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h3>Species Data</h3>
        
        <!-- Records per page selector -->
        <form method="POST" class="records-per-page">
            <label for="records_per_page">Records per page:</label>
            <select name="records_per_page" id="records_per_page" onchange="this.form.submit()">
                <option value="10" <?php echo $records_per_page == 10 ? 'selected' : ''; ?>>10</option>
                <option value="25" <?php echo $records_per_page == 25 ? 'selected' : ''; ?>>25</option>
                <option value="50" <?php echo $records_per_page == 50 ? 'selected' : ''; ?>>50</option>
                <option value="100" <?php echo $records_per_page == 100 ? 'selected' : ''; ?>>100</option>
            </select>
        </form>

        <!-- Species data table -->
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Species Code</th>
                    <th>Local Name</th>
                    <th>Species Group</th>
                    <th>Royal Class</th>
                    <th>Commercial Group</th>
                    <th>Dip/NonDip</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['No']); ?></td>
                            <td><?php echo htmlspecialchars($row['SPECODE']); ?></td>
                            <td><?php echo htmlspecialchars($row['Local_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['Spec-Gr']); ?></td>
                            <td><?php echo htmlspecialchars($row['ROY_CLASS']); ?></td>
                            <td><?php echo htmlspecialchars($row['COMM_Gr']); ?></td>
                            <td><?php echo htmlspecialchars($row['Dip/NonDip']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">No species data found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_records > 0): ?>
            <div class="records-info">
                Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $records_per_page, $total_records); ?> 
                of <?php echo $total_records; ?> species
            </div>

            <!-- Pagination -->
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1">&laquo; First</a>
                    <a href="?page=<?php echo ($page - 1); ?>">&lsaquo; Prev</a>
                <?php endif; ?>

                <?php 
                $pagination_range = 2;
                for ($i = max(1, $page - $pagination_range); $i <= min($total_pages, $page + $pagination_range); $i++): 
                ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo ($page + 1); ?>">Next &rsaquo;</a>
                    <a href="?page=<?php echo $total_pages; ?>">Last &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <footer>
        <p>&copy; 2025 Forestry Management System</p>
    </footer>
</body>
</html>