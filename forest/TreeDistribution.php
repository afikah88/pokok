<?php
// TreeDistribution.php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "forestdb";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get viewport boundaries from GET parameters
$north = isset($_GET['north']) ? floatval($_GET['north']) : null;
$south = isset($_GET['south']) ? floatval($_GET['south']) : null;
$east = isset($_GET['east']) ? floatval($_GET['east']) : null;
$west = isset($_GET['west']) ? floatval($_GET['west']) : null;

// Base query for trees
$sql = "
SELECT 
    block_x,
    block_y, 
    coord_x,
    coord_y,
    species_code,
    species_group,
    diameter,
    height,
    status,
    cut_angle,
    diameter30,
    (coord_x / 100000) AS relative_x,
    (coord_y / 100000) AS relative_y
FROM tree_data
WHERE 1=1";

// Add viewport filtering if boundaries are provided
if ($north && $south && $east && $west) {
    $sql .= " AND (coord_x / 100000 + 102.24896927028513) BETWEEN $west AND $east
              AND (coord_y / 100000 + 3.97627092663759) BETWEEN $south AND $north";
}

$sql .= " ORDER BY block_x, block_y";

$result = $conn->query($sql);

$data = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

$conn->close();

header('Content-Type: application/json');
echo json_encode($data);
?>