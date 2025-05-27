<?php

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../lib/utilities.php";

class Region {
    private $db;
    
    public function __construct() {
        $this->db = getConnection();
    }
    
    public function getAll() {
        $stmt = $this->db->query("SELECT r.id, r.name, r.created_at, r.updated_at, 
                                       u1.username as created_by_name, u2.username as updated_by_name,
                                       (SELECT COUNT(*) FROM node WHERE region_id = r.id) as node_count
                                FROM region r
                                LEFT JOIN user u1 ON r.created_by = u1.id
                                LEFT JOIN user u2 ON r.updated_by = u2.id
                                ORDER BY r.name");
        
        return $stmt->fetchAll();
    }
    
    public function getAllRegions() {
        $stmt = $this->db->query("SELECT id, name FROM region ORDER BY name");
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT r.id, r.name, r.created_at, r.updated_at, 
                                         u1.username as created_by_name, u2.username as updated_by_name
                                     FROM region r
                                     LEFT JOIN user u1 ON r.created_by = u1.id
                                     LEFT JOIN user u2 ON r.updated_by = u2.id
                                     WHERE r.id = ?");
        $stmt->execute([$id]);
        
        return $stmt->fetch();
    }
    
    public function create($name, $userId) {
        $now = getCurrentTimestamp();
        
        $stmt = $this->db->prepare("INSERT INTO region (name, created_at, updated_at, created_by, updated_by)
                                    VALUES (?, ?, ?, ?, ?)");
        
        return $stmt->execute([$name, $now, $now, $userId, $userId]);
    }
    
    public function update($id, $name, $userId) {
        $now = getCurrentTimestamp();
        
        $stmt = $this->db->prepare("UPDATE region 
                                    SET name = ?, 
                                        updated_at = ?,
                                        updated_by = ?
                                    WHERE id = ?");
        
        return $stmt->execute([$name, $now, $userId, $id]);
    }
    
    public function delete($id) {
        try {
            $this->db->beginTransaction();
            
            $checkStmt = $this->db->prepare("SELECT COUNT(*) FROM node WHERE region_id = ?");
            $checkStmt->execute([$id]);
            
            if ($checkStmt->fetchColumn() > 0) {
                $this->db->rollback();
                return false;
            }
            
            $userCheckStmt = $this->db->prepare("SELECT COUNT(*) FROM user WHERE region_id = ?");
            $userCheckStmt->execute([$id]);
            
            if ($userCheckStmt->fetchColumn() > 0) {
                $this->db->rollback();
                return false;
            }
            
            $regionStmt = $this->db->prepare("DELETE FROM region WHERE id = ?");
            $result = $regionStmt->execute([$id]);
            
            $this->db->commit();
            return $result;
        } catch (PDOException $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    public function getForDropdown() {
        $stmt = $this->db->query("SELECT id, name FROM region ORDER BY name");
        return $stmt->fetchAll();
    }
} 