<?php
session_start();


require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../lib/utilities.php";
require_once __DIR__ . "/../src/models/User.php";
require_once __DIR__ . "/../src/models/TransportRecord.php";
require_once __DIR__ . "/../src/models/Node.php";
require_once __DIR__ . "/../src/models/Segment.php";

if (!isset($_SESSION['user_id'])) {
    redirect('../login.php');
}

$baseUrl = getBaseUrl();
$pageTitle = "Панель Управления";
$useChartJs = true;

$user = new User();
$transportRecord = new TransportRecord();
$userRoles = $_SESSION['roles'] ?? [];

$isAdmin = hasRole('admin');
$isEmployee = hasRole('employee');
$isClient = hasRole('client');

$currentUser = $user->getUserById($_SESSION['user_id']);

if ($isClient) {
    $userTransports = $transportRecord->getTransportsByReceiver($_SESSION['user_id']);
    $userStats = $transportRecord->getStatisticsByReceiver($_SESSION['user_id'], 30);
} elseif ($isEmployee && !empty($_SESSION['region_id'])) {
    $regionTransports = $transportRecord->getTransportsByRegion($_SESSION['region_id']);
    $regionStats = $transportRecord->getStatisticsByRegion($_SESSION['region_id'], 30);
} elseif ($isAdmin) {
    $allTransports = $transportRecord->getAllTransports(100);
    $globalStats = $transportRecord->getStatistics(30);
}

