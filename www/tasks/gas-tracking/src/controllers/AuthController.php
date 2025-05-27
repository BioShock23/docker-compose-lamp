<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../lib/utilities.php";
require_once __DIR__ . "/../models/User.php";

class AuthController {
    private $userModel;
    
    public function __construct() {
        $this->userModel = new User();
    }
    
    public function login() {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8') : '';
            $password = $_POST['password'] ?? '';
            $rememberMe = isset($_POST['remember_me']);
            
            if (empty($username) || empty($password)) {
                setFlashMessage('error', 'Имя пользователя и пароль обязательны');
                return false;
            }
            
            if ($this->userModel->login($username, $password, $rememberMe)) {
                if (!headers_sent()) {
                    redirect('/pages/dashboard.php');
                }
                exit;
            } else {
                setFlashMessage('error', 'Неверное имя пользователя или пароль');
                return false;
            }
        }
        
        return false;
    }
    
    public function logout() {
        $this->userModel->logout();
        redirect('/pages/statistics.php');
    }
} 