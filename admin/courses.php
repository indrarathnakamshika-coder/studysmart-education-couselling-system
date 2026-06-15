<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

$editingCourse = null;
$saveError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $courseId = (int) ($_POST['course_id'] ?? 0);
        if ($courseId > 0) {
            $stmt = mysqli_prepare($conn, "DELETE FROM course_catalog WHERE course_id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $courseId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        header('Location: courses.php?saved=1');
        exit;
    }

    $courseId = (int) ($_POST['course_id'] ?? 0);
    $courseName = trim($_POST['course_name'] ?? '');
    $duration = (int) ($_POST['duration_months'] ?? 0);
    $qualification = trim($_POST['qualification_required'] ?? '');
    $stream = trim($_POST['stream_required'] ?? '');
    $fees = (float) ($_POST['fees'] ?? 0);
    $registrationFee = (float) ($_POST['registration_fee'] ?? 0);
    $installment = (float) ($_POST['installment_amount'] ?? 0);
    $studyMode = trim($_POST['study_mode'] ?? '');
    $intakeMonth = trim($_POST['intake_month'] ?? '');
    $status = ($_POST['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';

    
    $legacyField = $stream; 
    $legacyLevel = $qualification; 

    if ($action === 'edit' && $courseId > 0) {
        $sql = "UPDATE course_catalog
            SET course_name = ?, duration_months = ?, qualification_required = ?, stream_required = ?,
                fees = ?, registration_fee = ?, installment_amount = ?, study_mode = ?, intake_month = ?, status = ?,
                field = ?, level = ?, mode = ?, average_fee = ?
            WHERE course_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                'sissdddssssssdi',
                $courseName,
                $duration,
                $qualification,
                $stream,
                $fees,
                $registrationFee,
                $installment,
                $studyMode,
                $intakeMonth,
                $status,
                $legacyField,
                $legacyLevel,
                $studyMode,
                $fees,
                $courseId
            );
            if (!mysqli_stmt_execute($stmt)) {
                $saveError = mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $saveError = mysqli_error($conn);
        }
    } else {
        $sql = "INSERT INTO course_catalog
            (course_name, duration_months, qualification_required, stream_required, fees, registration_fee,
             installment_amount, study_mode, intake_month, status, field, level, mode, average_fee)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                'sissdddssssssd',
                $courseName,
                $duration,
                $qualification,
                $stream,
                $fees,
                $registrationFee,
                $installment,
                $studyMode,
                $intakeMonth,
                $status,
                $legacyField,
                $legacyLevel,
                $studyMode,
                $fees
            );
            if (!mysqli_stmt_execute($stmt)) {
                $saveError = mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $saveError = mysqli_error($conn);
        }
    }

    if ($saveError !== '') {
        header('Location: courses.php?error=' . urlencode($saveError));
    } else {
        header('Location: courses.php?saved=1');
    }
    exit;
}

$editId = (int) ($_GET['edit_id'] ?? 0);
if ($editId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM course_catalog WHERE course_id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $editId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $editingCourse = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
    }
}

$courses = [];
$result = mysqli_query($conn, "SELECT c.*, COALESCE(ix.intakes_list, '') AS intakes_list
    FROM course_catalog c
    LEFT JOIN (
        SELECT course_id, GROUP_CONCAT(DISTINCT intake_name ORDER BY start_date SEPARATOR ', ') AS intakes_list
        FROM course_intakes
        GROUP BY course_id
    ) ix ON ix.course_id = c.course_id
    ORDER BY c.course_id DESC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $courses[] = $row;
    }
}

