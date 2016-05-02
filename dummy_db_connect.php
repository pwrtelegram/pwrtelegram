<?php
$pdo = new PDO('mysql:host=localhost;dbname=pwrtelegram', "user", "pass");
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

?>
