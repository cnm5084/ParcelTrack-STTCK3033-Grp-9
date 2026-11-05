# ParcelTrack Module 2

This package continues the step-by-step setup already completed in MySQL Workbench. It contains Module 2 source code and remaining shared database objects. It does not implement Module 1 login or Module 3 delivery/admin pages. Share it with teammates before integrating. No PHP/MySQL runtime testing has been performed in the generation environment. Run the tests below on your laptop; no tests are claimed passed.

## 1 Database setup

You previously created users, parcels, parcel_status_history and v_public_tracking. Keep those tables. In Workbench choose File > Open SQL Script and open database/remaining.sql. Execute all statements. This adds rate_limits, audit_logs, delivery_proofs and the two views without dropping your tables or records. IF NOT EXISTS does not repair an incompatible existing table. Stop and inspect any SQL error. You should have six tables and two views. MySQL 8.0.16 or later enforces the earlier weight CHECK constraint; confirm SELECT VERSION().

Use your existing MySQL Server on 127.0.0.1:3306. Leave XAMPP MySQL stopped and run XAMPP Apache.

## 2 Place files

Extract the package to C:\xampp\parceltrack-module2 (OUTSIDE htdocs). Open that folder in VS Code. public/ contains the web pages; src/, scripts/ and database/ must stay outside the web root.

In XAMPP click Apache Config > httpd.conf. Add at the end (adjust paths if XAMPP is installed elsewhere):

```apache
Alias /parceltrack-m2 "C:/xampp/parceltrack-module2/public"
<Directory "C:/xampp/parceltrack-module2/public">
    Options -Indexes
    AllowOverride None
    Require local
    DirectoryIndex index.php
</Directory>
```

Save and restart Apache. The URL is http://localhost/parceltrack-m2/ . Require local intentionally restricts this development alias to your laptop. The original htdocs/parceltrack test page can remain separately. Never place the entire extracted folder under htdocs.

## 3 Create a dedicated application database account

In Workbench, signed in as root, execute the following after replacing the password placeholder LOCALLY. Do not send or commit the password. Do not save real credential SQL in the repository. Use a unique password and the same value in private configuration.

```sql
CREATE USER 'parceltrack_app'@'127.0.0.1' IDENTIFIED BY 'REPLACE_WITH_YOUR_OWN_PASSWORD';
GRANT SELECT ON parceltrack.users TO 'parceltrack_app'@'127.0.0.1';
GRANT SELECT, INSERT ON parceltrack.parcels TO 'parceltrack_app'@'127.0.0.1';
GRANT SELECT, INSERT ON parceltrack.parcel_status_history TO 'parceltrack_app'@'127.0.0.1';
GRANT SELECT, INSERT, DELETE ON parceltrack.rate_limits TO 'parceltrack_app'@'127.0.0.1';
GRANT INSERT ON parceltrack.audit_logs TO 'parceltrack_app'@'127.0.0.1';
GRANT SELECT ON parceltrack.v_public_tracking TO 'parceltrack_app'@'127.0.0.1';
```

If the account exists, inspect it rather than recreating or dropping it. This account has Module 2 privileges only; coordinate separate privileges for M1, M3 and maintenance. Do not use root for normal web requests.

## 4 Private configuration

Create C:\xampp\parceltrack-private. Copy config.example.ini into it and rename the copy config.ini. Fill in your application database account and password. This file must remain outside htdocs and outside the repository. bootstrap.php reads it automatically. Non-default installations can set PARCELTRACK_CONFIG to another absolute private path.

PHP needs pdo_mysql and mbstring enabled (normally available through XAMPP). If you see Service temporarily unavailable, inspect XAMPP Apache's error log. Application errors deliberately do not reveal SQL or credentials in the browser. Confirm the config path and credentials locally. Never paste credentials when asking for help.

## 5 Verify PHP syntax on your laptop

