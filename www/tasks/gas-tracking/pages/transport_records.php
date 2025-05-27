<?php
session_start();


require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../lib/utilities.php";
require_once __DIR__ . "/../src/models/TransportRecord.php";
require_once __DIR__ . "/../src/models/Segment.php";

if (!isset($_SESSION['user_id']) || (!hasRole('employee') && !hasRole('admin'))) {
    redirect('../login.php');
}

$baseUrl = getBaseUrl();
$pageTitle = "Управление транспортировками";

$transportRecord = new TransportRecord();
$segment = new Segment();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $segmentId = filter_input(INPUT_POST, 'segment_id', FILTER_SANITIZE_NUMBER_INT);
    $gradeId = filter_input(INPUT_POST, 'grade_id', FILTER_SANITIZE_NUMBER_INT);
    $receiverUserId = filter_input(INPUT_POST, 'receiver_user_id', FILTER_SANITIZE_NUMBER_INT);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $departureTime = $_POST['departure_time'] ?? '';
    $arrivalTime = $_POST['arrival_time'] ?? '';
    
    if (empty($segmentId)) {
        $errors[] = "Выберите сегмент маршрута.";
    }
    
    if (empty($gradeId)) {
        $errors[] = "Выберите марку газа.";
    }
    
    if (empty($receiverUserId)) {
        $errors[] = "Выберите получателя.";
    }
    
    if (empty($amount) || $amount <= 0) {
        $errors[] = "Введите корректный объем газа.";
    }
    
    if (empty($departureTime)) {
        $errors[] = "Введите время отправления.";
    }
    
    if (empty($arrivalTime)) {
        $errors[] = "Введите время прибытия.";
    }
    
    if ($departureTime && $arrivalTime && strtotime($departureTime) >= strtotime($arrivalTime)) {
        $errors[] = "Время прибытия должно быть позже времени отправления.";
    }
    
    if (empty($errors)) {
        if ($transportRecord->create($segmentId, $gradeId, $receiverUserId, $amount, $departureTime, $arrivalTime, $_SESSION['user_id'])) {
            setFlashMessage('success', 'Запись о транспортировке успешно создана.');
            redirect('transport_records.php');
        } else {
            $errors[] = "Ошибка при создании записи о транспортировке.";
        }
    }
}

$gasGrades = $transportRecord->getAllGasGrades();
$clients = $transportRecord->getClientsForDropdown();
$segments = $segment->getAll();

$currentUserRegionId = $_SESSION['region_id'] ?? null;
if (hasRole('employee') && !hasRole('admin')) {
    $transports = $transportRecord->getTransportsByRegion($currentUserRegionId);
} else {
    $transports = $transportRecord->getAllTransports(50);
}

$flashMessages = getFlashMessages();

include __DIR__ . "/../src/views/header.php";
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3"><i class="fas fa-shipping-fast me-2"></i>Управление транспортировками</h1>
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

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-plus me-2"></i>Добавить транспортировку
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="segment_id" class="form-label">Сегмент маршрута</label>
                            <select class="form-select" id="segment_id" name="segment_id" required>
                                <option value="">Выберите сегмент</option>
                                <?php foreach ($segments as $segmentItem): ?>
                                    <option value="<?= $segmentItem['id'] ?>">
                                        <?= htmlspecialchars($segmentItem['from_node_name']) ?> → 
                                        <?= htmlspecialchars($segmentItem['to_node_name']) ?> 
                                        (<?= htmlspecialchars($segmentItem['method_name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="grade_id" class="form-label">Марка газа</label>
                            <select class="form-select" id="grade_id" name="grade_id" required>
                                <option value="">Выберите марку газа</option>
                                <?php foreach ($gasGrades as $grade): ?>
                                    <option value="<?= $grade['id'] ?>">
                                        <?= htmlspecialchars($grade['grade_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="receiver_user_id" class="form-label">Получатель</label>
                            <select class="form-select" id="receiver_user_id" name="receiver_user_id" required>
                                <option value="">Выберите получателя</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= $client['id'] ?>">
                                        <?= htmlspecialchars($client['username']) ?> (<?= htmlspecialchars($client['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">Объем (м³)</label>
                            <input type="number" class="form-control" id="amount" name="amount" 
                                   step="0.001" min="0.001" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="departure_time" class="form-label">Время отправления</label>
                            <input type="datetime-local" class="form-control" id="departure_time" 
                                   name="departure_time" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="arrival_time" class="form-label">Время прибытия</label>
                            <input type="datetime-local" class="form-control" id="arrival_time" 
                                   name="arrival_time" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-2"></i>Создать транспортировку
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list me-2"></i>
                        <?= hasRole('employee') && !hasRole('admin') ? 'Транспортировки в вашем регионе' : 'Все транспортировки' ?>
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($transports)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Транспортировки не найдены.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Маршрут</th>
                                        <th>Марка газа</th>
                                        <th>Объем (м³)</th>
                                        <th>Получатель</th>
                                        <th>Отправление</th>
                                        <th>Прибытие</th>
                                        <th>Статус</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transports as $transport): ?>
                                    <?php 
                                    $now = time();
                                    $departure = strtotime($transport['departure_time']);
                                    $arrival = strtotime($transport['arrival_time']);
                                    
                                    if ($now < $departure) {
                                        $status = '<span class="badge bg-secondary">Запланирована</span>';
                                    } elseif ($now >= $departure && $now < $arrival) {
                                        $status = '<span class="badge bg-warning">В пути</span>';
                                    } else {
                                        $status = '<span class="badge bg-success">Завершена</span>';
                                    }
                                    ?>
                                    <tr>
                                        <td><?= $transport['id'] ?></td>
                                        <td>
                                            <small>
                                                <?= htmlspecialchars($transport['from_node_name']) ?><br>
                                                <i class="fas fa-arrow-down"></i><br>
                                                <?= htmlspecialchars($transport['to_node_name']) ?>
                                            </small>
                                        </td>
                                        <td><?= htmlspecialchars($transport['grade_name']) ?></td>
                                        <td><?= number_format($transport['amount'], 3) ?></td>
                                        <td><?= htmlspecialchars($transport['receiver_username']) ?></td>
                                        <td><?= date('d.m.Y H:i', strtotime($transport['departure_time'])) ?></td>
                                        <td><?= date('d.m.Y H:i', strtotime($transport['arrival_time'])) ?></td>
                                        <td><?= $status ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($transports) >= 50): ?>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Показаны последние 50 записей. Используйте фильтры для уточнения поиска.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const departureInput = document.getElementById('departure_time');
    const arrivalInput = document.getElementById('arrival_time');
    
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    departureInput.min = now.toISOString().slice(0, 16);
    
    departureInput.addEventListener('change', function() {
        if (this.value) {
            const departureTime = new Date(this.value);
            departureTime.setHours(departureTime.getHours() + 1);
            arrivalInput.min = departureTime.toISOString().slice(0, 16);
            
            if (arrivalInput.value && new Date(arrivalInput.value) <= new Date(this.value)) {
                arrivalInput.value = '';
            }
        }
    });
});
</script>

<?php include __DIR__ . "/../src/views/footer.php"; ?> 