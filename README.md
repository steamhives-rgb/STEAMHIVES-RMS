# STEAMhives RMS

This repository contains a student result access system built with static HTML, client-side JavaScript, and PHP backend APIs.

## Files

- `student_access.html` — public student result access page
- `index.html` — landing or home page
- `admin.html` — admin interface page
- `api.php` — main backend API router
- `config.php` — database configuration and bootstrap helper
- `database.sql` — MySQL schema and database seed structure
- `db-adapter.js` — optional database adapter script (frontend/backend utility)

## How it works

- `student_access.html` loads the school list from `api.php?action=getSchools`
- The student enters a school and PIN, then the page calls `api.php?action=verifyPin`
- `api.php` uses `config.php` to connect to the MySQL database and return result records
- The page displays a preview and allows PDF download via jsPDF

## Setup on cPanel

1. Upload files
   - Put `index.html`, `admin.html`, `student_access.html`, `api.php`, `config.php`, `db-adapter.js`, and any supporting assets into `public_html` or the target web folder.

2. Create the database
   - Go to **MySQL® Databases** in cPanel.
   - Create a new database.
   - Create a new database user.
   - Add the user to the database with `ALL PRIVILEGES`.

3. Import the schema
   - Open **phpMyAdmin**.
   - Select the new database.
   - Use the **Import** tab to upload `database.sql`.

4. Configure `config.php`
   - Open `config.php` in cPanel File Manager or use FTP.
   - Update these values to match your new database:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'your_db_name');
     define('DB_USER', 'your_db_user');
     define('DB_PASS', 'your_db_password');
     ```
   - Save the file.

5. Test the deployment
   - Visit `https://yourdomain.com/student_access.html`
   - The page should load and populate the school dropdown.
   - Enter a valid PIN and verify the results display.

## Common cPanel issues

- `500` or blank page: check `error_log` in cPanel or the cPanel error logs.
- API failures: verify `config.php` database credentials and that the MySQL import completed.
- Path issues: if the site is in a subfolder, use the correct URL path.

## Local testing

To quickly test locally with PHP built-in server:

```bash
cd /Users/macbook/Documents/STEAMHIVES-RMS
php -S localhost:8000
```

Then open `http://localhost:8000/student_access.html` in your browser.

## Notes

- The public page uses CDN-hosted jsPDF libraries, so no server-side installation is required for PDF generation.
- `config.php` also enables sessions and CORS headers for API usage.
- Keep `config.php` credentials secure and do not commit real passwords to version control.
