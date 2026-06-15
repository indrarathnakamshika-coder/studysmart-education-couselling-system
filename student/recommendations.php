<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

$studyMode = trim((string) ($profile['study_mode'] ?? ''));
$budgetRange = trim((string) ($profile['budget_range'] ?? ''));
$financialStatus = trim((string) ($profile['financial_status'] ?? ''));
$progressionLevel = (int) ($profile['progression_level'] ?? 0);
$interestsBlob = strtolower(trim((string) ($profile['interests'] ?? '')));

/**
 * @return list<string> needles to try in course text (token + synonyms)
 */
function studentInterestExpandNeedles(string $token): array
{
    $t = strtolower(trim($token));
    $shortAllowed = ['it', 'ai', 'ui', 'ux', 'hr'];
    if (strlen($t) < 3 && !in_array($t, $shortAllowed, true)) {
        return [];
    }
    $synonyms = [
        'business' => ['business', 'finance', 'financial', 'bba', 'commerce', 'accounting', 'economics', 'marketing', 'management', 'entrepreneur', 'mba', 'trade'],
        'commerce' => ['commerce', 'business', 'accounting', 'trade'],
        'finance' => ['finance', 'financial', 'accounting', 'banking', 'investment'],
        'accounting' => ['accounting', 'finance', 'audit', 'tax'],
        'law' => ['law', 'legal', 'attorney', 'advocate', 'jurisprudence', 'llb'],
        'technology' => ['technology', 'technical', 'software', 'digital', 'computing', 'it', 'informatics'],
        'computer' => ['computer', 'software', 'programming', 'developer', 'computing', 'cyber', 'data'],
        'it' => ['it', 'information technology', 'software', 'computer', 'network', 'cyber', 'cloud'],
        'cyber' => ['cyber', 'security', 'ethical hacking', 'network security', 'infosec'],
        'data' => ['data', 'analytics', 'statistics', 'machine learning', 'science'],
        'design' => ['design', 'graphic', 'creative', 'media', 'visual', 'branding'],
        'media' => ['media', 'communication', 'journalism', 'content', 'broadcast', 'film'],
        'health' => ['health', 'medical', 'clinical', 'nursing', 'nurse', 'biomedical', 'pharma', 'medicine'],
        'nursing' => ['nursing', 'nurse', 'clinical', 'healthcare', 'medical'],
        'pharmacy' => ['pharmacy', 'pharma', 'pharmaceutical', 'medicine', 'clinical'],
        'engineering' => ['engineering', 'engineer', 'mechanical', 'electrical', 'civil', 'biomedical'],
        'civil' => ['civil', 'construction', 'structural', 'infrastructure', 'engineering'],
        'mechanical' => ['mechanical', 'manufacturing', 'machine', 'engineering'],
        'electrical' => ['electrical', 'electronics', 'power', 'engineering'],
        'science' => ['science', 'physics', 'chemistry', 'biology', 'mathematics', 'maths', 'math'],
        'education' => ['education', 'teaching', 'teacher', 'pedagogy', 'academic'],
        'psychology' => ['psychology', 'mental health', 'counselling', 'behavior', 'behaviour'],
        'hospitality' => ['hospitality', 'hotel', 'tourism', 'travel', 'culinary', 'event'],
        'management' => ['management', 'operations', 'leadership', 'administration', 'business'],
        'marketing' => ['marketing', 'digital marketing', 'branding', 'advertising', 'sales'],
    ];
    $out = [$t];
    if (isset($synonyms[$t])) {
        $out = array_merge($out, $synonyms[$t]);
    }

    return array_values(array_unique($out));
}

function studentInterestContainsNeedle(string $haystack, string $needle): bool
{
    $needle = strtolower(trim($needle));
    if ($needle === '') {
        return false;
    }
    $escaped = preg_quote($needle, '/');
    $escaped = str_replace('\ ', '\s+', $escaped);
    return preg_match('/\b' . $escaped . '\b/u', $haystack) === 1;
}

/**
 * Interest-based uplift on top of the level baseline (e.g. business interest → stronger match on BBA/finance courses).
 */