include __DIR__ . "/../src/views/header.php";
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3"><i class="fas fa-tachometer-alt me-2"></i>Панель Управления</h1>
                <div class="text-muted">
                    Добро пожаловать, <strong><?= htmlspecialchars($currentUser['username']) ?></strong>
                    <?php if (!empty($currentUser['region_name'])): ?>
                        | Регион: <strong><?= htmlspecialchars($currentUser['region_name']) ?></strong>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                <?php if ($isClient): ?>
                                    Ваши транспортировки
                                <?php elseif ($isEmployee): ?>
                                    Транспортировки в регионе
                                <?php else: ?>
                                    Всего транспортировок
                                <?php endif; ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php 
                                if ($isClient && isset($userTransports)) {
                                    echo count($userTransports);
                                } elseif ($isEmployee && isset($regionTransports)) {
                                    echo count($regionTransports);
                                } elseif ($isAdmin && isset($allTransports)) {
                                    echo count($allTransports);
                                } else {
                                    echo '0';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-shipping-fast fa-2x text-gray-300"></i>
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
                                Объем за месяц (м³)
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php 
                                $monthlyVolume = 0;
                                if ($isClient && isset($userStats)) {
                                    $monthlyVolume = array_sum(array_column($userStats, 'total_amount'));
                                } elseif ($isEmployee && isset($regionStats)) {
                                    $monthlyVolume = array_sum(array_column($regionStats, 'total_amount'));
                                } elseif ($isAdmin && isset($globalStats)) {
                                    $monthlyVolume = array_sum(array_column($globalStats, 'total_amount'));
                                }
                                echo number_format($monthlyVolume, 2);
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-bar fa-2x text-gray-300"></i>
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
                                Роли
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= implode(', ', array_map('ucfirst', $userRoles)) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-tag fa-2x text-gray-300"></i>
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
                                Последний вход
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                <?= $currentUser['last_login'] ? date('d.m.Y H:i', strtotime($currentUser['last_login'])) : 'Впервые' ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-line me-2"></i>
                        <?php if ($isClient): ?>
                            Ваша статистика транспортировки за месяц
                        <?php elseif ($isEmployee): ?>
                            Статистика по региону за месяц
                        <?php else: ?>
                            Общая статистика за месяц
                        <?php endif; ?>
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="statisticsChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-tools me-2"></i>Быстрые действия
                    </h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="<?= $baseUrl ?>/pages/map.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-map me-2"></i>Открыть карту
                        </a>
                        
                        <?php if ($isAdmin): ?>
                            <a href="<?= $baseUrl ?>/pages/admin.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-cog me-2"></i>Администрирование
                            </a>
                            <a href="<?= $baseUrl ?>/admin/users.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-users me-2"></i>Управление пользователями
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($isEmployee || $isAdmin): ?>
                            <a href="<?= $baseUrl ?>/pages/transport_records.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-plus me-2"></i>Добавить транспортировку
                            </a>
                        <?php endif; ?>
                        
                        <a href="<?= $baseUrl ?>/pages/profile.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-user me-2"></i>Профиль пользователя
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (($isClient && isset($userTransports)) || ($isEmployee && isset($regionTransports)) || ($isAdmin && isset($allTransports))): ?>
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list me-2"></i>
                        <?php if ($isClient): ?>
                            Ваши недавние транспортировки
                        <?php elseif ($isEmployee): ?>
                            Транспортировки в регионе
                        <?php else: ?>
                            Все транспортировки
                        <?php endif; ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Маршрут</th>
                                    <th>Марка газа</th>
                                    <th>Объем (м³)</th>
                                    <th>Отправление</th>
                                    <th>Прибытие</th>
                                    <?php if (!$isClient): ?>
                                        <th>Получатель</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $records = [];
                                if ($isClient && isset($userTransports)) {
                                    $records = array_slice($userTransports, 0, 10);
                                } elseif ($isEmployee && isset($regionTransports)) {
                                    $records = array_slice($regionTransports, 0, 10);
                                } elseif ($isAdmin && isset($allTransports)) {
                                    $records = array_slice($allTransports, 0, 10);
                                }
                                
                                foreach ($records as $record): ?>
                                <tr>
                                    <td><?= htmlspecialchars($record['id']) ?></td>
                                    <td><?= htmlspecialchars($record['from_node_name'] . ' → ' . $record['to_node_name']) ?></td>
                                    <td><?= htmlspecialchars($record['grade_name']) ?></td>
                                    <td><?= number_format($record['amount'], 2) ?></td>
                                    <td><?= date('d.m.Y H:i', strtotime($record['departure_time'])) ?></td>
                                    <td><?= date('d.m.Y H:i', strtotime($record['arrival_time'])) ?></td>
                                    <?php if (!$isClient): ?>
                                        <td><?= htmlspecialchars($record['receiver_username'] ?? 'N/A') ?></td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php 
    $chartData = [];
    if ($isClient && isset($userStats)) {
        $chartData = $userStats;
    } elseif ($isEmployee && isset($regionStats)) {
        $chartData = $regionStats;
    } elseif ($isAdmin && isset($globalStats)) {
        $chartData = $globalStats;
    }
    ?>
    
    const chartData = <?= json_encode($chartData) ?>;
    
    if (chartData.length > 0) {
        const dates = [...new Set(chartData.map(item => item.date))].sort();
        const grades = [...new Set(chartData.map(item => item.grade_name))];
        
        const datasets = grades.map((grade, index) => {
            const colors = [
                'rgba(54, 162, 235, 0.7)',
                'rgba(255, 99, 132, 0.7)',
                'rgba(75, 192, 192, 0.7)',
                'rgba(255, 206, 86, 0.7)',
                'rgba(153, 102, 255, 0.7)'
            ];
            
            const borderColors = [
                'rgba(54, 162, 235, 1)',
                'rgba(255, 99, 132, 1)',
                'rgba(75, 192, 192, 1)',
                'rgba(255, 206, 86, 1)',
                'rgba(153, 102, 255, 1)'
            ];
            
            return {
                label: grade,
                data: dates.map(date => {
                    const match = chartData.find(item => item.date === date && item.grade_name === grade);
                    return match ? parseFloat(match.total_amount) : 0;
                }),
                backgroundColor: colors[index % colors.length],
                borderColor: borderColors[index % borderColors.length],
                borderWidth: 2,
                fill: false,
                tension: 0.4
            };
        });
        
        const ctx = document.getElementById('statisticsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: datasets
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Объем транспортировки газа по дням'
                    },
                    legend: {
                        display: true,
                        position: 'top'
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
                            text: 'Дата'
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php include __DIR__ . "/../src/views/footer.php"; ?> 