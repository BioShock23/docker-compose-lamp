<?php
session_start();


require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../lib/utilities.php";
require_once __DIR__ . "/../src/models/User.php";
require_once __DIR__ . "/../src/models/Region.php";

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('dashboard.php');
}

$pageTitle = "Управление регионами";
$region = new Region();

$errors = [];
$success = false;
$editRegion = null;

if (isset($_GET['edit'])) {
    $editRegion = $region->getById($_GET['edit']);
    if (!$editRegion) {
        setFlashMessage('error', 'Регион не найден.');
        redirect('regions.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $regionName = sanitizeInput($_POST['region_name'] ?? '');
    $regionId = filter_input(INPUT_POST, 'region_id', FILTER_SANITIZE_NUMBER_INT);
    
    if (empty($regionName)) {
        $errors[] = "Название региона обязательно для заполнения.";
    }
    
    if (empty($errors)) {
        if ($action === 'create') {
            if ($region->create($regionName, $_SESSION['user_id'])) {
                setFlashMessage('success', 'Регион успешно создан.');
                redirect('regions.php');
            } else {
                $errors[] = "Ошибка при создании региона. Возможно, регион с таким именем уже существует.";
            }
        } elseif ($action === 'update' && $regionId) {
            if ($region->update($regionId, $regionName, $_SESSION['user_id'])) {
                setFlashMessage('success', 'Регион успешно обновлен.');
                redirect('regions.php');
            } else {
                $errors[] = "Ошибка при обновлении региона.";
            }
        }
    }
}

if (isset($_GET['delete'])) {
    $deleteId = filter_input(INPUT_GET, 'delete', FILTER_SANITIZE_NUMBER_INT);
    if ($deleteId && $region->delete($deleteId)) {
        setFlashMessage('success', 'Регион успешно удален.');
    } else {
        setFlashMessage('error', 'Ошибка при удалении региона. Возможно, регион используется в узлах или пользователями.');
    }
    redirect('regions.php');
}

$regions = $region->getAll();
$flashMessages = getFlashMessages();

include __DIR__ . "/../src/views/header.php";
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3"><i class="fas fa-map-marker-alt me-2"></i>Управление регионами</h1>
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
                        <i class="fas fa-<?= $editRegion ? 'edit' : 'plus' ?> me-2"></i>
                        <?= $editRegion ? 'Редактировать регион' : 'Добавить новый регион' ?>
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="<?= $editRegion ? 'update' : 'create' ?>">
                        <?php if ($editRegion): ?>
                            <input type="hidden" name="region_id" value="<?= $editRegion['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="region_name" class="form-label">Название региона</label>
                            <input type="text" class="form-control" id="region_name" name="region_name" 
                                   value="<?= htmlspecialchars($editRegion['name'] ?? '') ?>" required>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <?php if ($editRegion): ?>
                                <a href="regions.php" class="btn btn-secondary">Отмена</a>
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
                        <i class="fas fa-list me-2"></i>Список регионов
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($regions)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Регионы не найдены. Создайте первый регион, используя форму слева.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Название</th>
                                        <th>Узлов</th>
                                        <th>Создан</th>
                                        <th>Автор</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($regions as $regionItem): ?>
                                    <tr>
                                        <td><?= $regionItem['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($regionItem['name']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= $regionItem['node_count'] ?></span>
                                        </td>
                                        <td><?= date('d.m.Y H:i', strtotime($regionItem['created_at'])) ?></td>
                                        <td><?= htmlspecialchars($regionItem['created_by_name'] ?? 'N/A') ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="regions.php?edit=<?= $regionItem['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($regionItem['node_count'] == 0): ?>
                                                    <a href="regions.php?delete=<?= $regionItem['id'] ?>" 
                                                       class="btn btn-sm btn-outline-danger"
                                                       onclick="return confirm('Вы уверены, что хотите удалить этот регион?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-outline-secondary" disabled 
                                                            title="Нельзя удалить регион с узлами">
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