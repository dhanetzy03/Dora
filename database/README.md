# Shukran Café - Database Setup Guide

## Web-based Inventory Tracking System with Enhanced Stock Monitoring and Sales Validation

---

## ⚡ QUICK START (RECOMMENDED)

### **Single-Step Setup** ✅
The main database file now includes ALL features. No need to run migrations separately!

1. **Import the Database:**
   ```bash
   # Using phpMyAdmin:
   # 1. Open http://localhost/phpmyadmin
   # 2. Click "Import" tab
   # 3. Choose file: database/shukran_cafe.sql
   # 4. Click "Go"
   ```

2. **That's it!** All tables and features are now ready.

---

## Database Structure

### Database Name
`shukran_cafe`

### Tables Overview (14 Tables Total)

**Core Tables:**
1. **users** - User accounts (Admin and Staff) with last_login tracking
2. **categories** - Product categories
3. **suppliers** - Supplier information
4. **products** - Product information with pricing and shelf life
5. **inventory** - Main inventory with cost tracking, shelf life, and expiry dates
6. **stock** - Current stock levels with expiry tracking
7. **stock_movements** - Stock movement tracking with spoilage support
8. **raw_materials** - Raw materials inventory (separate tracking)

**Transaction Tables:**
9. **sales** - Sales transactions with validation
10. **sale_items** - Individual items in sales with markup tracking
11. **purchases** - Purchase orders from suppliers
12. **purchase_items** - Individual items in purchases

**Enhanced Features Tables:**
13. **spoilage_records** - Spoilage tracking with financial loss calculation
14. **inventory_snapshots** - Beginning/ending inventory snapshots
15. **activity_logs** - System activity audit trail

---

## ✨ Features Included

### ✅ Panelist Requirements (All Implemented)
1. **Shelf Life Tracking** - Track expiry dates for each item
2. **Spoilage Monitoring** - Record and monitor spoiled items
3. **Beginning/Ending Inventory** - Daily inventory snapshots
4. **Complete Reports** - Six comprehensive report types
5. **Sales Validation** - Admin validation of sales transactions
6. **Stock Monitoring** - Real-time stock level tracking

---

## Installation Steps

### Option 1: Using phpMyAdmin (Recommended for Beginners)
1. Open phpMyAdmin at `http://localhost/phpmyadmin`
2. Click on "Import" tab
3. Click "Choose File" and select `database/shukran_cafe.sql`
4. Click "Go" button at the bottom
5. Wait for the import to complete (should take less than 10 seconds)

### Option 2: Using MySQL Command Line
```bash
mysql -u root -p < database/shukran_cafe.sql
```

### Option 3: Using Windows Batch File
Double-click: `run_all_migrations.bat` (if migrations need to be reapplied)

---

## Default Credentials

### Admin Account
- **Username:** `admintester`
- **Password:** `test123`

### Staff Account
- **Username:** `staffuser`
- **Password:** `test123`

**⚠️ IMPORTANT:** Change these passwords after initial setup for production use!

---

## 🔧 Configuration

### Database Connection
The database connection is configured in:
**File:** `config/db_connect.php`

```php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "shukran_cafe";
```

Update these values if your MySQL configuration is different.

---

## 📚 Helper Functions Available

### 1. Session Management
**File:** `config/session.php`

Available functions:
- `is_logged_in()` - Check if user is logged in
-
- `validate_user()` - User authentication
- `check_permission()` - Permission checking
- `sanitize_input()` - Input sanitization
- `generate_transaction_number()` - Generate unique transaction IDs

$current_user = get_current_user_data();
**File:** `config/session.php`

Available functions:
- `is_logged_in()` - Check if user is logged in
- `get_current_user()` - Get current user info
- `set_user_session()` - Set user session
- `destroy_user_session()` - Logout user
- `is_admin()` - Check if user is admin
- `is_staff()` - Check if user is staff
- `require_login()` - Require authentication
- `require_admin()` - Require admin role
- `set_flash_message()` - Set flash messages
- `get_flash_message()` - Get flash messages
- `generate_csrf_token()` - CSRF protection
- `verify_csrf_token()` - Verify CSRF token
- `check_session_timeout()` - Session timeout check

