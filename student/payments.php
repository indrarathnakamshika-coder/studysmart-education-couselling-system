<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

/**
 * Course program fee from catalog for this row only (no global default).
 * Admin “Fees” is preferred, then legacy average_fee.
 *
 * @return array{0: float, 1: string} amount and which field was used ('fees'|'average_fee'|'').
 */
function paymentsCourseProgramFeeDetail(array $courseRow): array
{
    $fees = (float) ($courseRow['fees'] ?? 0);
    if ($fees > 0) {
        return [$fees, 'fees'];
    }
    $avg = (float) ($courseRow['average_fee'] ?? 0);
    if ($avg > 0) {
        return [$avg, 'average_fee'];
    }

    return [0.0, ''];
}

function paymentsCourseProgramFee(array $courseRow): float
{
    return paymentsCourseProgramFeeDetail($courseRow)[0];
}

function paymentsApplicationPaidSum(mysqli $connection, int $userId, int $applicationId): float
{
    $sql = 'SELECT COALESCE(SUM(amount_paid), 0) AS s FROM student_payment_history WHERE user_id = ? AND application_id = ?';
    $stmt = mysqli_prepare($connection, $sql);
    if (!$stmt) {
        return 0.0;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $userId, $applicationId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return (float) ($row['s'] ?? 0);
}

/** Catalog installment, or one-quarter of total liability when unset. */
function paymentsApplicationScheduleInstallment(float $catalogInstallment, float $totalLiability): float
{
    if ($catalogInstallment > 0) {
        return $catalogInstallment;
    }
    if ($totalLiability > 0) {
        return round($totalLiability / 4, 2);
    }

    return 0.0;
}

$paymentPlan = $paymentPlan ?? 'Full';
$amountPaidInput = 0.00;
$formError = '';
$paymentAction = 'make_payment';
$applicationCheckoutId = (int) ($_GET['application_id'] ?? ($_POST['application_id'] ?? 0));
$applicationCheckout = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_card_payment'])) {
    $appId = (int) ($_POST['application_id'] ?? 0);
    $cardholder = trim($_POST['cardholder_name'] ?? '');
    $cardNumber = preg_replace('/\s+/', '', trim($_POST['card_number'] ?? ''));
    $cardExpiry = trim($_POST['card_expiry'] ?? '');
    $cardCvv = trim($_POST['card_cvv'] ?? '');

    if ($appId <= 0) {
        $formError = 'Invalid application.';
    } elseif ($cardholder === '' || $cardNumber === '' || $cardExpiry === '' || $cardCvv === '') {
        $formError = 'Please complete all card fields.';
    } elseif (strlen($cardNumber) < 12 || strlen($cardNumber) > 19 || !ctype_digit($cardNumber)) {
        $formError = 'Enter a valid card number.';
    } elseif (!preg_match('/^\d{2}\/\d{2}$/', $cardExpiry)) {
        $formError = 'Expiry must be MM/YY.';
    } elseif (strlen($cardCvv) < 3 || strlen($cardCvv) > 4 || !ctype_digit($cardCvv)) {
        $formError = 'Enter a valid CVV.';
    }

    if ($formError === '') {
        $loadApp = mysqli_prepare(
            $conn,
            'SELECT a.application_id, a.status, a.payment_status, c.course_name, c.average_fee, c.fees, c.registration_fee, c.installment_amount
            FROM student_applications a
            INNER JOIN course_catalog c ON c.course_id = a.course_id
            WHERE a.application_id = ? AND a.user_id = ? LIMIT 1'
        );
        $appRow = null;
        if ($loadApp) {
            mysqli_stmt_bind_param($loadApp, 'ii', $appId, $userId);
            mysqli_stmt_execute($loadApp);
            $appRes = mysqli_stmt_get_result($loadApp);
            $appRow = $appRes ? mysqli_fetch_assoc($appRes) : null;
            mysqli_stmt_close($loadApp);
        }

        if ($appRow === null) {
            $formError = 'Application not found.';
        } elseif (strcasecmp((string) $appRow['status'], 'Approved') !== 0) {
            $formError = 'Payment is only available for approved applications.';
        } elseif (strcasecmp((string) $appRow['payment_status'], 'Paid') === 0) {
            $formError = 'This application is already paid.';
        } else {
            [$programFee, $feeSrc] = paymentsCourseProgramFeeDetail($appRow);
            $catalogReg = (float) ($appRow['registration_fee'] ?? 0);
            if ($catalogReg < 0) {
                $catalogReg = 0;
            }
            $paidSoFar = paymentsApplicationPaidSum($conn, $userId, $appId);
            $liability = $programFee + $catalogReg;
            $outstanding = max(0, $liability - $paidSoFar);
            $planChoice = trim((string) ($_POST['application_course_payment_plan'] ?? ''));
            if ($planChoice === '' || !in_array($planChoice, ['Full', 'Installment'], true)) {
                $formError = 'Please select full payment or installment before paying.';
            } else {
                $scheduleInst = paymentsApplicationScheduleInstallment(
                    max(0, (float) ($appRow['installment_amount'] ?? 0)),
                    $liability
                );
                $installmentCharge = $outstanding > 0 ? min($scheduleInst, $outstanding) : 0;
                $canInstallmentPlan = $outstanding > 0.009
                    && $installmentCharge > 0.009
                    && ($outstanding - $installmentCharge) > 0.009;

                if ($feeSrc === '' && $catalogReg <= 0) {
                    $formError = 'Course fees are not set for this program in the catalog. Please contact the office.';
                } elseif ($outstanding <= 0) {
                    $formError = 'No balance is due for this application.';
                } elseif ($planChoice === 'Installment' && !$canInstallmentPlan) {
                    $formError = 'Installment plan is not available for this balance (use full payment).';
                } else {
                    $amount = $planChoice === 'Installment' ? $installmentCharge : $outstanding;
                }
            }
        }

        if ($formError === '' && isset($amount) && $amount > 0) {
            $methodLabel = 'Card · ' . substr($cardNumber, 0, 4) . ' **** **** ' . substr($cardNumber, -4);
            $planChoice = trim((string) ($_POST['application_course_payment_plan'] ?? ''));
            if (!in_array($planChoice, ['Full', 'Installment'], true)) {
                $planChoice = 'Full';
            }
            [$programFeePost,] = paymentsCourseProgramFeeDetail($appRow);
            $catalogRegPost = max(0, (float) ($appRow['registration_fee'] ?? 0));
            $liabilityPost = $programFeePost + $catalogRegPost;
            $paidAfter = paymentsApplicationPaidSum($conn, $userId, $appId) + $amount;
            $remainingAfter = max(0, $liabilityPost - $paidAfter);
            $newAppPayStatus = $remainingAfter <= 0.009 ? 'Paid' : 'Pending';
            $statusNote = ($planChoice === 'Installment' ? 'Course fee (installment) — ' : 'Course fee (full) — ')
                . ($appRow['course_name'] ?? 'Course');
            if ($newAppPayStatus === 'Pending') {
                $statusNote .= ' — Remaining: LKR ' . number_format($remainingAfter, 2);
            }
            $planLabel = $planChoice === 'Installment' ? 'Course fee · Installment' : 'Course fee · Full';

            $upd = mysqli_prepare($conn, 'UPDATE student_applications SET payment_status = ? WHERE application_id = ? AND user_id = ?');
            if ($upd) {
                mysqli_stmt_bind_param($upd, 'sii', $newAppPayStatus, $appId, $userId);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
            }

            $hist = mysqli_prepare(
                $conn,
                'INSERT INTO student_payment_history (user_id, payment_plan, payment_method, amount_paid, status_note, application_id)
                VALUES (?, ?, ?, ?, ?, ?)'
            );
            $lastHistoryId = 0;
            if ($hist) {
                mysqli_stmt_bind_param($hist, 'issdsi', $userId, $planLabel, $methodLabel, $amount, $statusNote, $appId);
                mysqli_stmt_execute($hist);
                $lastHistoryId = (int) mysqli_insert_id($conn);
                mysqli_stmt_close($hist);
            }

            // Mirror course/application payments into student_payments so admin payments, dashboard, and reports stay in sync.
            $methodSync = substr($methodLabel, 0, 30);
            $syncStmt = mysqli_prepare(
                $conn,
                "INSERT INTO student_payments (user_id, registration_fee, payment_plan, payment_method, payment_status, application_id)
                VALUES (?, ?, 'Full', ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    registration_fee = VALUES(registration_fee),
                    payment_method = VALUES(payment_method),
                    payment_status = VALUES(payment_status),
                    application_id = VALUES(application_id)"
            );
            if ($syncStmt) {
                mysqli_stmt_bind_param($syncStmt, 'idssi', $userId, $liabilityPost, $methodSync, $newAppPayStatus, $appId);
                mysqli_stmt_execute($syncStmt);
                mysqli_stmt_close($syncStmt);
            }

            $receiptQs = $lastHistoryId > 0 ? '&receipt_history_id=' . $lastHistoryId : '';
            header('Location: payments.php?application_id=' . $appId . '&paid=1&app_payment=1' . $receiptQs);
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['general_card_payment'])) {
    $cardholder = trim($_POST['cardholder_name'] ?? '');
    $cardNumber = preg_replace('/\s+/', '', trim($_POST['card_number'] ?? ''));
    $cardExpiry = trim($_POST['card_expiry'] ?? '');
    $cardCvv = trim($_POST['card_cvv'] ?? '');

    if ($cardholder === '' || $cardNumber === '' || $cardExpiry === '' || $cardCvv === '') {
        $formError = 'Please complete all card fields.';
    } elseif (strlen($cardNumber) < 12 || strlen($cardNumber) > 19 || !ctype_digit($cardNumber)) {
        $formError = 'Enter a valid card number.';
    } elseif (!preg_match('/^\d{2}\/\d{2}$/', $cardExpiry)) {
        $formError = 'Expiry must be MM/YY.';
    } elseif (strlen($cardCvv) < 3 || strlen($cardCvv) > 4 || !ctype_digit($cardCvv)) {
        $formError = 'Enter a valid CVV.';
    }

    $fee = (float) ($_POST['registration_fee'] ?? 2500);
    $paymentPlanPost = trim($_POST['payment_plan'] ?? 'Full');
    $amountPaidPost = (float) ($_POST['amount_paid'] ?? 0);
    if ($fee <= 0) {
        $fee = 2500;
    }
    if (!in_array($paymentPlanPost, ['Full', 'Monthly'], true)) {
        $paymentPlanPost = 'Full';
    }

    $existingTotalPaid = 0.0;
    $totalPaidQuery = mysqli_prepare($conn, "SELECT COALESCE(SUM(amount_paid), 0) AS total_paid FROM student_payment_history WHERE user_id = ?");
    if ($totalPaidQuery) {
        mysqli_stmt_bind_param($totalPaidQuery, 'i', $userId);
        mysqli_stmt_execute($totalPaidQuery);
        $totalPaidRes = mysqli_stmt_get_result($totalPaidQuery);
        $totalPaidRow = $totalPaidRes ? mysqli_fetch_assoc($totalPaidRes) : null;
        $existingTotalPaid = (float) ($totalPaidRow['total_paid'] ?? 0);
        mysqli_stmt_close($totalPaidQuery);
    }

    $currentOutstanding = max(0, $fee - $existingTotalPaid);
    $recordAmount = 0.0;
    $selectedStatus = 'Pending';
    $selectedMethod = 'Card *' . substr($cardNumber, -4);

    if ($formError === '') {
        if ($paymentPlanPost === 'Full') {
            if ($currentOutstanding <= 0) {
                $formError = 'Your full payment is already completed. No outstanding due.';
            } else {
                $recordAmount = $currentOutstanding;
                $selectedStatus = 'Paid';
            }
        } else {
            if ($amountPaidPost <= 0) {
                $formError = 'Enter a valid installment amount greater than 0 for monthly payment.';
            } elseif ($currentOutstanding <= 0) {
                $formError = 'Your monthly installments are already completed. No outstanding due.';
            } elseif ($amountPaidPost > $currentOutstanding) {
                $formError = 'Installment amount cannot be greater than the outstanding due.';
            } else {
                $recordAmount = $amountPaidPost;
                $runningTotal = $existingTotalPaid + $recordAmount;
                $selectedStatus = $runningTotal >= $fee ? 'Paid' : 'Pending';
            }
        }
    }

    if ($formError === '') {
        $paymentSql = "INSERT INTO student_payments (user_id, registration_fee, payment_plan, payment_method, payment_status)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                registration_fee = VALUES(registration_fee),
                payment_plan = VALUES(payment_plan),
                payment_method = VALUES(payment_method),
                payment_status = VALUES(payment_status)";
        $paymentStmt = mysqli_prepare($conn, $paymentSql);
        if ($paymentStmt) {
            mysqli_stmt_bind_param($paymentStmt, 'idsss', $userId, $fee, $paymentPlanPost, $selectedMethod, $selectedStatus);
            mysqli_stmt_execute($paymentStmt);
            mysqli_stmt_close($paymentStmt);
        }

        $lastGeneralHistoryId = 0;
        if ($recordAmount > 0) {
            $remainingAfterPayment = max(0, $fee - ($existingTotalPaid + $recordAmount));
            $statusNote = $paymentPlanPost === 'Full'
                ? 'Full payment completed'
                : 'Remaining balance: LKR ' . number_format($remainingAfterPayment, 2);
            $historyStmt = mysqli_prepare($conn, "INSERT INTO student_payment_history (user_id, payment_plan, payment_method, amount_paid, status_note) VALUES (?, ?, ?, ?, ?)");
            if ($historyStmt) {
                mysqli_stmt_bind_param($historyStmt, 'issds', $userId, $paymentPlanPost, $selectedMethod, $recordAmount, $statusNote);
                mysqli_stmt_execute($historyStmt);
                $lastGeneralHistoryId = (int) mysqli_insert_id($conn);
                mysqli_stmt_close($historyStmt);
            }
        }

        $receiptGen = $lastGeneralHistoryId > 0 ? '&receipt_history_id=' . $lastGeneralHistoryId : '';
        header('Location: payments.php?saved=1&paid=1&action=make_payment' . $receiptGen);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['application_card_payment']) && !isset($_POST['general_card_payment'])) {
    $paymentAction = trim($_POST['payment_action'] ?? 'make_payment');
    $paymentPlan = trim($_POST['payment_plan'] ?? 'Full');
    $selectedMethod = trim($_POST['payment_method'] ?? 'Not selected');
    $fee = (float) ($_POST['registration_fee'] ?? 2500);
    $amountPaidInput = (float) ($_POST['amount_paid'] ?? 0);
    if ($fee <= 0) {
        $fee = 2500;
    }
    if (!in_array($paymentPlan, ['Full', 'Monthly'], true)) {
        $paymentPlan = 'Full';
    }

    $existingTotalPaid = 0.0;
    $totalPaidQuery = mysqli_prepare($conn, "SELECT COALESCE(SUM(amount_paid), 0) AS total_paid FROM student_payment_history WHERE user_id = ?");
    if ($totalPaidQuery) {
        mysqli_stmt_bind_param($totalPaidQuery, 'i', $userId);
        mysqli_stmt_execute($totalPaidQuery);
        $totalPaidRes = mysqli_stmt_get_result($totalPaidQuery);
        $totalPaidRow = $totalPaidRes ? mysqli_fetch_assoc($totalPaidRes) : null;
        $existingTotalPaid = (float) ($totalPaidRow['total_paid'] ?? 0);
        mysqli_stmt_close($totalPaidQuery);
    }

    $currentOutstanding = max(0, $fee - $existingTotalPaid);
    $recordAmount = 0.0;
    $selectedStatus = 'Pending';

    if ($selectedMethod === 'Not selected') {
        $formError = 'Please choose a payment method before submitting payment.';
    }

    if ($formError === '' && $paymentPlan === 'Full') {
        if ($currentOutstanding <= 0) {
            $formError = 'Your full payment is already completed. No outstanding due.';
        } else {
            $recordAmount = $currentOutstanding;
            $selectedStatus = 'Paid';
        }
    }

    if ($formError === '' && $paymentPlan === 'Monthly') {
        if ($amountPaidInput <= 0) {
            $formError = 'Enter a valid installment amount greater than 0 for monthly payment.';
        } elseif ($currentOutstanding <= 0) {
            $formError = 'Your monthly installments are already completed. No outstanding due.';
        } elseif ($amountPaidInput > $currentOutstanding) {
            $formError = 'Installment amount cannot be greater than the outstanding due.';
        } else {
            $recordAmount = $amountPaidInput;
            $runningTotal = $existingTotalPaid + $recordAmount;
            if ($runningTotal >= $fee) {
                $selectedStatus = 'Paid';
            } else {
                $selectedStatus = 'Pending';
            }
        }
    }

    if ($formError === '') {
        $paymentSql = "INSERT INTO student_payments (user_id, registration_fee, payment_plan, payment_method, payment_status)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                registration_fee = VALUES(registration_fee),
                payment_plan = VALUES(payment_plan),
                payment_method = VALUES(payment_method),
                payment_status = VALUES(payment_status)";
        $paymentStmt = mysqli_prepare($conn, $paymentSql);
        if ($paymentStmt) {
            mysqli_stmt_bind_param($paymentStmt, 'idsss', $userId, $fee, $paymentPlan, $selectedMethod, $selectedStatus);
            mysqli_stmt_execute($paymentStmt);
            mysqli_stmt_close($paymentStmt);
        }

        $lastLegacyHistoryId = 0;
        if ($recordAmount > 0) {
            $remainingAfterPayment = max(0, $fee - ($existingTotalPaid + $recordAmount));
            $statusNote = $paymentPlan === 'Full'
                ? 'Full payment completed'
                : 'Remaining balance: LKR ' . number_format($remainingAfterPayment, 2);
            $historyStmt = mysqli_prepare($conn, "INSERT INTO student_payment_history (user_id, payment_plan, payment_method, amount_paid, status_note) VALUES (?, ?, ?, ?, ?)");
            if ($historyStmt) {
                mysqli_stmt_bind_param($historyStmt, 'issds', $userId, $paymentPlan, $selectedMethod, $recordAmount, $statusNote);
                mysqli_stmt_execute($historyStmt);
                $lastLegacyHistoryId = (int) mysqli_insert_id($conn);
                mysqli_stmt_close($historyStmt);
            }
        }

        $redirectAction = $paymentAction === 'checkout' ? 'checkout' : 'make_payment';
        $receiptLeg = $lastLegacyHistoryId > 0 ? '&receipt_history_id=' . $lastLegacyHistoryId : '';
        header('Location: payments.php?saved=1&paid=1&action=' . urlencode($redirectAction) . $receiptLeg);
        exit;
    }
}

if ($applicationCheckoutId > 0) {
    $loadCheckout = mysqli_prepare(
        $conn,
        'SELECT a.application_id, a.status, a.payment_status,
            c.course_name, c.field, c.level, c.mode, c.duration_months,
            c.average_fee, c.fees, c.registration_fee, c.installment_amount
        FROM student_applications a
        INNER JOIN course_catalog c ON c.course_id = a.course_id
        WHERE a.application_id = ? AND a.user_id = ? LIMIT 1'
    );
    if ($loadCheckout) {
        mysqli_stmt_bind_param($loadCheckout, 'ii', $applicationCheckoutId, $userId);
        mysqli_stmt_execute($loadCheckout);
        $checkoutRes = mysqli_stmt_get_result($loadCheckout);
        $applicationCheckout = $checkoutRes ? mysqli_fetch_assoc($checkoutRes) : null;
        mysqli_stmt_close($loadCheckout);
    }
}

$paymentSelect = mysqli_prepare($conn, "SELECT registration_fee, payment_plan, payment_method, payment_status FROM student_payments WHERE user_id = ? LIMIT 1");
if ($paymentSelect) {
    mysqli_stmt_bind_param($paymentSelect, 'i', $userId);
    mysqli_stmt_execute($paymentSelect);
    $paymentRes = mysqli_stmt_get_result($paymentSelect);
    if ($paymentRes && mysqli_num_rows($paymentRes) > 0) {
        $row = mysqli_fetch_assoc($paymentRes);
        $registrationFee = (float) ($row['registration_fee'] ?? 2500);
        $paymentPlan = $row['payment_plan'] ?? 'Full';
        $paymentMethod = $row['payment_method'] ?? 'Not selected';
    }
    mysqli_stmt_close($paymentSelect);
}

$historyRows = [];
$totalPaid = 0.0;
$historyStmt = mysqli_prepare($conn, "SELECT amount_paid, payment_plan, payment_method, status_note, paid_at, application_id
    FROM student_payment_history
    WHERE user_id = ?
    ORDER BY paid_at DESC");
if ($historyStmt) {
    mysqli_stmt_bind_param($historyStmt, 'i', $userId);
    mysqli_stmt_execute($historyStmt);
    $historyRes = mysqli_stmt_get_result($historyStmt);
    while ($historyRes && ($entry = mysqli_fetch_assoc($historyRes))) {
        $historyRows[] = $entry;
        $totalPaid += (float) ($entry['amount_paid'] ?? 0);
    }
    mysqli_stmt_close($historyStmt);
}

$outstanding = max(0, $registrationFee - $totalPaid);

$showPaymentSuccessModal = isset($_GET['paid']) && $_GET['paid'] === '1';
$paymentSuccessFromApp = isset($_GET['app_payment']) && $_GET['app_payment'] === '1';
$paymentSuccessIsCheckout = isset($_GET['action']) && $_GET['action'] === 'checkout';
$paymentReceiptHistoryId = (int) ($_GET['receipt_history_id'] ?? 0);

studentPageStart('Payments', 'payments', $studentName);
?>

<?php if (isset($_GET['saved']) && $_GET['saved'] === '1'): ?>
    <div class="alert alert-success">Payment details updated successfully.</div>
<?php endif; ?>
<?php if ($formError !== ''): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php if ($applicationCheckoutId > 0): ?>
    <?php if ($applicationCheckout === null): ?>
        <section class="student-section">
            <p class="help-text">We could not find this application or it does not belong to your account.</p>
            <p><a href="applications.php">Back to applications</a></p>
        </section>
    <?php else: ?>
        <?php
        $appStatus = strtolower((string) $applicationCheckout['status']);
        $appPay = strtolower((string) ($applicationCheckout['payment_status'] ?? 'Unpaid'));
        [$programFeeDisplay, $programFeeSource] = paymentsCourseProgramFeeDetail($applicationCheckout);
        $catalogRegistrationFee = max(0, (float) ($applicationCheckout['registration_fee'] ?? 0));
        $paidTowardThisApp = paymentsApplicationPaidSum($conn, $userId, (int) $applicationCheckout['application_id']);
        $totalLiability = $programFeeDisplay + $catalogRegistrationFee;
        $outstandingThisApp = max(0, $totalLiability - $paidTowardThisApp);
        $chargedNowThisApp = ($appPay !== 'paid') ? $outstandingThisApp : 0;
        $scheduleInstallmentCatalog = paymentsApplicationScheduleInstallment(
            max(0, (float) ($applicationCheckout['installment_amount'] ?? 0)),
            $totalLiability
        );
        $installmentChargeNow = $outstandingThisApp > 0 ? min($scheduleInstallmentCatalog, $outstandingThisApp) : 0;
        $applicationCanInstallment = $outstandingThisApp > 0.009
            && $installmentChargeNow > 0.009
            && ($outstandingThisApp - $installmentChargeNow) > 0.009;
        $chargedNowFullApp = $outstandingThisApp;
        $chargedNowInstallmentApp = $installmentChargeNow;
        $feeSourceReadable = $programFeeSource === 'fees' ? 'Fees' : ($programFeeSource === 'average_fee' ? 'Average fee' : '');
        ?>
        <section class="student-section payment-card-section payment-checkout-shell payment-gateway-page payment-gateway-page--wide">
            <div class="payment-gateway-brand">
                <span class="payment-gateway-lock" aria-hidden="true">🔒</span>
                <span>Secure payment</span>
            </div>

            <?php
            $fieldLbl = trim((string) ($applicationCheckout['field'] ?? ''));
            $levelLbl = trim((string) ($applicationCheckout['level'] ?? ''));
            $fieldPath = $fieldLbl !== '' && $levelLbl !== ''
                ? $fieldLbl . ' · ' . $levelLbl
                : ($fieldLbl !== '' ? $fieldLbl : ($levelLbl !== '' ? $levelLbl : '—'));
            if ($feeSourceReadable !== '') {
                $orderFootnote = 'Program fee uses this course’s catalog ' . $feeSourceReadable . ' field. Paid and outstanding are only payments linked to application #' . (int) $applicationCheckout['application_id'] . '.';
            } elseif ($catalogRegistrationFee > 0) {
                $orderFootnote = 'No program fee is set in Fees or Average fee for this course; the amount due uses this course’s catalog registration only. Paid and outstanding are only payments linked to application #' . (int) $applicationCheckout['application_id'] . '.';
            } else {
                $orderFootnote = 'No program fee or registration is set in the catalog for this course. Paid and outstanding are only payments linked to application #' . (int) $applicationCheckout['application_id'] . '.';
            }
            ?>
            <?php if ($appPay === 'paid'): ?>
                <div class="payment-card-solo">
                    <div class="payment-card-entry">
                        <p class="help-text">This course fee has already been paid. Thank you.</p>
                        <p><a class="btn-primary btn-action" href="applications.php">Back to applications</a></p>
                    </div>
                </div>
            <?php elseif ($appStatus !== 'approved'): ?>
                <p class="help-text">Payments open once your application is approved.</p>
                <p><a class="btn-primary btn-action" href="applications.php">Back to applications</a></p>
            <?php elseif ($chargedNowThisApp <= 0): ?>
                <?php if ($feeSourceReadable === '' && $catalogRegistrationFee <= 0): ?>
                    <div class="alert alert-error">Course fees are not set for this program in the catalog (Fees or Average fee, and optional registration). You cannot pay online until an administrator updates the course.</div>
                <?php else: ?>
                    <p class="help-text"><strong>No balance is due</strong> for this application based on this course’s fees and what you have already paid.</p>
                <?php endif; ?>
                <p><a class="btn-primary btn-action" href="applications.php">Back to applications</a></p>
            <?php else: ?>
                <form method="post" action="payments.php?application_id=<?php echo (int) $applicationCheckoutId; ?>" class="payment-gateway-form payment-card-form payment-real-form" id="application-card-form">
                    <input type="hidden" name="application_card_payment" value="1">
                    <input type="hidden" name="application_id" value="<?php echo (int) $applicationCheckoutId; ?>">

                    <div class="payment-card-solo">
                        <div class="payment-card-entry">
                            <div class="payment-card-entry-head">
                                <h2 class="payment-card-entry-title">Payment details</h2>
                                <p class="payment-card-entry-sub">Application #<?php echo (int) $applicationCheckout['application_id']; ?> · <?php echo htmlspecialchars($applicationCheckout['course_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>

                            <div class="payment-fake-card" aria-hidden="true">
                                <div class="payment-fake-card-top">
                                    <span class="payment-fake-chip"></span>
                                    <span class="payment-fake-brand">VISA</span>
                                </div>
                                <p class="payment-fake-pan" data-fake-pan>•••• •••• •••• ••••</p>
                                <div class="payment-fake-card-bottom">
                                    <span class="payment-fake-name" data-fake-name>NAME ON CARD</span>
                                    <span class="payment-fake-exp" data-fake-exp>MM/YY</span>
                                </div>
                            </div>

                            <div class="payment-card-fields-split">
                                <div class="form-group">
                                    <label class="form-label" for="cardholder_name">Name on card</label>
                                    <input class="form-control payment-input-lg" id="cardholder_name" name="cardholder_name" type="text" autocomplete="cc-name" required placeholder="e.g. S. Perera">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="card_number">Card number</label>
                                    <input class="form-control payment-input-lg payment-input-mono" id="card_number" name="card_number" type="text" inputmode="numeric" autocomplete="cc-number" placeholder="1234 5678 9012 3456" maxlength="23" required>
                                </div>
                            </div>
                            <div class="payment-card-fields-split">
                                <div class="form-group">
                                    <label class="form-label" for="card_expiry">Expiry date</label>
                                    <input class="form-control payment-input-lg payment-input-mono" id="card_expiry" name="card_expiry" type="text" autocomplete="cc-exp" placeholder="MM/YY" maxlength="5" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="card_cvv">CVV</label>
                                    <input class="form-control payment-input-lg payment-input-mono" id="card_cvv" name="card_cvv" type="password" inputmode="numeric" autocomplete="cc-csc" placeholder="123" maxlength="4" required>
                                    <span class="payment-field-hint">3 or 4 digits on the back of your card</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="application-payment-plan">Payment option</label>
                                <select class="form-control payment-plan-select" id="application-payment-plan" name="application_course_payment_plan" required>
                                    <option value="" selected>Select how you will pay…</option>
                                    <option value="Full">Full payment — LKR <?php echo htmlspecialchars(number_format($chargedNowFullApp, 2), ENT_QUOTES, 'UTF-8'); ?></option>
                                    <option value="Installment" <?php echo $applicationCanInstallment ? '' : 'disabled'; ?>>Installment — LKR <?php echo htmlspecialchars(number_format($chargedNowInstallmentApp, 2), ENT_QUOTES, 'UTF-8'); ?><?php echo $applicationCanInstallment ? '' : ' (not available)'; ?></option>
                                </select>
                            </div>
                            <p class="help-text" id="app-plan-hint">Choose full payment or installment to see the amount charged.</p>

                            <div class="payment-card-fields-split" id="application-installment-wrap" hidden>
                                <div class="form-group">
                                    <span class="form-label">Scheduled installment</span>
                                    <p class="payment-readonly-value">LKR <?php echo htmlspecialchars(number_format($scheduleInstallmentCatalog, 2), ENT_QUOTES, 'UTF-8'); ?> <span class="help-text">(catalog or quarter of total)</span></p>
                                </div>
                                <div class="form-group">
                                    <span class="form-label">Balance after this payment</span>
                                    <p class="payment-readonly-value" id="app-balance-after">—</p>
                                </div>
                            </div>

                            <div class="payment-card-fields-split">
                                <div class="form-group">
                                    <span class="form-label">Outstanding</span>
                                    <p class="payment-readonly-value">LKR <?php echo htmlspecialchars(number_format($outstandingThisApp, 2), ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <div class="form-group">
                                    <span class="form-label">Charged now</span>
                                    <p class="payment-readonly-value payment-readonly-value--emphasis payment-order-amount--placeholder" id="app-charged-now">—</p>
                                </div>
                            </div>

                            <p class="help-text payment-card-note">Demonstration only — do not enter real card details on shared computers.</p>

                            <button type="submit" class="payment-proceed-btn" id="payment-proceed-submit" disabled>
                                <span class="payment-proceed-label">Proceed to payment</span>
                                <span class="payment-proceed-amount" id="app-proceed-amount">LKR 0.00</span>
                            </button>
                        </div>
                    </div>
                </form>

                <script>
                (function () {
                    var form = document.getElementById('application-card-form');
                    if (!form) return;
                    var outstanding = <?php echo json_encode($outstandingThisApp); ?>;
                    var fullAmt = <?php echo json_encode($chargedNowFullApp); ?>;
                    var instAmt = <?php echo json_encode($chargedNowInstallmentApp); ?>;
                    var canInst = <?php echo json_encode($applicationCanInstallment); ?>;
                    var num = document.getElementById('card_number');
                    var name = document.getElementById('cardholder_name');
                    var exp = document.getElementById('card_expiry');
                    var fakePan = document.querySelector('[data-fake-pan]');
                    var fakeName = document.querySelector('[data-fake-name]');
                    var fakeExp = document.querySelector('[data-fake-exp]');
                    var chargedEl = document.getElementById('app-charged-now');
                    var proceedAmt = document.getElementById('app-proceed-amount');
                    var hint = document.getElementById('app-plan-hint');
                    var balanceAfter = document.getElementById('app-balance-after');
                    var instWrap = document.getElementById('application-installment-wrap');
                    var planSel = document.getElementById('application-payment-plan');
                    var submitBtn = document.getElementById('payment-proceed-submit');

                    function fmtMoney(n) {
                        var s = Number(n).toFixed(2);
                        var parts = s.split('.');
                        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        return 'LKR ' + parts.join('.');
                    }

                    function syncPlanUi() {
                        var v = planSel ? planSel.value : '';
                        var inst = v === 'Installment' && canInst;
                        if (instWrap) instWrap.hidden = !inst;
                        if (!v) {
                            if (chargedEl) { chargedEl.textContent = '—'; chargedEl.classList.add('payment-order-amount--placeholder'); }
                            if (proceedAmt) proceedAmt.textContent = 'LKR 0.00';
                            if (balanceAfter) balanceAfter.textContent = '—';
                            if (hint) hint.textContent = 'Choose full payment or installment to see the amount charged.';
                            if (submitBtn) submitBtn.disabled = true;
                            return;
                        }
                        var charge = inst ? instAmt : fullAmt;
                        var after = Math.max(0, outstanding - charge);
                        if (chargedEl) { chargedEl.textContent = fmtMoney(charge); chargedEl.classList.remove('payment-order-amount--placeholder'); }
                        if (proceedAmt) proceedAmt.textContent = fmtMoney(charge);
                        if (balanceAfter) balanceAfter.textContent = fmtMoney(after);
                        if (hint) {
                            hint.textContent = inst
                                ? 'Installment: you pay part of the outstanding now; the rest stays due until you pay again from this application.'
                                : 'Full payment: one charge for the full outstanding amount on this application.';
                        }
                        if (submitBtn) submitBtn.disabled = false;
                    }

                    num.addEventListener('input', function () {
                        var d = num.value.replace(/\D/g, '').slice(0, 19);
                        var parts = [];
                        for (var i = 0; i < d.length; i += 4) parts.push(d.slice(i, i + 4));
                        num.value = parts.join(' ');
                        if (fakePan) fakePan.textContent = num.value || '•••• •••• •••• ••••';
                    });
                    name.addEventListener('input', function () {
                        var t = (name.value || '').trim().toUpperCase() || 'NAME ON CARD';
                        if (fakeName) fakeName.textContent = t.length > 22 ? t.slice(0, 22) + '…' : t;
                    });
                    exp.addEventListener('input', function () {
                        var raw = exp.value.replace(/\D/g, '').slice(0, 4);
                        if (raw.length <= 2) {
                            exp.value = raw;
                        } else {
                            exp.value = raw.slice(0, 2) + '/' + raw.slice(2);
                        }
                        if (fakeExp) {
                            fakeExp.textContent = exp.value || 'MM/YY';
                        }
                    });
                    if (planSel) planSel.addEventListener('change', syncPlanUi);
                    syncPlanUi();
                })();
                </script>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php if ($applicationCheckoutId <= 0): ?>
    <section class="student-section payment-card-section payment-checkout-shell payment-gateway-page payment-gateway-page--wide">
        <div class="payment-gateway-brand">
            <span class="payment-gateway-lock" aria-hidden="true">🔒</span>
            <span>Secure payment</span>
        </div>
        <h2 class="payment-page-title">Registration balance</h2>
        <p class="help-text payment-page-lead">Pay your account registration target with a card. This is separate from paying a specific approved application (use Make payment from Applications for that).</p>

        <?php if ($outstanding <= 0): ?>
            <div class="payment-all-clear">
                <p class="help-text"><strong>No balance due.</strong> Your recorded payments cover this registration target.</p>
                <?php if ($paymentPlan === 'Monthly'): ?>
                    <p class="help-text">Monthly plan: no upcoming installments.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <form method="post" action="payments.php" class="payment-gateway-form" id="general-card-form">
                <input type="hidden" name="general_card_payment" value="1">
                <input type="hidden" name="registration_fee" value="<?php echo htmlspecialchars((string) $registrationFee, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="payment_plan" value="Full">

                <div class="payment-card-solo">
                    <div class="payment-card-entry">
                        <div class="payment-card-entry-head">
                            <h3 class="payment-card-entry-title">Card details</h3>
                            <p class="payment-card-entry-sub">Credit or debit card</p>
                        </div>

                        <div class="payment-fake-card" aria-hidden="true">
                            <div class="payment-fake-card-top">
                                <span class="payment-fake-chip"></span>
                                <span class="payment-fake-brand">VISA</span>
                            </div>
                            <p class="payment-fake-pan" data-general-fake-pan>•••• •••• •••• ••••</p>
                            <div class="payment-fake-card-bottom">
                                <span class="payment-fake-name" data-general-fake-name>NAME ON CARD</span>
                                <span class="payment-fake-exp" data-general-fake-exp>MM/YY</span>
                            </div>
                        </div>

                        <div class="payment-card-form payment-real-form">
                            <div class="payment-card-fields-split">
                                <div class="form-group">
                                    <label class="form-label" for="general_cardholder_name">Name on card</label>
                                    <input class="form-control payment-input-lg" id="general_cardholder_name" name="cardholder_name" type="text" autocomplete="cc-name" required placeholder="As printed on your card">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="general_card_number">Card number</label>
                                    <input class="form-control payment-input-lg payment-input-mono" id="general_card_number" name="card_number" type="text" inputmode="numeric" autocomplete="cc-number" placeholder="1234 5678 9012 3456" maxlength="23" required>
                                </div>
                            </div>
                            <div class="payment-card-fields-split">
                                <div class="form-group">
                                    <label class="form-label" for="general_card_expiry">Expiry date</label>
                                    <input class="form-control payment-input-lg payment-input-mono" id="general_card_expiry" name="card_expiry" type="text" autocomplete="cc-exp" placeholder="MM/YY" maxlength="5" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="general_card_cvv">CVV</label>
                                    <input class="form-control payment-input-lg payment-input-mono" id="general_card_cvv" name="card_cvv" type="password" inputmode="numeric" autocomplete="cc-csc" placeholder="123" maxlength="4" required>
                                    <span class="payment-field-hint">3 or 4 digits on the back of your card</span>
                                </div>
                            </div>

                            <p class="help-text payment-card-note">Demonstration checkout — do not use real card data on shared devices.</p>

                            <button type="submit" class="payment-proceed-btn" id="general-payment-proceed">
                                <span class="payment-proceed-label">Proceed to payment</span>
                                <span class="payment-proceed-amount payment-order-amount--placeholder" id="general-proceed-amount">LKR 0.00</span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <script>
            (function () {
                var form = document.getElementById('general-card-form');
                if (!form) return;
                var num = document.getElementById('general_card_number');
                var name = document.getElementById('general_cardholder_name');
                var exp = document.getElementById('general_card_expiry');
                var fakePan = document.querySelector('[data-general-fake-pan]');
                var fakeName = document.querySelector('[data-general-fake-name]');
                var fakeExp = document.querySelector('[data-general-fake-exp]');
                if (!num || !name || !exp) return;

                num.addEventListener('input', function () {
                    var d = num.value.replace(/\D/g, '').slice(0, 19);
                    var parts = [];
                    for (var i = 0; i < d.length; i += 4) parts.push(d.slice(i, i + 4));
                    num.value = parts.join(' ');
                    if (fakePan) fakePan.textContent = num.value || '•••• •••• •••• ••••';
                });
                name.addEventListener('input', function () {
                    var t = (name.value || '').trim().toUpperCase() || 'NAME ON CARD';
                    if (fakeName) fakeName.textContent = t.length > 22 ? t.slice(0, 22) + '…' : t;
                });
                exp.addEventListener('input', function () {
                    var raw = exp.value.replace(/\D/g, '').slice(0, 4);
                    exp.value = raw.length <= 2 ? raw : raw.slice(0, 2) + '/' + raw.slice(2);
                    if (fakeExp) fakeExp.textContent = exp.value || 'MM/YY';
                });
            })();
            </script>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="student-section">
    <h2>Payment History</h2>
    <?php if (empty($historyRows)): ?>
        <p class="help-text">No payment records yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="student-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Plan</th>
                        <th>Method</th>
                        <th>Amount Paid</th>
                        <th>Application</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historyRows as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date('d M Y', strtotime($item['paid_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($item['payment_plan'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($item['payment_method'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>LKR <?php echo htmlspecialchars(number_format((float) $item['amount_paid'], 2), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo !empty($item['application_id']) ? '#' . (int) $item['application_id'] : '—'; ?></td>
                            <td><?php echo htmlspecialchars($item['status_note'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($showPaymentSuccessModal): ?>
    <div id="payment-success-modal" class="payment-modal-overlay is-open" role="dialog" aria-modal="true" aria-labelledby="payment-success-title" tabindex="-1">
        <div class="payment-modal-dialog">
            <div class="payment-modal-icon" aria-hidden="true">✓</div>
            <h2 id="payment-success-title" class="payment-modal-title">Payment successful</h2>
            <?php if ($paymentSuccessFromApp): ?>
                <p class="payment-modal-text">Your payment was completed successfully. A receipt has been saved to your payment history.</p>
            <?php elseif ($paymentSuccessIsCheckout): ?>
                <p class="payment-modal-text">Your checkout is complete and your payment has been recorded.</p>
            <?php else: ?>
                <p class="payment-modal-text">Your payment was recorded successfully.</p>
            <?php endif; ?>
            <div class="payment-modal-actions">
                <button type="button" class="payment-modal-btn-primary" id="payment-success-modal-ok">OK</button>
            </div>
        </div>
    </div>
    <?php if ($paymentReceiptHistoryId > 0): ?>
        <div id="payment-receipt-followup" class="payment-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="payment-receipt-followup-title" tabindex="-1" aria-hidden="true">
            <div class="payment-modal-dialog">
                <h2 id="payment-receipt-followup-title" class="payment-modal-title">Your receipt</h2>
                <p class="payment-modal-text">View, print, or download a PDF of this payment (same options as the office receipt).</p>
                <div class="payment-modal-actions payment-modal-actions--stack">
                    <a class="payment-modal-btn-primary" target="_blank" rel="noopener" href="payment_receipt.php?id=<?php echo (int) $paymentReceiptHistoryId; ?>">View receipt</a>
                    <button type="button" class="payment-modal-btn-secondary" id="student-receipt-followup-print">Print</button>
                    <button type="button" class="payment-modal-btn-secondary" id="student-receipt-followup-download">Download PDF</button>
                    <?php if ($paymentSuccessFromApp): ?>
                        <a class="payment-modal-btn-secondary" href="applications.php#application-history">Application history</a>
                    <?php endif; ?>
                    <button type="button" class="payment-modal-btn-secondary" id="payment-receipt-followup-close">Close</button>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <script>
    (function () {
        var modal = document.getElementById('payment-success-modal');
        var ok = document.getElementById('payment-success-modal-ok');
        var followup = document.getElementById('payment-receipt-followup');
        var receiptHistId = <?php echo (int) $paymentReceiptHistoryId; ?>;
        var receiptBase = 'payment_receipt.php?id=' + receiptHistId;
        if (!modal) return;

        function stripSuccessParams() {
            try {
                var u = new URL(window.location.href);
                u.searchParams.delete('paid');
                u.searchParams.delete('app_payment');
                u.searchParams.delete('action');
                u.searchParams.delete('receipt_history_id');
                if (u.searchParams.get('saved') === '1') {
                    u.searchParams.delete('saved');
                }
                var qs = u.searchParams.toString();
                history.replaceState({}, '', u.pathname + (qs ? '?' + qs : '') + u.hash);
            } catch (err) {}
        }

        function onEscSuccess(e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                closeSuccessOnly();
            }
        }

        function closeSuccessOnly() {
            document.body.style.overflow = '';
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.removeEventListener('keydown', onEscSuccess);
            stripSuccessParams();
        }

        function onEscFollowup(e) {
            if (e.key === 'Escape' && followup && followup.classList.contains('is-open')) {
                closeFollowup();
            }
        }

        function closeFollowup() {
            if (!followup) return;
            followup.classList.remove('is-open');
            followup.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            document.removeEventListener('keydown', onEscFollowup);
        }

        document.body.style.overflow = 'hidden';

        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeSuccessOnly();
                if (receiptHistId > 0 && followup) {
                    openFollowup();
                }
            }
        });
        if (ok) {
            ok.addEventListener('click', function () {
                closeSuccessOnly();
                if (receiptHistId > 0 && followup) {
                    openFollowup();
                }
            });
        }
        document.addEventListener('keydown', onEscSuccess);

        function openFollowup() {
            if (!followup) return;
            if (followup.dataset.bound !== '1') {
                followup.dataset.bound = '1';
                followup.addEventListener('click', function (e) {
                    if (e.target === followup) {
                        closeFollowup();
                    }
                });
                var fp = document.getElementById('student-receipt-followup-print');
                if (fp) {
                    fp.addEventListener('click', function () {
                        window.open(receiptBase + '&print=1', '_blank', 'noopener,noreferrer');
                    });
                }
                var fd = document.getElementById('student-receipt-followup-download');
                if (fd) {
                    fd.addEventListener('click', function () {
                        window.open(receiptBase + '&download=1', '_blank', 'noopener,noreferrer');
                    });
                }
                var fc = document.getElementById('payment-receipt-followup-close');
                if (fc) {
                    fc.addEventListener('click', closeFollowup);
                }
            }
            followup.classList.add('is-open');
            followup.removeAttribute('aria-hidden');
            document.body.style.overflow = 'hidden';
            document.removeEventListener('keydown', onEscFollowup);
            document.addEventListener('keydown', onEscFollowup);
        }

        setTimeout(function () {
            if (ok) {
                ok.focus();
            }
        }, 100);
    })();
    </script>
<?php endif; ?>

<?php studentPageEnd(); ?>
