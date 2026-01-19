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

// SQL query to aggregate data and enforce species group order
$sql = "
    SELECT
        CASE
            WHEN species_group = 1 THEN 'Mersawa'
            WHEN species_group = 2 THEN 'Keruing'
            WHEN species_group = 3 THEN 'Dipterocarp Commercial'
            WHEN species_group = 4 THEN 'NonDipterocarp Commercial'
            WHEN species_group = 5 THEN 'Dipterocarp NonCommercial'
            WHEN species_group = 6 THEN 'NonDipterocarp NonCommercial'
            ELSE 'Others'
        END AS spgroup_name,
        CASE 
            WHEN diameter BETWEEN 5 AND 15 THEN '5cm-15cm'
            WHEN diameter BETWEEN 15 AND 30 THEN '15cm-30cm'
            WHEN diameter BETWEEN 30 AND 45 THEN '30cm-45cm'
            WHEN diameter BETWEEN 45 AND 60 THEN '45cm-60cm'
            ELSE '60cm+'
        END AS diameter_range,
        COUNT(*) AS No,
        SUM(volume) AS Vol
    FROM tree_data
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
    // Prepare data for rendering the table
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $spgroupName = $row['spgroup_name'];
        $diameterRange = $row['diameter_range'];
        $data[$spgroupName][$diameterRange] = [
            'No' => $row['No'],
            'Vol' => $row['Vol']
        ];
    }

    // Display the table with proper styling
    echo "<h3>Stand Table:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%; text-align: center;'>
            <thead>
                <tr style='background-color: #f2f2f2;'>
                    <th>Species Group</th>
                    <th>5cm-15cm</th>
                    <th>15cm-30cm</th>
                    <th>30cm-45cm</th>
                    <th>45cm-60cm</th>
                    <th>60cm+</th>
                </tr>
            </thead>
            <tbody>";

    foreach ($data as $speciesGroup => $ranges) {
        echo "<tr>";
        echo "<td rowspan='2' style='font-weight: bold;'>$speciesGroup</td>";
        // Display No (Tree Count)
        foreach (['5cm-15cm', '15cm-30cm', '30cm-45cm', '45cm-60cm', '60cm+'] as $range) {
            echo "<td>No: " . (isset($ranges[$range]['No']) ? $ranges[$range]['No'] : '-') . "</td>";
        }
        echo "</tr>";

        // Display Vol (Volume)
        echo "<tr>";
        foreach (['5cm-15cm', '15cm-30cm', '30cm-45cm', '45cm-60cm', '60cm+'] as $range) {
            echo "<td>Vol: " . (isset($ranges[$range]['Vol']) ? number_format($ranges[$range]['Vol'], 2) : '-') . "</td>";
        }
        echo "</tr>";
    }

    echo "</tbody></table>";
} else {
    echo "No data found.";
}

$conn->close();
?>