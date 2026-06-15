<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

$prefillCourseId = (int) ($_GET['course_id'] ?? 0);
$applyMode = isset($_GET['apply']) && $_GET['apply'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_submit'])) {
    $courseId = (int) ($_POST['course_id'] ?? 0);
    $applicantName = trim($_POST['applicant_name'] ?? '');
    $applicantEmail = trim($_POST['applicant_email'] ?? '');
    $applicantPhone = trim($_POST['applicant_phone'] ?? '');
    $applicantMessage = trim($_POST['applicant_message'] ?? '');

    $redirectSuffix = 'submitted=1';
    if ($courseId > 0 && $applicantName !== '' && $applicantEmail !== '' && $applicantPhone !== '') {
        $checkStmt = mysqli_prepare(
            $conn,
            'SELECT application_id FROM student_applications
            WHERE user_id = ? AND course_id = ?
            AND LOWER(TRIM(COALESCE(status, \'\'))) <> \'rejected\'
            LIMIT 1'
        );
        if ($checkStmt) {
            mysqli_stmt_bind_param($checkStmt, 'ii', $userId, $courseId);
            mysqli_stmt_execute($checkStmt);
            $checkRes = mysqli_stmt_get_result($checkStmt);
            $exists = $checkRes && mysqli_num_rows($checkRes) > 0;
            mysqli_stmt_close($checkStmt);

            if ($exists) {
                $redirectSuffix = 'duplicate=1&course_id=' . $courseId;
            } else {
                $insertStmt = mysqli_prepare(
                    $conn,
                    'INSERT INTO student_applications (user_id, course_id, status, applicant_name, applicant_email, applicant_phone, applicant_message)
                    VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                if ($insertStmt) {
                    $statusPending = 'Pending';
                    mysqli_stmt_bind_param(
                        $insertStmt,
                        'iisssss',
                        $userId,
                        $courseId,
                        $statusPending,
                        $applicantName,
                        $applicantEmail,
                        $applicantPhone,
                        $applicantMessage
                    );
                    mysqli_stmt_execute($insertStmt);
                    mysqli_stmt_close($insertStmt);
                }
            }
        }
    }
    header('Location: applications.php?' . $redirectSuffix . '#application-history');
    exit;
}

$courses = [];
$coursesRes = mysqli_query($conn, 'SELECT course_id, course_name, field, level, duration_months, average_fee, fees FROM course_catalog ORDER BY course_name');
if ($coursesRes) {
    while ($course = mysqli_fetch_assoc($coursesRes)) {
        $courses[] = $course;
    }
}

$applications = [];
$applicationsStmt = mysqli_prepare(
    $conn,
    'SELECT a.application_id, a.course_id, a.status, a.applied_at, a.payment_status, c.course_name, c.field, c.level
    FROM student_applications a
    INNER JOIN course_catalog c ON c.course_id = a.course_id
    WHERE a.user_id = ?
    ORDER BY a.applied_at DESC'
);
if ($applicationsStmt) {
    mysqli_stmt_bind_param($applicationsStmt, 'i', $userId);
    mysqli_stmt_execute($applicationsStmt);
    $applicationsRes = mysqli_stmt_get_result($applicationsStmt);
    while ($applicationsRes && ($row = mysqli_fetch_assoc($applicationsRes))) {
        $applications[] = $row;
    }
    mysqli_stmt_close($applicationsStmt);
}

$blockedCourseIds = [];
foreach ($applications as $appRow) {
    if (strtolower(trim((string) ($appRow['status'] ?? ''))) !== 'rejected') {
        $blockedCourseIds[(int) ($appRow['course_id'] ?? 0)] = true;
    }
}

$showDuplicateModal = isset($_GET['duplicate']) && $_GET['duplicate'] === '1';
$showSubmittedModal = isset($_GET['submitted']) && $_GET['submitted'] === '1';
$duplicateModalCourseId = (int) ($_GET['course_id'] ?? 0);
$duplicateModalCourseName = '';
if ($duplicateModalCourseId > 0) {
    foreach ($courses as $c) {
        if ((int) ($c['course_id'] ?? 0) === $duplicateModalCourseId) {
            $duplicateModalCourseName = (string) ($c['course_name'] ?? '');
            break;
        }
    }
}

$selectedCourse = null;
if ($prefillCourseId > 0) {
    foreach ($courses as $c) {
        if ((int) $c['course_id'] === $prefillCourseId) {
            $selectedCourse = $c;
            break;
        }
    }
}

$applyBlocked = $applyMode && $selectedCourse !== null && isset($blockedCourseIds[(int) $selectedCourse['course_id']]);

studentPageStart('My Applications', 'applications', $studentName);
?>

<section class="student-section" id="application-form">
    <h2>Course application</h2>
    <p class="help-text">Enter your details and confirm the course you want to apply for. Fields are cleared after a successful submission.</p>
    <?php if ($applyBlocked): ?>
        <div class="alert alert-error" role="alert">
            You already have an active application for this course (not rejected). You cannot submit again for the same programme until the office updates or rejects your existing application.
        </div>
    <?php endif; ?>

    <form method="post" action="applications.php" class="application-form-grid">
        <input type="hidden" name="application_submit" value="1">
        <div class="form-group">
            <label class="form-label" for="applicant_name">Full name</label>
            <input class="form-control" id="applicant_name" name="applicant_name" type="text" required value="" autocomplete="name">
        </div>
        <div class="form-group">
            <label class="form-label" for="applicant_email">Email</label>
            <input class="form-control" id="applicant_email" name="applicant_email" type="email" required value="" autocomplete="email">
        </div>
        <div class="form-group">
            <label class="form-label" for="applicant_phone">Phone</label>
            <input class="form-control" id="applicant_phone" name="applicant_phone" type="text" required value="" autocomplete="tel">
        </div>
        <div class="form-group">
            <label class="form-label" for="course_id">Course</label>
            <?php if ($applyMode && $selectedCourse !== null): ?>
                <input type="hidden" name="course_id" value="<?php echo (int) $selectedCourse['course_id']; ?>">
                <div class="application-course-summary">
                    <strong><?php echo htmlspecialchars($selectedCourse['course_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span class="help-text"><?php echo htmlspecialchars($selectedCourse['field'] . ' · ' . $selectedCourse['level'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php
                    $displayFee = (float) ($selectedCourse['average_fee'] ?? 0);
                    if ($displayFee <= 0) {
                        $displayFee = (float) ($selectedCourse['fees'] ?? 0);
                    }
                    ?>
                    <span class="help-text">Duration: <?php echo (int) $selectedCourse['duration_months']; ?> months · Fee: LKR <?php echo htmlspecialchars(number_format($displayFee, 2), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php else: ?>
                <select id="course_id" name="course_id" class="form-control" required>
                    <option value="">Choose a course</option>
                    <?php foreach ($courses as $course): ?>
                        <?php
                        $cid = (int) ($course['course_id'] ?? 0);
                        $isBlocked = isset($blockedCourseIds[$cid]);
                        ?>
                        <option
                            value="<?php echo $cid; ?>"
                            <?php echo $isBlocked ? ' disabled' : ''; ?>
                            <?php echo $isBlocked ? ' title="You already have an active application for this course."' : ''; ?>
                        >
                            <?php echo htmlspecialchars($course['course_name'], ENT_QUOTES, 'UTF-8'); ?><?php echo $isBlocked ? ' (already applied)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
        <div class="form-group application-form-full">
            <label class="form-label" for="applicant_message">Message (optional)</label>
            <textarea class="form-control" id="applicant_message" name="applicant_message" rows="3" placeholder="Anything else we should know?"></textarea>
        </div>
        <div class="form-group application-form-full">
            <button type="submit" class="btn-primary btn-action"<?php echo $applyBlocked ? ' disabled' : ''; ?>>
                <span class="btn-icon">→</span>
                <span>Submit application</span>
            </button>
        </div>
    </form>
</section>

<section class="student-section" id="application-history">
    <h2>Application history</h2>
    <?php if (empty($applications)): ?>
        <p class="help-text">No applications yet. Use recommendations to pick a course, or choose one above.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="student-table">
                <thead>
                    <tr>
                        <th>Course name</th>
                        <th>Application status</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $application): ?>
                        <?php
                        $statusRaw = (string) $application['status'];
                        $status = strtolower($statusRaw);
                        $statusClass = 'status-pending';
                        if ($status === 'approved') {
                            $statusClass = 'status-approved';
                        } elseif ($status === 'rejected') {
                            $statusClass = 'status-rejected';
                        }
                        $statusLabel = $statusRaw !== '' ? ucfirst($status) : '—';
                        $pay = strtolower((string) ($application['payment_status'] ?? 'Unpaid'));
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($application['course_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <span class="status-pill <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($pay === 'paid'): ?>
                                    <span class="status-pill status-paid">Paid</span>
                                <?php elseif ($status === 'approved'): ?>
                                    <a class="btn-primary btn-action btn-compact" href="payments.php?application_id=<?php echo (int) $application['application_id']; ?>">
                                        <span class="btn-icon">👉</span>
                                        <span>Make Payment</span>
                                    </a>
                                <?php else: ?>
                                    <button type="button" class="btn-primary btn-action btn-compact is-disabled" disabled title="Available when your application is approved">
                                        <span class="btn-icon">👉</span>
                                        <span>Make Payment</span>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($showSubmittedModal): ?>
    <div id="application-submitted-modal" class="payment-modal-overlay is-open" role="dialog" aria-modal="true" aria-labelledby="application-submitted-title" tabindex="-1">
        <div class="payment-modal-dialog">
            <div class="payment-modal-icon payment-modal-icon--info" aria-hidden="true">i</div>
            <h2 id="application-submitted-title" class="payment-modal-title">Application submitted</h2>
            <p class="payment-modal-text">
                Thank you for your application!
                <br><br>
                We have successfully received your request. Please check your dashboard for updates, and our team will reach out if necessary.
            </p>
            <div class="payment-modal-actions">
                <button type="button" class="payment-modal-btn-primary" id="application-submitted-ok">OK</button>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var modal = document.getElementById('application-submitted-modal');
        var ok = document.getElementById('application-submitted-ok');
        if (!modal || !ok) return;
        document.body.style.overflow = 'hidden';
        function stripSubmittedParam() {
            try {
                var u = new URL(window.location.href);
                u.searchParams.delete('submitted');
                var qs = u.searchParams.toString();
                history.replaceState({}, '', u.pathname + (qs ? '?' + qs : '') + u.hash);
            } catch (err) {}
        }
        function closeModal() {
            document.body.style.overflow = '';
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            modal.remove();
            document.removeEventListener('keydown', onEsc);
            stripSubmittedParam();
        }
        function onEsc(e) {
            if (e.key === 'Escape' && modal.parentNode) closeModal();
        }
        ok.addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', onEsc);
    })();
    </script>
<?php endif; ?>

<?php if ($showDuplicateModal): ?>
    <div id="duplicate-application-modal" class="payment-modal-overlay is-open" role="dialog" aria-modal="true" aria-labelledby="duplicate-application-modal-title" tabindex="-1">
        <div class="payment-modal-dialog">
            <div class="payment-modal-icon payment-modal-icon--warning" aria-hidden="true">!</div>
            <h2 id="duplicate-application-modal-title" class="payment-modal-title">Duplicate application</h2>
            <p class="payment-modal-text">
                <?php if ($duplicateModalCourseName !== ''): ?>
                    You already have an active application for <strong><?php echo htmlspecialchars($duplicateModalCourseName, ENT_QUOTES, 'UTF-8'); ?></strong>.
                    The system does not allow a second application for the same course while your current one is pending or approved.
                <?php else: ?>
                    You already have an active application for this course. The system does not allow a second application while your current one is pending or approved.
                <?php endif; ?>
            </p>
            <div class="payment-modal-actions">
                <button type="button" class="payment-modal-btn-primary" id="duplicate-application-modal-ok">OK</button>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var modal = document.getElementById('duplicate-application-modal');
        var ok = document.getElementById('duplicate-application-modal-ok');
        if (!modal || !ok) return;
        document.body.style.overflow = 'hidden';
        function stripDuplicateParams() {
            try {
                var u = new URL(window.location.href);
                u.searchParams.delete('duplicate');
                u.searchParams.delete('course_id');
                var qs = u.searchParams.toString();
                history.replaceState({}, '', u.pathname + (qs ? '?' + qs : '') + u.hash);
            } catch (err) {}
        }
        function closeModal() {
            document.body.style.overflow = '';
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            modal.remove();
            document.removeEventListener('keydown', onEsc);
            stripDuplicateParams();
        }
        function onEsc(e) {
            if (e.key === 'Escape' && modal.parentNode) closeModal();
        }
        ok.addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', onEsc);
    })();
    </script>
<?php endif; ?>

<?php studentPageEnd(); ?>
