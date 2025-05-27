<?php
$baseUrl = getBaseUrl();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Система Учета Газа</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/css/styles.css">
    <?php if (isset($useLeaflet) && $useLeaflet): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A==" crossorigin="" />
    <?php endif; ?>
    <?php if (isset($useChartJs) && $useChartJs): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="<?= $baseUrl ?>/index.php">
                    <i class="fas fa-gas-pump me-2"></i>Учет Газа
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= $baseUrl ?>/pages/dashboard.php">Панель</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= $baseUrl ?>/pages/map.php">
                                    <i class="fas fa-map-marked-alt me-1"></i>Карта
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= $baseUrl ?>/pages/statistics.php">
                                    <i class="fas fa-chart-bar me-1"></i>Статистика
                                </a>
                            </li>
                            <?php if (in_array('employee', $_SESSION['roles'] ?? []) || in_array('admin', $_SESSION['roles'] ?? [])): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= $baseUrl ?>/pages/transport_records.php">
                                        <i class="fas fa-shipping-fast me-1"></i>Транспортировки
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if (in_array('admin', $_SESSION['roles'] ?? [])): ?>
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-cog me-1"></i>Администратор
                                    </a>
                                    <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                                        <li><a class="dropdown-item" href="<?= $baseUrl ?>/admin/users.php">Пользователи</a></li>
                                        <li><a class="dropdown-item" href="<?= $baseUrl ?>/admin/regions.php">Регионы</a></li>
                                        <li><a class="dropdown-item" href="<?= $baseUrl ?>/admin/node_types.php">Типы Узлов</a></li>
                                        <li><a class="dropdown-item" href="<?= $baseUrl ?>/admin/transport_methods.php">Методы Транспортировки</a></li>
                                        <li><a class="dropdown-item" href="<?= $baseUrl ?>/admin/gas_grades.php">Марки Газа</a></li>
                                    </ul>
                                </li>
                            <?php endif; ?>
                            <?php if (!in_array('client', $_SESSION['roles'] ?? [])): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="managementDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-tools me-1"></i>Управление
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="managementDropdown">
                                    <li><a class="dropdown-item" href="<?= $baseUrl ?>/management/nodes.php">Узлы</a></li>
                                    <li><a class="dropdown-item" href="<?= $baseUrl ?>/management/segments.php">Сегменты</a></li>
                                </ul>
                            </li>
                            <?php endif; ?>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= $baseUrl ?>/pages/statistics.php">Статистика</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <ul class="navbar-nav">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['username']); ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                    <li><a class="dropdown-item" href="<?= $baseUrl ?>/pages/profile.php">Профиль</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?= $baseUrl ?>/src/views/auth/logout.php">Выход</a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= $baseUrl ?>/login.php">Вход</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <main class="container py-4">
        <?php if (isset($flashMessages) && !empty($flashMessages)): ?>
            <?php foreach ($flashMessages as $type => $message): ?>
                <div class="alert alert-<?php echo $type === 'error' ? 'danger' : $type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?> 