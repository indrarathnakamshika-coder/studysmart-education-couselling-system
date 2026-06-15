<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

$myApplications = [];
$appDashStmt = mysqli_prepare(
    $conn,
    'SELECT a.application_id, a.status, a.payment_status, a.applied_at, c.course_name
    FROM student_applications a
    INNER JOIN course_catalog c ON c.course_id = a.course_id
    WHERE a.user_id = ?
    ORDER BY a.applied_at DESC'
);
if ($appDashStmt) {
    mysqli_stmt_bind_param($appDashStmt, 'i', $userId);
    mysqli_stmt_execute($appDashStmt);
    $appDashRes = mysqli_stmt_get_result($appDashStmt);
    if ($appDashRes) {
        while ($row = mysqli_fetch_assoc($appDashRes)) {
            $myApplications[] = $row;
        }
    }
    mysqli_stmt_close($appDashStmt);
}

$showProfileCelebration = isset($_GET['profile_complete']) && $_GET['profile_complete'] === '1';
$progLevel = (int) ($profile['progression_level'] ?? 0);
$eligibleText = trim((string) ($profile['eligible_category'] ?? ''));
$eligibleDisplay = $eligibleText !== '' ? htmlspecialchars(ucfirst($eligibleText), ENT_QUOTES, 'UTF-8') : '';

$isFreshProfile = $profile === null
    || (
        trim((string) ($profile['education_qualification'] ?? '')) === ''
        && trim((string) ($profile['financial_status'] ?? '')) === ''
        && trim((string) ($profile['interests'] ?? '')) === ''
    );
$profilePrimaryCta = $isFreshProfile ? 'Set Up My Profile' : 'Complete Profile';

studentPageStart('Dashboard', 'dashboard', $studentName);
?>

<?php if ($showProfileCelebration): ?>
    <div class="alert alert-success dashboard-alert-soft">Profile saved. Your level is set below.</div>
<?php endif; ?>

