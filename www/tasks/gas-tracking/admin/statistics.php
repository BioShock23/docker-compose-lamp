<?php
session_start();


require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../lib/utilities.php";
require_once __DIR__ . "/../src/models/User.php";
require_once __DIR__ . "/../src/models/TransportRecord.php";
require_once __DIR__ . "/../src/models/Region.php";

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('dashboard.php');
}

$pageTitle = "Системная статистика";
$useChartJs = true;

$user = new User();
$transportRecord = new TransportRecord();
$region = new Region();

$db = getConnection();

$stats = [
    'users' => [
        'total' => $db->query("SELECT COUNT(*) FROM user")->fetchColumn(),
        'active' => $db->query("SELECT COUNT(*) FROM user WHERE is_active = 1")->fetchColumn(),
        'by_role' => $db->query("SELECT r.role_name, COUNT(ur.user_id) as count 
                                 FROM role r 
                                 LEFT JOIN user_role ur ON r.id = ur.role_id 
                                 LEFT JOIN user u ON ur.user_id = u.id AND u.is_active = 1
                                 GROUP BY r.id, r.role_name 
                                 ORDER BY count DESC")->fetchAll()
    ],
    'regions' => [
        'total' => $db->query("SELECT COUNT(*) FROM region")->fetchColumn(),
        'with_nodes' => $db->query("SELECT COUNT(DISTINCT region_id) FROM node")->fetchColumn(),
        'details' => $db->query("SELECT r.name, 
                                        COUNT(DISTINCT n.id) as nodes,
                                        COUNT(DISTINCT u.id) as users,
                                        COUNT(DISTINCT tr.id) as transports
                                 FROM region r
                                 LEFT JOIN node n ON r.id = n.region_id
                                 LEFT JOIN user u ON r.id = u.region_id AND u.is_active = 1
                                 LEFT JOIN transport_record tr ON n.id IN (
                                     SELECT from_node_id FROM segment WHERE id = tr.segment_id
                                     UNION
                                     SELECT to_node_id FROM segment WHERE id = tr.segment_id
                                 )
                                 GROUP BY r.id, r.name
                                 ORDER BY nodes DESC")->fetchAll()
    ],
    'nodes' => [
        'total' => $db->query("SELECT COUNT(*) FROM node")->fetchColumn(),
        'by_type' => $db->query("SELECT nt.type_name, COUNT(n.id) as count 
                                 FROM node_type nt 
                                 LEFT JOIN node n ON nt.id = n.type_id 
                                 GROUP BY nt.id, nt.type_name 
                                 ORDER BY count DESC")->fetchAll()
    ],
    'segments' => [
        'total' => $db->query("SELECT COUNT(*) FROM segment")->fetchColumn(),
        'by_method' => $db->query("SELECT tm.method_name, COUNT(s.id) as count 
                                   FROM transport_method tm 
                                   LEFT JOIN segment s ON tm.id = s.method_id 
                                   GROUP BY tm.id, tm.method_name 
                                   ORDER BY count DESC")->fetchAll()
    ],
    'transports' => [
        'total' => $db->query("SELECT COUNT(*) FROM transport_record")->fetchColumn(),
        'last_30_days' => $db->query("SELECT COUNT(*) FROM transport_record 
                                      WHERE departure_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
        'by_grade' => $db->query("SELECT gg.grade_name, COUNT(tr.id) as count 
                                  FROM gas_grade gg 
                                  LEFT JOIN transport_record tr ON gg.id = tr.grade_id 
                                  GROUP BY gg.id, gg.grade_name 
                                  ORDER BY count DESC")->fetchAll(),
        'monthly_volume' => $db->query("SELECT DATE_FORMAT(departure_time, '%Y-%m') as month,
                                               SUM(amount) as total_volume,
                                               COUNT(*) as total_transports
                                        FROM transport_record 
                                        WHERE departure_time >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                                        GROUP BY DATE_FORMAT(departure_time, '%Y-%m')
                                        ORDER BY month")->fetchAll()
    ]
];

$flashMessages = getFlashMessages();

include __DIR__ . "/../src/views/header.php";
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3"><i class="fas fa-chart-bar me-2"></i>Системная статистика</h1>
                <a href="<?= $baseUrl ?>/pages/admin.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Назад к админ панели
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

    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Пользователей
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $stats['users']['active'] ?> / <?= $stats['users']['total'] ?>
                            </div>
                            <div class="text-xs text-muted">Активных / Всего</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-left-success shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Регионов с узлами
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $stats['regions']['with_nodes'] ?> / <?= $stats['regions']['total'] ?>
                            </div>
                            <div class="text-xs text-muted">С узлами / Всего</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-map-marker-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-left-info shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Узлов / Сегментов
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $stats['nodes']['total'] ?> / <?= $stats['segments']['total'] ?>
                            </div>
                            <div class="text-xs text-muted">Инфраструктура</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-project-diagram fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Транспортировок
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $stats['transports']['last_30_days'] ?>
                            </div>
                            <div class="text-xs text-muted">За последние 30 дней</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-shipping-fast fa-2x text-gray-300"></i>
                        </div>
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
                        <i class="fas fa-users me-2"></i>Пользователи по ролям
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Роль</th>
                                    <th>Количество</th>
                                    <th>Процент</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['users']['by_role'] as $role): ?>
                                <tr>
                                    <td><?= htmlspecialchars(ucfirst($role['role_name'])) ?></td>
                                    <td><?= $role['count'] ?></td>
                                    <td>
                                        <?php 
                                        $percentage = $stats['users']['active'] > 0 ? round(($role['count'] / $stats['users']['active']) * 100, 1) : 0;
                                        ?>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" role="progressbar" style="width: <?= $percentage ?>%">
                                                <?= $percentage ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-pie me-2"></i>Объем транспортировки по месяцам
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="monthlyVolumeChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-sitemap me-2"></i>Узлы по типам
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Тип узла</th>
                                    <th>Количество</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['nodes']['by_type'] as $nodeType): ?>
                                <tr>
                                    <td><?= htmlspecialchars($nodeType['type_name']) ?></td>
                                    <td><span class="badge bg-info"><?= $nodeType['count'] ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-truck me-2"></i>Сегменты по методам транспортировки
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Метод</th>
                                    <th>Количество</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['segments']['by_method'] as $method): ?>
                                <tr>
                                    <td><?= htmlspecialchars($method['method_name']) ?></td>
                                    <td><span class="badge bg-success"><?= $method['count'] ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-map me-2"></i>Детализация по регионам
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Регион</th>
                                    <th>Узлов</th>
                                    <th>Пользователей</th>
                                    <th>Транспортировок</th>
                                    <th>Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['regions']['details'] as $regionDetail): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($regionDetail['name']) ?></strong></td>
                                    <td><span class="badge bg-info"><?= $regionDetail['nodes'] ?></span></td>
                                    <td><span class="badge bg-primary"><?= $regionDetail['users'] ?></span></td>
                                    <td><span class="badge bg-success"><?= $regionDetail['transports'] ?></span></td>
                                    <td>
                                        <?php if ($regionDetail['nodes'] > 0 && $regionDetail['users'] > 0): ?>
                                            <span class="badge bg-success">Активный</span>
                                        <?php elseif ($regionDetail['nodes'] > 0): ?>
                                            <span class="badge bg-warning">Есть узлы</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Пустой</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const monthlyData = <?= json_encode($stats['transports']['monthly_volume']) ?>;
    
    if (monthlyData.length > 0) {
        const ctx = document.getElementById('monthlyVolumeChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: monthlyData.map(item => item.month),
                datasets: [{
                    label: 'Объем (м³)',
                    data: monthlyData.map(item => parseFloat(item.total_volume || 0)),
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Объем транспортировки газа по месяцам'
                    },
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Объем (м³)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Месяц'
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php include __DIR__ . "/../src/views/footer.php"; ?> 