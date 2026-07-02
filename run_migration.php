<?php
require_once '../config/db.php';

$sqlFile = 'stock_transfer_schema.sql';
if (!file_exists($sqlFile)) {
    die("SQL file not found.");
}

$sql = file_get_contents($sqlFile);
if (mysqli_multi_query($conn, $sql)) {
    do {
        // Store first result set
        if ($result = mysqli_store_result($conn)) {
            mysqli_free_result($result);
        }
    } while (mysqli_next_result($conn));
    echo "Migration successful! Stock transfer tables created.";
} else {
    echo "Error executing migration: " . mysqli_error($conn);
}
?>
