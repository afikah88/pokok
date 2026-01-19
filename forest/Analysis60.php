<?php 
set_include_path('../');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forest Analysis</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
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

        .block-select {
            width: 100%;
            max-width: 200px;
            margin: 0 auto 2rem;
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
        }

        .chart-container {
            position: relative;
            height: 600px;
            width: 100%;
            margin-top: 2rem;
        }

        h3 {
            color: #2d5a27;
            font-size: 1.5rem;
            margin: 0 1rem 1rem;
            border-bottom: 3px solid #4a7c59;
            padding-bottom: 0.75rem;
            text-align: left;
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

echo "<div class='container'>";

// Get list of available block coordinates
$blockQuery = "SELECT DISTINCT block_x, block_y FROM tree_data60 ORDER BY block_x, block_y";
$blockResult = $conn->query($blockQuery);

echo "<form method='GET' class='block-select'>";
echo "<select name='block' onchange='this.form.submit()'>";
echo "<option value=''>Select Block</option>";

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

    echo "<h3>Forest Analysis - Block ($block_x, $block_y)</h3>";
    echo "<div class='chart-container'><canvas id='forestChart'></canvas></div>";

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
            END AS group_name,
            species_group,
            SUM(CASE WHEN status = 'cut' AND diameter >= 45 THEN volume ELSE 0 END) as production0,
            COUNT(CASE WHEN status = 'victim_stem' AND diameter >= 45 THEN 1 END) as damage,
            SUM(CASE WHEN diameter30 >= 45 THEN volume30 ELSE 0 END) as growth30,
            SUM(CASE 
                WHEN (status = 'victim_crown' OR status = 'keep') 
                AND diameter30 >= 45 
                AND species_group IN (1, 2, 3, 5) 
                THEN volume30 
                ELSE 0 
            END) as production30
        FROM tree_data60
        WHERE block_x = '$block_x' AND block_y = '$block_y'
        GROUP BY species_group
        ORDER BY species_group";

    $result = $conn->query($sql);
    $labels = [];
    $production0Data = [];
    $damageData = [];
    $growth30Data = [];
    $production30Data = [];

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $labels[] = $row['group_name'];
            $production0Data[] = round($row['production0'], 2);
            $damageData[] = intval($row['damage']);
            $growth30Data[] = round($row['growth30'], 2);
            $production30Data[] = round($row['production30'], 2);
        }
    }

    // Convert PHP arrays to JavaScript arrays
    $labelsJSON = json_encode($labels);
    $production0JSON = json_encode($production0Data);
    $damageJSON = json_encode($damageData);
    $growth30JSON = json_encode($growth30Data);
    $production30JSON = json_encode($production30Data);

    echo "<script>
        const ctx = document.getElementById('forestChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: $labelsJSON,
                datasets: [
                    {
                        label: 'Production 0 (m³)',
                        data: $production0JSON,
                        backgroundColor: 'rgba(56, 180, 218, 0.6)',
                        borderColor: 'rgb(86, 221, 245)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Damage (count)',
                        data: $damageJSON,
                        backgroundColor: 'rgba(255, 99, 132, 0.6)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1,
                        yAxisID: 'y1'
                    },
                    {
                        label: 'Growth 30 (m³)',
                        data: $growth30JSON,
                        backgroundColor: 'rgba(54, 218, 103, 0.6)',
                        borderColor: 'rgb(54, 235, 123)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Production 30 (m³)',
                        data: $production30JSON,
                        backgroundColor: 'rgba(175, 82, 218, 0.6)',
                        borderColor: 'rgb(149, 94, 221)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Volume (m³)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Damage Count'
                        },
                        min: 0,
                        max: 10,
                        ticks: {
                            stepSize: 1
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Forest Block Analysis'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += context.parsed.y;
                                return label;
                            }
                        }
                    }
                }
            }
        });
    </script>";
}

echo "</div>";
$conn->close();
?>
</body>
</html>