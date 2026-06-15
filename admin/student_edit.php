<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

/**
 * @return array{0: int, 1: string}
 */
function adminStudentProgression(string $qualification, ?int $alPassCount): array
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

$studentUserId = (int) ($_GET['id'] ?? 0);
if ($studentUserId <= 0) {
    header('Location: students.php');
    exit;
}

$userRow = null;
$studentRow = null;
$profile = null;

$uStmt = mysqli_prepare($conn, 'SELECT user_id, username, email, role FROM users WHERE user_id = ? LIMIT 1');
if ($uStmt) {
    mysqli_stmt_bind_param($uStmt, 'i', $studentUserId);
    mysqli_stmt_execute($uStmt);
    $uRes = mysqli_stmt_get_result($uStmt);
    $userRow = $uRes ? mysqli_fetch_assoc($uRes) : null;
    mysqli_stmt_close($uStmt);
}

if ($userRow === null || ($userRow['role'] ?? '') !== 'student') {
    header('Location: students.php');
    exit;
}

$sStmt = mysqli_prepare($conn, 'SELECT user_id, full_name, phone FROM students WHERE user_id = ? LIMIT 1');
if ($sStmt) {
    mysqli_stmt_bind_param($sStmt, 'i', $studentUserId);
    mysqli_stmt_execute($sStmt);
    $sRes = mysqli_stmt_get_result($sStmt);
    $studentRow = $sRes ? mysqli_fetch_assoc($sRes) : null;
    mysqli_stmt_close($sStmt);
}

$pStmt = mysqli_prepare($conn, 'SELECT * FROM student_profiles WHERE user_id = ? LIMIT 1');
if ($pStmt) {
    mysqli_stmt_bind_param($pStmt, 'i', $studentUserId);
    mysqli_stmt_execute($pStmt);
    $pRes = mysqli_stmt_get_result($pStmt);
    $profile = $pRes && mysqli_num_rows($pRes) > 0 ? mysqli_fetch_assoc($pRes) : null;
    mysqli_stmt_close($pStmt);
}

