<?php

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../lib/utilities.php";

class NodeType {
    private $db;
    
    public function __construct() {
        $this->db = getConnection();
    }
    
    public function getAll() {
        $stmt = $this->db->query("SELECT nt.id, nt.type_name, nt.description, nt.created_at, nt.updated_at,
                                       u1.username as created_by_name, u2.username as updated_by_name,
                                       (SELECT COUNT(*) FROM node WHERE type_id = nt.id) as usage_count
                                FROM node_type nt
                                LEFT JOIN user u1 ON nt.created_by = u1.id
                                LEFT JOIN user u2 ON nt.updated_by = u2.id
                                ORDER BY nt.type_name");
        
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT nt.id, nt.type_name, nt.description, nt.created_at, nt.updated_at,
                                         u1.username as created_by_name, u2.username as updated_by_name
                                     FROM node_type nt
                                     LEFT JOIN user u1 ON nt.created_by = u1.id
                                     LEFT JOIN user u2 ON nt.updated_by = u2.id
                                     WHERE nt.id = ?");
        $stmt->execute([$id]);
        
        return $stmt->fetch();
    }
    
    public function create($typeName, $description, $userId) {
        $now = getCurrentTimestamp();
        
        $stmt = $this->db->prepare("INSERT INTO node_type (type_name, description, created_at, updated_at, created_by, updated_by)
                                    VALUES (?, ?, ?, ?, ?, ?)");
        
        return $stmt->execute([$typeName, $description, $now, $now, $userId, $userId]);
    }
    
    public function update($id, $typeName, $description, $userId) {
        $now = getCurrentTimestamp();
        
        $stmt = $this->db->prepare("UPDATE node_type 
                                    SET type_name = ?, 
                                        description = ?,
                                        updated_at = ?,
                                        updated_by = ?
                                    WHERE id = ?");
        
        return $stmt->execute([$typeName, $description, $now, $userId, $id]);
    }
    
    public function delete($id) {
        try {
            $this->db->beginTransaction();
            
            $checkStmt = $this->db->prepare("SELECT COUNT(*) FROM node WHERE type_id = ?");
            $checkStmt->execute([$id]);
            
            if ($checkStmt->fetchColumn() > 0) {
                $this->db->rollback();
                return false;
            }
            
            $typeStmt = $this->db->prepare("DELETE FROM node_type WHERE id = ?");
            $result = $typeStmt->execute([$id]);
            
            $this->db->commit();
            return $result;
        } catch (PDOException $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    public function getForDropdown() {
        $stmt = $this->db->query("SELECT id, type_name FROM node_type ORDER BY type_name");
        return $stmt->fetchAll();
    }
} 