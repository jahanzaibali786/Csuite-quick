<?php

namespace App\DataTables;

use App\Models\ProductService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class SalesByProductServiceSummaryDataTable extends DataTable
{
    public function dataTable($query)
    {
        $user = Auth::user();

        return datatables()
            ->eloquent($query)
            ->addColumn('product_service', fn($r) => $r->name ?? '-')
            ->addColumn('quantity', fn($r) => number_format($r->total_quantity ?? 0, 0))
            ->addColumn('amount', fn($r) => $user->priceFormat($r->total_amount ?? 0))
            ->addColumn('percent_of_sales', function($r) use ($query) {
                // Calculate percentage of total sales
                $totalSales = (clone $query)->sum('total_amount');
                $percentage = $totalSales > 0 ? (($r->total_amount ?? 0) / $totalSales) * 100 : 0;
                return number_format($percentage, 1) . '%';
            })
            ->addColumn('average_price', function($r) {
                $avgPrice = ($r->total_quantity ?? 0) > 0 ? ($r->total_amount ?? 0) / ($r->total_quantity ?? 0) : 0;
                return Auth::user()->priceFormat($avgPrice);
            })
            ->addColumn('cogs', function($r) use ($user) {
                // Cost of Goods Sold = Purchase Price * Quantity Sold
                $cogs = ($r->purchase_price ?? 0) * ($r->total_quantity ?? 0);
                return $user->priceFormat($cogs);
            })
            ->addColumn('avg_cogs', function($r) use ($user) {
                return $user->priceFormat($r->purchase_price ?? 0);
            })
            ->addColumn('gross_margin', function($r) use ($user) {
                $cogs = ($r->purchase_price ?? 0) * ($r->total_quantity ?? 0);
                $grossMargin = ($r->total_amount ?? 0) - $cogs;
                return $user->priceFormat($grossMargin);
            })
            ->addColumn('gross_margin_percent', function($r) {
                $cogs = ($r->purchase_price ?? 0) * ($r->total_quantity ?? 0);
                $grossMargin = ($r->total_amount ?? 0) - $cogs;
                $grossMarginPercent = ($r->total_amount ?? 0) > 0 ? ($grossMargin / ($r->total_amount ?? 0)) * 100 : 0;
                return number_format($grossMarginPercent, 1) . '%';
            })
            ->with('totals', function () use ($query) {
                // Compute totals on the same base query
                try {
                    $rows = (clone $query)->get();
                    $totalQuantity = (float) $rows->sum('total_quantity');
                    $totalAmount = (float) $rows->sum('total_amount');
                    $totalCogs = (float) $rows->sum(function($row) {
                        return ($row->purchase_price ?? 0) * ($row->total_quantity ?? 0);
                    });
                    $totalGrossMargin = $totalAmount - $totalCogs;
                    $totalGrossMarginPercent = $totalAmount > 0 ? ($totalGrossMargin / $totalAmount) * 100 : 0;
                    
                    \Log::info('SalesByProductService DataTable Totals calculated:', [
                        'quantity' => $totalQuantity,
                        'amount' => $totalAmount,
                        'cogs' => $totalCogs,
                        'gross_margin' => $totalGrossMargin,
                        'gross_margin_percent' => $totalGrossMarginPercent
                    ]);
                    
                    return [
                        'quantity' => $totalQuantity,
                        'amount' => $totalAmount,
                        'cogs' => $totalCogs,
                        'gross_margin' => $totalGrossMargin,
                        'gross_margin_percent' => $totalGrossMarginPercent
                    ];
                } catch (\Exception $e) {
                    \Log::error('Error calculating sales by product service totals: ' . $e->getMessage());
                    return [
                        'quantity' => 0,
                        'amount' => 0,
                        'cogs' => 0,
                        'gross_margin' => 0,
                        'gross_margin_percent' => 0
                    ];
                }
            })
            ->rawColumns(['product_service', 'quantity', 'amount', 'percent_of_sales', 'average_price', 'cogs', 'avg_cogs', 'gross_margin', 'gross_margin_percent']);
    }

    public function query()
    {
        $user = Auth::user();
        $ownerId = $user->type === 'company' ? $user->creatorId() : $user->ownedId();
        $column = ($user->type == 'company') ? 'created_by' : 'owned_by';

        // Get date range filters
        $startDate = request('start_date');
        $endDate = request('end_date');
        $reportPeriod = request('report_period', 'all_dates');
        
        // Calculate date range based on report period (only if not 'all_dates')
        if ($reportPeriod && $reportPeriod !== 'all_dates' && $reportPeriod !== 'custom') {
            $dates = $this->calculateDateRange($reportPeriod);
            $startDate = $dates['start'];
            $endDate = $dates['end'];
        }
        
        // Debug the date values
        \Log::info('SalesByProductService Date Filters Applied:', [
            'report_period' => $reportPeriod,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'request_start' => request('start_date'),
            'request_end' => request('end_date')
        ]);

        // Create a subquery for invoice products with calculated totals
        $invoiceProductsSubquery = DB::table('invoice_products as ip')
            ->join('invoices as i', 'i.id', '=', 'ip.invoice_id')
            ->select(
                'ip.product_id',
                DB::raw('SUM(ip.quantity) as total_quantity'),
                DB::raw('SUM(
                    (ip.price * ip.quantity - COALESCE(ip.discount, 0)) + 
                    COALESCE((
                        SELECT SUM((ipp.price * ipp.quantity - COALESCE(ipp.discount, 0)) * (COALESCE(t.rate, 0) / 100))
                        FROM invoice_products ipp
                        LEFT JOIN taxes t ON FIND_IN_SET(t.id, ipp.tax) > 0
                        WHERE ipp.id = ip.id
                    ), 0)
                ) as total_amount')
            )
            ->where('i.created_by', $ownerId)
            ->where('i.status', '!=', 0); // Only include non-draft invoices
            
        // Apply date filters to invoice subquery (only if dates are provided)
        if ($startDate && $startDate !== '') {
            $invoiceProductsSubquery->whereDate('i.issue_date', '>=', $startDate);
            \Log::info('Applied invoice start date filter: ' . $startDate);
        }
        if ($endDate && $endDate !== '') {
            $invoiceProductsSubquery->whereDate('i.issue_date', '<=', $endDate);
            \Log::info('Applied invoice end date filter: ' . $endDate);
        }
        
        $invoiceProductsSubquery->groupBy('ip.product_id');

        // Main query using Eloquent ProductService model
        $model = new \App\Models\ProductService();
        $q = $model->newQuery()
            ->where('product_services.' . $column, $ownerId)
            ->leftJoinSub($invoiceProductsSubquery, 'sales', function($join) {
                $join->on('product_services.id', '=', 'sales.product_id');
            })
            ->select([
                'product_services.*',
                DB::raw('COALESCE(sales.total_quantity, 0) as total_quantity'),
                DB::raw('COALESCE(sales.total_amount, 0) as total_amount'),
            ])
            ->having('total_quantity', '>', 0); // Only show products that have been sold

        // Apply product/service name filter if provided
        if (request()->filled('product_name') && request('product_name') !== '') {
            $q->where('product_services.name', 'like', '%' . request('product_name') . '%');
        }

        // Apply category filter if provided
        if (request()->filled('category') && request('category') !== '') {
            $q->where('product_services.category_id', request('category'));
        }

        // Apply type filter if provided
        if (request()->filled('type') && request('type') !== '') {
            $q->where('product_services.type', request('type'));
        }

        return $q->orderBy('total_amount', 'DESC');
    }
    
    private function calculateDateRange($period)
    {
        $today = \Carbon\Carbon::today();
        
        switch ($period) {
            case 'today':
                return ['start' => $today->format('Y-m-d'), 'end' => $today->format('Y-m-d')];
            case 'this_week':
                return ['start' => $today->startOfWeek()->format('Y-m-d'), 'end' => $today->endOfWeek()->format('Y-m-d')];
            case 'this_month':
                return ['start' => $today->startOfMonth()->format('Y-m-d'), 'end' => $today->endOfMonth()->format('Y-m-d')];
            case 'this_quarter':
                return ['start' => $today->startOfQuarter()->format('Y-m-d'), 'end' => $today->endOfQuarter()->format('Y-m-d')];
            case 'this_year':
                return ['start' => $today->startOfYear()->format('Y-m-d'), 'end' => $today->endOfYear()->format('Y-m-d')];
            case 'last_week':
                $lastWeek = $today->subWeek();
                return ['start' => $lastWeek->startOfWeek()->format('Y-m-d'), 'end' => $lastWeek->endOfWeek()->format('Y-m-d')];
            case 'last_month':
                $lastMonth = $today->subMonth();
                return ['start' => $lastMonth->startOfMonth()->format('Y-m-d'), 'end' => $lastMonth->endOfMonth()->format('Y-m-d')];
            case 'last_quarter':
                $lastQuarter = $today->subQuarter();
                return ['start' => $lastQuarter->startOfQuarter()->format('Y-m-d'), 'end' => $lastQuarter->endOfQuarter()->format('Y-m-d')];
            case 'last_year':
                $lastYear = $today->subYear();
                return ['start' => $lastYear->startOfYear()->format('Y-m-d'), 'end' => $lastYear->endOfYear()->format('Y-m-d')];
            case 'last_7_days':
                return ['start' => $today->subDays(7)->format('Y-m-d'), 'end' => \Carbon\Carbon::today()->format('Y-m-d')];
            case 'last_30_days':
                return ['start' => $today->subDays(30)->format('Y-m-d'), 'end' => \Carbon\Carbon::today()->format('Y-m-d')];
            case 'last_90_days':
                return ['start' => $today->subDays(90)->format('Y-m-d'), 'end' => \Carbon\Carbon::today()->format('Y-m-d')];
            case 'last_12_months':
                return ['start' => $today->subMonths(12)->format('Y-m-d'), 'end' => \Carbon\Carbon::today()->format('Y-m-d')];
            default:
                return ['start' => null, 'end' => null];
        }
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('sales-by-product-service-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('rt')
            ->parameters([
                'responsive'     => true,
                'autoWidth'      => false,
                'paging'         => false,
                'searching'      => false,
                'info'           => false,
                'ordering'       => false,
                'colReorder'     => true,
                'fixedHeader'    => true,
                'scrollY'        => '400px',
                'scrollX'        => false,
                'scrollCollapse' => true,
            ]);
    }

    protected function getColumns()
    {
        return [
            Column::make('product_service')->data('product_service')->name('product_service')->title(__('Product/Service'))->addClass('text-left'),
            Column::make('quantity')->data('quantity')->name('quantity')->title(__('Quantity'))->addClass('text-right'),
            Column::make('amount')->data('amount')->name('amount')->title(__('Amount'))->addClass('text-right'),
            Column::make('percent_of_sales')->data('percent_of_sales')->name('percent_of_sales')->title(__('% Of Sales'))->addClass('text-right'),
            Column::make('average_price')->data('average_price')->name('average_price')->title(__('Avg. Price'))->addClass('text-right'),
            Column::make('cogs')->data('cogs')->name('cogs')->title(__('COGS'))->addClass('text-right'),
            Column::make('avg_cogs')->data('avg_cogs')->name('avg_cogs')->title(__('Avg. COGS'))->addClass('text-right'),
            Column::make('gross_margin')->data('gross_margin')->name('gross_margin')->title(__('Gross Margin'))->addClass('text-right'),
            Column::make('gross_margin_percent')->data('gross_margin_percent')->name('gross_margin_percent')->title(__('Gross Margin %'))->addClass('text-right'),
        ];
    }

    protected function filename(): string
    {
        return 'SalesByProductServiceSummary_' . date('YmdHis');
    }
}