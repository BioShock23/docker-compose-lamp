<?php
session_start();


require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../lib/utilities.php";
require_once __DIR__ . "/../src/models/User.php";
require_once __DIR__ . "/../src/models/Region.php";

if (!isset($_SESSION['user_id'])) {
    redirect('../login.php');
}

$baseUrl = getBaseUrl();
$pageTitle = "Профиль пользователя";
$user = new User();
$region = new Region();

$currentUser = $user->getUserById($_SESSION['user_id']);
$regions = $region->getAll();
$userRoles = $_SESSION['roles'] ?? [];

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $regionId = filter_input(INPUT_POST, 'region_id', FILTER_SANITIZE_NUMBER_INT);
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($email)) {
        $errors[] = "Email обязателен для заполнения.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Неверный формат email.";
    }
    
    if ($newPassword && ($newPassword !== $confirmPassword)) {
        $errors[] = "Пароли не совпадают.";
    }
    
    if ($newPassword && strlen($newPassword) < 6) {
        $errors[] = "Пароль должен содержать минимум 6 символов.";
    }
    
    if ($newPassword && $currentPassword !== $currentUser['password']) {
        $errors[] = "Неверный текущий пароль.";
    }
    
    if (empty($errors)) {
        $updateResult = $user->updateUser($_SESSION['user_id'], $email, $regionId ?: null);
        
        if ($updateResult && $newPassword) {
            $updateResult = $user->updatePassword($_SESSION['user_id'], $newPassword);
        }
        
        if ($updateResult) {
            $_SESSION['email'] = $email;
            $_SESSION['region_id'] = $regionId ?: null;
            
            if ($regionId) {
                $regionData = $region->getById($regionId);
                $_SESSION['region_name'] = $regionData['name'] ?? null;
            } else {
                $_SESSION['region_name'] = null;
            }
            
            $success = true;
            $currentUser = $user->getUserById($_SESSION['user_id']);
        } else {
            $errors[] = "Ошибка при обновлении профиля.";
        }
    }
}

include __DIR__ . "/../src/views/header.php";
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Профиль пользователя</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            Профиль успешно обновлен!
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Имя пользователя</label>
                                    <input type="text" class="form-control" id="username" value="<?= htmlspecialchars($currentUser['username']) ?>" readonly>
                                    <div class="form-text">Имя пользователя нельзя изменить</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($currentUser['email']) ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="region_id" class="form-label">Регион</label>
                                    <select class="form-select" id="region_id" name="region_id">
                                        <option value="">Выберите регион</option>
                                        <?php foreach ($regions as $regionOption): ?>
                                            <option value="<?= $regionOption['id'] ?>" <?= $regionOption['id'] == $currentUser['region_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($regionOption['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="roles" class="form-label">Роли</label>
                                    <input type="text" class="form-control" id="roles" value="<?= implode(', ', array_map('ucfirst', $userRoles)) ?>" readonly>
                                    <div class="form-text">Роли управляются администратором</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_login" class="form-label">Последний вход</label>
                                    <input type="text" class="form-control" id="last_login" value="<?= $currentUser['last_login'] ? date('d.m.Y H:i', strtotime($currentUser['last_login'])) : 'Впервые' ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="created_at" class="form-label">Дата регистрации</label>
                                    <input type="text" class="form-control" id="created_at" value="<?= date('d.m.Y H:i', strtotime($currentUser['created_at'])) ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        <h6 class="mb-3"><i class="fas fa-lock me-2"></i>Изменение пароля</h6>
                        <div class="form-text mb-3">Оставьте поля пустыми, если не хотите менять пароль</div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Текущий пароль</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Новый пароль</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Подтвердите пароль</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="<?= $baseUrl ?>/pages/dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Назад к панели
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Сохранить изменения
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../src/views/footer.php"; ?> 