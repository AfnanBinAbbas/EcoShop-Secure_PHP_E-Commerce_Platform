// EcoShop Frontend JavaScript with Enhanced Security
$(document).ready(function() {
    // Global variables
    let products = [];
    let cartItems = [];
    let currentUser = null;
    let orders = [];
    let isAuthMode = 'login';
    let csrfToken = null;
    
    // Initialize the application
    init();
    
    // Initialize auth form state
    initializeAuthForm();
    
    function init() {
        loadProducts();
        setupEventListeners();
        updateCartDisplay();
        checkAuthStatus();
        setupCSRFProtection();
    }
    
    // Setup CSRF protection
    function setupCSRFProtection() {
        // Add CSRF token to all AJAX requests
        $.ajaxSetup({
            beforeSend: function(xhr, settings) {
                if (!/^(GET|HEAD|OPTIONS|TRACE)$/i.test(settings.type) && !this.crossDomain) {
                    if (csrfToken) {
                        xhr.setRequestHeader("X-CSRF-Token", csrfToken);
                    }
                }
            }
        });
    }
    
    // Check authentication status on page load
    function checkAuthStatus() {
        $.ajax({
            url: 'api/auth.php',
            method: 'GET',
            xhrFields: {
                withCredentials: true
            },
            success: function(response) {
                if (response.success && response.data) {
                    currentUser = response.data;
                    csrfToken = response.data.csrf_token;
                    updateAuthDisplay();
                    loadUserCart();
                }
            },
            error: function() {
                // User not authenticated, continue as guest
                updateAuthDisplay();
            }
        });
    }
    
    // Event Listeners
    function setupEventListeners() {
        // Navigation
        $('.nav-link').on('click', function(e) {
            e.preventDefault();
            const target = $(this).attr('href');
            if (target.startsWith('#')) {
                scrollToSection(target);
                updateActiveNavLink($(this));
            }
        });
        
        // Shop Now button
        $('#shopNowBtn').on('click', function() {
            scrollToSection('#products');
        });
        
        // Search functionality with debouncing
        let searchTimeout;
        $('#searchInput').on('input', function() {
            clearTimeout(searchTimeout);
            const searchTerm = $(this).val().toLowerCase();
            searchTimeout = setTimeout(() => {
                filterProducts(searchTerm);
            }, 300);
        });
        
        // Category filter
        $('#categoryFilter').on('change', function() {
            filterAndSortProducts();
        });
        
        // Sort filter
        $('#sortFilter').on('change', function() {
            filterAndSortProducts();
        });
        
        // Cart button
        $('#cartBtn').on('click', function() {
            openCartModal();
        });
        
        // Auth button
        $('#authBtn').on('click', function() {
            if (currentUser) {
                logout();
            } else {
                openAuthModal();
            }
        });
        
        // Admin button
        $('#adminBtn').on('click', function() {
            if (currentUser && currentUser.is_admin) {
                window.open('admin/index.html', '_blank');
            }
        });
        
        // Orders button
        $('#ordersBtn').on('click', function() {
            openOrdersModal();
        });
        
        // Modal close buttons
        $('.modal-close').on('click', function() {
            closeModals();
        });
        
        // Close modal when clicking outside
        $('.modal').on('click', function(e) {
            if (e.target === this) {
                closeModals();
            }
        });
        
        // Auth form submission with validation
        $('#authForm').on('submit', function(e) {
            e.preventDefault();
            handleAuthSubmit();
        });
        
        // Auth mode switch (delegated event for dynamically created elements)
        $(document).on('click', '#authSwitchLink', function(e) {
            e.preventDefault();
            switchAuthMode();
        });
        
        // Continue shopping button
        $(document).on('click', '.continue-shopping-btn', function() {
            closeModals();
            scrollToSection('#products');
        });
        
        // Add to cart buttons (delegated event)
        $(document).on('click', '.add-to-cart-btn', function() {
            const productId = parseInt($(this).data('product-id'));
            addToCart(productId);
        });
        
        // Cart item quantity controls
        $(document).on('click', '.quantity-btn', function() {
            const productId = parseInt($(this).data('product-id'));
            const action = $(this).data('action');
            updateCartQuantity(productId, action);
        });
        
        // Remove from cart
        $(document).on('click', '.remove-item-btn', function() {
            const productId = parseInt($(this).data('product-id'));
            removeFromCart(productId);
        });
        
        // Checkout button
        $(document).on('click', '#checkoutBtn', function() {
            if (!currentUser) {
                showNotification('Please login to checkout', 'warning');
                openAuthModal();
                return;
            }
            proceedToCheckout();
        });
        
        // Real-time form validation
        $('#authForm input').on('blur', function() {
            validateField($(this));
        });
        
        // Password strength indicator
        $('#password').on('input', function() {
            if (isAuthMode === 'register') {
                updatePasswordStrength($(this).val());
            }
        });
    }
    
    // Enhanced form validation
    function validateField($field) {
        const fieldName = $field.attr('name');
        const value = $field.val().trim();
        let isValid = true;
        let message = '';
        
        // Remove existing error styling
        $field.removeClass('error');
        $field.next('.error-message').remove();
        
        switch (fieldName) {
            case 'email':
                const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
                if (!value) {
                    isValid = false;
                    message = 'Email is required';
                } else if (!emailRegex.test(value)) {
                    isValid = false;
                    message = 'Please enter a valid email address';
                } else if (value.length > 254) {
                    isValid = false;
                    message = 'Email is too long';
                }
                break;
                
            case 'name':
                if (isAuthMode === 'register') {
                    if (!value) {
                        isValid = false;
                        message = 'Name is required';
                    } else if (value.length < 2) {
                        isValid = false;
                        message = 'Name must be at least 2 characters';
                    } else if (value.length > 100) {
                        isValid = false;
                        message = 'Name is too long';
                    } else if (!/^[a-zA-Z\s\'-]+$/.test(value)) {
                        isValid = false;
                        message = 'Name contains invalid characters';
                    }
                }
                break;
                
            case 'password':
                if (!value) {
                    isValid = false;
                    message = 'Password is required';
                } else if (isAuthMode === 'register') {
                    const passwordValidation = validatePasswordStrength(value);
                    if (!passwordValidation.isValid) {
                        isValid = false;
                        message = passwordValidation.message;
                    }
                }
                break;
        }
        
        if (!isValid) {
            $field.addClass('error');
            $field.after(`<div class="error-message">${message}</div>`);
        }
        
        return isValid;
    }
    
    // Password strength validation
    function validatePasswordStrength(password) {
        const requirements = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[^A-Za-z0-9]/.test(password)
        };
        
        const failedRequirements = [];
        if (!requirements.length) failedRequirements.push('at least 8 characters');
        if (!requirements.uppercase) failedRequirements.push('one uppercase letter');
        if (!requirements.lowercase) failedRequirements.push('one lowercase letter');
        if (!requirements.number) failedRequirements.push('one number');
        if (!requirements.special) failedRequirements.push('one special character');
        
        return {
            isValid: failedRequirements.length === 0,
            message: failedRequirements.length > 0 ? 
                `Password must contain ${failedRequirements.join(', ')}` : '',
            strength: Object.values(requirements).filter(Boolean).length
        };
    }
    
    // Update password strength indicator
    function updatePasswordStrength(password) {
        const validation = validatePasswordStrength(password);
        const $indicator = $('#passwordStrength');
        
        if (!$indicator.length) {
            $('#password').after('<div id="passwordStrength" class="password-strength"></div>');
        }
        
        const strengthLevels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
        const strengthLevel = Math.min(validation.strength, 4);
        const strengthText = strengthLevels[strengthLevel];
        const strengthClass = ['very-weak', 'weak', 'fair', 'good', 'strong'][strengthLevel];
        
        $('#passwordStrength')
            .removeClass('very-weak weak fair good strong')
            .addClass(strengthClass)
            .html(`
                <div class="strength-bar">
                    <div class="strength-fill" style="width: ${(validation.strength / 5) * 100}%"></div>
                </div>
                <span class="strength-text">${strengthText}</span>
            `);
    }
    
    // Enhanced authentication handling
    function handleAuthSubmit() {
        const $form = $('#authForm');
        const formData = {
            action: isAuthMode,
            email: $('#email').val().trim(),
            password: $('#password').val()
        };
        
        if (isAuthMode === 'register') {
            formData.name = $('#name').val().trim();
        }
        
        // Remove disabled fields from validation
        $form.find('input:disabled').removeAttr('required');
        
        // Validate all fields
        let isFormValid = true;
        $form.find('input[required]').each(function() {
            if (!validateField($(this))) {
                isFormValid = false;
            }
        });
        
        if (!isFormValid) {
            showNotification('Please fix the errors above', 'error');
            return;
        }
        
        // Show loading state
        const $submitBtn = $('#authSubmitBtn');
        const originalText = $submitBtn.text();
        $submitBtn.prop('disabled', true).text('Processing...');
        
        $.ajax({
            url: 'api/auth.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            xhrFields: {
                withCredentials: true
            },
            success: function(response) {
                if (response.success) {
                    currentUser = response.data;
                    csrfToken = response.data.csrf_token;
                    updateAuthDisplay();
                    closeModals();
                    loadUserCart();
                    
                    const action = isAuthMode === 'login' ? 'Login' : 'Registration';
                    showNotification(`${action} successful! Welcome ${currentUser.name}`, 'success');
                } else {
                    showNotification(response.message || 'Authentication failed', 'error');
                }
            },
            error: function(xhr) {
                let message = 'Authentication failed';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                } else if (xhr.status === 429) {
                    message = 'Too many attempts. Please try again later.';
                } else if (xhr.status === 0) {
                    message = 'Network error. Please check your connection.';
                }
                showNotification(message, 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).text(originalText);
            }
        });
    }
    
    // Enhanced logout function
    function logout() {
        $.ajax({
            url: 'api/auth.php',
            method: 'DELETE',
            xhrFields: {
                withCredentials: true
            },
            success: function(response) {
                currentUser = null;
                csrfToken = null;
                cartItems = [];
                updateAuthDisplay();
                updateCartDisplay();
                showNotification('Logged out successfully', 'success');
            },
            error: function() {
                // Force logout on client side even if server request fails
                currentUser = null;
                csrfToken = null;
                cartItems = [];
                updateAuthDisplay();
                updateCartDisplay();
                showNotification('Logged out', 'info');
            }
        });
    }
    
    // Load products with error handling
    function loadProducts() {
        $.ajax({
            url: 'api/products.php',
            method: 'GET',
            xhrFields: {
                withCredentials: true
            },
            success: function(response) {
                if (response.success && response.data) {
                    products = response.data;
                    displayProducts(products);
                    populateCategories();
                } else {
                    showNotification('Failed to load products', 'error');
                }
            },
            error: function() {
                showNotification('Failed to load products. Please refresh the page.', 'error');
            }
        });
    }
    
    // Enhanced cart management
    function addToCart(productId) {
        const product = products.find(p => p.id === productId);
        if (!product) {
            showNotification('Product not found', 'error');
            return;
        }
        
        if (!product.in_stock) {
            showNotification('Product is out of stock', 'warning');
            return;
        }
        
        const existingItem = cartItems.find(item => item.id === productId);
        
        if (existingItem) {
            existingItem.quantity += 1;
        } else {
            cartItems.push({
                id: productId,
                name: product.name,
                price: product.price,
                image: product.image,
                quantity: 1,
                discount: product.discount || 0
            });
        }
        
        updateCartDisplay();
        saveCartToStorage();
        showNotification(`${product.name} added to cart`, 'success');
        
        // Add visual feedback
        const $btn = $(`.add-to-cart-btn[data-product-id="${productId}"]`);
        $btn.addClass('added');
        setTimeout(() => $btn.removeClass('added'), 1000);
    }
    
    // Update cart quantity with validation
    function updateCartQuantity(productId, action) {
        const item = cartItems.find(item => item.id === productId);
        if (!item) return;
        
        if (action === 'increase') {
            item.quantity += 1;
        } else if (action === 'decrease') {
            if (item.quantity > 1) {
                item.quantity -= 1;
            } else {
                removeFromCart(productId);
                return;
            }
        }
        
        updateCartDisplay();
        saveCartToStorage();
    }
    
    // Remove item from cart
    function removeFromCart(productId) {
        const itemIndex = cartItems.findIndex(item => item.id === productId);
        if (itemIndex > -1) {
            const itemName = cartItems[itemIndex].name;
            cartItems.splice(itemIndex, 1);
            updateCartDisplay();
            saveCartToStorage();
            showNotification(`${itemName} removed from cart`, 'info');
        }
    }
    
    // Save cart to localStorage
    function saveCartToStorage() {
        try {
            localStorage.setItem('ecoshop_cart', JSON.stringify(cartItems));
        } catch (e) {
            console.warn('Failed to save cart to localStorage:', e);
        }
    }
    
    // Load cart from localStorage
    function loadCartFromStorage() {
        try {
            const savedCart = localStorage.getItem('ecoshop_cart');
            if (savedCart) {
                cartItems = JSON.parse(savedCart);
                updateCartDisplay();
            }
        } catch (e) {
            console.warn('Failed to load cart from localStorage:', e);
            cartItems = [];
        }
    }
    
    // Load user-specific cart (when logged in)
    function loadUserCart() {
        if (currentUser) {
            // In a real application, you would load the user's cart from the server
            // For now, we'll merge localStorage cart with any server-side cart
            loadCartFromStorage();
        }
    }
    
    // Enhanced notification system
    function showNotification(message, type = 'info', duration = 5000) {
        const $notification = $(`
            <div class="notification notification-${type}">
                <div class="notification-content">
                    <i class="fas ${getNotificationIcon(type)}"></i>
                    <span>${message}</span>
                    <button class="notification-close">&times;</button>
                </div>
            </div>
        `);
        
        $('body').append($notification);
        
        // Animate in
        setTimeout(() => $notification.addClass('show'), 100);
        
        // Auto remove
        const timeout = setTimeout(() => {
            removeNotification($notification);
        }, duration);
        
        // Manual close
        $notification.find('.notification-close').on('click', function() {
            clearTimeout(timeout);
            removeNotification($notification);
        });
    }
    
    function getNotificationIcon(type) {
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        return icons[type] || icons.info;
    }
    
    function removeNotification($notification) {
        $notification.removeClass('show');
        setTimeout(() => $notification.remove(), 300);
    }
    
    // Update authentication display
    function updateAuthDisplay() {
        const $authBtn = $('#authBtn');
        const $adminBtn = $('#adminBtn');
        const $ordersBtn = $('#ordersBtn');
        
        if (currentUser) {
            $authBtn.html(`
                <i class="fas fa-user"></i>
                <span>${currentUser.name}</span>
            `);
            
            $ordersBtn.show();
            
            if (currentUser.is_admin) {
                $adminBtn.show();
            } else {
                $adminBtn.hide();
            }
        } else {
            $authBtn.html(`
                <i class="fas fa-user"></i>
                <span>Login</span>
            `);
            $ordersBtn.hide();
            $adminBtn.hide();
        }
    }
    
    // Update cart display
    function updateCartDisplay() {
        const totalItems = cartItems.reduce((sum, item) => sum + item.quantity, 0);
        $('#cartCount').text(totalItems);
        
        if (totalItems > 0) {
            $('#cartBtn').addClass('has-items');
        } else {
            $('#cartBtn').removeClass('has-items');
        }
    }
    
    // Display products with enhanced error handling
    function displayProducts(productsToShow) {
        const $container = $('#productsGrid');
        const $loading = $('#productsLoading');
        
        // Hide loading spinner
        $loading.hide();
        
        if (!productsToShow || productsToShow.length === 0) {
            $container.html(`
                <div class="no-products">
                    <i class="fas fa-search"></i>
                    <h3>No products found</h3>
                    <p>Try adjusting your search or filter criteria</p>
                </div>
            `);
            return;
        }
        
        const productsHTML = productsToShow.map(product => {
            const discountedPrice = product.discount > 0 ? 
                (product.price * (1 - product.discount / 100)).toFixed(2) : 
                product.price;
            
            return `
                <div class="product-card" data-product-id="${product.id}">
                    <div class="product-image">
                        <img src="${product.image}" alt="${product.name}" loading="lazy">
                        ${product.discount > 0 ? `<div class="discount-badge">${product.discount}% OFF</div>` : ''}
                        ${!product.in_stock ? '<div class="out-of-stock-badge">Out of Stock</div>' : ''}
                    </div>
                    <div class="product-info">
                        <h3 class="product-name">${product.name}</h3>
                        <p class="product-description">${product.description}</p>
                        <div class="product-rating">
                            ${generateStarRating(product.rating)}
                            <span class="rating-text">(${product.rating})</span>
                        </div>
                        <div class="product-price">
                            ${product.discount > 0 ? 
                                `<span class="original-price">$${product.price}</span>
                                 <span class="discounted-price">$${discountedPrice}</span>` :
                                `<span class="price">$${product.price}</span>`
                            }
                        </div>
                        <button class="add-to-cart-btn ${!product.in_stock ? 'disabled' : ''}" 
                                data-product-id="${product.id}" 
                                ${!product.in_stock ? 'disabled' : ''}>
                            <i class="fas fa-shopping-cart"></i>
                            ${product.in_stock ? 'Add to Cart' : 'Out of Stock'}
                        </button>
                    </div>
                </div>
            `;
        }).join('');
        
        $container.html(productsHTML);
    }
    
    // Generate star rating HTML
    function generateStarRating(rating) {
        const fullStars = Math.floor(rating);
        const hasHalfStar = rating % 1 !== 0;
        const emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);
        
        let starsHTML = '';
        
        // Full stars
        for (let i = 0; i < fullStars; i++) {
            starsHTML += '<i class="fas fa-star"></i>';
        }
        
        // Half star
        if (hasHalfStar) {
            starsHTML += '<i class="fas fa-star-half-alt"></i>';
        }
        
        // Empty stars
        for (let i = 0; i < emptyStars; i++) {
            starsHTML += '<i class="far fa-star"></i>';
        }
        
        return starsHTML;
    }
    
    // Enhanced product filtering
    function filterProducts(searchTerm) {
        let filteredProducts = products;
        
        if (searchTerm) {
            filteredProducts = products.filter(product => 
                product.name.toLowerCase().includes(searchTerm) ||
                product.description.toLowerCase().includes(searchTerm) ||
                product.category.toLowerCase().includes(searchTerm)
            );
        }
        
        const category = $('#categoryFilter').val();
        if (category && category !== 'all') {
            filteredProducts = filteredProducts.filter(product => 
                product.category.toLowerCase() === category.toLowerCase()
            );
        }
        
        sortProducts(filteredProducts);
    }
    
    // Sort products
    function sortProducts(productsToSort) {
        const sortBy = $('#sortFilter').val();
        
        switch (sortBy) {
            case 'name':
                productsToSort.sort((a, b) => a.name.localeCompare(b.name));
                break;
            case 'price-low':
                productsToSort.sort((a, b) => a.price - b.price);
                break;
            case 'price-high':
                productsToSort.sort((a, b) => b.price - a.price);
                break;
            case 'rating':
                productsToSort.sort((a, b) => b.rating - a.rating);
                break;
            default:
                // Keep original order
                break;
        }
        
        displayProducts(productsToSort);
    }
    
    // Combined filter and sort
    function filterAndSortProducts() {
        const searchTerm = $('#searchInput').val().toLowerCase();
        filterProducts(searchTerm);
    }
    
    // Populate category filter
    function populateCategories() {
        const categories = [...new Set(products.map(product => product.category))];
        const $categoryFilter = $('#categoryFilter');
        
        $categoryFilter.empty().append('<option value="all">All Categories</option>');
        
        categories.forEach(category => {
            $categoryFilter.append(`<option value="${category.toLowerCase()}">${category}</option>`);
        });
    }
    
    // Modal management
    function openAuthModal() {
        $('#authModal').addClass('active');
        $('#email').focus();
        $('body').addClass('modal-open');
    }
    
    function openCartModal() {
        updateCartModal();
        $('#cartModal').addClass('active');
        $('body').addClass('modal-open');
    }
    
    function closeModals() {
        $('.modal').removeClass('active');
        $('body').removeClass('modal-open');
        
        // Clear form errors
        $('.error-message').remove();
        $('.error').removeClass('error');
        $('#passwordStrength').remove();
    }
    
    // Update cart modal content
    function updateCartModal() {
        const $cartItems = $('#cartItems');
        const $cartTotal = $('#cartTotal');
        const $checkoutBtn = $('#checkoutBtn');
        
        if (cartItems.length === 0) {
            $cartItems.html(`
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Your cart is empty</h3>
                    <p>Add some products to get started</p>
                    <button class="continue-shopping-btn">Continue Shopping</button>
                </div>
            `);
            $checkoutBtn.hide();
            return;
        }
        
        let total = 0;
        const itemsHTML = cartItems.map(item => {
            const itemPrice = item.discount > 0 ? 
                item.price * (1 - item.discount / 100) : 
                item.price;
            const itemTotal = itemPrice * item.quantity;
            total += itemTotal;
            
            return `
                <div class="cart-item" data-product-id="${item.id}">
                    <img src="${item.image}" alt="${item.name}" class="cart-item-image">
                    <div class="cart-item-details">
                        <h4 class="cart-item-name">${item.name}</h4>
                        <div class="cart-item-price">
                            ${item.discount > 0 ? 
                                `<span class="original-price">$${item.price}</span>
                                 <span class="discounted-price">$${itemPrice.toFixed(2)}</span>` :
                                `<span class="price">$${item.price}</span>`
                            }
                        </div>
                    </div>
                    <div class="cart-item-controls">
                        <div class="quantity-controls">
                            <button class="quantity-btn" data-product-id="${item.id}" data-action="decrease">
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="quantity">${item.quantity}</span>
                            <button class="quantity-btn" data-product-id="${item.id}" data-action="increase">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="item-total">$${itemTotal.toFixed(2)}</div>
                        <button class="remove-item-btn" data-product-id="${item.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
        
        $cartItems.html(itemsHTML);
        $cartTotal.text(`$${total.toFixed(2)}`);
        $checkoutBtn.show();
    }
    
    // Open orders modal
    function openOrdersModal() {
        $('#ordersModal').addClass('active');
        $('body').addClass('modal-open');
        loadUserOrders();
    }
    
    // Load user orders
    function loadUserOrders() {
        if (!currentUser) {
            $('#ordersEmpty').show();
            $('#ordersList').hide();
            $('#ordersLoading').hide();
            return;
        }
        
        $('#ordersLoading').show();
        $('#ordersList').hide();
        $('#ordersEmpty').hide();
        
        $.ajax({
            url: 'api/orders.php',
            method: 'GET',
            xhrFields: {
                withCredentials: true
            },
            success: function(response) {
                $('#ordersLoading').hide();
                if (response.success && response.data.length > 0) {
                    displayOrders(response.data);
                    $('#ordersList').show();
                    $('#ordersEmpty').hide();
                } else {
                    $('#ordersEmpty').show();
                    $('#ordersList').hide();
                }
            },
            error: function() {
                $('#ordersLoading').hide();
                $('#ordersEmpty').show();
                $('#ordersList').hide();
                showNotification('Failed to load orders', 'error');
            }
        });
    }
    
    // Display orders in the modal
    function displayOrders(orders) {
        const $ordersList = $('#ordersList');
        const ordersHTML = orders.map(order => {
            const orderDate = new Date(order.created_at).toLocaleDateString();
            const itemsList = order.items.map(item => 
                `${item.product_name} x${item.quantity}`
            ).join(', ');
            
            return `
                <div class="order-item">
                    <div class="order-header">
                        <h4>Order #${order.id}</h4>
                        <span class="order-date">${orderDate}</span>
                        <span class="order-status status-${order.status}">${order.status}</span>
                    </div>
                    <div class="order-details">
                        <p><strong>Items:</strong> ${itemsList}</p>
                        <p><strong>Total:</strong> $${parseFloat(order.total).toFixed(2)}</p>
                        <p><strong>Status:</strong> ${order.status}</p>
                    </div>
                </div>
            `;
        }).join('');
        
        $ordersList.html(ordersHTML);
    }
    
    // Switch between login and register modes
    function switchAuthMode() {
        isAuthMode = isAuthMode === 'login' ? 'register' : 'login';
        
        const $form = $('#authForm');
        const $title = $('#authModalTitle');
        const $submitBtn = $('#authSubmitBtn');
        const $switchText = $('#authSwitchText');
        const $switchLink = $('#authSwitchLink');
        const $nameGroup = $('#nameGroup');
        
        if (isAuthMode === 'register') {
            $title.text('Create Account');
            $submitBtn.text('Register');
            $switchText.html('Already have an account? <a href="#" id="authSwitchLink">Login</a>');
            $nameGroup.show();
            $('#name').attr('required', true).prop('disabled', false);
        } else {
            $title.text('Login');
            $submitBtn.text('Login');
            $switchText.html("Don't have an account? <a href='#' id='authSwitchLink'>Register</a>");
            $nameGroup.hide();
            $('#name').removeAttr('required').prop('disabled', true);
        }
        
        // Clear form
        $form[0].reset();
        $('.error-message').remove();
        $('.error').removeClass('error');
        $('#passwordStrength').remove();
    }
    
    // Checkout process
    function proceedToCheckout() {
        if (cartItems.length === 0) {
            showNotification('Your cart is empty', 'warning');
            return;
        }
        
        // Calculate total
        const total = cartItems.reduce((sum, item) => {
            const itemPrice = item.discount > 0 ? 
                item.price * (1 - item.discount / 100) : 
                item.price;
            return sum + (itemPrice * item.quantity);
        }, 0);
        
        // Show order summary and get shipping address
        const orderSummary = cartItems.map(item => 
            `${item.name} x${item.quantity}`
        ).join('\n');
        
        const shippingAddress = prompt(`Order Summary:\n${orderSummary}\n\nTotal: $${total.toFixed(2)}\n\nPlease enter your shipping address (minimum 10 characters):`);
        
        if (shippingAddress && shippingAddress.trim().length >= 10) {
            // Create order data
            const orderData = {
                items: cartItems.map(item => ({
                    productId: item.id,
                    quantity: item.quantity
                })),
                shipping_address: shippingAddress.trim()
            };
            
            // Show loading state
            const $checkoutBtn = $('#checkoutBtn');
            const originalText = $checkoutBtn.text();
            $checkoutBtn.prop('disabled', true).text('Processing...');
            
            // Send order to backend
            $.ajax({
                url: 'api/orders.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(orderData),
                xhrFields: {
                    withCredentials: true
                },
                beforeSend: function(xhr) {
                    if (csrfToken) {
                        xhr.setRequestHeader("X-CSRF-Token", csrfToken);
                    }
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('Order placed successfully! Order #' + response.data.id, 'success');
                        cartItems = [];
                        updateCartDisplay();
                        saveCartToStorage();
                        closeModals();
                        
                        // Refresh user's orders if needed
                        if (currentUser) {
                            // Could load user orders here
                        }
                    } else {
                        showNotification(response.message || 'Failed to place order', 'error');
                    }
                },
                error: function(xhr) {
                    let message = 'Failed to place order';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    } else if (xhr.status === 401) {
                        message = 'Please login to place an order';
                    } else if (xhr.status === 400) {
                        message = 'Invalid order data';
                    }
                    showNotification(message, 'error');
                },
                complete: function() {
                    $checkoutBtn.prop('disabled', false).text(originalText);
                }
            });
        } else if (shippingAddress !== null) {
            showNotification('Shipping address must be at least 10 characters long', 'error');
        }
    }
    
    // Utility functions
    function scrollToSection(target) {
        const $target = $(target);
        if ($target.length) {
            $('html, body').animate({
                scrollTop: $target.offset().top - 80
            }, 800);
        }
    }
    
    function updateActiveNavLink($clickedLink) {
        $('.nav-link').removeClass('active');
        $clickedLink.addClass('active');
    }
    
    // Initialize auth form state
    function initializeAuthForm() {
        // Ensure name field is properly disabled initially
        $('#name').prop('disabled', true).removeAttr('required');
        $('#nameGroup').hide();
    }
    
    // Initialize cart from localStorage on page load
    loadCartFromStorage();
    
    // Handle page visibility change (for security)
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Page is hidden, could implement additional security measures
        } else {
            // Page is visible, could refresh auth status
            if (currentUser) {
                checkAuthStatus();
            }
        }
    });
    
    // Handle beforeunload for unsaved changes
    window.addEventListener('beforeunload', function(e) {
        if (cartItems.length > 0) {
            saveCartToStorage();
        }
    });
});

