<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_delete'])) {
    $delId = (int) ($_POST['user_id'] ?? 0);
    if ($delId > 0) {
        $chk = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_id = ? AND role = 'student' LIMIT 1");
        if ($chk) {
            mysqli_stmt_bind_param($chk, 'i', $delId);
            mysqli_stmt_execute($chk);
            $chkRes = mysqli_stmt_get_result($chk);
            $okStudent = $chkRes && mysqli_fetch_assoc($chkRes);
            mysqli_stmt_close($chk);
            if ($okStudent) {
                $tblHist = mysqli_query($conn, "SHOW TABLES LIKE 'student_payment_history'");
                if ($tblHist && mysqli_num_rows($tblHist) > 0) {
                    $h = mysqli_prepare($conn, 'DELETE FROM student_payment_history WHERE user_id = ?');
                    if ($h) {
                        mysqli_stmt_bind_param($h, 'i', $delId);
                        mysqli_stmt_execute($h);
                        mysqli_stmt_close($h);
                    }
                }
                $tables = [
                    'DELETE FROM student_payments WHERE user_id = ?',
                    'DELETE FROM student_applications WHERE user_id = ?',
                    'DELETE FROM student_profiles WHERE user_id = ?',
                    'DELETE FROM students WHERE user_id = ?',
                    'DELETE FROM users WHERE user_id = ? AND role = ?',
                ];
                foreach ($tables as $idx => $sql) {
                    $st = mysqli_prepare($conn, $sql);
                    if ($st) {
                        if ($idx === 4) {
                            $roleStudent = 'student';
                            mysqli_stmt_bind_param($st, 'is', $delId, $roleStudent);
                        } else {
                            mysqli_stmt_bind_param($st, 'i', $delId);
                        }
                        mysqli_stmt_execute($st);
                        mysqli_stmt_close($st);
                    }
                }
                flash_put('success', 'Student account and related records were removed.');
            } else {
                flash_put('error', 'Could not delete that account.');
            }
        }
    }
    header('Location: students.php');
    exit;
}

function profileCompletionPercent(?array $profileData): int
{
    if ($profileData === null) {
        return 0;
    }

    $fields = [
        'full_name', 'phone', 'date_of_birth', 'address', 'city', 'country',
        'highest_education', 'institution', 'graduation_year', 'gpa',
        'financial_status', 'budget_range', 'study_mode', 'preferred_field', 'preferred_level'
    ];

    $filled = 0;
    foreach ($fields as $field) {
        $value = trim((string) ($profileData[$field] ?? ''));
        if ($value !== '') {
            $filled++;
        }
    }

    return (int) round(($filled / count($fields)) * 100);
}

$students = [];
$sql = "SELECT
            u.user_id,
            u.username,
            u.email,
            COALESCE(p.full_name, s.full_name, '') AS student_name,
            COALESCE(p.phone, s.phone, '') AS phone,
            p.city,
            p.country,
            p.highest_education,
            p.institution,
            p.graduation_year,
            p.gpa,
            p.preferred_field,
            p.preferred_level,
            p.study_mode,
            p.financial_status,
            p.budget_range,
            p.address,
            p.date_of_birth,
            p.notes,
            p.updated_at AS profile_updated_at
        FROM users u
        LEFT JOIN students s ON s.user_id = u.user_id
        LEFT JOIN student_profiles p ON p.user_id = u.user_id
        WHERE u.role = 'student'
        ORDER BY u.user_id DESC";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }
}

$flashOk = flash_take('success');
$flashErr = flash_take('error');

