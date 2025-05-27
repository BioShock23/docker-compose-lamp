<?php
session_start();

if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . "/lib/utilities.php";
    redirect('/pages/dashboard.php');
}

require_once __DIR__ . "/src/controllers/AuthController.php";
require_once __DIR__ . "/lib/utilities.php";

$controller = new AuthController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->login();
}

$flashMessages = getFlashMessages();

include __DIR__ . "/src/views/auth/login.php";
?> 