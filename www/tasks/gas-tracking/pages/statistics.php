<?php
session_start();


require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../lib/utilities.php";
require_once __DIR__ . "/../src/models/TransportRecord.php";

$useChartJs = true;
$pageTitle = "Статистика Транспортировки Газа";

$transportRecord = new TransportRecord();
$publicStats = $transportRecord->getPublicStatistics();
$monthlyStats = $transportRecord->getMonthlyStatistics();
$gradeStats = $transportRecord->getGradeStatistics();

include __DIR__ . "/../src/views/header.php";
?>

<div class="jumbotron bg-light p-5 rounded-3 mb-4">
    <div class="container">
        <h1 class="display-4"><i class="fas fa-chart-bar text-primary me-3"></i>Статистика Транспортировки Газа</h1>
        <p class="lead">Публичная статистика по транспортировке газа в системе.</p>
    </div>
</div>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card text-center bg-primary text-white">
                <div class="card-body">
                    <i class="fas fa-route fa-2x mb-2"></i>
                    <h5 class="card-title">Активные Маршруты</h5>
                    <h2><?= $publicStats['active_routes'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center bg-success text-white">
                <div class="card-body">
                    <i class="fas fa-shipping-fast fa-2x mb-2"></i>
                    <h5 class="card-title">Всего Транспортировок</h5>
                    <h2><?= $publicStats['total_transports'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center bg-info text-white">
                <div class="card-body">
                    <i class="fas fa-gas-pump fa-2x mb-2"></i>
                    <h5 class="card-title">Общий Объем (м³)</h5>
                    <h2><?= number_format($publicStats['total_volume'] ?? 0) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center bg-warning text-white">
                <div class="card-body">
                    <i class="fas fa-map-marker-alt fa-2x mb-2"></i>
                    <h5 class="card-title">Узлов в Системе</h5>
                    <h2><?= $publicStats['total_nodes'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Месячная Статистика</h5>
                </div>
                <div class="card-body">
                    <canvas id="monthlyChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>По Маркам Газа</h5>
                </div>
                <div class="card-body">
                    <canvas id="gradeChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const monthlyStats = <?= json_encode($monthlyStats ?? []) ?>;
    const gradeStats = <?= json_encode($gradeStats ?? []) ?>;
    
    if (monthlyStats.length > 0) {
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthlyStats.map(item => item.month),
                datasets: [{
                    label: 'Объем транспортировки (м³)',
                    data: monthlyStats.map(item => parseFloat(item.total_volume)),
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
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
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Месячные Объемы Транспортировки'
                    }
                }
            }
        });
    }
    
    if (gradeStats.length > 0) {
        const gradeCtx = document.getElementById('gradeChart').getContext('2d');
        new Chart(gradeCtx, {
            type: 'doughnut',
            data: {
                labels: gradeStats.map(item => item.grade_name),
                datasets: [{
                    data: gradeStats.map(item => parseFloat(item.total_volume)),
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 205, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                        'rgba(255, 159, 64, 0.8)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Распределение по Маркам Газа'
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
});
</script>

<?php include __DIR__ . "/../src/views/footer.php"; ?> 