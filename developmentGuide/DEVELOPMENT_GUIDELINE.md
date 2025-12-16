
**Project Status:** In Development  
**Last Updated:** November 12, 2025  
**Project Owner:** Dhanetzy03

---

## 📑 TABLE OF CONTENTS

1. [Project Overview](#project-overview)
2. [Project Status & Completion](#project-status--completion)
3. [Architecture & Structure](#architecture--structure)
4. [Development Standards](#development-standards)
5. [Database Guidelines](#database-guidelines)
6. [Frontend Guidelines](#frontend-guidelines)
7. [Backend Guidelines](#backend-guidelines)
8. [Security Standards](#security-standards)
9. [Testing Checklist](#testing-checklist)
10. [Deployment Checklist](#deployment-checklist)
11. [Common Issues & Solutions](#common-issues--solutions)

---

## 🎯 PROJECT OVERVIEW

### Project Title
**A Web-Based Raw Materials Inventory & Order Management System for Shukran Café**

### Project Purpose
- **Admin Side:** Manage raw materials (ingredients) inventory in real-time
- **Staff Side:** Process customer orders (like a POS cashier system)
- **Auto-Deduction:** Each order automatically deducts raw materials from inventory
- **Stock Monitoring:** Track ingredient usage and remaining stock levels
- **Inventory Validation:** Monitor material movements and verify stock accuracy

### System Flow
```
Customer Order (Staff Input) 
    ↓
Order Recipe/Items Selection
    ↓
Deduct Raw Materials from Inventory
    ↓
Update Inventory in Real-Time
    ↓
Track Stock Levels & Generate Alerts (Low Stock)
```

### Target Users
- **Admin/Manager:** Manage raw materials inventory, add/edit/delete ingredients, monitor stock levels, view usage reports
- **Staff/Cashier:** Record customer orders, input quantities, process sales → automatic inventory deduction
- **View Dashboard:** See real-time stock status, ingredient usage, and alerts

### Technology Stack
- **Backend:** PHP 7.4+
- **Database:** MySQL
- **Frontend:** HTML5, CSS3, JavaScript
- **Server:** Apache (XAMPP)
- **Icons:** Boxicons

---

## ✅ PROJECT STATUS & COMPLETION

### ✨ COMPLETED FEATURES

#### 🔐 Authentication System
- [x] Login/Logout functionality
- [x] Role-based access control (Admin & Staff)
- [x] Session management
- [x] Password storage (currently plain text - TO BE IMPROVED)
- [x] Automatic redirects based on role

#### 👨‍💼 Admin Panel
- [x] Admin Dashboard with statistics
- [x] Raw Materials Inventory Management (view ingredients)
- [x] Stock Monitoring & tracking
- [x] Sidebar navigation
- [x] Admin styling & UI

#### 👥 Staff Panel (POS/Cashier)
- [x] Staff Dashboard with statistics
- [x] Order form (like POS system)
- [x] Order history/sales table
- [x] Staff styling & UI

#### 📊 Database
- [x] Database schema created
- [x] User accounts setup
- [x] Tables structure defined
- [x] Sample data included

---

### 🚧 INCOMPLETE/TO-DO FEATURES

#### High Priority 🔴
1. **Order-to-Inventory Deduction (CRITICAL)**
   - [ ] Create order processing logic
   - [ ] Link menu items to raw materials/recipes
   - [ ] Auto-deduct materials when order is placed
   - [ ] Update inventory in real-time
   - [ ] Handle insufficient stock scenarios
   - [ ] Create recipes/menu items table structure

2. **Raw Materials Management**
   - [ ] Complete Raw Materials CRUD (Add/Edit/Delete ingredients)
   - [ ] Track material quantities, units, reorder levels
   - [ ] Set low stock thresholds/alerts
   - [ ] Recipe management (which materials needed for each item)

3. **Security Hardening**
   - [ ] Implement password hashing (bcrypt/argon2)
   - [ ] Add CSRF token protection
   - [ ] Implement input validation on all forms
   - [ ] Add prepared statements to all queries
   - [ ] SQL injection prevention verification

4. **Data Validation & Error Handling**
   - [ ] Client-side form validation (JavaScript)
   - [ ] Server-side form validation (PHP)
   - [ ] Error logging system
   - [ ] User-friendly error messages
   - [ ] Exception handling
   - [ ] Insufficient stock warnings

#### Medium Priority 🟡
5. **Reports & Analytics**
   - [ ] Daily sales report (orders processed)
   - [ ] Material usage report (ingredient consumption)
   - [ ] Stock movement analysis
   - [ ] Low stock alerts report
   - [ ] PDF/Excel export functionality

6. **Admin Features**
   - [ ] User management (Create/Edit/Delete staff accounts)
   - [ ] Raw material category management
   - [ ] Supplier/vendor management
   - [ ] Purchase order management (for restocking)
   - [ ] Inventory adjustment (spoilage, waste, corrections)
   - [ ] System settings/configuration

7. **Staff/Cashier Features**
   - [ ] Menu/Item selection interface (not just raw materials)
   - [ ] Quick order buttons for popular items
   - [ ] Quantity input & order confirmation
   - [ ] View order history with timestamps
   - [ ] Undo/Cancel recent orders
   - [ ] Payment method tracking

#### Low Priority 🟢
8. **UI/UX Improvements**
   - [ ] Mobile responsive design optimization
   - [ ] Dark mode toggle
   - [ ] Dashboard customization
   - [ ] Advanced filtering on tables
   - [ ] Search functionality
   - [ ] Voice/barcode scanning for quick orders

9. **Notifications & Alerts**
   - [ ] Low stock notifications
   - [ ] Email alerts for critical items
   - [ ] In-app notification system
   - [ ] Sales validation notifications

10. **Performance**
    - [ ] Pagination on large tables
    - [ ] Lazy loading for images
    - [ ] Caching mechanisms
    - [ ] Database query optimization

11. **Documentation**
    - [ ] API documentation
    - [ ] User manual
    - [ ] Admin guide
    - [ ] Staff guide
    - [ ] Database dictionary

---

## 🏗️ ARCHITECTURE & STRUCTURE

### Directory Structure
```
Dora/
├── config/                    # Configuration files
│   ├── db_connect.php        # Database connection
│   ├── db_helper.php         # Database helper functions
│   └── session.php           # Session management
│
├── database/                  # Database files
│   ├── README.md             # Database setup guide
│   └── shukran_cafe.sql      # SQL schema & data
│
├── src/                       # Source code
│   ├── auth/                 # Authentication
│   │   ├── login.php         # Login page
│   │   └── logout.php        # Logout page
│   │
│   ├── admindash/            # Admin dashboard
│   │   ├── admin.php         # Main admin page
│   │   ├── dashboard.php     # Dashboard overview
│   │   ├── inventory.php     # Raw materials/inventory management
│   │   ├── stock_monitoring.php  # Stock tracking & alerts
│   │   ├── sidebar.php       # Navigation sidebar
│   │   └── (admin.php is the main entry)
│   │
│   ├── dashboard/            # Staff dashboard (POS/Cashier)
│   │   ├── staff.php         # Order entry page (like cashier)
│   │   └── style.css         # Staff styles
│   │
│   └── styles/               # Global styles
│       ├── admin-style.css   # Admin panel styles
│       └── staff-style.css   # Staff panel styles
│
├── index.php                 # Main entry point (router)
├── SETUP_NOTES.md           # Setup documentation
├── UPGRADE_SUMMARY.md       # Recent changes
└── DEVELOPMENT_GUIDELINE.md # This file
```

### MVC-Inspired Architecture
```
Raw Materials Data (Database)
    ↓
config/db_helper.php (Business Logic - Recipes & Deductions)
    ↓
Admin Panel: View/Manage Materials
    ↓
Staff Panel: Enter Order → Deduct Materials
    ↓
Real-Time Inventory Updates
```

---

## 📝 DEVELOPMENT STANDARDS

### 1. **Naming Conventions**

#### PHP Files
- Use **snake_case** for filenames: `admin_dashboard.php`, `user_profile.php`
- Use **PascalCase** for class names: `UserManager`, `InventoryController`
- Use **camelCase** for function names: `getUserProfile()`, `validateInput()`

#### Variables & Constants
```php
// Variables - camelCase
$userName = "John";
$inventoryCount = 50;

// Constants - UPPER_SNAKE_CASE
define('DB_HOST', 'localhost');
define('MAX_ITEMS_PER_PAGE', 25);

// Database columns - snake_case
// Examples: user_id, product_name, stock_quantity
```

#### CSS Classes
- Use **kebab-case**: `.admin-dashboard`, `.staff-header`, `.card-item`
- Prefix component classes: `.btn-primary`, `.form-input`, `.card-header`

### 2. **Code Style**

#### PHP Indentation
- Use **4 spaces** for indentation
- No tabs

```php
<?php
if ($condition) {
    // Code here
    for ($i = 0; $i < 10; $i++) {
        echo "Item " . $i;
    }
}
```

#### HTML Structure
- Use semantic HTML5 tags: `<header>`, `<nav>`, `<main>`, `<section>`, `<article>`, `<footer>`
- Always close tags properly
- Use meaningful IDs and classes

```html
<main class="dashboard">
    <section class="inventory-section">
        <h2>Inventory List</h2>
        <!-- Content here -->
    </section>
</main>
```

#### CSS Organization
- Group related styles together
- Use comments to separate sections
- Follow mobile-first responsive design

```css
/* ========== Header Styles ========== */
.header {
    background: #667eea;
    padding: 20px;
}

.header h1 {
    color: white;
    font-size: 24px;
}

/* ========== Responsive ========== */
@media (max-width: 768px) {
    .header {
        padding: 15px;
    }
}
```

### 3. **Comments & Documentation**

#### PHP Comments
```php
// Single line comment for brief explanations

/*
 * Multi-line comment for longer explanations
 * Use this for complex logic
 */

/**
 * Function documentation block
 * 
 * @param string $username The user's username
 * @param string $password The user's password
 * @return array User data array or false
 */
function authenticate($username, $password) {
    // Implementation
}
```

#### HTML/CSS Comments
```html
<!-- Section name -->
<div class="section-name">
    <!-- Component description -->
    <div class="component">...</div>
</div>
```

---

## 🗄️ DATABASE GUIDELINES

### 1. **Database Connection**

**File:** `config/db_connect.php`

Current configuration:
```
Host: localhost
Username: root
Password: (empty)
Database: shukran_cafe
```

**Never hardcode credentials in production!** Use environment variables.

### 2. **Available Helper Functions**

Located in `config/db_helper.php`:

```php
// Query execution
db_query($query, $params = [])  // Execute prepared statement
db_fetch_one($query, $params)   // Get single row
db_fetch_all($query, $params)   // Get all rows

// CRUD operations
db_insert($table, $data)        // Insert row
db_update($table, $data, $where) // Update row
db_delete($table, $where)       // Delete row

// Utilities
sanitize_input($input)          // Sanitize input
generate_transaction_number()   // Generate unique ID
log_activity($action, $details) // Log activity

// User functions
validate_user($username, $password) // Authenticate
check_permission($user_id, $resource) // Check access
```

### 3. **Query Standards**

**Always use prepared statements:**
```php
// ✅ CORRECT - Uses prepared statement
$query = "SELECT * FROM products WHERE category = ? AND stock > ?";
$result = db_fetch_all($query, ['Electronics', 10]);

// ❌ WRONG - Direct query (SQL Injection risk)
$query = "SELECT * FROM products WHERE category = '$category'";
```

**Naming conventions for queries:**
- Fetch data: `$query`, `$sql`
- Results: `$result`, `$data`, `$items`
- Count: `$count`, `$total`

### 4. **Transaction Handling**

For multi-step operations:
```php
try {
    // Start transaction
    $conn->begin_transaction();
    
    // Multiple operations
    db_insert('table1', $data1);
    db_update('table2', $data2, $where);
    
    // Commit if all successful
    $conn->commit();
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    log_activity('Transaction failed', $e->getMessage());
}
```

### 5. **Database Schema**

**Key Tables for Raw Materials & Order System:**

| Table | Purpose | Key Fields |
|-------|---------|-----------|
| `users` | User accounts | user_id, username, password, role |
| `raw_materials` | Ingredients/materials | material_id, material_name, unit, quantity, reorder_level, supplier_id |
| `material_stock` | Current stock levels | stock_id, material_id, quantity_on_hand |
| `menu_items` | Cafe menu items | item_id, item_name, price, description |
| `recipes` | Item recipes/formulas | recipe_id, item_id, material_id, quantity_needed |
| `orders` | Customer orders | order_id, staff_id, total_amount, order_date, status |
| `order_items` | Items in each order | order_item_id, order_id, menu_item_id, quantity |
| `stock_movements` | Material tracking | movement_id, material_id, type (In/Out), quantity, reference_id, timestamp |
| `suppliers` | Vendor info | supplier_id, supplier_name, contact, email |
| `categories` | Material categories | category_id, category_name |

**Data Flow in System:**
```
1. Admin adds Raw Materials → stored in raw_materials table
2. Admin creates Menu Items → stored in menu_items table
3. Admin links Materials to Menu Items → stored in recipes table
4. Staff enters Customer Order → creates entry in orders & order_items
5. System triggers Deduction Logic:
   - For each item in order, fetch recipe
   - Reduce material quantities based on recipe
   - Update material_stock
   - Log movement in stock_movements
6. Admin can view updated inventory in stock_movements history
```

---

## 🎨 FRONTEND GUIDELINES

### 1. **CSS Organization**

Create separate CSS files for different sections:

```
styles/
├── admin-style.css      # Admin panel styles
├── staff-style.css      # Staff panel styles
├── base.css             # Global styles (future)
└── components.css       # Reusable components (future)
```

**Always link styles in `<head>`:**
```html
<head>
    <link rel="stylesheet" href="../styles/admin-style.css">
</head>
```

### 2. **Component Structure**

#### Cards Component
```html
<div class="card">
    <div class="card-header">
        <h3>Card Title</h3>
    </div>
    <div class="card-body">
        <!-- Content -->
    </div>
    <div class="card-footer">
        <!-- Actions -->
    </div>
</div>
```

#### Button Variants
```html
<button class="btn btn-primary">Primary Action</button>
<button class="btn btn-secondary">Secondary Action</button>
<button class="btn btn-danger">Delete</button>
<button class="btn btn-success">Approve</button>
```

#### Form Structure
```html
<form action="process.php" method="POST" class="form">
    <div class="form-group">
        <label for="input-name">Label</label>
        <input type="text" id="input-name" name="input-name" required>
    </div>
    
    <button type="submit" class="btn btn-primary">Submit</button>
</form>
```

### 3. **Color Scheme**

**Admin Panel (Purple Gradient):**
- Primary: `#667eea`
- Secondary: `#764ba2`
- Accent: `#f5576c`

**Staff Panel (Pink Gradient):**
- Primary: `#f093fb`
- Secondary: `#f5576c`
- Accent: `#667eea`

**Status Colors:**
- Success: `#10b981` (Green)
- Warning: `#f59e0b` (Orange)
- Danger: `#ef4444` (Red)
- Info: `#3b82f6` (Blue)

### 4. **Responsive Design**

```css
/* Mobile First Approach */
.container {
    width: 100%;
    padding: 10px;
}

/* Tablets */
@media (min-width: 768px) {
    .container {
        width: 100%;
        padding: 20px;
    }
}

/* Desktop */
@media (min-width: 1024px) {
    .container {
        max-width: 1200px;
        margin: 0 auto;
    }
}
```

### 5. **JavaScript Standards**

```javascript
// Use meaningful variable names
const getUserData = () => {
    // Implementation
};

// Use const by default, let if needed, avoid var
const maxItems = 50;
let currentIndex = 0;

// Use arrow functions
const processData = (items) => items.filter(item => item.active);

// Add comments for complex logic
// Validate user input before submission
const validateForm = (formData) => {
    // Implementation
};
```

---

## 🔧 BACKEND GUIDELINES

### 1. **Entry Point Pattern**

**Always start with file inclusion pattern:**

```php
<?php
// 1. Include configuration files
require_once '../config/db_connect.php';
require_once '../config/db_helper.php';
require_once '../config/session.php';

// 2. Check authentication/authorization
if (!is_logged_in()) {
    header("Location: ../auth/login.php");
    exit();
}

// 3. Check role if needed
if (!is_admin()) {
    header("Location: ../dashboard/staff.php");
    exit();
}

// 4. Process form submissions (POST requests)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle POST data
}

// 5. Fetch data for display (GET requests)
$data = db_fetch_all("SELECT * FROM products");

// 6. Pass to view/HTML
?>

<!DOCTYPE html>
<html>
<head>...</head>
<body>
    <!-- Display $data -->
</body>
</html>
```

### 2. **Session Management**

**Check user role before displaying content:**

```php
<?php
// Check if user is admin
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    // Show admin content
}

// Check if user is staff
if (isset($_SESSION['role']) && $_SESSION['role'] === 'staff') {
    // Show staff content
}

// Get current user info
$current_user = $_SESSION['username'] ?? null;
$user_role = $_SESSION['role'] ?? null;
?>
```

### 3. **Form Processing Pattern**

```php
<?php
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Get and sanitize input
    $name = sanitize_input($_POST['name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    
    // 2. Validate input
    if (empty($name) || empty($email)) {
        $error = "All fields are required";
    } else {
        // 3. Process data
        $data = [
            'name' => $name,
            'email' => $email,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // 4. Execute database operation
        if (db_insert('users', $data)) {
            $success = "User added successfully";
            log_activity('ADD_USER', "User: $name");
        } else {
            $error = "Failed to add user";
        }
    }
}
?>
```

### 4. **Order Processing & Material Deduction (CRITICAL LOGIC)**

**This is the core logic of your system - handle with care!**

```php
<?php
/**
 * Process Customer Order and Deduct Materials
 * 
 * @param int $staff_id - Staff member who processed order
 * @param array $items - Array of ['menu_item_id' => quantity, ...]
 * @param float $total_amount - Order total
 * @return bool - Success/failure
 */
function processOrderAndDeductMaterials($staff_id, $items, $total_amount) {
    try {
        // Start transaction for data consistency
        $conn->begin_transaction();
        
        // Step 1: Create order record
        $order_data = [
            'staff_id' => $staff_id,
            'total_amount' => $total_amount,
            'order_date' => date('Y-m-d H:i:s'),
            'status' => 'completed'
        ];
        $order_id = db_insert('orders', $order_data);
        
        // Step 2: For each item in order
        foreach ($items as $menu_item_id => $quantity) {
            // 2a. Add item to order_items table
            $order_item_data = [
                'order_id' => $order_id,
                'menu_item_id' => $menu_item_id,
                'quantity' => $quantity
            ];
            db_insert('order_items', $order_item_data);
            
            // 2b. Get recipe for this menu item
            $recipe_query = "SELECT material_id, quantity_needed FROM recipes WHERE item_id = ?";
            $materials = db_fetch_all($recipe_query, [$menu_item_id]);
            
            // 2c. Deduct each material from stock
            foreach ($materials as $material) {
                $deduction_qty = $material['quantity_needed'] * $quantity;
                
                // Check if enough stock available
                $stock_query = "SELECT quantity_on_hand FROM material_stock WHERE material_id = ?";
                $stock = db_fetch_one($stock_query, [$material['material_id']]);
                
                if ($stock['quantity_on_hand'] < $deduction_qty) {
                    throw new Exception("Insufficient stock for material ID: " . $material['material_id']);
                }
                
                // Deduct from stock
                $new_qty = $stock['quantity_on_hand'] - $deduction_qty;
                db_update('material_stock', 
                    ['quantity_on_hand' => $new_qty],
                    ['material_id' => $material['material_id']]
                );
                
                // Log the movement
                $movement_data = [
                    'material_id' => $material['material_id'],
                    'type' => 'Out',
                    'quantity' => $deduction_qty,
                    'reference_type' => 'Order',
                    'reference_id' => $order_id,
                    'created_by' => $staff_id,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                db_insert('stock_movements', $movement_data);
            }
        }
        
        // Step 3: Commit transaction
        $conn->commit();
        log_activity('ORDER_PROCESSED', "Order #$order_id created by Staff #$staff_id");
        return true;
        
    } catch (Exception $e) {
        // Rollback entire transaction if any error
        $conn->rollback();
        log_activity('ORDER_FAILED', $e->getMessage());
        throw new Exception("Order processing failed: " . $e->getMessage());
    }
}
?>
```

**Key Points:**
- ✅ Use transactions to ensure atomicity (all or nothing)
- ✅ Check stock availability BEFORE deducting
- ✅ Automatically log all movements
- ✅ Throw exceptions on insufficient stock
- ✅ Staff doesn't need to input materials - it's automatic via recipe

**Testing Checklist for This Logic:**
- [ ] Order with single item deducts correct materials
- [ ] Order with multiple items deducts from multiple materials
- [ ] Insufficient stock shows error (order not created)
- [ ] Stock movements are logged correctly
- [ ] Multiple orders don't exceed available stock
- [ ] Materials marked as low stock when below reorder level

### 5. **Error Handling**

```php
<?php
// Use try-catch for database operations
try {
    $result = db_fetch_all($query, $params);
} catch (Exception $e) {
    log_activity('ERROR', $e->getMessage());
    $error = "An error occurred. Please try again later.";
}

// Provide user-friendly error messages
if ($error) {
    echo "<div class='alert alert-danger'>$error</div>";
}
?>
```

---

## 🔐 SECURITY STANDARDS

### 1. **Authentication Requirements**

**TO-DO: Implement password hashing**
```php
// Current (INSECURE - for development only):
// Password stored as plain text

// TO-DO: Use bcrypt
$password_hash = password_hash($_POST['password'], PASSWORD_BCRYPT);

// Verify password
if (password_verify($_POST['password'], $stored_hash)) {
    // Password correct
}
```

### 2. **Input Validation**

**Always validate and sanitize user input:**

```php
<?php
// Sanitize input
$name = sanitize_input($_POST['name'] ?? '');

// Validate input
if (strlen($name) < 3) {
    $error = "Name must be at least 3 characters";
}

// Check against whitelist
$allowed_roles = ['admin', 'staff'];
if (!in_array($role, $allowed_roles)) {
    $error = "Invalid role";
}
?>
```

### 3. **CSRF Protection (TO-DO)**

Generate and validate CSRF tokens:
```php
// Generate token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Verify token
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("CSRF token mismatch");
}
```

### 4. **SQL Injection Prevention**

**Always use prepared statements:**

```php
// ✅ CORRECT
$query = "SELECT * FROM users WHERE username = ? AND status = ?";
$user = db_fetch_one($query, [$username, 'active']);

// ❌ WRONG - Vulnerable to SQL injection
$query = "SELECT * FROM users WHERE username = '$username'";
```

### 5. **Session Security**

```php
<?php
// In session.php or login page
session_start();

// Set secure session options
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
// ini_set('session.cookie_secure', 1);  // Uncomment for HTTPS

// Regenerate session ID after login
session_regenerate_id(true);

// Set session timeout
if (isset($_SESSION['last_activity']) && 
    (time() - $_SESSION['last_activity'] > 1800)) {
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();
?>
```

---

## ✅ TESTING CHECKLIST

### Before Committing Code:

#### [ ] Functionality Testing
- [ ] Feature works as intended
- [ ] All user inputs are accepted
- [ ] Database operations (CRUD) work correctly
- [ ] Navigation works properly
- [ ] Links point to correct pages

#### [ ] Error Handling
- [ ] Empty form submission shows error
- [ ] Invalid data shows error message
- [ ] Database errors are handled gracefully
- [ ] No PHP notices/warnings in error log

#### [ ] Security Testing
- [ ] User cannot access unauthorized pages
- [ ] Session properly validates roles
- [ ] Input is sanitized
- [ ] SQL injection attempts are blocked
- [ ] Logout clears session properly

#### [ ] Browser Testing
- [ ] Test in Chrome
- [ ] Test in Firefox
- [ ] Test in Safari
- [ ] Test on mobile browser
- [ ] Responsive design works

#### [ ] Performance
- [ ] Page loads in < 2 seconds
- [ ] No console errors
- [ ] Images optimized
- [ ] Database queries are efficient

---

## 🚀 DEPLOYMENT CHECKLIST

### Before Going Live:

#### [ ] Security
- [ ] Implement password hashing (bcrypt)
- [ ] Add CSRF token protection
- [ ] Update database credentials (environment variables)
- [ ] Enable HTTPS
- [ ] Set secure cookie options
- [ ] Remove debug output
- [ ] Disable file uploading if not needed

#### [ ] Database
- [ ] Backup database
- [ ] Test database recovery
- [ ] Verify all indexes are present
- [ ] Check for orphaned records

#### [ ] Documentation
- [ ] Update README.md
- [ ] Document API endpoints
- [ ] Create user manual
- [ ] Create admin guide
- [ ] Document common issues

#### [ ] Testing
- [ ] Full end-to-end testing
- [ ] Load testing
- [ ] Security audit
- [ ] Accessibility audit

#### [ ] Configuration
- [ ] Update config files
- [ ] Set appropriate file permissions
- [ ] Configure error logging
- [ ] Set up backup schedule

---

## 🆘 COMMON ISSUES & SOLUTIONS

### Issue 1: "Session Check Failed"
**Problem:** User redirected to login despite being logged in

**Solution:**
1. Check `config/session.php` is included
2. Verify session variables are set correctly in login.php
3. Check browser cookies are enabled
4. Clear browser cache and try again

```php
// Verify session is set
var_dump($_SESSION); // Temporary debug
```

### Issue 2: "404 Page Not Found"
**Problem:** Links are broken or pages don't exist

**Solution:**
1. Check file paths in `<a href="...">` tags
2. Verify file exists in the specified directory
3. Use relative paths correctly: `../folder/file.php`
4. Update `index.php` routing if needed

### Issue 3: "Database Connection Failed"
**Problem:** Cannot connect to database

**Solution:**
1. Verify XAMPP/Apache is running
2. Verify MySQL is running
3. Check `config/db_connect.php` credentials:
   - Host: `localhost`
   - Username: `root`
   - Password: `` (empty)
   - Database: `shukran_cafe`
4. Recreate database from `database/shukran_cafe.sql`

### Issue 4: "Undefined Variable" Errors
**Problem:** PHP notices about undefined variables

**Solution:**
```php
// ❌ Wrong
echo $variable;

// ✅ Correct
echo $variable ?? 'default value';
// Or
if (isset($variable)) {
    echo $variable;
}
```

### Issue 5: "CSS Not Loading"
**Problem:** Styles not applying to page

**Solution:**
1. Check CSS file path in `<link>` tag
2. Verify file exists at that path
3. Clear browser cache (Ctrl+Shift+Delete)
4. Check CSS syntax for errors
5. Verify CSS specificity isn't being overridden

---

## 📞 QUICK REFERENCE

### File Locations
- Main entry: `index.php`
- Database setup: `database/shukran_cafe.sql`
- Config: `config/`
- Admin pages: `src/admindash/`
- Staff pages: `src/dashboard/`
- Auth pages: `src/auth/`

### Default Credentials
- **Admin:** `admin` / `admin123`
- **Staff:** `staff` / `staff123`

### Key Functions
- `is_logged_in()` - Check if logged in
- `is_admin()` - Check if admin
- `db_fetch_all()` - Get multiple records
- `db_insert()` - Insert record
- `sanitize_input()` - Clean input

### Important URLs (Local Development)
- Main: `http://localhost/f4/Dora/`
- Admin: `http://localhost/f4/Dora/src/admindash/admin.php`
- Staff: `http://localhost/f4/Dora/src/dashboard/staff.php`
- phpMyAdmin: `http://localhost/phpmyadmin/`

---

## 🎓 NEXT STEPS

### Immediate Tasks (Next Session) - CRITICAL
1. [ ] **Implement Order-to-Material Deduction System** (HIGHEST PRIORITY)
   - Create recipes table linking menu items to materials
   - Implement processOrderAndDeductMaterials() function
   - Create staff order form that triggers deductions
   - Test with sample orders
   
2. [ ] Create Menu Items management
   - Add/Edit/Delete menu items in admin panel
   - Link items to their recipes (which materials needed)

3. [ ] Implement password hashing (bcrypt)
4. [ ] Add form validation (client & server-side)
5. [ ] Add CSRF token protection

### Short-term Tasks (This Week)
1. [ ] Complete Raw Materials CRUD (Add/Edit/Delete ingredients)
2. [ ] Implement recipe management system
3. [ ] Test full order flow: Staff enters order → Materials deducted → Inventory updated
4. [ ] Add error handling for insufficient stock scenarios
5. [ ] Create low stock alerts when materials fall below reorder level

### Medium-term Tasks (Next 2 weeks)
1. [ ] Generate material usage reports (what was consumed)
2. [ ] Generate sales reports (orders processed)
3. [ ] Add stock adjustment capabilities for admins
4. [ ] Implement purchase order management (restocking)
5. [ ] Add pagination to tables

### Long-term Tasks (Future)
1. [ ] Dashboard analytics & charts (material usage trends)
2. [ ] Advanced inventory reports
3. [ ] Mobile-friendly cashier interface
4. [ ] Barcode scanning for quick orders
5. [ ] Multi-location inventory support

---

## 📚 RESOURCES

### References
- PHP Official: https://www.php.net/
- MySQL Official: https://www.mysql.com/
- W3Schools PHP: https://www.w3schools.com/php/
- MDN Web Docs: https://developer.mozilla.org/

### Tools
- phpMyAdmin: Database management
- XAMPP: Local development server
- VS Code: Code editor
- Git: Version control

---

## 📝 CHANGELOG

**v1.1.0 - November 12, 2025 (Updated)**
- 🔄 Clarified project as Raw Materials Inventory + Order System (POS-like)
- 📊 Updated feature list to reflect material deduction on order
- 🔧 Added critical "Order Processing & Material Deduction" backend logic section
- 📋 Reorganized database schema for recipes & material tracking
- 🎯 Updated architecture diagram to show order → deduction flow
- 🚀 Reprioritized next steps with order system as HIGHEST priority

**v1.0.0 - November 12, 2025**
- Initial guideline document created
- Documented completed features
- Listed to-do items by priority
- Added development standards
- Created testing and deployment checklists

---

**Remember:** This guideline is a living document. Update it as your project evolves!

🚀 **Happy Coding!**
