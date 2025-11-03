<?php

namespace App\DataTables;

use App\Models\ProductService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Carbon\Carbon;

class SalesByProductServiceSummaryDataTable extends DataTable
{
    public function dataTable($query)
    {
        $rows = $query->get();

        // === Compute Totals ===
        $totalQuantity = (float) $rows->sum('total_quantity');
        $totalAmount   = (float) $rows->sum('total_amount');
        $totalCogs     = (float) $rows->sum(fn($r) => ($r->purchase_price ?? 0) * ($r->total_quantity ?? 0));
        $totalGrossMargin = $totalAmount - $totalCogs;
        $totalGrossMarginPercent = $totalAmount > 0 ? ($totalGrossMargin / $totalAmount) * 100 : 0;

        $data = collect();

        foreach ($rows as $r) {
            $cogs = ($r->purchase_price ?? 0) * ($r->total_quantity ?? 0);
            $grossMargin = ($r->total_amount ?? 0) - $cogs;
            $grossMarginPercent = ($r->total_amount ?? 0) > 0 ? ($grossMargin / $r->total_amount) * 100 : 0;

            $data->push([
                'product_service' => e($r->name ?? '-'),
                'quantity' => number_format($r->total_quantity ?? 0, 2),
                'amount' => number_format($r->total_amount ?? 0, 2),
                'percent_of_sales' => $totalAmount > 0
                    ? number_format((($r->total_amount ?? 0) / $totalAmount) * 100, 1) . '%'
                    : '0.0%',
                'average_price' => number_format(($r->total_quantity ?? 0) > 0
                    ? ($r->total_amount / $r->total_quantity)
                    : 0, 2),
                'cogs' => number_format($cogs, 2),
                'avg_cogs' => number_format($r->purchase_price ?? 0, 2),
                'gross_margin' => number_format($grossMargin, 2),
                'gross_margin_percent' => number_format($grossMarginPercent, 1) . '%',
            ]);
        }

        // === Add Total Row ===
        if ($rows->count() > 0) {
            $data->push([
                'product_service' => '<strong>Total</strong>',
                'quantity' => '<strong>' . number_format($totalQuantity, 2) . '</strong>',
                'amount' => '<strong>' . number_format($totalAmount, 2) . '</strong>',
                'percent_of_sales' => '<strong>100%</strong>',
                'average_price' => '<strong>-</strong>',
                'cogs' => '<strong>' . number_format($totalCogs, 2) . '</strong>',
                'avg_cogs' => '<strong>-</strong>',
                'gross_margin' => '<strong>' . number_format($totalGrossMargin, 2) . '</strong>',
                'gross_margin_percent' => '<strong>' . number_format($totalGrossMarginPercent, 1) . '%</strong>',
                'DT_RowClass' => 'summary-total'
            ]);
        } else {
            $data->push([
                'product_service' => 'No data found for the selected period.',
                'quantity' => '',
                'amount' => '',
                'percent_of_sales' => '',
                'average_price' => '',
                'cogs' => '',
                'avg_cogs' => '',
                'gross_margin' => '',
                'gross_margin_percent' => '',
                'DT_RowClass' => 'no-data-row'
            ]);
        }

        return datatables()
            ->collection($data)
            ->rawColumns([
                'product_service',
                'quantity',
                'amount',
                'percent_of_sales',
                'average_price',
                'cogs',
                'avg_cogs',
                'gross_margin',
                'gross_margin_percent'
            ]);
    }

