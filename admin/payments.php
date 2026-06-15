<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

$payments = [];
$sql = "SELECT p.payment_id, p.user_id, p.registration_fee, p.payment_method, p.payment_status, p.updated_at, u.username
        FROM student_payments p
        LEFT JOIN users u ON u.user_id = p.user_id
        ORDER BY p.payment_id DESC";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $payments[] = $row;
    }
}

adminPageStart('Manage Payments', 'payments', $adminName);
?>
<section class="admin-section">
    <h2 style="margin-bottom:12px;">Payment Records</h2>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Payment ID</th><th>Student</th><th>Amount</th><th>Method</th><th>Status</th><th>Updated</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($payments as $pay): ?>
                    <tr>
                        <td><?php echo (int) $pay['payment_id']; ?></td>
                        <td><?php echo htmlspecialchars(($pay['username'] ?? 'User') . ' (#' . (int) $pay['user_id'] . ')', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo number_format((float) $pay['registration_fee'], 2); ?></td>
                        <td><?php echo htmlspecialchars($pay['payment_method'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><span class="status-pill"><?php echo htmlspecialchars($pay['payment_status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td><?php echo htmlspecialchars((string) $pay['updated_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <a class="btn btn-secondary btn-small" href="payment_receipt.php?id=<?php echo (int) $pay['payment_id']; ?>">View receipt</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php adminPageEnd(); ?>
