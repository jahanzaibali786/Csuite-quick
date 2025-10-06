<?php

namespace App\DataTables;

use App\Models\InvoiceProduct;
use App\Models\Tax;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class SalesbyCustomerTypeDetailDataTable extends DataTable
{
    public function dataTable($query)
    {
        $rows = $query->get();
        $taxes = Tax::all()->keyBy('id');

        $data = collect();
        $runningBalance = 0;
        $totalAmount = 0;
        $totalQuantity = 0;
        $totalSalesWithTax = 0;

        foreach ($rows as $r) {
            // ---- Calculate amount (exclude tax) ----
            $baseAmount = ($r->price ?? 0) * ($r->quantity ?? 0);
            $discount = $r->discount ?? 0;
            $amount = $baseAmount - $discount;

            // ---- Calculate tax amount ----
            $taxAmount = 0;
            if (!empty($r->tax)) {
                foreach (explode(',', $r->tax) as $taxId) {
                    $taxId = trim($taxId);
                    if (isset($taxes[$taxId])) {
                        $rate = (float) $taxes[$taxId]->rate;
                        $taxAmount += (($baseAmount - $discount) * $rate / 100);
                    }
                }
            }

            $salesWithTax = $amount + $taxAmount;

            // ---- Update running totals ----
            $runningBalance += $amount;
            $totalQuantity += ($r->quantity ?? 0);
            $totalAmount += $amount;
            $totalSalesWithTax += $salesWithTax;

            // ---- Add row to collection ----
            $data->push([
                'transaction_type' => 'Invoice',
                'transaction_date' => optional($r->invoice)->issue_date
                    ? Carbon::parse($r->invoice->issue_date)->format('m/d/Y')
                    : '-',
                'invoice_number' => optional($r->invoice)->ref_number ?? $r->invoice_id ?? '-',
                'memo_description' => $r->description
                    ?? (optional($r->invoice)->ref_number
                        ? "Invoice Ref #" . optional($r->invoice)->ref_number
                        : '-'),
                'customer_name' => optional(optional($r->invoice)->customer)->name ?? '-',
                'quantity' => number_format(($r->quantity ?? 0), 2),
                'sales_price' => number_format(($r->price ?? 0), 2),
                'amount' => number_format($amount, 2),
                'balance' => number_format($runningBalance, 2), // ✅ running total balance
                'sales_with_tax' => number_format($salesWithTax, 2),
            ]);
        }

        // ✅ Add total row
        if ($rows->count() > 0) {
            $data->push([
                'transaction_type' => '<strong>Total</strong>',
                'transaction_date' => '<strong></strong>',
                'invoice_number' => '<strong></strong>',
                'memo_description' => '<strong></strong>',
                'customer_name' => '<strong></strong>',
                'quantity' => '<strong>' . number_format($totalQuantity, 2) . '</strong>',
                'sales_price' => '<strong></strong>',
                'amount' => '<strong>' . number_format($totalAmount, 2) . '</strong>',
                'balance' => '<strong>' . number_format($runningBalance, 2) . '</strong>', // ✅ final total balance
                'sales_with_tax' => '<strong>' . number_format($totalSalesWithTax, 2) . '</strong>',
                'DT_RowClass' => 'summary-total'
            ]);
        } else {
            $data->push([
                'transaction_type' => 'No records found.',
                'transaction_date' => '',
                'invoice_number' => '',
                'memo_description' => '',
                'customer_name' => '',
                'quantity' => '',
                'sales_price' => '',
                'amount' => '',
                'balance' => '',
                'sales_with_tax' => '',
                'DT_RowClass' => 'no-data-row'
            ]);
        }

        return datatables()
            ->collection($data)
            ->rawColumns([
                'transaction_type',
                'transaction_date',
                'invoice_number',
                'memo_description',
                'customer_name',
                'quantity',
                'sales_price',
                'amount',
                'balance',
                'sales_with_tax',
            ]);
    }

    public function query(InvoiceProduct $model)
    {
        $user = Auth::user();
        $start = request()->get('start_date') ?? request()->get('startDate') ?? date('Y-01-01');
        $end = request()->get('end_date') ?? request()->get('endDate') ?? date('Y-m-d');

        $query = $model->with(['invoice.customer'])
            ->whereHas('invoice', function ($q) use ($user, $start, $end) {
                $q->whereBetween(DB::raw('DATE(issue_date)'), [$start, $end])
                    ->where('created_by', $user->creatorId());
            });

        return $query->orderBy('id', 'desc');
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
                'scrollX' => true,
                'scrollY' => '420px',
                'scrollCollapse' => true,
            ]);
    }

    protected function getColumns()
    {
        return [
            Column::make('transaction_type')->title('Transaction Type'),
            Column::make('transaction_date')->title('Transaction Date'),
            Column::make('invoice_number')->title('Invoice Number / Num'),
            Column::make('memo_description')->title('Memo/Description'),
            Column::make('customer_name')->title('Customer Name'),
            Column::make('quantity')->title('Quantity')->addClass('text-right'),
            Column::make('sales_price')->title('Sales Price')->addClass('text-right'),
            Column::make('amount')->title('Amount')->addClass('text-right'),
            Column::make('balance')->title('Balance')->addClass('text-right'),
            Column::make('sales_with_tax')->title('Sales With Tax')->addClass('text-right'),
        ];
    }

    protected function filename(): string
    {
        return 'SalesByCustomerTypeDetail_' . date('YmdHis');
    }
}
