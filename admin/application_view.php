<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

$applicationId = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_status_update'])) {
    $postId = (int) ($_POST['application_id'] ?? 0);
    $status = $_POST['status'] ?? 'Pending';
    if (!in_array($status, ['Pending', 'Approved', 'Rejected'], true)) {
        $status = 'Pending';
    }
    if ($postId <= 0) {
        header('Location: applications.php');
        exit;
    }

    if ($status === 'Rejected') {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE student_applications SET status = ?, payment_status = 'Unpaid' WHERE application_id = ?"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'si', $status, $postId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        // Keep dashboard / reports payment summary in sync: one student_payments row per user, keyed by application_id when set.
        $paySync = mysqli_prepare($conn, "UPDATE student_payments SET payment_status = 'Unpaid' WHERE application_id = ?");
        if ($paySync) {
            mysqli_stmt_bind_param($paySync, 'i', $postId);
            mysqli_stmt_execute($paySync);
            mysqli_stmt_close($paySync);
        }
    } else {
        $stmt = mysqli_prepare($conn, 'UPDATE student_applications SET status = ? WHERE application_id = ?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'si', $status, $postId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    $notify = 'saved';
    if ($status === 'Approved') {
        $notify = 'approved';
    } elseif ($status === 'Rejected') {
        $notify = 'rejected';
    } elseif ($status === 'Pending') {
        $notify = 'pending';
    }

    header('Location: application_view.php?id=' . $postId . '&saved=1&notify=' . rawurlencode($notify));
    exit;
}

if ($applicationId <= 0) {
    header('Location: applications.php');
    exit;
}

$app = null;
$sql = 'SELECT a.application_id, a.user_id, a.status, a.applied_at, a.payment_status,
        a.applicant_name, a.applicant_email, a.applicant_phone,
        a.course_id,
        c.course_name, c.field, c.level, c.duration_months, c.average_fee, c.fees,
        u.username, u.email AS account_email
        FROM student_applications a
        LEFT JOIN course_catalog c ON c.course_id = a.course_id
        LEFT JOIN users u ON u.user_id = a.user_id
        WHERE a.application_id = ?
        LIMIT 1';
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $applicationId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
        $app = mysqli_fetch_assoc($res) ?: null;
    }
    mysqli_stmt_close($stmt);
}

if ($app === null) {
    header('Location: applications.php');
    exit;
}

$displayFee = (float) ($app['average_fee'] ?? 0);
if ($displayFee <= 0) {
    $displayFee = (float) ($app['fees'] ?? 0);
}

$statusLower = strtolower((string) ($app['status'] ?? ''));
$statusPillClass = 'status-pill';
if ($statusLower === 'approved') {
    $statusPillClass .= ' status-approved';
} elseif ($statusLower === 'rejected') {
    $statusPillClass .= ' status-rejected';
} else {
    $statusPillClass .= ' status-pending';
}

$payLower = strtolower((string) ($app['payment_status'] ?? 'unpaid'));

$notify = isset($_GET['notify']) ? (string) $_GET['notify'] : '';

adminPageStart('Application #' . $applicationId, 'applications', $adminName);
?>
<?php if (isset($_GET['saved']) && $_GET['saved'] === '1'): ?>
    <?php if ($notify === 'approved'): ?>
        <div class="alert alert-info admin-flash-moderate" role="status">
            <strong class="admin-flash-title">Application Approved Successfully!</strong>
        </div>
    <?php else: ?>
    <div class="alert alert-success">
        <?php if ($notify === 'rejected'): ?>
            Application status set to <strong>Rejected</strong>.
        <?php elseif ($notify === 'pending'): ?>
            Application status set to <strong>Pending</strong>.
        <?php else: ?>
            Application status updated.
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<p style="margin-bottom:14px;">
    <a class="btn btn-ghost btn-small" href="applications.php">← Back to applications</a>
</p>

