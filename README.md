# EduManage - Student Management System

A beautiful, mobile-responsive, full-stack Student Management System built using PHP (Procedural), MySQL, CSS, and Bootstrap 5.

## 🚀 Features

- **Responsive Landing Page**: Sleek welcome portal highlighting the system features.
- **Secure Authentication**: Session-based admin login using bcrypt password hashing (`password_hash` & `password_verify`).
- **Interactive Dashboard**: Real-time statistics counters and list of the 5 most recent registrations.
- **CRUD Directory**: Complete student register containing full names, custom course tracks, admission dates, email, and phone validation.
- **Smart Search Filter**: Multi-keyword filtering querying names and Student IDs via prepared SQL statements.
- **Input Validation**: Front-end validation (Bootstrap 5) matched with robust backend validation checks (e.g., duplicate Student ID checks, email format checks, etc.).

---

## 🛠️ Installation & Setup

To run this application on your local machine using tools like **XAMPP**, **WAMP**, or standard PHP/MySQL:

### Step 1: Clone/Copy Project Files
Move the `SMS` folder into your web server's public directory:
- **XAMPP**: `C:\xampp\htdocs\SMS`
- **WAMP**: `C:\wamp64\www\SMS`

### Step 2: Database Setup (MySQL)
1. Start your Apache and MySQL servers.
2. Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
3. Click on the **SQL** tab and execute the contents of [schema.sql](schema.sql):
   - This creates a database named `student_db`.
   - Creates the `admin` and `students` tables.
   - Inserts a default administrator account.

*Alternatively, you can run the SQL query directly inside your MySQL client CLI:*
```bash
mysql -u root -p < schema.sql
```

### Step 3: Configure Database Credentials
Open [config/db.php](config/db.php) and modify the constants to match your database settings:
```php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Your MySQL username (default is root)
define('DB_PASSWORD', '');     // Your MySQL password (default is empty)
define('DB_NAME', 'student_db');
```

---

## 🔑 Administrative Login Credentials

Use the following credentials to access the protected sections of the application:

- **URL**: `http://localhost/SMS/login.php`
- **Username**: `admin`
- **Password**: `admin123`

---

## 📂 Project Structure

```text
SMS/
│
├── config/
│   └── db.php               # Database connection logic
│
├── css/
│   └── style.css            # Custom CSS for rich aesthetics, glassmorphism, & transitions
│
├── includes/
│   ├── header.php           # Page wrapper header containing navbar, links, and stylesheets
│   └── footer.php           # Page wrapper footer containing copyright and scripts
│
├── index.php                # Public Welcome page
├── login.php                # Admin authentication form
├── logout.php               # Ends the session and redirects to homepage
├── dashboard.php            # Analytics counts and recent logs
├── students.php             # Full directory with keyword search filter
├── add-student.php          # Form to enroll/register new student
├── edit-student.php         # Form to modify student data
├── delete-student.php       # Handles deletions securely in background
└── schema.sql               # Database definition file
```
