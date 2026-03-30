<?php
require_once 'config/config.php';

try {
    $pdo = db();
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>Koneksi database berhasil</h3>";
    echo "<p>Tabel ditemukan: " . count($tables) . "</p>";
    echo "<ul>";
    foreach ($tables as $t) echo "<li>$t</li>";
    echo "</ul>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
