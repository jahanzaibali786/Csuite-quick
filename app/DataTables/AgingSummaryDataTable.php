<?php

namespace App\DataTables;

use App\Models\Invoice;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\Html\Column;
use Carbon\Carbon;

class AgingSummaryDataTable extends DataTable
{
    public function dataTable($query)
    {
        $data = collect();

        // Fetch entries safely
        $entries = $query->get();

        // Track totals
        $currentTotal   = 0;
        $days15Total    = 0;
        $days30Total    = 0;
        $days45Total    = 0;
        $daysMore45Total= 0;
        $grandTotal     = 0;

        if ($entries->count() > 0) {
            foreach ($entries as $entry) {
                $current   = $entry->current ?? 0;
                $days15    = $entry->days_1_15 ?? 0;
                $days30    = $entry->days_16_30 ?? 0;
                $days45    = $entry->days_31_45 ?? 0;
                $daysMore45= $entry->days_45_plus ?? 0;
                $totalDue  = $entry->total_due ?? 0;

                // Add row
                $data->push([
                    'customer_name' => $entry->customer_name,
                    'current'       => number_format($current, 2),
                    'days_1_15'     => number_format($days15, 2),
                    'days_16_30'    => number_format($days30, 2),
                    'days_31_45'    => number_format($days45, 2),
                    'days_45_plus'  => number_format($daysMore45, 2),
                    'total_due'     => '<strong>' . number_format($totalDue, 2) . '</strong>',
                ]);

                // Update totals
                $currentTotal    += $current;
                $days15Total     += $days15;
                $days30Total     += $days30;
                $days45Total     += $days45;
                $daysMore45Total += $daysMore45;
                $grandTotal      += $totalDue;
            }

            // Add totals row
            $data->push([
                'customer_name' => '<strong>Total</strong>',
                'current'       => '<strong>' . number_format($currentTotal, 2) . '</strong>',
                'days_1_15'     => '<strong>' . number_format($days15Total, 2) . '</strong>',
                'days_16_30'    => '<strong>' . number_format($days30Total, 2) . '</strong>',
                'days_31_45'    => '<strong>' . number_format($days45Total, 2) . '</strong>',
                'days_45_plus'  => '<strong>' . number_format($daysMore45Total, 2) . '</strong>',
                'total_due'     => '<strong>' . number_format($grandTotal, 2) . '</strong>',
                'DT_RowClass'   => 'summary-total'
            ]);
        } else {
            // No data case
            $data->push([
                'customer_name' => 'No data found for the selected period.',
                'current'       => '',
                'days_1_15'     => '',
                'days_16_30'    => '',
                'days_31_45'    => '',
                'days_45_plus'  => '',
                'total_due'     => '',
                'DT_RowClass'   => 'no-data-row'
            ]);
        }

        return datatables()
            ->collection($data)
            ->rawColumns([
                'customer_name',
                'current',
                'days_1_15',
                'days_16_30',
                'days_31_45',
                'days_45_plus',
                'total_due'
            ]);
    }

    public function query(Invoice $model)
    {
        $start = request()->get('start_date') ?? Carbon::now()->startOfYear()->format('Y-m-d');
        $end   = request()->get('end_date') ?? Carbon::now()->endOfDay()->format('Y-m-d');
        $today = Carbon::now()->format('Y-m-d');

        return $model->newQuery()
            ->select('customers.name as customer_name')
            ->selectRaw("
                SUM(CASE 
                    WHEN DATEDIFF('$today', invoices.due_date) <= 0
                    THEN (invoice_products.price * invoice_products.quantity - invoice_products.discount)
                    ELSE 0 END
                ) as current
            ")
            ->selectRaw("
                SUM(CASE 
                    WHEN DATEDIFF('$today', invoices.due_date) BETWEEN 1 AND 15
                    THEN (invoice_products.price * invoice_products.quantity - invoice_products.discount)
                    ELSE 0 END
                ) as days_1_15
            ")
            ->selectRaw("
                SUM(CASE 
                    WHEN DATEDIFF('$today', invoices.due_date) BETWEEN 16 AND 30
                    THEN (invoice_products.price * invoice_products.quantity - invoice_products.discount)
                    ELSE 0 END
                ) as days_16_30
            ")
            ->selectRaw("
                SUM(CASE 
                    WHEN DATEDIFF('$today', invoices.due_date) BETWEEN 31 AND 45
                    THEN (invoice_products.price * invoice_products.quantity - invoice_products.discount)
                    ELSE 0 END
                ) as days_31_45
            ")
            ->selectRaw("
                SUM(CASE 
                    WHEN DATEDIFF('$today', invoices.due_date) > 45
                    THEN (invoice_products.price * invoice_products.quantity - invoice_products.discount)
                    ELSE 0 END
                ) as days_45_plus
            ")
            ->selectRaw("
                SUM((invoice_products.price * invoice_products.quantity) - invoice_products.discount) as total_due
            ")
            ->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id')
            ->leftJoin('invoice_products', 'invoice_products.invoice_id', '=', 'invoices.id')
            ->where('invoices.created_by', \Auth::user()->creatorId())
            ->whereBetween('invoices.issue_date', [$start, $end])
            ->groupBy('customers.name');
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('aging-summary-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(0, 'desc')
            ->parameters([
                'paging'         => false,
                'searching'      => false,
                'info'           => false,
                'ordering'       => false,
                'scrollY'        => '500px',
                'colReorder'     => true,
                'scrollCollapse' => true,
                'createdRow'     => "function(row, data) {
                    $('td:eq(1), td:eq(2), td:eq(3), td:eq(4), td:eq(5), td:eq(6)', row).addClass('text-right');
                    if ($(row).hasClass('summary-total')) {
                        $(row).addClass('font-weight-bold bg-light');
                    }
                }"
            ]);
    }

    protected function getColumns()
    {
        return [
            Column::make('customer_name')->title('Customer Name'),
            Column::make('current')->title('Current'),
            Column::make('days_1_15')->title('1-15 DAYS'),
            Column::make('days_16_30')->title('16-30 DAYS'),
            Column::make('days_31_45')->title('31-45 DAYS'),
            Column::make('days_45_plus')->title('> 45 DAYS'),
            Column::make('total_due')->title('Total'),
        ];
    }
}
