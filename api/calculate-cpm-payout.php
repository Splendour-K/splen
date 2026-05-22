<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_role('admin');

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$contest_id = $_POST['contest_id'] ?? 0;

if (!$action || !$contest_id) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

if ($action === 'calculate_cpm_payouts') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM contests WHERE id = ?");
        $stmt->execute([$contest_id]);
        $contest = $stmt->fetch();

        if (!$contest) {
            echo json_encode(['error' => 'Contest not found']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT cs.* FROM contest_submissions cs
            WHERE cs.contest_id = ? AND cs.status = 'winner' AND cs.views_verified = 1
        ");
        $stmt->execute([$contest_id]);
        $winners = $stmt->fetchAll();

        if (empty($winners)) {
            echo json_encode(['message' => 'No verified winners to calculate payouts for']);
            exit;
        }

        $total_budget = $contest['total_contest_budget'];
        $total_verified_views = 0;

        foreach ($winners as $winner) {
            $total_verified_views += ($winner['verified_view_count'] ?? 0);
        }

        if ($total_verified_views === 0) {
            echo json_encode(['message' => 'No verified views from winners']);
            exit;
        }

        $cpm = $total_budget / ($total_verified_views / 1000);

        $payout_records = [];

        foreach ($winners as $winner) {
            $verified_views = $winner['verified_view_count'] ?? 0;
            $payout_amount = ($verified_views / 1000) * $cpm;

            $stmt = $pdo->prepare("
                INSERT INTO payments (creator_id, job_id, amount, calculated_amount, currency, payment_type, status)
                VALUES (?, 0, ?, ?, ?, 'contest_cpm', 'pending')
            ");
            $stmt->execute([
                $winner['creator_id'],
                $payout_amount,
                $payout_amount,
                $contest['currency']
            ]);

            $payment_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("UPDATE contest_submissions SET payment_id = ? WHERE id = ?");
            $stmt->execute([$payment_id, $winner['id']]);

            create_notification(
                $winner['creator_id'],
                'CPM Payout Calculated',
                'Your CPM payout for ' . $contest['title'] . ' has been calculated at ' . number_format($cpm, 4) . ' ' . $contest['currency'] . ' per 1000 views.',
                'cpm_calculated',
                'creator/my-contests.php',
                'contest_payout',
                $contest_id
            );

            $payout_records[] = [
                'winner_id' => $winner['id'],
                'creator_id' => $winner['creator_id'],
                'verified_views' => $verified_views,
                'amount' => $payout_amount
            ];
        }

        $stmt = $pdo->prepare("UPDATE contests SET cpm_rate = ?, cpm_calculated_at = NOW() WHERE id = ?");
        $stmt->execute([$cpm, $contest_id]);

        echo json_encode([
            'success' => true,
            'cpm' => $cpm,
            'total_budget' => $total_budget,
            'total_views' => $total_verified_views,
            'payouts' => $payout_records,
            'count' => count($payout_records)
        ]);

    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['error' => 'Unknown action']);
?>
