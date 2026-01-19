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

// Retrieve data
$sql = "SELECT block_x, block_y, coord_x, coord_y, treeNum, species_group, diameter, height, volume, status, diameter30, volume30 FROM tree_data";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "Trees data retrieved successfully.<br>";

    // Loop through each row and update diameter30 and volume30 if status is 'cut'
    while ($row = $result->fetch_assoc()) {
        $status = $row['status'];
        $treeNum = $conn->real_escape_string($row['treeNum']); // Escape special characters for safety

        if ($status === 'cut') {
            // Update diameter30 and volume30 to NULL for cut trees
            $updateSql = "UPDATE tree_data SET diameter30 = NULL, volume30 = NULL WHERE treeNum = '$treeNum'";
            if ($conn->query($updateSql) === TRUE) {
                echo "TreeNum $treeNum updated successfully.<br>";
            } else {
                echo "Error updating TreeNum $treeNum: " . $conn->error . "<br>";
            }
        }
    }
} else {
    echo "No tree data found in the database.<br>";
}

// Close the database connection
$conn->close();

echo "Tree data update complete.";
?>