    public function query()
    {
        $user = Auth::user();
        $ownerId = $user->type === 'company' ? $user->creatorId() : $user->ownedId();
        $column = $user->type === 'company' ? 'created_by' : 'owned_by';

        // === Determine Date Range ===
        $reportPeriod = request('report_period', 'all_dates');
        $startDate = request('start_date') ?? request('startDate') ?? Carbon::now()->startOfYear()->format('Y-m-d');
        $endDate   = request('end_date') ?? request('endDate') ?? Carbon::now()->endOfDay()->format('Y-m-d');

        if ($reportPeriod && !in_array($reportPeriod, ['all_dates', 'custom'])) {
            $dates = $this->calculateDateRange($reportPeriod);
            $startDate = $dates['start'];
            $endDate   = $dates['end'];
        }

        // === Subquery: Invoices joined with products ===
        $invoiceProductsSubquery = DB::table('invoice_products as ip')
            ->join('invoices as i', 'i.id', '=', 'ip.invoice_id')
            ->leftJoin('taxes as t', DB::raw('FIND_IN_SET(t.id, ip.tax)'), '>', DB::raw('0'))
            ->select(
                'ip.product_id',
                DB::raw('SUM(ip.quantity) as total_quantity'),
                //DB::raw('SUM((ip.price * ip.quantity - COALESCE(ip.discount, 0)) + ((ip.price * ip.quantity - COALESCE(ip.discount, 0)) * COALESCE(t.rate, 0) / 100)) as total_amount')
                DB::raw('SUM(ip.price * ip.quantity - COALESCE(ip.discount, 0)) as total_amount')

            )
            ->where('i.' . $column, $ownerId)
            ->where('i.status', '!=', 0);

        if ($startDate) {
            $invoiceProductsSubquery->whereDate('i.issue_date', '>=', $startDate);
        }
        if ($endDate) {
            $invoiceProductsSubquery->whereDate('i.issue_date', '<=', $endDate);
        }

        $invoiceProductsSubquery->groupBy('ip.product_id');

        // === Main Query ===
        $model = new ProductService();
        $q = $model->newQuery()
            ->where('product_services.' . $column, $ownerId)
            ->leftJoinSub($invoiceProductsSubquery, 'sales', function ($join) {
                $join->on('product_services.id', '=', 'sales.product_id');
            })
            ->select([
                'product_services.*',
                DB::raw('COALESCE(sales.total_quantity, 0) as total_quantity'),
                DB::raw('COALESCE(sales.total_amount, 0) as total_amount'),
            ])
            ->having('total_quantity', '>', 0);

        // === Optional Filters ===
        if (request()->filled('product_name')) {
            $q->where('product_services.name', 'like', '%' . request('product_name') . '%');
        }
        if (request()->filled('category')) {
            $q->where('product_services.category_id', request('category'));
        }
        if (request()->filled('type')) {
            $q->where('product_services.type', request('type'));
        }

        return $q->orderBy('total_amount', 'DESC');
    }

    private function calculateDateRange($period)
    {
        $today = Carbon::today();

        return match ($period) {
            'today' => ['start' => $today->format('Y-m-d'), 'end' => $today->format('Y-m-d')],
            'this_week' => ['start' => $today->startOfWeek()->format('Y-m-d'), 'end' => $today->endOfWeek()->format('Y-m-d')],
            'this_month' => ['start' => $today->startOfMonth()->format('Y-m-d'), 'end' => $today->endOfMonth()->format('Y-m-d')],
            'this_quarter' => ['start' => $today->startOfQuarter()->format('Y-m-d'), 'end' => $today->endOfQuarter()->format('Y-m-d')],
            'this_year' => ['start' => $today->startOfYear()->format('Y-m-d'), 'end' => $today->endOfYear()->format('Y-m-d')],
            'last_week' => ['start' => $today->subWeek()->startOfWeek()->format('Y-m-d'), 'end' => $today->endOfWeek()->format('Y-m-d')],
            'last_month' => ['start' => $today->subMonth()->startOfMonth()->format('Y-m-d'), 'end' => $today->endOfMonth()->format('Y-m-d')],
            'last_quarter' => ['start' => $today->subQuarter()->startOfQuarter()->format('Y-m-d'), 'end' => $today->endOfQuarter()->format('Y-m-d')],
            'last_year' => ['start' => $today->subYear()->startOfYear()->format('Y-m-d'), 'end' => $today->endOfYear()->format('Y-m-d')],
            'last_7_days' => ['start' => Carbon::today()->subDays(7)->format('Y-m-d'), 'end' => Carbon::today()->format('Y-m-d')],
            'last_30_days' => ['start' => Carbon::today()->subDays(30)->format('Y-m-d'), 'end' => Carbon::today()->format('Y-m-d')],
            'last_90_days' => ['start' => Carbon::today()->subDays(90)->format('Y-m-d'), 'end' => Carbon::today()->format('Y-m-d')],
            'last_12_months' => ['start' => Carbon::today()->subMonths(12)->format('Y-m-d'), 'end' => Carbon::today()->format('Y-m-d')],
            default => ['start' => null, 'end' => null],
        };
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
                'scrollX' => false,
                'scrollCollapse' => true,
            ]);
    }

    protected function getColumns()
    {
        return [
            Column::make('product_service')->title(__('Product/Service'))->addClass('text-left'),
            Column::make('quantity')->title(__('Quantity'))->addClass('text-right'),
            Column::make('amount')->title(__('Amount'))->addClass('text-right'),
            Column::make('percent_of_sales')->title(__('% Of Sales'))->addClass('text-right'),
            Column::make('average_price')->title(__('Avg. Price'))->addClass('text-right'),
            Column::make('cogs')->title(__('COGS'))->addClass('text-right'),
            Column::make('avg_cogs')->title(__('Avg. COGS'))->addClass('text-right'),
            Column::make('gross_margin')->title(__('Gross Margin'))->addClass('text-right'),
            Column::make('gross_margin_percent')->title(__('Gross Margin %'))->addClass('text-right'),
        ];
    }

    protected function filename(): string
    {
        return 'SalesByProductServiceSummary_' . date('YmdHis');
    }
}
