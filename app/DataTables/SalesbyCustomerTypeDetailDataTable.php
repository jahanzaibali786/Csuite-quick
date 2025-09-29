<?php

namespace App\DataTables;

use App\Models\InvoiceProduct;
use App\Models\Tax;
use Illuminate\Support\Carbon;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class SalesbyCustomerTypeDetailDataTable extends DataTable
{
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->addColumn('transaction_type', function ($row) {
                return 'Invoice'; // Later expand for Payments, Transactions, etc.
            })
            ->addColumn('transaction_date', function ($row) {
                return optional($row->invoice)->issue_date
                    ? Carbon::parse($row->invoice->issue_date)->format('m/d/Y')
                    : '-';
            })
            ->addColumn('invoice_number', function ($row) {
                return optional($row->invoice)->ref_number ?? $row->invoice_id ?? '-';
            })
            ->addColumn('memo_description', function ($row) {
                if ($row->description) {
                    return $row->description;
                }
                return optional($row->invoice)->ref_number
                    ? "Invoice Ref #" . optional($row->invoice)->ref_number
                    : '-';
            })
            ->addColumn('customer_name', function ($row) {
                return optional(optional($row->invoice)->customer)->name ?? '-';
            })
            ->addColumn('quantity', function ($row) {
                return $row->quantity ?? 0;
            })
            ->addColumn('sales_price', function ($row) {
                return $row->price ? number_format($row->price, 2) : '0.00';
            })
            ->addColumn('amount', function ($row) {
                return ($row->price && $row->quantity)
                    ? number_format($row->price * $row->quantity, 2)
                    : '0.00';
            })
            ->addColumn('balance', function ($row) {
                return optional($row->invoice)->getDue()
                    ? number_format(optional($row->invoice)->getDue(), 2)
                    : '0.00';
            })
            ->addColumn('sales_with_tax', function ($row) {
                $baseAmount = ($row->price ?? 0) * ($row->quantity ?? 0);
                $discount   = $row->discount ?? 0;
                $tax = 0;

                if ($row->tax) {
                    foreach (explode(',', $row->tax) as $taxId) {
                        $taxObj = Tax::find($taxId);
                        if ($taxObj) {
                            $tax += (($row->price ?? 0) * ($row->quantity ?? 0) - $discount) * ($taxObj->rate / 100);
                        }
                    }
                }

                return number_format($baseAmount + $tax, 2);
            });
    }

    public function query(InvoiceProduct $model)
    {
        $query = $model->with(['invoice.customer']);

        // Date filter
        if (request()->filled('start_date') && request()->filled('end_date')) {
            $query->whereHas('invoice', function ($q) {
                $q->whereBetween(\DB::raw('DATE(issue_date)'), [
                    request('start_date'),
                    request('end_date')
                ]);
            });
        } else {
            $start = date('Y-01-01');
            $end   = date('Y-m-d');
            $query->whereHas('invoice', function ($q) use ($start, $end) {
                $q->whereBetween(\DB::raw('DATE(issue_date)'), [$start, $end]);
            });
        }

        return $query->orderBy('id', 'desc');
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('customer-report')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('Bfrtip')
            ->orderBy(1, 'desc')
            ->parameters([
                'responsive' => true,
                'autoWidth'  => false,
                'paging'     => true,   // ✅ prevent -1 length bug
                'pageLength' => 50,     // default rows per page
                'searching'  => true,
                'info'       => true,
                'ordering'   => true,
                'buttons'    => [
                    'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'
                ],
            ]);
    }

    protected function getColumns()
    {
        return [
            Column::make('transaction_type')->title('Transaction Type')->width('150px'),
            Column::make('transaction_date')->title('Transaction Date')->width('120px'),
            Column::make('invoice_number')->title('Invoice Number / Num')->width('120px'),
            Column::make('memo_description')->title('Memo/Description')->width('200px'),
            Column::make('customer_name')->title('Customer Name')->width('150px'),
            Column::make('quantity')->title('Quantity')->width('100px')->addClass('text-right'),
            Column::make('sales_price')->title('Sales Price')->width('100px')->addClass('text-right'),
            Column::make('amount')->title('Amount')->width('120px')->addClass('text-right'),
            Column::make('balance')->title('Balance')->width('120px')->addClass('text-right'),
            Column::make('sales_with_tax')->title('Sales With Tax')->width('150px')->addClass('text-right'),
        ];
    }

    protected function filename(): string
    {
        return 'SalesByCustomerTypeDetail_' . date('YmdHis');
    }
}
