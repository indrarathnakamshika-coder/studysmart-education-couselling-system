<?php
function studentPageStart(string $title, string $activePage, string $studentName): void
{
    $menuItems = [
        'dashboard' => ['label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'dashboard'],
        'profile' => ['label' => 'My Profile', 'href' => 'profile.php', 'icon' => 'profile'],
        'recommendations' => ['label' => 'Recommendations', 'href' => 'recommendations.php', 'icon' => 'recommendations'],
        'applications' => ['label' => 'My Applications', 'href' => 'applications.php', 'icon' => 'applications'],
        'payments' => ['label' => 'Payments', 'href' => 'payments.php', 'icon' => 'payments'],
    ];
    $iconMap = [
        'dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/></svg>',
        'profile' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-4.4 0-8 2.2-8 5v2h16v-2c0-2.8-3.6-5-8-5z"/></svg>',
        'recommendations' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 9.6 8.1 3 9l4.8 4.3L6.6 20 12 16.7 17.4 20l-1.2-6.7L21 9l-6.6-.9z"/></svg>',
        'applications' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h9l5 5v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm8 1.5V8h4.5zM8 12h8v1.8H8zm0 4h8v1.8H8z"/></svg>',
        'payments' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2H3zm0 5h18v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zm3 3v2h6v-2z"/></svg>',
    ];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> - StudySmart</title>
        <link rel="stylesheet" href="../styles.css">
        <link rel="stylesheet" href="student.css?v=<?php echo (int) @filemtime(__DIR__ . '/student.css'); ?>">
    </head>
    <body class="student-body">
        <?php render_success_toast('student'); ?>
        <div class="student-shell">
            <aside class="student-sidebar">
                <div>
                    <div class="student-brand">
                        <div class="brand-pill">SS</div>
                        <span>StudySmart</span>
                    </div>
                    <p class="student-user-name"><?php echo htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="student-user-role">Student Panel</p>

                    <nav class="student-nav">
                        <?php foreach ($menuItems as $key => $item): ?>
                            <a
                                href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"
                                class="student-nav-link <?php echo $activePage === $key ? 'is-active' : ''; ?>"
                            >
                                <span class="student-nav-icon"><?php echo $iconMap[$item['icon']] ?? ''; ?></span>
                                <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>

                <a href="../logout.php" class="student-logout-link">
                    <span class="logout-icon">⏻</span>
                    <span>Logout</span>
                </a>
            </aside>

            <main class="student-main">
                <header class="student-page-header">
                    <div>
                        <h1><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
                        <p>Welcome back to your StudySmart counselling workspace.</p>
                    </div>
                    <div class="student-header-tools">
                        <div class="student-header-badge">
                            <span>Professional Counselling Platform</span>
                        </div>
                        <details class="student-user-menu">
                            <summary class="student-user-trigger">
                                <span class="student-user-avatar"><?php echo strtoupper(substr($studentName, 0, 1)); ?></span>
                                <span class="student-user-trigger-name"><?php echo htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'); ?></span>
                            </summary>
                            <div class="student-user-dropdown">
                                <a href="profile.php">My Profile</a>
                                <a href="dashboard.php">Dashboard</a>
                                <a href="../logout.php">Logout</a>
                            </div>
                        </details>
                    </div>
                </header>
    <?php
}

function studentPageEnd(): void
{
    ?>
            </main>
        </div>
        <div class="student-chatbot" id="student-chatbot">
            <button
                type="button"
                class="student-chatbot__toggle"
                id="student-chatbot-toggle"
                aria-label="Open StudySmart assistant"
                aria-controls="student-chatbot-panel"
                aria-expanded="false"
            >
                <span class="student-chatbot__toggle-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" role="presentation" focusable="false">
                        <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8A2.5 2.5 0 0 1 17.5 16H10l-4.5 4v-4H6.5A2.5 2.5 0 0 1 4 13.5z"/>
                    </svg>
                </span>
                <span class="student-chatbot__toggle-text">Assistant</span>
            </button>
            <section class="student-chatbot__panel" id="student-chatbot-panel" aria-hidden="true" aria-label="StudySmart virtual assistant">
                <header class="student-chatbot__header">
                    <div>
                        <h2>StudySmart Assistant</h2>
                        <p>Virtual education guide</p>
                    </div>
                    <button type="button" class="student-chatbot__close" id="student-chatbot-close" aria-label="Close assistant">&times;</button>
                </header>
                <div class="student-chatbot__messages" id="student-chatbot-messages"></div>
                <form class="student-chatbot__composer" id="student-chatbot-form">
                    <label class="student-chatbot__sr-only" for="student-chatbot-input">Ask a question</label>
                    <textarea
                        id="student-chatbot-input"
                        class="student-chatbot__input"
                        rows="1"
                        maxlength="500"
                        placeholder="Ask about courses, eligibility, applications, or payments..."
                        required
                    ></textarea>
                    <button type="submit" class="student-chatbot__send">Send</button>
                </form>
            </section>
        </div>
        <script src="chatbot.js?v=<?php echo (int) @filemtime(__DIR__ . '/chatbot.js'); ?>"></script>
    </body>
    </html>
    <?php
}
?>
