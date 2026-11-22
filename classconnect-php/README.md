# ClassConnect (PHP version)

ClassConnect is an online classroom. Teachers create classrooms, upload learning materials, and share a class code. Students join with the code and download materials from any device.

This version is built with **PHP, SQLite, HTML, CSS, and JavaScript**. It has no Python, no frameworks, and no build step.

## What you need

- **PHP 8.0 or newer** with the **PDO SQLite** and **SQLite3** extensions (both are included in the standard Windows download)
- A web browser

## Setup on Windows

1. **Download PHP.** Go to https://windows.php.net/download and download the **VS16 x64 Non Thread Safe** zip (the newest version listed).
2. **Extract** it to `C:\php` and add that folder to your PATH:
   Start menu → search "environment variables" → **Edit the system environment variables** → **Environment Variables** → select **Path** under *User variables* → **Edit** → **New** → `C:\php` → OK.
3. **Create the settings file.** In `C:\php`, copy `php.ini-development` and rename the copy to `php.ini`. Open `php.ini` in Notepad, make these changes (remove the leading `;` to turn a line on):
   ```
   extension_dir = "ext"
   extension=pdo_sqlite
   extension=sqlite3
   extension=fileinfo
   upload_max_filesize = 25M
   post_max_size = 26M
   ```
   Save the file.
4. **Check it works.** Open a new Command Prompt and run `php -v`. You should see the PHP version. Run `php -m` and confirm `pdo_sqlite` is in the list.

## Run ClassConnect

1. Extract `classconnect-php.zip` anywhere (for example, your Desktop).
2. Open Command Prompt in the `classconnect-php` folder. The quickest way is to click the address bar in File Explorer, type `cmd`, and press Enter.
3. Start the server:
   ```
   php -S localhost:8000 -t public
   ```
4. Open **http://localhost:8000** in your browser.
5. Keep the Command Prompt window open while you use the app. Press `Ctrl+C` there to stop it.

Register one account as a **Teacher** and another as a **Student** (use a private browser window for the second one, so both stay logged in).

## Running the tests

```
php tests\run-smoke.php
```
or, if you have the Linux/macOS tools, run the scripted browser test in `tests/smoke-test.sh`.

## Project layout

```
classconnect-php/
├── public/                 ← the only folder the web server exposes
│   ├── index.php           landing page
│   ├── register.php        create an account
│   ├── login.php           log in (with login throttling)
│   ├── logout.php          log out
│   ├── dashboard.php       teacher or student dashboard
│   ├── classroom.php       classroom page: info, class code, materials, students
│   ├── classroom-form.php  create or edit a classroom
│   ├── join.php            students join with a class code
│   ├── upload.php          teachers upload and manage materials
│   ├── material-edit.php   edit a material or replace its file
│   ├── download.php        downloads and previews, with access checks
│   ├── action.php          handles leave, remove student, regenerate code, deletes
│   └── assets/
│       ├── css/style.css   blue and white theme, responsive layout
│       └── js/app.js       menu, confirmations, copy code, file checks
├── app/                    ← private code (not web accessible)
│   ├── bootstrap.php       setup, sessions, security headers
│   ├── db.php              SQLite connection and tables
│   ├── auth.php            logins, roles, CSRF protection, throttling
│   ├── classrooms.php      classroom rules and queries
│   ├── materials.php       material rules and queries
│   ├── files.php           upload checks and storage
│   ├── helpers.php         validation, formatting, flash messages
│   ├── layout.php          page frame, cards, error pages
│   └── icons.php           inline SVG icons
├── storage/                ← created automatically (not web accessible)
│   ├── classconnect.sqlite database
│   ├── uploads/            uploaded files, with random names
│   └── sessions/           login sessions
└── tests/                  smoke tests
```

## How it works

- **Roles:** teachers manage only their own classrooms and materials. Students see only the classrooms they joined. Anyone else gets a "not found" page, so private classrooms are not revealed.
- **Uploads:** each file is checked by extension and by its contents, so a renamed program cannot pass as a PDF. Files are limited to 25 MB and stored under random names outside the public folder. They can only be downloaded through `download.php`, which checks access first.
- **Security:** passwords are hashed with `password_hash`. Every form has a CSRF token. Login attempts are limited (8 failures in 15 minutes per account and address). Pages send security headers, including a Content Security Policy.
- **Class codes** are 6 characters, use no look-alike letters or digits, and are checked for uniqueness. Teachers can regenerate a code at any time.

## Troubleshooting

- **"'php' is not recognized"**: PHP is not on your PATH. Reopen Command Prompt after changing PATH, or add `C:\php` again.
- **"could not find driver" or a PDO error**: enable `extension=pdo_sqlite` and `extension=sqlite3` in `php.ini`, then restart the server.
- **Uploads fail with "larger than the N MB upload limit"**: `php.ini` still has PHP's default limit. Set `upload_max_filesize = 25M` and `post_max_size = 26M`, then restart the server. The upload page shows a warning when this is the problem.
- **Changes to `php.ini` have no effect**: stop the server with `Ctrl+C` and start it again.
- **Port 8000 is busy**: use another port, for example `php -S localhost:8080 -t public`.

## Going live

For a real school or public site, use a hosting provider or a server with PHP and set:

- Serve the `public` folder as the site root, and keep `app` and `storage` outside it.
- Turn `display_errors` off in `php.ini` and keep errors in a log.
- Serve everything over HTTPS.
- Back up `storage/classconnect.sqlite` and `storage/uploads/` together.
