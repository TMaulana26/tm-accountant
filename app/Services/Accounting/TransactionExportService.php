<?php

namespace App\Services\Accounting;

use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class TransactionExportService
{
    /**
     * Generate Excel (.xlsx) file from queryTransactions result.
     *
     * @param  array{
     *     period_label: string,
     *     start_date: string,
     *     end_date: string,
     *     total_count: int,
     *     total_amount: float,
     *     total_expense: float,
     *     total_income: float,
     *     transactions: array<int, array{
     *         id: int,
     *         entry_number: string,
     *         date: string,
     *         raw_date: Carbon,
     *         description: string,
     *         amount: float,
     *         type: string,
     *         wallet_name: string,
     *         category_name: string
     *     }>,
     *     wallet_account: ?Account,
     *     wallet_balance: ?float
     * }  $queryResult
     * @return array{file_path: string, filename: string}
     */
    public function exportToExcel(array $queryResult, ?string $reportTitle = null): array
    {
        $exportDir = storage_path('app/exports');
        if (! is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $walletAccount = $queryResult['wallet_account'] ?? null;
        $walletName = $walletAccount?->name ?? 'Semua Dompet';
        $periodLabel = $queryResult['period_label'] ?? 'Semua Periode';
        $transactions = $queryResult['transactions'] ?? [];
        $hasRunningBalance = ($walletAccount !== null);

        $slugName = Str::slug($walletAccount ? $walletAccount->name : 'semua_transaksi');
        $filename = "mutasi_{$slugName}_".now()->format('Ymd_His').'.xlsx';
        $filePath = $exportDir.DIRECTORY_SEPARATOR.$filename;

        $options = new Options;

        // Configure column widths (1-indexed)
        $options->setColumnWidth(6, 1);   // No
        $options->setColumnWidth(14, 2);  // Tanggal
        $options->setColumnWidth(20, 3);  // No. Jurnal
        $options->setColumnWidth(34, 4);  // Keterangan
        $options->setColumnWidth(26, 5);  // Kategori Akun
        $options->setColumnWidth(22, 6);  // Dompet / Rekening
        $options->setColumnWidth(18, 7);  // Pemasukan (Rp)
        $options->setColumnWidth(18, 8);  // Pengeluaran (Rp)
        if ($hasRunningBalance) {
            $options->setColumnWidth(20, 9); // Saldo Berjalan (Rp)
        }

        $writer = new Writer($options);
        $writer->openToFile($filePath);

        // --- Styles ---
        $titleStyle = (new Style)
            ->setFontBold()
            ->setFontSize(14)
            ->setFontColor('0F172A'); // Slate 900

        $metaStyle = (new Style)
            ->setFontSize(10)
            ->setFontColor('475569'); // Slate 600

        $headerStyle = (new Style)
            ->setFontBold()
            ->setFontSize(10)
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor('1E293B') // Navy Dark Slate
            ->setCellAlignment(CellAlignment::CENTER);

        $dataCenterStyle = (new Style)
            ->setFontSize(10)
            ->setCellAlignment(CellAlignment::CENTER);

        $dataLeftStyle = (new Style)
            ->setFontSize(10)
            ->setCellAlignment(CellAlignment::LEFT);

        $dataNumberStyle = (new Style)
            ->setFontSize(10)
            ->setFormat('#,##0')
            ->setCellAlignment(CellAlignment::RIGHT);

        $zebraNumberStyle = (new Style)
            ->setFontSize(10)
            ->setBackgroundColor('F8FAFC')
            ->setFormat('#,##0')
            ->setCellAlignment(CellAlignment::RIGHT);

        $zebraCenterStyle = (new Style)
            ->setFontSize(10)
            ->setBackgroundColor('F8FAFC')
            ->setCellAlignment(CellAlignment::CENTER);

        $zebraLeftStyle = (new Style)
            ->setFontSize(10)
            ->setBackgroundColor('F8FAFC')
            ->setCellAlignment(CellAlignment::LEFT);

        $totalLabelStyle = (new Style)
            ->setFontBold()
            ->setFontSize(11)
            ->setBackgroundColor('E2E8F0') // Slate 200
            ->setFontColor('0F172A')
            ->setCellAlignment(CellAlignment::RIGHT);

        $totalNumberStyle = (new Style)
            ->setFontBold()
            ->setFontSize(11)
            ->setBackgroundColor('E2E8F0')
            ->setFontColor('0F172A')
            ->setFormat('#,##0')
            ->setCellAlignment(CellAlignment::RIGHT);

        // --- 1. Title & Metadata Header ---
        $docTitle = $reportTitle ?: 'BUKU MUTASI KEUANGAN - TM ACCOUNTANT';
        $writer->addRow(Row::fromValues([$docTitle], $titleStyle));
        $writer->addRow(Row::fromValues(["Akun / Dompet: {$walletName}"], $metaStyle));
        $writer->addRow(Row::fromValues(["Periode: {$periodLabel}"], $metaStyle));
        $writer->addRow(Row::fromValues(['Waktu Cetak: '.now()->translatedFormat('l, d F Y H:i:s').' WIB'], $metaStyle));
        $writer->addRow(Row::fromValues([''])); // Blank row

        // --- 2. Table Column Headers ---
        $headers = [
            'No',
            'Tanggal',
            'No. Jurnal',
            'Keterangan Transaksi',
            'Kategori Akun',
            'Dompet / Rekening',
            'Pemasukan (Rp)',
            'Pengeluaran (Rp)',
        ];

        if ($hasRunningBalance) {
            $headers[] = 'Saldo Berjalan (Rp)';
        }

        $writer->addRow(Row::fromValues($headers, $headerStyle));

        // --- 3. Compute Opening Balance for Running Balance ---
        $runningBalance = 0.0;
        if ($hasRunningBalance && ! empty($transactions)) {
            $firstTx = $transactions[0];
            $firstDate = Carbon::parse($firstTx['raw_date'])->format('Y-m-d');
            $firstId = $firstTx['id'];

            $prevTotals = $walletAccount->journalItems()
                ->whereHas('journalEntry', function ($q) use ($firstDate, $firstId) {
                    $q->where(function ($sub) use ($firstDate, $firstId) {
                        $sub->whereDate('date', '<', $firstDate)
                            ->orWhere(function ($sub2) use ($firstDate, $firstId) {
                                $sub2->whereDate('date', '=', $firstDate)
                                    ->where('id', '<', $firstId);
                            });
                    });
                })
                ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
                ->first();

            $runningBalance = (float) (($prevTotals->total_debit ?? 0) - ($prevTotals->total_credit ?? 0));

            // Write Saldo Awal row
            $openingCells = [
                Cell::fromValue('-', $dataCenterStyle),
                Cell::fromValue($firstTx['date'], $dataCenterStyle),
                Cell::fromValue('-', $dataCenterStyle),
                Cell::fromValue('[SALDO AWAL SEBELUM PERIODE INI]', (new Style)->setFontItalic()->setFontSize(10)),
                Cell::fromValue('Saldo Kas', $dataLeftStyle),
                Cell::fromValue($walletAccount->name, $dataLeftStyle),
                Cell::fromValue(0, $dataNumberStyle),
                Cell::fromValue(0, $dataNumberStyle),
                Cell::fromValue($runningBalance, $dataNumberStyle),
            ];
            $writer->addRow(new Row($openingCells));
        }

        // --- 4. Populate Data Rows ---
        $rowNumber = 1;
        $sumIncome = 0.0;
        $sumExpense = 0.0;

        foreach ($transactions as $index => $item) {
            $isZebra = ($index % 2 === 1);
            $cStyle = $isZebra ? $zebraCenterStyle : $dataCenterStyle;
            $lStyle = $isZebra ? $zebraLeftStyle : $dataLeftStyle;
            $nStyle = $isZebra ? $zebraNumberStyle : $dataNumberStyle;

            $income = 0.0;
            $expense = 0.0;

            if ($item['type'] === 'income') {
                $income = (float) $item['amount'];
                $sumIncome += $income;
                if ($hasRunningBalance) {
                    $runningBalance += $income;
                }
            } elseif ($item['type'] === 'expense') {
                $expense = (float) $item['amount'];
                $sumExpense += $expense;
                if ($hasRunningBalance) {
                    $runningBalance -= $expense;
                }
            } elseif ($item['type'] === 'transfer') {
                // Determine direction relative to filtered wallet
                if ($hasRunningBalance) {
                    $isOut = str_starts_with($item['wallet_name'], $walletAccount->name);
                    if ($isOut) {
                        $expense = (float) $item['amount'];
                        $sumExpense += $expense;
                        $runningBalance -= $expense;
                    } else {
                        $income = (float) $item['amount'];
                        $sumIncome += $income;
                        $runningBalance += $income;
                    }
                } else {
                    $expense = (float) $item['amount'];
                    $sumExpense += $expense;
                }
            }

            $cells = [
                Cell::fromValue($rowNumber, $cStyle),
                Cell::fromValue($item['date'], $cStyle),
                Cell::fromValue($item['entry_number'], $cStyle),
                Cell::fromValue($item['description'], $lStyle),
                Cell::fromValue($item['category_name'], $lStyle),
                Cell::fromValue($item['wallet_name'], $lStyle),
                Cell::fromValue($income, $nStyle),
                Cell::fromValue($expense, $nStyle),
            ];

            if ($hasRunningBalance) {
                $cells[] = Cell::fromValue($runningBalance, $nStyle);
            }

            $writer->addRow(new Row($cells));
            $rowNumber++;
        }

        // --- 5. Total Summary Row ---
        $totalCells = [
            Cell::fromValue('', $totalLabelStyle),
            Cell::fromValue('', $totalLabelStyle),
            Cell::fromValue('', $totalLabelStyle),
            Cell::fromValue('TOTAL KESELURUHAN', $totalLabelStyle),
            Cell::fromValue('', $totalLabelStyle),
            Cell::fromValue('', $totalLabelStyle),
            Cell::fromValue($sumIncome, $totalNumberStyle),
            Cell::fromValue($sumExpense, $totalNumberStyle),
        ];

        if ($hasRunningBalance) {
            $totalCells[] = Cell::fromValue($runningBalance, $totalNumberStyle);
        }

        $writer->addRow(new Row($totalCells));

        // --- 6. Net Movement Summary (Surplus/Defisit) ---
        $net = $sumIncome - $sumExpense;
        $netLabel = ($net >= 0 ? 'SURPLUS (BERSIH): ' : 'DEFISIT (BERSIH): ').'Rp '.number_format($net, 0, ',', '.');
        $writer->addRow(Row::fromValues([''])); // Blank row
        $writer->addRow(Row::fromValues([$netLabel], (new Style)->setFontBold()->setFontSize(11)->setFontColor($net >= 0 ? '15803D' : 'B91C1C')));

        $writer->close();

        return [
            'file_path' => $filePath,
            'filename' => $filename,
        ];
    }
}
