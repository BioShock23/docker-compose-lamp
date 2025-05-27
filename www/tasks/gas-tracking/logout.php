<?php
session_start();
require_once __DIR__ . "/src/controllers/AuthController.php";

$controller = new AuthController();
$controller->logout();
?> 