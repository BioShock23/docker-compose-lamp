<?php
session_start();


require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../lib/utilities.php";
require_once __DIR__ . "/../src/models/Node.php";

if (!isset($_SESSION['user_id'])) {
    redirect('../login.php');
}

if (hasRole('client')) {
    redirect('pages/dashboard.php');
}

$pageTitle = "Управление Узлами";
$useLeaflet = true;
$nodeModel = new Node();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = sanitizeInput($_POST['name'] ?? '');
        $regionId = (int)($_POST['region_id'] ?? 0);
        $nodeTypeId = (int)($_POST['node_type_id'] ?? 0);
        $lat = (float)($_POST['lat'] ?? 0);
        $lng = (float)($_POST['lng'] ?? 0);
        
        if ($nodeModel->create($name, $regionId, $nodeTypeId, $lat, $lng)) {
            setFlashMessage('success', 'Узел успешно создан');
        } else {
            setFlashMessage('error', 'Ошибка при создании узла');
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = sanitizeInput($_POST['name'] ?? '');
        $regionId = (int)($_POST['region_id'] ?? 0);
        $nodeTypeId = (int)($_POST['node_type_id'] ?? 0);
        $lat = (float)($_POST['lat'] ?? 0);
        $lng = (float)($_POST['lng'] ?? 0);
        
        if ($nodeModel->update($id, $name, $regionId, $nodeTypeId, $lat, $lng)) {
            setFlashMessage('success', 'Узел успешно обновлен');
        } else {
            setFlashMessage('error', 'Ошибка при обновлении узла');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        if ($nodeModel->delete($id)) {
            setFlashMessage('success', 'Узел успешно удален');
        } else {
            setFlashMessage('error', 'Ошибка при удалении узла');
        }
    }
    
    redirect('nodes.php');
}

$nodes = $nodeModel->getAll();
$regions = $nodeModel->getRegions();
$nodeTypes = $nodeModel->getNodeTypes();

$flashMessages = getFlashMessages();
include __DIR__ . "/../src/views/header.php";
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Управление Узлами</h1>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/pages/dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Назад к панели
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nodeModal">
            <i class="fas fa-plus me-2"></i>Добавить Узел
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Регион</th>
                        <th>Тип</th>
                        <th>Координаты</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nodes as $node): ?>
                        <tr>
                            <td><?= $node['id'] ?></td>
                            <td><?= htmlspecialchars($node['name']) ?></td>
                            <td><?= htmlspecialchars($node['region_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($node['type_name'] ?? 'N/A') ?></td>
                            <td><?= $node['lat'] ?>, <?= $node['lng'] ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary edit-btn" 
                                        data-id="<?= $node['id'] ?>"
                                        data-name="<?= htmlspecialchars($node['name']) ?>"
                                        data-region-id="<?= $node['region_id'] ?>"
                                        data-node-type-id="<?= $node['node_type_id'] ?>"
                                        data-lat="<?= $node['lat'] ?>"
                                        data-lng="<?= $node['lng'] ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $node['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" 
                                            onclick="return confirm('Вы уверены?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="nodeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="nodeForm">
                <div class="modal-header">
                    <h5 class="modal-title">Узел</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="nodeId">
                    <input type="hidden" name="lat" id="lat">
                    <input type="hidden" name="lng" id="lng">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Название</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="region_id" class="form-label">Регион</label>
                        <select class="form-select" id="region_id" name="region_id" required>
                            <option value="">Выберите регион</option>
                            <?php foreach ($regions as $region): ?>
                                <option value="<?= $region['id'] ?>"><?= htmlspecialchars($region['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="node_type_id" class="form-label">Тип узла</label>
                        <select class="form-select" id="node_type_id" name="node_type_id" required>
                            <option value="">Выберите тип</option>
                            <?php foreach ($nodeTypes as $type): ?>
                                <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Выберите местоположение на карте</label>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Кликните на карте, чтобы выбрать координаты узла
                        </div>
                        <div id="nodeMap" style="height: 400px; border: 1px solid #ddd; border-radius: 0.375rem;"></div>
                        <div class="mt-2">
                            <small class="text-muted" id="coordinatesDisplay">Координаты не выбраны</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary" id="saveNodeBtn" disabled>Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let nodeMap;
    let selectedMarker;
    let selectedLat = null;
    let selectedLng = null;
    
    const editButtons = document.querySelectorAll('.edit-btn');
    const nodeModal = document.getElementById('nodeModal');
    const nodeForm = document.getElementById('nodeForm');
    const saveBtn = document.getElementById('saveNodeBtn');
    const coordinatesDisplay = document.getElementById('coordinatesDisplay');
    
    function initMap() {
        if (nodeMap) {
            nodeMap.remove();
        }
        
        nodeMap = L.map('nodeMap').setView([55.7558, 37.6173], 5);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(nodeMap);
        
        nodeMap.on('click', function(e) {
            setSelectedLocation(e.latlng.lat, e.latlng.lng);
        });
    }
    
    function setSelectedLocation(lat, lng) {
        selectedLat = lat;
        selectedLng = lng;
        
        if (selectedMarker) {
            nodeMap.removeLayer(selectedMarker);
        }
        
        selectedMarker = L.marker([selectedLat, selectedLng]).addTo(nodeMap);
        
        document.getElementById('lat').value = selectedLat;
        document.getElementById('lng').value = selectedLng;
        
        coordinatesDisplay.textContent = `Широта: ${selectedLat.toFixed(6)}, Долгота: ${selectedLng.toFixed(6)}`;
        
        saveBtn.disabled = false;
    }
    
    nodeModal.addEventListener('shown.bs.modal', function() {
        setTimeout(() => {
            initMap();
            if (selectedLat && selectedLng) {
                nodeMap.setView([selectedLat, selectedLng], 10);
                selectedMarker = L.marker([selectedLat, selectedLng]).addTo(nodeMap);
                coordinatesDisplay.textContent = `Широта: ${selectedLat.toFixed(6)}, Долгота: ${selectedLng.toFixed(6)}`;
                saveBtn.disabled = false;
            }
        }, 100);
    });
    
    nodeModal.addEventListener('hidden.bs.modal', function() {
        if (nodeMap) {
            nodeMap.remove();
            nodeMap = null;
        }
        selectedMarker = null;
        selectedLat = null;
        selectedLng = null;
        saveBtn.disabled = true;
        coordinatesDisplay.textContent = 'Координаты не выбраны';
        
        document.getElementById('formAction').value = 'create';
        document.getElementById('nodeId').value = '';
        document.getElementById('name').value = '';
        document.getElementById('region_id').value = '';
        document.getElementById('node_type_id').value = '';
        document.getElementById('lat').value = '';
        document.getElementById('lng').value = '';
    });
    
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('formAction').value = 'update';
            document.getElementById('nodeId').value = this.dataset.id;
            document.getElementById('name').value = this.dataset.name;
            document.getElementById('region_id').value = this.dataset.regionId;
            document.getElementById('node_type_id').value = this.dataset.nodeTypeId;
            
            selectedLat = parseFloat(this.dataset.lat);
            selectedLng = parseFloat(this.dataset.lng);
            document.getElementById('lat').value = selectedLat;
            document.getElementById('lng').value = selectedLng;
            
            new bootstrap.Modal(nodeModal).show();
        });
    });
    
    nodeForm.addEventListener('submit', function(e) {
        if (!selectedLat || !selectedLng) {
            e.preventDefault();
            alert('Пожалуйста, выберите местоположение на карте');
        }
    });
});
</script>

<?php include __DIR__ . "/../src/views/footer.php"; ?> 