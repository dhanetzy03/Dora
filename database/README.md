# Shukran Café - Database Setup Guide

## Web-based Inventory Tracking System with Enhanced Stock Monitoring and Sales Validation

---

## Database Structure

### Database Name
`shukran_cafe`

### Tables Overview

1. **users** - User accounts (Admin and Staff)
2. **categories** - Product categories
3. **suppliers** - Supplier information
4. **products** - Product/Item information (ready for future inventory)
5. **stock** - Current stock levels (ready for inventory tracking)
6. **stock_movements** - Stock movement tracking (Enhanced Stock Monitoring)
7. **sales** - Sales transactions (Sales Validation)
8. **sale_items** - Individual items in sales
9. **purchases** - Purchase orders from suppliers
10. **purchase_items** - Individual items in purchases
11. **activity_logs** - System activity audit trail

---

## Installation Steps

### 1. Import Database

**Using phpMyAdmin:**
1. Open phpMyAdmin (usually at `http://localhost/phpmyadmin`)
2. Click on "Import" tab
3. Choose file: `database/shukran_cafe.sql`
4. Click "Go" button

**Using MySQL Command Line:**
```bash
mysql -u root -p < database/shukran_cafe.sql
```

### 2. Verify Database Connection

The database connection is configured in:
```
## Default Credentials

### Admin Account
- **Username:** `admin`
- **Password:** `admin123`
### 2. Database Helper Functions
 `get_current_user_data()` - Get current user info

- `db_query()` - Execute prepared statements
- `db_fetch_one()` - Fetch single row
- `db_fetch_all()` - Fetch all rows
- `db_insert()` - Insert data
- `db_update()` - Update data
- `db_delete()` - Delete data
- `log_activity()` - Log system activities
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
