<?php
session_start();


require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../lib/utilities.php";
require_once __DIR__ . "/../src/models/User.php";
require_once __DIR__ . "/../src/models/TransportMethod.php";

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('dashboard.php');
}

$pageTitle = "Управление методами транспортировки";
$transportMethod = new TransportMethod();

$errors = [];
$success = false;
$editMethod = null;

if (isset($_GET['edit'])) {
    $editMethod = $transportMethod->getById($_GET['edit']);
    if (!$editMethod) {
        setFlashMessage('error', 'Метод транспортировки не найден.');
        redirect('transport_methods.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $methodName = sanitizeInput($_POST['method_name'] ?? '');
    $methodId = filter_input(INPUT_POST, 'method_id', FILTER_SANITIZE_NUMBER_INT);
    
    if (empty($methodName)) {
        $errors[] = "Название метода транспортировки обязательно для заполнения.";
    }
    
    if (empty($errors)) {
        if ($action === 'create') {
            if ($transportMethod->create($methodName, $_SESSION['user_id'])) {
                setFlashMessage('success', 'Метод транспортировки успешно создан.');
                redirect('transport_methods.php');
            } else {
                $errors[] = "Ошибка при создании метода транспортировки. Возможно, метод с таким именем уже существует.";
            }
        } elseif ($action === 'update' && $methodId) {
            if ($transportMethod->update($methodId, $methodName, $_SESSION['user_id'])) {
                setFlashMessage('success', 'Метод транспортировки успешно обновлен.');
                redirect('transport_methods.php');
            } else {
                $errors[] = "Ошибка при обновлении метода транспортировки.";
            }
        }
    }
}

if (isset($_GET['delete'])) {
    $deleteId = filter_input(INPUT_GET, 'delete', FILTER_SANITIZE_NUMBER_INT);
    if ($deleteId && $transportMethod->delete($deleteId)) {
        setFlashMessage('success', 'Метод транспортировки успешно удален.');
    } else {
        setFlashMessage('error', 'Ошибка при удалении метода транспортировки. Возможно, метод используется в сегментах.');
    }
    redirect('transport_methods.php');
}

$transportMethods = $transportMethod->getAll();
$flashMessages = getFlashMessages();

include __DIR__ . "/../src/views/header.php";
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3"><i class="fas fa-truck me-2"></i>Управление методами транспортировки</h1>
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
                        <i class="fas fa-<?= $editMethod ? 'edit' : 'plus' ?> me-2"></i>
                        <?= $editMethod ? 'Редактировать метод транспортировки' : 'Добавить метод транспортировки' ?>
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="<?= $editMethod ? 'update' : 'create' ?>">
                        <?php if ($editMethod): ?>
                            <input type="hidden" name="method_id" value="<?= $editMethod['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="method_name" class="form-label">Название метода</label>
                            <input type="text" class="form-control" id="method_name" name="method_name" 
                                   value="<?= htmlspecialchars($editMethod['method_name'] ?? '') ?>" required>
                            <div class="form-text">Например: Трубопровод, Автотранспорт, Железная дорога</div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <?php if ($editMethod): ?>
                                <a href="transport_methods.php" class="btn btn-secondary">Отмена</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Обновить
                                </button>
                            <?php else: ?>
                                <div></div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Создать
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list me-2"></i>Список методов транспортировки
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($transportMethods)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Методы транспортировки не найдены. Создайте первый метод, используя форму слева.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Название</th>
                                        <th>Использований в сегментах</th>
                                        <th>Создан</th>
                                        <th>Автор</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transportMethods as $method): ?>
                                    <tr>
                                        <td><?= $method['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($method['method_name']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= $method['usage_count'] ?></span>
                                        </td>
                                        <td><?= date('d.m.Y H:i', strtotime($method['created_at'])) ?></td>
                                        <td><?= htmlspecialchars($method['created_by_name'] ?? 'N/A') ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="transport_methods.php?edit=<?= $method['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($method['usage_count'] == 0): ?>
                                                    <a href="transport_methods.php?delete=<?= $method['id'] ?>" 
                                                       class="btn btn-sm btn-outline-danger"
                                                       onclick="return confirm('Вы уверены, что хотите удалить этот метод транспортировки?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-outline-secondary" disabled 
                                                            title="Нельзя удалить метод, используемый в сегментах">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../src/views/footer.php"; ?> 