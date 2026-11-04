<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Support\ArabicText;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Fake shop data. A fixed random seed makes every run produce the same rows;
 * dates are relative to "today" (or APP_DEMO_DATE when set).
 */
class DemoDataSeeder extends Seeder
{
    private const FIRST_NAMES = [
        ['أحمد', 'ahmed'], ['محمد', 'mohamed'], ['سارة', 'sara'], ['ليلى', 'layla'], ['يوسف', 'youssef'],
        ['مريم', 'mariam'], ['عمر', 'omar'], ['نور', 'nour'], ['خالد', 'khaled'], ['هدى', 'huda'],
        ['كريم', 'karim'], ['سلمى', 'salma'], ['طارق', 'tarek'], ['رنا', 'rana'], ['حسن', 'hassan'],
        ['دينا', 'dina'], ['مازن', 'mazen'], ['ياسمين', 'yasmin'], ['فادي', 'fadi'], ['جنى', 'jana'],
    ];

    private const LAST_NAMES = [
        ['السيد', 'elsayed'], ['النجار', 'alnajjar'], ['الحداد', 'alhaddad'], ['الشريف', 'alsharif'],
        ['العلي', 'alali'], ['حسين', 'hussein'], ['منصور', 'mansour'], ['عبدالله', 'abdullah'],
        ['الخطيب', 'alkhatib'], ['سليمان', 'suleiman'], ['يوسف', 'youssef'], ['الأحمد', 'alahmad'],
    ];

    private const CITIES = ['القاهرة', 'الرياض', 'دبي', 'عمّان', 'الدار البيضاء', 'تونس', 'الدوحة', 'مسقط', 'بيروت', 'الكويت'];

    /** [name, unit price] */
    private const PRODUCTS = [
        ['قهوة عربية ٥٠٠ جم', 18.50], ['شاي أخضر عضوي', 9.75], ['تمر مجدول ١ كجم', 24.00], ['زيت زيتون بكر', 15.25],
        ['عسل طبيعي', 21.00], ['كوب سيراميك', 7.50], ['حقيبة قماشية', 12.00], ['دفتر ملاحظات', 4.25],
        ['شمعة معطّرة', 11.00], ['صابون طبيعي', 5.50], ['سماعات لاسلكية', 59.00], ['شاحن سريع', 19.99],
        ['مصباح مكتب', 34.00], ['قارورة ماء حرارية', 16.75], ['ساعة حائط', 27.50], ['طقم أكواب', 22.00],
    ];

    private Randomizer $random;

    /** @var array<int, Carbon> customer id => sign-up day */
    private array $customerSince = [];

    public function run(): void
    {
        $this->random = new Randomizer(new Mt19937(2026));
        $today = Carbon::today();

        DB::transaction(function () use ($today) {
            $customerIds = $this->seedCustomers($today);
            $this->seedOrders($customerIds, $today);
        });
    }

    /** @return list<int> */
    private function seedCustomers(Carbon $today): array
    {
        $ids = [];
        $this->customerSince = [];
        for ($i = 0; $i < 42; $i++) {
            [$first, $firstLatin] = self::FIRST_NAMES[$i % count(self::FIRST_NAMES)];
            [$last, $lastLatin] = self::LAST_NAMES[($i * 7 + intdiv($i, 20)) % count(self::LAST_NAMES)];
            // Spread sign-ups over the last ~4 months, a few of them inside the last 30 days.
            $created = $today->copy()->subDays(max(1, 125 - $i * 3))->setTime(10, 0);

            $name = "$first $last";
            $email = sprintf('%s.%s%02d@example.com', $firstLatin, $lastLatin, $i + 1);
            $phone = sprintf('555-01%02d', $i);
            $city = self::CITIES[$this->random->getInt(0, count(self::CITIES) - 1)];

            $ids[] = $id = DB::table('customers')->insertGetId([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'city' => $city,
                'search_text' => ArabicText::customerSearchText($name, $email, $phone, $city),
                'notes' => $i % 9 === 0 ? 'عميل دائم، يفضّل التواصل عبر البريد.' : null,
                'created_at' => $created,
                'updated_at' => $created,
            ]);
            $this->customerSince[$id] = $created->copy()->startOfDay();
        }

        return $ids;
    }

