<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

$paymentId = (int) ($_GET['id'] ?? 0);

if ($paymentId <= 0) {
    header('Location: payments.php');
    exit;
}

$pay = null;
$sql = 'SELECT p.payment_id, p.user_id, p.registration_fee, p.payment_method, p.payment_status, p.updated_at,
        p.application_id, p.payment_plan,
        u.username, u.email,
        c.course_name
        FROM student_payments p
        LEFT JOIN users u ON u.user_id = p.user_id
        LEFT JOIN student_applications a ON a.application_id = p.application_id AND a.user_id = p.user_id
        LEFT JOIN course_catalog c ON c.course_id = a.course_id
        WHERE p.payment_id = ?
        LIMIT 1';
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $paymentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
        $pay = mysqli_fetch_assoc($res) ?: null;
    }
    mysqli_stmt_close($stmt);
}

if ($pay === null) {
    header('Location: payments.php');
    exit;
}

$userId = (int) ($pay['user_id'] ?? 0);
$history = [];
if ($userId > 0) {
    $histSql = 'SELECT history_id, payment_plan, payment_method, amount_paid, status_note, paid_at, application_id
        FROM student_payment_history
        WHERE user_id = ?
        ORDER BY paid_at DESC, history_id DESC
        LIMIT 25';
    $histStmt = mysqli_prepare($conn, $histSql);
    if ($histStmt) {
        mysqli_stmt_bind_param($histStmt, 'i', $userId);
        mysqli_stmt_execute($histStmt);
        $histRes = mysqli_stmt_get_result($histStmt);
        if ($histRes) {
            while ($row = mysqli_fetch_assoc($histRes)) {
                $history[] = $row;
            }
        }
        mysqli_stmt_close($histStmt);
    }
}

$payStatusLower = strtolower((string) ($pay['payment_status'] ?? 'unpaid'));
$payPillClass = 'status-pill admin-receipt-status';
if ($payStatusLower === 'paid') {
    $payPillClass .= ' status-paid';
} elseif ($payStatusLower === 'pending') {
    $payPillClass .= ' status-pending';
} else {
    $payPillClass .= ' status-unpaid';
}

adminPageStart('Payment receipt #' . $paymentId, 'payments', $adminName);
?>
<p class="admin-no-print" style="margin-bottom:16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
    <a class="btn btn-ghost btn-small" href="payments.php">← Back to payments</a>
    <button type="button" class="btn btn-secondary btn-small" id="receiptPdfBtn">Download PDF</button>
</p>

