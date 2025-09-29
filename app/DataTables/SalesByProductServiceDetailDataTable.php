<?php

namespace App\DataTables;

use App\Models\InvoiceProduct;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class SalesByProductServiceDetailDataTable extends DataTable
{
    public function dataTable($query)
    {
        $user = Auth::user();

        return datatables()
            ->eloquent($query)
            ->addColumn('transaction_date', fn($r) => \Carbon\Carbon::parse($r->transaction_date)->format('m/d/Y'))
            ->addColumn('transaction_type', fn($r) => $r->transaction_type ?? 'Invoice')
            ->addColumn('num', fn($r) => $r->invoice_number ?? '-')
            ->addColumn('customer_full_name', fn($r) => $r->customer_name ?? '-')
            ->addColumn('memo_description', fn($r) => $r->description ?? '-')
            ->addColumn('quantity', fn($r) => number_format($r->quantity ?? 0, 2))
            ->addColumn('sales_price', fn($r) => $user->priceFormat($r->price ?? 0))
            ->addColumn('amount', function($r) use ($user) {
                // Calculate amount: (price * quantity) - discount + tax
                $baseAmount = ($r->price ?? 0) * ($r->quantity ?? 0);
                $discount = $r->discount ?? 0;
                $taxAmount = $this->calculateTaxAmount($r);
                $amount = $baseAmount - $discount + $taxAmount;
                return $user->priceFormat($amount);
            })
            ->addColumn('balance', function($r) use ($user) {
                // Running balance calculation will be handled by grouping
                $baseAmount = ($r->price ?? 0) * ($r->quantity ?? 0);
                $discount = $r->discount ?? 0;
                $taxAmount = $this->calculateTaxAmount($r);
                $amount = $baseAmount - $discount + $taxAmount;
                return $user->priceFormat($amount);
            })
            ->with('groupedData', function () use ($query) {
                // Group data by product/service for the hierarchical display
                try {
                    $rows = (clone $query)->get();
                    $grouped = $rows->groupBy('product_name');
                    
                    $groupedData = [];
                    foreach ($grouped as $productName => $transactions) {
                        $totalAmount = 0;
                        $transactionData = [];
                        
                        foreach ($transactions as $transaction) {
                            $baseAmount = ($transaction->price ?? 0) * ($transaction->quantity ?? 0);
                            $discount = $transaction->discount ?? 0;
                            $taxAmount = $this->calculateTaxAmount($transaction);
                            $amount = $baseAmount - $discount + $taxAmount;
                            $totalAmount += $amount;
                            
                            $transactionData[] = [
                                'transaction_date' => $transaction->transaction_date,
                                'transaction_type' => $transaction->transaction_type ?? 'Invoice',
                                'num' => $transaction->invoice_number ?? '-',
                                'customer_full_name' => $transaction->customer_name ?? '-',
                                'memo_description' => $transaction->description ?? '-',
                                'quantity' => $transaction->quantity ?? 0,
                                'sales_price' => $transaction->price ?? 0,
                                'amount' => $amount,
                                'balance' => $totalAmount // Running total for this product
                            ];
                        }
                        
                        $groupedData[$productName] = [
                            'transactions' => $transactionData,
                            'total' => $totalAmount,
                            'count' => count($transactionData)
                        ];
                    }
                    
                    \Log::info('SalesByProductService Detail grouped data calculated:', [
                        'products' => count($groupedData),
                        'total_transactions' => $rows->count()
                    ]);
                    
                    return $groupedData;
                } catch (\Exception $e) {
                    \Log::error('Error calculating sales by product service detail data: ' . $e->getMessage());
                    return [];
                }
            })
            ->rawColumns(['transaction_date', 'transaction_type', 'num', 'customer_full_name', 'memo_description', 'quantity', 'sales_price', 'amount', 'balance']);
    }

    private function calculateTaxAmount($transaction)
    {
        if (empty($transaction->tax)) {
            return 0;
        }
        
        $taxIds = explode(',', $transaction->tax);
        $totalTaxRate = 0;
        
        foreach ($taxIds as $taxId) {
            $tax = DB::table('taxes')->where('id', $taxId)->first();
            if ($tax) {
                $totalTaxRate += $tax->rate;
            }
        }
        
        $baseAmount = ($transaction->price ?? 0) * ($transaction->quantity ?? 0) - ($transaction->discount ?? 0);
        return ($baseAmount * $totalTaxRate) / 100;
    }

    public function query()
    {
        $user = Auth::user();
        $ownerId = $user->type === 'company' ? $user->creatorId() : $user->ownedId();

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
        \Log::info('SalesByProductService Detail Date Filters Applied:', [
            'report_period' => $reportPeriod,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'request_start' => request('start_date'),
            'request_end' => request('end_date')
        ]);

        // Main query using InvoiceProduct model with all necessary joins
        $model = new \App\Models\InvoiceProduct();
        $q = $model->newQuery()
            ->join('invoices as i', 'i.id', '=', 'invoice_products.invoice_id')
            ->join('product_services as ps', 'ps.id', '=', 'invoice_products.product_id')
            ->join('customers as c', 'c.id', '=', 'i.customer_id')
            ->select([
                'invoice_products.*',
                'i.issue_date as transaction_date',
                'i.invoice_id as invoice_number',
                'ps.name as product_name',
                'c.name as customer_name',
                DB::raw("'Invoice' as transaction_type")
            ])
            ->where('i.created_by', $ownerId)
            ->where('i.status', '!=', 0); // Only include non-draft invoices
            
        // Apply date filters (only if dates are provided)
        if ($startDate && $startDate !== '') {
            $q->whereDate('i.issue_date', '>=', $startDate);
            \Log::info('Applied detail start date filter: ' . $startDate);
        }
        if ($endDate && $endDate !== '') {
            $q->whereDate('i.issue_date', '<=', $endDate);
            \Log::info('Applied detail end date filter: ' . $endDate);
        }

        // Apply product/service name filter if provided
        if (request()->filled('product_name') && request('product_name') !== '') {
            $q->where('ps.name', 'like', '%' . request('product_name') . '%');
        }

        // Apply customer filter if provided
        if (request()->filled('customer_name') && request('customer_name') !== '') {
            $q->where('c.name', 'like', '%' . request('customer_name') . '%');
        }

        // Apply category filter if provided
        if (request()->filled('category') && request('category') !== '') {
            $q->where('ps.category_id', request('category'));
        }

        // Apply type filter if provided
        if (request()->filled('type') && request('type') !== '') {
            $q->where('ps.type', request('type'));
        }

        return $q->orderBy('ps.name', 'ASC')
                ->orderBy('i.issue_date', 'DESC');
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
            ->setTableId('sales-by-product-service-detail-table')
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
                'scrollX'        => true,
                'scrollCollapse' => true,
            ]);
    }

    protected function getColumns()
    {
        return [
            Column::make('transaction_date')->data('transaction_date')->name('transaction_date')->title(__('Transaction Date'))->addClass('text-left'),
            Column::make('transaction_type')->data('transaction_type')->name('transaction_type')->title(__('Transaction Type'))->addClass('text-left'),
            Column::make('num')->data('num')->name('num')->title(__('Num'))->addClass('text-left'),
            Column::make('customer_full_name')->data('customer_full_name')->name('customer_full_name')->title(__('Customer Full Name'))->addClass('text-left'),
            Column::make('memo_description')->data('memo_description')->name('memo_description')->title(__('Memo/Description'))->addClass('text-left'),
            Column::make('quantity')->data('quantity')->name('quantity')->title(__('Quantity'))->addClass('text-right'),
            Column::make('sales_price')->data('sales_price')->name('sales_price')->title(__('Sales Price'))->addClass('text-right'),
            Column::make('amount')->data('amount')->name('amount')->title(__('Amount'))->addClass('text-right'),
            Column::make('balance')->data('balance')->name('balance')->title(__('Balance'))->addClass('text-right'),
        ];
    }

    protected function filename(): string
    {
        return 'SalesByProductServiceDetail_' . date('YmdHis');
    }
}