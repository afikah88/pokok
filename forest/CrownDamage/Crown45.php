<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forest Management System</title>
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

        .header {
            margin-bottom: 2rem;
            text-align: center;
        }

        h3 {
            color: #2d5a27;
            font-size: 1.5rem;
            margin:  0 1rem 0;
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
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%234a7c59' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
        }

        select:hover {
            border-color: #2d5a27;
            box-shadow: 0 0 0 3px rgba(74, 124, 89, 0.1);
        }

        select:focus {
            outline: none;
            border-color: #2d5a27;
            box-shadow: 0 0 0 3px rgba(74, 124, 89, 0.2);
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 1rem;
            border-radius: 8px;
            overflow: hidden;
        }

        thead {
            background-color: #4a7c59;
            color: white;
        }

        th {
            padding: 1rem;
            text-align: center;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            text-align: center;
            font-size: 0.95rem;
        }

        tbody tr:nth-child(even) {
            background-color: #f8faf9;
        }

        tbody tr:hover {
            background-color: #f0f7f4;
        }

        td:first-child {
            font-weight: 600;
            color: #2d5a27;
            text-align: left;
        }

        .numeric-value {
            font-family: 'Monaco', 'Consolas', monospace;
            color: #2c5282;
        }

        @media (max-width: 1024px) {
            .container {
                padding: 1rem;
            }

            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>
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

// Get list of available block coordinates
$blockQuery = "SELECT DISTINCT block_x, block_y FROM tree_data ORDER BY block_x, block_y";
$blockResult = $conn->query($blockQuery);

echo "<div class='container'>";
echo "<div class='header'>";
echo "<form method='GET' class='block-select'>";
echo "<select name='block' onchange='this.form.submit()'>";

while ($blockRow = $blockResult->fetch_assoc()) {
    $blockValue = $blockRow['block_x'] . ',' . $blockRow['block_y'];
    $selected = (isset($_GET['block']) && $_GET['block'] == $blockValue) ? 'selected' : '';
    echo "<option value='$blockValue' $selected>Block (" . $blockRow['block_x'] . ", " . $blockRow['block_y'] . ")</option>";
}
echo "</select>";
echo "</form>";

if (isset($_GET['block']) && $_GET['block'] != '') {
    list($block_x, $block_y) = explode(',', $_GET['block']);
    $block_x = $conn->real_escape_string($block_x);
    $block_y = $conn->real_escape_string($block_y);

    $sql = "
        SELECT
            CASE
                WHEN v.species_group = 1 THEN 'Mersawa'
                WHEN v.species_group = 2 THEN 'Keruing'
                WHEN v.species_group = 3 THEN 'Dipterocarp Commercial'
                WHEN v.species_group = 4 THEN 'NonDipterocarp Commercial'
                WHEN v.species_group = 5 THEN 'Dipterocarp NonCommercial'
                WHEN v.species_group = 6 THEN 'NonDipterocarp NonCommercial'
                ELSE 'Others'
            END AS spgroup_name,
            CASE 
                WHEN v.diameter BETWEEN 5 AND 15 THEN '5cm-15cm'
                WHEN v.diameter BETWEEN 15 AND 30 THEN '15cm-30cm'
                WHEN v.diameter BETWEEN 30 AND 45 THEN '30cm-45cm'
                WHEN v.diameter BETWEEN 45 AND 60 THEN '45cm-60cm'
                ELSE '60cm+'
            END AS diameter_range,
            COUNT(*) AS No,
            SUM(v.volume) AS Vol
        FROM tree_data c
        INNER JOIN tree_victims tv ON c.treeNum = tv.cut_tree_num
        INNER JOIN tree_data v ON tv.victim_tree_num = v.treeNum
        WHERE c.diameter >= 45
        AND c.species_group IN (1, 2, 3, 5)
        AND c.status = 'cut'
        AND tv.damage_category = 2
        AND c.block_x = '$block_x'
        AND c.block_y = '$block_y'
        AND v.block_x = '$block_x'
        AND v.block_y = '$block_y'
        GROUP BY spgroup_name, diameter_range
        ORDER BY FIELD(spgroup_name, 
            'Mersawa', 
            'Keruing', 
            'Dipterocarp Commercial', 
            'NonDipterocarp Commercial', 
            'Dipterocarp NonCommercial', 
            'NonDipterocarp NonCommercial', 
            'Others'
        ),
        FIELD(diameter_range, '5cm-15cm', '15cm-30cm', '30cm-45cm', '45cm-60cm', '60cm+')
    ";

    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $data = [];
        $totals = ['No' => 0, 'Vol' => 0];
        
        while ($row = $result->fetch_assoc()) {
            $spgroupName = $row['spgroup_name'];
            $diameterRange = $row['diameter_range'];
            $data[$spgroupName][$diameterRange] = [
                'No' => $row['No'],
                'Vol' => $row['Vol']
            ];
            $totals['No'] += $row['No'];
            $totals['Vol'] += $row['Vol'];
        }

        echo "<h3>Crown Damage Cutting 45 - Block ($block_x, $block_y)</h3>";
        //echo "<p class='subtitle'>Trees to be Cut ≥ 55cm</p>";
        echo "<table>
                <thead>
                    <tr>
                        <th>Species Group</th>
                        <th>5cm-15cm</th>
                        <th>15cm-30cm</th>
                        <th>30cm-45cm</th>
                        <th>45cm-60cm</th>
                        <th>60cm+</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>";

        foreach ($data as $speciesGroup => $ranges) {
            $groupTotals = ['No' => 0, 'Vol' => 0];
            foreach ($ranges as $range) {
                $groupTotals['No'] += $range['No'];
                $groupTotals['Vol'] += $range['Vol'];
            }
            
            echo "<tr>";
            echo "<td rowspan='2' class='species-group'>$speciesGroup</td>";
            foreach (['5cm-15cm', '15cm-30cm', '30cm-45cm', '45cm-60cm', '60cm+'] as $range) {
                echo "<td>" . (isset($ranges[$range]['No']) ? $ranges[$range]['No'] : '-') . " trees</td>";
            }
            echo "<td class='total-cell'>{$groupTotals['No']} trees</td>";
            echo "</tr>";

            echo "<tr>";
            foreach (['5cm-15cm', '15cm-30cm', '30cm-45cm', '45cm-60cm', '60cm+'] as $range) {
                echo "<td class='volume'>" . (isset($ranges[$range]['Vol']) ? number_format($ranges[$range]['Vol'], 2) : '-') . " m³</td>";
            }
            echo "<td class='total-cell'>" . number_format($groupTotals['Vol'], 2) . " m³</td>";
            echo "</tr>";
        }

        echo "<tr class='total-row'>";
        echo "<td colspan='6' class='total-cell'>Grand Total:</td>";
        echo "<td class='total-cell'>{$totals['No']} trees<br>" . number_format($totals['Vol'], 2) . " m³</td>";
        echo "</tr>";

        echo "</tbody></table>";
    } else {
        echo "<p>No data found for Block ($block_x, $block_y).</p>";
    }
}

echo "</div>";
$conn->close();
?>
</body>
</html>