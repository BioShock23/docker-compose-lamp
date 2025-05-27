<?php
session_start();

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/lib/utilities.php";

if (isset($_SESSION['user_id'])) {
    redirect('pages/dashboard.php');
} else {
    redirect('pages/statistics.php');
}
?> 