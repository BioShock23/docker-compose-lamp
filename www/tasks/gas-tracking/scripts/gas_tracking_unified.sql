-- =====================================================
-- Gas Tracking System - Unified Database Setup Script
-- =====================================================

-- Drop database if exists and create new one
DROP DATABASE IF EXISTS gas_tracking;
CREATE DATABASE gas_tracking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gas_tracking;

-- Create application user for Docker environment
DROP USER IF EXISTS 'gas_app'@'localhost';
DROP USER IF EXISTS 'gas_app'@'%';
CREATE USER 'gas_app'@'%' IDENTIFIED BY 'gas_app_password';
GRANT ALL PRIVILEGES ON gas_tracking.* TO 'gas_app'@'%';
FLUSH PRIVILEGES;

-- =====================================================
-- TABLE CREATION
-- =====================================================

SET FOREIGN_KEY_CHECKS=0;

-- Roles table
CREATE TABLE role (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_by BIGINT,
    updated_by BIGINT
);

-- Regions table
CREATE TABLE region (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_by BIGINT,
    updated_by BIGINT
);

-- Users table
CREATE TABLE user (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    region_id BIGINT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_login DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    remember_token VARCHAR(255),
    FOREIGN KEY (region_id) REFERENCES region(id)
);

-- Add foreign keys for role table
ALTER TABLE role 
    ADD CONSTRAINT fk_role_created_by FOREIGN KEY (created_by) REFERENCES user(id),
    ADD CONSTRAINT fk_role_updated_by FOREIGN KEY (updated_by) REFERENCES user(id);

-- Add foreign keys for region table
ALTER TABLE region 
    ADD CONSTRAINT fk_region_created_by FOREIGN KEY (created_by) REFERENCES user(id),
    ADD CONSTRAINT fk_region_updated_by FOREIGN KEY (updated_by) REFERENCES user(id);

-- User roles junction table
CREATE TABLE user_role (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    role_id BIGINT NOT NULL,
    assigned_at DATETIME NOT NULL,
    assigned_by BIGINT,
    FOREIGN KEY (user_id) REFERENCES user(id),
    FOREIGN KEY (role_id) REFERENCES role(id),
    FOREIGN KEY (assigned_by) REFERENCES user(id)
);

-- Node types table
CREATE TABLE node_type (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    type_name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_by BIGINT,
    updated_by BIGINT,
    FOREIGN KEY (created_by) REFERENCES user(id),
    FOREIGN KEY (updated_by) REFERENCES user(id)
);

-- Nodes table
CREATE TABLE node (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    type_id BIGINT NOT NULL,
    location POINT NOT NULL,
    region_id BIGINT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_by BIGINT,
    updated_by BIGINT,
    FOREIGN KEY (type_id) REFERENCES node_type(id),
    FOREIGN KEY (region_id) REFERENCES region(id),
    FOREIGN KEY (created_by) REFERENCES user(id),
    FOREIGN KEY (updated_by) REFERENCES user(id)
);

-- Transport methods table
CREATE TABLE transport_method (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    method_name VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_by BIGINT,
    updated_by BIGINT,
    FOREIGN KEY (created_by) REFERENCES user(id),
    FOREIGN KEY (updated_by) REFERENCES user(id)
);

-- Segments table
CREATE TABLE segment (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    from_node_id BIGINT NOT NULL,
    to_node_id BIGINT NOT NULL,
    method_id BIGINT NOT NULL,
    capacity DECIMAL(12,3) DEFAULT 0,
    length DECIMAL(12,3) DEFAULT 0,
    geometry LINESTRING NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_by BIGINT,
    updated_by BIGINT,
    FOREIGN KEY (from_node_id) REFERENCES node(id),
    FOREIGN KEY (to_node_id) REFERENCES node(id),
    FOREIGN KEY (method_id) REFERENCES transport_method(id),
    FOREIGN KEY (created_by) REFERENCES user(id),
    FOREIGN KEY (updated_by) REFERENCES user(id)
);

