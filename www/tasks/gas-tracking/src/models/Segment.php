<?php

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../lib/utilities.php";

class Segment {
    private $db;
    
    public function __construct() {
        $this->db = getConnection();
    }
    
    public function getAll($regionId = null) {
        $sql = "SELECT s.id, s.from_node_id, s.to_node_id, s.method_id, s.capacity,
                       f_calculate_node_distance(s.from_node_id, s.to_node_id) as length,
                       n1.name as from_node_name, n2.name as to_node_name, 
                       tm.method_name, 
                       ST_AsGeoJSON(s.geometry) as geometry_json,
                       s.created_at, s.updated_at, 
                       u1.username as created_by_name, u2.username as updated_by_name
                FROM segment s
                JOIN node n1 ON s.from_node_id = n1.id
                JOIN node n2 ON s.to_node_id = n2.id
                JOIN transport_method tm ON s.method_id = tm.id
                LEFT JOIN user u1 ON s.created_by = u1.id
                LEFT JOIN user u2 ON s.updated_by = u2.id";
        
        $params = [];
        
        if ($regionId) {
            $sql .= " WHERE n1.region_id = ? OR n2.region_id = ?";
            $params = [$regionId, $regionId];
        }
        
        $sql .= " ORDER BY n1.name, n2.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function getAllSegments() {
        $sql = "SELECT s.id, s.from_node_id, s.to_node_id, s.method_id, s.capacity,
                       f_calculate_node_distance(s.from_node_id, s.to_node_id) as length,
                       n1.name as from_node_name, n2.name as to_node_name, 
                       tm.method_name, 
                       ST_AsGeoJSON(s.geometry) as geometry_json
                FROM segment s
                JOIN node n1 ON s.from_node_id = n1.id
                JOIN node n2 ON s.to_node_id = n2.id
                JOIN transport_method tm ON s.method_id = tm.id
                ORDER BY n1.name, n2.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function getSegmentsByRegion($regionId) {
        $sql = "SELECT s.id, s.from_node_id, s.to_node_id, s.method_id, s.capacity,
                       f_calculate_node_distance(s.from_node_id, s.to_node_id) as length,
                       n1.name as from_node_name, n2.name as to_node_name, 
                       tm.method_name, 
                       ST_AsGeoJSON(s.geometry) as geometry_json
                FROM segment s
                JOIN node n1 ON s.from_node_id = n1.id
                JOIN node n2 ON s.to_node_id = n2.id
                JOIN transport_method tm ON s.method_id = tm.id
                WHERE n1.region_id = ? OR n2.region_id = ?
                ORDER BY n1.name, n2.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$regionId, $regionId]);
        
