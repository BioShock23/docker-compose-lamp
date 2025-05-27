<?php

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../lib/utilities.php";

class TransportRecord {
    private $db;
    
    public function __construct() {
        $this->db = getConnection();
    }
    
    public function getAll($userId = null, $regionId = null) {
        $sql = "SELECT tr.id, tr.segment_id, tr.grade_id, tr.receiver_user_id, 
                       tr.amount, tr.departure_time, tr.arrival_time,
                       s.from_node_id, s.to_node_id, 
                       n1.name as from_node_name, n2.name as to_node_name,
                       n1.region_id as from_region_id, n2.region_id as to_region_id,
                       r1.name as from_region_name, r2.name as to_region_name,
                       s.method_id, tm.method_name,
                       gg.grade_name, u.username as receiver_name,
                       tr.created_at, tr.updated_at, 
                       u1.username as created_by_name, u2.username as updated_by_name
                FROM transport_record tr
                JOIN segment s ON tr.segment_id = s.id
                JOIN node n1 ON s.from_node_id = n1.id
                JOIN node n2 ON s.to_node_id = n2.id
                JOIN region r1 ON n1.region_id = r1.id
                JOIN region r2 ON n2.region_id = r2.id
                JOIN transport_method tm ON s.method_id = tm.id
                JOIN gas_grade gg ON tr.grade_id = gg.id
                JOIN user u ON tr.receiver_user_id = u.id
                LEFT JOIN user u1 ON tr.created_by = u1.id
                LEFT JOIN user u2 ON tr.updated_by = u2.id";
        
        $params = [];
        $conditions = [];
        
        if ($userId) {
            $conditions[] = "tr.receiver_user_id = ?";
            $params[] = $userId;
        }
        
        if ($regionId) {
            $conditions[] = "(n1.region_id = ? OR n2.region_id = ?)";
            $params[] = $regionId;
            $params[] = $regionId;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY tr.departure_time DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT tr.id, tr.segment_id, tr.grade_id, tr.receiver_user_id, 
                                          tr.amount, tr.departure_time, tr.arrival_time,
                                          s.from_node_id, s.to_node_id, 
                                          n1.name as from_node_name, n2.name as to_node_name,
                                          n1.region_id as from_region_id, n2.region_id as to_region_id,
                                          r1.name as from_region_name, r2.name as to_region_name,
                                          s.method_id, tm.method_name,
                                          gg.grade_name, u.username as receiver_name,
                                          tr.created_at, tr.updated_at, 
                                          u1.username as created_by_name, u2.username as updated_by_name
                                    FROM transport_record tr
                                    JOIN segment s ON tr.segment_id = s.id
                                    JOIN node n1 ON s.from_node_id = n1.id
                                    JOIN node n2 ON s.to_node_id = n2.id
                                    JOIN region r1 ON n1.region_id = r1.id
                                    JOIN region r2 ON n2.region_id = r2.id
                                    JOIN transport_method tm ON s.method_id = tm.id
                                    JOIN gas_grade gg ON tr.grade_id = gg.id
                                    JOIN user u ON tr.receiver_user_id = u.id
                                    LEFT JOIN user u1 ON tr.created_by = u1.id
                                    LEFT JOIN user u2 ON tr.updated_by = u2.id
                                    WHERE tr.id = ?");
        $stmt->execute([$id]);
        
        return $stmt->fetch();
    }
    
    public function create($segmentId, $gradeId, $receiverUserId, $amount, $departureTime, $arrivalTime, $userId) {
        $now = getCurrentTimestamp();
        
        $stmt = $this->db->prepare("INSERT INTO transport_record (segment_id, grade_id, receiver_user_id, amount, departure_time, arrival_time, created_at, updated_at, created_by, updated_by)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        return $stmt->execute([$segmentId, $gradeId, $receiverUserId, $amount, $departureTime, $arrivalTime, $now, $now, $userId, $userId]);
    }
    
    public function update($id, $segmentId, $gradeId, $receiverUserId, $amount, $departureTime, $arrivalTime, $userId) {
        $now = getCurrentTimestamp();
        
        $stmt = $this->db->prepare("UPDATE transport_record 
                                    SET segment_id = ?, 
                                        grade_id = ?, 
                                        receiver_user_id = ?, 
                                        amount = ?,
                                        departure_time = ?,
                                        arrival_time = ?,
                                        updated_at = ?,
                                        updated_by = ?
                                    WHERE id = ?");
        
        return $stmt->execute([$segmentId, $gradeId, $receiverUserId, $amount, $departureTime, $arrivalTime, $now, $userId, $id]);
    }
    
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM transport_record WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function getAllGasGrades() {
        $stmt = $this->db->query("SELECT id, grade_name, description FROM gas_grade ORDER BY grade_name");
        return $stmt->fetchAll();
    }
    
    public function getClientsForDropdown() {
        $stmt = $this->db->prepare("SELECT u.id, u.username, u.email 
                                    FROM user u 
                                    JOIN user_role ur ON u.id = ur.user_id 
                                    JOIN role r ON ur.role_id = r.id 
                                    WHERE r.role_name = ? 
                                    ORDER BY u.username");
        $stmt->execute(['client']);
        
        return $stmt->fetchAll();
    }
    
    public function getStatistics($period = 30, $regionId = null) {
        $sql = "SELECT 
                    DATE(tr.departure_time) as date,
                    SUM(tr.amount) as total_amount,
                    gg.grade_name,
                    COUNT(tr.id) as total_transports
                FROM transport_record tr
                JOIN gas_grade gg ON tr.grade_id = gg.id
                JOIN segment s ON tr.segment_id = s.id
                JOIN node n1 ON s.from_node_id = n1.id
                JOIN node n2 ON s.to_node_id = n2.id
                WHERE tr.departure_time >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
        
        $params = [$period];
        
        if ($regionId) {
            $sql .= " AND (n1.region_id = ? OR n2.region_id = ?)";
            $params[] = $regionId;
            $params[] = $regionId;
        }
        
        $sql .= " GROUP BY DATE(tr.departure_time), gg.grade_name
                  ORDER BY DATE(tr.departure_time)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function getActiveRoutes($regionId = null) {
        $sql = "SELECT 
                    s.id as segment_id,
                    n1.name as from_node_name,
                    n2.name as to_node_name,
                    tm.method_name,
                    COUNT(tr.id) as transport_count,
                    SUM(tr.amount) as total_amount
                FROM segment s
                JOIN node n1 ON s.from_node_id = n1.id
                JOIN node n2 ON s.to_node_id = n2.id
                JOIN transport_method tm ON s.method_id = tm.id
                LEFT JOIN transport_record tr ON s.id = tr.segment_id
                    AND tr.departure_time <= NOW() 
                    AND tr.arrival_time >= NOW()";
        
        $params = [];
        
        if ($regionId) {
            $sql .= " WHERE (n1.region_id = ? OR n2.region_id = ?)";
            $params = [$regionId, $regionId];
        }
        
        $sql .= " GROUP BY s.id, n1.name, n2.name, tm.method_name
                  HAVING COUNT(tr.id) > 0
                  ORDER BY transport_count DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function getTransportsByReceiver($receiverUserId) {
        $stmt = $this->db->prepare("SELECT tr.id, tr.segment_id, tr.grade_id, tr.receiver_user_id, 
                                          tr.amount, tr.departure_time, tr.arrival_time,
                                          s.from_node_id, s.to_node_id, 
                                          n1.name as from_node_name, n2.name as to_node_name,
                                          n1.region_id as from_region_id, n2.region_id as to_region_id,
                                          r1.name as from_region_name, r2.name as to_region_name,
                                          s.method_id, tm.method_name,
                                          gg.grade_name, u.username as receiver_username,
                                          tr.created_at, tr.updated_at
                                    FROM transport_record tr
                                    JOIN segment s ON tr.segment_id = s.id
                                    JOIN node n1 ON s.from_node_id = n1.id
                                    JOIN node n2 ON s.to_node_id = n2.id
                                    JOIN region r1 ON n1.region_id = r1.id
                                    JOIN region r2 ON n2.region_id = r2.id
                                    JOIN transport_method tm ON s.method_id = tm.id
                                    JOIN gas_grade gg ON tr.grade_id = gg.id
                                    JOIN user u ON tr.receiver_user_id = u.id
                                    WHERE tr.receiver_user_id = ?
                                    ORDER BY tr.departure_time DESC");
        $stmt->execute([$receiverUserId]);
        
        return $stmt->fetchAll();
    }
    
    public function getTransportsByRegion($regionId) {
        $stmt = $this->db->prepare("SELECT tr.id, tr.segment_id, tr.grade_id, tr.receiver_user_id, 
                                          tr.amount, tr.departure_time, tr.arrival_time,
                                          s.from_node_id, s.to_node_id, 
                                          n1.name as from_node_name, n2.name as to_node_name,
                                          n1.region_id as from_region_id, n2.region_id as to_region_id,
                                          r1.name as from_region_name, r2.name as to_region_name,
                                          s.method_id, tm.method_name,
                                          gg.grade_name, u.username as receiver_username,
                                          tr.created_at, tr.updated_at
                                    FROM transport_record tr
                                    JOIN segment s ON tr.segment_id = s.id
                                    JOIN node n1 ON s.from_node_id = n1.id
                                    JOIN node n2 ON s.to_node_id = n2.id
                                    JOIN region r1 ON n1.region_id = r1.id
                                    JOIN region r2 ON n2.region_id = r2.id
                                    JOIN transport_method tm ON s.method_id = tm.id
                                    JOIN gas_grade gg ON tr.grade_id = gg.id
                                    JOIN user u ON tr.receiver_user_id = u.id
                                    WHERE n1.region_id = ? OR n2.region_id = ?
                                    ORDER BY tr.departure_time DESC");
        $stmt->execute([$regionId, $regionId]);
        
        return $stmt->fetchAll();
    }
    
    public function getAllTransports($limit = 100) {
        $stmt = $this->db->prepare("SELECT tr.id, tr.segment_id, tr.grade_id, tr.receiver_user_id, 
                                          tr.amount, tr.departure_time, tr.arrival_time,
                                          s.from_node_id, s.to_node_id, 
                                          n1.name as from_node_name, n2.name as to_node_name,
                                          n1.region_id as from_region_id, n2.region_id as to_region_id,
                                          r1.name as from_region_name, r2.name as to_region_name,
                                          s.method_id, tm.method_name,
                                          gg.grade_name, u.username as receiver_username,
                                          tr.created_at, tr.updated_at
                                    FROM transport_record tr
                                    JOIN segment s ON tr.segment_id = s.id
                                    JOIN node n1 ON s.from_node_id = n1.id
                                    JOIN node n2 ON s.to_node_id = n2.id
                                    JOIN region r1 ON n1.region_id = r1.id
                                    JOIN region r2 ON n2.region_id = r2.id
                                    JOIN transport_method tm ON s.method_id = tm.id
                                    JOIN gas_grade gg ON tr.grade_id = gg.id
                                    JOIN user u ON tr.receiver_user_id = u.id
                                    ORDER BY tr.departure_time DESC
                                    LIMIT ?");
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll();
    }
    
    public function getStatisticsByReceiver($receiverUserId, $period = 30) {
        $stmt = $this->db->prepare("SELECT 
                    DATE(tr.departure_time) as date,
                    SUM(tr.amount) as total_amount,
                    gg.grade_name,
                    COUNT(tr.id) as total_transports
                FROM transport_record tr
                JOIN gas_grade gg ON tr.grade_id = gg.id
                WHERE tr.receiver_user_id = ? 
                AND tr.departure_time >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY DATE(tr.departure_time), gg.grade_name
                ORDER BY DATE(tr.departure_time)");
        $stmt->execute([$receiverUserId, $period]);
        
        return $stmt->fetchAll();
    }
    
    public function getStatisticsByRegion($regionId, $period = 30) {
        $stmt = $this->db->prepare("SELECT 
                    DATE(tr.departure_time) as date,
                    SUM(tr.amount) as total_amount,
                    gg.grade_name,
                    COUNT(tr.id) as total_transports
                FROM transport_record tr
                JOIN gas_grade gg ON tr.grade_id = gg.id
                JOIN segment s ON tr.segment_id = s.id
                JOIN node n1 ON s.from_node_id = n1.id
                JOIN node n2 ON s.to_node_id = n2.id
                WHERE (n1.region_id = ? OR n2.region_id = ?)
                AND tr.departure_time >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY DATE(tr.departure_time), gg.grade_name
                ORDER BY DATE(tr.departure_time)");
        $stmt->execute([$regionId, $regionId, $period]);
        
        return $stmt->fetchAll();
    }
    
    public function getPublicStatistics() {
        $stmt = $this->db->query("SELECT 
            (SELECT COUNT(DISTINCT CONCAT(s.from_node_id, '-', s.to_node_id)) 
             FROM transport_record tr 
             JOIN segment s ON tr.segment_id = s.id 
             WHERE tr.departure_time <= NOW() AND (tr.arrival_time IS NULL OR tr.arrival_time >= NOW())) as active_routes,
            (SELECT COUNT(*) FROM transport_record) as total_transports,
            (SELECT COALESCE(SUM(amount), 0) FROM transport_record) as total_volume,
            (SELECT COUNT(*) FROM node) as total_nodes");
        
        return $stmt->fetch();
    }
    
    public function getMonthlyStatistics($months = 12) {
        $stmt = $this->db->prepare("SELECT 
            DATE_FORMAT(tr.departure_time, '%Y-%m') as month,
            SUM(tr.amount) as total_volume,
            COUNT(tr.id) as transport_count
            FROM transport_record tr
            WHERE tr.departure_time >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(tr.departure_time, '%Y-%m')
            ORDER BY month");
        $stmt->execute([$months]);
        
        return $stmt->fetchAll();
    }
    
    public function getGradeStatistics($months = 12) {
        $stmt = $this->db->prepare("SELECT 
            gg.grade_name,
            SUM(tr.amount) as total_volume,
            COUNT(tr.id) as transport_count
            FROM transport_record tr
            JOIN gas_grade gg ON tr.grade_id = gg.id
            WHERE tr.departure_time >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            GROUP BY gg.id, gg.grade_name
            ORDER BY total_volume DESC");
        $stmt->execute([$months]);
        
        return $stmt->fetchAll();
    }
    
    public function getNodes() {
        $stmt = $this->db->query("SELECT id, name FROM node ORDER BY name");
        return $stmt->fetchAll();
    }
    
    public function getMethods() {
        $stmt = $this->db->query("SELECT id, method_name as name FROM transport_method ORDER BY method_name");
        return $stmt->fetchAll();
    }
    
    public function getGrades() {
        $stmt = $this->db->query("SELECT id, grade_name as name FROM gas_grade ORDER BY grade_name");
        return $stmt->fetchAll();
    }
    
    public function getClientNodes($userId) {
        $stmt = $this->db->prepare("SELECT DISTINCT n.id, n.name, n.type_id, nt.type_name, 
                                          ST_AsGeoJSON(n.location) as location_json,
                                          n.region_id, r.name as region_name
                                    FROM node n
                                    JOIN node_type nt ON n.type_id = nt.id
                                    JOIN region r ON n.region_id = r.id
                                    JOIN segment s ON (n.id = s.from_node_id OR n.id = s.to_node_id)
                                    JOIN transport_record tr ON s.id = tr.segment_id
                                    WHERE tr.receiver_user_id = ?
                                    ORDER BY n.name");
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll();
    }
    
    public function getClientSegments($userId) {
        $stmt = $this->db->prepare("SELECT DISTINCT s.id, s.from_node_id, s.to_node_id, s.method_id,
                                          n1.name as from_node_name, n2.name as to_node_name,
                                          tm.method_name,
                                          ST_AsGeoJSON(s.geometry) as geometry_json
                                    FROM segment s
                                    JOIN node n1 ON s.from_node_id = n1.id
                                    JOIN node n2 ON s.to_node_id = n2.id
                                    JOIN transport_method tm ON s.method_id = tm.id
                                    JOIN transport_record tr ON s.id = tr.segment_id
                                    WHERE tr.receiver_user_id = ?");
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll();
    }
} 