function studentInterestMatchPoints(string $interestsLower, array $courseRow): int
{
    if ($interestsLower === '') {
        return 0;
    }
    $haystack = strtolower(
        ($courseRow['course_name'] ?? '') . ' ' .
        ($courseRow['field'] ?? '') . ' ' .
        ($courseRow['description'] ?? '') . ' ' .
        ($courseRow['category'] ?? '') . ' ' .
        ($courseRow['stream_required'] ?? '') . ' ' .
        ($courseRow['level'] ?? '')
    );
    $tokens = preg_split('/[\s,;&]+/', $interestsLower, -1, PREG_SPLIT_NO_EMPTY);
    $stopwords = [
        'and', 'the', 'for', 'with', 'after', 'before', 'from', 'into', 'that', 'this', 'want', 'need',
        'course', 'courses', 'study', 'program', 'programme', 'option', 'options', 'level',
    ];
    $themes = 0;
    foreach ($tokens as $tok) {
        if (in_array($tok, $stopwords, true)) {
            continue;
        }
        $needles = studentInterestExpandNeedles($tok);
        if ($needles === []) {
            continue;
        }
        foreach ($needles as $needle) {
            if (strlen($needle) >= 3 && studentInterestContainsNeedle($haystack, $needle)) {
                $themes++;
                break;
            }
        }
    }

    return min(14, $themes * 7);
}

$minBudget = 0;
$maxBudget = 0;
if ($budgetRange !== '') {
    preg_match_all('/\d[\d,]*/', $budgetRange, $budgetNums);
    if (!empty($budgetNums[0])) {
        $first = (int) str_replace(',', '', (string) $budgetNums[0][0]);
        $second = isset($budgetNums[0][1]) ? (int) str_replace(',', '', (string) $budgetNums[0][1]) : 0;

        if ($second > 0) {
            $minBudget = min($first, $second);
            $maxBudget = max($first, $second);
        } else {
            // If student enters a single amount (e.g. "300000"), treat it as max budget.
            $minBudget = 0;
            $maxBudget = $first;
        }
    }
}

