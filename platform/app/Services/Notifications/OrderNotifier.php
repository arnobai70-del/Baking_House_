<?php

namespace App\Services\Notifications;

use App\Enums\OrderStatus;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Support\Taka;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Customer order notifications over the configured SMS driver and email. Failures are logged,
 * never thrown: a notification problem must not break checkout or order processing.
 */
class OrderNotifier
{
    public function __construct(private SmsGateway $sms) {}

    public function orderPlaced(Order $order, ?string $guestKey = null): void
    {
        $link = route('orders.show', ['order' => $order->number] + ($guestKey !== null ? ['key' => $guestKey] : []));

        $this->send($order, 'order_placed', sprintf(
            'Baking House: order %s received. Total %s (%s). Track: %s',
            $order->number, Taka::format($order->total_paisa), $order->payment_method->label(), $link,
        ));
    }

    public function paymentReceived(Order $order): void
    {
        $this->send($order, 'payment_received', sprintf('Baking House: payment received for order %s. Thank you!', $order->number));
    }

    public function statusChanged(Order $order, OrderStatus $status): void
    {
        if (! in_array($status, [OrderStatus::Confirmed, OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Cancelled], true)) {
            return;
        }

        $this->send($order, 'status_'.$status->value, sprintf('Baking House: order %s is now %s.', $order->number, strtolower($status->label())));
    }

    public function refundRecorded(Order $order, int $amountPaisa): void
    {
        $this->send($order, 'refund_recorded', sprintf('Baking House: a refund of %s for order %s has been recorded.', Taka::format($amountPaisa), $order->number));
    }

    public static function mask(string $value): string
    {
        if (str_contains($value, '@')) {
            [$local, $domain] = explode('@', $value, 2);

            return substr($local, 0, 1).str_repeat('*', max(1, strlen($local) - 1)).'@'.$domain;
        }

        return strlen($value) > 5 ? substr($value, 0, 3).str_repeat('*', strlen($value) - 5).substr($value, -2) : '***';
    }

    private function send(Order $order, string $template, string $message): void
    {
        if (config('commerce.notifications.sms_enabled')) {
            $this->attempt($order, 'sms', $this->sms->name(), $template, $order->customer_phone, fn () => $this->sms->send($order->customer_phone, $message));
        }

        if (config('commerce.notifications.email_enabled') && $order->customer_email) {
            $this->attempt($order, 'email', (string) config('mail.default'), $template, $order->customer_email, fn () => Mail::raw($message, function ($mail) use ($order): void {
                $mail->to($order->customer_email)->subject('Baking House order '.$order->number);
            }));
        }
    }

    private function attempt(Order $order, string $channel, string $driver, string $template, string $recipient, callable $send): void
    {
        $status = 'sent';
        $error = null;

        try {
            $send();
        } catch (Throwable $e) {
            $status = 'failed';
            $error = substr($e->getMessage(), 0, 500);
            Log::warning("Order notification failed ({$channel}/{$template}) for {$order->number}: {$error}");
        }

        NotificationLog::query()->forceCreate([
            'order_id' => $order->id,
            'channel' => $channel,
            'driver' => $driver,
            'template' => $template,
            'recipient_masked' => self::mask($recipient),
            'status' => $status,
            'error' => $error,
        ]);
    }
}
