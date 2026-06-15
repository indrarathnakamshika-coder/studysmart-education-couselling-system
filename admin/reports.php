<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

function getSingleValue(string $sql, string $key): int
{
    global $conn;
    $result = mysqli_query($conn, $sql);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    return (int) ($row[$key] ?? 0);
}

$totalStudents = getSingleValue("SELECT COUNT(*) AS c FROM students", 'c');
$totalApplications = getSingleValue("SELECT COUNT(*) AS c FROM student_applications", 'c');
$paidPayments = getSingleValue(
    "SELECT COUNT(*) AS c FROM student_applications WHERE status <> 'Rejected' AND LOWER(TRIM(COALESCE(payment_status, ''))) = 'paid'",
    'c'
);
$pendingPayments = getSingleValue(
    "SELECT COUNT(*) AS c FROM student_applications WHERE status <> 'Rejected' AND LOWER(TRIM(COALESCE(payment_status, ''))) = 'pending'",
    'c'
);

$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');
$courseIdFilter = (int) ($_GET['course_id'] ?? 0);

$validStart = $startDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate);
$validEnd = $endDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate);

if (!$validStart || !$validEnd) {
    $startDate = date('Y-m-d', strtotime('-30 days'));
    $endDate = date('Y-m-d');
}

$courses = [];
$resCourses = mysqli_query($conn, "SELECT course_id, course_name FROM course_catalog ORDER BY course_name ASC");
if ($resCourses) {
    while ($row = mysqli_fetch_assoc($resCourses)) {
        $courses[] = $row;
    }
}

$whereCourse = $courseIdFilter > 0 ? " AND a.course_id = {$courseIdFilter} " : "";

$applicationsInRange = getSingleValue(
    "SELECT COUNT(*) AS c FROM student_applications a WHERE DATE(a.applied_at) BETWEEN '{$startDate}' AND '{$endDate}'{$whereCourse}",
    'c'
);