adminPageStart('Manage Courses', 'courses', $adminName);
?>
<?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success">Course record saved successfully.</div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] !== ''): ?>
    <div class="alert alert-error">Failed to save course: <?php echo htmlspecialchars((string) $_GET['error'], ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<section class="admin-section">
    <h2 style="margin-bottom:12px;"><?php echo $editingCourse ? 'Edit Course' : 'Add Course'; ?></h2>
    <form method="post">
        <input type="hidden" name="action" value="<?php echo $editingCourse ? 'edit' : 'create'; ?>">
        <input type="hidden" name="course_id" value="<?php echo (int) ($editingCourse['course_id'] ?? 0); ?>">

        <div class="admin-form-grid">
            <div><label class="form-label">Course Name</label><input class="form-control" name="course_name" required value="<?php echo htmlspecialchars($editingCourse['course_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div><label class="form-label">Duration (Months)</label><input class="form-control" type="number" min="0" name="duration_months" value="<?php echo (int) ($editingCourse['duration_months'] ?? 0); ?>"></div>
            <div><label class="form-label">Qualification Required</label><input class="form-control" name="qualification_required" value="<?php echo htmlspecialchars($editingCourse['qualification_required'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div><label class="form-label">Stream Required</label><input class="form-control" name="stream_required" value="<?php echo htmlspecialchars($editingCourse['stream_required'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div><label class="form-label">Fees</label><input class="form-control" type="number" step="0.01" min="0" name="fees" value="<?php echo (float) ($editingCourse['fees'] ?? 0); ?>"></div>
            <div><label class="form-label">Registration Fee</label><input class="form-control" type="number" step="0.01" min="0" name="registration_fee" value="<?php echo (float) ($editingCourse['registration_fee'] ?? 0); ?>"></div>
            <div><label class="form-label">Installment Amount</label><input class="form-control" type="number" step="0.01" min="0" name="installment_amount" value="<?php echo (float) ($editingCourse['installment_amount'] ?? 0); ?>"></div>
            <div><label class="form-label">Study Mode</label><input class="form-control" name="study_mode" value="<?php echo htmlspecialchars($editingCourse['study_mode'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div><label class="form-label">Intake Month</label><input class="form-control" name="intake_month" value="<?php echo htmlspecialchars($editingCourse['intake_month'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div><label class="form-label">Status</label>
                <select class="form-control" name="status">
                    <option value="Active" <?php echo (($editingCourse['status'] ?? '') === 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo (($editingCourse['status'] ?? '') === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
        </div>
        <div style="margin-top:12px;">
            <button type="submit" class="btn-primary btn-small"><?php echo $editingCourse ? 'Update Course' : 'Add Course'; ?></button>
            <?php if ($editingCourse): ?>
                <a href="courses.php" style="margin-left:8px;">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="admin-section">
    <h2 style="margin-bottom:12px;">Course List</h2>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Duration</th>
                    <th>Fees</th>
                    <th>Registration</th>
                    <th>Installment</th>
                    <th>Mode</th>
                    <th>Intake</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $course): ?>
                    <?php
                    $feesValue = (float) ($course['fees'] ?? 0);
                    if ($feesValue <= 0) {
                        $feesValue = (float) ($course['average_fee'] ?? 0);
                    }

                    $regFeeValue = (float) ($course['registration_fee'] ?? 0);
                    if ($regFeeValue <= 0) {
                        $regFeeValue = 2500.00;
                    }

                    $installmentValue = (float) ($course['installment_amount'] ?? 0);
                    if ($installmentValue <= 0 && $feesValue > 0) {
                        $installmentValue = round($feesValue / 3, 2);
                    }

                    $modeValue = trim((string) ($course['study_mode'] ?? ''));
                    if ($modeValue === '') {
                        $modeValue = trim((string) ($course['mode'] ?? ''));
                    }

                    $intakeValue = trim((string) ($course['intake_month'] ?? ''));
                    $intakesList = trim((string) ($course['intakes_list'] ?? ''));
                    if ($intakesList !== '') {
                        $intakeValue = $intakesList;
                    }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($course['course_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int) ($course['duration_months'] ?? 0); ?> months</td>
                        <td><?php echo number_format($feesValue, 2); ?></td>
                        <td><?php echo number_format($regFeeValue, 2); ?></td>
                        <td><?php echo number_format($installmentValue, 2); ?></td>
                        <td><?php echo htmlspecialchars($modeValue, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($intakeValue, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><span class="status-pill"><?php echo htmlspecialchars($course['status'] ?? 'Active', ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td>
                            <div class="admin-inline-actions">
                                <a href="courses.php?edit_id=<?php echo (int) $course['course_id']; ?>">Edit</a>
                                <form method="post" onsubmit="return confirm('Delete this course?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="course_id" value="<?php echo (int) $course['course_id']; ?>">
                                    <button type="submit" class="btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php adminPageEnd(); ?>
