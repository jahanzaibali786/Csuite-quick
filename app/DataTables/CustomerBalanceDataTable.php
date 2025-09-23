<?php

namespace App\DataTables;

use App\Models\Invoice;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\Html\Column;

class CustomerBalanceDataTable extends DataTable
{
    public function dataTable($query)
    {
        $data = collect();
        $totalBalance = 0;

        $entries = $query->get(); // Get all rows

        if ($entries->count() > 0) {
            foreach ($entries as $row) {
                $customerBalance = $row['price'] + $row['total_tax'] - $row['pay_price'];
                $balance = $customerBalance - $row['credit_price'];
                $totalBalance += $balance;

                $data->push([
                    'name'          => $row['name'],
                    'price'         => number_format($row['price'] + $row['total_tax'], 2),
                    'credit_price'  => number_format($row['credit_price'] ?? 0, 2),
                    'balance'       => number_format($balance, 2),
                ]);
            }

            // Add total row at the bottom
            $data->push([
                'name'          => '<strong>Total</strong>',
                'price'         => '',
                'credit_price'  => '',
                'balance'       => '<strong>' . number_format($totalBalance, 2) . '</strong>',
                'DT_RowClass'   => 'summary-total'
            ]);
        } else {
            // No data case
            $data->push([
                'name'          => 'No data found for the selected period.',
                'price'         => '',
                'credit_price'  => '',
                'balance'       => '',
                'DT_RowClass'   => 'no-data-row'
            ]);
        }

        return datatables()
            ->collection($data)
            ->rawColumns(['name', 'balance']);
    }

    public function query(Invoice $model)
    {
        $start = request()->get('start_date') ?? date('Y-01-01');
        $end   = request()->get('end_date') ?? date('Y-m-d');

        return $model->newQuery()
            ->select('customers.name')
            ->selectRaw('SUM((invoice_products.price * invoice_products.quantity) - invoice_products.discount) as price')
            ->selectRaw('SUM(invoice_payments.amount) as pay_price')
            ->selectRaw('(SELECT SUM((price * quantity - discount) * (taxes.rate / 100)) FROM invoice_products
                        LEFT JOIN taxes ON FIND_IN_SET(taxes.id, invoice_products.tax) > 0
                        WHERE invoice_products.invoice_id = invoices.id) as total_tax')
            ->selectRaw('(SELECT SUM(credit_notes.amount) FROM credit_notes
                        WHERE credit_notes.invoice = invoices.id) as credit_price')
            ->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id')
            ->leftJoin('invoice_payments', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->leftJoin('invoice_products', 'invoice_products.invoice_id', '=', 'invoices.id')
            ->where('invoices.created_by', \Auth::user()->creatorId())
            ->whereBetween('invoices.issue_date', [$start, $end])
            ->groupBy('customers.name');
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('customer-balance-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(0, 'desc')
            ->parameters([
                'paging'         => false,
                'searching'      => false,
                'info'           => true,
                'ordering'       => false,
                'scrollY'        => '500px',
                'scrollCollapse' => true,
                'colReorder'     => true,
                'createdRow'     => "function(row, data) {
                    $('td:eq(1), td:eq(2), td:eq(3)', row).addClass('text-right');
                    if ($(row).hasClass('summary-total')) {
                        $(row).addClass('font-weight-bold bg-light');
                    }
                }"
            ]);
    }

    protected function getColumns()
    {
        return [
            Column::make('name')->title('Customer Name'),
            Column::make('price')->title('Invoice Balance'),
            Column::make('credit_price')->title('Available Credits'),
            Column::make('balance')->title('Balance'),
        ];
    }
}
