<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

$message = '';
$submitMode = '';
$setupMode = isset($_GET['setup']) && $_GET['setup'] === '1';
$isEditMode = isset($_GET['edit']) && $_GET['edit'] === '1';

if ($setupMode && isStudentProfileComplete($profile)) {
    header('Location: dashboard.php');
    exit;
}

$showProfileForm = $profile === null || $isEditMode || ($setupMode && !isStudentProfileComplete($profile));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $setupMode = isset($_POST['setup_context']) && $_POST['setup_context'] === '1';
    $submitMode = $_POST['submit_mode'] ?? 'save';
    $wasIncomplete = !isStudentProfileComplete($profile);
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $highestEducation = trim($_POST['highest_education'] ?? '');
    $institution = trim($_POST['institution'] ?? '');
    $graduationYear = trim($_POST['graduation_year'] ?? '');
    $gpa = trim($_POST['gpa'] ?? '');
    $financialStatus = trim($_POST['financial_status'] ?? '');
    $budgetRange = trim($_POST['budget_range'] ?? '');
    $studyMode = trim($_POST['study_mode'] ?? '');
    $interests = trim($_POST['interests'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $educationQualification = trim($_POST['education_qualification'] ?? '');
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
        [$progressionLevel, $eligibleCategory] = computeStudentProgression($educationQualification, $alPassCount);
    }

    $profileCompleted = 0;
    if ($educationQualification !== '' && $financialStatus !== '' && $interests !== '') {
        $profileCompleted = 1;
    }

    $saveSql = "INSERT INTO student_profiles (
            user_id, full_name, phone, date_of_birth, address, city, country,
            highest_education, institution, graduation_year, gpa, financial_status,
            budget_range, study_mode, interests, notes,
            education_qualification, al_pass_count, progression_level, eligible_category, profile_completed
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            interests = VALUES(interests),
            notes = VALUES(notes),
            education_qualification = VALUES(education_qualification),
            al_pass_count = VALUES(al_pass_count),
            progression_level = VALUES(progression_level),
            eligible_category = VALUES(eligible_category),
            profile_completed = VALUES(profile_completed)";

    $saveStmt = mysqli_prepare($conn, $saveSql);
    if ($saveStmt) {
        $alPassForBind = $educationQualification === 'al' ? $alPassCount : 0;
        $progressionForBind = $educationQualification === '' ? 0 : (int) $progressionLevel;
        $bindTypes = 'i' . str_repeat('s', 16) . 'iisi';
        mysqli_stmt_bind_param(
            $saveStmt,
            $bindTypes,
            $userId,
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
            $interests,
            $notes,
            $educationQualification,
            $alPassForBind,
            $progressionForBind,
            $eligibleCategory,
            $profileCompleted
        );
        mysqli_stmt_execute($saveStmt);
        mysqli_stmt_close($saveStmt);
        if ($profileCompleted === 1 && $wasIncomplete) {
            header('Location: dashboard.php?profile_complete=1');
            exit;
        }
        if ($setupMode && $profileCompleted === 0) {
            header('Location: profile.php?setup=1&saved=1');
            exit;
        }
        $modeQuery = $submitMode === 'update' ? 'update' : 'save';
        header('Location: profile.php?saved=1&mode=' . $modeQuery);
        exit;
    }
}

if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    $message = (isset($_GET['mode']) && $_GET['mode'] === 'update')
        ? 'Profile details updated successfully.'
        : 'Profile information saved successfully.';
    $isEditMode = false;
}

$refreshStmt = mysqli_prepare($conn, "SELECT * FROM student_profiles WHERE user_id = ? LIMIT 1");
if ($refreshStmt) {
    mysqli_stmt_bind_param($refreshStmt, 'i', $userId);
    mysqli_stmt_execute($refreshStmt);
    $refreshResult = mysqli_stmt_get_result($refreshStmt);
    $profile = $refreshResult && mysqli_num_rows($refreshResult) > 0 ? mysqli_fetch_assoc($refreshResult) : null;
    mysqli_stmt_close($refreshStmt);
}

studentPageStart('My Profile', 'profile', $studentName);
?>

<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php if ($setupMode && !$profileComplete): ?>
    <div class="student-section profile-setup-banner">
        <h2>Complete your profile</h2>
        <p class="help-text">Add your education qualification, interests, and payment preference so we can assign your progress level and recommend suitable courses.</p>
    </div>
