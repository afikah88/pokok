<?php
// Database connection
set_time_limit(550);
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "forestdb";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT block_x, block_y, coord_x, coord_y, treeNum, species_group, diameter, height, volume FROM tree_data";
$result = $conn->query($sql);

$trees = [];
if ($result->num_rows > 0) {
    echo "Trees data retrieved successfully.<br>";
    while ($row = $result->fetch_assoc()) {
        $blockX = $row['block_x'];
        $blockY = $row['block_y'];
        $coordX = $row['coord_x'];
        $coordY = $row['coord_y'];
        $tree_Num = $row['treeNum'];
        $speciesGroup = $row['species_group'];
        $diameter = $row['diameter'];
        $height = $row['height'];
        $volume = $row['volume'];
        
        $trees[] = [
            'blockX' => $blockX,
            'blockY' => $blockY,
            'coordX' => $coordX,
            'coordY' => $coordY,
            'treeNum' => $tree_Num,
            'speciesGroup' => $speciesGroup,
            'diameter' => $diameter,
            'height' => $height,
            'volume' => $volume,
            'status' => '',
            'prod' => 0.00,
            'cut_angle' => '',
            'damage_stem' => 0,
            'damage_crown' => 0
        ];
    }
} else {
    echo "Tree data retrieval failed.<br>";
    die("No tree found in the database.");
}

$victimRecords = [];
$batchUpdates = [];

