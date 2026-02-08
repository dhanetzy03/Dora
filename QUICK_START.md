# 🚀 QUICK START GUIDE

## Step 1: Run Database Migration

**CRITICAL: Do this first before using new features!**

1. Open **phpMyAdmin** (http://localhost/phpmyadmin)
2. Click on `shukran_cafe` database on the left
3. Click **SQL** tab at the top
4. Click **Choose File** or open the import section
5. Navigate to:
   ```
   C:\to replace files\Dora\database\migrations\contentSchemas\9_add_shelf_life_and_spoilage.sql
   ```
6. Click **Go** or **Import**
7. Wait for success message

**OR Copy/Paste Method:**
1. Open the file `9_add_shelf_life_and_spoilage.sql` in a text editor
2. Copy all content
3. Go to phpMyAdmin → shukran_cafe → SQL tab
4. Paste the content
5. Click **Go**

---

## Step 2: Test New Features

### Test 1: Shelf Life Tracking
1. Login as admin
2. Go to **Inventory** page
3. Click **Add Item**
4. Fill in:
   - Item Code: TEST001
   - Item Name: Test Milk
   - Category: Perishable
   - Unit: L
   - Stock Qty: 10
   - Cost: 50
   - Reorder Level: 5
   - **Shelf Life (Days): 7** ← NEW!
   - **Date Received: [Today's date]** ← NEW!
5. Click **Add Item**
6. Check if expiry date appears in table

### Test 2: Spoilage Recording
1. Go to **Spoilage** page from sidebar
2. Select an inventory item
3. Enter quantity to spoil
4. Select reason (e.g., Expired)
5. Click **🗑️ Record Spoilage**
6. Verify:
   - Inventory reduced
   - Record appears in table
   - Financial loss calculated

### Test 3: Beginning/Ending Inventory
1. Go to **Beg/End Inventory** from sidebar
2. Click **📸 Capture Beginning Inventory**
3. Wait for success message
4. Later in day, click **📸 Capture Ending Inventory**
5. Select date to see comparison

### Test 4: Reports
1. Go to **Reports** from sidebar
2. Try each report type:
   - 💰 Sales Report
   - 📦 Inventory Movement
   - 🗑️ Spoilage Report
   - ⚠️ Expiry Alerts
   - 📊 Stock Levels
   - 📈 Beginning/Ending

---

## Step 3: Daily Workflow

**Every Morning:**
- [ ] Capture Beginning Inventory
- [ ] Check Expiry Alerts Report

**During Day:**
- [ ] Record any spoilage immediately
- [ ] Add stock when received
- [ ] Process sales as normal

**Every Evening:**
- [ ] Capture Ending Inventory
- [ ] Review Spoilage Report
- [ ] Check Stock Levels Report

---

## Navigation Quick Links

From Admin Dashboard:

| Feature | Menu Item | Icon |
|---------|-----------|------|
| Add/Edit Items with Shelf Life | **Inventory** | 📦 |
| Record Spoilage | **Spoilage** | 🗑️ |
| Daily Snapshots | **Beg/End Inventory** | 📊 |
| View All Reports | **Reports** | 📈 |

---

## Common Questions

**Q: Do I need to enter shelf life for all items?**
A: No, it's optional. Enter it for perishable items only.

**Q: What happens if an item expires?**
A: It shows ⚠️ warning in inventory table and appears in Expiry Alert Report.

**Q: Can I edit past spoilage records?**
A: No, but you can view them in Reports. Always record accurately.

**Q: How often should I create snapshots?**
A: Daily recommended. Beginning at opening, Ending at closing.

**Q: Can I delete snapshots?**
A: Not through UI. Manual deletion in database if needed.

---

## System Requirements Check

✅ PHP 7.4 or higher
✅ MySQL database
✅ Apache server (XAMPP)
✅ Modern web browser

---

## 🎯 You're Ready!

All features are implemented and ready to use.
Start with the database migration, then explore!

**Need detailed info?** See `NEW_FEATURES_GUIDE.md`

---

**Last Updated:** January 27, 2026
**Version:** 2.0 with Shelf Life, Spoilage, Snapshots & Reports
