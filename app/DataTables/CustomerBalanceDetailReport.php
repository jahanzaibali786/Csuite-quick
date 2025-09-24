<?php

namespace App\DataTables;

use App\Models\Invoice;
use Carbon\Carbon;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Illuminate\Support\Facades\DB;

class CustomerBalanceDetailReport extends DataTable
{
    public function dataTable($query)
    {
        $data = collect($query->get());

        $grandTotalAmount = 0;
        $grandBalanceDue = 0;
        $grandBalance = 0;

        // ✅ Group by Customer Name
        $groupedData = $data->groupBy('name');

        $finalData = collect();

        foreach ($groupedData as $customer => $rows) {
            $subtotalAmount = 0;
            $subtotalDue = 0;
            $subtotalBalance = 0;

            // Header row for this customer
            $finalData->push((object) [
                'transaction' => '<strong>' . $customer . '</strong>',
                'due_date' => '',
                'type' => null,
                'status_label' => '',
                'total_amount' => null,
                'balance' => 0,      // 👈 added
                'open_balance' => 0,      // 👈 added
                'balance_due' => null,
                'isPlaceholder' => true,
                'isSubtotal' => true,
            ]);


            foreach ($rows as $row) {
                $subtotalAmount += ($row->subtotal ?? 0) + ($row->total_tax ?? 0);
                $subtotalDue += $row->balance_due;
                $subtotalBalance += $row->balance ?? 0; // Error happens when I add this row
                $row->customer = $customer;
                $row->past_due = $row->age > 0 ? $row->age . ' Days' : '-'; // 👈 Past Due column
                $finalData->push($row);
            }

            // Subtotal row for this customer
            $finalData->push((object) [
                // 'customer' => '<strong>Subtotal</strong>',
                'transaction' => '<strong>Subtotal For '. $customer .'</strong>',
                'due_date' => '',
                'past_due' => '',
                'type' => '',
                'status_label' => '',
                // 'age' => '',
                'total_amount' => $subtotalAmount,
                'balance_due' => $subtotalDue,
                'balance' => $subtotalBalance,
                'isSubtotal' => true,
            ]);

            $finalData->push((object) [
                'transaction' => '',
                'due_date' => '',
                'type' => '',
                'status_label' => '',
                'total_amount' => 0,
                'balance' => 0,      // 👈 added
                'open_balance' => 0,      // 👈 added
                'balance_due' => 0,
                'isPlaceholder' => true,
                'isSubtotal' => true,
            ]);


            $grandTotalAmount += $subtotalAmount;
            $grandBalanceDue += $subtotalDue;
            $grandBalance += $subtotalBalance;

        }

        // ✅ Add grand total row
        $finalData->push((object) [
            'transaction' => '<strong>Grand Total</strong>',
            'due_date' => '',
            'past_due' => '',
            'type' => '',
            'status_label' => '',
            'age' => '',
            'total_amount' => $grandTotalAmount,
            'balance' => $grandBalance,      // 👈 SHOWS TOTAL BALANCE
            'open_balance' => $grandBalanceDue,   // 👈 SHOWS TOTAL OPEN BALANCE
            'balance_due' => $grandBalanceDue,
            'isGrandTotal' => true,
        ]);


        return datatables()
            ->collection($finalData)
            ->addColumn('transaction', function ($row) {
                if (isset($row->isSubtotal) || isset($row->isGrandTotal) || isset($row->isPlaceholder)) {
                    return $row->transaction ?? '';
                }

                return \Auth::user()->invoiceNumberFormat($row->invoice ?? ($row->id ?? ''));
            })

            ->addColumn('due_date', fn($row) => $row->due_date ?? '')
            ->addColumn('past_due', fn($row) => $row->past_due ?? '')
            ->addColumn(
                'type',
                fn($row) =>
                isset($row->isSubtotal) || isset($row->isGrandTotal) ? '' : 'Invoice'
            )
            ->addColumn('status_label', function ($row) {
                if (isset($row->isSubtotal) || isset($row->isGrandTotal)) {
                    return '';
                }
                $status = $row->status ?? 0;
                $labels = \App\Models\Invoice::$statues;
                $classes = [
                    0 => 'bg-secondary',
                    1 => 'bg-warning',
                    2 => 'bg-danger',
                    3 => 'bg-info',
                    4 => 'bg-primary',
                ];
                return '<span class="status_badge badge text-white ' . ($classes[$status] ?? 'bg-secondary') . ' p-2 px-3 rounded">'
                    . __($labels[$status] ?? '-') . '</span>';
            })
            ->addColumn('issue_date', fn($row) => $row->issue_date ?? '')
            ->editColumn('total_amount', function ($row) {
                if (isset($row->isPlaceholder)) {
                    return '';
                }
                if (isset($row->isSubtotal) || isset($row->isGrandTotal)) {
                    return number_format($row->total_amount ?? 0);
                }
                $total = ($row->subtotal ?? 0) + ($row->total_tax ?? 0);
                return number_format($total);
            })
            ->editColumn(
                'balance_due',
                fn($row) =>
                isset($row->isPlaceholder) ? '' : number_format($row->balance_due ?? 0)
            )
            ->editColumn('balance', function ($row) {
                if (isset($row->isPlaceholder)) {
                    return '';
                }
                if (isset($row->isSubtotal) || isset($row->isGrandTotal)) {
                    return number_format($row->balance ?? 0);
                }
                return number_format($row->balance ?? 0);
            })

            ->addColumn(
                'open_balance',
                fn($row) =>
                isset($row->isPlaceholder) ? '' : number_format($row->balance_due ?? 0)
            )
            ->rawColumns(['customer', 'transaction', 'status_label']);
    }

