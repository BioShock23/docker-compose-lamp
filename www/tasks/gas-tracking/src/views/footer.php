    </main>
    <footer class="py-3 mt-4 bg-light">
        <div class="container text-center">
            <p class="mb-1">Система Учета Газа &copy; <?= date('Y') ?></p>
            <p class="mb-0 text-muted">Информационная система для мониторинга транспортировки газа</p>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (isset($useLeaflet) && $useLeaflet): ?>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <?php endif; ?>
    <script src="./public/js/main.js"></script>
</body>
</html> 