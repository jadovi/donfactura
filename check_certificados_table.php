<?php
$pdo = new PDO('mysql:host=localhost;dbname=dte_sistema', 'root', '123123');
$stmt = $pdo->query('DESCRIBE certificados');
echo "Estructura tabla certificados:\n";
while ($row = $stmt->fetch()) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
