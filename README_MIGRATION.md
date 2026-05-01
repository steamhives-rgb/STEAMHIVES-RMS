# MySQL Migration for STEAMhives RMS

This guide helps migrate data from the localStorage-based STEAMhives RMS to a MySQL database.

## Files

- `migrate.sql`: Contains the MySQL database schema (CREATE TABLE statements)
- `export_data.js`: JavaScript script to export current localStorage data to SQL INSERT statements

## Step 1: Set up MySQL Database

1. Create a MySQL database (e.g., `steamhives_rms`)
2. Run the `migrate.sql` file to create all tables:

```bash
mysql -u username -p database_name < migrate.sql
```

## Step 2: Export Data from localStorage

1. Open the STEAMhives RMS app in your browser
2. Open the browser console (F12 → Console)
3. Copy and paste the contents of `export_data.js` into the console
4. Run the export function:

```javascript
exportAllDataToSql();
```

This will generate SQL INSERT statements and copy them to your clipboard.

## Step 3: Import Data into MySQL

1. Save the generated SQL from Step 2 to a file (e.g., `data.sql`)
2. Import it into your MySQL database:

```bash
mysql -u username -p database_name < data.sql
```

## Database Schema Overview

- **schools**: School information
- **students**: Student records with biodata
- **results**: Full term results
- **midterm_results**: Midterm test results
- **settings**: Key-value settings per school
- **attendance_records**: Daily attendance records
- **result_pins**: Student result access pins
- **coupons**: Plan upgrade coupons

## Notes

- All image data (passports, signatures) are stored as base64 strings in LONGTEXT fields
- JSON fields store complex objects like subjects arrays and affective ratings
- Foreign keys ensure data integrity
- Indexes are created for common query patterns

## Post-Migration

After migration, you'll need to update the RMS app to use MySQL instead of localStorage. This would require backend API development and frontend changes to use AJAX calls instead of localStorage.</content>
<parameter name="filePath">/Users/macbook/Documents/STEAMHIVES-RMS/README_MIGRATION.md