        return $stmt->fetchAll();
    }
    
    public function getSegmentsByReceiver($userId) {
        $sql = "SELECT DISTINCT s.id, s.from_node_id, s.to_node_id, s.method_id, s.capacity,
                       f_calculate_node_distance(s.from_node_id, s.to_node_id) as length,
                       n1.name as from_node_name, n2.name as to_node_name, 
                       tm.method_name, 
                       ST_AsGeoJSON(s.geometry) as geometry_json
                FROM segment s
                JOIN node n1 ON s.from_node_id = n1.id
                JOIN node n2 ON s.to_node_id = n2.id
                JOIN transport_method tm ON s.method_id = tm.id
                JOIN transport_record tr ON s.id = tr.segment_id
                WHERE tr.receiver_user_id = ?
                ORDER BY n1.name, n2.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT s.id, s.from_node_id, s.to_node_id, s.method_id, s.capacity,
                                          f_calculate_node_distance(s.from_node_id, s.to_node_id) as length,
                                          n1.name as from_node_name, n2.name as to_node_name, 
                                          tm.method_name, 
                                          ST_AsGeoJSON(s.geometry) as geometry_json,
                                          s.created_at, s.updated_at, 
                                          u1.username as created_by_name, u2.username as updated_by_name
                                   FROM segment s
                                   JOIN node n1 ON s.from_node_id = n1.id
                                   JOIN node n2 ON s.to_node_id = n2.id
                                   JOIN transport_method tm ON s.method_id = tm.id
                                   LEFT JOIN user u1 ON s.created_by = u1.id
                                   LEFT JOIN user u2 ON s.updated_by = u2.id
                                   WHERE s.id = ?");
        $stmt->execute([$id]);
        
        return $stmt->fetch();
    }
    
    public function create($fromNodeId, $toNodeId, $capacity = 0, $length = 0, $methodId = 1) {
        $now = getCurrentTimestamp();
        $userId = $_SESSION['user_id'] ?? 1;
        
        $points = $this->generateLineString($fromNodeId, $toNodeId);
        if (!$points) {
            return false;
        }
        
        $linestring = createMysqlLinestring($points);
        
        $stmt = $this->db->prepare("INSERT INTO segment (from_node_id, to_node_id, method_id, capacity, geometry, created_at, updated_at, created_by, updated_by)
                                    VALUES (?, ?, ?, ?, ST_GeomFromText(?), ?, ?, ?, ?)");
        
        return $stmt->execute([$fromNodeId, $toNodeId, $methodId, $capacity, $linestring, $now, $now, $userId, $userId]);
    }
    
    public function update($id, $fromNodeId, $toNodeId, $capacity = 0, $length = 0, $methodId = 1) {
        $now = getCurrentTimestamp();
        $userId = $_SESSION['user_id'] ?? 1;
        
        $points = $this->generateLineString($fromNodeId, $toNodeId);
        if (!$points) {
            return false;
        }
        
        $linestring = createMysqlLinestring($points);
        
        $stmt = $this->db->prepare("UPDATE segment 
                                    SET from_node_id = ?, 
                                        to_node_id = ?, 
                                        method_id = ?,
                                        capacity = ?,
                                        geometry = ST_GeomFromText(?),
                                        updated_at = ?,
                                        updated_by = ?
                                    WHERE id = ?");
        
        return $stmt->execute([$fromNodeId, $toNodeId, $methodId, $capacity, $linestring, $now, $userId, $id]);
    }
    
    public function delete($id) {
        try {
            $this->db->beginTransaction();
            
            $transportStmt = $this->db->prepare("DELETE FROM transport_record WHERE segment_id = ?");
            $transportStmt->execute([$id]);
            
            $segmentStmt = $this->db->prepare("DELETE FROM segment WHERE id = ?");
            $result = $segmentStmt->execute([$id]);
            
            $this->db->commit();
            return $result;
        } catch (PDOException $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    public function getAllTransportMethods() {
        $stmt = $this->db->query("SELECT id, method_name FROM transport_method ORDER BY method_name");
        return $stmt->fetchAll();
    }
    
    public function generateLineString($fromNodeId, $toNodeId) {
        $stmt = $this->db->prepare("SELECT ST_X(location) as lon, ST_Y(location) as lat FROM node WHERE id = ?");
        
        $stmt->execute([$fromNodeId]);
        $fromNode = $stmt->fetch();
        
        $stmt->execute([$toNodeId]);
        $toNode = $stmt->fetch();
        
        if (!$fromNode || !$toNode) {
            return false;
        }
        
        return [
            [$fromNode['lat'], $fromNode['lon']],
            [$toNode['lat'], $toNode['lon']]
        ];
    }
    
    public function getNodes() {
        $stmt = $this->db->query("SELECT id, name FROM node ORDER BY name");
        return $stmt->fetchAll();
    }
    
    private function calculateDistance($fromNodeId, $toNodeId) {
        $stmt = $this->db->prepare("SELECT f_calculate_node_distance(?, ?) as distance");
        $stmt->execute([$fromNodeId, $toNodeId]);
        $result = $stmt->fetch();
        
        return $result ? (float)$result['distance'] : 0;
    }
} 