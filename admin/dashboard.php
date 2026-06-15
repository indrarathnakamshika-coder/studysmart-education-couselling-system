<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

function countTable(string $table): int
{
    global $conn;
    $result = mysqli_query($conn, "SELECT COUNT(*) AS total_count FROM {$table}");
    $row = $result ? mysqli_fetch_assoc($result) : null;
    return (int) ($row['total_count'] ?? 0);
}

$totalStudents = countTable('students');
$totalCourses = countTable('course_catalog');
$totalApplications = countTable('student_applications');
$totalPayments = countTable('student_payments');

$applicationsByCourse = [];
$resAppCourse = mysqli_query($conn, "SELECT c.course_name, COUNT(a.application_id) AS app_count
    FROM course_catalog c
    LEFT JOIN student_applications a ON a.course_id = c.course_id
    GROUP BY c.course_id
    ORDER BY app_count DESC
    LIMIT 8");
if ($resAppCourse) {
    while ($row = mysqli_fetch_assoc($resAppCourse)) {
        $applicationsByCourse[] = $row;
    }
}

// Per-application payment funnel (rejected applications count as Unpaid; not tied to student_payments rows).
$paymentStatus = ['Paid' => 0, 'Pending' => 0, 'Unpaid' => 0];
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
        if (isset($paymentStatus[$statusKey])) {
            $paymentStatus[$statusKey] = (int) $row['c'];
        }
    }
}