-- Gas grades table
CREATE TABLE gas_grade (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    grade_name VARCHAR(50) NOT NULL,
    description TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_by BIGINT,
    updated_by BIGINT,
    FOREIGN KEY (created_by) REFERENCES user(id),
    FOREIGN KEY (updated_by) REFERENCES user(id)
);

-- Transport records table
CREATE TABLE transport_record (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    segment_id BIGINT NOT NULL,
    grade_id BIGINT NOT NULL,
    receiver_user_id BIGINT NOT NULL,
    amount DECIMAL(12,3) NOT NULL,
    departure_time DATETIME NOT NULL,
    arrival_time DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_by BIGINT,
    updated_by BIGINT,
    FOREIGN KEY (segment_id) REFERENCES segment(id),
    FOREIGN KEY (grade_id) REFERENCES gas_grade(id),
    FOREIGN KEY (receiver_user_id) REFERENCES user(id),
    FOREIGN KEY (created_by) REFERENCES user(id),
    FOREIGN KEY (updated_by) REFERENCES user(id)
);

SET FOREIGN_KEY_CHECKS=1;

-- =====================================================
-- VIEWS
-- =====================================================

-- View for user statistics
CREATE VIEW v_user_stats AS
SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_users
FROM user;

-- View for node statistics
CREATE VIEW v_node_stats AS
SELECT 
    COUNT(*) as total_nodes,
    COUNT(DISTINCT region_id) as regions_with_nodes
FROM node;

-- View for transport statistics
CREATE VIEW v_transport_stats AS
SELECT 
    COUNT(*) as total_transports,
    COUNT(DISTINCT CONCAT(s.from_node_id, '-', s.to_node_id)) as unique_routes,
    COALESCE(SUM(tr.amount), 0) as total_volume
FROM transport_record tr
JOIN segment s ON tr.segment_id = s.id;

-- View for detailed segments with node information
CREATE VIEW v_segments_detailed AS
SELECT 
    s.id,
    s.from_node_id,
    s.to_node_id,
    s.method_id,
    s.capacity,
    s.length,
    fn.name as from_node_name,
    tn.name as to_node_name,
    tm.method_name,
    ST_AsText(s.geometry) as geometry_text,
    s.created_at,
    s.updated_at
FROM segment s
JOIN node fn ON s.from_node_id = fn.id
JOIN node tn ON s.to_node_id = tn.id
JOIN transport_method tm ON s.method_id = tm.id;

-- View for transport records with full details
CREATE VIEW v_transport_records_detailed AS
SELECT 
    tr.id,
    tr.segment_id,
    tr.grade_id,
    tr.receiver_user_id,
    tr.amount,
    tr.departure_time,
    tr.arrival_time,
    fn.name as from_node_name,
    tn.name as to_node_name,
    tm.method_name,
    gg.grade_name,
    u.username as receiver_username,
    r.name as receiver_region,
    tr.created_at,
    tr.updated_at
FROM transport_record tr
JOIN segment s ON tr.segment_id = s.id
JOIN node fn ON s.from_node_id = fn.id
JOIN node tn ON s.to_node_id = tn.id
JOIN transport_method tm ON s.method_id = tm.id
JOIN gas_grade gg ON tr.grade_id = gg.id
JOIN user u ON tr.receiver_user_id = u.id
LEFT JOIN region r ON u.region_id = r.id;

-- View for regions with node count
CREATE VIEW v_regions_with_stats AS
SELECT 
    r.id,
    r.name,
    COUNT(n.id) as node_count,
    r.created_at,
    r.updated_at
FROM region r
LEFT JOIN node n ON r.id = n.region_id
GROUP BY r.id, r.name, r.created_at, r.updated_at;

-- View for node types with usage count
CREATE VIEW v_node_types_with_stats AS
SELECT 
    nt.id,
    nt.type_name,
    nt.description,
    COUNT(n.id) as usage_count,
    nt.created_at,
    nt.updated_at
FROM node_type nt
LEFT JOIN node n ON nt.id = n.type_id
GROUP BY nt.id, nt.type_name, nt.description, nt.created_at, nt.updated_at;

-- View for gas grades with usage count
CREATE VIEW v_gas_grades_with_stats AS
SELECT 
    gg.id,
    gg.grade_name,
    gg.description,
    COUNT(tr.id) as usage_count,
    gg.created_at,
    gg.updated_at
