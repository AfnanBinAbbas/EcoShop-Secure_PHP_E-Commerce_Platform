// Admin Panel JavaScript

class AdminPanel {
    constructor() {
        this.currentUser = null;
        this.currentSection = 'dashboard';
        this.init();
    }

    init() {
        this.bindEvents();
        this.checkAuthStatus();
    }

    bindEvents() {
        // Login form
        $('#admin-login-form').on('submit', (e) => this.handleLogin(e));
        
        // Logout button
        $('#admin-logout').on('click', () => this.handleLogout());
        
        // Navigation
        $('.nav-link').on('click', (e) => this.handleNavigation(e));
        
        // Product management
        $('#add-product-btn').on('click', () => this.showProductModal());
        $('#product-form').on('submit', (e) => this.handleProductSubmit(e));
        $('#cancel-product').on('click', () => this.hideProductModal());
        $('.close').on('click', () => this.hideProductModal());
        
        // Modal close on outside click
        $(window).on('click', (e) => {
            if ($(e.target).hasClass('modal')) {
                this.hideProductModal();
            }
        });
    }

    async checkAuthStatus() {
        try {
            const response = await fetch('../api/auth.php', {
                credentials: 'include'
            });
            const data = await response.json();
            
            if (data.success && data.data.is_admin) {
                this.currentUser = data.data;
                this.showDashboard();
                this.loadDashboardData();
            } else {
                this.showLogin();
            }
        } catch (error) {
            console.error('Auth check failed:', error);
            this.showLogin();
        }
    }

