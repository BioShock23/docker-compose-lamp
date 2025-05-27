<?php
session_start();

require_once __DIR__ . "/../../../config/database.php";
require_once __DIR__ . "/../../controllers/AuthController.php";

$controller = new AuthController();
$controller->logout();
?> 