<section class="admin-section">
    <h2 style="margin-bottom:6px;">Course application</h2>
    <p class="admin-help-text">Submitted details as entered by the student (read-only).</p>

    <div class="admin-application-detail-grid">
        <div class="form-group">
            <label class="form-label">Full name</label>
            <div class="admin-readonly-field"><?php echo htmlspecialchars((string) ($app['applicant_name'] ?? ''), ENT_QUOTES, 'UTF-8') !== '' ? htmlspecialchars((string) $app['applicant_name'], ENT_QUOTES, 'UTF-8') : '—'; ?></div>
        </div>
        <div class="form-group">
            <label class="form-label">Email</label>
            <div class="admin-readonly-field"><?php echo htmlspecialchars((string) ($app['applicant_email'] ?? ''), ENT_QUOTES, 'UTF-8') !== '' ? htmlspecialchars((string) $app['applicant_email'], ENT_QUOTES, 'UTF-8') : '—'; ?></div>
        </div>
        <div class="form-group">
            <label class="form-label">Phone</label>
            <div class="admin-readonly-field"><?php echo htmlspecialchars((string) ($app['applicant_phone'] ?? ''), ENT_QUOTES, 'UTF-8') !== '' ? htmlspecialchars((string) $app['applicant_phone'], ENT_QUOTES, 'UTF-8') : '—'; ?></div>
        </div>
        <div class="form-group">
            <label class="form-label">Account username</label>
            <div class="admin-readonly-field"><?php echo htmlspecialchars((string) ($app['username'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?> (#<?php echo (int) ($app['user_id'] ?? 0); ?>)</div>
        </div>
        <div class="form-group">
            <label class="form-label">Account email</label>
            <div class="admin-readonly-field"><?php echo htmlspecialchars((string) ($app['account_email'] ?? ''), ENT_QUOTES, 'UTF-8') !== '' ? htmlspecialchars((string) $app['account_email'], ENT_QUOTES, 'UTF-8') : '—'; ?></div>
        </div>
        <div class="form-group">
            <label class="form-label">Applied on</label>
            <div class="admin-readonly-field"><?php echo htmlspecialchars((string) ($app['applied_at'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="form-group admin-application-detail-span">
            <label class="form-label">Course</label>
            <div class="admin-readonly-field">
                <strong><?php echo htmlspecialchars((string) ($app['course_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></strong>
                <?php
                $field = trim((string) ($app['field'] ?? ''));
                $level = trim((string) ($app['level'] ?? ''));
                if ($field !== '' || $level !== '') {
                    echo '<br><span class="admin-readonly-muted">';
                    echo htmlspecialchars($field . ($field !== '' && $level !== '' ? ' · ' : '') . $level, ENT_QUOTES, 'UTF-8');
                    echo '</span>';
                }
                ?>
                <br><span class="admin-readonly-muted">Duration: <?php echo (int) ($app['duration_months'] ?? 0); ?> months · Fee: LKR <?php echo htmlspecialchars(number_format($displayFee, 2), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Payment (registration)</label>
            <div class="admin-readonly-field">
                <span class="status-pill <?php echo $payLower === 'paid' ? 'status-paid' : ($payLower === 'pending' ? 'status-pending' : 'status-unpaid'); ?>">
                    <?php echo htmlspecialchars((string) ($app['payment_status'] ?? 'Unpaid'), ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </div>
        </div>
    </div>
</section>

<section class="admin-section admin-application-decision">
    <h2 style="margin-bottom:8px;">Review decision</h2>
    <p style="margin-bottom:12px;">Current status:
        <span class="<?php echo htmlspecialchars($statusPillClass, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars((string) ($app['status'] ?? 'Pending'), ENT_QUOTES, 'UTF-8'); ?>
        </span>
    </p>
    <form method="post" class="admin-application-decision-form">
        <input type="hidden" name="application_status_update" value="1">
        <input type="hidden" name="application_id" value="<?php echo (int) $app['application_id']; ?>">
        <div class="admin-inline-actions admin-application-decision-buttons">
            <button class="btn btn-primary btn-small" type="submit" name="status" value="Approved" onclick="return confirm('Approve this application?');">Approve</button>
            <button class="btn btn-danger btn-small" type="submit" name="status" value="Rejected" onclick="return confirm('Reject this application?');">Reject</button>
            <button class="btn btn-ghost btn-small" type="submit" name="status" value="Pending" onclick="return confirm('Mark this application as pending?');">Mark pending</button>
        </div>
    </form>
</section>
<?php adminPageEnd(); ?>
