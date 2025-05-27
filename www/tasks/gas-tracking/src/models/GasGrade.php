<?php

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../lib/utilities.php";

class GasGrade {
    private $db;
    
    public function __construct() {
        $this->db = getConnection();
    }
    
    public function getAll() {
        $stmt = $this->db->query("SELECT gg.id, gg.grade_name, gg.description, gg.created_at, gg.updated_at,
                                       u1.username as created_by_name, u2.username as updated_by_name,
                                       (SELECT COUNT(*) FROM transport_record WHERE grade_id = gg.id) as usage_count
                                FROM gas_grade gg
                                LEFT JOIN user u1 ON gg.created_by = u1.id
                                LEFT JOIN user u2 ON gg.updated_by = u2.id
                                ORDER BY gg.grade_name");
        
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT gg.id, gg.grade_name, gg.description, gg.created_at, gg.updated_at,
                                         u1.username as created_by_name, u2.username as updated_by_name
                                     FROM gas_grade gg
                                     LEFT JOIN user u1 ON gg.created_by = u1.id
                                     LEFT JOIN user u2 ON gg.updated_by = u2.id
                                     WHERE gg.id = ?");
        $stmt->execute([$id]);
        
        return $stmt->fetch();
    }
    
    public function create($gradeName, $description, $userId) {
        $now = getCurrentTimestamp();
        
        $stmt = $this->db->prepare("INSERT INTO gas_grade (grade_name, description, created_at, updated_at, created_by, updated_by)
                                    VALUES (?, ?, ?, ?, ?, ?)");
        
        return $stmt->execute([$gradeName, $description, $now, $now, $userId, $userId]);
    }
    
    public function update($id, $gradeName, $description, $userId) {
        $now = getCurrentTimestamp();
        
        $stmt = $this->db->prepare("UPDATE gas_grade 
                                    SET grade_name = ?, 
                                        description = ?,
                                        updated_at = ?,
                                        updated_by = ?
                                    WHERE id = ?");
        
        return $stmt->execute([$gradeName, $description, $now, $userId, $id]);
    }
    
    public function delete($id) {
        try {
            $this->db->beginTransaction();
            
            $checkStmt = $this->db->prepare("SELECT COUNT(*) FROM transport_record WHERE grade_id = ?");
            $checkStmt->execute([$id]);
            
            if ($checkStmt->fetchColumn() > 0) {
                $this->db->rollback();
                return false;
            }
            
            $gradeStmt = $this->db->prepare("DELETE FROM gas_grade WHERE id = ?");
            $result = $gradeStmt->execute([$id]);
            
            $this->db->commit();
            return $result;
        } catch (PDOException $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    public function getForDropdown() {
        $stmt = $this->db->query("SELECT id, grade_name FROM gas_grade ORDER BY grade_name");
        return $stmt->fetchAll();
    }
} 