FROM gas_grade gg
LEFT JOIN transport_record tr ON gg.id = tr.grade_id
GROUP BY gg.id, gg.grade_name, gg.description, gg.created_at, gg.updated_at;

-- View for transport methods with usage count
CREATE VIEW v_transport_methods_with_stats AS
SELECT 
    tm.id,
    tm.method_name,
    COUNT(s.id) as usage_count,
    tm.created_at,
    tm.updated_at
FROM transport_method tm
LEFT JOIN segment s ON tm.id = s.method_id
GROUP BY tm.id, tm.method_name, tm.created_at, tm.updated_at;

-- =====================================================
-- FUNCTIONS
-- =====================================================

DELIMITER //

-- Function to get user role
CREATE FUNCTION f_get_user_role(user_id BIGINT) 
RETURNS VARCHAR(50)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE role_name VARCHAR(50);
    SELECT r.role_name INTO role_name
    FROM user_role ur
    JOIN role r ON ur.role_id = r.id
    WHERE ur.user_id = user_id
    LIMIT 1;
    RETURN IFNULL(role_name, 'no_role');
END //

-- Function to calculate distance between two nodes
CREATE FUNCTION f_calculate_node_distance(node1_id BIGINT, node2_id BIGINT)
RETURNS DECIMAL(10,2)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE distance DECIMAL(10,2);
    SELECT ST_Distance_Sphere(n1.location, n2.location) / 1000 INTO distance
    FROM node n1, node n2
    WHERE n1.id = node1_id AND n2.id = node2_id;
    RETURN IFNULL(distance, 0);
END //

-- Function to get total transport volume for user
CREATE FUNCTION f_get_user_transport_volume(user_id BIGINT)
RETURNS DECIMAL(15,3)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE total_volume DECIMAL(15,3);
    SELECT COALESCE(SUM(amount), 0) INTO total_volume
    FROM transport_record
    WHERE receiver_user_id = user_id;
    RETURN total_volume;
END //

-- Function to check if segment exists
CREATE FUNCTION f_segment_exists(from_node BIGINT, to_node BIGINT, method BIGINT)
RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE segment_count INT;
    SELECT COUNT(*) INTO segment_count
    FROM segment
    WHERE from_node_id = from_node AND to_node_id = to_node AND method_id = method;
    RETURN segment_count > 0;
END //

DELIMITER ;

-- =====================================================
-- STORED PROCEDURES
-- =====================================================

DELIMITER //

-- Procedure to get all segments with details
CREATE PROCEDURE sp_get_segments_detailed(
    IN p_limit INT,
    IN p_offset INT
)
BEGIN
    SET p_limit = IFNULL(p_limit, 100);
    SET p_offset = IFNULL(p_offset, 0);
    
    SELECT * FROM v_segments_detailed
    ORDER BY id
    LIMIT p_limit OFFSET p_offset;
END //

-- Procedure to get transport records with filters
CREATE PROCEDURE sp_get_transport_records(
    IN p_user_id BIGINT,
    IN p_grade_id BIGINT,
    IN p_from_date DATETIME,
    IN p_to_date DATETIME,
    IN p_limit INT,
    IN p_offset INT
)
BEGIN
    SET p_limit = IFNULL(p_limit, 100);
    SET p_offset = IFNULL(p_offset, 0);
    
    SELECT * FROM v_transport_records_detailed
    WHERE (p_user_id IS NULL OR receiver_user_id = p_user_id)
      AND (p_grade_id IS NULL OR grade_id = p_grade_id)
      AND (p_from_date IS NULL OR departure_time >= p_from_date)
      AND (p_to_date IS NULL OR arrival_time <= p_to_date)
    ORDER BY departure_time DESC
    LIMIT p_limit OFFSET p_offset;
END //

