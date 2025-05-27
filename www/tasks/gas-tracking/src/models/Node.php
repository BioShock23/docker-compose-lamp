<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../lib/utilities.php";

class Node {
    private $db;
    
    public function __construct() {
        $this->db = getConnection();
    }
    
    public function getAll($regionId = null) {
        $sql = "SELECT n.id, n.name, n.type_id as node_type_id, nt.type_name, 
                       ST_X(n.location) as lng, ST_Y(n.location) as lat,
                       n.region_id, r.name as region_name,
                       n.created_at, n.updated_at, 
                       u1.username as created_by_name, u2.username as updated_by_name
                FROM node n
                JOIN node_type nt ON n.type_id = nt.id
                JOIN region r ON n.region_id = r.id
                LEFT JOIN user u1 ON n.created_by = u1.id
                LEFT JOIN user u2 ON n.updated_by = u2.id";
        
        $params = [];
        
        if ($regionId) {
            $sql .= " WHERE n.region_id = ?";
            $params[] = $regionId;
        }
        
        $sql .= " ORDER BY n.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function getAllNodes() {
        $sql = "SELECT n.id, n.name, n.type_id, nt.type_name, 
                       ST_AsGeoJSON(n.location) as location_json,
                       n.region_id, r.name as region_name
                FROM node n
                JOIN node_type nt ON n.type_id = nt.id
                JOIN region r ON n.region_id = r.id
                ORDER BY n.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function getNodesByRegion($regionId) {
        $sql = "SELECT n.id, n.name, n.type_id, nt.type_name, 
                       ST_AsGeoJSON(n.location) as location_json,
                       n.region_id, r.name as region_name
                FROM node n
                JOIN node_type nt ON n.type_id = nt.id
                JOIN region r ON n.region_id = r.id
                WHERE n.region_id = ?
                ORDER BY n.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$regionId]);
        
        return $stmt->fetchAll();
    }
    
    public function getNodesByIds($nodeIds) {
        if (empty($nodeIds)) {
            return [];
        }
        
        $placeholders = str_repeat('?,', count($nodeIds) - 1) . '?';
        
        $sql = "SELECT n.id, n.name, n.type_id, nt.type_name, 
                       ST_AsGeoJSON(n.location) as location_json,
                       n.region_id, r.name as region_name
                FROM node n
                JOIN node_type nt ON n.type_id = nt.id
                JOIN region r ON n.region_id = r.id
                WHERE n.id IN ($placeholders)
                ORDER BY n.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($nodeIds);
        
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT n.id, n.name, n.type_id, nt.type_name, 
                                          ST_AsGeoJSON(n.location) as location_json,
                                          n.region_id, r.name as region_name,
                                          n.created_at, n.updated_at, 
                                          u1.username as created_by_name, u2.username as updated_by_name
                                   FROM node n
                                   JOIN node_type nt ON n.type_id = nt.id
                                   JOIN region r ON n.region_id = r.id
                                   LEFT JOIN user u1 ON n.created_by = u1.id
                                   LEFT JOIN user u2 ON n.updated_by = u2.id
                                   WHERE n.id = ?");
        $stmt->execute([$id]);
        
        return $stmt->fetch();
    }
    
    public function create($name, $regionId, $nodeTypeId, $lat, $lng) {
        $now = getCurrentTimestamp();
        $point = createMysqlPoint($lat, $lng);
        $userId = $_SESSION['user_id'] ?? 1;
        
        $stmt = $this->db->prepare("INSERT INTO node (name, type_id, location, region_id, created_at, updated_at, created_by, updated_by)
                                    VALUES (?, ?, ST_GeomFromText(?), ?, ?, ?, ?, ?)");
        
        return $stmt->execute([$name, $nodeTypeId, $point, $regionId, $now, $now, $userId, $userId]);
    }
    
    public function update($id, $name, $regionId, $nodeTypeId, $lat, $lng) {
        $now = getCurrentTimestamp();
        $point = createMysqlPoint($lat, $lng);
        $userId = $_SESSION['user_id'] ?? 1;
        
        $stmt = $this->db->prepare("UPDATE node 
                                    SET name = ?, 
                                        type_id = ?, 
                                        location = ST_GeomFromText(?), 
                                        region_id = ?,
                                        updated_at = ?,
                                        updated_by = ?
                                    WHERE id = ?");
        
        return $stmt->execute([$name, $nodeTypeId, $point, $regionId, $now, $userId, $id]);
    }
    
    public function delete($id) {
        try {
            $this->db->beginTransaction();
            
            $segmentStmt = $this->db->prepare("DELETE FROM segment WHERE from_node_id = ? OR to_node_id = ?");
            $segmentStmt->execute([$id, $id]);
            
            $nodeStmt = $this->db->prepare("DELETE FROM node WHERE id = ?");
            $result = $nodeStmt->execute([$id]);
            
            $this->db->commit();
            return $result;
        } catch (PDOException $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    public function getAllNodeTypes() {
        $stmt = $this->db->query("SELECT id, type_name, description FROM node_type ORDER BY type_name");
        return $stmt->fetchAll();
    }
    
    public function getRegions() {
        $stmt = $this->db->query("SELECT id, name FROM region ORDER BY name");
        return $stmt->fetchAll();
    }
    
    public function getNodeTypes() {
        $stmt = $this->db->query("SELECT id, type_name as name FROM node_type ORDER BY type_name");
        return $stmt->fetchAll();
    }
    
    public function getNodeReceivers($nodeId) {
        $stmt = $this->db->prepare("SELECT DISTINCT u.id, u.username, u.email,
                                          COUNT(tr.id) as transport_count,
                                          MAX(tr.departure_time) as last_transport
                                    FROM user u
                                    JOIN transport_record tr ON u.id = tr.receiver_user_id
                                    JOIN segment s ON tr.segment_id = s.id
                                    WHERE s.from_node_id = ? OR s.to_node_id = ?
                                    GROUP BY u.id, u.username, u.email
                                    ORDER BY last_transport DESC");
        $stmt->execute([$nodeId, $nodeId]);
        
        return $stmt->fetchAll();
    }
} 