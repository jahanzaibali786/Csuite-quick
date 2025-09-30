<?php

namespace App\DataTables;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceProduct;
use App\Models\ProductService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class SalesByCustomerDetailDataTable extends DataTable
{
    public function dataTable($query)
    {
        return datatables()
            ->of($query)
            ->editColumn('transaction_date', function ($row) {
                try {
                    return date('m/d/Y', strtotime($row->transaction_date ?? ''));
                } catch (\Exception $e) {
                    return $row->transaction_date ?? '-';
                }
            })
            ->editColumn('transaction_type', function ($row) {
                return $row->transaction_type ?? '';
            })
            ->editColumn('num', function ($row) {
                return $row->num ?? '-';
            })
            ->editColumn('product_service_name', function ($row) {
                return $row->product_service_name ?? '-';
            })
            ->editColumn('memo_description', function ($row) {
                return $row->memo_description ?? '-';
            })
            ->editColumn('quantity', function ($row) {
                try {
                    return number_format((float)($row->quantity ?? 0), 2);
                } catch (\Exception $e) {
                    return '0.00';
                }
            })
            ->editColumn('sales_price', function ($row) {
                try {
                    return \Auth::user()->priceFormat((float)($row->sales_price ?? 0));
                } catch (\Exception $e) {
                    return '$0.00';
                }
            })
            ->editColumn('amount', function ($row) {
                try {
                    return \Auth::user()->priceFormat((float)($row->amount ?? 0));
                } catch (\Exception $e) {
                    return '$0.00';
                }
            })
            ->editColumn('balance', function ($row) {
                try {
                    return \Auth::user()->priceFormat((float)($row->balance ?? 0));
                } catch (\Exception $e) {
                    return '$0.00';
                }
            });
    }

    public function query()
    {
        $user = Auth::user();
        $ownerId = $user->type === 'company' ? $user->creatorId() : $user->ownedId();
        $ownerColumn = $user->type === 'company' ? 'created_by' : 'owned_by';

        $start = request('start_date', date('Y-01-01'));
        $end = request('end_date', date('Y-m-d'));
        $selectedCustomer = request('customer_name', '');

        // Debug logging
        \Log::info('SalesByCustomerDetail Debug', [
            'user_id' => $user->id,
            'user_type' => $user->type,
            'owner_id' => $ownerId,
            'owner_column' => $ownerColumn,
            'start_date' => $start,
            'end_date' => $end,
            'selected_customer' => $selectedCustomer,
            'request_params' => request()->all()
        ]);

        // Build the query for detailed sales by customer
        $query = DB::table('invoice_products')
            ->join('invoices', 'invoices.id', '=', 'invoice_products.invoice_id')
            ->join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->leftJoin('product_services', 'product_services.id', '=', 'invoice_products.product_id')
            ->where("invoices.$ownerColumn", $ownerId)
            ->whereBetween('invoices.issue_date', [$start, $end])
            ->whereIn('invoices.status', [1, 2, 3, 4]) // Exclude draft (0)
            ->select([
                'customers.name as customer_name',
                'invoices.issue_date as transaction_date',
                DB::raw("'Invoice' as transaction_type"),
                'invoices.invoice_id as num',
                DB::raw('COALESCE(product_services.name, "") as product_service_name'),
                DB::raw('COALESCE(product_services.description, "") as memo_description'),
                DB::raw('COALESCE(invoice_products.quantity, 0) as quantity'),
                DB::raw('COALESCE(invoice_products.price, 0) as sales_price'),
                DB::raw('COALESCE((invoice_products.price * invoice_products.quantity - COALESCE(invoice_products.discount, 0)), 0) as amount'),
                DB::raw('COALESCE((invoice_products.price * invoice_products.quantity - COALESCE(invoice_products.discount, 0)), 0) as balance')
            ]);

        // Apply customer filter if specified
        if (!empty($selectedCustomer)) {
            $query->where('customers.name', 'LIKE', '%' . $selectedCustomer . '%');
        }

        // Order by customer, then by date, then by product
        $query->orderBy('customers.name', 'asc')
              ->orderBy('invoices.issue_date', 'asc')
              ->orderBy('product_services.name', 'asc');

        // Debug: Log the actual SQL query
        \Log::info('SalesByCustomerDetail SQL', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]);

        return $query;
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('sales-by-customer-detail-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('rt')
            ->parameters([
                'responsive' => true,
                'autoWidth' => false,
                'paging' => false,
                'searching' => false,
                'info' => false,
                'ordering' => true,
                'order' => [[0, 'asc']], // Sort by Transaction Date ascending
                'colReorder' => true,
                'fixedHeader' => true,
                'scrollY' => '420px',
                'scrollX' => true,
                'scrollCollapse' => true,
                'columnDefs' => [
                    [
                        'targets' => [5, 6, 7, 8], // Quantity, Sales Price, Amount, Balance columns
                        'className' => 'text-right'
                    ]
                ],
                'language' => [
                    'emptyTable' => 'No sales data found for the selected period. Check your date range or ensure there are invoices with products for your customers.',
                    'zeroRecords' => 'No detailed sales found for the selected criteria.'
                ]
            ]);
    }

    protected function getColumns()
    {
        return [
            Column::make('transaction_date')->title(__('Transaction Date'))->visible(true)->width('12%'),
            Column::make('transaction_type')->title(__('Transaction Type'))->visible(true)->width('12%'),
            Column::make('num')->title(__('Num'))->visible(true)->width('10%'),
            Column::make('product_service_name')->title(__('Product/Service Full Name'))->visible(true)->width('20%'),
            Column::make('memo_description')->title(__('Memo/Description'))->visible(true)->width('18%'),
            Column::make('quantity')->title(__('Quantity'))->visible(true)->addClass('text-right')->width('8%'),
            Column::make('sales_price')->title(__('Sales Price'))->visible(true)->addClass('text-right')->width('10%'),
            Column::make('amount')->title(__('Amount'))->visible(true)->addClass('text-right')->width('10%'),
            Column::make('balance')->title(__('Balance'))->visible(false)->addClass('text-right')->width('10%'),
        ];
    }

    protected function filename(): string
    {
        return 'SalesByCustomerDetail_'.date('YmdHis');
    }
}