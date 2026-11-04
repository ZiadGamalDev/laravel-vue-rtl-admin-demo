<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A handful of sample issues so the error log page is not empty on a fresh
 * install. They are demo rows, not errors this app actually raised.
 */
class DemoErrorSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $issues = [
            ['Symfony\Component\Mailer\Exception\TransportException', 'Connection could not be established with host "smtp.example.test:587": stream_socket_client(): Connection refused', 'vendor/symfony/mailer/Transport/Smtp/Stream/SocketStream.php', 154, 27, 9 * 24 * 60, 35, null, 'POST', '/api/invoices'],
            ['Illuminate\Database\QueryException', 'SQLSTATE[HY000]: General error: 5 database is locked (Connection: sqlite, SQL: update "orders" set "total" = 154.75 where "id" = 236)', 'app/Models/Order.php', 63, 14, 6 * 24 * 60, 120, null, 'PUT', '/api/orders/236'],
            ['TypeError', 'App\Services\DashboardStats::change(): Argument #1 ($current) must be of type int|float, null given', 'app/Services/DashboardStats.php', 136, 5, 3 * 24 * 60, 5 * 60, null, 'GET', '/api/dashboard'],
            ['RuntimeException', 'خطأ تجريبي أُطلق من سجل الأخطاء.', 'app/Http/Controllers/Api/ErrorEventController.php', 61, 2, 26 * 60, 26 * 60 - 5, null, 'POST', '/api/error-events/trigger'],
            ['Illuminate\Http\Client\ConnectionException', 'cURL error 28: Operation timed out after 10001 milliseconds with 0 bytes received (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://api.example.test/rates', 'vendor/laravel/framework/src/Illuminate/Http/Client/PendingRequest.php', 1661, 9, 20 * 24 * 60, 4 * 24 * 60, 3 * 24 * 60, 'GET', '/api/dashboard'],
            ['ErrorException', 'Undefined array key "unit_price"', 'app/Http/Controllers/Api/OrderController.php', 151, 3, 12 * 24 * 60, 11 * 24 * 60, 10 * 24 * 60, 'PUT', '/api/orders/198'],
        ];

        foreach ($issues as [$class, $message, $file, $line, $count, $firstMinutesAgo, $lastMinutesAgo, $resolvedMinutesAgo, $method, $url]) {
            DB::table('error_events')->insert([
                'fingerprint' => sha1("$class|$file|$line"),
                'exception_class' => $class,
                'message' => $message,
                'file' => $file,
                'line' => $line,
                'trace' => json_encode($this->trace($class, $file, $line), JSON_UNESCAPED_SLASHES),
                'count' => $count,
                'first_seen_at' => $now->copy()->subMinutes($firstMinutesAgo),
                'last_seen_at' => $now->copy()->subMinutes($lastMinutesAgo),
                'resolved_at' => $resolvedMinutesAgo ? $now->copy()->subMinutes($resolvedMinutesAgo) : null,
                'last_method' => $method,
                'last_url' => $url,
                'created_at' => $now->copy()->subMinutes($firstMinutesAgo),
                'updated_at' => $now->copy()->subMinutes($lastMinutesAgo),
            ]);
        }
    }

    private function trace(string $class, string $file, int $line): array
    {
        return [
            ['file' => $file, 'line' => $line, 'function' => $class],
            ['file' => 'vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php', 'line' => 46, 'function' => 'Illuminate\Routing\ControllerDispatcher->dispatch'],
            ['file' => 'vendor/laravel/framework/src/Illuminate/Routing/Route.php', 'line' => 265, 'function' => 'Illuminate\Routing\Route->runController'],
            ['file' => 'vendor/laravel/framework/src/Illuminate/Routing/Router.php', 'line' => 822, 'function' => 'Illuminate\Routing\Router->Illuminate\Routing\{closure}'],
            ['file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php', 'line' => 180, 'function' => 'Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}'],
            ['file' => 'app/Http/Middleware/SetLocale.php', 'line' => 26, 'function' => 'App\Http\Middleware\SetLocale->handle'],
            ['file' => 'vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php', 'line' => 175, 'function' => 'Illuminate\Foundation\Http\Kernel->sendRequestThroughRouter'],
            ['file' => 'public/index.php', 'line' => 20, 'function' => 'Illuminate\Foundation\Application->handleRequest'],
        ];
    }
}