adminPageStart('Manage Students', 'students', $adminName);
?>
<section class="admin-section">
    <div class="admin-section-head">
        <h2>Registered Students</h2>
        <a class="btn btn-primary" href="../register.php" target="_blank" rel="noopener noreferrer">
            Register new student
        </a>
    </div>
    <?php if ($flashOk !== null): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($flashErr !== null): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($flashErr, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Profile Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                <?php
                    $profile = [
                        'full_name' => $student['student_name'] ?? '',
                        'phone' => $student['phone'] ?? '',
                        'date_of_birth' => $student['date_of_birth'] ?? '',
                        'address' => $student['address'] ?? '',
                        'city' => $student['city'] ?? '',
                        'country' => $student['country'] ?? '',
                        'highest_education' => $student['highest_education'] ?? '',
                        'institution' => $student['institution'] ?? '',
                        'graduation_year' => $student['graduation_year'] ?? '',
                        'gpa' => $student['gpa'] ?? '',
                        'financial_status' => $student['financial_status'] ?? '',
                        'budget_range' => $student['budget_range'] ?? '',
                        'study_mode' => $student['study_mode'] ?? '',
                        'preferred_field' => $student['preferred_field'] ?? '',
                        'preferred_level' => $student['preferred_level'] ?? '',
                        'notes' => $student['notes'] ?? '',
                        'updated_at' => $student['profile_updated_at'] ?? '',
                    ];
                    $completion = profileCompletionPercent($profile);
                    $statusText = $completion >= 80 ? 'Complete' : ($completion >= 30 ? 'Partial' : 'Incomplete');
                    $statusClass = $completion >= 80 ? 'status-approved' : ($completion >= 30 ? 'status-pending' : 'status-unpaid');

                    $detailsPayload = [
                        'id' => (int) $student['user_id'],
                        'name' => (string) ($student['student_name'] ?: 'N/A'),
                        'username' => (string) ($student['username'] ?? ''),
                        'email' => (string) ($student['email'] ?? ''),
                        'phone' => (string) ($student['phone'] ?: '-'),
                        'profile_status' => $statusText,
                        'profile_completion' => $completion,
                        'city' => (string) ($student['city'] ?: '-'),
                        'country' => (string) ($student['country'] ?: '-'),
                        'education' => (string) ($student['highest_education'] ?: '-'),
                        'institution' => (string) ($student['institution'] ?: '-'),
                        'graduation_year' => (string) ($student['graduation_year'] ?: '-'),
                        'gpa' => (string) ($student['gpa'] ?: '-'),
                        'preferred_stream' => (string) ($student['preferred_field'] ?: '-'),
                        'preferred_level' => (string) ($student['preferred_level'] ?: '-'),
                        'study_mode' => (string) ($student['study_mode'] ?: '-'),
                        'financial_status' => (string) ($student['financial_status'] ?: '-'),
                        'budget_range' => (string) ($student['budget_range'] ?: '-'),
                        'address' => (string) ($student['address'] ?: '-'),
                        'date_of_birth' => (string) ($student['date_of_birth'] ?: '-'),
                        'notes' => (string) ($student['notes'] ?: '-'),
                        'updated_at' => (string) ($student['profile_updated_at'] ?: '-'),
                    ];
                ?>
                <tr>
                    <td><?php echo (int) $student['user_id']; ?></td>
                    <td><?php echo htmlspecialchars($student['student_name'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($student['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($student['phone'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <span class="status-pill <?php echo $statusClass; ?>">
                            <?php echo htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8'); ?> (<?php echo (int) $completion; ?>%)
                        </span>
                    </td>
                    <td>
                        <div class="admin-table-actions">
                            <a class="btn btn-secondary btn-small" href="student_edit.php?id=<?php echo (int) $student['user_id']; ?>">Edit</a>
                            <button
                                type="button"
                                class="btn btn-ghost btn-small"
                                data-student-details="<?php echo htmlspecialchars(json_encode($detailsPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                onclick="openStudentDetails(this)"
                            >
                                View
                            </button>
                            <form method="post" class="admin-inline-form" onsubmit="return confirm('Delete this student and all related applications and payments? This cannot be undone.');">
                                <input type="hidden" name="student_delete" value="1">
                                <input type="hidden" name="user_id" value="<?php echo (int) $student['user_id']; ?>">
                                <button type="submit" class="btn btn-danger btn-small">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="admin-modal-backdrop" id="studentDetailsBackdrop" role="dialog" aria-modal="true" aria-labelledby="studentDetailsTitle">
    <div class="admin-modal">
        <div class="admin-modal-header">
            <div>
                <h3 id="studentDetailsTitle">Student Details</h3>
                <p id="studentDetailsSubtitle">Profile information</p>
            </div>
            <button type="button" class="btn btn-ghost" onclick="closeStudentDetails()">Close</button>
        </div>
        <div class="admin-modal-body">
            <div class="admin-detail-grid" id="studentDetailsGrid"></div>
        </div>
    </div>
</div>

<script>
    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function detailCard(label, value) {
        return `
            <div class="admin-detail-card">
                <div class="k">${escapeHtml(label)}</div>
                <div class="v">${escapeHtml(value ?? '-')}</div>
            </div>
        `;
    }

    function openStudentDetails(btn) {
        const raw = btn.getAttribute('data-student-details') || '{}';
        let data = {};
        try { data = JSON.parse(raw); } catch (e) { data = {}; }

        const title = `Student #${data.id ?? ''} — ${data.name ?? 'Details'}`;
        const subtitle = `${data.profile_status ?? 'Profile'} • ${data.profile_completion ?? 0}% completion`;

        document.getElementById('studentDetailsTitle').textContent = title;
        document.getElementById('studentDetailsSubtitle').textContent = subtitle;

        const grid = document.getElementById('studentDetailsGrid');
        grid.innerHTML = [
            detailCard('Username', data.username),
            detailCard('Email', data.email),
            detailCard('Phone', data.phone),
            detailCard('City', data.city),
            detailCard('Country', data.country),
            detailCard('Highest Education', data.education),
            detailCard('Institution', data.institution),
            detailCard('Graduation Year', data.graduation_year),
            detailCard('GPA', data.gpa),
            detailCard('Preferred Stream', data.preferred_stream),
            detailCard('Preferred Level', data.preferred_level),
            detailCard('Study Mode', data.study_mode),
            detailCard('Financial Status', data.financial_status),
            detailCard('Budget Range', data.budget_range),
            detailCard('Address', data.address),
            detailCard('Date of Birth', data.date_of_birth),
            detailCard('Notes', data.notes),
            detailCard('Last Updated', data.updated_at),
        ].join('');

        const backdrop = document.getElementById('studentDetailsBackdrop');
        backdrop.classList.add('is-open');
    }

    function closeStudentDetails() {
        document.getElementById('studentDetailsBackdrop').classList.remove('is-open');
    }

    document.getElementById('studentDetailsBackdrop').addEventListener('click', (e) => {
        if (e.target && e.target.id === 'studentDetailsBackdrop') {
            closeStudentDetails();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeStudentDetails();
        }
    });
</script>
<?php adminPageEnd(); ?>
