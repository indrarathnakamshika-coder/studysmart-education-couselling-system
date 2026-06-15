<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['reply' => 'Please sign in as a student to use the assistant.']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);
$message = '';

if (is_array($payload) && isset($payload['message'])) {
    $message = trim((string) $payload['message']);
} elseif (isset($_POST['message'])) {
    $message = trim((string) $_POST['message']);
}

if ($message === '') {
    echo json_encode([
        'reply' => "You can ask me about course levels, eligibility after O/L or A/L, applications, payments, recommendations, or where to find each feature in StudySmart.",
    ]);
    exit;
}

$text = strtolower($message);

/**
 * @param list<string> $needles
 */
function chatbotHasAny(string $text, array $needles): bool
{
    foreach ($needles as $needle) {
        if ($needle !== '' && strpos($text, strtolower($needle)) !== false) {
            return true;
        }
    }
    return false;
}

if (chatbotHasAny($text, ['level 1', 'level 2', 'level 3', 'level 4', 'levels', 'progression', 'pathway'])) {
    $reply = "StudySmart uses a simple Level 1-4 pathway so recommendations stay realistic for your current education stage.\n\n"
        . "Level 1 focuses on certificate and skill-based starts, Level 2 is foundation, Level 3 is HND/diploma, and Level 4 is degree-level study. This helps you avoid courses that are too advanced too early.\n\n"
        . "Next step: open My Profile and confirm your education qualification so your level is set correctly, then check Recommendations.";
} elseif (chatbotHasAny($text, ['o/l', 'ol', 'ordinary level', 'a/l', 'al', 'advanced level', 'eligibility', 'eligible'])) {
    $reply = "Eligibility is mainly based on your education stage in My Profile.\n\n"
        . "If you are after O/L, a skill-based or foundation start is usually best. After A/L, you can often move toward foundation, HND, or degree routes depending on your background and preferences.\n\n"
        . "Next step: update your qualification in My Profile, then compare the recommended path before applying.";
} elseif (chatbotHasAny($text, ['skill', 'certificate', 'foundation', 'hnd', 'diploma', 'degree', 'course option', 'course'])) {
    $reply = "Here is a quick way to compare course options:\n\n"
        . "Skill/Certificate = faster entry and practical basics. Foundation = bridge to higher study. HND/Diploma = strong career-focused progression. Degree = full academic path with wider long-term options.\n\n"
        . "Next step: use Recommendations to see the best-fit set for your level, then open Applications to submit the one that matches your goal and budget.";
} elseif (chatbotHasAny($text, ['recommend', 'recommendation', 'match', 'percentage', '%'])) {
    $reply = "Recommendations are rule-based and profile-driven.\n\n"
        . "The system starts with your level band, then adjusts match score using your interests, study mode preference, and budget fit. That is why two courses in the same level can still show different percentages.\n\n"
        . "Next step: complete missing profile fields, refresh Recommendations, and shortlist 2-3 high-match courses before applying.";
} elseif (chatbotHasAny($text, ['apply', 'application', 'submit', 'duplicate'])) {
    $reply = "To apply, go to Applications, fill your details, choose a course, and submit.\n\n"
        . "StudySmart blocks duplicate active applications for the same course, so you do not accidentally submit the same program twice. If blocked, you will see a message box explaining it.\n\n"
        . "Next step: review Application history first, then apply for a different course or wait for an update from the office.";
} elseif (chatbotHasAny($text, ['payment', 'pay', 'card', 'receipt', 'installment', 'full payment'])) {
    $reply = "Payments are managed after your application is approved.\n\n"
        . "Open Payments or use Make Payment from approved items, then complete card details and continue checkout. After success, your status updates and you can view/print/download your receipt.\n\n"
        . "Next step: check your Application history status first, then proceed only for approved courses.";
} elseif (chatbotHasAny($text, ['dashboard', 'profile', 'navigation', 'where', 'menu', 'find'])) {
    $reply = "Quick navigation guide:\n\n"
        . "Dashboard gives your overall progress, My Profile stores your academic and preference details, Recommendations suggests suitable courses, Applications manages submissions, and Payments handles fee checkout and receipts.\n\n"
        . "Next step: if you are new, start with My Profile -> Recommendations -> Applications -> Payments.";
} elseif (chatbotHasAny($text, ['hello', 'hi', 'hey', 'help'])) {
    $reply = "Hi! I can guide you through courses, eligibility, recommendations, applications, payments, and navigation in StudySmart.\n\n"
        . "You can ask things like: 'Which level am I in?', 'What can I study after O/L?', 'How do I apply?', or 'How does payment work?'.\n\n"
        . "Next step: tell me your goal (career focus or study level), and I will suggest the best path.";
} else {
    $reply = "Good question. I can give the clearest help on course levels, O/L-A/L eligibility, recommendations, applications, payments, and where to find each feature in the menu.\n\n"
        . "Try asking in a direct way, for example: 'Explain Level 3', 'How to apply for a course?', or 'How do recommendation percentages work?'.\n\n"
        . "Next step: start from My Profile if you want better recommendations.";
}

echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);