foreach ($trees as &$tree) {
    if (($tree['speciesGroup'] == 1 || $tree['speciesGroup'] == 2 || $tree['speciesGroup'] == 3 || $tree['speciesGroup'] == 5) 
        && $tree['diameter'] > 45) { 
        $tree['status'] = "cut";
        $tree['cut_angle'] = rand(1,360);
    } else {
        $tree['status'] = "keep";
        $tree['cut_angle'] = NULL;
    }

    $min_cut_angle_rad = deg2rad($tree['cut_angle'] - 1);
    $max_cut_angle_rad = deg2rad($tree['cut_angle'] + 1);

    // Maintain a list of affected trees from felling & crown
    $affected_felling = [];
    $affected_crown = [];

    if ($tree['status'] == "cut") {
        $tree['prod'] = $tree['volume'];
        if($tree['cut_angle'] > 0 && $tree['cut_angle'] <= 90) {
            $x1 = $tree['coordX'] + $tree['height'] * cos($min_cut_angle_rad);
            $y1 = $tree['coordY'] + $tree['height'] * sin($min_cut_angle_rad);
            $x2 = $tree['coordX'] + $tree['height'] * cos($max_cut_angle_rad);
            $y2 = $tree['coordY'] + $tree['height'] * sin($max_cut_angle_rad);

            //crown damage, find center of the crown
            $crownx = $tree['coordX'] + ($tree['height'] + 5) * cos(deg2rad($tree['cut_angle']));
            $crowny = $tree['coordY'] + ($tree['height'] + 5) * sin(deg2rad($tree['cut_angle']));

        } else if($tree['cut_angle'] > 90 && $tree['cut_angle'] <= 180) {
            $x1 = $tree['coordX'] - $tree['height'] * cos($min_cut_angle_rad);
            $y1 = $tree['coordY'] + $tree['height'] * sin($min_cut_angle_rad);
            $x2 = $tree['coordX'] - $tree['height'] * cos($max_cut_angle_rad);
            $y2 = $tree['coordY'] + $tree['height'] * sin($max_cut_angle_rad);

            $crownx = $tree['coordX'] - ($tree['height'] + 5) * cos(deg2rad($tree['cut_angle']));
            $crowny = $tree['coordY'] + ($tree['height'] + 5) * sin(deg2rad($tree['cut_angle']));

        } else if($tree['cut_angle'] > 180 && $tree['cut_angle'] <= 270) {
            $x1 = $tree['coordX'] - $tree['height'] * cos($min_cut_angle_rad);
            $y1 = $tree['coordY'] - $tree['height'] * sin($min_cut_angle_rad);
            $x2 = $tree['coordX'] - $tree['height'] * cos($max_cut_angle_rad);
            $y2 = $tree['coordY'] - $tree['height'] * sin($max_cut_angle_rad);

            $crownx = $tree['coordX'] - ($tree['height'] + 5) * cos(deg2rad($tree['cut_angle']));
            $crowny = $tree['coordY'] - ($tree['height'] + 5) * sin(deg2rad($tree['cut_angle']));

        } else {
            $x1 = $tree['coordX'] + $tree['height'] * cos($min_cut_angle_rad);
            $y1 = $tree['coordY'] - $tree['height'] * sin($min_cut_angle_rad);
            $x2 = $tree['coordX'] + $tree['height'] * cos($max_cut_angle_rad);
            $y2 = $tree['coordY'] - $tree['height'] * sin($max_cut_angle_rad);

            $crownx = $tree['coordX'] + ($tree['height'] + 5) * cos(deg2rad($tree['cut_angle']));
            $crowny = $tree['coordY'] - ($tree['height'] + 5) * sin(deg2rad($tree['cut_angle']));
        }

        $x0 = $tree['coordX'];
        $y0 = $tree['coordY'];

        // Check for affected trees
        foreach ($trees as &$other_tree) {
            if ($other_tree['treeNum'] !== $tree['treeNum']) {
                $xt = $other_tree['coordX'];
                $yt = $other_tree['coordY'];
                
                if (isPointInTriangle($x0, $y0, $x1, $y1, $x2, $y2, $xt, $yt)) {
                    $affected_felling[] = [
                        'cut_tree_num' => $tree['treeNum'],
                        'victim_tree_num' => $other_tree['treeNum'],
                        'damage_category' => 1 // Stem damage
                    ];
                    $other_tree['status'] = "victim_stem";
                    //$affected_felling[] = $other_tree['treeNum'];
                    //$other_tree['damage_stem'] = 1;

                    $batchUpdates[] = [
                        'treeNum' => $other_tree['treeNum'],
                        'status' => "victim_stem",
                        'cut_angle' => "NULL",
                        'damage_stem' => 1,
                        'damage_crown' => "NULL"
                    ];
                }

                // Check crown damage (5-meter radius)
                $crown_dx = $other_tree['coordX'] - $crownx;
                $crown_dy = $other_tree['coordY'] - $crowny;
                $dist_crown = sqrt($crown_dx ** 2 + $crown_dy ** 2);

                if ($dist_crown <= 5) {
                    $affected_crown[] = [
                        'cut_tree_num' => $tree['treeNum'],
                        'victim_tree_num' => $other_tree['treeNum'],
                        'damage_category' => 2 // Crown damage
                    ];
                    $other_tree['status'] = "victim_crown";
                    //$affected_crown[] = $other_tree['treeNum'];
                    //$other_tree['damage_crown'] = count($affected_crown);

                    // Add to batch updates immediately
                    $batchUpdates[] = [
                        'treeNum' => $other_tree['treeNum'],
                        'status' => "victim_crown",
                        'cut_angle' => "NULL",
                        'damage_stem' => "NULL",
                        'damage_crown' => 1
                    ];
                }
            }
        }
        // Store affected felling and crown records
        $victimRecords = array_merge($victimRecords, $affected_felling, $affected_crown);
    }

    // Add the current tree to batch updates
    $batchUpdates[] = [
        'treeNum' => $tree['treeNum'],
        'status' => $tree['status'],
        'prod' => is_null($tree['prod']) || $tree['prod'] === 0 ? "NULL" : number_format($tree['prod'], 2, '.', ''),
        'cut_angle' => is_null($tree['cut_angle']) || $tree['cut_angle'] == 0 ? "NULL" : $tree['cut_angle'],
        'damage_stem' => $tree['damage_stem'] > 0 ? $tree['damage_stem'] : "NULL",
        'damage_crown' => $tree['damage_crown'] > 0 ? $tree['damage_crown'] : "NULL"
    ];

    // Process batch if size limit reached
    if (count($batchUpdates) >= 1000) {
        updateBatch($conn, $batchUpdates);
        $batchUpdates = [];
    }
}

/* Update any remaining records
if (!empty($batchUpdates)) {
    updateBatch($conn, $batchUpdates);
}
    */

echo "Batch updates complete.<br>";

// Insert victim data in batches
if (!empty($victimRecords)) {
    insertVictimData($conn, $victimRecords);
}

$conn->close();

/**
 * Update tree_data table in batches.
 *
 * @param mysqli $conn The database connection.
 * @param array $batchUpdates Array of updates.
 */
