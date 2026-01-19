<?php
ini_set('memory_limit', '512M');
set_time_limit(550); // Sets maximum execution time to 5 minutes
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

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

// Drop and recreate the table to ensure a clean state
$conn->query("DROP TABLE IF EXISTS tree_data65");
$conn->query("
    CREATE TABLE tree_data65 (
        id INT AUTO_INCREMENT PRIMARY KEY,
        block_x INT,
        block_y INT,
        coord_x INT,
        coord_y INT,
        treeNum VARCHAR(20),
        species_code VARCHAR(10),
        species_group INT,
        diameter DECIMAL(5, 2),
        height DECIMAL(5, 2),
        volume DECIMAL(10, 2),
        status VARCHAR(100),
        prod DECIMAL(10, 2) DEFAULT NULL,
        cut_angle INT DEFAULT NULL,
        damage_stem INT DEFAULT NULL,
        damage_crown INT DEFAULT NULL,
        diameter30 DECIMAL(5,2),
        volume30 DECIMAL (5,2)
    )
") or die("Table creation failed: " . $conn->error);

// Fetch species codes and organize by species group
$speciesGroups = [];
$sql = "SELECT SPECODE, `SPEC-Gr` FROM species";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "Species data retrieved successfully.<br>";
    while ($row = $result->fetch_assoc()) {
        $speciesGroup = $row['SPEC-Gr'];
        $speciesCode = $row['SPECODE'];
        
        if (!isset($speciesGroups[$speciesGroup])) {
            $speciesGroups[$speciesGroup] = [];
        }
        $speciesGroups[$speciesGroup][] = $speciesCode;
    }
} else {
    echo "Species data retrieval failed.<br>";
    die("No species codes found in the database.");
}

// Define diameter groups and distribution percentages
$diameterGroups = [
    [5, 15], [15, 30], [30, 45], [45, 60], [60, 80]
];
// Tree distribution per species group and diameter group
$treeDistribution = [
    [15, 12, 4, 2, 2], 
    [21, 18, 6, 4, 4], 
    [21, 18, 6, 4, 4], 
    [30, 27, 9, 5, 3], 
    [30, 27, 9, 4, 4], 
    [39, 36, 12, 7, 4], 
    [44, 42, 14, 9, 4], 
];

// fixed increment cm/year
$diameterIncrement = [
    [0.4, 0.6, 0.5, 0.5, 0.7],
];

// Prepare the insert statement
$stmt = $conn->prepare("INSERT INTO tree_data65 (block_x, block_y, coord_x, coord_y, treeNum, species_code, species_group, diameter, height, 
                        volume, status, prod, cut_angle, damage_stem, damage_crown, diameter30, volume30) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iiiissidddsdiiidd", $blockX, $blockY, $coordX, $coordY, $treeNum, $speciesCode, $speciesGroupKey, $diameter, $height, 
                    $volume, $status, $prod, $cut_angle, $damage_stem, $damage_crown, $diameter30, $volume30);

// Reset row counter to track total rows
$totalRows = 0;
$maxRows = 50000; // Limit total rows to 50,000