$errors = [];
$saved = isset($_GET['saved']) && $_GET['saved'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $dateOfBirth = trim((string) ($_POST['date_of_birth'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $city = trim((string) ($_POST['city'] ?? ''));
    $country = trim((string) ($_POST['country'] ?? ''));
    $highestEducation = trim((string) ($_POST['highest_education'] ?? ''));
    $institution = trim((string) ($_POST['institution'] ?? ''));
    $graduationYear = trim((string) ($_POST['graduation_year'] ?? ''));
    $gpa = trim((string) ($_POST['gpa'] ?? ''));
    $financialStatus = trim((string) ($_POST['financial_status'] ?? ''));
    $budgetRange = trim((string) ($_POST['budget_range'] ?? ''));
    $studyMode = trim((string) ($_POST['study_mode'] ?? ''));
    $preferredField = trim((string) ($_POST['preferred_field'] ?? ''));
    $preferredLevel = trim((string) ($_POST['preferred_level'] ?? ''));
    $interests = trim((string) ($_POST['interests'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));

    $educationQualification = trim((string) ($_POST['education_qualification'] ?? ''));
    $allowedQual = ['below_ol', 'ol', 'al', ''];
    if (!in_array($educationQualification, $allowedQual, true)) {
        $educationQualification = '';
    }
    $alPassCount = null;
    if ($educationQualification === 'al') {
        $alPassCount = min(5, max(0, (int) ($_POST['al_pass_count'] ?? 0)));
    }

    if ($educationQualification === '') {
        $progressionLevel = 0;
        $eligibleCategory = '';
    } else {
        [$progressionLevel, $eligibleCategory] = adminStudentProgression($educationQualification, $alPassCount);
    }

    $profileCompleted = 0;
    if ($educationQualification !== '' && $financialStatus !== '' && $interests !== '') {
        $profileCompleted = 1;
    }

    if ($username === '' || $email === '') {
        $errors[] = 'Username and email are required.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters, or leave blank to keep the current password.';
    }

    if (empty($errors)) {
        $dup = mysqli_prepare($conn, 'SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id <> ? LIMIT 1');
        if ($dup) {
            mysqli_stmt_bind_param($dup, 'ssi', $username, $email, $studentUserId);
            mysqli_stmt_execute($dup);
            $dupRes = mysqli_stmt_get_result($dup);
            if ($dupRes && mysqli_num_rows($dupRes) > 0) {
                $errors[] = 'Another account already uses this username or email.';
            }
            mysqli_stmt_close($dup);
        }
    }

    if (empty($errors)) {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $upUser = mysqli_prepare($conn, 'UPDATE users SET username = ?, email = ?, password = ? WHERE user_id = ? AND role = ?');
            if ($upUser) {
                $roleStudent = 'student';
                mysqli_stmt_bind_param($upUser, 'sssis', $username, $email, $hash, $studentUserId, $roleStudent);
                mysqli_stmt_execute($upUser);
                mysqli_stmt_close($upUser);
            }
        } else {
            $upUser = mysqli_prepare($conn, 'UPDATE users SET username = ?, email = ? WHERE user_id = ? AND role = ?');
            if ($upUser) {
                $roleStudent = 'student';
                mysqli_stmt_bind_param($upUser, 'ssis', $username, $email, $studentUserId, $roleStudent);
                mysqli_stmt_execute($upUser);
                mysqli_stmt_close($upUser);
            }
        }

        $upStu = mysqli_prepare($conn, 'UPDATE students SET full_name = ?, phone = ? WHERE user_id = ?');
        if ($upStu) {
            mysqli_stmt_bind_param($upStu, 'ssi', $fullName, $phone, $studentUserId);
            mysqli_stmt_execute($upStu);
            $aff = mysqli_stmt_affected_rows($upStu);
            mysqli_stmt_close($upStu);
            if ($aff === 0) {
                $insStu = mysqli_prepare($conn, 'INSERT INTO students (user_id, full_name, phone) VALUES (?, ?, ?)');
                if ($insStu) {
                    mysqli_stmt_bind_param($insStu, 'iss', $studentUserId, $fullName, $phone);
                    mysqli_stmt_execute($insStu);
                    mysqli_stmt_close($insStu);
                }
            }
        }

        $alPassForBind = $educationQualification === 'al' ? (int) $alPassCount : 0;
        $progressionForBind = $educationQualification === '' ? 0 : (int) $progressionLevel;

        $saveSql = "INSERT INTO student_profiles (
            user_id, full_name, phone, date_of_birth, address, city, country,
            highest_education, institution, graduation_year, gpa, financial_status,
            budget_range, study_mode, preferred_field, preferred_level, interests, notes,
            education_qualification, al_pass_count, progression_level, eligible_category, profile_completed
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            full_name = VALUES(full_name),
            phone = VALUES(phone),
            date_of_birth = VALUES(date_of_birth),
            address = VALUES(address),
            city = VALUES(city),
            country = VALUES(country),
            highest_education = VALUES(highest_education),
            institution = VALUES(institution),
            graduation_year = VALUES(graduation_year),
            gpa = VALUES(gpa),
            financial_status = VALUES(financial_status),
            budget_range = VALUES(budget_range),
            study_mode = VALUES(study_mode),
            preferred_field = VALUES(preferred_field),
            preferred_level = VALUES(preferred_level),
            interests = VALUES(interests),
            notes = VALUES(notes),
            education_qualification = VALUES(education_qualification),
            al_pass_count = VALUES(al_pass_count),
            progression_level = VALUES(progression_level),
            eligible_category = VALUES(eligible_category),
            profile_completed = VALUES(profile_completed)";

        $saveStmt = mysqli_prepare($conn, $saveSql);
        if ($saveStmt) {
            mysqli_stmt_bind_param(
                $saveStmt,
                'i' . str_repeat('s', 18) . 'iisi',
                $studentUserId,
                $fullName,
                $phone,
                $dateOfBirth,
                $address,
                $city,
                $country,
                $highestEducation,
                $institution,
                $graduationYear,
                $gpa,
                $financialStatus,
                $budgetRange,
                $studyMode,
                $preferredField,
                $preferredLevel,
                $interests,
                $notes,
                $educationQualification,
                $alPassForBind,
                $progressionForBind,
                $eligibleCategory,
                $profileCompleted
            );
            if (!mysqli_stmt_execute($saveStmt)) {
                $errors[] = 'Could not save profile: ' . mysqli_stmt_error($saveStmt);
            }
            mysqli_stmt_close($saveStmt);
        } else {
            $errors[] = 'Could not prepare profile save: ' . mysqli_error($conn);
        }
    }

    if (empty($errors)) {
        header('Location: student_edit.php?id=' . $studentUserId . '&saved=1');
        exit;
    }

    $alPassForBindErr = $educationQualification === 'al' ? min(5, max(0, (int) ($_POST['al_pass_count'] ?? 0))) : 0;
    $userRow['username'] = $username;
    $userRow['email'] = $email;
    $studentRow = ['user_id' => $studentUserId, 'full_name' => $fullName, 'phone' => $phone];
    $profile = array_merge($profile ?? [], [
        'full_name' => $fullName,
        'phone' => $phone,
        'date_of_birth' => $dateOfBirth,
        'address' => $address,
        'city' => $city,
        'country' => $country,
        'highest_education' => $highestEducation,
        'institution' => $institution,
        'graduation_year' => $graduationYear,
        'gpa' => $gpa,
        'financial_status' => $financialStatus,
        'budget_range' => $budgetRange,
        'study_mode' => $studyMode,
        'preferred_field' => $preferredField,
        'preferred_level' => $preferredLevel,
        'interests' => $interests,
        'notes' => $notes,
        'education_qualification' => $educationQualification,
        'al_pass_count' => $alPassForBindErr,
    ]);
}

adminPageStart('Edit student', 'students', $adminName);
?>
<section class="admin-section">
    <p style="margin-bottom:12px;">
        <a class="btn btn-ghost btn-small" href="students.php">← Back to students</a>
    </p>

    <?php if ($saved && empty($errors)): ?>
        <div class="alert alert-success">Student details saved successfully.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <h2 style="margin-bottom:12px;">Edit student #<?php echo (int) $studentUserId; ?></h2>

    <form method="post" action="student_edit.php?id=<?php echo (int) $studentUserId; ?>" class="admin-form-stack">
        <input type="hidden" name="update_student" value="1">

        <h3 class="admin-form-section-title">Account</h3>
        <div class="admin-form-grid">
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input class="form-control" id="username" name="username" type="text" required
                    value="<?php echo htmlspecialchars((string) ($userRow['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" name="email" type="email" required
                    value="<?php echo htmlspecialchars((string) ($userRow['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="password">New password</label>
                <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" placeholder="Leave blank to keep current">
                <span class="admin-help-text">Minimum 8 characters when changing.</span>
            </div>
        </div>

        <h3 class="admin-form-section-title">Student record</h3>
        <div class="admin-form-grid">
            <div class="form-group">
                <label class="form-label" for="full_name">Full name</label>
                <input class="form-control" id="full_name" name="full_name" type="text"
                    value="<?php echo htmlspecialchars((string) ($studentRow['full_name'] ?? $profile['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="phone">Phone</label>
                <input class="form-control" id="phone" name="phone" type="text"
                    value="<?php echo htmlspecialchars((string) ($studentRow['phone'] ?? $profile['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>

        <h3 class="admin-form-section-title">Profile</h3>
        <div class="admin-form-grid">
            <div class="form-group">
                <label class="form-label" for="date_of_birth">Date of birth</label>
                <input class="form-control" id="date_of_birth" name="date_of_birth" type="date"
                    value="<?php echo htmlspecialchars((string) ($profile['date_of_birth'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group full">
                <label class="form-label" for="address">Address</label>
                <input class="form-control" id="address" name="address" type="text"
                    value="<?php echo htmlspecialchars((string) ($profile['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="city">City</label>
                <input class="form-control" id="city" name="city" type="text"
                    value="<?php echo htmlspecialchars((string) ($profile['city'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="country">Country</label>
                <input class="form-control" id="country" name="country" type="text"
                    value="<?php echo htmlspecialchars((string) ($profile['country'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>

        <div class="admin-form-grid">
            <div class="form-group">
                <label class="form-label" for="education_qualification">Education qualification</label>
                <select class="form-control" id="education_qualification" name="education_qualification">
                    <option value="">—</option>
                    <option value="below_ol" <?php echo (($profile['education_qualification'] ?? '') === 'below_ol') ? 'selected' : ''; ?>>Below O/L</option>
                    <option value="ol" <?php echo (($profile['education_qualification'] ?? '') === 'ol') ? 'selected' : ''; ?>>O/L completed</option>
                    <option value="al" <?php echo (($profile['education_qualification'] ?? '') === 'al') ? 'selected' : ''; ?>>Advanced Level (A/L)</option>
                </select>
            </div>
            <div class="form-group" id="al-pass-field" style="<?php echo (($profile['education_qualification'] ?? '') === 'al') ? '' : 'display:none;'; ?>">
                <label class="form-label" for="al_pass_count">A/L passes</label>
                <select class="form-control" id="al_pass_count" name="al_pass_count">
                    <?php for ($p = 0; $p <= 5; $p++): ?>
                        <option value="<?php echo $p; ?>" <?php echo ((int) ($profile['al_pass_count'] ?? 0) === $p) ? 'selected' : ''; ?>><?php echo $p; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="highest_education">Education (detail)</label>
                <input class="form-control" id="highest_education" name="highest_education" type="text"
                    value="<?php echo htmlspecialchars((string) ($profile['highest_education'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="institution">Institution</label>
                <input class="form-control" id="institution" name="institution" type="text"
                    value="<?php echo htmlspecialchars((string) ($profile['institution'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="graduation_year">Graduation year</label>
                <input class="form-control" id="graduation_year" name="graduation_year" type="text"
                    value="<?php echo htmlspecialchars((string) ($profile['graduation_year'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="gpa">GPA / grade</label>
                <input class="form-control" id="gpa" name="gpa" type="text"
                    value="<?php echo htmlspecialchars((string) ($profile['gpa'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>

        <div class="admin-form-grid">
            <div class="form-group full">
                <label class="form-label" for="interests">Interests &amp; goals</label>
                <textarea class="form-control" id="interests" name="interests" rows="3"><?php echo htmlspecialchars((string) ($profile['interests'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label" for="financial_status">Financial status</label>
                <select class="form-control" id="financial_status" name="financial_status">
                    <option value="">—</option>
                    <option value="Self-funded" <?php echo (($profile['financial_status'] ?? '') === 'Self-funded') ? 'selected' : ''; ?>>Self-funded</option>
                    <option value="Scholarship needed" <?php echo (($profile['financial_status'] ?? '') === 'Scholarship needed') ? 'selected' : ''; ?>>Scholarship needed</option>
                    <option value="Loan support" <?php echo (($profile['financial_status'] ?? '') === 'Loan support') ? 'selected' : ''; ?>>Loan support</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="budget_range">Budget range</label>
                <input class="form-control" id="budget_range" name="budget_range" type="text"
                    value="<?php echo htmlspecialchars((string) ($profile['budget_range'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="study_mode">Study mode</label>
                <select class="form-control" id="study_mode" name="study_mode">
                    <option value="">—</option>
                    <option value="Full-time" <?php echo (($profile['study_mode'] ?? '') === 'Full-time') ? 'selected' : ''; ?>>Full-time</option>
                    <option value="Part-time" <?php echo (($profile['study_mode'] ?? '') === 'Part-time') ? 'selected' : ''; ?>>Part-time</option>
                    <option value="Online" <?php echo (($profile['study_mode'] ?? '') === 'Online') ? 'selected' : ''; ?>>Online</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="preferred_field">Preferred field</label>
                <input class="form-control" id="preferred_field" name="preferred_field" type="text"
                    value="<?php echo htmlspecialchars((string) ($profile['preferred_field'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="preferred_level">Preferred level</label>
                <input class="form-control" id="preferred_level" name="preferred_level" type="text"
                    value="<?php echo htmlspecialchars((string) ($profile['preferred_level'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group full">
                <label class="form-label" for="notes">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="2"><?php echo htmlspecialchars((string) ($profile['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
        </div>

        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a class="btn btn-ghost" href="students.php">Cancel</a>
        </div>
    </form>

    <div class="admin-danger-zone" style="margin-top:28px;padding-top:20px;border-top:1px solid #fecaca;">
        <h3 class="admin-form-section-title" style="color:#b91c1c;">Delete student</h3>
        <p class="admin-help-text">Removes this account, profile, applications, and payment records linked to this user. This cannot be undone.</p>
        <form method="post" action="students.php" onsubmit="return confirm('Permanently delete this student and all related data?');">
            <input type="hidden" name="student_delete" value="1">
            <input type="hidden" name="user_id" value="<?php echo (int) $studentUserId; ?>">
            <button type="submit" class="btn btn-danger">Delete student</button>
        </form>
    </div>
</section>

<script>
(function () {
    var qual = document.getElementById('education_qualification');
    var alBlock = document.getElementById('al-pass-field');
    if (!qual || !alBlock) return;
    var sync = function () { alBlock.style.display = qual.value === 'al' ? '' : 'none'; };
    qual.addEventListener('change', sync);
    sync();
})();
</script>
<?php adminPageEnd(); ?>
