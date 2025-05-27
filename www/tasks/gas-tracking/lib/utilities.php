<?php

require_once __DIR__ . "/../config/database.php";

function sanitizeInput($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitizeInput($value);
        }
        return $data;
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function hasRole($roleName) {
    if (!isLoggedIn()) {
        return false;
    }
    
    return in_array($roleName, $_SESSION['roles'] ?? []);
}

function isGuest() {
    return !isLoggedIn();
}

function canViewStatistics() {
    return true;
}

function canManageNodes() {
    return hasRole('employee') || hasRole('admin');
}

function canManageSegments() {
    return hasRole('employee') || hasRole('admin');
}

function canManageUsers() {
    return hasRole('admin');
}

function canViewAllRegions() {
    return hasRole('admin');
}

function canViewRegion($regionId) {
    if (hasRole('admin')) {
        return true;
    }
    
    if (hasRole('employee')) {
        return $_SESSION['region_id'] == $regionId;
    }
    
    return false;
}

function getUserAccessLevel() {
    if (hasRole('admin')) return 'admin';
    if (hasRole('employee')) return 'employee';
    if (hasRole('client')) return 'client';
    return 'guest';
}

function getCurrentTimestamp() {
    return date('Y-m-d H:i:s');
}

function pointToGeoJson($pointWkb) {
    if (!$pointWkb) {
        return null;
    }
    
    $point = unpack('corder/Ltype/ddlon/ddlat', $pointWkb);
    
    return [
        'type' => 'Point',
        'coordinates' => [$point['lon'], $point['lat']]
    ];
}

function linestringToGeoJson($linestringWkb) {
    if (!$linestringWkb) {
        return null;
    }
    
    $numPoints = unpack('L', substr($linestringWkb, 9, 4))[1];
    $coordinates = [];
    
    for ($i = 0; $i < $numPoints; $i++) {
        $point = unpack('ddlon/ddlat', substr($linestringWkb, 13 + ($i * 16), 16));
        $coordinates[] = [$point['lon'], $point['lat']];
    }
    
    return [
        'type' => 'LineString',
        'coordinates' => $coordinates
    ];
}

function redirect($path) {
    $baseUrl = getBaseUrl();
    if (strpos($path, '/') === 0) {
        header("Location: $baseUrl$path");
    } else {
        header("Location: $baseUrl/$path");
    }
    exit;
}

function setFlashMessage($type, $message) {
    if (!isset($_SESSION)) {
        session_start();
    }
    
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    
    if (!isset($_SESSION['flash_messages'][$type])) {
        $_SESSION['flash_messages'][$type] = [];
    }
    
    $_SESSION['flash_messages'][$type][] = $message;
}

function getFlashMessages() {
    if (!isset($_SESSION)) {
        session_start();
    }
    
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    
    return $messages;
}

function createMysqlPoint($lat, $lon) {
    return "POINT($lon $lat)";
}

function createMysqlLinestring($points) {
    $pointStrings = [];
    foreach ($points as $point) {
        $pointStrings[] = "{$point[1]} {$point[0]}";
    }
    
    return "LINESTRING(" . implode(", ", $pointStrings) . ")";
}

function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    $scriptPath = $_SERVER['SCRIPT_NAME'];
    $projectRoot = '/tasks/gas-tracking';
    
    return $protocol . '://' . $host . $projectRoot;
} 