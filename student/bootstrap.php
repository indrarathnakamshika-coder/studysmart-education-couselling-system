<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/flash.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'student') {
    header('Location: ../admin_dashboard.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$studentName = $_SESSION['username'] ?? 'Student';

function studentColumnExists(mysqli $connection, string $table, string $column): bool
{
    $tableEscaped = mysqli_real_escape_string($connection, $table);
    $columnEscaped = mysqli_real_escape_string($connection, $column);
    $sql = "SHOW COLUMNS FROM `{$tableEscaped}` LIKE '{$columnEscaped}'";
    $result = mysqli_query($connection, $sql);

    return $result && mysqli_num_rows($result) > 0;
}

function studentEnsureColumn(mysqli $connection, string $table, string $column, string $definition): void
{
    if (!studentColumnExists($connection, $table, $column)) {
        mysqli_query($connection, "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS student_profiles (
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
    notes TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

studentEnsureColumn($conn, 'student_profiles', 'education_qualification', "VARCHAR(40) NOT NULL DEFAULT ''");
studentEnsureColumn($conn, 'student_profiles', 'al_pass_count', 'INT UNSIGNED NULL DEFAULT NULL');
studentEnsureColumn($conn, 'student_profiles', 'progression_level', 'TINYINT UNSIGNED NULL DEFAULT NULL');
studentEnsureColumn($conn, 'student_profiles', 'eligible_category', "VARCHAR(160) NOT NULL DEFAULT ''");
studentEnsureColumn($conn, 'student_profiles', 'profile_completed', 'TINYINT(1) NOT NULL DEFAULT 0');
studentEnsureColumn($conn, 'student_profiles', 'interests', "VARCHAR(500) NOT NULL DEFAULT ''");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS course_catalog (
    course_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_name VARCHAR(120) NOT NULL,
    field VARCHAR(100) NOT NULL,
    level VARCHAR(50) NOT NULL,
    mode VARCHAR(50) NOT NULL,
    average_fee DECIMAL(10,2) NOT NULL,
    duration_months INT UNSIGNED NOT NULL,
    description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS student_applications (
    application_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

studentEnsureColumn($conn, 'student_applications', 'applicant_name', "VARCHAR(160) NOT NULL DEFAULT ''");
studentEnsureColumn($conn, 'student_applications', 'applicant_email', "VARCHAR(160) NOT NULL DEFAULT ''");
studentEnsureColumn($conn, 'student_applications', 'applicant_phone', "VARCHAR(40) NOT NULL DEFAULT ''");
studentEnsureColumn($conn, 'student_applications', 'applicant_message', 'TEXT NULL');
studentEnsureColumn($conn, 'student_applications', 'payment_status', "VARCHAR(30) NOT NULL DEFAULT 'Unpaid'");
studentEnsureColumn($conn, 'student_applications', 'intake_id', 'INT UNSIGNED NULL DEFAULT NULL');

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS student_payments (
    payment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    registration_fee DECIMAL(10,2) NOT NULL DEFAULT 2500.00,
    payment_plan VARCHAR(20) NOT NULL DEFAULT 'Full',
    payment_method VARCHAR(30) DEFAULT 'Not selected',
    payment_status VARCHAR(30) NOT NULL DEFAULT 'Unpaid',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS student_payment_history (
    history_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    payment_plan VARCHAR(20) NOT NULL,
    payment_method VARCHAR(30) NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
    status_note VARCHAR(80) NOT NULL DEFAULT 'Recorded',
    paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

studentEnsureColumn($conn, 'student_payment_history', 'application_id', 'INT UNSIGNED NULL DEFAULT NULL');

studentEnsureColumn($conn, 'course_catalog', 'fees', 'DECIMAL(10,2) DEFAULT 0.00');
studentEnsureColumn($conn, 'course_catalog', 'status', "VARCHAR(20) DEFAULT 'Active'");
studentEnsureColumn($conn, 'course_catalog', 'category', "VARCHAR(100) NOT NULL DEFAULT ''");
studentEnsureColumn($conn, 'course_catalog', 'qualification_required', "VARCHAR(160) NOT NULL DEFAULT ''");
studentEnsureColumn($conn, 'course_catalog', 'stream_required', "VARCHAR(160) NOT NULL DEFAULT ''");
studentEnsureColumn($conn, 'course_catalog', 'registration_fee', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
studentEnsureColumn($conn, 'course_catalog', 'installment_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
studentEnsureColumn($conn, 'course_catalog', 'duration_months', 'INT UNSIGNED NOT NULL DEFAULT 0');

mysqli_query($conn, "ALTER TABLE student_payments ADD COLUMN IF NOT EXISTS payment_plan VARCHAR(20) NOT NULL DEFAULT 'Full'");
studentEnsureColumn($conn, 'student_payments', 'application_id', 'INT UNSIGNED NULL DEFAULT NULL');

$seedCountResult = mysqli_query($conn, "SELECT COUNT(*) AS total_courses FROM course_catalog");
$seedRow = $seedCountResult ? mysqli_fetch_assoc($seedCountResult) : ['total_courses' => 0];

if ((int) ($seedRow['total_courses'] ?? 0) === 0) {
    mysqli_query($conn, "INSERT INTO course_catalog (course_name, field, level, mode, average_fee, duration_months, description) VALUES
        ('BSc Computer Science', 'Computer Science', 'Undergraduate', 'Full-time', 450000.00, 48, 'Core computing, software development, and data systems.'),
        ('BBA Finance', 'Business', 'Undergraduate', 'Full-time', 380000.00, 36, 'Financial management, accounting, and business strategy.'),
        ('Diploma in Graphic Design', 'Design', 'Diploma', 'Part-time', 210000.00, 18, 'Creative design, branding, and digital media skills.'),
        ('MSc Data Science', 'Data Science', 'Postgraduate', 'Full-time', 620000.00, 24, 'Machine learning, analytics, and AI applications.'),
        ('Certificate in Cybersecurity', 'Cybersecurity', 'Certificate', 'Online', 180000.00, 12, 'Security fundamentals, threat detection, and incident response.')");
}

// Keep legacy typos normalized and ensure additional catalog options exist.
mysqli_query($conn, "UPDATE course_catalog SET course_name = 'Diploma in Graphic Design'
    WHERE LOWER(REPLACE(course_name, ' ', '')) IN ('diplomaingraphicdesign', 'diplomaingraphicdesigns')");

$bioMedicalCheck = mysqli_prepare($conn, "SELECT course_id FROM course_catalog WHERE course_name = ? LIMIT 1");
if ($bioMedicalCheck) {
    $bioMedicalName = 'BEng Biomedical Engineering';
    mysqli_stmt_bind_param($bioMedicalCheck, 's', $bioMedicalName);
    mysqli_stmt_execute($bioMedicalCheck);
    $bioMedicalRes = mysqli_stmt_get_result($bioMedicalCheck);
    $existsBioMedical = $bioMedicalRes && mysqli_num_rows($bioMedicalRes) > 0;
    mysqli_stmt_close($bioMedicalCheck);

    if (!$existsBioMedical) {
        $bioMedicalInsert = mysqli_prepare($conn, "INSERT INTO course_catalog (course_name, field, level, mode, average_fee, duration_months, description)
            VALUES (?, 'Engineering', 'Undergraduate', 'Full-time', 540000.00, 48, 'Biomedical systems, medical devices, and healthcare engineering fundamentals.')");
        if ($bioMedicalInsert) {
            mysqli_stmt_bind_param($bioMedicalInsert, 's', $bioMedicalName);
            mysqli_stmt_execute($bioMedicalInsert);
            mysqli_stmt_close($bioMedicalInsert);
        }
    }
}

$profileQuery = mysqli_prepare($conn, "SELECT * FROM student_profiles WHERE user_id = ? LIMIT 1");
$profile = null;
if ($profileQuery) {
    mysqli_stmt_bind_param($profileQuery, 'i', $userId);
    mysqli_stmt_execute($profileQuery);
    $result = mysqli_stmt_get_result($profileQuery);
    if ($result && mysqli_num_rows($result) > 0) {
        $profile = mysqli_fetch_assoc($result);
        if (!empty($profile['full_name'])) {
            $studentName = $profile['full_name'];
        }
    }
    mysqli_stmt_close($profileQuery);
}

$applicationsCount = 0;
$applicationsStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total_applications FROM student_applications WHERE user_id = ?");
if ($applicationsStmt) {
    mysqli_stmt_bind_param($applicationsStmt, 'i', $userId);
    mysqli_stmt_execute($applicationsStmt);
    $applicationsResult = mysqli_stmt_get_result($applicationsStmt);
    $applicationsRow = $applicationsResult ? mysqli_fetch_assoc($applicationsResult) : null;
    $applicationsCount = (int) ($applicationsRow['total_applications'] ?? 0);
    mysqli_stmt_close($applicationsStmt);
}

$recommendedCount = 0;
if ($profile !== null && isStudentProfileComplete($profile)) {
    $recRes = mysqli_query($conn, 'SELECT level, qualification_required, stream_required, course_name, category, status FROM course_catalog');
    if ($recRes) {
        $pl = (int) ($profile['progression_level'] ?? 0);
        while ($row = mysqli_fetch_assoc($recRes)) {
            if (!empty($row['status']) && $row['status'] === 'Inactive') {
                continue;
            }
            if ($pl >= 1 && $pl <= 4 && courseMatchesStudentProgression($pl, $row)) {
                $recommendedCount++;
            }
        }
    }
}

$paymentStatus = 'Unpaid';
$paymentMethod = 'Not selected';
$registrationFee = 2500.00;

$paymentPlan = 'Full';
$paymentSelect = mysqli_prepare($conn, "SELECT registration_fee, payment_plan, payment_method, payment_status FROM student_payments WHERE user_id = ? LIMIT 1");
if ($paymentSelect) {
    mysqli_stmt_bind_param($paymentSelect, 'i', $userId);
    mysqli_stmt_execute($paymentSelect);
    $paymentResult = mysqli_stmt_get_result($paymentSelect);
    if ($paymentResult && mysqli_num_rows($paymentResult) > 0) {
        $paymentRow = mysqli_fetch_assoc($paymentResult);
        $paymentStatus = $paymentRow['payment_status'] ?? $paymentStatus;
        $paymentPlan = $paymentRow['payment_plan'] ?? $paymentPlan;
        $paymentMethod = $paymentRow['payment_method'] ?? $paymentMethod;
        $registrationFee = (float) ($paymentRow['registration_fee'] ?? $registrationFee);
    }
    mysqli_stmt_close($paymentSelect);
}

function profileCompletionPercent(?array $profileData): int
{
    if ($profileData === null) {
        return 0;
    }

    $fields = [
        'full_name', 'phone', 'date_of_birth', 'address', 'city', 'country',
        'highest_education', 'institution', 'graduation_year', 'gpa',
        'financial_status', 'budget_range', 'study_mode',
        'education_qualification', 'interests',
    ];
    $filled = 0;
    foreach ($fields as $field) {
        $value = trim((string) ($profileData[$field] ?? ''));
        if ($value !== '') {
            $filled++;
        }
    }

    return (int) round(($filled / count($fields)) * 100);
}

$profileCompletion = profileCompletionPercent($profile);

/**
 * @return array{0: int, 1: string} Progression level (1–4) and human-readable eligible category.
 */
function computeStudentProgression(string $qualification, ?int $alPassCount): array
{
    $passes = (int) ($alPassCount ?? 0);
    if ($qualification === 'below_ol') {
        return [1, 'skill-based courses'];
    }
    if ($qualification === 'ol') {
        return [2, 'foundation programs'];
    }
    if ($qualification === 'al') {
        if ($passes >= 3) {
            return [4, 'degree programs'];
        }

        return [3, 'HND programs'];
    }

    return [1, 'skill-based courses'];
}

/**
 * Builds one string from all admin fields that describe pathway, so "Foundation" in the course name still counts.
 */
function courseCatalogTierText(array $row): string
{
    return strtolower(trim(
        ($row['level'] ?? '') . ' ' .
        ($row['qualification_required'] ?? '') . ' ' .
        ($row['stream_required'] ?? '') . ' ' .
        ($row['course_name'] ?? '') . ' ' .
        ($row['category'] ?? '')
    ));
}

/**
 * Classifies a course catalog "level" (often copied from admin qualification text) into a strict pathway tier.
 * Used so recommendations match one band only: L1 certificate, L2 foundation, L3 HND, L4 degree.
 *
 * @return string One of: certificate, foundation, hnd, undergraduate, postgraduate, unknown
 */
function classifyCourseRecommendationTier(string $raw): string
{
    $s = strtolower(trim(preg_replace('/\s+/', ' ', $raw)));
    if ($s === '') {
        return 'unknown';
    }
    $exactMap = [
        'certificate' => 'certificate',
        'foundation' => 'foundation',
        'hnd' => 'hnd',
        'diploma' => 'hnd',
        'undergraduate' => 'undergraduate',
        'postgraduate' => 'postgraduate',
    ];
    if (isset($exactMap[$s])) {
        return $exactMap[$s];
    }

    $postgraduate = ['postgraduate', 'post graduate', 'msc', 'm.sc', 'mba', 'phd', 'ph.d', 'master', 'masters', 'm.phil', 'doctorate'];
    foreach ($postgraduate as $k) {
        if (strpos($s, $k) !== false) {
            return 'postgraduate';
        }
    }
    $undergraduate = ['undergraduate', 'bachelor', 'bsc', 'b.sc', 'beng', 'bba', 'b.com', 'degree', 'honours', 'hons', 'llb', 'll.b', 'b.arch'];
    foreach ($undergraduate as $k) {
        if (strpos($s, $k) !== false) {
            return 'undergraduate';
        }
    }
    $foundationHints = [
        'foundation',
        'foundational',
        'pre-university',
        'pre university',
        'preuniversity',
        'foundation year',
        'foundation programme',
        'foundation program',
        'foundation course',
        'foundation studies',
        'foundation level',
        'uni foundation',
        'university foundation',
    ];
    foreach ($foundationHints as $hint) {
        if (strpos($s, $hint) !== false) {
            return 'foundation';
        }
    }
    if (strpos($s, 'hnd') !== false) {
        return 'hnd';
    }
    $hndLike = ['diploma', 'associate', 'advanced diploma', 'higher national'];
    foreach ($hndLike as $k) {
        if (strpos($s, $k) !== false) {
            return 'hnd';
        }
    }
    $certificate = ['certificate', 'cert', 'skill', 'short course', 'o/l', 'o level', 'ordinary level', 'ordinary'];
    foreach ($certificate as $k) {
        if (strpos($s, $k) !== false) {
            return 'certificate';
        }
    }

    return 'unknown';
}

/**
 * @param array|string $courseLevelOrRow Full course_catalog row (preferred) or legacy level string only
 */
function courseMatchesStudentProgression(int $progressionLevel, $courseLevelOrRow): bool
{
    if (is_array($courseLevelOrRow)) {
        $tier = classifyCourseRecommendationTier(courseCatalogTierText($courseLevelOrRow));
    } else {
        $tier = classifyCourseRecommendationTier((string) $courseLevelOrRow);
    }
    switch ($progressionLevel) {
        case 1:
            return $tier === 'certificate';
        case 2:
            return $tier === 'foundation';
        case 3:
            return $tier === 'hnd';
        case 4:
            return $tier === 'undergraduate' || $tier === 'postgraduate';
        default:
            return false;
    }
}

function isStudentProfileComplete(?array $profileData): bool
{
    if ($profileData === null) {
        return false;
    }
    if ((int) ($profileData['profile_completed'] ?? 0) === 1) {
        return true;
    }
    $qual = trim((string) ($profileData['education_qualification'] ?? ''));
    $financial = trim((string) ($profileData['financial_status'] ?? ''));
    $interests = trim((string) ($profileData['interests'] ?? ''));

    return $qual !== '' && $financial !== '' && $interests !== '';
}

$profileComplete = isStudentProfileComplete($profile);
?>
