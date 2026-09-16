<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SaleSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::query()->count() === 0) {
            $this->call(ProductSeeder::class);
        }

        $products = Product::query()
            ->where('is_active', true)
            ->get(['id', 'price', 'stock']);

        if ($products->isEmpty()) {
            $this->command?->warn('No products available to seed sales.');

            return;
        }

        $customers = $this->ensureCustomers();

        Schema::disableForeignKeyConstraints();
        SaleItem::query()->delete();
        Sale::query()->delete();
        Schema::enableForeignKeyConstraints();

        $paymentMethods = ['cash', 'card', 'bank_transfer'];
        $start = Carbon::now()->subYear()->startOfDay();
        $end = Carbon::now()->endOfDay();
        $refCounter = 1;

        $salesBuffer = [];
        $itemsBuffer = [];
        $now = now();

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            // Quieter on Sundays; busier weekdays/Saturdays.
            $salesToday = $day->isSunday()
                ? random_int(1, 3)
                : ($day->isSaturday() ? random_int(3, 8) : random_int(2, 7));

            for ($s = 0; $s < $salesToday; $s++) {
                $soldAt = $day->copy()->setTime(random_int(9, 21), random_int(0, 59), random_int(0, 59));
                $lineCount = random_int(1, 5);
                $picked = $products->random($lineCount);

                if ($picked instanceof Product) {
                    $picked = collect([$picked]);
                }

                $subtotal = 0;
                $lineRows = [];

                foreach ($picked as $product) {
                    $qty = random_int(1, 4);
                    $unitPrice = (float) $product->price;
                    $lineTotal = round($unitPrice * $qty, 2);
                    $subtotal += $lineTotal;

                    $lineRows[] = [
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ];
                }

                $discount = random_int(0, 100) < 25 ? round(random_int(0, 50) + (random_int(0, 99) / 100), 2) : 0;
                $tax = round($subtotal * (random_int(0, 5) / 100), 2);
                $total = max(0, round($subtotal - $discount + $tax, 2));
                $paid = $total + (random_int(0, 100) < 40 ? round(random_int(0, 100), 2) : 0);
                $change = round($paid - $total, 2);

                $tempKey = $refCounter;
                $salesBuffer[] = [
                    '_key' => $tempKey,
                    'customer_id' => random_int(0, 100) < 65 ? $customers->random()->id : null,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'tax' => $tax,
                    'total' => $total,
                    'paid_amount' => $paid,
                    'change_amount' => $change,
                    'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                    'reference' => sprintf('INV-%s-%05d', $soldAt->format('Ymd'), $refCounter),
                    'sold_at' => $soldAt->toDateTimeString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                    '_lines' => $lineRows,
                ];

                $refCounter++;

                if (count($salesBuffer) >= 100) {
                    $this->flushSales($salesBuffer, $itemsBuffer);
                    $salesBuffer = [];
                    $itemsBuffer = [];
                }
            }
        }

        if ($salesBuffer !== []) {
            $this->flushSales($salesBuffer, $itemsBuffer);
        }

        $this->command?->info('Seeded one year of sales ('.($refCounter - 1).' invoices).');
    }

    /**
     * @param  list<array<string, mixed>>  $salesBuffer
     * @param  list<array<string, mixed>>  $itemsBuffer
     */
    private function flushSales(array &$salesBuffer, array &$itemsBuffer): void
    {
        DB::transaction(function () use (&$salesBuffer, &$itemsBuffer): void {
            $now = now();

            foreach ($salesBuffer as $saleData) {
                $lines = $saleData['_lines'];
                unset($saleData['_key'], $saleData['_lines']);

                $saleId = DB::table('sales')->insertGetId($saleData);

                foreach ($lines as $line) {
                    $itemsBuffer[] = [
                        'sale_id' => $saleId,
                        'product_id' => $line['product_id'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'line_total' => $line['line_total'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($itemsBuffer, 500) as $chunk) {
                DB::table('sale_items')->insert($chunk);
            }

            $itemsBuffer = [];
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, Customer>
     */
    private function ensureCustomers()
    {
        if (Customer::query()->count() === 0) {
            $names = [
                'Ahmed Khan', 'Fatima Ali', 'Hassan Raza', 'Ayesha Siddiqui', 'Bilal Ahmed',
                'Sana Malik', 'Usman Tariq', 'Zainab Iqbal', 'Imran Shah', 'Maryam Noor',
                'Omar Farooq', 'Hina Bashir', 'Saad Javed', 'Nida Rahman', 'Kashif Mehmood',
            ];

            $now = now();
            $rows = [];
            foreach ($names as $i => $name) {
                $rows[] = [
                    'name' => $name,
                    'phone' => '03'.str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
                    'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
                    'address' => 'Medical Store Customer #'.($i + 1),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            Customer::query()->insert($rows);
        }

        return Customer::query()->get();
    }
}
