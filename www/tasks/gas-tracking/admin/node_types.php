<?php
session_start();


require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../lib/utilities.php";
require_once __DIR__ . "/../src/models/User.php";
require_once __DIR__ . "/../src/models/NodeType.php";

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('dashboard.php');
}

$pageTitle = "Управление типами узлов";
$nodeType = new NodeType();

$errors = [];
$success = false;
$editType = null;

if (isset($_GET['edit'])) {
    $editType = $nodeType->getById($_GET['edit']);
    if (!$editType) {
        setFlashMessage('error', 'Тип узла не найден.');
        redirect('node_types.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $typeName = sanitizeInput($_POST['type_name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $typeId = filter_input(INPUT_POST, 'type_id', FILTER_SANITIZE_NUMBER_INT);
    
    if (empty($typeName)) {
        $errors[] = "Название типа узла обязательно для заполнения.";
    }
    
    if (empty($errors)) {
        if ($action === 'create') {
            if ($nodeType->create($typeName, $description, $_SESSION['user_id'])) {
                setFlashMessage('success', 'Тип узла успешно создан.');
                redirect('node_types.php');
            } else {
                $errors[] = "Ошибка при создании типа узла. Возможно, тип с таким именем уже существует.";
            }
        } elseif ($action === 'update' && $typeId) {
            if ($nodeType->update($typeId, $typeName, $description, $_SESSION['user_id'])) {
                setFlashMessage('success', 'Тип узла успешно обновлен.');
                redirect('node_types.php');
            } else {
                $errors[] = "Ошибка при обновлении типа узла.";
            }
        }
    }
}

if (isset($_GET['delete'])) {
    $deleteId = filter_input(INPUT_GET, 'delete', FILTER_SANITIZE_NUMBER_INT);
    if ($deleteId && $nodeType->delete($deleteId)) {
        setFlashMessage('success', 'Тип узла успешно удален.');
    } else {
        setFlashMessage('error', 'Ошибка при удалении типа узла. Возможно, тип используется в узлах.');
    }
    redirect('node_types.php');
}

$nodeTypes = $nodeType->getAll();
$flashMessages = getFlashMessages();

include __DIR__ . "/../src/views/header.php";
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3"><i class="fas fa-sitemap me-2"></i>Управление типами узлов</h1>
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
                        <i class="fas fa-<?= $editType ? 'edit' : 'plus' ?> me-2"></i>
                        <?= $editType ? 'Редактировать тип узла' : 'Добавить тип узла' ?>
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="<?= $editType ? 'update' : 'create' ?>">
                        <?php if ($editType): ?>
                            <input type="hidden" name="type_id" value="<?= $editType['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="type_name" class="form-label">Название типа</label>
                            <input type="text" class="form-control" id="type_name" name="type_name" 
                                   value="<?= htmlspecialchars($editType['type_name'] ?? '') ?>" required>
                            <div class="form-text">Например: Скважина, Склад газа, Компрессорная станция</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Описание</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($editType['description'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <?php if ($editType): ?>
                                <a href="node_types.php" class="btn btn-secondary">Отмена</a>
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
                        <i class="fas fa-list me-2"></i>Список типов узлов
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($nodeTypes)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Типы узлов не найдены. Создайте первый тип узла, используя форму слева.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Название</th>
                                        <th>Описание</th>
                                        <th>Использований</th>
                                        <th>Создан</th>
                                        <th>Автор</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($nodeTypes as $type): ?>
                                    <tr>
                                        <td><?= $type['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($type['type_name']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($type['description'] ?: 'Не указано') ?></td>
                                        <td>
                                            <span class="badge bg-info"><?= $type['usage_count'] ?></span>
                                        </td>
                                        <td><?= date('d.m.Y H:i', strtotime($type['created_at'])) ?></td>
                                        <td><?= htmlspecialchars($type['created_by_name'] ?? 'N/A') ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="node_types.php?edit=<?= $type['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($type['usage_count'] == 0): ?>
                                                    <a href="node_types.php?delete=<?= $type['id'] ?>" 
                                                       class="btn btn-sm btn-outline-danger"
                                                       onclick="return confirm('Вы уверены, что хотите удалить этот тип узла?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-outline-secondary" disabled 
                                                            title="Нельзя удалить тип узла, используемый в узлах">
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