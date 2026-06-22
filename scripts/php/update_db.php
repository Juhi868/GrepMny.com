<?php
require_once 'db.php';
$conn->query("ALTER TABLE login ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL;");
echo "Column added.";
?>
