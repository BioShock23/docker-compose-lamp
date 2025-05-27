<?php
session_start();


require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../lib/utilities.php";
require_once __DIR__ . "/../src/models/Node.php";
require_once __DIR__ . "/../src/models/Segment.php";
require_once __DIR__ . "/../src/models/User.php";
require_once __DIR__ . "/../src/models/Region.php";
require_once __DIR__ . "/../src/models/TransportRecord.php";

if (!isset($_SESSION['user_id'])) {
    redirect('../login.php');
}

$user = new User();
$userInfo = $user->getUserById($_SESSION['user_id']);
$userRoles = $_SESSION['roles'] ?? [];

$isAdmin = hasRole('admin');
$isEmployee = hasRole('employee');
$isClient = hasRole('client');

if (!$isAdmin && !$isEmployee && !$isClient) {
    redirect('dashboard.php');
}

$userRegionId = $userInfo['region_id'] ?? null;

$node = new Node();
$segment = new Segment();
$transportRecord = new TransportRecord();

if ($isAdmin) {
    $nodes = $node->getAllNodes();
    $segments = $segment->getAllSegments();
} elseif ($isEmployee) {
    $nodes = $node->getNodesByRegion($userRegionId);
    $segments = $segment->getSegmentsByRegion($userRegionId);
} elseif ($isClient) {
    $nodes = $transportRecord->getClientNodes($_SESSION['user_id']);
    $segments = $transportRecord->getClientSegments($_SESSION['user_id']);
}

$regions = [];
if ($isAdmin) {
    $region = new Region();
    $regions = $region->getAllRegions();
}

