<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/flash.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../student/dashboard.php');
    exit;
}

function adminExec(string $sql): void
{
    global $conn;
    mysqli_query($conn, $sql);
}

function adminColumnExists(string $table, string $column): bool
{
    global $conn;
    $tableEscaped = mysqli_real_escape_string($conn, $table);
    $columnEscaped = mysqli_real_escape_string($conn, $column);
    $sql = "SHOW COLUMNS FROM `{$tableEscaped}` LIKE '{$columnEscaped}'";
    $result = mysqli_query($conn, $sql);
    return $result && mysqli_num_rows($result) > 0;
}

adminExec("CREATE TABLE IF NOT EXISTS users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) DEFAULT '',
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student','admin') NOT NULL DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

adminExec("CREATE TABLE IF NOT EXISTS students (
    student_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    full_name VARCHAR(120) DEFAULT '',
    phone VARCHAR(20) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

adminExec("CREATE TABLE IF NOT EXISTS student_profiles (
    profile_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    full_name VARCHAR(120) DEFAULT '',
    phone VARCHAR(20) DEFAULT '',
    date_of_birth DATE NULL,
    address VARCHAR(255) DEFAULT '',
    city VARCHAR(100) DEFAULT '',
    country VARCHAR(100) DEFAULT '',
    highest_education VARCHAR(120) DEFAULT '',
    institution VARCHAR(120) DEFAULT '',
    graduation_year VARCHAR(10) DEFAULT '',
    gpa VARCHAR(10) DEFAULT '',
    financial_status VARCHAR(50) DEFAULT '',
    budget_range VARCHAR(50) DEFAULT '',
    study_mode VARCHAR(50) DEFAULT '',
    preferred_field VARCHAR(100) DEFAULT '',
    preferred_level VARCHAR(50) DEFAULT '',
    notes TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (!adminColumnExists('student_profiles', 'interests')) {
    adminExec("ALTER TABLE student_profiles ADD COLUMN interests VARCHAR(500) NOT NULL DEFAULT ''");
}
if (!adminColumnExists('student_profiles', 'education_qualification')) {
    adminExec("ALTER TABLE student_profiles ADD COLUMN education_qualification VARCHAR(40) NOT NULL DEFAULT ''");
}
if (!adminColumnExists('student_profiles', 'al_pass_count')) {
    adminExec('ALTER TABLE student_profiles ADD COLUMN al_pass_count INT UNSIGNED NULL DEFAULT NULL');
}
if (!adminColumnExists('student_profiles', 'progression_level')) {
    adminExec('ALTER TABLE student_profiles ADD COLUMN progression_level TINYINT UNSIGNED NULL DEFAULT NULL');
}
if (!adminColumnExists('student_profiles', 'eligible_category')) {
    adminExec("ALTER TABLE student_profiles ADD COLUMN eligible_category VARCHAR(160) NOT NULL DEFAULT ''");
}
if (!adminColumnExists('student_profiles', 'profile_completed')) {
    adminExec('ALTER TABLE student_profiles ADD COLUMN profile_completed TINYINT(1) NOT NULL DEFAULT 0');
}

adminExec("CREATE TABLE IF NOT EXISTS course_catalog (
    course_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_name VARCHAR(150) NOT NULL,
    category VARCHAR(100) DEFAULT '',
    duration_months INT UNSIGNED DEFAULT 0,
    qualification_required VARCHAR(120) DEFAULT '',
    stream_required VARCHAR(120) DEFAULT '',
    fees DECIMAL(10,2) DEFAULT 0.00,
    registration_fee DECIMAL(10,2) DEFAULT 0.00,
    installment_amount DECIMAL(10,2) DEFAULT 0.00,
    study_mode VARCHAR(50) DEFAULT '',
    intake_month VARCHAR(20) DEFAULT '',
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");


if (!adminColumnExists('course_catalog', 'duration_months')) {
    adminExec("ALTER TABLE course_catalog ADD COLUMN duration_months INT UNSIGNED DEFAULT 0");
}
if (!adminColumnExists('course_catalog', 'qualification_required')) {
    adminExec("ALTER TABLE course_catalog ADD COLUMN qualification_required VARCHAR(120) DEFAULT ''");
}
if (!adminColumnExists('course_catalog', 'stream_required')) {
    adminExec("ALTER TABLE course_catalog ADD COLUMN stream_required VARCHAR(120) DEFAULT ''");
}
if (!adminColumnExists('course_catalog', 'fees')) {
    adminExec("ALTER TABLE course_catalog ADD COLUMN fees DECIMAL(10,2) DEFAULT 0.00");
}
if (!adminColumnExists('course_catalog', 'registration_fee')) {
    adminExec("ALTER TABLE course_catalog ADD COLUMN registration_fee DECIMAL(10,2) DEFAULT 0.00");
}
if (!adminColumnExists('course_catalog', 'installment_amount')) {
    adminExec("ALTER TABLE course_catalog ADD COLUMN installment_amount DECIMAL(10,2) DEFAULT 0.00");
}
if (!adminColumnExists('course_catalog', 'study_mode')) {
    adminExec("ALTER TABLE course_catalog ADD COLUMN study_mode VARCHAR(50) DEFAULT ''");
}
if (!adminColumnExists('course_catalog', 'intake_month')) {
    adminExec("ALTER TABLE course_catalog ADD COLUMN intake_month VARCHAR(20) DEFAULT ''");
}
if (!adminColumnExists('course_catalog', 'status')) {
    adminExec("ALTER TABLE course_catalog ADD COLUMN status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active'");
}
if (!adminColumnExists('course_catalog', 'category')) {
    adminExec("ALTER TABLE course_catalog ADD COLUMN category VARCHAR(100) DEFAULT ''");
}
if (!adminColumnExists('course_catalog', 'description')) {
    adminExec("ALTER TABLE course_catalog ADD COLUMN description TEXT");
}


if (!adminColumnExists('course_catalog', 'field')) {
    adminExec("ALTER TABLE course_catalog ADD COLUMN field VARCHAR(100) DEFAULT ''");
}
if (!adminColumnExists('course_catalog', 'level')) {
    adminExec("ALTER TABLE course_catalog ADD COLUMN level VARCHAR(50) DEFAULT ''");
}
if (!adminColumnExists('course_catalog', 'mode')) {
    adminExec("ALTER TABLE course_catalog ADD COLUMN mode VARCHAR(50) DEFAULT ''");
}
if (!adminColumnExists('course_catalog', 'average_fee')) {
    adminExec("ALTER TABLE course_catalog ADD COLUMN average_fee DECIMAL(10,2) DEFAULT 0.00");
}


adminExec("UPDATE course_catalog
    SET category = field
    WHERE (category IS NULL OR category = '') AND field IS NOT NULL AND field <> ''");

adminExec("UPDATE course_catalog
    SET study_mode = mode
    WHERE (study_mode IS NULL OR study_mode = '') AND mode IS NOT NULL AND mode <> ''");

adminExec("UPDATE course_catalog
    SET stream_required = field
    WHERE (stream_required IS NULL OR stream_required = '') AND field IS NOT NULL AND field <> ''");

adminExec("UPDATE course_catalog
    SET qualification_required = level
    WHERE (qualification_required IS NULL OR qualification_required = '') AND level IS NOT NULL AND level <> ''");

adminExec("UPDATE course_catalog
    SET fees = average_fee
    WHERE (fees IS NULL OR fees = 0) AND average_fee IS NOT NULL AND average_fee > 0");

adminExec("UPDATE course_catalog
    SET registration_fee = 2500.00
    WHERE (registration_fee IS NULL OR registration_fee = 0)");

adminExec("UPDATE course_catalog
    SET installment_amount = CASE
        WHEN (installment_amount IS NULL OR installment_amount = 0) AND fees > 0 THEN ROUND(fees / 3, 2)
        ELSE installment_amount
    END");

adminExec("CREATE TABLE IF NOT EXISTS course_intakes (
    intake_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    intake_name VARCHAR(120) NOT NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    available_seats INT UNSIGNED DEFAULT 0,
    status ENUM('Open','Closed') NOT NULL DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

adminExec("CREATE TABLE IF NOT EXISTS student_applications (
    application_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    intake_id INT UNSIGNED NULL,
    status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (!adminColumnExists('student_applications', 'intake_id')) {
    adminExec("ALTER TABLE student_applications ADD COLUMN intake_id INT UNSIGNED NULL");
}

if (!adminColumnExists('student_applications', 'applicant_name')) {
    adminExec("ALTER TABLE student_applications ADD COLUMN applicant_name VARCHAR(160) NOT NULL DEFAULT ''");
}
if (!adminColumnExists('student_applications', 'applicant_email')) {
    adminExec("ALTER TABLE student_applications ADD COLUMN applicant_email VARCHAR(160) NOT NULL DEFAULT ''");
}
if (!adminColumnExists('student_applications', 'applicant_phone')) {
    adminExec("ALTER TABLE student_applications ADD COLUMN applicant_phone VARCHAR(40) NOT NULL DEFAULT ''");
}
if (!adminColumnExists('student_applications', 'applicant_message')) {
    adminExec("ALTER TABLE student_applications ADD COLUMN applicant_message TEXT NULL");
}
if (!adminColumnExists('student_applications', 'payment_status')) {
    adminExec("ALTER TABLE student_applications ADD COLUMN payment_status VARCHAR(30) NOT NULL DEFAULT 'Unpaid'");
}

adminExec("CREATE TABLE IF NOT EXISTS student_payments (
    payment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    application_id INT UNSIGNED NULL,
    registration_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method VARCHAR(30) DEFAULT 'Not selected',
    payment_status ENUM('Unpaid','Pending','Paid') NOT NULL DEFAULT 'Unpaid',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (!adminColumnExists('student_payments', 'application_id')) {
    adminExec("ALTER TABLE student_payments ADD COLUMN application_id INT UNSIGNED NULL");
}

if (!adminColumnExists('student_payments', 'payment_plan')) {
    adminExec("ALTER TABLE student_payments ADD COLUMN payment_plan VARCHAR(20) NOT NULL DEFAULT 'Full'");
}

$adminName = $_SESSION['username'] ?? 'Admin';
?>
