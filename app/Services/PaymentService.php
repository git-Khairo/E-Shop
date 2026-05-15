<?php

namespace App\Services;

use App\Exceptions\PaymentFailedException;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

/**
 * PaymentService — either real Stripe or a deterministic mock used in tests
 * and load simulations. The interface stays the same.
 *
 * Note: payment is the ONE step that must not be retried blindly inside a DB
 * transaction (network calls + idempotency). The OrderService therefore:
 *   1) opens a transaction,
 *   2) reserves stock + creates order rows,
 *   3) COMMITS,
 *   4) then calls the payment provider,
 *   5) on failure, compensates by incrementing stock back + marks order failed.
 */
class PaymentService
{
    public function __construct(
        private readonly string $driver = 'mock',
        private readonly ?string $stripeSecret = null,
    ) {}

    public function charge(Order $order, string $paymentMethodToken): Payment
    {
        return match ($this->driver) {
            'stripe' => $this->chargeStripe($order, $paymentMethodToken),
            'wallet' => $this->chargeWallet($order),
            default  => $this->chargeMock($order, $paymentMethodToken),
        };
    }

    /**
     * Charge via internal wallet — uses WalletService with optimistic locking.
     * Concurrency-safe: the debit operation uses versioned UPDATE to prevent double-spend.
     */
    private function chargeWallet(Order $order): Payment
    {
        /** @var WalletService $walletService */
        $walletService = App::make(WalletService::class);

        try {
            $tx = $walletService->debit(
                $order->user_id,
                (float) $order->total,
                "Payment for order {$order->reference}",
                $order->reference,
                ['order_id' => $order->id],
            );

            return Payment::create([
                'order_id'           => $order->id,
                'provider'           => 'wallet',
                'provider_reference' => "wallet_tx_{$tx->id}",
                'amount'             => $order->total,
                'currency'           => 'USD',
                'status'             => 'succeeded',
                'meta'               => [
                    'wallet_transaction_id' => $tx->id,
                    'balance_after'         => $tx->balance_after,
                ],
            ]);
        } catch (\RuntimeException $e) {
            Payment::create([
                'order_id'           => $order->id,
                'provider'           => 'wallet',
                'provider_reference' => null,
                'amount'             => $order->total,
                'currency'           => 'USD',
                'status'             => 'failed',
                'meta'               => ['error' => $e->getMessage()],
            ]);

            throw new PaymentFailedException("Wallet payment failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Deterministic mock used for load testing. Tokens:
     *   "mock_success" -> success
     *   "mock_fail"    -> failure
     *   "mock_random"  -> 95% success rate
     */
    private function chargeMock(Order $order, string $token): Payment
    {
        $succeed = match ($token) {
            'mock_fail'   => false,
            'mock_random' => random_int(1, 100) <= 95,
            default       => true,
        };

        $payment = Payment::create([
            'order_id'           => $order->id,
            'provider'           => 'mock',
            'provider_reference' => 'mock_'.bin2hex(random_bytes(8)),
            'amount'             => $order->total,
            'currency'           => 'USD',
            'status'             => $succeed ? 'succeeded' : 'failed',
            'meta'               => ['token' => $token],
        ]);

        if (! $succeed) {
            throw new PaymentFailedException('Mock payment declined.');
        }

        return $payment;
    }

    private function chargeStripe(Order $order, string $paymentMethodToken): Payment
    {
        $stripe = new StripeClient($this->stripeSecret);

        try {
            $intent = $stripe->paymentIntents->create([
                'amount'              => (int) round(((float) $order->total) * 100),
                'currency'            => 'usd',
                'payment_method'      => $paymentMethodToken,
                'confirm'             => true,
                'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
                'metadata'            => ['order_id' => $order->id, 'reference' => $order->reference],
            ]);
        } catch (ApiErrorException $e) {
            Log::warning('Stripe payment failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);

            Payment::create([
                'order_id'           => $order->id,
                'provider'           => 'stripe',
                'provider_reference' => null,
                'amount'             => $order->total,
                'currency'           => 'USD',
                'status'             => 'failed',
                'meta'               => ['error' => $e->getMessage()],
            ]);

            throw new PaymentFailedException($e->getMessage(), 0, $e);
        }

        $status = $intent->status === PaymentIntent::STATUS_SUCCEEDED ? 'succeeded' : 'pending';

        return Payment::create([
            'order_id'           => $order->id,
            'provider'           => 'stripe',
            'provider_reference' => $intent->id,
            'amount'             => $order->total,
            'currency'           => 'USD',
            'status'             => $status,
            'meta'               => $intent->toArray(),
        ]);
    }
}
