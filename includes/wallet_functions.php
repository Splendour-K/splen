<?php
/**
 * Splennet — Brand Wallet Functions
 *
 * All balance mutations happen inside DB transactions.
 * No direct balance updates without a wallet_transactions record.
 * No negative balances allowed.
 */

// ── Retrieve / auto-create wallet ────────────────────────────

/**
 * Get a brand's wallet row. Creates one (GHS, zero balance) if it doesn't exist yet.
 */
function get_brand_wallet(int $brand_id): array|false {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM brand_wallets WHERE brand_id = ?");
    $stmt->execute([$brand_id]);
    $wallet = $stmt->fetch();

    if (!$wallet) {
        $pdo->prepare("INSERT IGNORE INTO brand_wallets (brand_id, currency) VALUES (?, 'GHS')")
            ->execute([$brand_id]);
        $stmt->execute([$brand_id]);
        $wallet = $stmt->fetch();
    }

    return $wallet;
}

// ── Pre-publish wallet check ──────────────────────────────────

/**
 * Check whether a brand can publish a campaign of $required_amount in $currency.
 *
 * Returns an array:
 *   ['ok' => bool, 'wallet' => row, 'reason' => string,
 *    'available' => float, 'required' => float, 'shortfall' => float]
 *
 * Possible reasons when ok=false: 'frozen', 'closed', 'currency_mismatch', 'insufficient'
 */
function check_wallet_for_publish(int $brand_id, float $required_amount, string $campaign_currency): array {
    $wallet = get_brand_wallet($brand_id);

    $base = [
        'ok'        => false,
        'wallet'    => $wallet,
        'available' => (float)($wallet['available_balance'] ?? 0),
        'required'  => $required_amount,
        'shortfall' => 0,
        'reason'    => '',
    ];

    if (!$wallet || $wallet['status'] === 'closed') {
        $base['reason'] = 'closed';
        return $base;
    }

    if ($wallet['status'] === 'frozen') {
        $base['reason'] = 'frozen';
        return $base;
    }

    if (strtoupper($wallet['currency']) !== strtoupper($campaign_currency)) {
        $base['reason'] = 'currency_mismatch';
        return $base;
    }

    $available = (float)$wallet['available_balance'];
    $shortfall = max(0.0, $required_amount - $available);

    if ($shortfall > 0) {
        $base['reason']    = 'insufficient';
        $base['available'] = $available;
        $base['shortfall'] = $shortfall;
        return $base;
    }

    return array_merge($base, ['ok' => true, 'available' => $available, 'shortfall' => 0]);
}

/**
 * Build the human-readable wallet error string for display on publish pages.
 */
function wallet_error_message(array $check): string {
    $wallet = $check['wallet'];
    $currency = $wallet['currency'] ?? 'GHS';

    if ($check['reason'] === 'frozen' || $check['reason'] === 'closed') {
        return "Your wallet is not active. Please contact admin.";
    }

    if ($check['reason'] === 'currency_mismatch') {
        return "Campaign currency must match your wallet currency ({$wallet['currency']}). Please select {$wallet['currency']} for this campaign.";
    }

    if ($check['reason'] === 'insufficient') {
        $avail    = format_money($check['available'], $currency);
        $required = format_money($check['required'],  $currency);
        $needed   = format_money($check['shortfall'], $currency);
        return "Insufficient wallet balance.\n\nCurrent wallet balance: {$avail}\nRequired budget: {$required}\nAmount needed: {$needed}\n\nPlease fund your wallet or reduce your campaign budget.";
    }

    return "Wallet check failed. Please contact admin.";
}

// ── Reserve budget when publishing ───────────────────────────

/**
 * Move $amount from available_balance → reserved_balance.
 * Writes a wallet_transactions row.
 *
 * $tx_type: 'campaign_reserve' | 'contest_reserve' | 'ugc_order_reserve'
 * $ref_type: 'campaign' | 'contest' | 'ugc_order'
 */
