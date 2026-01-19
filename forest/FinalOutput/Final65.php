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
    die("<div class='container'><p style='color: #e53e3e; text-align: center;'>Connection failed: " . $conn->connect_error . "</p></div>");
}

// Get list of available block coordinates
$blockQuery = "SELECT DISTINCT block_x, block_y FROM tree_data ORDER BY block_x, block_y";
$blockResult = $conn->query($blockQuery);

echo "<div class='container'>";
echo "<div class='header'>";
echo "<form method='GET' class='block-select'>";
echo "<select name='block' onchange='this.form.submit()'>";
echo "<option value=''>Select Forest Block</option>";

while ($blockRow = $blockResult->fetch_assoc()) {
    $blockValue = $blockRow['block_x'] . ',' . $blockRow['block_y'];
    $selected = (isset($_GET['block']) && $_GET['block'] == $blockValue) ? 'selected' : '';
    echo "<option value='$blockValue' $selected>Block (" . $blockRow['block_x'] . ", " . $blockRow['block_y'] . ")</option>";
}
echo "</select>";
echo "</form>";
echo "</div>";

if (isset($_GET['block']) && $_GET['block'] != '') {
    list($block_x, $block_y) = explode(',', $_GET['block']);
    $block_x = $conn->real_escape_string($block_x);
    $block_y = $conn->real_escape_string($block_y);

    // Initialize array for storing table data
    $data = [
        "Mersawa" => [],
        "Keruing" => [],
        "Dip Marketable" => [],
        "Dip Non Market" => [],
        "Non Dip Market" => [],
        "Non Dip Non Market" => [],
        "Others" => []
    ];

    $sql = "
        SELECT
            CASE
                WHEN species_group = 1 THEN 'Mersawa'
                WHEN species_group = 2 THEN 'Keruing'
                WHEN species_group = 3 THEN 'Dip Marketable'
                WHEN species_group = 4 THEN 'Dip Non Market'
                WHEN species_group = 5 THEN 'Non Dip Market'
                WHEN species_group = 6 THEN 'Non Dip Non Market'
                ELSE 'Others'
            END AS species_group_name,
            block_x,
            block_y,
            species_group,
            diameter,
            volume,
            volume30,
            diameter30,
            status
        FROM tree_data
        WHERE block_x = '$block_x'
        AND block_y = '$block_y'";

    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $sgroup = $row['species_group'];
            $group = $row['species_group_name'];
            $status = $row['status'];
            $diameter = $row['diameter'];
            $diameter30 = $row['diameter30'];

            // Initialize counts if not set
            if (!isset($data[$group]['TotalVolume'])) $data[$group]['TotalVolume'] = 0;
            if (!isset($data[$group]['TotalNumber'])) $data[$group]['TotalNumber'] = 0;
            if (!isset($data[$group]['Prod0'])) $data[$group]['Prod0'] = 0;
            if (!isset($data[$group]['Damage0'])) $data[$group]['Damage0'] = 0;
            if (!isset($data[$group]['Remain0'])) $data[$group]['Remain0'] = 0;
            if (!isset($data[$group]['TotalGrowth30'])) $data[$group]['TotalGrowth30'] = 0;
            if (!isset($data[$group]['TotalProd30'])) $data[$group]['TotalProd30'] = 0;

            // Update counts
            if ($diameter >= 65) {
                $data[$group]['TotalVolume'] += $row['volume'];
                $data[$group]['TotalNumber']++;
                }
    
                if ($diameter >= 65) {
                    if ($status === 'cut') {
                        $data[$group]['Prod0'] += $row['volume'];
                    } elseif ($status === 'victim_stem') {
                        $data[$group]['Damage0']++;
                    }
                }
    
                if ($diameter >= 65){
                    if ($status === 'victim_crown' || $status === 'keep') {
                        $data[$group]['Remain0']++;
                    }
                }
    
                if ($status === 'victim_crown' || $status === 'keep') {
                    if ($diameter30 >= 65){
                        if (in_array($sgroup, [1, 2, 3, 5])) {
                                $data[$group]['TotalProd30'] += $row['volume30'];
                            }
                        }
                }
                    if ($diameter30 >= 65){
                        $data[$group]['TotalGrowth30'] += $row['volume30'];
                }
        }

        echo "<h3>Cutting Regime 65 - Block ($block_x, $block_y)</h3>";
        echo "<table>";
        echo "<thead>
                <tr>
                    <th>Species Groups</th>
                    <th>Total Volume 0</th>
                    <th>Total Number 0</th>
                    <th>Prod 0</th>
                    <th>Damage 0</th>
                    <th>Remain 0</th>
                    <th>Total Growth 30</th>
                    <th>Total Prod 30</th>
                </tr>
              </thead>";
        echo "<tbody>";

        foreach ($data as $group => $values) {
            echo "<tr>";
            echo "<td>$group</td>";
            echo "<td class='numeric-value'>" . (isset($values['TotalVolume']) ? number_format($values['TotalVolume'], 2) : '-') . "</td>";
            echo "<td class='numeric-value'>" . (isset($values['TotalNumber']) ? number_format($values['TotalNumber']) : '-') . "</td>";
            echo "<td class='numeric-value'>" . (isset($values['Prod0']) ? number_format($values['Prod0'], 2) : '-') . "</td>";
            echo "<td class='numeric-value'>" . (isset($values['Damage0']) ? number_format($values['Damage0']) : '-') . "</td>";
            echo "<td class='numeric-value'>" . (isset($values['Remain0']) ? number_format($values['Remain0']) : '-') . "</td>";
            echo "<td class='numeric-value'>" . (isset($values['TotalGrowth30']) ? number_format($values['TotalGrowth30'], 2) : '-') . "</td>";
            echo "<td class='numeric-value'>" . (isset($values['TotalProd30']) ? number_format($values['TotalProd30'], 2) : '-') . "</td>";
            echo "</tr>";
        }

        echo "</tbody></table>";
    } else {
        echo "<p style='text-align: center; color: #718096;'>No data found for Block ($block_x, $block_y).</p>";
    }
}

echo "</div>";
$conn->close();
?>
</body>
</html>