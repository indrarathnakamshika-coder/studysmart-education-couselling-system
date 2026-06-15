<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $intakeId = (int) ($_POST['intake_id'] ?? 0);
        $stmt = mysqli_prepare($conn, "DELETE FROM course_intakes WHERE intake_id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $intakeId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } else {
        $intakeId = (int) ($_POST['intake_id'] ?? 0);
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $name = trim($_POST['intake_name'] ?? '');
        $startDate = $_POST['start_date'] ?? null;
        $endDate = $_POST['end_date'] ?? null;
        $seats = (int) ($_POST['available_seats'] ?? 0);
        $status = ($_POST['status'] ?? 'Open') === 'Closed' ? 'Closed' : 'Open';

        if ($action === 'edit' && $intakeId > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE course_intakes SET course_id=?, intake_name=?, start_date=?, end_date=?, available_seats=?, status=? WHERE intake_id=?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'isssisi', $courseId, $name, $startDate, $endDate, $seats, $status, $intakeId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO course_intakes (course_id, intake_name, start_date, end_date, available_seats, status) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'isssis', $courseId, $name, $startDate, $endDate, $seats, $status);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }
    header('Location: intakes.php?saved=1');
    exit;
}

$editing = null;
$editId = (int) ($_GET['edit_id'] ?? 0);
if ($editId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM course_intakes WHERE intake_id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $editId);
        mysqli_stmt_execute($stmt);
        $editing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
    }
}

$courses = [];
$resCourses = mysqli_query($conn, "SELECT course_id, course_name FROM course_catalog ORDER BY course_name ASC");
if ($resCourses) {
    while ($row = mysqli_fetch_assoc($resCourses)) {
        $courses[] = $row;
    }
}

$intakes = [];
$resIntakes = mysqli_query($conn, "SELECT i.*, c.course_name FROM course_intakes i LEFT JOIN course_catalog c ON c.course_id = i.course_id ORDER BY i.intake_id DESC");
if ($resIntakes) {
    while ($row = mysqli_fetch_assoc($resIntakes)) {
        $intakes[] = $row;
    }
}

adminPageStart('Manage Intakes', 'intakes', $adminName);
?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Intake record saved successfully.</div><?php endif; ?>
<section class="admin-section">
    <h2 style="margin-bottom:12px;"><?php echo $editing ? 'Edit Intake' : 'Add Intake'; ?></h2>
    <form method="post">
        <input type="hidden" name="action" value="<?php echo $editing ? 'edit' : 'create'; ?>">
        <input type="hidden" name="intake_id" value="<?php echo (int) ($editing['intake_id'] ?? 0); ?>">
        <div class="admin-form-grid">
            <div><label class="form-label">Course</label><select class="form-control" name="course_id" required><?php foreach ($courses as $course): ?><option value="<?php echo (int) $course['course_id']; ?>" <?php echo ((int) ($editing['course_id'] ?? 0) === (int) $course['course_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($course['course_name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label">Intake Name</label><input class="form-control" name="intake_name" required value="<?php echo htmlspecialchars($editing['intake_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div><label class="form-label">Available Seats</label><input class="form-control" name="available_seats" type="number" min="0" value="<?php echo (int) ($editing['available_seats'] ?? 0); ?>"></div>
            <div><label class="form-label">Start Date</label><input class="form-control" type="date" name="start_date" value="<?php echo htmlspecialchars($editing['start_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div><label class="form-label">End Date</label><input class="form-control" type="date" name="end_date" value="<?php echo htmlspecialchars($editing['end_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div><label class="form-label">Status</label><select class="form-control" name="status"><option value="Open" <?php echo (($editing['status'] ?? '') === 'Open') ? 'selected' : ''; ?>>Open</option><option value="Closed" <?php echo (($editing['status'] ?? '') === 'Closed') ? 'selected' : ''; ?>>Closed</option></select></div>
        </div>
        <div style="margin-top:12px;"><button class="btn-primary btn-small" type="submit"><?php echo $editing ? 'Update Intake' : 'Add Intake'; ?></button></div>
    </form>
</section>

<section class="admin-section">
    <h2 style="margin-bottom:12px;">Intake List</h2>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Course</th><th>Intake Name</th><th>Start</th><th>End</th><th>Seats</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($intakes as $intake): ?>
                <tr>
                    <td><?php echo htmlspecialchars($intake['course_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($intake['intake_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) $intake['start_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) $intake['end_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo (int) $intake['available_seats']; ?></td>
                    <td><span class="status-pill"><?php echo htmlspecialchars($intake['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td><div class="admin-inline-actions"><a href="intakes.php?edit_id=<?php echo (int) $intake['intake_id']; ?>">Edit</a><form method="post" onsubmit="return confirm('Delete this intake?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="intake_id" value="<?php echo (int) $intake['intake_id']; ?>"><button class="btn-danger" type="submit">Delete</button></form></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php adminPageEnd(); ?>
