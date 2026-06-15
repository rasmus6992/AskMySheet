# AskMySheet

DEMO: https://rasmus6992.com/randomaistuff/talktoexcel/public/

A secure, framework-free PHP 8 application that accepts an Excel/CSV upload and lets the same browser session ask questions grounded only in the parsed workbook data.

## Included

- PHP 8.1+ OOP backend
- PhpSpreadsheet parser with a 5,000-row ceiling
- MySQL IP-based upload and question limits
- Atomic quota reservation to prevent concurrent bypasses
- Server-side compressed workbook context; raw files are not retained
- OpenAI Responses API integration through native cURL
- CSRF protection, prepared SQL, MIME/content validation, secure cookies, and restrictive response headers
- Responsive Tailwind CSS dashboard with drag-and-drop upload, dark mode, and AJAX chat

## Project layout

```text
public/                 Web document root
  index.php             Dashboard
  upload.php            Upload API
  ask.php               Question API
  status.php            Session/usage API
  assets/               Compiled CSS and JavaScript
src/                    Application classes
storage/contexts/       Compressed temporary workbook context
bootstrap.php           Environment, session, and security headers
schema.sql              MySQL schema
.env.example            Configuration template
```

## 1. Requirements

- PHP 8.1 or newer
- MySQL 8.0+
- Composer 2
- PHP extensions: curl, fileinfo, pdo_mysql, mbstring, zip, xml, gd, zlib
- Node.js is only needed when rebuilding Tailwind CSS; a compiled `public/assets/app.css` is included

## 2. Install dependencies

```bash
composer install --no-dev --optimize-autoloader
```

To rebuild the CSS after editing classes:

```bash
npm install
npm run build
```

## 3. Configure MySQL

Create/import the schema:

```bash
mysql -u root -p < schema.sql
```

Create a least-privilege application user if needed:

```sql
CREATE USER 'talk_to_excel'@'localhost' IDENTIFIED BY 'use-a-strong-password';
GRANT SELECT, INSERT, UPDATE, DELETE ON talk_to_excel.* TO 'talk_to_excel'@'localhost';
FLUSH PRIVILEGES;
```

## 4. Configure the application

```bash
cp .env.example .env
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Paste the generated value into `APP_KEY`, then set the database and OpenAI credentials.

Important settings:

- `MAX_ROWS=5000` is the physical row ceiling across the workbook.
- `MAX_CONTEXT_BYTES=1000000` prevents oversized model requests. A very wide workbook can therefore be truncated before 5,000 rows.
- `MAX_COLUMNS=100` and `MAX_CELL_CHARS=500` prevent pathological spreadsheets from consuming excessive memory/tokens.
- `CONTEXT_RETENTION_HOURS=24` controls temporary server-side context lifetime.
- `TRUSTED_PROXIES` must contain only exact IPs of reverse proxies you control. Otherwise the app intentionally uses `REMOTE_ADDR` and ignores spoofable forwarding headers.

## 5. Web server

Set the web server document root to the `public/` directory. Do not expose the project root, `.env`, `src/`, or `storage/`.

Example Apache virtual host:

```apache
<VirtualHost *:443>
    ServerName excel.example.com
    DocumentRoot /var/www/talk-to-your-excel/public

    <Directory /var/www/talk-to-your-excel/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Example Nginx location:

```nginx
root /var/www/talk-to-your-excel/public;
index index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}

location ~ /\. {
    deny all;
}
```

Give the PHP-FPM/web-server user write access only to the context directory:

```bash
chown -R www-data:www-data storage/contexts
chmod 700 storage/contexts
```

Also configure PHP itself (`php.ini` or the FPM pool) with at least:

```ini
upload_max_filesize = 10M
post_max_size = 11M
max_execution_time = 180
memory_limit = 512M
session.cookie_httponly = 1
session.cookie_samesite = Lax
```

## 6. Test

Open the site, upload a small workbook, and ask a question whose answer is visible in the sheet. Check the `ip_usage`, `uploads`, and `questions` tables to confirm counters and audit records.

## Behavior and security notes

- The IP is stored only as a keyed HMAC (`BINARY(32)`), not as plaintext.
- One upload and ten successful questions are allowed per IP. Failed workbook parsing and failed OpenAI calls release their reserved quota.
- The raw uploaded file remains only in PHP's temporary upload location and is not copied into application storage.
- Spreadsheet cells are treated as untrusted data. The prompt explicitly tells the model not to obey instructions found inside cells.
- Answers are rendered with `textContent`, preventing model-generated HTML from executing in the browser.
- IP limits can affect multiple users behind the same office/mobile NAT. For a commercial product, replace or combine IP limits with authenticated account limits.
- For financially critical totals, add a deterministic calculation/query layer rather than relying solely on an LLM to perform large aggregations.
