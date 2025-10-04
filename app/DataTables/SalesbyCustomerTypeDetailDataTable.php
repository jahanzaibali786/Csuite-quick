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
                return 'Invoice';
            })
            ->addColumn('transaction_date', function ($row) {
                return optional($row->invoice)->issue_date
                    ? Carbon::parse($row->invoice->issue_date)->format('m/d/Y')
                    : 'No date available';
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

            // ---- Raw numeric fields (for accurate front-end formatting) ----
            ->addColumn('quantity_raw', function ($row) {
                return (float) ($row->quantity ?? 0);
            })
            ->addColumn('sales_price_raw', function ($row) {
                return (float) ($row->price ?? 0);
            })
            ->addColumn('amount_raw', function ($row) {
                return (float) (($row->price ?? 0) * ($row->quantity ?? 0));
            })
            ->addColumn('balance_raw', function ($row) {
                return (float) (optional($row->invoice)->getDue() ?? 0);
            })
            ->addColumn('sales_with_tax_raw', function ($row) {
                $baseAmount = (float) (($row->price ?? 0) * ($row->quantity ?? 0));
                $discount = (float) ($row->discount ?? 0);
                $tax = 0.0;

                if ($row->tax) {
                    foreach (explode(',', $row->tax) as $taxId) {
                        $taxObj = Tax::find($taxId);
                        if ($taxObj) {
                            $tax += (($row->price ?? 0) * ($row->quantity ?? 0) - $discount) * ($taxObj->rate / 100);
                        }
                    }
                }
                return $baseAmount + $tax;
            })

            // ---- Formatted display fields (kept for non-JS context/fallbacks) ----
            ->addColumn('quantity', function ($row) {
                return number_format(($row->quantity ?? 0), 2);
            })
            ->addColumn('sales_price', function ($row) {
                return number_format(($row->price ?? 0), 2);
            })
            ->addColumn('amount', function ($row) {
                $val = ($row->price ?? 0) * ($row->quantity ?? 0);
                return number_format($val, 2);
            })
            ->addColumn('balance', function ($row) {
                $val = optional($row->invoice)->getDue() ?? 0;
                return number_format($val, 2);
            })
            ->addColumn('sales_with_tax', function ($row) {
                $baseAmount = ($row->price ?? 0) * ($row->quantity ?? 0);
                $discount = $row->discount ?? 0;
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

    /*public function query(InvoiceProduct $model)
    {
        $query = $model->with(['invoice.customer']);

        // I want to use these variables later, please adjust it
        $start = request()->get('start_date') ?? request()->get('startDate') ?? Carbon::now()->startOfYear()->format('Y-m-d');
        $end = request()->get('end_date') ?? request()->get('endDate') ?? Carbon::now()->endOfDay()->format('Y-m-d');

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

        // Filter by created_by through invoice relationship
        $query->whereHas('invoice', function ($q) {
            $q->where('created_by', \Auth::user()->creatorId());
        });

        return $query->orderBy('id', 'desc');
    }*/

    public function query(InvoiceProduct $model)
    {
        $query = $model->with(['invoice.customer']);

        // Get start and end dates from request, fallback to defaults
        $start = request()->get('start_date')
            ?? request()->get('startDate')
            ?? date('Y-01-01');
        $end = request()->get('end_date')
            ?? request()->get('endDate')
            ?? date('Y-m-d');

        // Ensure the query filters by date
        $query->whereHas('invoice', function ($q) use ($start, $end) {
            $q->whereBetween(\DB::raw('DATE(issue_date)'), [$start, $end]);
        });

        // Filter by created_by through invoice relationship
        $query->whereHas('invoice', function ($q) {
            $q->where('created_by', \Auth::user()->creatorId());
        });

        return $query->orderBy('id', 'desc');
    }


    public function html()
    {
        return $this->builder()
            ->setTableId('customer-balance-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('t')
            ->orderBy(1, 'desc')
            ->parameters([
                'responsive' => false,
                'autoWidth' => false,
                'paging' => false,
                'searching' => false,
                'info' => false,
                'ordering' => false,
                'processing' => true,
                'serverSide' => true,
                'scrollX' => true,
                'scrollY' => '420px',
                'scrollCollapse' => true,
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

            // Display columns (formatted); raw fields are in the JSON payload for JS renderers
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
