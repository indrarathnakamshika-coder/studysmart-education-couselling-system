<?php
declare(strict_types=1);

function flash_put(string $type, string $message): void
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][$type] = $message;
}

function flash_take(string $type): ?string
{
    if (!isset($_SESSION['flash'][$type]) || !is_string($_SESSION['flash'][$type])) {
        return null;
    }
    $msg = $_SESSION['flash'][$type];
    unset($_SESSION['flash'][$type]);
    return $msg;
}

/**
 * @param string|array<int,string> $body
 */
function render_auth_notice(string $variant, string $title, $body): void
{
    $allowed = ['success', 'error', 'info'];
    if (!in_array($variant, $allowed, true)) {
        $variant = 'info';
    }
    $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    echo '<div class="notice notice--' . htmlspecialchars($variant, ENT_QUOTES, 'UTF-8') . '" role="' . ($variant === 'error' ? 'alert' : 'status') . '">';
    echo '<div class="notice__icon" aria-hidden="true">';
    if ($variant === 'success') {
        echo '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>';
    } elseif ($variant === 'error') {
        echo '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>';
    } else {
        echo '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>';
    }
    echo '</div><div class="notice__body"><p class="notice__title">' . $t . '</p>';
    if (is_array($body)) {
        echo '<ul class="notice__list">';
        foreach ($body as $line) {
            echo '<li>' . htmlspecialchars((string) $line, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p class="notice__msg">' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    echo '</div><button type="button" class="notice__close" aria-label="Dismiss">&times;</button></div>';
}

/**
 * Same centred modal pattern as payment success (student/payments.php).
 *
 * @param 'student'|'admin' $workspace Subline under the welcome message
 */
function render_success_toast(string $workspace = 'student'): void
{
    $msg = flash_take('success');
    if ($msg === null || $msg === '') {
        return;
    }
    $safe = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
    $tagline = $workspace === 'admin'
        ? 'Manage courses, intakes, students, applications, payments, and reports.'
        : 'Welcome back to your StudySmart counselling workspace.';
    $tagEsc = htmlspecialchars($tagline, ENT_QUOTES, 'UTF-8');
    echo '<div id="login-success-modal" class="payment-modal-overlay is-open" role="dialog" aria-modal="true" aria-labelledby="login-success-modal-title" tabindex="-1">';
    echo '<div class="payment-modal-dialog">';
    echo '<div class="payment-modal-icon" aria-hidden="true">✓</div>';
    echo '<h2 id="login-success-modal-title" class="payment-modal-title">Successfully signed in</h2>';
    echo '<p class="payment-modal-text">' . $safe . '<br><br><span class="payment-modal-subline">' . $tagEsc . '</span></p>';
    echo '<div class="payment-modal-actions">';
    echo '<button type="button" class="payment-modal-btn-primary" id="login-success-modal-ok">OK</button>';
    echo '</div></div></div>';
    echo '<script>(function(){var m=document.getElementById("login-success-modal");var ok=document.getElementById("login-success-modal-ok");if(!m||!ok)return;document.body.style.overflow="hidden";function close(){document.body.style.overflow="";m.classList.remove("is-open");m.setAttribute("aria-hidden","true");m.remove();document.removeEventListener("keydown",onEsc);}function onEsc(e){if(e.key==="Escape"&&m.parentNode)close();}ok.addEventListener("click",close);m.addEventListener("click",function(e){if(e.target===m)close();});document.addEventListener("keydown",onEsc);})();</script>';
}
