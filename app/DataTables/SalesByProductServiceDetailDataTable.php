<?php

namespace App\DataTables;

use App\Models\InvoiceProduct;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class SalesByProductServiceDetailDataTable extends DataTable
{
    // public function dataTable($query)
    // {
    //     $user = Auth::user();

    //     // Get all rows first (for running balance + total row)
    //     $rows = $query->get();

    //     $data = collect();
    //     $runningBalance = 0;
    //     $totalAmount = 0;
    //     $totalQuantity = 0;

    //     foreach ($rows as $r) {
    //         // Calculate amount = (price * quantity) - discount + tax
    //         $baseAmount = ($r->price ?? 0) * ($r->quantity ?? 0);
    //         $discount = $r->discount ?? 0;
    //         $taxAmount = $this->calculateTaxAmount($r);
    //         $amount = $baseAmount - $discount + $taxAmount;

    //         // Update totals
    //         $runningBalance += $amount;
    //         $totalAmount += $amount;
    //         $totalQuantity += ($r->quantity ?? 0);

    //         // Add each transaction row
    //         $data->push([
    //             'transaction_date' => \Carbon\Carbon::parse($r->transaction_date)->format('m/d/Y'),
    //             'transaction_type' => $r->transaction_type ?? 'Invoice',
    //             'num' => $r->invoice_number ?? '-',
    //             'customer_full_name' => $r->customer_name ?? '-',
    //             'memo_description' => $r->description ?? '-',
    //             'quantity' => number_format($r->quantity ?? 0, 2),
    //             'sales_price' => number_format($r->price ?? 0),
    //             'amount' => number_format($amount, 2),
    //             'balance' => number_format($runningBalance, 2),
    //         ]);
    //     }

    //     // Add total row (all bold, empty placeholders)
    //     if ($rows->count() > 0) {
    //         $data->push([
    //             'transaction_date' => '<strong>Total</strong>',
    //             'transaction_type' => '<strong></strong>',
    //             'num' => '<strong></strong>',
    //             'customer_full_name' => '<strong></strong>',
    //             'memo_description' => '<strong></strong>',
    //             'quantity' => '<strong>' . number_format($totalQuantity, 2) . '</strong>',
    //             'sales_price' => '<strong></strong>',
    //             'amount' => '<strong>' . number_format($totalAmount, 2) . '</strong>',
    //             'balance' => '<strong>' . number_format($runningBalance, 2) . '</strong>',
    //             'DT_RowClass' => 'summary-total'
    //         ]);
    //     } else {
    //         $data->push([
    //             'transaction_date' => 'No records found.',
    //             'transaction_type' => '',
    //             'num' => '',
    //             'customer_full_name' => '',
    //             'memo_description' => '',
    //             'quantity' => '',
    //             'sales_price' => '',
    //             'amount' => '',
    //             'balance' => '',
    //             'DT_RowClass' => 'no-data-row'
    //         ]);
    //     }

    //     return datatables()
    //         ->collection($data)
    //         ->rawColumns([
    //             'transaction_date',
    //             'transaction_type',
    //             'num',
    //             'customer_full_name',
    //             'memo_description',
    //             'quantity',
    //             'sales_price',
    //             'amount',
    //             'balance',
    //         ]);
    // }

    public function dataTable($query)
    {
        $user = Auth::user();

        // Get all rows first (for running balance + total row)
        $rows = $query->get();

        $data = collect();
        $runningBalance = 0;
        $totalAmount = 0;
        $totalQuantity = 0;

        foreach ($rows as $r) {
            // Calculate amount = (price * quantity) - discount (EXCLUDE TAX)
            $baseAmount = ($r->price ?? 0) * ($r->quantity ?? 0);
            $discount = $r->discount ?? 0;
            $amount = $baseAmount - $discount; // ✅ exclude tax here
            $taxAmount = $this->calculateTaxAmount($r); // optional, keep for future use

            // Update totals
            $runningBalance += $amount;
            $totalAmount += $amount;
            $totalQuantity += ($r->quantity ?? 0);

            // Add each transaction row
            $data->push([
                'transaction_date' => \Carbon\Carbon::parse($r->transaction_date)->format('m/d/Y'),
                'transaction_type' => $r->transaction_type ?? 'Invoice',
                'num' => $r->invoice_number ?? '-',
                'customer_full_name' => $r->customer_name ?? '-',
                'memo_description' => $r->description ?? '-',
                'quantity' => number_format($r->quantity ?? 0, 2),
                'sales_price' => number_format($r->price ?? 0),
                'amount' => number_format($amount, 2),
                'balance' => number_format($runningBalance, 2),
            ]);
        }

        // Add total row (all bold)
        if ($rows->count() > 0) {
            $data->push([
                'transaction_date' => '<strong>Total</strong>',
                'transaction_type' => '<strong></strong>',
                'num' => '<strong></strong>',
                'customer_full_name' => '<strong></strong>',
                'memo_description' => '<strong></strong>',
                'quantity' => '<strong>' . number_format($totalQuantity, 2) . '</strong>',
                'sales_price' => '<strong></strong>',
                'amount' => '<strong>' . number_format($totalAmount, 2) . '</strong>',
                'balance' => '<strong>' . number_format($runningBalance, 2) . '</strong>',
                'DT_RowClass' => 'summary-total'
            ]);
        } else {
            $data->push([
                'transaction_date' => 'No records found.',
                'transaction_type' => '',
                'num' => '',
                'customer_full_name' => '',
                'memo_description' => '',
                'quantity' => '',
                'sales_price' => '',
                'amount' => '',
                'balance' => '',
                'DT_RowClass' => 'no-data-row'
            ]);
        }

        return datatables()
            ->collection($data)
            ->rawColumns([
                'transaction_date',
                'transaction_type',
                'num',
                'customer_full_name',
                'memo_description',
                'quantity',
                'sales_price',
                'amount',
                'balance',
            ]);
    }



    private function calculateTaxAmount($transaction)
    {
        if (empty($transaction->tax)) {
            return 0;
        }

        $taxIds = explode(',', $transaction->tax);
        $totalTaxRate = 0;

        foreach ($taxIds as $taxId) {
            $tax = DB::table('taxes')->where('id', $taxId)->first();
            if ($tax) {
                $totalTaxRate += $tax->rate;
            }
        }

        $baseAmount = ($transaction->price ?? 0) * ($transaction->quantity ?? 0) - ($transaction->discount ?? 0);
        return ($baseAmount * $totalTaxRate) / 100;
    }

    public function query()
    {
        $user = Auth::user();
        $ownerId = $user->type === 'company' ? $user->creatorId() : $user->ownedId();

        // Date filters
        $startDate = request()->get('start_date')
            ?? request()->get('startDate')
            ?? date('Y-01-01');
        $endDate = request()->get('end_date')
            ?? request()->get('endDate')
            ?? date('Y-m-d');
        $reportPeriod = request('report_period', 'all_dates');

        if ($reportPeriod && $reportPeriod !== 'all_dates' && $reportPeriod !== 'custom') {
            $dates = $this->calculateDateRange($reportPeriod);
            $startDate = $dates['start'];
            $endDate = $dates['end'];
        }

        // 🔹 Main query — Fetch invoice + product/service details
        $q = InvoiceProduct::query()
            ->join('invoices as i', 'i.id', '=', 'invoice_products.invoice_id')
            ->join('product_services as ps', 'ps.id', '=', 'invoice_products.product_id')
            ->join('customers as c', 'c.id', '=', 'i.customer_id')
            ->select([
                'invoice_products.*',
                'i.issue_date as transaction_date',
                'i.invoice_id as invoice_number',
                'ps.name as product_name',
                'c.name as customer_name',
                DB::raw("'Invoice' as transaction_type"),
            ])
            ->where('i.created_by', $ownerId)
            ->where('i.status', '!=', 0); // exclude drafts

        // Apply date filters
        if ($startDate) {
            $q->whereDate('i.issue_date', '>=', $startDate);
        }
        if ($endDate) {
            $q->whereDate('i.issue_date', '<=', $endDate);
        }

        // Filters
        if (request()->filled('product_name')) {
            $q->where('ps.name', 'like', '%' . request('product_name') . '%');
        }
        if (request()->filled('customer_name')) {
            $q->where('c.name', 'like', '%' . request('customer_name') . '%');
        }
        if (request()->filled('category')) {
            $q->where('ps.category_id', request('category'));
        }
        if (request()->filled('type')) {
            $q->where('ps.type', request('type'));
        }

        return $q->orderBy('i.issue_date', 'desc');
    }



    private function calculateDateRange($period)
    {
        $today = \Carbon\Carbon::today();

        switch ($period) {
            case 'today':
                return ['start' => $today->format('Y-m-d'), 'end' => $today->format('Y-m-d')];
            case 'this_week':
                return ['start' => $today->startOfWeek()->format('Y-m-d'), 'end' => $today->endOfWeek()->format('Y-m-d')];
            case 'this_month':
                return ['start' => $today->startOfMonth()->format('Y-m-d'), 'end' => $today->endOfMonth()->format('Y-m-d')];
            case 'this_quarter':
                return ['start' => $today->startOfQuarter()->format('Y-m-d'), 'end' => $today->endOfQuarter()->format('Y-m-d')];
            case 'this_year':
                return ['start' => $today->startOfYear()->format('Y-m-d'), 'end' => $today->endOfYear()->format('Y-m-d')];
            case 'last_week':
                $lastWeek = $today->subWeek();
                return ['start' => $lastWeek->startOfWeek()->format('Y-m-d'), 'end' => $lastWeek->endOfWeek()->format('Y-m-d')];
            case 'last_month':
                $lastMonth = $today->subMonth();
                return ['start' => $lastMonth->startOfMonth()->format('Y-m-d'), 'end' => $lastMonth->endOfMonth()->format('Y-m-d')];
            case 'last_quarter':
                $lastQuarter = $today->subQuarter();
                return ['start' => $lastQuarter->startOfQuarter()->format('Y-m-d'), 'end' => $lastQuarter->endOfQuarter()->format('Y-m-d')];
            case 'last_year':
                $lastYear = $today->subYear();
                return ['start' => $lastYear->startOfYear()->format('Y-m-d'), 'end' => $lastYear->endOfYear()->format('Y-m-d')];
            case 'last_7_days':
                return ['start' => $today->subDays(7)->format('Y-m-d'), 'end' => \Carbon\Carbon::today()->format('Y-m-d')];
            case 'last_30_days':
                return ['start' => $today->subDays(30)->format('Y-m-d'), 'end' => \Carbon\Carbon::today()->format('Y-m-d')];
            case 'last_90_days':
                return ['start' => $today->subDays(90)->format('Y-m-d'), 'end' => \Carbon\Carbon::today()->format('Y-m-d')];
            case 'last_12_months':
                return ['start' => $today->subMonths(12)->format('Y-m-d'), 'end' => \Carbon\Carbon::today()->format('Y-m-d')];
            default:
                return ['start' => null, 'end' => null];
        }
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('customer-balance-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('rt')
            ->parameters([
                'responsive' => true,
                'autoWidth' => false,
                'paging' => false,
                'searching' => false,
                'info' => false,
                'ordering' => false,
                'colReorder' => true,
                'fixedHeader' => true,
                'scrollY' => '400px',
                'scrollX' => true,
                'scrollCollapse' => true,
            ]);
    }

    protected function getColumns()
    {
        return [
            Column::make('transaction_date')->data('transaction_date')->name('transaction_date')->title(__('Transaction Date'))->addClass('text-left'),
            Column::make('transaction_type')->data('transaction_type')->name('transaction_type')->title(__('Transaction Type'))->addClass('text-left'),
            Column::make('num')->data('num')->name('num')->title(__('Num'))->addClass('text-left'),
            Column::make('customer_full_name')->data('customer_full_name')->name('customer_full_name')->title(__('Customer Full Name'))->addClass('text-left'),
            Column::make('memo_description')->data('memo_description')->name('memo_description')->title(__('Memo/Description'))->addClass('text-left'),
            Column::make('quantity')->data('quantity')->name('quantity')->title(__('Quantity'))->addClass('text-right'),
            Column::make('sales_price')->data('sales_price')->name('sales_price')->title(__('Sales Price'))->addClass('text-right'),
            Column::make('amount')->data('amount')->name('amount')->title(__('Amount'))->addClass('text-right'),
            Column::make('balance')->data('balance')->name('balance')->title(__('Balance'))->addClass('text-right'),
        ];
    }

    protected function filename(): string
    {
        return 'SalesByProductServiceDetail_' . date('YmdHis');
    }
}