$courses = [];
$courseRes = mysqli_query(
    $conn,
    "SELECT c.*, COALESCE(ix.intakes_list, '') AS intakes_list
    FROM course_catalog c
    LEFT JOIN (
        SELECT course_id, GROUP_CONCAT(DISTINCT intake_name ORDER BY start_date SEPARATOR ', ') AS intakes_list
        FROM course_intakes
        WHERE status = 'Open'
        GROUP BY course_id
    ) ix ON ix.course_id = c.course_id
    ORDER BY c.field, c.level, c.course_name"
);
if ($courseRes) {
    while ($row = mysqli_fetch_assoc($courseRes)) {
        if (!empty($row['status']) && $row['status'] === 'Inactive') {
            continue;
        }

        if (!$profileComplete || $progressionLevel < 1 || $progressionLevel > 4) {
            continue;
        }
        if (!courseMatchesStudentProgression($progressionLevel, $row)) {
            continue;
        }

        $courseFee = (float) ($row['average_fee'] ?? 0);
        if ($courseFee <= 0) {
            $courseFee = (float) ($row['fees'] ?? 0);
        }

        $score = 80;
        $reasons = [];

        $tierHints = [
            1 => 'certificate / skill-based courses only',
            2 => 'foundation programs only',
            3 => 'HND-level programs only',
            4 => 'degree-level programs (undergraduate & postgraduate)',
        ];
        $reasons[] = 'Matches Level ' . $progressionLevel . ': ' . ($tierHints[$progressionLevel] ?? 'your pathway') . '.';

        $interestPts = studentInterestMatchPoints($interestsBlob, $row);
        $hasInterestMatch = $interestPts > 0;
        $budgetMatch = false;
        $budgetNearMatch = false;
        $budgetFarAbove = false;

        if ($maxBudget > 0) {
            $budgetMatch = $courseFee <= $maxBudget;
            $budgetNearMatch = !$budgetMatch && $courseFee <= (int) ceil($maxBudget * 1.12);
            $budgetFarAbove = $courseFee > (int) ceil($maxBudget * 1.35);
        }

        // Tiered rule-set requested by product:
        // 1) interest + budget => >95, 2) interest match => >90, 3) others => >80.
        if ($hasInterestMatch && $budgetMatch) {
            $score = 96;
            $reasons[] = 'Strong match: your interests align and this fee range is budget-friendly.';
        } elseif ($hasInterestMatch && $budgetNearMatch) {
            $score = 94;
            $reasons[] = 'Good match: your interests align and the fee is close to your budget range.';
        } elseif ($hasInterestMatch) {
            $score = 91;
            $reasons[] = 'Strong interest alignment: this course is highly relevant to your stated interests.';
        } else {
            $score = 81;
            $reasons[] = 'Level-fit option: this course suits your pathway, though interest overlap is lower.';
        }

        $contactDaysRaw = trim((string) ($row['study_mode'] ?? ''));
        if ($contactDaysRaw === '') {
            $contactDaysRaw = trim((string) ($row['mode'] ?? ''));
        }
        if ($studyMode !== '' && strcasecmp($studyMode, $contactDaysRaw) === 0) {
            $score += 1;
            $reasons[] = 'Study mode matches your preference.';
        }

        if ($maxBudget > 0) {
            if ($budgetMatch) {
                $score += 1;
                $reasons[] = 'Fee is within your stated budget range.';
            } elseif ($budgetNearMatch) {
                $score += 0;
                $reasons[] = 'Fee is slightly above your budget ceiling but still close.';
            } elseif ($budgetFarAbove) {
                $score -= 2;
                $reasons[] = 'Fee is well above your budget range.';
            } else {
                $score -= 1;
                $reasons[] = 'Budget fit is moderate relative to your stated range.';
            }
        }

        if ($financialStatus === 'Installment' && $courseFee >= 350000) {
            $score += 1;
            $reasons[] = 'Installment preference fits this higher-fee course.';
        }
        if ($financialStatus === 'Full Payment' && $budgetMatch) {
            $score += 1;
            $reasons[] = 'Full-payment preference aligns with this budget-fit option.';
        }

        if ($profileCompletion < 50) {
            $score -= 2;
            $reasons[] = 'Add more profile detail for finer match confidence.';
        }

        // Small stable spread so similar courses do not look identical.
        $courseId = (int) ($row['course_id'] ?? 0);
        $varietyKey = $courseId . "\0" . $userId . "\0" . ($row['course_name'] ?? '') . "\0" . ($row['field'] ?? '');
        $variety = ((int) (abs(crc32($varietyKey)) % 4));
        if ($hasInterestMatch && $budgetMatch) {
            // Keep this tier above 95 as requested.
            $variety = max(0, $variety);
        } elseif ($hasInterestMatch) {
            // Interest-only tier stays above 90.
            $variety = max(0, $variety - 1);
        } else {
            // Other level-fit courses stay above 80.
            $variety = max(0, $variety - 1);
        }
        $score += $variety;

        if ($hasInterestMatch && $budgetMatch) {
            $score = max(96, $score);
        } elseif ($hasInterestMatch) {
            $score = max(91, $score);
        } else {
            $score = max(81, $score);
        }

        $row['match_score'] = max(80, min(99, (int) round($score)));
        $row['reasons'] = $reasons;
        $row['display_fee'] = $courseFee;
        $row['contact_days'] = $contactDaysRaw !== '' ? $contactDaysRaw : '—';
        $row['intake_label'] = trim((string) ($row['intakes_list'] ?? '')) !== ''
            ? (string) $row['intakes_list']
            : trim((string) ($row['intake_month'] ?? ''));
        $cat = trim((string) ($row['category'] ?? ''));
        $stream = trim((string) ($row['stream_required'] ?? ''));
        $row['category_label'] = $cat !== ''
            ? $row['category']
            : ($stream !== '' ? $stream : ucfirst(strtolower((string) ($row['level'] ?? ''))));
        $courses[] = $row;
    }
}

usort($courses, static function (array $a, array $b): int {
    $scoreA = (int) ($a['match_score'] ?? 0);
    $scoreB = (int) ($b['match_score'] ?? 0);
    $bandRankA = ($scoreA > 95) ? 3 : (($scoreA >= 90) ? 2 : 1);
    $bandRankB = ($scoreB > 95) ? 3 : (($scoreB >= 90) ? 2 : 1);
    $cmp = $bandRankB <=> $bandRankA;
    if ($cmp !== 0) {
        return $cmp;
    }
    $cmp = ($b['match_score'] ?? 0) <=> ($a['match_score'] ?? 0);
    if ($cmp !== 0) {
        return $cmp;
    }
    return ($a['course_id'] ?? 0) <=> ($b['course_id'] ?? 0);
});

$courseTotal = count($courses);

studentPageStart('Recommendations', 'recommendations', $studentName);
?>