    /** @param list<int> $customerIds */
    private function seedOrders(array $customerIds, Carbon $today): void
    {
        $orderNumber = 10000;
        $invoiceNumber = 1000;

        // 90 days of history; recent days are busier so the chart trends upward.
        for ($daysAgo = 89; $daysAgo >= 0; $daysAgo--) {
            $date = $today->copy()->subDays($daysAgo);
            $base = $daysAgo < 30 ? 2 : 1;
            $count = $base + $this->random->getInt(0, 2) + ($date->isWeekend() ? 1 : 0);

            for ($n = 0; $n < $count; $n++) {
                // Only customers who had signed up by that day can place an order.
                $eligible = array_values(array_filter($customerIds, fn (int $id) => $this->customerSince[$id]->lessThanOrEqualTo($date)));
                $eligible = $eligible ?: array_slice($customerIds, 0, 5);
                $customerId = $eligible[$this->random->getInt(0, count($eligible) - 1)];
                $status = $this->orderStatus($daysAgo);
                $createdAt = $date->copy()->setTime(9 + $n * 2, 15);

                $orderId = DB::table('orders')->insertGetId([
                    'number' => sprintf('ORD-%05d', ++$orderNumber),
                    'customer_id' => $customerId,
                    'status' => $status->value,
                    'ordered_at' => $date->toDateString(),
                    'total' => 0,
                    'notes' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $total = 0.0;
                $itemCount = $this->random->getInt(1, 4);
                $picked = $this->random->pickArrayKeys(self::PRODUCTS, $itemCount);
                foreach ($picked as $key) {
                    [$product, $price] = self::PRODUCTS[$key];
                    $quantity = $this->random->getInt(1, 5);
                    $line = round($quantity * $price, 2);
                    $total += $line;
                    DB::table('order_items')->insert([
                        'order_id' => $orderId,
                        'product_name' => $product,
                        'quantity' => $quantity,
                        'unit_price' => $price,
                        'line_total' => $line,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }
                DB::table('orders')->where('id', $orderId)->update(['total' => round($total, 2)]);

                if ($status === OrderStatus::Cancelled || $this->random->getInt(1, 10) > 8) {
                    continue;
                }

                $issued = $date->copy()->addDay();
                if ($issued->greaterThan($today)) {
                    $issued = $today->copy();
                }
                $due = $issued->copy()->addDays(14);

                DB::table('invoices')->insert([
                    'number' => sprintf('INV-%05d', ++$invoiceNumber),
                    'order_id' => $orderId,
                    'customer_id' => $customerId,
                    'status' => $this->invoiceStatus($due, $today)->value,
                    'amount' => round($total, 2),
                    'issued_at' => $issued->toDateString(),
                    'due_at' => $due->toDateString(),
                    'notes' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }
    }

    private function orderStatus(int $daysAgo): OrderStatus
    {
        $roll = $this->random->getInt(1, 100);

        if ($roll <= 6) {
            return OrderStatus::Cancelled;
        }
        if ($daysAgo <= 2) {
            return $roll <= 60 ? OrderStatus::Pending : OrderStatus::Processing;
        }
        if ($daysAgo <= 7) {
            return $roll <= 50 ? OrderStatus::Processing : OrderStatus::Completed;
        }

        return OrderStatus::Completed;
    }

    private function invoiceStatus(Carbon $due, Carbon $today): InvoiceStatus
    {
        if ($due->greaterThanOrEqualTo($today)) {
            return $this->random->getInt(1, 3) === 1 ? InvoiceStatus::Paid : InvoiceStatus::Draft;
        }

        return $this->random->getInt(1, 100) <= 85 ? InvoiceStatus::Paid : InvoiceStatus::Overdue;
    }
}
