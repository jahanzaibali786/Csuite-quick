<?php

namespace App\DataTables;

use App\Models\Invoice;
use Carbon\Carbon;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AgingDetailsDataTable extends DataTable
{
    public function dataTable($query)
    {
        $today = Carbon::today();

        return datatables()
            ->eloquent($query)
            ->addColumn('bucket', function ($row) use ($today) {
                $dueDate = $row->due_date ?? $row->issue_date;
                if (!$dueDate)
                    return 'Current';

                try {
                    $due = Carbon::parse($dueDate);
                } catch (\Exception $e) {
                    return 'Current';
                }

                $age = $today->diffInDays($due, false);

                if ($age <= 0)
                    return 'Current';
                if ($age <= 15)
                    return '1–15 Days';
                if ($age <= 30)
                    return '16–30 Days';
                if ($age <= 45)
                    return '31–45 Days';
                return '>45 Days';
            })
            ->addColumn('transaction', fn($row) => \Auth::user()->invoiceNumberFormat($row->id))
            ->addColumn('type', fn($row) => 'Invoice')
            ->addColumn('status_label', function ($row) {
                $status = $row->status;
                $labels = \App\Models\Invoice::$statues;
                $classes = [
                    0 => 'bg-secondary',
                    1 => 'bg-warning',
                    2 => 'bg-danger',
                    3 => 'bg-info',
                    4 => 'bg-primary',
                ];
                return '<span class="status_badge badge ' . ($classes[$status] ?? 'bg-secondary') . ' p-2 px-3 rounded">'
                    . __($labels[$status] ?? '-') . '</span>';
            })
            ->addColumn('customer', fn($row) => $row->customer->name ?? '-')
            ->addColumn('age', function ($row) use ($today) {
                $dueDate = $row->due_date ?? $row->issue_date;
                if (!$dueDate)
                    return '-';

                try {
                    $due = Carbon::parse($dueDate);
                } catch (\Exception $e) {
                    return '-';
                }

                $age = $today->diffInDays($due, false);
                return $age > 0 ? $age . ' Days' : '-';
            })
            ->editColumn('total_amount', fn($row) => \Auth::user()->priceFormat($row->total_amount))
            ->editColumn('balance_due', fn($row) => \Auth::user()->priceFormat($row->balance_due))
            ->rawColumns(['status_label']);
    }

    // public function dataTable($query)
    // {
    //     $today = Carbon::today();
    //     $data = collect($query->get());

    //     $grandTotalAmount = 0;
    //     $grandBalanceDue = 0;

    //     $groupedData = $data->groupBy(function ($row) use ($today) {
    //         $dueDate = $row->due_date ?? $row->issue_date;
    //         if (!$dueDate)
    //             return 'Current';

    //         try {
    //             $due = Carbon::parse($dueDate);
    //         } catch (\Exception $e) {
    //             return 'Current';
    //         }

    //         $age = $today->diffInDays($due, false);
    //         if ($age <= 0)
    //             return 'Current';
    //         if ($age <= 15)
    //             return '1–15 Days';
    //         if ($age <= 30)
    //             return '16–30 Days';
    //         if ($age <= 45)
    //             return '31–45 Days';
    //         return '>45 Days';
    //     });

    //     $finalData = collect();

    //     foreach ($groupedData as $bucket => $rows) {
    //         $subtotalAmount = 0;
    //         $subtotalDue = 0;

    //         foreach ($rows as $row) {
    //             $subtotalAmount += $row->total_amount;
    //             $subtotalDue += $row->balance_due;
    //             $finalData->push($row);
    //         }

    //         // Add subtotal row for this bucket
    //         $finalData->push((object) [
    //             'bucket' => $bucket,
    //             'due_date' => '',
    //             'transaction' => '<strong>Subtotal for ' . $bucket . '</strong>',
    //             'type' => '',
    //             'status_label' => '',
    //             'customer' => '',
    //             'age' => '',
    //             'total_amount' => $subtotalAmount,
    //             'balance_due' => $subtotalDue,
    //             'isSubtotal' => true,
    //         ]);

    //         $grandTotalAmount += $subtotalAmount;
    //         $grandBalanceDue += $subtotalDue;
    //     }

    //     // Add grand total row
    //     $finalData->push((object) [
    //         'bucket' => '',
    //         'due_date' => '',
    //         'transaction' => '<strong>Grand Total</strong>',
    //         'type' => '',
    //         'status_label' => '',
    //         'customer' => '',
    //         'age' => '',
    //         'total_amount' => $grandTotalAmount,
    //         'balance_due' => $grandBalanceDue,
    //         'isGrandTotal' => true,
    //     ]);

    //     return datatables()
    //         ->collection($finalData)
    //         ->addColumn('bucket', fn($row) => $row->bucket ?? '')
    //         ->addColumn('transaction', fn($row) => $row->transaction ?? '-')
    //         ->addColumn('type', fn($row) => $row->type ?? 'Invoice')
    //         ->addColumn('status_label', fn($row) => $row->status_label ?? '')
    //         ->addColumn('customer', fn($row) => $row->customer->name ?? ($row->isSubtotal ?? $row->isGrandTotal ? '' : '-'))
    //         ->addColumn('age', fn($row) => $row->age ?? '')
    //         ->editColumn('total_amount', fn($row) => number_format($row->total_amount, 2))
    //         ->editColumn('balance_due', fn($row) => number_format($row->balance_due, 2))
    //         ->rawColumns(['transaction']);
    // }

    public function query()
    {
        return Invoice::with('customer')
            ->where('created_by', \Auth::user()->creatorId());
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('aging-details-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(0, 'asc')
            ->parameters([
                'paging' => false,
                'searching' => false,
                'info' => true,
                'ordering' => false,
                'rowGroup' => [
                    'dataSrc' => 'bucket',
                ],
                'footerCallback' => <<<JS
function (row, data, start, end, display) {
    var api = this.api();

    // Helper function to parse number
    var parseVal = function (i) {
        return typeof i === 'string'
            ? parseFloat(i.replace(/[^0-9.-]+/g, '')) || 0
            : typeof i === 'number'
                ? i
                : 0;
    };

    // Total over all pages
    var totalAmount = api.column(7, { page: 'all'}).data()
        .reduce((a, b) => parseVal(a) + parseVal(b), 0);

    var totalDue = api.column(8, { page: 'all'}).data()
        .reduce((a, b) => parseVal(a) + parseVal(b), 0);

    // Update footer
    $(api.column(7).footer()).html(totalAmount.toLocaleString());
    $(api.column(8).footer()).html(totalDue.toLocaleString());
}
JS
            ]);
    }

    protected function getColumns()
    {
        return [
            Column::make('bucket')->title('Bucket')->visible(false),
            Column::make('due_date')->title('Date'),
            Column::make('transaction')->title('Transaction'),
            Column::make('type')->title('Type'),
            Column::make('status_label')->title('Status'),
            Column::make('customer')->title('Customer Name'),
            Column::make('age')->title('Age'),
            Column::make('total_amount')->title('Amount'),
            Column::make('balance_due')->title('Balance Due'),
        ];
    }

    // protected function filename()
    // {
    //     return 'AgingDetails_' . date('YmdHis');
    // }
}
