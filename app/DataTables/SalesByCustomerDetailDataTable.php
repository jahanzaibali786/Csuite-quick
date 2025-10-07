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
        $rows = $query->get();
        $data = collect();

        $runningBalance = 0;
        $totalAmount = 0;
        $totalQuantity = 0;

        foreach ($rows as $r) {
            $amount = ($r->sales_price ?? 0) * ($r->quantity ?? 0);
            $runningBalance += $amount;
            $totalAmount += $amount;
            $totalQuantity += ($r->quantity ?? 0);

            $data->push([
                'transaction_date' => date('m/d/Y', strtotime($r->transaction_date ?? '')),
                'transaction_type' => $r->transaction_type ?? '',
                'num' => $r->num ?? '-',
                'product_service_name' => $r->product_service_name ?? '-',
                'memo_description' => $r->memo_description ?? '-',
                'quantity' => number_format($r->quantity ?? 0, 2),
                'sales_price' => number_format($r->sales_price ?? 0),
                'amount' => number_format($amount, 2),
                'balance' => number_format($runningBalance, 2),
            ]);
        }

        // Add a total row
        if ($rows->count() > 0) {
            $data->push([
                'transaction_date' => '<strong>Total</strong>',
                'transaction_type' => '',
                'num' => '',
                'product_service_name' => '',
                'memo_description' => '',
                'quantity' => '<strong>' . number_format($totalQuantity, 2) . '</strong>',
                'sales_price' => '',
                'amount' => '<strong>' . number_format($totalAmount, 2) . '</strong>',
                'balance' => '<strong>' . number_format($runningBalance, 2) . '</strong>',
                'DT_RowClass' => 'summary-total'
            ]);
        }

        return datatables()
            ->collection($data)
            ->rawColumns(['transaction_date', 'quantity', 'amount', 'balance']);
    }


    public function query()
    {
        $user = Auth::user();

        // Determine owner ID based on user type
        $ownerId = $user->type === 'company' ? $user->creatorId() : $user->ownedId();

        // Use consistent column for created_by (same as Product/Service Detail)
        $ownerColumn = 'invoices.created_by';

        // Date range
        $start = request()->get('start_date') ?? request()->get('startDate') ?? date('Y-01-01');
        $end = request()->get('end_date') ?? request()->get('endDate') ?? date('Y-m-d');
        $selectedCustomer = request('customer_name', '');

        // Build the query (aligned with SalesByProductServiceDetail)
        $query = DB::table('invoice_products')
            ->join('invoices', 'invoices.id', '=', 'invoice_products.invoice_id')
            ->join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->leftJoin('product_services', 'product_services.id', '=', 'invoice_products.product_id')
            ->where($ownerColumn, $ownerId)
            ->where('invoices.status', '!=', 0) // ✅ Exclude draft invoices
            ->whereBetween('invoices.issue_date', [$start, $end])
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
                DB::raw('COALESCE((invoice_products.price * invoice_products.quantity - COALESCE(invoice_products.discount, 0)), 0) as balance'),
            ]);

        // Apply optional customer filter
        if (!empty($selectedCustomer)) {
            $query->where('customers.name', 'LIKE', '%' . $selectedCustomer . '%');
        }

        // Order consistently
        $query->orderBy('customers.name', 'asc')
            ->orderBy('invoices.issue_date', 'asc')
            ->orderBy('product_services.name', 'asc');

        // Log for debug (optional)
        \Log::info('SalesByCustomerDetail SQL', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]);

        return $query;
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
                    'emptyTable' => 'No Data Found for the selected period.',
                    'zeroRecords' => 'No Data Found'
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
            Column::make('balance')->title(__('Balance'))->visible(true)->addClass('text-right')->width('10%'),
        ];
    }

    protected function filename(): string
    {
        return 'SalesByCustomerDetail_' . date('YmdHis');
    }
}