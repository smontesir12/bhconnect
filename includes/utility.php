<?php
/**
 * Compute approved occupied days for a tenant in a billing period.
 * Logic:
 *   1. Contract days within period = base days
 *   2. Subtract days where tenant was "checked out" (approved absence)
 */
function computeApprovedDays($conn, $tenantId, $periodStart, $periodEnd) {
    // 1. Get active contract
    $stmt = $conn->prepare("SELECT move_in, move_out FROM contracts
                            WHERE tenant_id = ? AND status='active' LIMIT 1");
    $stmt->bind_param("i", $tenantId);
    $stmt->execute();
    $c = $stmt->get_result()->fetch_assoc();
    if (!$c) return 0;

    // 2. Clamp contract to billing period
    $start = max(strtotime($c['move_in']), strtotime($periodStart));
    $end   = min(
        $c['move_out'] ? strtotime($c['move_out']) : strtotime($periodEnd),
        strtotime($periodEnd)
    );
    if ($end < $start) return 0;

    $contractDays = floor(($end - $start) / 86400) + 1;

    // 3. Walk occupancy logs, subtract approved absences
    $stmt = $conn->prepare("SELECT event_type, event_time FROM occupancy_logs
                            WHERE tenant_id = ?
                              AND status IN ('valid','approved')
                              AND event_time BETWEEN ? AND ?
                            ORDER BY event_time ASC");
    $ps = date('Y-m-d 00:00:00', $start);
    $pe = date('Y-m-d 23:59:59', $end);
    $stmt->bind_param("iss", $tenantId, $ps, $pe);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $absentDays = 0;
    $checkoutTime = null;
    foreach ($logs as $log) {
        if ($log['event_type'] === 'check_out' && $checkoutTime === null) {
            $checkoutTime = strtotime($log['event_time']);
        } elseif ($log['event_type'] === 'check_in' && $checkoutTime !== null) {
            $absentDays += floor((strtotime($log['event_time']) - $checkoutTime) / 86400);
            $checkoutTime = null;
        }
    }
    // If still checked out at period end, count until end
    if ($checkoutTime !== null) {
        $absentDays += floor(($end - $checkoutTime) / 86400);
    }

    return max(0, $contractDays - $absentDays);
}

/**
 * Compute utility shares for all active tenants for a given bill.
 * Returns array of [tenant_id, approved_days, share_amount].
 */
function computeAllocation($conn, $billId) {
    $bill = $conn->query("SELECT * FROM utility_bills WHERE id = $billId")->fetch_assoc();
    if (!$bill) return [];

    $tenants = $conn->query("SELECT id FROM tenants WHERE status='active'")->fetch_all(MYSQLI_ASSOC);

    $rows = [];
    $totalDays = 0;
    foreach ($tenants as $t) {
        $days = computeApprovedDays($conn, $t['id'], $bill['billing_start'], $bill['billing_end']);
        $rows[] = ['tenant_id' => $t['id'], 'approved_days' => $days];
        $totalDays += $days;
    }

    foreach ($rows as &$r) {
        $r['share_amount'] = $totalDays > 0
            ? round(($r['approved_days'] / $totalDays) * $bill['total_amount'], 2)
            : 0;
    }
    return ['total_days' => $totalDays, 'rows' => $rows];
}
?>