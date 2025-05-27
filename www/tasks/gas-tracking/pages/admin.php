<?php
session_start();


require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../lib/utilities.php";
require_once __DIR__ . "/../src/models/User.php";
require_once __DIR__ . "/../src/models/Region.php";

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('dashboard.php');
}

$baseUrl = getBaseUrl();
$pageTitle = "Администрирование";

$user = new User();
$region = new Region();

$regions = $region->getAll();

$flashMessages = getFlashMessages();

include __DIR__ . "/../src/views/header.php";
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3"><i class="fas fa-cogs me-2"></i>Администрирование</h1>
                <a href="<?= $baseUrl ?>/pages/dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Назад к панели
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($flashMessages)): ?>
        <?php foreach ($flashMessages as $type => $message): ?>
            <div class="alert alert-<?= $type === 'error' ? 'danger' : $type ?> alert-dismissible fade show">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-map-marker-alt me-2"></i>Управление регионами
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <a href="<?= $baseUrl ?>/admin/regions.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Добавить регион
                        </a>
                    </div>
                    
                    <?php if (empty($regions)): ?>
                        <div class="alert alert-info">Регионы не найдены.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Название</th>
                                        <th>Узлов</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($regions, 0, 5) as $regionItem): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($regionItem['name']) ?></td>
                                        <td><?= $regionItem['node_count'] ?></td>
                                        <td>
                                            <a href="<?= $baseUrl ?>/admin/regions.php?edit=<?= $regionItem['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (count($regions) > 5): ?>
                                <div class="text-center">
                                    <a href="<?= $baseUrl ?>/admin/regions.php" class="btn btn-outline-primary btn-sm">
                                        Показать все (<?= count($regions) ?>)
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-users me-2"></i>Управление пользователями
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <a href="<?= $baseUrl ?>/admin/users.php" class="btn btn-primary">
                            <i class="fas fa-users-cog me-2"></i>Управление пользователями
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-industry me-2"></i>Справочники системы
                    </h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="<?= $baseUrl ?>/admin/node_types.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-sitemap me-2"></i>Типы узлов
                        </a>
                        <a href="<?= $baseUrl ?>/admin/transport_methods.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-truck me-2"></i>Методы транспортировки
                        </a>
                        <a href="<?= $baseUrl ?>/admin/gas_grades.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-vial me-2"></i>Марки газа
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-database me-2"></i>Системные операции
                    </h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="<?= $baseUrl ?>/admin/statistics.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-chart-bar me-2"></i>Системная статистика
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-info-circle me-2"></i>Информация о системе
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="h4 text-primary"><?= count($regions) ?></div>
                                <div class="text-muted">Регионов</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="h4 text-success">
                                    <?php
                                    $stmt = $user->db ?? getConnection();
                                    $result = $stmt->query("SELECT COUNT(*) FROM user WHERE is_active = 1");
                                    echo $result->fetchColumn();
                                    ?>
                                </div>
                                <div class="text-muted">Активных пользователей</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="h4 text-info">
                                    <?php
                                    $result = $stmt->query("SELECT COUNT(*) FROM node");
                                    echo $result->fetchColumn();
                                    ?>
                                </div>
                                <div class="text-muted">Узлов</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="h4 text-warning">
                                    <?php
                                    $result = $stmt->query("SELECT COUNT(*) FROM transport_record WHERE departure_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                                    echo $result->fetchColumn();
                                    ?>
                                </div>
                                <div class="text-muted">Транспортировок за месяц</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../src/views/footer.php"; ?> 