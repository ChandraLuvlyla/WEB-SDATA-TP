// script.js - JavaScript untuk interaksi

document.addEventListener('DOMContentLoaded', function() {
    // Tab functionality for login page
    const tabBtns = document.querySelectorAll('.tab-btn');
    const formContainers = document.querySelectorAll('.form-container');
    
    if (tabBtns.length > 0) {
        tabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Update active tab button
                tabBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Show active form
                formContainers.forEach(container => {
                    container.classList.remove('active');
                    if (container.id === `${tabId}-form`) {
                        container.classList.add('active');
                    }
                });
            });
        });
    }
    
    // Cart quantity buttons
    const qtyButtons = document.querySelectorAll('.qty-btn');
    qtyButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('form');
            const qtyInput = form.querySelector('input[name="quantity"]');
            let currentQty = parseInt(qtyInput.value);
            
            if (this.textContent.includes('-')) {
                qtyInput.value = Math.max(0, currentQty - 1);
            } else {
                qtyInput.value = Math.min(10, currentQty + 1);
            }
            
            // Auto-submit form
            form.submit();
        });
    });
    
    // Auto-submit cart update forms
    const qtyInputs = document.querySelectorAll('input[name="quantity"]');
    qtyInputs.forEach(input => {
        input.addEventListener('change', function() {
            const form = this.closest('form');
            if (form) {
                form.submit();
            }
        });
    });
    
    // Add smooth animations
    const menuItems = document.querySelectorAll('.menu-item');
    menuItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredInputs = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredInputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.style.borderColor = '#ff6b6b';
                    
                    // Add error message
                    if (!input.nextElementSibling || !input.nextElementSibling.classList.contains('error-message')) {
                        const errorMsg = document.createElement('div');
                        errorMsg.className = 'error-message';
                        errorMsg.style.color = '#ff6b6b';
                        errorMsg.style.fontSize = '12px';
                        errorMsg.style.marginTop = '5px';
                        errorMsg.textContent = 'Field ini wajib diisi!';
                        input.parentNode.appendChild(errorMsg);
                    }
                } else {
                    input.style.borderColor = '';
                    const errorMsg = input.parentNode.querySelector('.error-message');
                    if (errorMsg) {
                        errorMsg.remove();
                    }
                }
            });
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    });
    
    // Search form enhancement
    const searchTypeSelect = document.getElementById('search_type');
    const searchTermInput = document.getElementById('search_term');
    
    if (searchTypeSelect && searchTermInput) {
        searchTypeSelect.addEventListener('change', function() {
            const searchType = this.value;
            
            // Update placeholder based on search type
            switch(searchType) {
                case 'order_number':
                    searchTermInput.placeholder = 'Masukkan nomor pesanan (contoh: 1001)';
                    searchTermInput.pattern = '[0-9]+';
                    break;
                case 'customer_name':
                    searchTermInput.placeholder = 'Masukkan nama pelanggan';
                    searchTermInput.pattern = null;
                    break;
                case 'customer_kode':
                    searchTermInput.placeholder = 'Masukkan kode pelanggan';
                    searchTermInput.pattern = '[A-Za-z0-9]{4,20}';
                    break;
                case 'date':
                    searchTermInput.placeholder = 'Masukkan tanggal (YYYY-MM-DD)';
                    searchTermInput.pattern = '[0-9]{4}-[0-9]{2}-[0-9]{2}';
                    break;
            }
        });
    }
    
    // Auto-refresh cart count
    function updateCartCount() {
        const cartCounts = document.querySelectorAll('.cart-count');
        if (cartCounts.length > 0) {
            // In a real application, you would fetch this from the server
            // For now, we'll use a placeholder
            cartCounts.forEach(count => {
                count.textContent = document.querySelectorAll('.cart-item').length || 0;
            });
        }
    }
    
    // Initialize cart count
    updateCartCount();
    
    // Add item to cart animation
    const addToCartButtons = document.querySelectorAll('[name="add_to_cart"]');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const form = this.closest('form');
            const itemName = form.closest('.menu-item').querySelector('.menu-item-title').textContent;
            
            // Show notification
            showNotification(`${itemName} ditambahkan ke keranjang!`, 'success');
        });
    });
    
    // Notification function
    function showNotification(message, type = 'info') {
        // Remove existing notification
        const existingNotification = document.querySelector('.notification');
        if (existingNotification) {
            existingNotification.remove();
        }
        
        // Create notification
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        `;
        
        // Add styles
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background: ${type === 'success' ? '#36b37e' : '#ff8ba7'};
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
        
        // Add keyframes
        if (!document.querySelector('#notification-styles')) {
            const style = document.createElement('style');
            style.id = 'notification-styles';
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOut {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }
    }
    
    // Print receipt functionality
    const printButtons = document.querySelectorAll('[onclick*="print"]');
    printButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!window.print) {
                showNotification('Fungsi cetak tidak tersedia di browser ini', 'error');
                e.preventDefault();
            }
        });
    });
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
    
    // Logout confirmation
    const logoutLinks = document.querySelectorAll('a[href*="logout"]');
    logoutLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Yakin ingin logout?')) {
                e.preventDefault();
            }
        });
    });
    
    // Show toast notification
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    // Check for logout message in URL
    function checkLogoutMessage() {
        const urlParams = new URLSearchParams(window.location.search);
        const message = urlParams.get('message');
        const type = urlParams.get('type');
        
        if (message === 'logout_success') {
            let messageText = 'Anda telah berhasil logout!';
            if (type === 'admin') {
                messageText = 'Anda telah berhasil logout dari sistem admin!';
            }
            showToast(messageText, 'success');
            
            // Remove the message from URL
            const newUrl = window.location.pathname;
            window.history.replaceState({}, document.title, newUrl);
        }
    }
    
    // Check for logout message
    checkLogoutMessage();
    
    // Auto-hide success alerts after 5 seconds
    const successAlerts = document.querySelectorAll('.alert-success');
    successAlerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s ease';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
    
    // Fungsi untuk modal pembatalan
    window.showCancelModal = function(orderNumber) {
        document.getElementById('cancel_order_number').value = orderNumber;
        document.getElementById('cancelModal').style.display = 'flex';
    }
    
    window.closeCancelModal = function() {
        document.getElementById('cancelModal').style.display = 'none';
    }
    
    // Fungsi untuk modal reject
    window.showRejectModal = function(requestId, orderNumber) {
        document.getElementById('reject_request_id').value = requestId;
        document.getElementById('reject_order_number').value = orderNumber;
        document.getElementById('rejectModal').style.display = 'flex';
    }
    
    window.closeRejectModal = function() {
        document.getElementById('rejectModal').style.display = 'none';
    }
    
    // Close modal ketika klik di luar
    window.onclick = function(event) {
        const cancelModal = document.getElementById('cancelModal');
        const rejectModal = document.getElementById('rejectModal');
        
        if (cancelModal && event.target == cancelModal) {
            closeCancelModal();
        }
        
        if (rejectModal && event.target == rejectModal) {
            closeRejectModal();
        }
    }
});