<section class="admin-section admin-receipt-page">
    <div class="admin-receipt-shell">
        <div id="receiptPdfRoot" class="admin-receipt-ticket">
            <header class="admin-receipt-ticket-header">
                <div class="admin-receipt-ticket-brand">StudySmart</div>
                <div class="admin-receipt-ticket-title">Payment receipt</div>
                <div class="admin-receipt-ticket-tagline">Registrar office copy</div>
            </header>

            <div class="admin-receipt-rule"></div>

            <div class="admin-receipt-kv">
                <div class="admin-receipt-kv-row">
                    <span class="admin-receipt-k">Receipt no.</span>
                    <span class="admin-receipt-v">PAY-<?php echo (int) $pay['payment_id']; ?></span>
                </div>
                <div class="admin-receipt-kv-row">
                    <span class="admin-receipt-k">Issued</span>
                    <span class="admin-receipt-v"><?php echo htmlspecialchars((string) ($pay['updated_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>

            <div class="admin-receipt-rule"></div>

            <p class="admin-receipt-section-label">Bill to</p>
            <p class="admin-receipt-text-strong"><?php echo htmlspecialchars((string) ($pay['username'] ?? 'Student'), ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="admin-receipt-text-muted">User ID <?php echo (int) $pay['user_id']; ?></p>
            <p class="admin-receipt-text"><?php echo htmlspecialchars((string) ($pay['email'] ?? ''), ENT_QUOTES, 'UTF-8') !== '' ? htmlspecialchars((string) $pay['email'], ENT_QUOTES, 'UTF-8') : '—'; ?></p>

            <div class="admin-receipt-rule"></div>

            <p class="admin-receipt-section-label">Summary</p>
            <div class="admin-receipt-kv">
                <div class="admin-receipt-kv-row">
                    <span class="admin-receipt-k">Amount (LKR)</span>
                    <span class="admin-receipt-v"><?php echo htmlspecialchars(number_format((float) ($pay['registration_fee'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="admin-receipt-kv-row">
                    <span class="admin-receipt-k">Plan</span>
                    <span class="admin-receipt-v"><?php echo htmlspecialchars((string) ($pay['payment_plan'] ?? 'Full'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="admin-receipt-kv-row">
                    <span class="admin-receipt-k">Method</span>
                    <span class="admin-receipt-v"><?php echo htmlspecialchars((string) ($pay['payment_method'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="admin-receipt-kv-row admin-receipt-kv-row--status">
                    <span class="admin-receipt-k">Status</span>
                    <span class="admin-receipt-v"><span class="<?php echo htmlspecialchars($payPillClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($pay['payment_status'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span></span>
                </div>
                <?php if (!empty($pay['application_id'])): ?>
                    <div class="admin-receipt-kv-row">
                        <span class="admin-receipt-k">Application</span>
                        <span class="admin-receipt-v">#<?php echo (int) $pay['application_id']; ?></span>
                    </div>
                    <?php if (!empty($pay['course_name'])): ?>
                        <div class="admin-receipt-kv-row admin-receipt-kv-row--stack">
                            <span class="admin-receipt-k">Course</span>
                            <span class="admin-receipt-v admin-receipt-v--block"><?php echo htmlspecialchars((string) $pay['course_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($history)): ?>
                <div class="admin-receipt-rule"></div>
                <p class="admin-receipt-section-label">Recent transactions</p>
                <div class="admin-receipt-history">
                    <?php foreach ($history as $h): ?>
                        <div class="admin-receipt-history-card">
                            <div class="admin-receipt-history-date"><?php echo htmlspecialchars((string) ($h['paid_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="admin-receipt-kv admin-receipt-kv--compact">
                                <div class="admin-receipt-kv-row">
                                    <span class="admin-receipt-k">Amount</span>
                                    <span class="admin-receipt-v">LKR <?php echo htmlspecialchars(number_format((float) ($h['amount_paid'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="admin-receipt-kv-row">
                                    <span class="admin-receipt-k">Plan</span>
                                    <span class="admin-receipt-v"><?php echo htmlspecialchars((string) ($h['payment_plan'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="admin-receipt-kv-row">
                                    <span class="admin-receipt-k">Method</span>
                                    <span class="admin-receipt-v"><?php echo htmlspecialchars((string) ($h['payment_method'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <?php if (trim((string) ($h['status_note'] ?? '')) !== ''): ?>
                                    <div class="admin-receipt-kv-row admin-receipt-kv-row--stack">
                                        <span class="admin-receipt-k">Note</span>
                                        <span class="admin-receipt-v admin-receipt-v--block"><?php echo htmlspecialchars((string) $h['status_note'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($h['application_id']) && $h['application_id'] !== null && (int) $h['application_id'] > 0): ?>
                                    <div class="admin-receipt-kv-row">
                                        <span class="admin-receipt-k">App #</span>
                                        <span class="admin-receipt-v"><?php echo (int) $h['application_id']; ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-receipt-rule"></div>
                <p class="admin-receipt-empty">No transaction lines recorded for this student yet.</p>
            <?php endif; ?>

            <div class="admin-receipt-rule"></div>
            <footer class="admin-receipt-ticket-footer">
                <p>Generated from StudySmart records.</p>
                <p>Questions? Contact the registrar.</p>
            </footer>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script>
(function () {
  const receiptId = <?php echo json_encode((int) $paymentId); ?>;
  const btn = document.getElementById('receiptPdfBtn');
  const root = document.getElementById('receiptPdfRoot');
  if (!btn || !root || !window.html2canvas || !window.jspdf) {
    return;
  }
  btn.addEventListener('click', async function () {
    const label = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Preparing PDF…';
    try {
      const canvas = await html2canvas(root, {
        scale: 2,
        backgroundColor: '#ffffff',
        logging: false,
        useCORS: true
      });
      const imgData = canvas.toDataURL('image/png');
      const { jsPDF } = window.jspdf;
      const pdf = new jsPDF('p', 'mm', 'a4');
      const pageW = pdf.internal.pageSize.getWidth();
      const pageH = pdf.internal.pageSize.getHeight();
      const margin = 16;
      const imgW = pageW - margin * 2;
      const imgH = (canvas.height * imgW) / canvas.width;
      let y = margin;
      pdf.addImage(imgData, 'PNG', margin, y, imgW, imgH);
      while (y + imgH > pageH - margin) {
        y -= pageH - margin * 2;
        pdf.addPage();
        pdf.addImage(imgData, 'PNG', margin, y, imgW, imgH);
      }
      pdf.save('studysmart-receipt-PAY-' + receiptId + '.pdf');
    } catch (e) {
      alert('Could not create PDF. Try again or use a different browser.');
    } finally {
      btn.disabled = false;
      btn.textContent = label;
    }
  });
})();
</script>
<?php adminPageEnd(); ?>
