<?php
session_start();


require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../lib/utilities.php";
require_once __DIR__ . "/../src/models/User.php";
require_once __DIR__ . "/../src/models/Region.php";

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('dashboard.php');
}

$pageTitle = "Управление пользователями";
$user = new User();
$region = new Region();

$errors = [];
$success = false;
$editUser = null;

if (isset($_GET['edit'])) {
    $editUser = $user->getUserById($_GET['edit']);
    if (!$editUser) {
        setFlashMessage('error', 'Пользователь не найден.');
        redirect('users.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $regionId = filter_input(INPUT_POST, 'region_id', FILTER_SANITIZE_NUMBER_INT);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $roles = $_POST['roles'] ?? [];
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    
    if (empty($username)) {
        $errors[] = "Имя пользователя обязательно для заполнения.";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Введите корректный email.";
    }
    
    if ($action === 'create' && empty($password)) {
        $errors[] = "Пароль обязателен при создании пользователя.";
    }
    
    if (!empty($password) && strlen($password) < 6) {
        $errors[] = "Пароль должен содержать минимум 6 символов.";
    }
    
    if (empty($roles)) {
        $errors[] = "Выберите хотя бы одну роль для пользователя.";
    }
    
    if (empty($errors)) {
        if ($action === 'create') {
            $newUserId = $user->createUser($username, $email, $password, $regionId ?: null, $isActive);
            if ($newUserId) {
                if ($user->assignRoles($newUserId, $roles)) {
                    setFlashMessage('success', 'Пользователь успешно создан.');
                    redirect('users.php');
                } else {
                    $errors[] = "Пользователь создан, но произошла ошибка при назначении ролей.";
                }
            } else {
                $errors[] = "Ошибка при создании пользователя. Возможно, пользователь с таким именем или email уже существует.";
            }
        } elseif ($action === 'update' && $userId) {
            $updateResult = $user->updateUser($userId, $email, $regionId ?: null, $isActive);
            if (!empty($password)) {
                $updateResult = $updateResult && $user->updatePassword($userId, $password);
            }
            if ($updateResult && $user->assignRoles($userId, $roles)) {
                setFlashMessage('success', 'Пользователь успешно обновлен.');
                redirect('users.php');
            } else {
                $errors[] = "Ошибка при обновлении пользователя.";
            }
        }
    }
}

if (isset($_GET['toggle_active'])) {
    $toggleId = filter_input(INPUT_GET, 'toggle_active', FILTER_SANITIZE_NUMBER_INT);
    if ($toggleId && $toggleId != $_SESSION['user_id']) {
        $targetUser = $user->getUserById($toggleId);
        if ($targetUser) {
            $newStatus = $targetUser['is_active'] ? 0 : 1;
            if ($user->updateUser($toggleId, $targetUser['email'], $targetUser['region_id'], $newStatus)) {
                setFlashMessage('success', $newStatus ? 'Пользователь активирован.' : 'Пользователь деактивирован.');
            } else {
                setFlashMessage('error', 'Ошибка при изменении статуса пользователя.');
            }
        }
    } else {
        setFlashMessage('error', 'Нельзя изменить статус собственной учетной записи.');
    }
    redirect('users.php');
}

$users = $user->getAllUsersWithRoles();
$regions = $region->getAll();
$availableRoles = $user->getAllRoles();
$flashMessages = getFlashMessages();

include __DIR__ . "/../src/views/header.php";
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3"><i class="fas fa-users me-2"></i>Управление пользователями</h1>
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
                        <i class="fas fa-<?= $editUser ? 'edit' : 'plus' ?> me-2"></i>
                        <?= $editUser ? 'Редактировать пользователя' : 'Добавить пользователя' ?>
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'create' ?>">
                        <?php if ($editUser): ?>
                            <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Имя пользователя</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?= htmlspecialchars($editUser['username'] ?? '') ?>" 
                                   <?= $editUser ? 'readonly' : 'required' ?>>
                            <?php if ($editUser): ?>
                                <div class="form-text">Имя пользователя нельзя изменить</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($editUser['email'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Пароль</label>
                            <input type="password" class="form-control" id="password" name="password">
                            <div class="form-text">
                                <?= $editUser ? 'Оставьте пустым, если не хотите изменять пароль' : 'Минимум 6 символов' ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="region_id" class="form-label">Регион</label>
                            <select class="form-select" id="region_id" name="region_id">
                                <option value="">Без региона</option>
                                <?php foreach ($regions as $regionOption): ?>
                                    <option value="<?= $regionOption['id'] ?>" 
                                            <?= (isset($editUser) && $editUser['region_id'] == $regionOption['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($regionOption['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Роли</label>
                            <?php 
                            $userRoleIds = [];
                            if ($editUser) {
                                $userRoles = $user->getUserRoles($editUser['id']);
                                $userRoleIds = array_column($userRoles, 'role_id');
                            }
                            ?>
                            <?php foreach ($availableRoles as $role): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                           name="roles[]" value="<?= $role['id'] ?>" 
                                           id="role_<?= $role['id'] ?>"
                                           <?= in_array($role['id'], $userRoleIds) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="role_<?= $role['id'] ?>">
                                        <?= htmlspecialchars(ucfirst($role['role_name'])) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" 
                                       id="is_active" <?= (!isset($editUser) || $editUser['is_active']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">
                                    Активный пользователь
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <?php if ($editUser): ?>
                                <a href="users.php" class="btn btn-secondary">Отмена</a>
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
                        <i class="fas fa-list me-2"></i>Список пользователей
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($users)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Пользователи не найдены.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Пользователь</th>
                                        <th>Email</th>
                                        <th>Роли</th>
                                        <th>Регион</th>
                                        <th>Статус</th>
                                        <th>Последний вход</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $userItem): ?>
                                    <tr class="<?= $userItem['is_active'] ? '' : 'table-secondary' ?>">
                                        <td><?= $userItem['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($userItem['username']) ?></strong>
                                            <?php if ($userItem['id'] == $_SESSION['user_id']): ?>
                                                <span class="badge bg-primary ms-1">Вы</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($userItem['email']) ?></td>
                                        <td>
                                            <?php if ($userItem['roles']): ?>
                                                <?php foreach (explode(',', $userItem['roles']) as $role): ?>
                                                    <span class="badge bg-secondary me-1"><?= htmlspecialchars(ucfirst(trim($role))) ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Нет ролей</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($userItem['region_name'] ?: 'Не указан') ?></td>
                                        <td>
                                            <?php if ($userItem['is_active']): ?>
                                                <span class="badge bg-success">Активен</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Неактивен</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $userItem['last_login'] ? date('d.m.Y H:i', strtotime($userItem['last_login'])) : 'Никогда' ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="users.php?edit=<?= $userItem['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($userItem['id'] != $_SESSION['user_id']): ?>
                                                    <a href="users.php?toggle_active=<?= $userItem['id'] ?>" 
                                                       class="btn btn-sm btn-outline-<?= $userItem['is_active'] ? 'warning' : 'success' ?>"
                                                       onclick="return confirm('Вы уверены, что хотите <?= $userItem['is_active'] ? 'деактивировать' : 'активировать' ?> этого пользователя?')">
                                                        <i class="fas fa-<?= $userItem['is_active'] ? 'pause' : 'play' ?>"></i>
                                                    </a>
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