<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

$applications = [];
$sql = "SELECT a.application_id, a.user_id, a.status, a.applied_at, c.course_name, i.intake_name, u.username
        FROM student_applications a
        LEFT JOIN course_catalog c ON c.course_id = a.course_id
        LEFT JOIN course_intakes i ON i.intake_id = a.intake_id
        LEFT JOIN users u ON u.user_id = a.user_id
        ORDER BY a.application_id DESC";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $applications[] = $row;
    }
}

adminPageStart('Manage Applications', 'applications', $adminName);
?>
<section class="admin-section">
    <h2 style="margin-bottom:12px;">Student Applications</h2>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>App ID</th><th>Student</th><th>Course</th><th>Intake</th><th>Applied On</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($applications as $app): ?>
                    <tr>
                        <td><?php echo (int) $app['application_id']; ?></td>
                        <td><?php echo htmlspecialchars(($app['username'] ?? 'User') . ' (#' . (int) $app['user_id'] . ')', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($app['course_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($app['intake_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $app['applied_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><span class="status-pill"><?php echo htmlspecialchars($app['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td>
                            <a class="btn btn-secondary btn-small" href="application_view.php?id=<?php echo (int) $app['application_id']; ?>">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php adminPageEnd(); ?>
