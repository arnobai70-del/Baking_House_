<?php

/**
 * Checkout concurrency worker (tests/Feature/ConcurrentCheckoutTest.php). Prepares a checkout for
 * the given cart, waits on a barrier so all workers submit at the same moment, then places the
 * order. Prints "OK <number>", "REJECTED <message>" or "ERR <class>: <message>".
 *
 * Usage: php checkout-worker.php <db-file> <cart-id> <checkout-token> <barrier-file>
 */

use App\Enums\PaymentMethod;
use App\Models\Cart;
use App\Services\Checkout\CheckoutDetails;
use App\Services\Checkout\CheckoutService;
use App\Services\Orders\OrderPlacer;
use App\Services\Pricing\PricingContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

[$script, $database, $cartId, $token, $barrier] = $argv;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config([
    'database.default' => 'concurrency',
    'database.connections.concurrency' => [
        'driver' => 'sqlite', 'database' => $database, 'foreign_key_constraints' => true, 'busy_timeout' => 20000, 'journal_mode' => 'wal',
    ],
    'mail.default' => 'array',
    'commerce.notifications.sms_enabled' => false,
]);
DB::purge('concurrency');

$cart = Cart::query()->findOrFail((int) $cartId);
$context = PricingContext::retail();
$summary = app(CheckoutService::class)->summarize($cart, $context, 'Dhaka', PaymentMethod::CashOnDelivery);
$details = new CheckoutDetails('Race Tester', '01712345678', null, 'House 1, Road 2', 'Mirpur', 'Dhaka', null, PaymentMethod::CashOnDelivery, $token, $summary['fingerprint']);

$deadline = microtime(true) + 30;
while (! file_exists($barrier) && microtime(true) < $deadline) {
    usleep(500);
}

try {
    echo 'OK '.app(OrderPlacer::class)->place($cart, $context, $details, null)->order->number;
} catch (ValidationException $e) {
    echo 'REJECTED '.implode(' ', $e->validator->errors()->all());
} catch (Throwable $e) {
    echo 'ERR '.get_class($e).': '.$e->getMessage();
}
