<?php
$pdo = new PDO('mysql:host=localhost;dbname=dte_sistema', 'root', '123123');
$stmt = $pdo->query('DESCRIBE folios');
echo "Estructura tabla folios:\n";
while ($row = $stmt->fetch()) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
