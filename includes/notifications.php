<?php
/**
 * Insert a notification (idempotent via ref_key).
 */
function notify($conn, $userId, $title, $message, $link = null, $type = 'info', $refKey = null) {
    $stmt = $conn->prepare("
        INSERT IGNORE INTO notifications (user_id, title, message, link, type, ref_key)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isssss", $userId, $title, $message, $link, $type, $refKey);
    $stmt->execute();
}

/**
 * Get unread count for a user.
 */
function unreadCount($conn, $userId) {
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'];
}

/**
 * Auto-generate due-date notifications for a tenant user.
 * Called on every dashboard load — safe to run repeatedly.
 */
function generateDueNotifications($conn, $tenantId, $userId) {
    $today = date('Y-m-d');
    $soon  = date('Y-m-d', strtotime('+3 days'));

    // Payments that are unpaid/partial with a due date
    $stmt = $conn->prepare("
        SELECT id, amount, payment_type, due_date, period_label
        FROM payments
        WHERE tenant_id = ?
          AND status IN ('unpaid','partial')
          AND due_date IS NOT NULL
          AND due_date <= ?
    ");
    $stmt->bind_param("is", $tenantId, $soon);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($rows as $p) {
        $isOverdue = $p['due_date'] < $today;
        $label     = $p['period_label'] ?: ucfirst($p['payment_type']);
        $amount    = number_format($p['amount'], 2);

        if ($isOverdue) {
            notify(
                $conn, $userId,
                "Overdue Payment: ₱$amount",
                "$label was due on {$p['due_date']}. Please settle your balance.",
                BASE . "/tenant/billing.php",
                'danger',
                "overdue_payment_{$p['id']}"
            );
        } else {
            notify(
                $conn, $userId,
                "Upcoming Payment: ₱$amount",
                "$label is due on {$p['due_date']}.",
                BASE . "/tenant/billing.php",
                'warning',
                "due_soon_payment_{$p['id']}"
            );
        }
    }
}
?>