// Loop through each block
for ($blockX = 1; $blockX <= 10 && $totalRows < $maxRows; $blockX++) {
    for ($blockY = 1; $blockY <= 10 && $totalRows < $maxRows; $blockY++) {
        $usedCoordinates = []; // Store used coordinates for the current block
        foreach ($diameterGroups as $diameterIndex => $diameterRange) {
            foreach ($treeDistribution as $speciesGroup => $treeCounts) {
                $treeCount = $treeCounts[$diameterIndex];
                
                for ($i = 0; $i < $treeCount && $totalRows < $maxRows; $i++) {
                    // Check if species group has species codes
                    $speciesGroupKey = $speciesGroup + 1; // Species group index is 0-based; DB starts from 1
                    if (!isset($speciesGroups[$speciesGroupKey]) || count($speciesGroups[$speciesGroupKey]) == 0) {
                        echo "Warning: No species codes found for species group: $speciesGroupKey<br>";
                        continue;
                    }
                    // Select a random species code
                    $speciesCode = $speciesGroups[$speciesGroupKey][array_rand($speciesGroups[$speciesGroupKey])];
                    
                    // Random diameter within the diameter range
                    $diameter = rand($diameterRange[0], $diameterRange[1]);

                    // Random height between 10 and 30
                    $height = rand(10, 30);

                    do {
                        // Generate random coordinates
                        $locationx = rand(1, 100);
                        $locationy = rand(1, 100);
                        $coordX = ($blockX - 1) * 100 + $locationx;
                        $coordY = ($blockY - 1) * 100 + $locationy;
                        $coordKey = "$blockX-$blockY-$coordX-$coordY";
                    } while (isset($usedCoordinates[$coordKey])); // Repeat if coordinates are already used
                
                    // Mark the coordinates as used
                    $usedCoordinates[$coordKey] = true;

                    $treeNum = "T$blockX$blockY$coordX$coordY";
                    $treeNum = "T$blockX$blockY$coordX$coordY";
                    $diamMeters = $diameter / 100;
                    if (in_array($speciesGroupKey, [1, 2, 3, 4])) { // Dipterocarp
                        if ($diamMeters < 0.15) {
                            $volume = 0.022 + 3.4 * pow($diamMeters, 2);
                        } else { // >= 0.15
                            $volume = -0.0971 + 9.503 * pow($diamMeters, 2);
                        }
                    } elseif (in_array($speciesGroupKey, [5, 6, 7])) { // Non-Dipterocarp
                        if ($diamMeters < 0.30) {
                            $volume = 0.03 + 2.8 * pow($diamMeters, 2);
                        } else { // >= 0.30
                            $volume = -0.331 + 6.694 * pow($diamMeters, 2);
                        }
                    }
                    $diameter30 = $diameter ;
                    for ($year=1 ; $year<=30 ; $year++){
                        if ($diameter30 >= 5 && $diameter30 < 15) {
                            $diameter30 += $diameterIncrement[0][0];
                        } elseif ($diameter30 >= 15 && $diameter30 < 30) {
                            $diameter30 += $diameterIncrement[0][1];
                        } elseif ($diameter30 >= 30 && $diameter30 < 45) {
                            $diameter30 += $diameterIncrement[0][2];
                        } elseif ($diameter30 >= 45 && $diameter30 < 60) {
                            $diameter30 += $diameterIncrement[0][3];
                        } elseif ($diameter30 >= 60) {
                            $diameter30 += $diameterIncrement[0][4];
                        }
                    }
                    $diamMeter30 = $diameter30 / 100;
                    if (in_array($speciesGroupKey,[1,2,3,4])){
                        if ($diamMeter30 < 0.15){
                            $volume30 = 0.022 + 3.4 * pow ($diamMeter30, 2);
                        } else {
                            $volume30 = -0.0971 + 9.503 * pow($diamMeter30, 2);
                        }
                    } elseif (in_array($speciesGroupKey, [5,6,7])) {
                        if ($diamMeter30 < 0.30 ) {
                            $volume30 = 0.03 + 2.8 * pow($diamMeter30, 2);
                        } else {
                            $volume30 = -0.331 + 6.694 * pow ($diamMeter30, 2);
                        }
                    }

                    // Execute the prepared statement
                    if (!$stmt->execute()) {
                        echo "Error inserting data: " . $stmt->error . "<br>";
                    }
                    
                    // Increment total row counter
                    $totalRows++;
                    
                    // Check if max row limit has been reached
                    if ($totalRows >= $maxRows) {
                        break;
                    }
                }
            }
        }
        unset($usedCoordinates);
    }
}

// Close resources
$stmt->close();
$conn->close();

echo "Tree data generation and insertion complete. Total rows inserted: $totalRows.";

include 'damage65.php';
?>
