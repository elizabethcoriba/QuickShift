# QuickShift — Job Scheduling & Shift Management System

A web-based job scheduling, shift application, and real-time messaging platform built with HTML, CSS, Vanilla JavaScript, PHP, and MySQL.

## Features
- **Role-Based Access:** Separate dashboards for **Business Owners** (post jobs, manage slots, review/hire applicants) and **Applicants** (browse shifts, apply, track status).
- **Multi-Layer Account Security:** Password hashing, Gmail 6-digit OTP verification (signup & password reset), 3 security questions, 15-minute idle session timeout, and progressive login/IP rate limiting.
- **Real-Time Messaging:** Built-in chat thread per job application with automated status notifications.

## Tech Stack
- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **Backend:** PHP 8+, PHPMailer (SMTP)
- **Database:** MySQL / MariaDB (`quickshift_db`)

## Local Setup (XAMPP)
1. Clone or extract this repository into `C:\xampp\htdocs\QuickShift`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`, create a database named `quickshift_db`, and import `quickshift_db.sql`.
4. Configure `db.php` (default: host `127.0.0.1`, user `root`, empty password) and add your Gmail SMTP credentials in `mailer.php` for OTP emails.
5. Open `http://localhost/QuickShift` in your browser.