    public function query(Invoice $model)
    {
        $start = request()->get('start_date')
            ?? request()->get('startDate')
            ?? Carbon::now()->startOfYear()->format('Y-m-d');

        $end = request()->get('end_date')
            ?? request()->get('endDate')
            ?? Carbon::now()->endOfDay()->format('Y-m-d');

        return $model->newQuery()
            ->select(
                'invoices.id',
                'invoices.invoice_id as invoice',
                'invoices.issue_date',
                'invoices.due_date',
                'invoices.status',
                'customers.name',
                DB::raw('SUM((invoice_products.price * invoice_products.quantity) - invoice_products.discount) as subtotal'),
                DB::raw('IFNULL(SUM(invoice_payments.amount), 0) as pay_price'),
                DB::raw('(SELECT IFNULL(SUM((price * quantity - discount) * (taxes.rate / 100)),0) 
                  FROM invoice_products 
                  LEFT JOIN taxes ON FIND_IN_SET(taxes.id, invoice_products.tax) > 0
                  WHERE invoice_products.invoice_id = invoices.id) as total_tax'),
                DB::raw('(SELECT IFNULL(SUM(credit_notes.amount),0) 
                  FROM credit_notes 
                  WHERE credit_notes.invoice = invoices.id) as credit_price'),
                DB::raw('
    (SUM((invoice_products.price * invoice_products.quantity) - invoice_products.discount)
     + (SELECT IFNULL(SUM((price * quantity - discount) * (taxes.rate / 100)),0) 
        FROM invoice_products 
        LEFT JOIN taxes ON FIND_IN_SET(taxes.id, invoice_products.tax) > 0
        WHERE invoice_products.invoice_id = invoices.id)
    ) as balance
'),


                DB::raw('(
                    (SUM((invoice_products.price * invoice_products.quantity) - invoice_products.discount))
                    + (SELECT IFNULL(SUM((price * quantity - discount) * (taxes.rate / 100)),0) 
                       FROM invoice_products 
                       LEFT JOIN taxes ON FIND_IN_SET(taxes.id, invoice_products.tax) > 0
                       WHERE invoice_products.invoice_id = invoices.id)
                    - (IFNULL(SUM(invoice_payments.amount),0)
                    + (SELECT IFNULL(SUM(credit_notes.amount),0) FROM credit_notes WHERE credit_notes.invoice = invoices.id))
                 ) as balance_due'),
                DB::raw('GREATEST(DATEDIFF(CURDATE(), invoices.due_date), 0) as age'),

            )
            ->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id')
            ->leftJoin('invoice_products', 'invoice_products.invoice_id', '=', 'invoices.id')
            ->leftJoin('invoice_payments', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->where('invoices.created_by', \Auth::user()->creatorId())
            ->whereBetween('invoices.issue_date', [$start, $end])
            ->groupBy('invoices.id');
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('customer-balance-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(0, 'asc')
            ->parameters([
                'paging' => false,
                'searching' => false,
                'info' => false,
                'ordering' => false,
                'rowGroup' => [
                    'dataSrc' => 'customer',
                ],
            ]);
    }

    protected function getColumns()
    {
        return [
            Column::make('issue_date')->title('Date'),
            Column::make('transaction')->title('Transaction'),
            Column::make('type')->title('Type'),
            Column::make('status_label')->title('Status'),
            Column::make('due_date')->title('Due Date'),
            Column::make('total_amount')->title('Amount'),      // optional: original invoice calc
            Column::make('open_balance')->title('Open Balance'), // 👈 from balance_due
            Column::make('balance')->title('Balance'),          // 👈 new
        ];
    }

}
