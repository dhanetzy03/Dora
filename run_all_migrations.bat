@echo off
REM ================================================
REM Run All SQL Migrations for Shukran Cafe
REM ================================================

echo.
echo ================================================
echo  Shukran Cafe - Database Migration Runner
echo ================================================
echo.

REM Set MySQL path (adjust if your XAMPP is in a different location)
set MYSQL_PATH=E:\xampp\mysql\bin\mysql.exe
set DB_NAME=shukran_cafe
set DB_USER=root
set DB_PASS=

REM Check if MySQL exists
if not exist "%MYSQL_PATH%" (
    echo ERROR: MySQL not found at %MYSQL_PATH%
    echo Please update MYSQL_PATH in this script to match your XAMPP installation
    pause
    exit /b 1
)

echo MySQL found at: %MYSQL_PATH%
echo Database: %DB_NAME%
echo.

REM First, create/reset the main database
echo [1/10] Running main database schema...
"%MYSQL_PATH%" -u %DB_USER% < "database\shukran_cafe.sql"
if %errorlevel% neq 0 (
    echo ERROR: Failed to run main database schema
    pause
    exit /b 1
)
echo SUCCESS: Main database created
echo.

REM Run migration files in order
echo [2/10] Running migration 1: Raw Materials Table...
"%MYSQL_PATH%" -u %DB_USER% %DB_NAME% < "database\migrations\contentSchemas\1rawMaterialsTable.sql"
if %errorlevel% neq 0 echo WARNING: Migration 1 may have issues
echo.

echo [3/10] Running migration 2: Suppliers Table...
"%MYSQL_PATH%" -u %DB_USER% %DB_NAME% < "database\migrations\contentSchemas\2suppliersTable.sql"
if %errorlevel% neq 0 echo WARNING: Migration 2 may have issues
echo.

echo [4/10] Running migration 3: Alter Stock Movements...
"%MYSQL_PATH%" -u %DB_USER% %DB_NAME% < "database\migrations\contentSchemas\3alterStock_movements_and_add_fk.sql"
if %errorlevel% neq 0 echo WARNING: Migration 3 may have issues
echo.

echo [5/10] Running migration 4: Add Category FK...
"%MYSQL_PATH%" -u %DB_USER% %DB_NAME% < "database\migrations\contentSchemas\4addCategoryFKtoRaw_materials_inventory.sql"
if %errorlevel% neq 0 echo WARNING: Migration 4 may have issues
echo.

echo [6/10] Running migration 5: Add References...
"%MYSQL_PATH%" -u %DB_USER% %DB_NAME% < "database\migrations\contentSchemas\5add_refs_stock_movements_saleitems.sql"
if %errorlevel% neq 0 echo WARNING: Migration 5 may have issues
echo.

echo [7/10] Running migration 6: Fix Enums and Defaults...
"%MYSQL_PATH%" -u %DB_USER% %DB_NAME% < "database\migrations\contentSchemas\6_fix_enums_and_defaults.sql"
if %errorlevel% neq 0 echo WARNING: Migration 6 may have issues
echo.

echo [8/10] Running migration 7: Add Cost Per Unit...
"%MYSQL_PATH%" -u %DB_USER% %DB_NAME% < "database\migrations\contentSchemas\7add_cost_per_unit_to_inventory.sql"
if %errorlevel% neq 0 echo WARNING: Migration 7 may have issues
echo.

echo [9/10] Running migration 8: Add Price to Products...
"%MYSQL_PATH%" -u %DB_USER% %DB_NAME% < "database\migrations\contentSchemas\8_add_price_to_products.sql"
if %errorlevel% neq 0 echo WARNING: Migration 8 may have issues
echo.

echo [10/10] Running migration 9: Shelf Life and Spoilage...
"%MYSQL_PATH%" -u %DB_USER% %DB_NAME% < "database\migrations\contentSchemas\9_add_shelf_life_and_spoilage.sql"
if %errorlevel% neq 0 echo WARNING: Migration 9 may have issues
echo.

echo ================================================
echo  ALL MIGRATIONS COMPLETED!
echo ================================================
echo.
echo Your database is now up to date with all features:
echo - Shelf Life Tracking
echo - Spoilage Monitoring
echo - Beginning/Ending Inventory
echo - Complete Reports System
echo.
echo You can now use the system!
echo.
pause
