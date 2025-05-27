document.addEventListener('DOMContentLoaded', function() {
    // Enable all Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Enable all Bootstrap popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Auto-hide alerts after 5 seconds
    const autoHideAlerts = document.querySelectorAll('.alert-dismissible:not(.alert-persistent)');
    autoHideAlerts.forEach(function(alert) {
        setTimeout(function() {
            const closeButton = alert.querySelector('.btn-close');
            if (closeButton) {
                closeButton.click();
            } else {
                alert.classList.remove('show');
                setTimeout(function() {
                    alert.remove();
                }, 150);
            }
        }, 5000);
    });
    
    // Handle bulk delete checkboxes
    const bulkCheckboxes = document.querySelectorAll('.bulk-checkbox');
    const selectAllCheckbox = document.getElementById('select-all');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checked = this.checked;
            bulkCheckboxes.forEach(function(checkbox) {
                checkbox.checked = checked;
            });
            updateBulkActionState();
        });
    }
    
    bulkCheckboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            updateBulkActionState();
        });
    });
    
    function updateBulkActionState() {
        const bulkActionButtons = document.querySelectorAll('.bulk-action');
        const checkedCount = document.querySelectorAll('.bulk-checkbox:checked').length;
        
        bulkActionButtons.forEach(function(button) {
            button.disabled = checkedCount === 0;
            
            if (button.dataset.countLabel) {
                const label = button.querySelector(button.dataset.countLabel);
                if (label) {
                    label.textContent = checkedCount;
                }
            }
        });
        
        if (selectAllCheckbox) {
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < bulkCheckboxes.length;
        }
    }
    
    // Confirmation dialogs
    const confirmForms = document.querySelectorAll('form[data-confirm]');
    confirmForms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const message = this.dataset.confirm || 'Are you sure you want to proceed?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // Date range pickers
    const dateRangePickers = document.querySelectorAll('.date-range-picker');
    dateRangePickers.forEach(function(picker) {
        const startInput = document.getElementById(picker.dataset.startInput);
        const endInput = document.getElementById(picker.dataset.endInput);
        
        if (startInput && endInput) {
            startInput.addEventListener('change', function() {
                endInput.min = this.value;
            });
            
            endInput.addEventListener('change', function() {
                startInput.max = this.value;
            });
        }
    });
}); 