-- Обновление представления для использования вычисляемой длины сегментов
DROP VIEW IF EXISTS v_segments_detailed;

CREATE VIEW v_segments_detailed AS
SELECT 
    s.id,
    s.from_node_id,
    s.to_node_id,
    s.method_id,
    s.capacity,
    f_calculate_node_distance(s.from_node_id, s.to_node_id) as length,
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