-- Procedure to create transport record
CREATE PROCEDURE sp_create_transport_record(
    IN p_segment_id BIGINT,
    IN p_grade_id BIGINT,
    IN p_receiver_user_id BIGINT,
    IN p_amount DECIMAL(12,3),
    IN p_departure_time DATETIME,
    IN p_arrival_time DATETIME,
    IN p_created_by BIGINT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    INSERT INTO transport_record (
        segment_id, grade_id, receiver_user_id, amount,
        departure_time, arrival_time, created_at, updated_at, created_by, updated_by
    ) VALUES (
        p_segment_id, p_grade_id, p_receiver_user_id, p_amount,
        p_departure_time, p_arrival_time, NOW(), NOW(), p_created_by, p_created_by
    );
    
    COMMIT;
    SELECT LAST_INSERT_ID() as record_id;
END //

-- Procedure to update transport record
CREATE PROCEDURE sp_update_transport_record(
    IN p_record_id BIGINT,
    IN p_segment_id BIGINT,
    IN p_grade_id BIGINT,
    IN p_receiver_user_id BIGINT,
    IN p_amount DECIMAL(12,3),
    IN p_departure_time DATETIME,
    IN p_arrival_time DATETIME,
    IN p_updated_by BIGINT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    UPDATE transport_record SET
        segment_id = p_segment_id,
        grade_id = p_grade_id,
        receiver_user_id = p_receiver_user_id,
        amount = p_amount,
        departure_time = p_departure_time,
        arrival_time = p_arrival_time,
        updated_at = NOW(),
        updated_by = p_updated_by
    WHERE id = p_record_id;
    
    COMMIT;
    SELECT ROW_COUNT() as affected_rows;
END //

-- Procedure to delete transport record
CREATE PROCEDURE sp_delete_transport_record(
    IN p_record_id BIGINT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    DELETE FROM transport_record WHERE id = p_record_id;
    
    COMMIT;
    SELECT ROW_COUNT() as affected_rows;
END //

-- Procedure to create segment with geometry
CREATE PROCEDURE sp_create_segment(
    IN p_from_node_id BIGINT,
    IN p_to_node_id BIGINT,
    IN p_method_id BIGINT,
    IN p_capacity DECIMAL(12,3),
    IN p_length DECIMAL(12,3),
    IN p_from_lat DECIMAL(10,8),
    IN p_from_lon DECIMAL(11,8),
    IN p_to_lat DECIMAL(10,8),
    IN p_to_lon DECIMAL(11,8),
    IN p_created_by BIGINT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    INSERT INTO segment (
        from_node_id, to_node_id, method_id, capacity, length, geometry,
        created_at, updated_at, created_by, updated_by
    ) VALUES (
        p_from_node_id, p_to_node_id, p_method_id, p_capacity, p_length,
        LineString(Point(p_from_lon, p_from_lat), Point(p_to_lon, p_to_lat)),
        NOW(), NOW(), p_created_by, p_created_by
    );
    
    COMMIT;
    SELECT LAST_INSERT_ID() as segment_id;
END //

-- Procedure to get nodes for dropdown
CREATE PROCEDURE sp_get_nodes_list()
BEGIN
    SELECT id, name FROM node ORDER BY name;
END //

-- Procedure to get transport methods for dropdown
CREATE PROCEDURE sp_get_transport_methods_list()
BEGIN
    SELECT id, method_name as name FROM transport_method ORDER BY method_name;
END //

-- Procedure to get gas grades for dropdown
CREATE PROCEDURE sp_get_gas_grades_list()
BEGIN
    SELECT id, grade_name as name FROM gas_grade ORDER BY grade_name;
END //

-- Procedure to get regions for dropdown
CREATE PROCEDURE sp_get_regions_list()
BEGIN
    SELECT id, name FROM region ORDER BY name;
END //

-- Procedure to get node types for dropdown
CREATE PROCEDURE sp_get_node_types_list()
BEGIN
    SELECT id, type_name as name FROM node_type ORDER BY type_name;
END //

-- Procedure to get roles for dropdown
CREATE PROCEDURE sp_get_roles_list()
BEGIN
    SELECT id, role_name FROM role ORDER BY role_name;
END //

-- Procedure to get system statistics
CREATE PROCEDURE sp_get_system_statistics()
BEGIN
    SELECT 
        (SELECT total_users FROM v_user_stats) as total_users,
        (SELECT active_users FROM v_user_stats) as active_users,
        (SELECT total_nodes FROM v_node_stats) as total_nodes,
        (SELECT total_transports FROM v_transport_stats) as total_transports,
        (SELECT total_volume FROM v_transport_stats) as total_volume,
        (SELECT unique_routes FROM v_transport_stats) as unique_routes;
END //

-- Procedure to get user by credentials
CREATE PROCEDURE sp_authenticate_user(
    IN p_username VARCHAR(100),
    IN p_password VARCHAR(255)
)
BEGIN
    SELECT u.*, f_get_user_role(u.id) as role_name
    FROM user u
    WHERE u.username = p_username AND u.password = p_password AND u.is_active = 1;
END //

-- Procedure to assign role to user
CREATE PROCEDURE sp_assign_user_role(
    IN p_user_id BIGINT,
    IN p_role_name VARCHAR(50),
    IN p_assigned_by BIGINT
)
BEGIN
    DECLARE v_role_id BIGINT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    SELECT id INTO v_role_id FROM role WHERE role_name = p_role_name;
    
    IF v_role_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Role not found';
    END IF;
    
    INSERT IGNORE INTO user_role (user_id, role_id, assigned_at, assigned_by)
    VALUES (p_user_id, v_role_id, NOW(), p_assigned_by);
    
    COMMIT;
    SELECT ROW_COUNT() as affected_rows;
END //

DELIMITER ;

-- =====================================================
-- INITIAL DATA SEEDING
-- =====================================================

-- Insert roles
INSERT INTO role (role_name, description, created_at, updated_at) VALUES
('admin', 'System administrator with full access', NOW(), NOW()),
('employee', 'Employee with limited access', NOW(), NOW()),
('client', 'Client with read-only access', NOW(), NOW());

-- Insert regions
INSERT INTO region (name, created_at, updated_at) VALUES
('Центральный', NOW(), NOW()),
('Северный', NOW(), NOW()),
('Южный', NOW(), NOW()),
('Западный', NOW(), NOW()),
('Восточный', NOW(), NOW());

-- Insert admin user
INSERT INTO user (username, password, email, region_id, is_active, created_at, updated_at) VALUES
('admin', 'admin123', 'admin@example.com', 1, 1, NOW(), NOW());

-- Update foreign key references for roles and regions
UPDATE role SET created_by = 1, updated_by = 1;
UPDATE region SET created_by = 1, updated_by = 1;

-- Assign admin role to admin user
INSERT INTO user_role (user_id, role_id, assigned_at, assigned_by) VALUES
(1, 1, NOW(), 1);

-- Insert node types
INSERT INTO node_type (type_name, description, created_at, updated_at, created_by, updated_by) VALUES
('Well', 'Gas extraction well', NOW(), NOW(), 1, 1),
('Storage', 'Gas storage facility', NOW(), NOW(), 1, 1),
('Processing', 'Gas processing plant', NOW(), NOW(), 1, 1);

-- Insert transport methods
INSERT INTO transport_method (method_name, created_at, updated_at, created_by, updated_by) VALUES
('Pipeline', NOW(), NOW(), 1, 1),
('Road Transport', NOW(), NOW(), 1, 1),
('Rail Transport', NOW(), NOW(), 1, 1);

-- Insert gas grades
INSERT INTO gas_grade (grade_name, description, created_at, updated_at, created_by, updated_by) VALUES
('Grade A', 'High quality gas for industrial use', NOW(), NOW(), 1, 1),
('Grade B', 'Standard gas for residential use', NOW(), NOW(), 1, 1),
('Grade C', 'Low grade gas for heating', NOW(), NOW(), 1, 1);

-- =====================================================
-- DEMO DATA SEEDING
-- =====================================================

-- Insert demo users
INSERT INTO user (username, password, email, region_id, is_active, created_at, updated_at) VALUES
('employee_central', 'admin123', 'emp_central@example.com', 1, 1, NOW(), NOW()),
('employee_north', 'admin123', 'emp_north@example.com', 2, 1, NOW(), NOW()),
('employee_south', 'admin123', 'emp_south@example.com', 3, 1, NOW(), NOW()),
('client1', 'admin123', 'client1@example.com', 1, 1, NOW(), NOW()),
('client2', 'admin123', 'client2@example.com', 2, 1, NOW(), NOW());

-- Assign roles to demo users
INSERT INTO user_role (user_id, role_id, assigned_at, assigned_by) VALUES
(2, 2, NOW(), 1), -- employee_central -> employee
(3, 2, NOW(), 1), -- employee_north -> employee
(4, 2, NOW(), 1), -- employee_south -> employee
(5, 3, NOW(), 1), -- client1 -> client
(6, 3, NOW(), 1); -- client2 -> client

-- Insert demo nodes
INSERT INTO node (name, type_id, location, region_id, created_at, updated_at, created_by, updated_by) VALUES
-- Central region
('Скважина Ц1', 1, POINT(37.61, 55.75), 1, NOW(), NOW(), 1, 1),
('Скважина Ц2', 1, POINT(37.65, 55.78), 1, NOW(), NOW(), 1, 1),
('Хранилище Ц1', 2, POINT(37.59, 55.73), 1, NOW(), NOW(), 1, 1),
-- Northern region
('Скважина С1', 1, POINT(30.31, 59.93), 2, NOW(), NOW(), 1, 1),
('Хранилище С1', 2, POINT(30.35, 59.95), 2, NOW(), NOW(), 1, 1),
-- Southern region
('Скважина Ю1', 1, POINT(39.71, 47.23), 3, NOW(), NOW(), 1, 1),
('Хранилище Ю1', 2, POINT(39.75, 47.25), 3, NOW(), NOW(), 1, 1);

-- Insert demo segments
INSERT INTO segment (from_node_id, to_node_id, method_id, capacity, length, geometry, created_at, updated_at, created_by, updated_by) VALUES
-- Central region connections
(1, 3, 1, 1500.00, 12.5, LineString(Point(37.61, 55.75), Point(37.59, 55.73)), NOW(), NOW(), 1, 1),
(2, 3, 1, 1200.00, 8.3, LineString(Point(37.65, 55.78), Point(37.59, 55.73)), NOW(), NOW(), 1, 1),
-- Northern region connections
(4, 5, 1, 2000.00, 15.7, LineString(Point(30.31, 59.93), Point(30.35, 59.95)), NOW(), NOW(), 1, 1),
-- Southern region connections
(6, 7, 2, 800.00, 6.2, LineString(Point(39.71, 47.23), Point(39.75, 47.25)), NOW(), NOW(), 1, 1),
-- Cross-region connections
(3, 5, 1, 3000.00, 85.4, LineString(Point(37.59, 55.73), Point(30.35, 59.95)), NOW(), NOW(), 1, 1),
(3, 7, 2, 1800.00, 95.2, LineString(Point(37.59, 55.73), Point(39.75, 47.25)), NOW(), NOW(), 1, 1);

-- Insert demo transport records
INSERT INTO transport_record (segment_id, grade_id, receiver_user_id, amount, departure_time, arrival_time, created_at, updated_at, created_by, updated_by) VALUES
-- For client1
(1, 1, 5, 1000.500, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), NOW(), NOW(), 1, 1),
(5, 2, 5, 750.300, DATE_SUB(NOW(), INTERVAL 1 DAY), NOW(), NOW(), NOW(), 1, 1),
-- For client2
(3, 1, 6, 1200.000, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), NOW(), NOW(), 1, 1),
(6, 1, 6, 890.500, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), NOW(), NOW(), 1, 1);

-- =====================================================
-- FINAL SETUP
-- =====================================================

-- Create indexes for better performance
CREATE INDEX idx_transport_record_receiver ON transport_record(receiver_user_id);
CREATE INDEX idx_transport_record_departure ON transport_record(departure_time);
CREATE INDEX idx_transport_record_segment ON transport_record(segment_id);
CREATE INDEX idx_user_role_user ON user_role(user_id);
CREATE INDEX idx_node_region ON node(region_id);
CREATE INDEX idx_segment_from_node ON segment(from_node_id);
CREATE INDEX idx_segment_to_node ON segment(to_node_id);