function reserve_wallet_budget(
    int    $brand_id,
    float  $amount,
    string $tx_type,
    string $ref_type,
    int    $ref_id,
    string $description
): bool {
    global $pdo;

    $wallet = get_brand_wallet($brand_id);
    if (!$wallet) return false;

    $wallet_id       = (int)$wallet['id'];
    $avail_before    = (float)$wallet['available_balance'];
    $reserved_before = (float)$wallet['reserved_balance'];

    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare("
            UPDATE brand_wallets
            SET available_balance = available_balance - ?,
                reserved_balance  = reserved_balance  + ?,
                updated_at        = NOW()
            WHERE id = ? AND available_balance >= ?
        ");
        $upd->execute([$amount, $amount, $wallet_id, $amount]);

        if ($upd->rowCount() === 0) {
            throw new Exception("Concurrent balance change — reservation aborted.");
        }

        $pdo->prepare("
            INSERT INTO wallet_transactions
                (wallet_id, brand_id, transaction_type, amount, currency,
                 balance_before, balance_after, reserved_before, reserved_after,
                 description, reference_type, reference_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $wallet_id, $brand_id, $tx_type, $amount, $wallet['currency'],
            $avail_before,    $avail_before    - $amount,
            $reserved_before, $reserved_before + $amount,
            $description, $ref_type, $ref_id,
        ]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

// ── Admin operations ──────────────────────────────────────────

/**
 * Credit a wallet (admin only).
 */
function admin_credit_wallet(int $wallet_id, float $amount, int $admin_id, string $description): bool {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM brand_wallets WHERE id = ?");
    $stmt->execute([$wallet_id]);
    $wallet = $stmt->fetch();
    if (!$wallet || $amount <= 0) return false;

    $avail_before    = (float)$wallet['available_balance'];
    $reserved_before = (float)$wallet['reserved_balance'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE brand_wallets SET available_balance = available_balance + ?, updated_at = NOW() WHERE id = ?")
            ->execute([$amount, $wallet_id]);

        $pdo->prepare("
            INSERT INTO wallet_transactions
                (wallet_id, brand_id, admin_id, transaction_type, amount, currency,
                 balance_before, balance_after, reserved_before, reserved_after, description)
            VALUES (?, ?, ?, 'admin_credit', ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $wallet_id, $wallet['brand_id'], $admin_id, $amount, $wallet['currency'],
            $avail_before, $avail_before + $amount,
            $reserved_before, $reserved_before,
            $description,
        ]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * Debit a wallet (admin only).
 * Pass $force=true to allow debit even when balance is insufficient (manual correction).
 */
function admin_debit_wallet(int $wallet_id, float $amount, int $admin_id, string $description, bool $force = false): bool {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM brand_wallets WHERE id = ?");
    $stmt->execute([$wallet_id]);
    $wallet = $stmt->fetch();
    if (!$wallet || $amount <= 0) return false;

    $avail_before    = (float)$wallet['available_balance'];
    $reserved_before = (float)$wallet['reserved_balance'];

    if (!$force && $avail_before < $amount) return false;

    $new_balance = max(0.0, $avail_before - $amount);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE brand_wallets SET available_balance = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$new_balance, $wallet_id]);

        $pdo->prepare("
            INSERT INTO wallet_transactions
                (wallet_id, brand_id, admin_id, transaction_type, amount, currency,
                 balance_before, balance_after, reserved_before, reserved_after, description)
            VALUES (?, ?, ?, 'admin_debit', ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $wallet_id, $wallet['brand_id'], $admin_id, $amount, $wallet['currency'],
            $avail_before, $new_balance,
            $reserved_before, $reserved_before,
            $description,
        ]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * Refund unused reserved budget back to available balance.
 * Called by admin when closing/cancelling a campaign.
 */
function refund_reserved_budget(
    int    $wallet_id,
    float  $amount,
    int    $admin_id,
    string $ref_type,
    int    $ref_id,
    string $description
): bool {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM brand_wallets WHERE id = ?");
    $stmt->execute([$wallet_id]);
    $wallet = $stmt->fetch();
    if (!$wallet) return false;

    $refund_amount   = min((float)$amount, (float)$wallet['reserved_balance']);
    if ($refund_amount <= 0) return false;

    $avail_before    = (float)$wallet['available_balance'];
    $reserved_before = (float)$wallet['reserved_balance'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            UPDATE brand_wallets
            SET available_balance = available_balance + ?,
                reserved_balance  = GREATEST(0, reserved_balance - ?),
                updated_at        = NOW()
            WHERE id = ?
        ")->execute([$refund_amount, $refund_amount, $wallet_id]);

        $pdo->prepare("
            INSERT INTO wallet_transactions
                (wallet_id, brand_id, admin_id, transaction_type, amount, currency,
                 balance_before, balance_after, reserved_before, reserved_after,
                 description, reference_type, reference_id)
            VALUES (?, ?, ?, 'refund_unused_budget', ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $wallet_id, $wallet['brand_id'], $admin_id, $refund_amount, $wallet['currency'],
            $avail_before,    $avail_before    + $refund_amount,
            $reserved_before, $reserved_before - $refund_amount,
            $description, $ref_type, $ref_id,
        ]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * Record a creator payout: moves amount from reserved → total_spent.
 * Called when admin releases a payment (from admin/contest-submissions.php etc.).
 */
function record_wallet_payout(int $wallet_id, float $amount, string $ref_type, int $ref_id, string $description): bool {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM brand_wallets WHERE id = ?");
    $stmt->execute([$wallet_id]);
    $wallet = $stmt->fetch();
    if (!$wallet) return false;

    $payout_amount   = min($amount, (float)$wallet['reserved_balance']);
    if ($payout_amount <= 0) return false;

    $avail_before    = (float)$wallet['available_balance'];
    $reserved_before = (float)$wallet['reserved_balance'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            UPDATE brand_wallets
            SET reserved_balance = GREATEST(0, reserved_balance - ?),
                total_spent      = total_spent + ?,
                updated_at       = NOW()
            WHERE id = ?
        ")->execute([$payout_amount, $payout_amount, $wallet_id]);

        $pdo->prepare("
            INSERT INTO wallet_transactions
                (wallet_id, brand_id, transaction_type, amount, currency,
                 balance_before, balance_after, reserved_before, reserved_after,
                 description, reference_type, reference_id)
            VALUES (?, ?, 'creator_payout', ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $wallet_id, $wallet['brand_id'], $payout_amount, $wallet['currency'],
            $avail_before, $avail_before,
            $reserved_before, $reserved_before - $payout_amount,
            $description, $ref_type, $ref_id,
        ]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

// ── Freeze / Unfreeze ─────────────────────────────────────────

function set_wallet_status(int $wallet_id, string $status): bool {
    global $pdo;
    $allowed = ['active', 'frozen', 'closed'];
    if (!in_array($status, $allowed, true)) return false;
    return $pdo->prepare("UPDATE brand_wallets SET status = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$status, $wallet_id]);
}

// ── Query helpers ─────────────────────────────────────────────

/**
 * Get paginated transaction history for a wallet, newest first.
 */
function get_wallet_transactions(int $wallet_id, int $limit = 30, int $offset = 0): array {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT wt.*, u.email AS admin_email
        FROM wallet_transactions wt
        LEFT JOIN users u ON wt.admin_id = u.id
        WHERE wt.wallet_id = ?
        ORDER BY wt.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$wallet_id, $limit, $offset]);
    return $stmt->fetchAll();
}

/**
 * Human-readable transaction type label.
 */
function format_tx_type(string $type): string {
    static $labels = [
        'admin_credit'         => '💰 Admin Credit',
        'admin_debit'          => '📤 Admin Debit',
        'campaign_reserve'     => '📢 Campaign Reserved',
        'contest_reserve'      => '🏆 Contest Reserved',
        'ugc_order_reserve'    => '🎬 UGC Order Reserved',
        'creator_payout'       => '💸 Creator Payout',
        'refund_unused_budget' => '↩️ Budget Refunded',
        'manual_adjustment'    => '⚙️ Manual Adjustment',
    ];
    return $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
}

/**
 * CSS class for a transaction amount (green for credits, red for debits).
 */
function tx_amount_class(string $type): string {
    $positive = ['admin_credit', 'refund_unused_budget'];
    return in_array($type, $positive, true)
        ? 'text-green-700 dark:text-green-400'
        : 'text-red-700 dark:text-red-400';
}

/**
 * Return "+" or "-" prefix for a transaction type.
 */
function tx_sign(string $type): string {
    $positive = ['admin_credit', 'refund_unused_budget'];
    return in_array($type, $positive, true) ? '+' : '−';
}
