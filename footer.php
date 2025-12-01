    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Global JavaScript Utilities -->
    <script>
        // Toggle mobile sidebar
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('active');
        }
        
        // Show toast notification
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) return;
            
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            
            const bgClasses = {
                success: 'bg-success',
                error: 'bg-danger',
                warning: 'bg-warning',
                info: 'bg-primary'
            };
            
            const toast = document.createElement('div');
            toast.className = `toast show align-items-center text-white ${bgClasses[type] || bgClasses.info} border-0`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas ${icons[type] || icons.info} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }
        
        // Copy to clipboard utility
        function copyToClipboard(text, successMessage = 'Скопировано!') {
            navigator.clipboard.writeText(text).then(() => {
                showToast(successMessage, 'success');
            }).catch(() => {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.opacity = '0';
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    showToast(successMessage, 'success');
                } catch (err) {
                    showToast('Ошибка копирования', 'error');
                }
                document.body.removeChild(textArea);
            });
        }
        
        // Format numbers with spaces
        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        }
        
        // Confirm delete action
        function confirmDelete(message = 'Вы уверены, что хотите удалить?') {
            return confirm(message);
        }
        
        // Generic API request helper
        async function apiRequest(url, data = {}, method = 'POST') {
            try {
                const options = {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                    }
                };
                
                if (method !== 'GET') {
                    options.body = JSON.stringify(data);
                }
                
                const response = await fetch(url, options);
                const result = await response.json();
                
                if (!result.success) {
                    showToast(result.error || 'Произошла ошибка', 'error');
                }
                
                return result;
            } catch (error) {
                showToast('Ошибка сети: ' + error.message, 'error');
                return { success: false, error: error.message };
            }
        }
        
        // API request with form data helper
        async function apiFormRequest(url, formData) {
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: formData.toString()
                });
                return await response.json();
            } catch (error) {
                showToast('Ошибка сети: ' + error.message, 'error');
                return { success: false, error: error.message };
            }
        }
        
        // Loading overlay
        function showLoading(element = null) {
            if (element) {
                element.classList.add('loading');
                element.setAttribute('disabled', 'true');
            }
            
            let overlay = document.getElementById('loadingOverlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'loadingOverlay';
                overlay.innerHTML = `
                    <div class="position-fixed top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center" 
                         style="background: rgba(0,0,0,0.3); z-index: 9998;">
                        <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Загрузка...</span>
                        </div>
                    </div>
                `;
                document.body.appendChild(overlay);
            }
            overlay.style.display = 'block';
        }
        
        function hideLoading(element = null) {
            if (element) {
                element.classList.remove('loading');
                element.removeAttribute('disabled');
            }
            
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Initialize Bootstrap popovers
            const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function(popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
            
            // Auto-dismiss alerts
            setTimeout(() => {
                document.querySelectorAll('.alert-dismissible:not(.alert-permanent)').forEach(alert => {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
        
        // Close sidebar on mobile when clicking outside
        document.addEventListener('click', function(e) {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar && sidebar.classList.contains('active') && window.innerWidth < 768) {
                if (!sidebar.contains(e.target) && !e.target.closest('[onclick="toggleSidebar()"]')) {
                    sidebar.classList.remove('active');
                }
            }
        });
    </script>
    
    <!-- Page-specific scripts -->
    <?php if (isset($pageScripts)): ?>
    <script><?php echo $pageScripts; ?></script>
    <?php endif; ?>
</body>
</html>