Open PowerShell in the extracted project folder and run:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { & C:\xampp\php\php.exe -l $_.FullName }
```

Each should report No syntax errors detected. Then open http://localhost/parceltrack-m2/track.php . The form should appear. Database access occurs when you submit a lookup.

## 6 Connect Module 1 login

Member 1 must authenticate the seller, regenerate the session ID, and set:

```php
$_SESSION['user_id'] = (int) $user['user_id'];
$_SESSION['role'] = $user['role'];
```

Both modules must use the same PHP session name, storage and cookie path (use / for the local alias setup). Login should redirect to /parceltrack-m2/my_parcels.php. Module 2 checks the account's current role, active status and must_change_pwd in the database on every protected request. An unauthenticated seller page returns 401 as expected. There is no automatic login or auth bypass in this package.

For independent public-page testing, use scripts/create_demo_data.php from the CLI. It creates fictional disabled seller data, a Registered parcel and history, and prints the tracking number. That disabled account cannot use the seller pages. For authenticated tests, use two real TEST seller accounts created through M1, with fictional information only.

## 7 Test the full flow

Log in through M1 as seller A, open Register parcel, submit a valid Malaysian recipient phone and positive two-decimal weight. Registration inserts the parcel, initial Registered history and audit record in one transaction. The page redirects to parcel details. Copy the tracking number and use Public tracking. Search your parcel by token or recipient in My parcels.

Log in as seller B and try seller A's detail ID: expect Parcel unavailable with no private details. Ask M3 to update an A parcel through the agreed states; public tracking should show the new history. M3 must update current_status and history together. Display times are Malaysia time; database writes use UTC. Coordinate timezone settings in M1/M3.

Open tests/security-tests.csv and fill in actual results after running every test. Keep your own Git commits and understand/explain the code for the individual assessment.

## 8 How the controls work

PDO uses native prepared statements for input values. Every seller detail/search query constrains seller_id from the authenticated session. HTML output uses htmlspecialchars. Registration has a CSRF token and server-side validation. Tracking numbers use random_bytes(6), uppercase hex and a KC prefix, backed by a unique database constraint and bounded collision retry.

The public page queries only the public view for status/time. A random token is still a bearer reference: anyone holding it can see the permitted history. Keep recipient data out of URLs, logs and public responses. The view alone is not a permissions boundary if the same account can read base tables; the public endpoint's fixed query and limited output are essential.

Tracking accepts 20 attempts per IP per rolling minute, including malformed POST lookups. The proposed limit uses a MySQL advisory lock so concurrent requests from the same IP are counted together. Shared network users share the limit. Proxy headers are ignored. Review thresholds before any production deployment. IPs are used only for request limiting and auditing. Run scripts/cleanup_rate_limits.php daily to remove old attempts; security audit-log retention requires a group decision.

## 9 Retention maintenance

scripts/anonymise_delivered.php applies the project policy to delivered parcels at least 12 calendar months old. Run it daily using Windows Task Scheduler after testing with fake old data. It needs a separate CLI-only maintenance database account with SELECT/UPDATE on parcels, SELECT/DELETE on delivery_proofs and INSERT on audit_logs. Configure its own private INI via PARCELTRACK_CONFIG in the task environment. Do not grant those broader permissions to the public web account.

The script anonymises recipient fields, removes proof files/metadata, marks anonymised_at and audits the event. It assumes proof files have strict bare UUID filenames and live in C:/xampp/parceltrack-private/proofs. M3 must use the SAME directory/naming scheme. Change the private proofs_dir setting if needed. The task fails safely on invalid filenames or file removal errors; inspect and retry. Preserve backups only under an agreed personal-data retention policy. Failed/undelivered parcel retention remains a group decision.

## 10 Remaining shared work

M1: login/account security. M3: delivery/admin UI, secure uploads and assignment checks. Agree proof requirements for Failed, exact phone acceptance rules and schema changes. Dashboard view may omit zero-count states; M3 must display zero for missing statuses. Obtain the three-member rubric and missing SR section from the lecturer. This package does not guarantee rubric completion or marks.

Official PHP references:
- https://www.php.net/manual/en/pdo.prepare.php
- https://www.php.net/manual/en/function.random-bytes.php
- https://www.php.net/manual/en/function.htmlspecialchars.php