$appsByCourse = [];
$resAppsByCourse = mysqli_query($conn, "SELECT c.course_name, COUNT(a.application_id) AS app_count
    FROM course_catalog c
    LEFT JOIN student_applications a
      ON a.course_id = c.course_id
     AND DATE(a.applied_at) BETWEEN '{$startDate}' AND '{$endDate}'
     {$whereCourse}
    GROUP BY c.course_id
    ORDER BY app_count DESC
    LIMIT 10");
if ($resAppsByCourse) {
    while ($row = mysqli_fetch_assoc($resAppsByCourse)) {
        $appsByCourse[] = $row;
    }
}

$payStatus = ['Paid' => 0, 'Pending' => 0, 'Unpaid' => 0];
$resPay = mysqli_query($conn, "SELECT bucket, COUNT(*) AS c FROM (
    SELECT CASE
        WHEN a.status = 'Rejected' THEN 'Unpaid'
        WHEN LOWER(TRIM(COALESCE(a.payment_status, ''))) = 'paid' THEN 'Paid'
        WHEN LOWER(TRIM(COALESCE(a.payment_status, ''))) = 'pending' THEN 'Pending'
        ELSE 'Unpaid'
    END AS bucket
    FROM student_applications a
) t
GROUP BY bucket");
if ($resPay) {
    while ($row = mysqli_fetch_assoc($resPay)) {
        $statusKey = (string) ($row['bucket'] ?? '');
        if (isset($payStatus[$statusKey])) {
            $payStatus[$statusKey] = (int) $row['c'];
        }
    }
}

$trendLabels = [];
$trendCounts = [];
$resTrend = mysqli_query($conn, "SELECT DATE(applied_at) AS d, COUNT(*) AS c
    FROM student_applications a
    WHERE DATE(applied_at) BETWEEN '{$startDate}' AND '{$endDate}'
    {$whereCourse}
    GROUP BY DATE(applied_at)
    ORDER BY d ASC");
if ($resTrend) {
    while ($row = mysqli_fetch_assoc($resTrend)) {
        $trendLabels[] = $row['d'];
        $trendCounts[] = (int) $row['c'];
    }
}

$popularCourses = [];
$popularResult = mysqli_query($conn, "SELECT c.course_name, COUNT(a.application_id) AS app_count
    FROM course_catalog c
    LEFT JOIN student_applications a
      ON a.course_id = c.course_id
     AND DATE(a.applied_at) BETWEEN '{$startDate}' AND '{$endDate}'
     {$whereCourse}
    GROUP BY c.course_id
    ORDER BY app_count DESC
    LIMIT 5");
if ($popularResult) {
    while ($row = mysqli_fetch_assoc($popularResult)) {
        $popularCourses[] = $row;
    }
}

$applicationReport = [];
$appReportSql = "SELECT
        a.application_id,
        a.applied_at,
        a.status,
        u.user_id,
        COALESCE(sp.full_name, s.full_name, u.username) AS student_name,
        c.course_name,
        i.intake_name,
        COALESCE(NULLIF(TRIM(a.payment_status), ''), 'Unpaid') AS payment_status
    FROM student_applications a
    LEFT JOIN users u ON u.user_id = a.user_id
    LEFT JOIN students s ON s.user_id = a.user_id
    LEFT JOIN student_profiles sp ON sp.user_id = a.user_id
    LEFT JOIN course_catalog c ON c.course_id = a.course_id
    LEFT JOIN course_intakes i ON i.intake_id = a.intake_id
    WHERE DATE(a.applied_at) BETWEEN '{$startDate}' AND '{$endDate}'
    {$whereCourse}
    ORDER BY a.application_id DESC
    LIMIT 50";
$resAppReport = mysqli_query($conn, $appReportSql);
if ($resAppReport) {
    while ($row = mysqli_fetch_assoc($resAppReport)) {
        $applicationReport[] = $row;
    }
}

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="studysmart_report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['StudySmart Report']);
    fputcsv($out, ['Generated', date('Y-m-d H:i:s')]);
    fputcsv($out, ['Date Range', "{$startDate} to {$endDate}"]);
    fputcsv($out, []);
    fputcsv($out, ['Total Students', $totalStudents]);
    fputcsv($out, ['Total Applications', $totalApplications]);
    fputcsv($out, ['Paid Payments', $paidPayments]);
    fputcsv($out, ['Pending Payments', $pendingPayments]);
    fputcsv($out, []);
    fputcsv($out, ['Applications by Course']);
    fputcsv($out, ['Course', 'Applications']);
    foreach ($appsByCourse as $row) {
        fputcsv($out, [$row['course_name'], (int) $row['app_count']]);
    }
    fputcsv($out, []);
    fputcsv($out, ['Application Report']);
    fputcsv($out, ['Application ID', 'Student', 'Course', 'Intake', 'Applied At', 'Status', 'Payment Status']);
    foreach ($applicationReport as $row) {
        fputcsv($out, [
            (int) $row['application_id'],
            $row['student_name'],
            $row['course_name'],
            $row['intake_name'],
            $row['applied_at'],
            $row['status'],
            $row['payment_status'],
        ]);
    }
    fputcsv($out, ['Payment Status']);
    fputcsv($out, ['Status', 'Count']);
    foreach ($payStatus as $k => $v) {
        fputcsv($out, [$k, $v]);
    }
    fclose($out);
    exit;
}

adminPageStart('Reports', 'reports', $adminName, [
    'note' => 'Filtered analytics: pick a date range and course, review KPIs, charts, and the application table — CSV and PDF exports use the same filters.',
    'class' => 'admin-page-header--reports',
]);
?>
<div class="admin-page admin-page--reports">
<div id="reportRoot">
    <header class="admin-reports-intro">
        <div class="admin-reports-intro__main">
            <span class="admin-reports-eyebrow">Reporting workspace</span>
            <h2 class="admin-reports-intro__title">Application &amp; payment insights</h2>
            <p class="admin-reports-intro__lede">This view is tuned for audits and term reviews — denser layout, document-style panels, and export-first controls.</p>
        </div>
        <ul class="admin-reports-intro__meta" aria-label="Report capabilities">
            <li>Date-scoped metrics</li>
            <li>Course filter</li>
            <li>CSV / PDF</li>
        </ul>
    </header>

<section class="admin-card-grid admin-report-kpi-grid">
    <article class="admin-card admin-report-kpi-card"><span class="admin-report-kpi-card__label">Total students</span><span class="admin-report-kpi-card__value"><?php echo $totalStudents; ?></span></article>
    <article class="admin-card admin-report-kpi-card"><span class="admin-report-kpi-card__label">Applications in range</span><span class="admin-report-kpi-card__value"><?php echo $applicationsInRange; ?></span></article>
    <article class="admin-card admin-report-kpi-card"><span class="admin-report-kpi-card__label">Paid (applications)</span><span class="admin-report-kpi-card__value"><?php echo $paidPayments; ?></span></article>
    <article class="admin-card admin-report-kpi-card"><span class="admin-report-kpi-card__label">Pending payment</span><span class="admin-report-kpi-card__value"><?php echo $pendingPayments; ?></span></article>
</section>

<section class="admin-toolbar admin-toolbar--reports">
    <div class="left">
        <a class="btn btn-primary" href="?export=csv&start_date=<?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?>&end_date=<?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>&course_id=<?php echo (int) $courseIdFilter; ?>">
            <span class="btn-icon"><svg viewBox="0 0 24 24"><path d="M12 3v10l3-3 1.4 1.4L12 17.8 7.6 11.4 9 10l3 3V3zM4 19h16v2H4z"/></svg></span>
            Export CSV/Excel
        </a>
        <button class="btn btn-primary" type="button" onclick="exportPDF()">
            <span class="btn-icon"><svg viewBox="0 0 24 24"><path d="M6 2h9l5 5v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm8 1.5V8h4.5zM8 12h8v2H8zm0 4h6v2H8z"/></svg></span>
            Export PDF
        </button>
    </div>
    <form class="right" method="get">
        <div>
            <label class="form-label">Start</label>
            <input class="form-control" type="date" name="start_date" value="<?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div>
            <label class="form-label">End</label>
            <input class="form-control" type="date" name="end_date" value="<?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div>
            <label class="form-label">Course</label>
            <select class="form-control" name="course_id">
                <option value="0">All courses</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?php echo (int) $c['course_id']; ?>" <?php echo ((int) $c['course_id'] === $courseIdFilter) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['course_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="align-self:end;">
            <button class="btn btn-primary" type="submit">
                <span class="btn-icon"><svg viewBox="0 0 24 24"><path d="M10 18a8 8 0 1 1 5.3-14l5.7 5.7-1.4 1.4-5.7-5.7A6 6 0 1 0 16 10h-2a4 4 0 1 1-4-4V4a6 6 0 0 0 0 12z"/></svg></span>
                Apply Filters
            </button>
        </div>
    </form>
</section>

<section class="admin-filter-summary admin-filter-summary--reports">
    <div class="admin-filter-chips">
        <span class="chip"><span class="k">Date</span> <?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?> → <?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?></span>
        <span class="chip"><span class="k">Course</span> <?php
            $courseLabel = 'All courses';
            foreach ($courses as $c) {
                if ((int) $c['course_id'] === $courseIdFilter) {
                    $courseLabel = (string) $c['course_name'];
                    break;
                }
            }
            echo htmlspecialchars($courseLabel, ENT_QUOTES, 'UTF-8');
        ?></span>
    </div>
    <div class="admin-filter-summary__hint">Exports include the table and summary blocks below.</div>
</section>

<section class="admin-grid-3 admin-reports-chart-deck">
    <div class="admin-chart-card admin-chart-card--reports">
        <h2>Applications by course</h2>
        <p class="admin-chart-card__sub">Top courses by application count for the selected window.</p>
        <div class="admin-chart-area"><canvas id="appsByCourse"></canvas></div>
    </div>
    <div class="admin-chart-card admin-chart-card--reports">
        <h2>Payment mix</h2>
        <p class="admin-chart-card__sub">Per-application payment buckets (rejected → unpaid).</p>
        <div class="admin-chart-area"><canvas id="paymentStatus"></canvas></div>
    </div>
    <div class="admin-chart-card admin-chart-card--reports">
        <h2>Daily application volume</h2>
        <p class="admin-chart-card__sub">Applications submitted per day in range.</p>
        <div class="admin-chart-area"><canvas id="appTrend"></canvas></div>
    </div>
</section>

<section class="admin-section admin-report-detail">
    <div class="admin-report-detail__head">
        <h2>Application register</h2>
        <span class="admin-report-detail__tag"><?php echo count($applicationReport); ?> rows</span>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Application ID</th>
                    <th>Student</th>
                    <th>Course</th>
                    <th>Intake</th>
                    <th>Applied At</th>
                    <th>Status</th>
                    <th>Payment</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applicationReport as $row): ?>
                    <?php
                    $s = strtolower((string) ($row['status'] ?? 'pending'));
                    $statusClass = $s === 'approved' ? 'status-approved' : ($s === 'rejected' ? 'status-rejected' : 'status-pending');
                    $p = strtolower((string) ($row['payment_status'] ?? 'unpaid'));
                    $payClass = $p === 'paid' ? 'status-paid' : ($p === 'pending' ? 'status-pending' : 'status-unpaid');
                    ?>
                    <tr>
                        <td><?php echo (int) $row['application_id']; ?></td>
                        <td><?php echo htmlspecialchars($row['student_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['course_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['intake_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $row['applied_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><span class="status-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td><span class="status-pill <?php echo $payClass; ?>"><?php echo htmlspecialchars((string) $row['payment_status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script>
  const appsByCourse = <?php echo json_encode($appsByCourse, JSON_UNESCAPED_UNICODE); ?>;
  const payStatus = <?php echo json_encode($payStatus, JSON_UNESCAPED_UNICODE); ?>;
  const trendLabels = <?php echo json_encode($trendLabels, JSON_UNESCAPED_UNICODE); ?>;
  const trendCounts = <?php echo json_encode($trendCounts, JSON_UNESCAPED_UNICODE); ?>;

  new Chart(document.getElementById('appsByCourse'), {
    type: 'bar',
    data: {
      labels: appsByCourse.map(r => r.course_name),
      datasets: [{
        label: 'Applications',
        data: appsByCourse.map(r => Number(r.app_count)),
        backgroundColor: 'rgba(13, 148, 136, 0.28)',
        borderColor: 'rgba(15, 118, 110, 0.95)',
        borderWidth: 1,
        borderRadius: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { ticks: { color: '#64748b', maxRotation: 42, minRotation: 0 }, grid: { display: false } },
        y: { beginAtZero: true, ticks: { precision: 0, color: '#64748b' }, grid: { color: 'rgba(148, 163, 184, 0.2)' } }
      }
    }
  });

  new Chart(document.getElementById('paymentStatus'), {
    type: 'doughnut',
    data: {
      labels: Object.keys(payStatus),
      datasets: [{
        data: Object.values(payStatus),
        backgroundColor: ['rgba(45, 212, 191, 0.45)','rgba(251, 191, 36, 0.5)','rgba(248, 113, 113, 0.45)'],
        borderColor: ['#0f766e','#b45309','#b91c1c'],
        borderWidth: 1
      }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: '#475569', boxWidth: 12 } } }, cutout: '62%' }
  });

  new Chart(document.getElementById('appTrend'), {
    type: 'line',
    data: {
      labels: trendLabels,
      datasets: [{
        label: 'Applications',
        data: trendCounts,
        borderColor: '#b45309',
        backgroundColor: 'rgba(251, 191, 36, 0.18)',
        fill: true,
        tension: 0.38,
        pointRadius: 3,
        pointBackgroundColor: '#fff7ed',
        pointBorderColor: '#c2410c'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { ticks: { color: '#64748b', maxRotation: 40 }, grid: { color: 'rgba(148, 163, 184, 0.15)' } },
        y: { beginAtZero: true, ticks: { precision: 0, color: '#64748b' }, grid: { color: 'rgba(148, 163, 184, 0.2)' } }
      }
    }
  });

  async function exportPDF() {
    const root = document.getElementById('reportRoot');
    const canvas = await html2canvas(root, { scale: 2, backgroundColor: '#faf8f5' });
    const imgData = canvas.toDataURL('image/png');
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('p', 'mm', 'a4');
    const pageWidth = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();
    const imgWidth = pageWidth;
    const imgHeight = canvas.height * imgWidth / canvas.width;
    let y = 0;
    pdf.addImage(imgData, 'PNG', 0, y, imgWidth, imgHeight);
    while (imgHeight + y > pageHeight) {
      y -= pageHeight;
      pdf.addPage();
      pdf.addImage(imgData, 'PNG', 0, y, imgWidth, imgHeight);
    }
    pdf.save('studysmart_report.pdf');
  }
</script>
</div>
<?php adminPageEnd(); ?>