<section class="student-section">
    <h2>Recommended Courses</h2>
    <p class="help-text">
        Based on your profile, we've carefully selected courses that best match your educational background, interests, and preferences. These recommendations are tailored to help you choose the most suitable path for your future. Explore the options below and take the next step toward achieving your academic and career goals.
    </p>
    <p class="help-text">
        Match guide: <strong>Most suitable</strong> is above 95% (interest + budget fit), <strong>Interest match</strong> is 90-95%, and <strong>Level-fit options</strong> stay above 80%.
    </p>
    <?php if (!$profileComplete): ?>
        <p class="help-text">Complete your profile with your education qualification, interests, and financial status to unlock level-based recommendations.</p>
        <p><a class="btn-primary btn-action" href="profile.php?setup=1"><span class="btn-icon">👉</span><span>Complete profile</span></a></p>
    <?php elseif ($profileCompletion < 60): ?>
        <p class="help-text">Tip: Your profile is <?php echo (int) $profileCompletion; ?>% complete. Add more details in My Profile to improve recommendation quality.</p>
    <?php endif; ?>
</section>

<?php if (empty($courses)): ?>
    <section class="student-section">
        <p class="help-text">
            No courses match your level band yet. Ask an administrator to tag catalog “qualification / level” text so it includes keywords we recognise
            (e.g. <strong>Foundation</strong> for Level 2, <strong>HND</strong> or <strong>Diploma</strong> for Level 3, <strong>Certificate</strong> for Level 1, <strong>Undergraduate</strong> / degree words for Level 4).
            You can also widen your budget range in My Profile.
        </p>
    </section>
<?php else: ?>
    <div class="student-cards-grid recommendation-grid recommendation-grid--full">
        <?php foreach ($courses as $course): ?>
            <?php
            $courseFee = (float) ($course['display_fee'] ?? 0);
            $discountedFullFee = $courseFee * 0.90;
            $durationMonths = (int) ($course['duration_months'] ?? 0);
            $monthlyFee = $durationMonths > 0 ? ($courseFee / $durationMonths) : $courseFee;
            $categoryLabel = htmlspecialchars((string) ($course['category_label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $contactDays = trim((string) ($course['contact_days'] ?? ''));
            $intakeLabel = trim((string) ($course['intake_label'] ?? ''));
            $matchScore = (int) ($course['match_score'] ?? 80);
            $matchBand = 'Level-fit option';
            $matchBandClass = 'rec-match-band--moderate';
            if ($matchScore > 95) {
                $matchBand = 'Most suitable';
                $matchBandClass = 'rec-match-band--top';
            } elseif ($matchScore >= 90) {
                $matchBand = 'Interest match';
                $matchBandClass = 'rec-match-band--good';
            }
            ?>
            <article class="student-card recommendation-card-actions">
                <h3><?php echo htmlspecialchars($course['course_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <p class="help-text rec-meta-line">
                    <span class="rec-icon">●</span>Category: <?php echo $categoryLabel !== '' ? $categoryLabel : '—'; ?>
                    <span class="rec-sep">|</span>
                    <span class="rec-icon">●</span><?php echo htmlspecialchars((string) ($course['field'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    <span class="rec-sep">|</span>
                    <span class="rec-icon">●</span><?php echo htmlspecialchars((string) ($course['mode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <p class="student-card-value rec-match-pct"><?php echo $matchScore; ?>% match</p>
                <p class="rec-match-band <?php echo $matchBandClass; ?>"><?php echo htmlspecialchars($matchBand, ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="rec-fee-box">
                    <p><strong>Course fee:</strong> LKR <?php echo htmlspecialchars(number_format($courseFee, 2), ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>Full payment (10% off):</strong> LKR <?php echo htmlspecialchars(number_format($discountedFullFee, 2), ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>Monthly estimate:</strong> LKR <?php echo htmlspecialchars(number_format($monthlyFee, 2), ENT_QUOTES, 'UTF-8'); ?>/month</p>
                    <p><strong>Duration:</strong> <?php echo (int) $durationMonths; ?> months</p>
                    <p><strong>Contact days:</strong> <?php echo htmlspecialchars($contactDays !== '' ? $contactDays : '—', ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>Intake:</strong> <?php echo htmlspecialchars($intakeLabel !== '' ? $intakeLabel : 'TBA', ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <ul class="help-text">
                    <?php foreach ($course['reasons'] as $reason): ?>
                        <li><?php echo htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
                <div class="rec-card-cta">
                    <a class="btn-primary btn-action" href="applications.php?apply=1&amp;course_id=<?php echo (int) $course['course_id']; ?>">
                        <span class="btn-icon">👉</span>
                        <span>Apply Now</span>
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php studentPageEnd(); ?>
