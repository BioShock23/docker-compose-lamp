<?php

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../lib/utilities.php";

class TransportMethod {
    private $db;
    
    public function __construct() {
        $this->db = getConnection();
    }
    
    public function getAll() {
        $stmt = $this->db->query("SELECT tm.id, tm.method_name, tm.created_at, tm.updated_at,
                                       u1.username as created_by_name, u2.username as updated_by_name,
                                       (SELECT COUNT(*) FROM segment WHERE method_id = tm.id) as usage_count
                                FROM transport_method tm
                                LEFT JOIN user u1 ON tm.created_by = u1.id
                                LEFT JOIN user u2 ON tm.updated_by = u2.id
                                ORDER BY tm.method_name");
        
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT tm.id, tm.method_name, tm.created_at, tm.updated_at,
                                         u1.username as created_by_name, u2.username as updated_by_name
                                     FROM transport_method tm
                                     LEFT JOIN user u1 ON tm.created_by = u1.id
                                     LEFT JOIN user u2 ON tm.updated_by = u2.id
                                     WHERE tm.id = ?");
        $stmt->execute([$id]);
        
        return $stmt->fetch();
    }
    
    public function create($methodName, $userId) {
        $now = getCurrentTimestamp();
        
        $stmt = $this->db->prepare("INSERT INTO transport_method (method_name, created_at, updated_at, created_by, updated_by)
                                    VALUES (?, ?, ?, ?, ?)");
        
        return $stmt->execute([$methodName, $now, $now, $userId, $userId]);
    }
    
    public function update($id, $methodName, $userId) {
        $now = getCurrentTimestamp();
        
        $stmt = $this->db->prepare("UPDATE transport_method 
                                    SET method_name = ?, 
                                        updated_at = ?,
                                        updated_by = ?
                                    WHERE id = ?");
        
        return $stmt->execute([$methodName, $now, $userId, $id]);
    }
    
    public function delete($id) {
        try {
            $this->db->beginTransaction();
            
            $checkStmt = $this->db->prepare("SELECT COUNT(*) FROM segment WHERE method_id = ?");
            $checkStmt->execute([$id]);
            
            if ($checkStmt->fetchColumn() > 0) {
                $this->db->rollback();
                return false;
            }
            
            $methodStmt = $this->db->prepare("DELETE FROM transport_method WHERE id = ?");
            $result = $methodStmt->execute([$id]);
            
            $this->db->commit();
            return $result;
        } catch (PDOException $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    public function getForDropdown() {
        $stmt = $this->db->query("SELECT id, method_name FROM transport_method ORDER BY method_name");
        return $stmt->fetchAll();
    }
} 