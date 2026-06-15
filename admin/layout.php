<?php
function adminPageStart(string $title, string $activePage, string $adminName, array $header = []): void
{
    $defaultNote = 'Manage courses, intakes, students, applications, payments, and reports.';
    $headerNote = trim((string) ($header['note'] ?? ''));
    $headerNoteOut = $headerNote !== '' ? $headerNote : $defaultNote;
    $headerClass = trim((string) ($header['class'] ?? ''));
    $menuItems = [
        'dashboard' => ['label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'dashboard'],
        'courses' => ['label' => 'Manage Courses', 'href' => 'courses.php', 'icon' => 'courses'],
        'intakes' => ['label' => 'Manage Intakes', 'href' => 'intakes.php', 'icon' => 'intakes'],
        'students' => ['label' => 'Manage Students', 'href' => 'students.php', 'icon' => 'students'],
        'applications' => ['label' => 'Manage Applications', 'href' => 'applications.php', 'icon' => 'applications'],
        'payments' => ['label' => 'Manage Payments', 'href' => 'payments.php', 'icon' => 'payments'],
        'reports' => ['label' => 'Reports', 'href' => 'reports.php', 'icon' => 'reports'],
    ];
    $iconMap = [
        'dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/></svg>',
        'courses' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v2H4zm0 4h10v2H4zm0 4h16v2H4zm0 4h10v2H4z"/></svg>',
        'intakes' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2v2H5a2 2 0 0 0-2 2v2h18V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2zM21 10H3v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2z"/></svg>',
        'students' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4zM8 12a3 3 0 1 0-3-3 3 3 0 0 0 3 3zm8 2c-3.3 0-6 1.8-6 4v2h12v-2c0-2.2-2.7-4-6-4zM8 14c-2.8 0-5 1.5-5 3.3V20h5v-1.9c0-1.3.5-2.5 1.4-3.4A7.3 7.3 0 0 0 8 14z"/></svg>',
        'applications' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h9l5 5v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm8 1.5V8h4.5zM8 12h8v1.8H8zm0 4h8v1.8H8z"/></svg>',
        'payments' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2H3zm0 5h18v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zm3 3v2h6v-2z"/></svg>',
        'reports' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19h16v2H4zM6 10h3v7H6zm5-4h3v11h-3zm5 6h3v5h-3z"/></svg>',
    ];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> - StudySmart Admin</title>
        <link rel="stylesheet" href="../styles.css">
        <link rel="stylesheet" href="admin.css">
    </head>
    <body class="admin-body">
        <?php render_success_toast('admin'); ?>
        <div class="admin-shell">
            <aside class="admin-sidebar">
                <div>
                    <div class="admin-brand">
                        <div class="brand-pill">SS</div>
                        <span>StudySmart</span>
                    </div>
                    <p class="admin-user-name"><?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="admin-user-role">Admin Panel</p>
                    <nav class="admin-nav">
                        <?php foreach ($menuItems as $key => $item): ?>
                            <a
                                href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"
                                class="admin-nav-link <?php echo $activePage === $key ? 'is-active' : ''; ?>"
                            >
                                <span class="admin-nav-icon"><?php echo $iconMap[$item['icon']] ?? ''; ?></span>
                                <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
                <a href="../logout.php" class="admin-logout-link">
                    <span class="logout-icon">⏻</span>
                    <span>Logout</span>
                </a>
            </aside>

            <main class="admin-main">
                <header class="admin-page-header<?php echo $headerClass !== '' ? ' ' . htmlspecialchars($headerClass, ENT_QUOTES, 'UTF-8') : ''; ?>">
                    <div>
                        <h1><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
                        <p><?php echo htmlspecialchars($headerNoteOut, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="admin-header-tools">
                        <div class="admin-header-badge">
                            <span>Administration Console</span>
                        </div>
                        <details class="admin-user-menu">
                            <summary class="admin-user-trigger">
                                <span class="admin-user-avatar"><?php echo strtoupper(substr($adminName, 0, 1)); ?></span>
                                <span class="admin-user-trigger-name"><?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?></span>
                            </summary>
                            <div class="admin-user-dropdown">
                                <a href="dashboard.php">Dashboard</a>
                                <a href="courses.php">Manage Courses</a>
                                <a href="../logout.php">Logout</a>
                            </div>
                        </details>
                    </div>
                </header>
    <?php
}

function adminPageEnd(): void
{
    ?>
            </main>
        </div>
    </body>
    </html>
    <?php
}
?>