function updateBatch($conn, $batchUpdates) {
    $case_status = [];
    $case_prod = [];
    $case_cut_angle = [];
    $treeNums = [];
    $case_stem = [];
    $case_crown = [];

    foreach ($batchUpdates as $update) {
        $treeNum = $update['treeNum'];
        $status = $update['status'];
        // If the status is 'keep', prod should be NULL
        if ($status === 'keep') {
            $prod = "NULL";
        } else {
            // Otherwise, prod should have a numerical value (e.g., volume)
            $prod = isset($update['prod']) && $update['prod'] !== NULL ? $update['prod'] : "NULL";
        }
        $cut_angle = is_null($update['cut_angle']) ? "NULL" : ($update['cut_angle'] === "" ? "NULL" : $update['cut_angle']);
        $damage_stem = (is_null($update['damage_stem']) || $update['damage_stem'] === 0) ? "NULL" : $update['damage_stem'];
        $damage_crown = (is_null($update['damage_crown']) || $update['damage_crown'] === 0) ? "NULL" : $update['damage_crown'];

        $case_status[] = "WHEN '$treeNum' THEN '$status'";
        $case_prod[] = "WHEN '$treeNum' THEN $prod";
        $case_cut_angle[] = "WHEN '$treeNum' THEN $cut_angle";
        $case_stem[] = "WHEN '$treeNum' THEN $damage_stem";
        $case_crown[] = "WHEN '$treeNum' THEN $damage_crown";
        $treeNums[] = "'$treeNum'";
    }
    // Execute single batch update outside the loop
    if (!empty($treeNums)) {
        $update_sql = "
            UPDATE tree_data
            SET
                status = CASE treeNum " . implode(" ", $case_status) . " END,
                prod = CASE treeNum " . implode(" ", $case_prod) . " END,
                cut_angle = CASE treeNum " . implode(" ", $case_cut_angle) . " END,
                damage_stem = CASE treeNum " . implode(" ", $case_stem) . " END,
                damage_crown = CASE treeNum " . implode(" ", $case_crown) . " END
            WHERE treeNum IN (" . implode(", ", $treeNums) . ")";
        
        if (!$conn->query($update_sql)) {
            echo "Error during batch update: " . $conn->error . "<br>";
        } else {
            echo "Batch of " . count($batchUpdates) . " rows updated successfully.<br>";
        }
    }
}

function isPointInTriangle($x0, $y0, $x1, $y1, $x2, $y2, $xt, $yt) {
    // Calculate the total area of the triangle
    $areaTotal = calculateArea($x0, $y0, $x1, $y1, $x2, $y2);

    // Calculate the areas of the sub-triangles
    $area1 = calculateArea($xt, $yt, $x1, $y1, $x2, $y2);
    $area2 = calculateArea($x0, $y0, $xt, $yt, $x2, $y2);
    $area3 = calculateArea($x0, $y0, $x1, $y1, $xt, $yt);

    // Check if the sum of sub-triangle areas equals the total area
    return abs($areaTotal - ($area1 + $area2 + $area3)) < 1e-6;
}

// Function to calculate the area of a triangle
function calculateArea($xA, $yA, $xB, $yB, $xC, $yC) {
    return abs(($xA * ($yB - $yC) + $xB * ($yC - $yA) + $xC * ($yA - $yB)) / 2.0);
}

// Add a function to insert victim data into the tree_victims table
function insertVictimData($conn, $victimData) {
    if (empty($victimData)) {
        return;
    }

    // Create a temporary table to store the values
    $createTempTable = "CREATE TEMPORARY TABLE temp_victims (
        cut_tree_num VARCHAR(255),
        victim_tree_num VARCHAR(255),
        damage_category INT
    )";
    
    if (!$conn->query($createTempTable)) {
        echo "Error creating temporary table: " . $conn->error . "<br>";
        return;
    }

    // Prepare statement for inserting into temporary table
    $stmt = $conn->prepare("INSERT INTO temp_victims (cut_tree_num, victim_tree_num, damage_category) VALUES (?, ?, ?)");
    
    if (!$stmt) {
        echo "Error preparing statement: " . $conn->error . "<br>";
        return;
    }

    // Insert records into temporary table
    foreach ($victimData as $data) {
        $cut_tree_num = $data['cut_tree_num'];
        $victim_tree_num = $data['victim_tree_num'];
        $damage_category = (int) $data['damage_category'];
        
        $stmt->bind_param("ssi", $cut_tree_num, $victim_tree_num, $damage_category);
        
        if (!$stmt->execute()) {
            echo "Error inserting into temporary table: " . $stmt->error . "<br>";
        }
    }
    
    $stmt->close();

    // Insert from temporary table to actual table with duplicate handling
    $insertSQL = "INSERT INTO tree_victims (cut_tree_num, victim_tree_num, damage_category)
                 SELECT cut_tree_num, victim_tree_num, damage_category 
                 FROM temp_victims
                 ON DUPLICATE KEY UPDATE damage_category = VALUES(damage_category)";
    
    if (!$conn->query($insertSQL)) {
        echo "Error inserting victim data: " . $conn->error . "<br>";
    } else {
        echo "Inserted " . count($victimData) . " victim records successfully.<br>";
    }

    // Drop temporary table
    $conn->query("DROP TEMPORARY TABLE IF EXISTS temp_victims");
}

?>