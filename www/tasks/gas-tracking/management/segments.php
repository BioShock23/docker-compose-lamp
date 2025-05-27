<?php
session_start();


require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../lib/utilities.php";
require_once __DIR__ . "/../src/models/Segment.php";

if (!isset($_SESSION['user_id'])) {
    redirect('../login.php');
}

if (hasRole('client')) {
    redirect('pages/dashboard.php');
}

$pageTitle = "Управление Сегментами";
$segmentModel = new Segment();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $fromNodeId = (int)($_POST['from_node_id'] ?? 0);
        $toNodeId = (int)($_POST['to_node_id'] ?? 0);
        $methodId = (int)($_POST['method_id'] ?? 1);
        $capacity = (float)($_POST['capacity'] ?? 0);
        
        if ($segmentModel->create($fromNodeId, $toNodeId, $capacity, 0, $methodId)) {
            setFlashMessage('success', 'Сегмент успешно создан');
        } else {
            setFlashMessage('error', 'Ошибка при создании сегмента');
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $fromNodeId = (int)($_POST['from_node_id'] ?? 0);
        $toNodeId = (int)($_POST['to_node_id'] ?? 0);
        $methodId = (int)($_POST['method_id'] ?? 1);
        $capacity = (float)($_POST['capacity'] ?? 0);
        
        if ($segmentModel->update($id, $fromNodeId, $toNodeId, $capacity, 0, $methodId)) {
            setFlashMessage('success', 'Сегмент успешно обновлен');
        } else {
            setFlashMessage('error', 'Ошибка при обновлении сегмента');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        if ($segmentModel->delete($id)) {
            setFlashMessage('success', 'Сегмент успешно удален');
        } else {
            setFlashMessage('error', 'Ошибка при удалении сегмента');
        }
    }
    
    redirect('segments.php');
}

$segments = $segmentModel->getAll();
$nodes = $segmentModel->getNodes();
$transportMethods = $segmentModel->getAllTransportMethods();

$flashMessages = getFlashMessages();
include __DIR__ . "/../src/views/header.php";
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Управление Сегментами</h1>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/pages/dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Назад к панели
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#segmentModal">
            <i class="fas fa-plus me-2"></i>Добавить Сегмент
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Начальный узел</th>
                        <th>Конечный узел</th>
                        <th>Метод транспортировки</th>
                        <th>Пропускная способность (м³/ч)</th>
                        <th>Длина (км)</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($segments as $segment): ?>
                        <tr>
                            <td><?= $segment['id'] ?></td>
                            <td><?= htmlspecialchars($segment['from_node_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($segment['to_node_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($segment['method_name'] ?? 'N/A') ?></td>
                            <td><?= number_format($segment['capacity'], 2) ?></td>
                            <td><?= number_format($segment['length'], 2) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary edit-btn" 
                                        data-id="<?= $segment['id'] ?>"
                                        data-from-node-id="<?= $segment['from_node_id'] ?>"
                                        data-to-node-id="<?= $segment['to_node_id'] ?>"
                                        data-method-id="<?= $segment['method_id'] ?>"
                                        data-capacity="<?= $segment['capacity'] ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $segment['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" 
                                            onclick="return confirm('Вы уверены?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="segmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="segmentForm">
                <div class="modal-header">
                    <h5 class="modal-title">Сегмент</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="segmentId">
                    
                    <div class="mb-3">
                        <label for="from_node_id" class="form-label">Начальный узел</label>
                        <select class="form-select" id="from_node_id" name="from_node_id" required>
                            <option value="">Выберите узел</option>
                            <?php foreach ($nodes as $node): ?>
                                <option value="<?= $node['id'] ?>"><?= htmlspecialchars($node['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="to_node_id" class="form-label">Конечный узел</label>
                        <select class="form-select" id="to_node_id" name="to_node_id" required>
                            <option value="">Выберите узел</option>
                            <?php foreach ($nodes as $node): ?>
                                <option value="<?= $node['id'] ?>"><?= htmlspecialchars($node['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="method_id" class="form-label">Метод транспортировки</label>
                        <select class="form-select" id="method_id" name="method_id" required>
                            <option value="">Выберите метод</option>
                            <?php foreach ($transportMethods as $method): ?>
                                <option value="<?= $method['id'] ?>"><?= htmlspecialchars($method['method_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="capacity" class="form-label">Пропускная способность (м³/ч)</label>
                        <input type="number" step="0.01" class="form-control" id="capacity" name="capacity" required>
                    </div>
                    
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        Длина сегмента рассчитывается автоматически на основе географического расстояния между выбранными узлами.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const editButtons = document.querySelectorAll('.edit-btn');
    const segmentModal = document.getElementById('segmentModal');
    const segmentForm = document.getElementById('segmentForm');
    
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('formAction').value = 'update';
            document.getElementById('segmentId').value = this.dataset.id;
            document.getElementById('from_node_id').value = this.dataset.fromNodeId;
            document.getElementById('to_node_id').value = this.dataset.toNodeId;
            document.getElementById('method_id').value = this.dataset.methodId;
            document.getElementById('capacity').value = this.dataset.capacity;
            
            new bootstrap.Modal(segmentModal).show();
        });
    });
    
    segmentModal.addEventListener('hidden.bs.modal', function() {
        segmentForm.reset();
        document.getElementById('formAction').value = 'create';
        document.getElementById('segmentId').value = '';
        document.getElementById('from_node_id').value = '';
        document.getElementById('to_node_id').value = '';
        document.getElementById('method_id').value = '';
        document.getElementById('capacity').value = '';
    });
});
</script>

<?php include __DIR__ . "/../src/views/footer.php"; ?> 