---

## Usage Examples

### Example 1: Using Database Helper in Your PHP Files

```php
<?php
// Include required files
require_once '../../config/db_connect.php';
require_once '../../config/db_helper.php';
require_once '../../config/session.php';

// Require user to be logged in
require_login();

// Get all active users
$users = db_fetch_all("SELECT * FROM users WHERE status = 'active'");

// Get single user by ID
$user = db_fetch_one("SELECT * FROM users WHERE user_id = ?", [1], 'i');

// Insert new category
$result = db_insert('categories', [
    'category_name' => 'New Category',
    'description' => 'Category description'
]);

// Update user
$result = db_update('users', 
    ['full_name' => 'Updated Name'],
    'user_id = ?',
    [1]
);

// Log activity
log_activity(
    $_SESSION['user_id'], 
    'CREATE', 
    'categories', 
    'Created new category'
);
?>
```

### Example 2: Authentication in Login Page

```php
<?php
require_once '../../config/db_connect.php';
require_once '../../config/db_helper.php';
require_once '../../config/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    
    $user = validate_user($username, $password);
    
    if ($user) {
        set_user_session($user);
        log_activity($user['user_id'], 'LOGIN', 'auth', 'User logged in');
        
        // Redirect based on role
        if ($user['role'] === 'admin') {
            header('Location: ../admindash/admin.php');
        } else {
            header('Location: ../dashboard/staff.php');
        }
        exit();
    } else {
        set_flash_message('error', 'Invalid credentials');
    }
}
?>
```

### Example 3: Protected Page

```php
<?php
require_once '../../config/db_connect.php';
require_once '../../config/db_helper.php';
require_once '../../config/session.php';

// Only admin can access this page
require_admin();

$current_user = get_current_user();
?>
```

---

## Key Features Ready for Implementation

### ✅ Enhanced Stock Monitoring
- Table: `stock_movements`
- Tracks all inventory movements (in/out/adjustments)
- Records previous and new quantities
- Links to purchases, sales, and adjustments
- Audit trail of who made changes

### ✅ Sales Validation
- Table: `sales` with validation fields
- `validated_by` - Which admin validated the sale
- `validated_at` - When validation occurred
- `status` - pending/completed/cancelled
- Complete sale items tracking

### ✅ User Management
- Role-based access (Admin/Staff)
- Session management
- Activity logging
- CSRF protection

### ✅ Audit Trail
- `activity_logs` table
- Tracks all system activities
- Records user actions, IP addresses, timestamps

---

## Next Steps

1. ✅ Database structure created
2. ✅ Backend connections ready
3. ✅ Authentication system ready
4. 🔜 Add inventory items (when ready)
5. 🔜 Implement stock monitoring features
6. 🔜 Implement sales validation workflow
7. 🔜 Build reporting features

---

## Sample Categories Included

- Beverages (Coffee, tea, and other drinks)
- Food Items (Sandwiches, pastries, and meals)
- Ingredients (Raw materials and ingredients)
- Supplies (Paper cups, napkins, and other supplies)

---

## Security Features

✅ Prepared statements (SQL injection prevention)
✅ Password hashing (bcrypt)
✅ CSRF token protection
✅ Session timeout (30 minutes)
✅ Input sanitization
✅ Activity logging
✅ Role-based access control

---

## File Organization

```
Dora/
├── config/
│   ├── db_connect.php      # Database connection
│   ├── db_helper.php        # Database helper functions
│   └── session.php          # Session management
├── database/
│   └── shukran_cafe.sql     # Database schema
└── src/
    ├── auth/
    │   └── login.php        # Login page
    ├── dashboard/
    │   └── staff.php        # Staff dashboard
    └── admindash/
        └── admin.php        # Admin dashboard
```

---

## Support

For questions or issues, please refer to this documentation or check the code comments in each file.

---

**Last Updated:** November 8, 2025
