<?php

namespace Database\Seeders;

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            // 1. ASET
            [
                'code' => '1-10000',
                'name' => 'Kas & Bank',
                'type' => AccountType::Asset,
                'category' => AccountCategory::CashAndBank,
                'is_system' => true,
                'children' => [],
            ],
            [
                'code' => '1-10100',
                'name' => 'Piutang',
                'type' => AccountType::Asset,
                'category' => AccountCategory::AccountsReceivable,
                'is_system' => true,
                'children' => [
                    ['code' => '1-10101', 'name' => 'Piutang Pribadi / Pinjaman Teman', 'type' => AccountType::Asset, 'category' => AccountCategory::AccountsReceivable],
                ],
            ],
            [
                'code' => '1-10200',
                'name' => 'Aset Lancar Lainnya',
                'type' => AccountType::Asset,
                'category' => AccountCategory::OtherCurrentAsset,
                'is_system' => true,
                'children' => [
                    ['code' => '1-10201', 'name' => 'Investasi & Tabungan Berjangka', 'type' => AccountType::Asset, 'category' => AccountCategory::OtherCurrentAsset],
                ],
            ],
            [
                'code' => '1-20000',
                'name' => 'Aset Tetap',
                'type' => AccountType::Asset,
                'category' => AccountCategory::FixedAsset,
                'is_system' => true,
                'children' => [
                    ['code' => '1-20001', 'name' => 'Peralatan Elektronik & Gadget', 'type' => AccountType::Asset, 'category' => AccountCategory::FixedAsset],
                    ['code' => '1-20002', 'name' => 'Kendaraan', 'type' => AccountType::Asset, 'category' => AccountCategory::FixedAsset],
                ],
            ],

            // 2. KEWAJIBAN / HUTANG
            [
                'code' => '2-10000',
                'name' => 'Kewajiban Lancar',
                'type' => AccountType::Liability,
                'category' => AccountCategory::OtherCurrentLiability,
                'is_system' => true,
                'children' => [
                    ['code' => '2-10001', 'name' => 'Hutang Kartu Kredit', 'type' => AccountType::Liability, 'category' => AccountCategory::CreditCard],
                    ['code' => '2-10002', 'name' => 'Paylater (Shopee / GoPay)', 'type' => AccountType::Liability, 'category' => AccountCategory::CreditCard],
                    ['code' => '2-10003', 'name' => 'Hutang Pribadi / Pinjaman', 'type' => AccountType::Liability, 'category' => AccountCategory::AccountsPayable],
                ],
            ],
            [
                'code' => '2-20000',
                'name' => 'Kewajiban Jangka Panjang',
                'type' => AccountType::Liability,
                'category' => AccountCategory::LongTermLiability,
                'is_system' => true,
                'children' => [
                    ['code' => '2-20001', 'name' => 'Cicilan Kendaraan / Rumah', 'type' => AccountType::Liability, 'category' => AccountCategory::LongTermLiability],
                ],
            ],

            // 3. EKUITAS
            [
                'code' => '3-10000',
                'name' => 'Ekuitas Pemilik',
                'type' => AccountType::Equity,
                'category' => AccountCategory::Equity,
                'is_system' => true,
                'children' => [
                    ['code' => '3-10001', 'name' => 'Modal Awal', 'type' => AccountType::Equity, 'category' => AccountCategory::Equity],
                    ['code' => '3-20001', 'name' => 'Laba Ditahan', 'type' => AccountType::Equity, 'category' => AccountCategory::RetainedEarnings, 'is_system' => true],
                ],
            ],

            // 4. PENDAPATAN
            [
                'code' => '4-10000',
                'name' => 'Pendapatan Utama',
                'type' => AccountType::Revenue,
                'category' => AccountCategory::OperatingRevenue,
                'is_system' => true,
                'children' => [
                    ['code' => '4-10001', 'name' => 'Gaji & Tunjangan', 'type' => AccountType::Revenue, 'category' => AccountCategory::OperatingRevenue],
                    ['code' => '4-10002', 'name' => 'Pendapatan Freelance / Proyek', 'type' => AccountType::Revenue, 'category' => AccountCategory::OperatingRevenue],
                    ['code' => '4-10003', 'name' => 'Hasil Penjualan / Usaha', 'type' => AccountType::Revenue, 'category' => AccountCategory::OperatingRevenue],
                ],
            ],
            [
                'code' => '4-20000',
                'name' => 'Pendapatan Lainnya',
                'type' => AccountType::Revenue,
                'category' => AccountCategory::OtherRevenue,
                'is_system' => true,
                'children' => [
                    ['code' => '4-20001', 'name' => 'Bunga Bank & Imbal Hasil Investasi', 'type' => AccountType::Revenue, 'category' => AccountCategory::OtherRevenue],
                    ['code' => '4-20002', 'name' => 'Cashback, Hadiah & Bonus', 'type' => AccountType::Revenue, 'category' => AccountCategory::OtherRevenue],
                ],
            ],

            // 6. BEBAN
            [
                'code' => '6-10000',
                'name' => 'Beban Kebutuhan Pokok & Operasional',
                'type' => AccountType::Expense,
                'category' => AccountCategory::OperatingExpense,
                'is_system' => true,
                'children' => [
                    ['code' => '6-10001', 'name' => 'Makanan & Minuman (Harian)', 'type' => AccountType::Expense, 'category' => AccountCategory::OperatingExpense],
                    ['code' => '6-10002', 'name' => 'Belanja Dapur & Groceries', 'type' => AccountType::Expense, 'category' => AccountCategory::OperatingExpense],
                    ['code' => '6-10003', 'name' => 'Transportasi & Bensin', 'type' => AccountType::Expense, 'category' => AccountCategory::OperatingExpense],
                    ['code' => '6-10004', 'name' => 'Tempat Tinggal, Kos & Sewa', 'type' => AccountType::Expense, 'category' => AccountCategory::OperatingExpense],
                    ['code' => '6-10005', 'name' => 'Listrik, Air & Internet', 'type' => AccountType::Expense, 'category' => AccountCategory::OperatingExpense],
                    ['code' => '6-10006', 'name' => 'Pulsa & Paket Data', 'type' => AccountType::Expense, 'category' => AccountCategory::OperatingExpense],
                    ['code' => '6-10007', 'name' => 'Kesehatan, Medis & Obat', 'type' => AccountType::Expense, 'category' => AccountCategory::OperatingExpense],
                    ['code' => '6-10008', 'name' => 'Pakaian & Perlengkapan Diri', 'type' => AccountType::Expense, 'category' => AccountCategory::OperatingExpense],
                    ['code' => '6-10009', 'name' => 'Pendidikan & Kursus', 'type' => AccountType::Expense, 'category' => AccountCategory::OperatingExpense],
                ],
            ],
            [
                'code' => '6-20000',
                'name' => 'Beban Hiburan & Gaya Hidup',
                'type' => AccountType::Expense,
                'category' => AccountCategory::OperatingExpense,
                'is_system' => true,
                'children' => [
                    ['code' => '6-20001', 'name' => 'Kafe, Resto & Nongkrong', 'type' => AccountType::Expense, 'category' => AccountCategory::OperatingExpense],
                    ['code' => '6-20002', 'name' => 'Langganan Digital (Streaming / Cloud / AI)', 'type' => AccountType::Expense, 'category' => AccountCategory::OperatingExpense],
                    ['code' => '6-20003', 'name' => 'Hiburan, Bioskop & Game', 'type' => AccountType::Expense, 'category' => AccountCategory::OperatingExpense],
                    ['code' => '6-20004', 'name' => 'Liburan & Traveling', 'type' => AccountType::Expense, 'category' => AccountCategory::OperatingExpense],
                ],
            ],
            [
                'code' => '6-30000',
                'name' => 'Beban Lain-lain & Finansial',
                'type' => AccountType::Expense,
                'category' => AccountCategory::OtherExpense,
                'is_system' => true,
                'children' => [
                    ['code' => '6-30001', 'name' => 'Donasi, Zakat & Sedekah', 'type' => AccountType::Expense, 'category' => AccountCategory::OtherExpense],
                    ['code' => '6-30002', 'name' => 'Biaya Admin Bank & Transfer', 'type' => AccountType::Expense, 'category' => AccountCategory::OtherExpense],
                    ['code' => '6-30003', 'name' => 'Bunga Pinjaman & Biaya Finansial', 'type' => AccountType::Expense, 'category' => AccountCategory::OtherExpense],
                    ['code' => '6-30004', 'name' => 'Beban Lainnya Tak Terduga', 'type' => AccountType::Expense, 'category' => AccountCategory::OtherExpense],
                ],
            ],
        ];

        foreach ($accounts as $parentData) {
            $children = $parentData['children'] ?? [];
            unset($parentData['children']);

            $parent = Account::updateOrCreate(
                ['code' => $parentData['code']],
                $parentData
            );

            foreach ($children as $childData) {
                $childData['parent_id'] = $parent->id;
                Account::updateOrCreate(
                    ['code' => $childData['code']],
                    $childData
                );
            }
        }
    }
}
