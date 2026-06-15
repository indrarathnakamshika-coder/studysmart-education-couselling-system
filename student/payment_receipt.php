<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

$historyId = (int) ($_GET['id'] ?? 0);
$autoPrint = isset($_GET['print']) && $_GET['print'] === '1';
$autoDownload = isset($_GET['download']) && $_GET['download'] === '1';

if ($historyId <= 0) {
    header('Location: payments.php');
    exit;
}

$row = null;
$sql = 'SELECT h.history_id, h.payment_plan, h.payment_method, h.amount_paid, h.status_note, h.paid_at, h.application_id,
    c.course_name
    FROM student_payment_history h
    LEFT JOIN student_applications a ON a.application_id = h.application_id AND a.user_id = h.user_id
    LEFT JOIN course_catalog c ON c.course_id = a.course_id
    WHERE h.history_id = ? AND h.user_id = ?
    LIMIT 1';
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ii', $historyId, $userId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
}

if ($row === null) {
    header('Location: payments.php');
    exit;
}

$receiptNo = 'TXN-' . (int) $row['history_id'];
$issued = $row['paid_at'] ?? '';
$courseLine = trim((string) ($row['course_name'] ?? ''));
$appId = (int) ($row['application_id'] ?? 0);

studentPageStart('Payment receipt', 'payments', $studentName);
?>
<p class="student-no-print payment-receipt-toolbar">
    <a class="btn-primary btn-action btn-compact" href="payments.php">← Back to payments</a>
    <button type="button" class="btn-primary btn-action btn-secondary-action btn-compact" id="studentReceiptPrintBtn">Print</button>
    <button type="button" class="btn-primary btn-action btn-secondary-action btn-compact" id="studentReceiptPdfBtn">Download PDF</button>
</p>

<section class="student-section student-receipt-page">
    <div class="student-receipt-shell">
        <div id="studentReceiptPdfRoot" class="student-receipt-ticket">
            <header class="student-receipt-ticket-header">
                <div class="student-receipt-ticket-brand">StudySmart</div>
                <div class="student-receipt-ticket-title">Payment receipt</div>
                <div class="student-receipt-ticket-tagline">Student copy</div>
            </header>

            <div class="student-receipt-rule"></div>

            <div class="student-receipt-kv">
                <div class="student-receipt-kv-row">
                    <span class="student-receipt-k">Receipt no.</span>
                    <span class="student-receipt-v"><?php echo htmlspecialchars($receiptNo, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="student-receipt-kv-row">
                    <span class="student-receipt-k">Paid on</span>
                    <span class="student-receipt-v"><?php echo htmlspecialchars((string) $issued, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>

            <div class="student-receipt-rule"></div>

            <p class="student-receipt-section-label">Student</p>
            <p class="student-receipt-text-strong"><?php echo htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'); ?></p>

            <div class="student-receipt-rule"></div>

            <p class="student-receipt-section-label">Payment</p>
            <div class="student-receipt-kv">
                <div class="student-receipt-kv-row">
                    <span class="student-receipt-k">Amount (LKR)</span>
                    <span class="student-receipt-v"><?php echo htmlspecialchars(number_format((float) ($row['amount_paid'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="student-receipt-kv-row">
                    <span class="student-receipt-k">Plan</span>
                    <span class="student-receipt-v"><?php echo htmlspecialchars((string) ($row['payment_plan'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="student-receipt-kv-row">
                    <span class="student-receipt-k">Method</span>
                    <span class="student-receipt-v"><?php echo htmlspecialchars((string) ($row['payment_method'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php if ($appId > 0): ?>
                    <div class="student-receipt-kv-row">
                        <span class="student-receipt-k">Application</span>
                        <span class="student-receipt-v">#<?php echo $appId; ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($courseLine !== ''): ?>
                    <div class="student-receipt-kv-row student-receipt-kv-row--stack">
                        <span class="student-receipt-k">Course</span>
                        <span class="student-receipt-v student-receipt-v--block"><?php echo htmlspecialchars($courseLine, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (trim((string) ($row['status_note'] ?? '')) !== ''): ?>
                    <div class="student-receipt-kv-row student-receipt-kv-row--stack">
                        <span class="student-receipt-k">Note</span>
                        <span class="student-receipt-v student-receipt-v--block"><?php echo htmlspecialchars((string) $row['status_note'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="student-receipt-rule"></div>
            <footer class="student-receipt-ticket-footer">
                <p>Generated from your StudySmart payment history.</p>
            </footer>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script>
(function () {
  var printBtn = document.getElementById('studentReceiptPrintBtn');
  if (printBtn) {
    printBtn.addEventListener('click', function () { window.print(); });
  }
  var receiptId = <?php echo json_encode((int) $row['history_id']); ?>;
  var btn = document.getElementById('studentReceiptPdfBtn');
  var root = document.getElementById('studentReceiptPdfRoot');
  if (!btn || !root || !window.html2canvas || !window.jspdf) {
    return;
  }
  function runPdf() {
    var label = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Preparing PDF…';
    html2canvas(root, {
      scale: 2,
      backgroundColor: '#ffffff',
      logging: false,
      useCORS: true
    }).then(function (canvas) {
      var imgData = canvas.toDataURL('image/png');
      var jsPDF = window.jspdf.jsPDF;
      var pdf = new jsPDF('p', 'mm', 'a4');
      var pageW = pdf.internal.pageSize.getWidth();
      var pageH = pdf.internal.pageSize.getHeight();
      var margin = 16;
      var imgW = pageW - margin * 2;
      var imgH = (canvas.height * imgW) / canvas.width;
      var y = margin;
      pdf.addImage(imgData, 'PNG', margin, y, imgW, imgH);
      while (y + imgH > pageH - margin) {
        y -= pageH - margin * 2;
        pdf.addPage();
        pdf.addImage(imgData, 'PNG', margin, y, imgW, imgH);
      }
      pdf.save('studysmart-receipt-TXN-' + receiptId + '.pdf');
    }).catch(function () {
      alert('Could not create PDF. Try again or use a different browser.');
    }).finally(function () {
      btn.disabled = false;
      btn.textContent = label;
    });
  }
  btn.addEventListener('click', runPdf);
  <?php if ($autoPrint): ?>
  window.addEventListener('load', function () {
    setTimeout(function () { window.print(); }, 300);
  });
  <?php endif; ?>
  <?php if ($autoDownload): ?>
  window.addEventListener('load', function () {
    setTimeout(runPdf, 400);
  });
  <?php endif; ?>
})();
</script>

<?php studentPageEnd(); ?>
