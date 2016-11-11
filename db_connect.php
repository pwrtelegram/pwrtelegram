<?php

require_once '../db_connect.php';
$pdo = new PDO($deep ? $deepdb : $db, $deep ? $deepdbuser : $dbuser, $deep ? $deepdbpassword : $dbpassword);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
