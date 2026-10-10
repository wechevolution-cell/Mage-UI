<?php
declare(strict_types=1);

/**
 * Payment abstraction: Provider → Payment → Subscription → Shop.
 * V1 ships the manual provider (Super Admin marks payments paid). Stripe / Viva Wallet / bank transfer
 * implement PaymentProvider and are registered in payment_providers — no core rewrite needed.
 */
interface PaymentProvider
{
    public function code(): string;
    public function name(): string;
    /** Start a payment for a subscription renewal; returns ['redirect'=>url] or ['status'=>...] */
    public function createPayment(array $payment, array $shop): array;
    /** Verify an incoming webhook; returns normalized ['payment_id'=>..., 'status'=>'paid|failed'] or null */
    public function handleWebhook(array $headers, string $body): ?array;
}

final class ManualPaymentProvider implements PaymentProvider
{
    public function code(): string { return 'manual'; }
    public function name(): string { return 'Χειροκίνητη (Super Admin)'; }
    public function createPayment(array $payment, array $shop): array { return ['status' => 'pending']; }
    public function handleWebhook(array $headers, string $body): ?array { return null; }
}

final class Payments
{
    /** @var array<string,PaymentProvider> */
    private static array $providers = [];

    public static function register(PaymentProvider $p): void { self::$providers[$p->code()] = $p; }

    public static function provider(string $code): ?PaymentProvider
    {
        if (!self::$providers) self::register(new ManualPaymentProvider());
        return self::$providers[$code] ?? null;
    }

    public static function seedProviders(): void
    {
        $p = DB::platform();
        foreach ([['manual', 'Χειροκίνητη', 1], ['bank_transfer', 'Τραπεζική κατάθεση', 0], ['stripe', 'Stripe', 0], ['viva', 'Viva Wallet', 0]] as [$c, $n, $en]) {
            if (!db_val($p, 'SELECT 1 FROM payment_providers WHERE code = ?', [$c])) {
                db_insert($p, 'payment_providers', ['code' => $c, 'name' => $n, 'enabled' => $en, 'config' => '{}', 'created_at' => now()]);
            }
        }
    }

    public static function markPaid(int $paymentId, string $reference = ''): void
    {
        $p = DB::platform();
        $pay = db_one($p, 'SELECT * FROM payments WHERE id = ?', [$paymentId]);
        if (!$pay || $pay['status'] === 'paid') return;
        db_update($p, 'payments', ['status' => 'paid', 'paid_at' => now(), 'reference' => $reference ?: $pay['reference']], 'id = ?', [$paymentId]);
        Audit::log('payment.paid', (int) $pay['shop_id'], 'payment', $paymentId, ['amount' => $pay['amount']]);
    }
}