<?php if (!empty($myApplications)): ?>
    <?php
    foreach ($myApplications as $dashApp):
        $st = strtolower((string) ($dashApp['status'] ?? ''));
        $pay = strtolower((string) ($dashApp['payment_status'] ?? 'unpaid'));
        if ($st !== 'approved' || $pay === 'paid') {
            continue;
        }
        $cname = htmlspecialchars((string) ($dashApp['course_name'] ?? 'your course'), ENT_QUOTES, 'UTF-8');
        ?>
        <div class="student-app-alert student-app-alert--success" role="status">
            <div class="student-app-alert__body">
                <strong>Application approved</strong>
                <p>Your application for <strong><?php echo $cname; ?></strong> has been approved. Complete payment to finish enrolment for this programme.</p>
            </div>
            <div class="student-app-alert__action">
                <a class="btn-primary btn-action" href="payments.php?application_id=<?php echo (int) $dashApp['application_id']; ?>">
                    <span class="btn-icon">👉</span>
                    <span>Proceed to payment</span>
                </a>
            </div>
        </div>
    <?php endforeach; ?>

    <section class="student-section student-dashboard-pipeline" aria-labelledby="dash-app-pipeline-heading">
        <h2 id="dash-app-pipeline-heading">Application progress</h2>
        <p class="help-text">Track each course application: review → approval → payment completed.</p>
        <div class="student-app-pipeline-list">
            <?php foreach ($myApplications as $dashApp): ?>
                <?php
                $st = strtolower((string) ($dashApp['status'] ?? ''));
                $pay = strtolower((string) ($dashApp['payment_status'] ?? 'unpaid'));
                $courseLabel = htmlspecialchars((string) ($dashApp['course_name'] ?? '—'), ENT_QUOTES, 'UTF-8');
                $isRejected = $st === 'rejected';
                $isPending = $st === 'pending';
                $isApproved = $st === 'approved';
                $isPaid = $pay === 'paid';
                $step1Done = $isApproved || $isPaid;
                $step2Done = $isPaid;
                $step3Done = $isPaid;
                $step1Current = $isPending;
                $step2Current = $isApproved && !$isPaid;
                $step3Current = $isPaid;
                ?>
                <article class="student-app-pipeline-card">
                    <header class="student-app-pipeline-card__head">
                        <h3><?php echo $courseLabel; ?></h3>
                        <span class="help-text">Applied <?php echo htmlspecialchars((string) ($dashApp['applied_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    </header>
                    <?php if ($isRejected): ?>
                        <p class="student-app-pipeline-rejected">This application was not approved. Contact the office if you have questions.</p>
                    <?php else: ?>
                        <ol class="student-app-stages" aria-label="Progress for <?php echo $courseLabel; ?>">
                            <li class="student-app-stage <?php echo $step1Done ? 'is-done' : ''; ?> <?php echo $step1Current ? 'is-current' : ''; ?>">
                                <span class="student-app-stage__dot">1</span>
                                <span class="student-app-stage__label">Pending</span>
                                <span class="student-app-stage__hint">Under review</span>
                            </li>
                            <li class="student-app-stage <?php echo $step2Done ? 'is-done' : ''; ?> <?php echo $step2Current ? 'is-current' : ''; ?>">
                                <span class="student-app-stage__dot">2</span>
                                <span class="student-app-stage__label">Approved</span>
                                <span class="student-app-stage__hint">Ready to pay</span>
                            </li>
                            <li class="student-app-stage <?php echo $step3Done ? 'is-done' : ''; ?> <?php echo $step3Current ? 'is-current' : ''; ?>">
                                <span class="student-app-stage__dot">3</span>
                                <span class="student-app-stage__label">Completed</span>
                                <span class="student-app-stage__hint">Payment received</span>
                            </li>
                        </ol>
                        <?php if ($isApproved && $isPaid): ?>
                            <p class="student-app-pipeline-done">Payment recorded for this application. Thank you.</p>
                        <?php elseif ($isPending): ?>
                            <p class="student-app-pipeline-wait">We will update this when your application has been reviewed.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if (!$profileComplete): ?>
    <nav class="dashboard-guided-flow" aria-label="Getting started steps">
        <div class="dashboard-guided-step is-active">
            <span class="dashboard-guided-num">1</span>
            <div>
                <strong>Complete profile</strong>
                <p>Qualification, interests, and financial details</p>
            </div>
        </div>
        <div class="dashboard-guided-connector" aria-hidden="true"></div>
        <div class="dashboard-guided-step">
            <span class="dashboard-guided-num">2</span>
            <div>
                <strong>Explore courses</strong>
                <p>See programs matched to your level</p>
            </div>
        </div>
        <div class="dashboard-guided-connector" aria-hidden="true"></div>
        <div class="dashboard-guided-step">
            <span class="dashboard-guided-num">3</span>
            <div>
                <strong>Apply &amp; enrol</strong>
                <p>Submit an application and continue when approved</p>
            </div>
        </div>
    </nav>

    <section class="student-section dashboard-setup-hero dashboard-setup-hero--compact">
        <div class="dashboard-setup-copy">
            <h2>Let’s personalize your journey</h2>
            <p class="help-text">
                Tell us about your education, interests, and financial situation so we can place you on the right pathway
                and recommend courses that fit your goals.
            </p>
            <div class="dashboard-actions-row">
                <a class="btn-primary btn-action dashboard-cta-primary" href="profile.php?setup=1" title="Open the profile form">
                    <span class="btn-icon">👉</span>
                    <span><?php echo htmlspecialchars($profilePrimaryCta, ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            </div>
            <p class="dashboard-cta-caption help-text">You can return anytime; your progress is saved when you submit the form.</p>
        </div>
        <div class="dashboard-setup-visual">
            <img src="../assets/dash.png" alt="Illustration of a student planning their study pathway">
        </div>
    </section>
<?php else: ?>
    <section class="student-section dashboard-main-moderate">
        <div class="dashboard-moderate-inner">
            <p class="dashboard-kicker">Your pathway</p>

            <div class="level-progress-wrap">
                <div>
                    <div class="level-steps level-steps--moderate" role="img" aria-label="Progress levels one through four; you are at level <?php echo $progLevel >= 1 && $progLevel <= 4 ? $progLevel : 1; ?>">
                        <?php for ($s = 1; $s <= 4; $s++): ?>
                            <div class="level-step <?php echo $progLevel >= $s ? 'is-done' : ''; ?> <?php echo $progLevel === $s ? 'is-current' : ''; ?>">
                                <span class="level-step-circle"><?php echo $s; ?></span>
                                <span class="level-step-label">
                                    <?php
                                    if ($s === 1) {
                                        echo 'Skills';
                                    } elseif ($s === 2) {
                                        echo 'Foundation';
                                    } elseif ($s === 3) {
                                        echo 'HND';
                                    } elseif ($s === 4) {
                                        echo 'Degree';
                                    } else {
                                        echo 'Level ' . $s;
                                    }
                                    ?>
                                </span>
                            </div>
                            <?php if ($s < 4): ?>
                                <div class="level-step-connector <?php echo $progLevel > $s ? 'is-done' : ''; ?>"></div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>

                    <div class="dashboard-level-message dashboard-level-message--moderate">
                        <?php if ($progLevel >= 1 && $progLevel <= 4 && $eligibleDisplay !== ''): ?>
                            <p class="dashboard-level-main">
                                You are currently at <strong>Level <?php echo $progLevel; ?></strong>. Based on your qualifications, you are eligible for
                                <strong><?php echo $eligibleDisplay; ?></strong>.
                                Keep progressing towards your academic goals!
                            </p>
                        <?php else: ?>
                            <p class="dashboard-level-main">Your profile is complete. Explore recommended courses to take the next step.</p>
                        <?php endif; ?>
                        <p class="dashboard-motivation">Your journey to success starts here. Let StudySmart guide your future.</p>

                        <div class="dashboard-actions-row dashboard-actions-row--center">
                            <a class="btn-primary btn-action" href="profile.php">
                                <span class="btn-icon">▣</span>
                                <span>My profile</span>
                            </a>
                            <a class="btn-primary btn-action btn-secondary-action" href="recommendations.php">
                                <span class="btn-icon">👉</span>
                                <span>View Recommended Courses</span>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="level-illustration">
                    <figure class="dashboard-path-visual">
                        <figcaption class="dashboard-path-caption">Your current StudySmart level</figcaption>
                        <div class="dashboard-path-svg-wrap" aria-hidden="true">
                            <svg class="dashboard-path-svg" viewBox="0 0 220 240" xmlns="http://www.w3.org/2000/svg">
                                <path d="M30 210 Q110 40 190 30" fill="none" stroke="#e2e8f0" stroke-width="10" stroke-linecap="round"/>
                                <path
                                    d="M30 210 Q110 40 190 30"
                                    fill="none"
                                    stroke="#2563eb"
                                    stroke-width="10"
                                    stroke-linecap="round"
                                    stroke-dasharray="380"
                                    stroke-dashoffset="<?php echo htmlspecialchars((string) max(0, 380 - (int) round($progLevel * 95)), ENT_QUOTES, 'UTF-8'); ?>"
                                    style="transition: stroke-dashoffset 0.75s ease;"
                                    opacity="0.92"
                                />
                                <?php
                                $nodes = [
                                    ['cx' => 30, 'cy' => 210, 'step' => 1],
                                    ['cx' => 78, 'cy' => 120, 'step' => 2],
                                    ['cx' => 132, 'cy' => 72, 'step' => 3],
                                    ['cx' => 190, 'cy' => 30, 'step' => 4],
                                ];
                                foreach ($nodes as $node):
                                    $n = (int) $node['step'];
                                    $done = $progLevel >= $n;
                                    $current = $progLevel === $n;
                                    $fill = $done ? '#1d4ed8' : '#e2e8f0';
                                    $stroke = $current ? '#fbbf24' : ($done ? '#1e3a8a' : '#cbd5e1');
                                    ?>
                                    <circle
                                        cx="<?php echo (int) $node['cx']; ?>"
                                        cy="<?php echo (int) $node['cy']; ?>"
                                        r="<?php echo $current ? '18' : '15'; ?>"
                                        fill="<?php echo htmlspecialchars($fill, ENT_QUOTES, 'UTF-8'); ?>"
                                        stroke="<?php echo htmlspecialchars($stroke, ENT_QUOTES, 'UTF-8'); ?>"
                                        stroke-width="<?php echo $current ? '4' : '2'; ?>"
                                    />
                                    <text
                                        x="<?php echo (int) $node['cx']; ?>"
                                        y="<?php echo (int) $node['cy'] + 5; ?>"
                                        text-anchor="middle"
                                        fill="<?php echo $done ? '#ffffff' : '#64748b'; ?>"
                                        font-size="14"
                                        font-weight="700"
                                        font-family="Segoe UI, system-ui, sans-serif"
                                    ><?php echo $n; ?></text>
                                <?php endforeach; ?>
                            </svg>
                        </div>
                    </figure>
                </div>
            </div>

            <div class="dashboard-hub">
                <h3 class="dashboard-hub-title">Your workspace</h3>
                <div class="dashboard-hub-grid">
                    <article class="dashboard-hub-card">
                        <h3>Profile readiness</h3>
                        <p class="dashboard-hub-stat"><?php echo (int) $profileCompletion; ?>%</p>
                        <p>Keep your interests and budget updated so matches stay accurate.</p>
                        <div class="dashboard-hub-actions">
                            <a class="btn-primary btn-action btn-secondary-action" href="profile.php"><span class="btn-icon">✎</span><span>Edit profile</span></a>
                        </div>
                    </article>
                    <article class="dashboard-hub-card">
                        <h3>Recommended courses</h3>
                        <p class="dashboard-hub-stat"><?php echo (int) $recommendedCount; ?></p>
                        <p>Programs aligned with your current StudySmart level.</p>
                        <div class="dashboard-hub-actions">
                            <a class="btn-primary btn-action" href="recommendations.php"><span class="btn-icon">→</span><span>Open recommendations</span></a>
                        </div>
                    </article>
                    <article class="dashboard-hub-card">
                        <h3>Applications</h3>
                        <p class="dashboard-hub-stat"><?php echo (int) $applicationsCount; ?></p>
                        <p>Track approvals and pay course fees when you are accepted.</p>
                        <div class="dashboard-hub-actions">
                            <a class="btn-primary btn-action btn-secondary-action" href="applications.php"><span class="btn-icon">▤</span><span>My applications</span></a>
                        </div>
                    </article>
                </div>
                <div class="dashboard-hub-wide">
                    <h3>Next steps</h3>
                    <ul>
                        <li>Review <strong>recommended courses</strong> and shortlist programs that fit your goals.</li>
                        <li>Submit an <strong>application</strong> from a course card — you will get status updates there.</li>
                        <li>When an application is <strong>approved</strong>, use <strong>Make Payment</strong> to complete the course fee checkout for that program.</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php studentPageEnd(); ?>