$trendLabels = [];
$trendCounts = [];
$resTrend = mysqli_query($conn, "SELECT DATE(applied_at) AS d, COUNT(*) AS c
    FROM student_applications
    WHERE applied_at >= (CURRENT_DATE - INTERVAL 13 DAY)
    GROUP BY DATE(applied_at)
    ORDER BY d ASC");
if ($resTrend) {
    while ($row = mysqli_fetch_assoc($resTrend)) {
        $trendLabels[] = $row['d'];
        $trendCounts[] = (int) $row['c'];
    }
}

$recentApplications = [];
$resRecentApps = mysqli_query($conn, "SELECT a.application_id, a.applied_at, a.status, u.username, c.course_name
    FROM student_applications a
    LEFT JOIN users u ON u.user_id = a.user_id
    LEFT JOIN course_catalog c ON c.course_id = a.course_id
    ORDER BY a.application_id DESC
    LIMIT 6");
if ($resRecentApps) {
    while ($row = mysqli_fetch_assoc($resRecentApps)) {
        $recentApplications[] = $row;
    }
}

$recentPayments = [];
$resRecentPays = mysqli_query($conn, "SELECT p.payment_id, p.updated_at, p.payment_status, p.registration_fee, u.username
    FROM student_payments p
    LEFT JOIN users u ON u.user_id = p.user_id
    ORDER BY p.updated_at DESC
    LIMIT 6");
if ($resRecentPays) {
    while ($row = mysqli_fetch_assoc($resRecentPays)) {
        $recentPayments[] = $row;
    }
}

adminPageStart('Admin Dashboard', 'dashboard', $adminName);
?>
<div class="admin-page admin-page--dashboard">
<section class="admin-card-grid">
    <article class="admin-card">
        <div class="admin-kpi-top">
            <div>
                <div class="label">Total Students</div>
                <div class="value"><?php echo $totalStudents; ?></div>
            </div>
            <div class="admin-kpi-icon" title="Students">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 1 9l11 6 9-4.9V17h2V9zm-6 9.6V16c0 2.2 2.7 4 6 4s6-1.8 6-4v-3.4l-6 3.2z"/></svg>
            </div>
        </div>
    </article>
    <article class="admin-card">
        <div class="admin-kpi-top">
            <div>
                <div class="label">Total Courses</div>
                <div class="value"><?php echo $totalCourses; ?></div>
            </div>
            <div class="admin-kpi-icon" title="Courses">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v2H4zm0 4h10v2H4zm0 4h16v2H4zm0 4h10v2H4z"/></svg>
            </div>
        </div>
    </article>
    <article class="admin-card">
        <div class="admin-kpi-top">
            <div>
                <div class="label">Total Applications</div>
                <div class="value"><?php echo $totalApplications; ?></div>
                <div class="admin-sparkline">
                    <?php
                    $maxTrend = 1;
                    foreach ($trendCounts as $v) {
                        $maxTrend = max($maxTrend, (int) $v);
                    }
                    $points = [];
                    $countTrend = max(1, count($trendCounts));
                    for ($i = 0; $i < $countTrend; $i++) {
                        $x = ($countTrend === 1) ? 0 : (int) round(($i / ($countTrend - 1)) * 100);
                        $y = 24 - (int) round(((int) ($trendCounts[$i] ?? 0) / $maxTrend) * 24);
                        $points[] = "{$x},{$y}";
                    }
                    ?>
                    <svg viewBox="0 0 100 26" width="100%" height="26" aria-hidden="true">
                        <polyline points="<?php echo htmlspecialchars(implode(' ', $points), ENT_QUOTES, 'UTF-8'); ?>" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></polyline>
                    </svg>
                </div>
            </div>
            <div class="admin-kpi-icon" title="Applications">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h9l5 5v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm8 1.5V8h4.5zM8 12h8v1.8H8zm0 4h8v1.8H8z"/></svg>
            </div>
        </div>
    </article>
    <article class="admin-card">
        <div class="admin-kpi-top">
            <div>
                <div class="label">Total Payments</div>
                <div class="value"><?php echo $totalPayments; ?></div>
                <div class="admin-kpi-note">Track payment verification progress.</div>
            </div>
            <div class="admin-kpi-icon" title="Payments">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2H3zm0 5h18v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zm3 3v2h6v-2z"/></svg>
            </div>
        </div>
    </article>
</section>

<section class="admin-grid-3">
    <div class="admin-chart-card">
        <h2>Applications by Course</h2>
        <div class="admin-chart-area"><canvas id="chartAppsCourse"></canvas></div>
    </div>
    <div class="admin-chart-card">
        <h2>Payment Status</h2>
        <div class="admin-chart-area"><canvas id="chartPayments"></canvas></div>
    </div>
    <div class="admin-chart-card admin-chart-card--trend">
        <h2>Applications Trend (14 days)</h2>
        <div class="admin-chart-area admin-chart-area--trend"><canvas id="chartTrend"></canvas></div>
    </div>
</section>

<section class="admin-grid-2">
    <div class="admin-section">
        <h2 style="margin-bottom:10px;">Recent Activity</h2>
        <div class="admin-activity-list">
            <?php foreach ($recentApplications as $app): ?>
                <div class="admin-activity-item">
                    <div>
                        <strong>Application #<?php echo (int) $app['application_id']; ?></strong>
                        <div><span><?php echo htmlspecialchars($app['username'] ?? 'Student', ENT_QUOTES, 'UTF-8'); ?> → <?php echo htmlspecialchars($app['course_name'] ?? 'Course', ENT_QUOTES, 'UTF-8'); ?></span></div>
                    </div>
                    <div>
                        <span><?php echo htmlspecialchars((string) $app['status'], ENT_QUOTES, 'UTF-8'); ?></span><br>
                        <span><?php echo htmlspecialchars((string) $app['applied_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="admin-activity-list" style="margin-top:12px;">
            <?php foreach ($recentPayments as $pay): ?>
                <div class="admin-activity-item">
                    <div>
                        <strong>Payment #<?php echo (int) $pay['payment_id']; ?></strong>
                        <div><span><?php echo htmlspecialchars($pay['username'] ?? 'Student', ENT_QUOTES, 'UTF-8'); ?> • LKR <?php echo htmlspecialchars(number_format((float) $pay['registration_fee'], 2), ENT_QUOTES, 'UTF-8'); ?></span></div>
                    </div>
                    <div>
                        <span><?php echo htmlspecialchars((string) $pay['payment_status'], ENT_QUOTES, 'UTF-8'); ?></span><br>
                        <span><?php echo htmlspecialchars((string) $pay['updated_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="admin-section">
        <h2 style="margin-bottom:10px;">Quick Actions</h2>
        <div class="admin-quick-actions">
            <a class="admin-quick-action" href="courses.php">
                <div class="title">Add / Update Courses</div>
                <div class="desc">Maintain course data that powers recommendations.</div>
            </a>
            <a class="admin-quick-action" href="intakes.php">
                <div class="title">Manage Intakes</div>
                <div class="desc">Open/close intakes and control seat availability.</div>
            </a>
            <a class="admin-quick-action" href="applications.php">
                <div class="title">Review Applications</div>
                <div class="desc">Approve or reject pending student applications.</div>
            </a>
            <a class="admin-quick-action" href="reports.php">
                <div class="title">View Reports</div>
                <div class="desc">Analytics, exports, and operational summaries.</div>
            </a>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  const appsByCourse = <?php echo json_encode($applicationsByCourse, JSON_UNESCAPED_UNICODE); ?>;
  const payStatus = <?php echo json_encode($paymentStatus, JSON_UNESCAPED_UNICODE); ?>;
  const trendLabels = <?php echo json_encode($trendLabels, JSON_UNESCAPED_UNICODE); ?>;
  const trendCounts = <?php echo json_encode($trendCounts, JSON_UNESCAPED_UNICODE); ?>;

  new Chart(document.getElementById('chartAppsCourse'), {
    type: 'bar',
    data: {
      labels: appsByCourse.map(r => r.course_name),
      datasets: [{
        label: 'Applications',
        data: appsByCourse.map(r => Number(r.app_count)),
        backgroundColor: 'rgba(37, 99, 235, 0.22)',
        borderColor: 'rgba(26, 61, 100, 0.95)',
        borderWidth: 1,
        borderRadius: 10
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
  });

  new Chart(document.getElementById('chartPayments'), {
    type: 'doughnut',
    data: {
      labels: Object.keys(payStatus),
      datasets: [{
        data: Object.values(payStatus),
        backgroundColor: ['rgba(34,197,94,0.35)','rgba(245,158,11,0.35)','rgba(239,68,68,0.35)'],
        borderColor: ['#16a34a','#d97706','#dc2626'],
        borderWidth: 1
      }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '65%' }
  });

  (function () {
    const trendCtx = document.getElementById('chartTrend');
    const trendGradientFill = (context) => {
      const chart = context.chart;
      const { ctx, chartArea } = chart;
      if (!chartArea) {
        return 'rgba(14, 165, 233, 0.12)';
      }
      const g = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
      g.addColorStop(0, 'rgba(14, 165, 233, 0.04)');
      g.addColorStop(0.45, 'rgba(59, 130, 246, 0.14)');
      g.addColorStop(1, 'rgba(99, 102, 241, 0.22)');
      return g;
    };
    new Chart(trendCtx, {
      type: 'line',
      data: {
        labels: trendLabels,
        datasets: [{
          label: 'Applications',
          data: trendCounts,
          borderColor: '#0369a1',
          borderWidth: 2.5,
          backgroundColor: trendGradientFill,
          fill: true,
          tension: 0.42,
          cubicInterpolationMode: 'monotone',
          pointRadius: 4,
          pointHoverRadius: 6,
          pointBackgroundColor: '#ffffff',
          pointBorderColor: '#0284c7',
          pointBorderWidth: 2,
          pointHoverBackgroundColor: '#e0f2fe',
          pointHoverBorderColor: '#0369a1'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.92)',
            titleFont: { size: 12, weight: '600' },
            bodyFont: { size: 12 },
            padding: 10,
            cornerRadius: 8,
            displayColors: false
          }
        },
        scales: {
          x: {
            grid: { color: 'rgba(148, 163, 184, 0.18)', drawTicks: false },
            ticks: {
              maxRotation: 40,
              minRotation: 0,
              font: { size: 10, weight: '500' },
              color: '#64748b'
            },
            border: { display: false }
          },
          y: {
            beginAtZero: true,
            ticks: { precision: 0, font: { size: 11, weight: '500' }, color: '#64748b' },
            grid: { color: 'rgba(148, 163, 184, 0.14)' },
            border: { display: false }
          }
        }
      }
    });
  })();
</script>
</div>
<?php adminPageEnd(); ?>