$pageTitle = "Интерактивная Карта";
$useLeaflet = true;
include __DIR__ . "/../src/views/header.php";
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Фильтры</h5>
                </div>
                <div class="card-body">
                    <form id="mapFilterForm">
                        <?php if ($isAdmin): ?>
                        <div class="mb-3">
                            <label for="regionFilter" class="form-label">Регион:</label>
                            <select class="form-select" id="regionFilter">
                                <option value="">Все регионы</option>
                                <?php foreach ($regions as $region): ?>
                                <option value="<?= $region['id'] ?>"><?= htmlspecialchars($region['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Тип узла:</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="showWells" checked>
                                <label class="form-check-label" for="showWells">Скважины</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="showStorage" checked>
                                <label class="form-check-label" for="showStorage">Хранилища</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Метод транспортировки:</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="showPipelines" checked>
                                <label class="form-check-label" for="showPipelines">Трубопроводы</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="showRoadTransport" checked>
                                <label class="form-check-label" for="showRoadTransport">Автотранспорт</label>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="button" id="applyFilters" class="btn btn-primary">Применить фильтры</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Информация</h5>
                </div>
                <div class="card-body">
                    <div id="nodeInfo">
                        <div class="alert alert-info">
                            Выберите узел или сегмент на карте для просмотра информации.
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-map-marked-alt me-2"></i>Карта Газовой Сети</h5>
                    <span class="badge bg-light text-dark" id="roleIndicator">
                        <?php
                        if ($isAdmin) echo "Администратор";
                        elseif ($isEmployee) echo "Сотрудник - " . htmlspecialchars($userInfo['region_name'] ?? '');
                        elseif ($isClient) echo "Клиент";
                        ?>
                    </span>
                </div>
                <div class="card-body p-0">
                    <div id="map" style="height: 700px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js" integrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA==" crossorigin=""></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const map = L.map('map').setView([55.7558, 37.6173], 5);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    const wellNodes = L.layerGroup().addTo(map);
    const storageNodes = L.layerGroup().addTo(map);
    
    const pipelineSegments = L.layerGroup().addTo(map);
    const roadSegments = L.layerGroup().addTo(map);
    
    const wellIcon = L.icon({
        iconUrl: '<?= $baseUrl ?>/public/img/well-icon.svg',
        iconSize: [24, 24],
        iconAnchor: [12, 12],
        popupAnchor: [0, -12]
    });
    
    const storageIcon = L.icon({
        iconUrl: '<?= $baseUrl ?>/public/img/storage-icon.svg',
        iconSize: [24, 24],
        iconAnchor: [12, 12],
        popupAnchor: [0, -12]
    });
    
    function showNodeInfo(node) {
        document.getElementById('nodeInfo').innerHTML = `
            <h6>${node.name}</h6>
            <p><strong>Тип:</strong> ${node.type_name}</p>
            <p><strong>Регион:</strong> ${node.region_name}</p>
            <div id="nodeReceivers">
                <p><i class="fas fa-spinner fa-spin"></i> Загрузка получателей...</p>
            </div>
        `;
        
        fetch('<?= $baseUrl ?>/api/get_node_receivers.php?node_id=' + node.id)
            .then(response => response.json())
            .then(receivers => {
                let receiversHtml = '<p><strong>Получатели:</strong></p>';
                if (receivers.length > 0) {
                    receiversHtml += '<ul class="list-unstyled">';
                    receivers.forEach(receiver => {
                        receiversHtml += `<li><small>${receiver.username} (${receiver.transport_count} поставок)</small></li>`;
                    });
                    receiversHtml += '</ul>';
                } else {
                    receiversHtml += '<small class="text-muted">Нет данных о получателях</small>';
                }
                document.getElementById('nodeReceivers').innerHTML = receiversHtml;
            })
            .catch(error => {
                document.getElementById('nodeReceivers').innerHTML = '<small class="text-danger">Ошибка загрузки получателей</small>';
            });
    }
    
    function showSegmentInfo(segment) {
        document.getElementById('nodeInfo').innerHTML = `
            <h6>Маршрут</h6>
            <p><strong>Откуда:</strong> ${segment.from_node_name}</p>
            <p><strong>Куда:</strong> ${segment.to_node_name}</p>
            <p><strong>Метод:</strong> ${segment.method_name}</p>
        `;
    }
    
    <?php
    if (empty($nodes)) {
        echo "const nodes = [];";
    } else {
        echo "const nodes = " . json_encode($nodes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";";
    }
    ?>
    const nodeMarkers = {};
    
    nodes.forEach(node => {
        const coordinates = JSON.parse(node.location_json);

        const marker = L.marker([coordinates.coordinates[1], coordinates.coordinates[0]], {
            icon: node.type_name === 'Well' ? wellIcon : storageIcon
        }).bindPopup(`<b>${node.name}</b><br>${node.type_name}`);
        
        if (node.type_name === 'Well') {
            wellNodes.addLayer(marker);
        } else {
            storageNodes.addLayer(marker);
        }
        
        nodeMarkers[node.id] = marker;
        
        marker.on('click', () => showNodeInfo(node));
    });
    
    const segments = <?= json_encode($segments) ?>;
    
    segments.forEach(segment => {
        const lineJson = JSON.parse(segment.geometry_json);
        const coordinates = lineJson.coordinates.map(coord => [coord[1], coord[0]]);
        
        const lineStyle = segment.method_name === 'Pipeline'
            ? { color: '#3388ff', weight: 3, opacity: 0.7 } 
            : { color: '#ff6b6b', weight: 3, opacity: 0.7, dashArray: '5, 5' };
        
        const polyline = L.polyline(coordinates, lineStyle)
            .bindPopup(`${segment.from_node_name} → ${segment.to_node_name}`);
        
        if (segment.method_name === 'Pipeline') {
            pipelineSegments.addLayer(polyline);
        } else {
            roadSegments.addLayer(polyline);
        }
        
        polyline.on('click', () => showSegmentInfo(segment));
    });
    
    if (nodes.length > 0) {
        const bounds = [];
        nodes.forEach(node => {
            const coordinates = JSON.parse(node.location_json);
            bounds.push([coordinates.coordinates[1], coordinates.coordinates[0]]);
        });
        map.fitBounds(bounds);
    }
    
    document.getElementById('applyFilters').addEventListener('click', function() {
        if (document.getElementById('showWells').checked) {
            wellNodes.addTo(map);
        } else {
            map.removeLayer(wellNodes);
        }
        
        if (document.getElementById('showStorage').checked) {
            storageNodes.addTo(map);
        } else {
            map.removeLayer(storageNodes);
        }

        if (document.getElementById('showPipelines').checked) {
            pipelineSegments.addTo(map);
        } else {
            map.removeLayer(pipelineSegments);
        }
        
        if (document.getElementById('showRoadTransport').checked) {
            roadSegments.addTo(map);
        } else {
            map.removeLayer(roadSegments);
        }
        
        const regionFilter = document.getElementById('regionFilter');
        if (regionFilter) {
            const selectedRegion = regionFilter.value;
            
            nodes.forEach(node => {
                const marker = nodeMarkers[node.id];
                
                if (!selectedRegion || node.region_id === parseInt(selectedRegion)) {
                    if (node.type_name === 'Well' && document.getElementById('showWells').checked) {
                        wellNodes.addLayer(marker);
                    } else if (node.type_name === 'Storage' && document.getElementById('showStorage').checked) {
                        storageNodes.addLayer(marker);
                    }
                } else {
                    if (node.type_name === 'Well') {
                        wellNodes.removeLayer(marker);
                    } else {
                        storageNodes.removeLayer(marker);
                    }
                }
            });
        }
    });
});
</script>

<?php include __DIR__ . "/../src/views/footer.php"; ?> 