<?php endif; ?>

<?php if ($profile !== null && !$showProfileForm): ?>
    <section class="student-section profile-wide">
        <div class="profile-headline">
            <h2>Profile Overview</h2>
            <span class="profile-pill">Recommendation-Focused Profile</span>
        </div>
        <div class="profile-view-grid">
            <div class="student-card">
                <h3>👤 Personal Information</h3>
                <p class="profile-item"><span class="profile-label">Name</span><span class="profile-value"><?php echo htmlspecialchars($profile['full_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></p>
                <p class="profile-item"><span class="profile-label">Phone</span><span class="profile-value"><?php echo htmlspecialchars($profile['phone'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></p>
                <p class="profile-item"><span class="profile-label">DOB</span><span class="profile-value"><?php echo htmlspecialchars($profile['date_of_birth'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></p>
                <p class="profile-item"><span class="profile-label">Address</span><span class="profile-value"><?php echo htmlspecialchars($profile['address'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></p>
            </div>
            <div class="student-card">
                <h3>🎓 Education Details</h3>
                <p class="profile-item"><span class="profile-label">Qualification</span><span class="profile-value"><?php
                    $q = (string) ($profile['education_qualification'] ?? '');
                    $qualLabels = ['below_ol' => 'Below O/L', 'ol' => 'O/L completed', 'al' => 'Advanced Level (A/L)'];
                    echo htmlspecialchars($qualLabels[$q] ?? ($profile['highest_education'] ?? '-'), ENT_QUOTES, 'UTF-8');
                ?></span></p>
                <?php if (($profile['education_qualification'] ?? '') === 'al'): ?>
                    <p class="profile-item"><span class="profile-label">A/L passes</span><span class="profile-value"><?php echo htmlspecialchars((string) ($profile['al_pass_count'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span></p>
                <?php endif; ?>
                <p class="profile-item"><span class="profile-label">Institution</span><span class="profile-value"><?php echo htmlspecialchars($profile['institution'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></p>
                <p class="profile-item"><span class="profile-label">Completed Year</span><span class="profile-value"><?php echo htmlspecialchars($profile['graduation_year'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></p>
                <p class="profile-item"><span class="profile-label">Grade</span><span class="profile-value"><?php echo htmlspecialchars($profile['gpa'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></p>
            </div>
            <div class="student-card">
                <h3>✨ Interests</h3>
                <p class="profile-item"><span class="profile-label">Focus areas</span><span class="profile-value"><?php echo htmlspecialchars($profile['interests'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></p>
            </div>
            <div class="student-card">
                <h3>🎯 Payment &amp; study</h3>
                <p class="profile-item"><span class="profile-label">Payment Preference</span><span class="profile-value"><?php echo htmlspecialchars($profile['financial_status'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></p>
                <p class="profile-item"><span class="profile-label">Budget</span><span class="profile-value"><?php echo htmlspecialchars($profile['budget_range'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></p>
                <p class="profile-item"><span class="profile-label">Study mode</span><span class="profile-value"><?php echo htmlspecialchars($profile['study_mode'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></p>
            </div>
        </div>

        <?php
        $prog = (int) ($profile['progression_level'] ?? 0);
        if ($prog >= 1 && $prog <= 4):
        ?>
            <div class="student-card profile-level-summary">
                <h3>📊 Your StudySmart level</h3>
                <p class="help-text">
                    <strong>Level <?php echo $prog; ?>.</strong>
                    You are eligible for <strong><?php echo htmlspecialchars($profile['eligible_category'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>.
                </p>
            </div>
        <?php endif; ?>

        <div class="profile-insight-grid">
            <div class="student-card profile-insight-card">
                <h3>📈 Profile Completion</h3>
                <p class="help-text">Your profile is <?php echo (int) $profileCompletion; ?>% complete.</p>
                <div class="profile-progress-track">
                    <div class="profile-progress-fill" style="width: <?php echo (int) $profileCompletion; ?>%;"></div>
                </div>
            </div>
            <div class="student-card profile-insight-card">
                <h3>🧭 Study preferences</h3>
                <p class="help-text"><strong>Study mode:</strong> <?php echo htmlspecialchars($profile['study_mode'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="help-text"><strong>Budget range:</strong> <?php echo htmlspecialchars($profile['budget_range'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="student-card profile-insight-card">
                <h3>✅ Recommendation Readiness</h3>
                <p class="help-text">
                    <?php if ($profileCompletion >= 80): ?>
                        Excellent. Your profile is highly ready for strong recommendation matching.
                    <?php elseif ($profileCompletion >= 60): ?>
                        Good. Add a few more details to unlock more accurate recommendations.
                    <?php else: ?>
                        In progress. Complete key fields to improve recommendation quality.
                    <?php endif; ?>
                </p>
                <p class="help-text"><strong>Matched Courses:</strong> <?php echo (int) $recommendedCount; ?></p>
            </div>
            <div class="student-card profile-insight-card">
                <h3>💡 Profile Tips</h3>
                <ul class="help-text profile-tips-list">
                    <li>Add a clear budget range for better financial-fit courses.</li>
                    <li>Choose study mode to align timetables with recommendations.</li>
                    <li>Keep qualification and education details updated.</li>
                </ul>
            </div>
        </div>

        <div class="profile-actions profile-actions-quick">
            <a class="btn-primary btn-action" href="profile.php?edit=1"><span class="btn-icon">✎</span><span>Edit Profile</span></a>
            <a class="btn-primary btn-action" href="recommendations.php"><span class="btn-icon">→</span><span>View Recommended Courses</span></a>
        </div>
    </section>
<?php else: ?>
    <section class="student-section profile-wide">
        <form method="post" action="profile.php">
            <?php if ($setupMode): ?>
                <input type="hidden" name="setup_context" value="1">
            <?php endif; ?>
            <div class="profile-section-title">
                <h2>Personal Information</h2>
            </div>
            <div class="profile-grid">
                <div class="form-group">
                    <label class="form-label" for="full_name">Full Name</label>
                    <input class="form-control" id="full_name" name="full_name" type="text" value="<?php echo htmlspecialchars($profile['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="phone">Phone</label>
                    <input class="form-control" id="phone" name="phone" type="text" value="<?php echo htmlspecialchars($profile['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="date_of_birth">Date of Birth</label>
                    <input class="form-control" id="date_of_birth" name="date_of_birth" type="date" value="<?php echo htmlspecialchars($profile['date_of_birth'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="address">Address</label>
                    <input class="form-control" id="address" name="address" type="text" value="<?php echo htmlspecialchars($profile['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="city">City</label>
                    <input class="form-control" id="city" name="city" type="text" value="<?php echo htmlspecialchars($profile['city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="country">Country</label>
                    <input class="form-control" id="country" name="country" type="text" value="<?php echo htmlspecialchars($profile['country'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="profile-divider"></div>
            <div class="profile-section-title"><h2>Education Details</h2></div>
            <div class="profile-grid">
                <div class="form-group">
                    <label class="form-label" for="education_qualification">Education qualification</label>
                    <select class="form-control" id="education_qualification" name="education_qualification" required>
                        <option value="">Select qualification</option>
                        <option value="below_ol" <?php echo (($profile['education_qualification'] ?? '') === 'below_ol') ? 'selected' : ''; ?>>Below O/L</option>
                        <option value="ol" <?php echo (($profile['education_qualification'] ?? '') === 'ol') ? 'selected' : ''; ?>>O/L completed</option>
                        <option value="al" <?php echo (($profile['education_qualification'] ?? '') === 'al') ? 'selected' : ''; ?>>Advanced Level (A/L)</option>
                    </select>
                </div>
                <div class="form-group" id="al-pass-field" style="<?php echo (($profile['education_qualification'] ?? '') === 'al') ? '' : 'display:none;'; ?>">
                    <label class="form-label" for="al_pass_count">Number of A/L passes</label>
                    <select class="form-control" id="al_pass_count" name="al_pass_count">
                        <?php for ($p = 0; $p <= 5; $p++): ?>
                            <option value="<?php echo $p; ?>" <?php echo ((int) ($profile['al_pass_count'] ?? 0) === $p) ? 'selected' : ''; ?>><?php echo $p; ?> pass<?php echo $p === 1 ? '' : 'es'; ?></option>
                        <?php endfor; ?>
                    </select>
                    <p class="help-text">Three or more passes unlock Level 4 (degree pathways).</p>
                </div>
                <div class="form-group"><label class="form-label" for="institution">Institution</label><input class="form-control" id="institution" name="institution" type="text" value="<?php echo htmlspecialchars($profile['institution'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
                <div class="form-group"><label class="form-label" for="graduation_year">Completed Year</label><input class="form-control" id="graduation_year" name="graduation_year" type="text" value="<?php echo htmlspecialchars($profile['graduation_year'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
                <div class="form-group"><label class="form-label" for="gpa">Grade</label><input class="form-control" id="gpa" name="gpa" type="text" value="<?php echo htmlspecialchars($profile['gpa'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
            </div>
            <div class="profile-divider"></div>
            <div class="profile-section-title"><h2>Interests</h2></div>
            <div class="profile-grid">
                <div class="form-group profile-form-span">
                    <label class="form-label" for="interests">Your interests &amp; career goals</label>
                    <textarea class="form-control" id="interests" name="interests" rows="3" required placeholder="e.g. IT, Design, Law, Business"><?php echo htmlspecialchars($profile['interests'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <p class="help-text">We use this text to boost course recommendations that align with what excites you.</p>
                </div>
            </div>
            <div class="profile-divider"></div>
            <div class="profile-section-title"><h2>Payment &amp; study</h2></div>
            <div class="profile-grid">
                <div class="form-group"><label class="form-label" for="financial_status">Payment Preference</label><select class="form-control" id="financial_status" name="financial_status" required><option value="">Select</option><option value="Full Payment" <?php echo (($profile['financial_status'] ?? '') === 'Full Payment') ? 'selected' : ''; ?>>Full Payment</option><option value="Installment" <?php echo (($profile['financial_status'] ?? '') === 'Installment') ? 'selected' : ''; ?>>Installment</option></select></div>
                <div class="form-group"><label class="form-label" for="budget_range">Budget Range</label><input class="form-control" id="budget_range" name="budget_range" type="text" value="<?php echo htmlspecialchars($profile['budget_range'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. 200000 - 400000"></div>
                <div class="form-group"><label class="form-label" for="study_mode">Study Mode</label><select class="form-control" id="study_mode" name="study_mode"><option value="">Select</option><option value="Full-time" <?php echo (($profile['study_mode'] ?? '') === 'Full-time') ? 'selected' : ''; ?>>Full-time</option><option value="Part-time" <?php echo (($profile['study_mode'] ?? '') === 'Part-time') ? 'selected' : ''; ?>>Part-time</option><option value="Online" <?php echo (($profile['study_mode'] ?? '') === 'Online') ? 'selected' : ''; ?>>Online</option></select></div>
                <div class="form-group"><label class="form-label" for="notes">Additional notes</label><textarea class="form-control" id="notes" name="notes"><?php echo htmlspecialchars($profile['notes'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea></div>
            </div>
            <div class="profile-actions">
                <button type="submit" name="submit_mode" value="save" class="btn-primary btn-action"><span class="btn-icon">✓</span><span>Save Profile</span></button>
                <a href="profile.php" class="btn-primary btn-action btn-secondary-action"><span class="btn-icon">↺</span><span>Cancel</span></a>
            </div>
        </form>
    </section>
<?php endif; ?>

<?php if (isset($_GET['saved']) && $_GET['saved'] === '1'): ?>
    <div id="profile-toast" class="payment-toast is-visible" role="status" aria-live="polite">
        <div class="payment-toast-icon">★</div>
        <div class="payment-toast-content">
            <strong>Impressive Profile Update!</strong>
            <p>Your profile looks great. About <?php echo (int) $recommendedCount; ?> courses mostly suit you now.</p>
        </div>
        <button type="button" class="payment-toast-close" id="profile-toast-close" aria-label="Close notification">×</button>
    </div>
    <script>
        (function () {
            var toast = document.getElementById('profile-toast');
            var closeBtn = document.getElementById('profile-toast-close');
            if (!toast) return;
            var close = function () { toast.classList.remove('is-visible'); toast.classList.add('is-hidden'); };
            if (closeBtn) closeBtn.addEventListener('click', close);
            setTimeout(close, 4600);
        })();
    </script>
<?php endif; ?>

<script>
(function () {
    var qual = document.getElementById('education_qualification');
    var alBlock = document.getElementById('al-pass-field');
    if (!qual || !alBlock) return;
    var sync = function () {
        alBlock.style.display = qual.value === 'al' ? '' : 'none';
    };
    qual.addEventListener('change', sync);
    sync();
})();
</script>

<?php studentPageEnd(); ?>
