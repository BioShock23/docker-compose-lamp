<?php

require_once __DIR__ . "/../../config/database.php";

class User {
    private $db;
    
    public function __construct() {
        $this->db = getConnection();
    }
    
    public function login($username, $password, $rememberMe = false) {
        $stmt = $this->db->prepare("SELECT u.id, u.password, u.username, u.email, u.region_id, r.name as region_name 
                                    FROM user u 
                                    LEFT JOIN region r ON u.region_id = r.id 
                                    WHERE u.username = ? AND u.is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && $password === $user['password']) {
            $this->startUserSession($user);
            
            if ($rememberMe) {
                $this->setRememberMeCookie($user['id']);
            }
            
            $this->updateLastLogin($user['id']);
            return true;
        }
        
        return false;
    }
    
    public function createUser($username, $email, $password, $regionId = null, $isActive = 1) {
        $now = date('Y-m-d H:i:s');
        
        try {
            $stmt = $this->db->prepare("INSERT INTO user (username, password, email, region_id, is_active, created_at, updated_at) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $password, $email, $regionId, $isActive, $now, $now]);
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function getUserRoles($userId) {
        $stmt = $this->db->prepare("SELECT r.role_name, r.id as role_id
                                    FROM user_role ur 
                                    JOIN role r ON ur.role_id = r.id 
                                    WHERE ur.user_id = ?");
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll();
    }
    
    public function getUserRoleNames($userId) {
        $stmt = $this->db->prepare("SELECT r.role_name 
                                    FROM user_role ur 
                                    JOIN role r ON ur.role_id = r.id 
                                    WHERE ur.user_id = ?");
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public function getAllUsersWithRoles() {
        $stmt = $this->db->prepare("SELECT u.id, u.username, u.email, u.is_active, u.last_login, u.created_at,
                                          r.name as region_name, u.region_id,
                                          GROUP_CONCAT(ro.role_name) as roles
                                    FROM user u
                                    LEFT JOIN region r ON u.region_id = r.id
                                    LEFT JOIN user_role ur ON u.id = ur.user_id
                                    LEFT JOIN role ro ON ur.role_id = ro.id
                                    GROUP BY u.id, u.username, u.email, u.is_active, u.last_login, u.created_at, r.name, u.region_id
                                    ORDER BY u.username");
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function getAllRoles() {
        $stmt = $this->db->query("SELECT id, role_name FROM role ORDER BY role_name");
        return $stmt->fetchAll();
    }
    
    public function assignRoles($userId, $roleIds) {
        try {
            $this->db->beginTransaction();
            
            $deleteStmt = $this->db->prepare("DELETE FROM user_role WHERE user_id = ?");
            $deleteStmt->execute([$userId]);
            
            $insertStmt = $this->db->prepare("INSERT INTO user_role (user_id, role_id, assigned_at, assigned_by) VALUES (?, ?, ?, ?)");
            $now = date('Y-m-d H:i:s');
            
            foreach ($roleIds as $roleId) {
                $insertStmt->execute([$userId, $roleId, $now, $_SESSION['user_id'] ?? 1]);
            }
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    public function getUserById($userId) {
        $stmt = $this->db->prepare("SELECT u.id, u.username, u.email, u.region_id, u.is_active, u.last_login, u.created_at, u.password,
                                          r.name as region_name 
                                    FROM user u 
                                    LEFT JOIN region r ON u.region_id = r.id 
                                    WHERE u.id = ?");
        $stmt->execute([$userId]);
        
        return $stmt->fetch();
    }
    
    public function updateUser($userId, $email, $regionId, $isActive = true) {
        $stmt = $this->db->prepare("UPDATE user 
                                    SET email = ?, region_id = ?, is_active = ?, updated_at = ? 
                                    WHERE id = ?");
        
        return $stmt->execute([$email, $regionId, $isActive, date('Y-m-d H:i:s'), $userId]);
    }
    
    public function updatePassword($userId, $newPassword) {
        $stmt = $this->db->prepare("UPDATE user 
                                    SET password = ?, updated_at = ? 
                                    WHERE id = ?");
        
        return $stmt->execute([$newPassword, date('Y-m-d H:i:s'), $userId]);
    }
    
    public function assignRole($userId, $roleName, $assignedBy) {
        $now = date('Y-m-d H:i:s');
        
        $roleStmt = $this->db->prepare("SELECT id FROM role WHERE role_name = ?");
        $roleStmt->execute([$roleName]);
        $roleId = $roleStmt->fetchColumn();
        
        if (!$roleId) {
            return false;
        }
        
        $checkStmt = $this->db->prepare("SELECT id FROM user_role WHERE user_id = ? AND role_id = ?");
        $checkStmt->execute([$userId, $roleId]);
        
        if ($checkStmt->fetchColumn()) {
            return true;
        }
        
        $stmt = $this->db->prepare("INSERT INTO user_role (user_id, role_id, assigned_at, assigned_by) 
                                    VALUES (?, ?, ?, ?)");
        
        return $stmt->execute([$userId, $roleId, $now, $assignedBy]);
    }
    
    public function removeRole($userId, $roleName) {
        $roleStmt = $this->db->prepare("SELECT id FROM role WHERE role_name = ?");
        $roleStmt->execute([$roleName]);
        $roleId = $roleStmt->fetchColumn();
        
        if (!$roleId) {
            return false;
        }
        
        $stmt = $this->db->prepare("DELETE FROM user_role WHERE user_id = ? AND role_id = ?");
        return $stmt->execute([$userId, $roleId]);
    }
    
    public function logout() {
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        $_SESSION = [];
        session_destroy();
    }
    
    private function startUserSession($user) {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['region_id'] = $user['region_id'];
        $_SESSION['region_name'] = $user['region_name'];
        
        $roles = $this->getUserRoleNames($user['id']);
        $_SESSION['roles'] = $roles;
    }
    
    private function setRememberMeCookie($userId) {
        $token = bin2hex(random_bytes(32));
        $expiry = time() + (30 * 24 * 60 * 60);
        
        $stmt = $this->db->prepare("UPDATE user SET remember_token = ? WHERE id = ?");
        $stmt->execute([$token, $userId]);
        
        setcookie('remember_token', $userId . ':' . $token, $expiry, '/', '', false, true);
    }
    
    private function updateLastLogin($userId) {
        $stmt = $this->db->prepare("UPDATE user SET last_login = ? WHERE id = ?");
        $stmt->execute([date('Y-m-d H:i:s'), $userId]);
    }
    
    public function checkRememberMeCookie() {
        if (isset($_COOKIE['remember_token'])) {
            list($userId, $token) = explode(':', $_COOKIE['remember_token']);
            
            $stmt = $this->db->prepare("SELECT * FROM user WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user && $token === $user['remember_token']) {
                $this->startUserSession($user);
                $this->updateLastLogin($user['id']);
                
                return true;
            }
        }
        
        return false;
    }
} 