    async handleLogin(e) {
        e.preventDefault();
        
        const email = $('#admin-email').val();
        const password = $('#admin-password').val();
        
        try {
            const response = await fetch('../api/auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'login',
                    email: email,
                    password: password
                })
            });
            
            const data = await response.json();
            
            if (data.success && data.data.is_admin) {
                this.currentUser = data.data;
                this.showDashboard();
                this.loadDashboardData();
                $('#admin-login-error').hide();
            } else {
                $('#admin-login-error').text('Invalid credentials or insufficient privileges').show();
            }
        } catch (error) {
            console.error('Login failed:', error);
            $('#admin-login-error').text('Login failed. Please try again.').show();
        }
    }

    async handleLogout() {
        try {
            await fetch('../api/auth.php', {
                method: 'DELETE',
                credentials: 'include'
            });
            
            this.currentUser = null;
            this.showLogin();
        } catch (error) {
            console.error('Logout failed:', error);
        }
    }

    showLogin() {
        $('#admin-login').show();
        $('#admin-dashboard').hide();
        $('#admin-email').val('');
        $('#admin-password').val('');
        $('#admin-login-error').hide();
    }

    showDashboard() {
        $('#admin-login').hide();
        $('#admin-dashboard').show();
        $('#admin-user-name').text(this.currentUser.name);
    }

    handleNavigation(e) {
        e.preventDefault();
        
        const section = $(e.target).data('section');
        if (!section) return;
        
        // Update active nav link
        $('.nav-link').removeClass('active');
        $(e.target).addClass('active');
        
        // Show corresponding section
        $('.admin-section').removeClass('active');
        $(`#${section}-section`).addClass('active');
        
        this.currentSection = section;
        
        // Load section data
        switch (section) {
            case 'dashboard':
                this.loadDashboardData();
                break;
            case 'products':
                this.loadProducts();
                break;
            case 'orders':
                this.loadOrders();
                break;
            case 'users':
                this.loadUsers();
                break;
        }
    }

    async loadDashboardData() {
        try {
            // Load products count
            const productsResponse = await fetch('../api/products.php', {
                credentials: 'include'
            });
            const productsData = await productsResponse.json();
            if (productsData.success) {
                $('#total-products').text(productsData.data.length);
            }
            
            // Load orders count
            const ordersResponse = await fetch('../api/orders.php', {
                credentials: 'include'
            });
            const ordersData = await ordersResponse.json();
            if (ordersData.success) {
                $('#total-orders').text(ordersData.data.length);
                
                // Calculate total revenue
                const totalRevenue = ordersData.data.reduce((sum, order) => {
                    return sum + (order.status !== 'cancelled' ? parseFloat(order.total) : 0);
                }, 0);
                $('#total-revenue').text(`$${totalRevenue.toFixed(2)}`);
            }
            
            // Load users count
            const usersResponse = await fetch('../api/users.php', {
                credentials: 'include'
            });
            const usersData = await usersResponse.json();
            if (usersData.success) {
                $('#total-users').text(usersData.data.length);
            }
            
        } catch (error) {
            console.error('Failed to load dashboard data:', error);
        }
    }

    async loadProducts() {
        try {
            const response = await fetch('../api/products.php', {
                credentials: 'include'
            });
            const data = await response.json();
            
            if (data.success) {
                this.renderProductsTable(data.data);
            }
        } catch (error) {
            console.error('Failed to load products:', error);
        }
    }

    renderProductsTable(products) {
        const tbody = $('#products-table-body');
        tbody.empty();
        
        products.forEach(product => {
            const row = `
                <tr>
                    <td>${product.id}</td>
                    <td><img src="../${product.image}" alt="${product.name}" onerror="this.src='../images/placeholder.jpg'"></td>
                    <td>${product.name}</td>
                    <td>$${parseFloat(product.price).toFixed(2)}</td>
                    <td>${product.category}</td>
                    <td>
                        <span class="status-badge ${product.in_stock ? 'status-delivered' : 'status-cancelled'}">
                            ${product.in_stock ? 'In Stock' : 'Out of Stock'}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="adminPanel.editProduct(${product.id})">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="adminPanel.deleteProduct(${product.id})">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    async loadOrders() {
        try {
            const response = await fetch('../api/orders.php', {
                credentials: 'include'
            });
            const data = await response.json();
            
            if (data.success) {
                this.renderOrdersTable(data.data);
            }
        } catch (error) {
            console.error('Failed to load orders:', error);
        }
    }

    renderOrdersTable(orders) {
        const tbody = $('#orders-table-body');
        tbody.empty();
        
        orders.forEach(order => {
            const customerName = order.user_name || 'Unknown';
            const customerEmail = order.user_email || '';
            const date = new Date(order.created_at).toLocaleDateString();
            
            const row = `
                <tr>
                    <td>#${order.id}</td>
                    <td>
                        <div>${customerName}</div>
                        <small class="text-muted">${customerEmail}</small>
                    </td>
                    <td>$${parseFloat(order.total).toFixed(2)}</td>
                    <td>
                        <span class="status-badge status-${order.status}">
                            ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                        </span>
                    </td>
                    <td>${date}</td>
                    <td>
                        <select class="order-status-select" data-order-id="${order.id}" onchange="adminPanel.updateOrderStatus(${order.id}, this.value)">
                            <option value="pending" ${order.status === 'pending' ? 'selected' : ''}>Pending</option>
                            <option value="processing" ${order.status === 'processing' ? 'selected' : ''}>Processing</option>
                            <option value="shipped" ${order.status === 'shipped' ? 'selected' : ''}>Shipped</option>
                            <option value="delivered" ${order.status === 'delivered' ? 'selected' : ''}>Delivered</option>
                            <option value="cancelled" ${order.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                        </select>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    async loadUsers() {
        try {
            const response = await fetch('../api/users.php', {
                credentials: 'include'
            });
            const data = await response.json();
            
            if (data.success) {
                this.renderUsersTable(data.data);
            } else {
                this.showMessage('Failed to load users', 'error');
            }
        } catch (error) {
            console.error('Failed to load users:', error);
            this.showMessage('Failed to load users', 'error');
        }
    }

    renderUsersTable(users) {
        const tbody = $('#users-table-body');
        tbody.empty();
        
        if (users.length === 0) {
            tbody.html('<tr><td colspan="6" class="text-center">No users found</td></tr>');
            return;
        }
        
        users.forEach(user => {
            const date = user.created_at || 'N/A';
            const lastLogin = user.last_login || 'Never';
            const statusClass = user.is_active ? 'status-delivered' : 'status-cancelled';
            const statusText = user.is_active ? 'Active' : 'Inactive';
            
            const row = `
                <tr>
                    <td>${user.id}</td>
                    <td>${user.name}</td>
                    <td>${user.email}</td>
                    <td>
                        <span class="status-badge ${user.is_admin ? 'status-delivered' : 'status-pending'}">
                            ${user.is_admin ? 'Admin' : 'User'}
                        </span>
                    </td>
                    <td>
                        <span class="status-badge ${statusClass}">
                            ${statusText}
                        </span>
                    </td>
                    <td>${date}</td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="adminPanel.editUser(${user.id})">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-sm ${user.is_active ? 'btn-danger' : 'btn-success'}" 
                                onclick="adminPanel.toggleUserStatus(${user.id}, ${!user.is_active})">
                            <i class="fas ${user.is_active ? 'fa-ban' : 'fa-check'}"></i> 
                            ${user.is_active ? 'Deactivate' : 'Activate'}
                        </button>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    showProductModal(product = null) {
        if (product) {
            $('#product-modal-title').text('Edit Product');
            $('#product-name').val(product.name);
            $('#product-price').val(product.price);
            $('#product-category').val(product.category);
            $('#product-description').val(product.description);
            $('#product-image').val(product.image);
            $('#product-discount').val(product.discount);
            $('#product-in-stock').prop('checked', product.in_stock);
            $('#product-form').data('product-id', product.id);
        } else {
            $('#product-modal-title').text('Add Product');
            $('#product-form')[0].reset();
            $('#product-form').removeData('product-id');
        }
        
        $('#product-modal').show();
    }

    hideProductModal() {
        $('#product-modal').hide();
    }

    async handleProductSubmit(e) {
        e.preventDefault();
        
        const formData = {
            name: $('#product-name').val(),
            price: parseFloat($('#product-price').val()),
            category: $('#product-category').val(),
            description: $('#product-description').val(),
            image: $('#product-image').val(),
            discount: parseInt($('#product-discount').val()) || 0,
            in_stock: $('#product-in-stock').is(':checked')
        };
        
        const productId = $('#product-form').data('product-id');
        const isEdit = !!productId;
        
        if (isEdit) {
            formData.id = productId;
        }
        
        try {
            const response = await fetch('../api/products.php', {
                method: isEdit ? 'PUT' : 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify(formData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.hideProductModal();
                this.loadProducts();
                this.showMessage(`Product ${isEdit ? 'updated' : 'created'} successfully!`, 'success');
            } else {
                this.showMessage(data.message || 'Operation failed', 'error');
            }
        } catch (error) {
            console.error('Product operation failed:', error);
            this.showMessage('Operation failed. Please try again.', 'error');
        }
    }

    async editProduct(productId) {
        try {
            const response = await fetch('../api/products.php', {
                credentials: 'include'
            });
            const data = await response.json();
            
            if (data.success) {
                const product = data.data.find(p => p.id === productId);
                if (product) {
                    this.showProductModal(product);
                }
            }
        } catch (error) {
            console.error('Failed to load product:', error);
        }
    }

    async deleteProduct(productId) {
        if (!confirm('Are you sure you want to delete this product?')) {
            return;
        }
        
        try {
            const response = await fetch('../api/products.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({ id: productId })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.loadProducts();
                this.showMessage('Product deleted successfully!', 'success');
            } else {
                this.showMessage(data.message || 'Delete failed', 'error');
            }
        } catch (error) {
            console.error('Delete failed:', error);
            this.showMessage('Delete failed. Please try again.', 'error');
        }
    }

    async updateOrderStatus(orderId, newStatus) {
        try {
            const response = await fetch('../api/orders.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({
                    id: orderId,
                    status: newStatus
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showMessage('Order status updated successfully!', 'success');
                this.loadOrders();
            } else {
                this.showMessage(data.message || 'Update failed', 'error');
            }
        } catch (error) {
            console.error('Status update failed:', error);
            this.showMessage('Update failed. Please try again.', 'error');
        }
    }

    async toggleUserStatus(userId, newStatus) {
        if (!confirm(`Are you sure you want to ${newStatus ? 'activate' : 'deactivate'} this user?`)) {
            return;
        }

        try {
            const response = await fetch('../api/users.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({
                    id: userId,
                    is_active: newStatus
                })
            });

            const data = await response.json();

            if (data.success) {
                this.loadUsers(); // Reload users to reflect status change
                this.showMessage(`User ${newStatus ? 'activated' : 'deactivated'} successfully!`, 'success');
            } else {
                this.showMessage(data.message || 'Status update failed', 'error');
            }
        } catch (error) {
            console.error('Status toggle failed:', error);
            this.showMessage('Status update failed. Please try again.', 'error');
        }
    }

    editUser(userId) {
        // Placeholder for user editing functionality
        alert('User editing functionality coming soon!');
    }

    showMessage(message, type) {
        // Create a temporary message element
        const messageEl = $(`<div class="${type}-message" style="position: fixed; top: 20px; right: 20px; z-index: 1001; padding: 1rem; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.2);">${message}</div>`);
        
        $('body').append(messageEl);
        messageEl.show();
        
        setTimeout(() => {
            messageEl.fadeOut(() => messageEl.remove());
        }, 3000);
    }
}

// Initialize admin panel when document is ready
$(document).ready(() => {
    window.adminPanel = new AdminPanel();
});

