<?php

namespace App\DataTables;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaxableSalesSummaryDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $dt = new EloquentDataTable($query);

        // Build children (all taxable invoices per product) once and attach to payload
        $children = $this->childrenData(); // [product_service_id => [rows...]]

        return $dt
            ->addColumn('control', function ($r) {
                return '<button class="btn btn-sm btn-light toggle-child" data-product-id="'.$r->product_service_id.'">
                            <i class="fa fa-angle-right me-1"></i>
                        </button>';
            })
            ->editColumn('product_service', fn($r) => $r->product_service)
            ->editColumn('taxable_amount',  fn($r) => (float) $r->taxable_amount) // raw numbers; format in JS
            ->editColumn('tax_amount',      fn($r) => (float) $r->tax_amount)
            ->rawColumns(['control'])
            ->escapeColumns([])
            ->with([
                'children' => $children,
                'currency_symbol' => Auth::user()->currencySymbol(),
            ]);
    }

    public function query(): QueryBuilder
    {
        $user = Auth::user();
        $ownerId     = $user->type === 'company' ? $user->creatorId() : $user->ownedId();
        $ownerColumn = $user->type === 'company' ? 'invoices.created_by' : 'invoices.owned_by';

        $startDate        = $this->request()->get('start_date');
        $endDate          = $this->request()->get('end_date');
        $reportPeriod     = $this->request()->get('report_period', 'all_dates');
        $accountingMethod = $this->request()->get('accounting_method', 'accrual');
        $selectedCustomer = $this->request()->get('customer_name');
        $selectedCategory = $this->request()->get('category');
        $selectedType     = $this->request()->get('type');
        $selectedProdName = $this->request()->get('product_name');

        $q = Invoice::query()
            ->where($ownerColumn, $ownerId)
            ->whereIn('invoices.status', [1,2,3,4])
            ->join('invoice_products','invoices.id','=','invoice_products.invoice_id')
            ->join('product_services','invoice_products.product_id','=','product_services.id')
            ->join('customers','invoices.customer_id','=','customers.id')
            ->whereNotNull('invoice_products.tax')
            ->where('invoice_products.tax','!=','')
            ->selectRaw('
                product_services.id   as product_service_id,
                product_services.name as product_service,
                SUM(invoice_products.price * invoice_products.quantity) as taxable_amount,
                SUM(
                    (invoice_products.price * invoice_products.quantity) *
                    (
                        (SELECT COALESCE(SUM(t.rate),0)
                         FROM taxes t
                         WHERE FIND_IN_SET(t.id, invoice_products.tax)) / 100
                    )
                ) as tax_amount
            ')
            ->groupBy('product_services.id','product_services.name');

        // Dates
        if ($accountingMethod === 'cash') {
            $q->leftJoin('invoice_payments','invoices.id','=','invoice_payments.invoice_id');
            if ($startDate && $endDate) {
                $q->whereBetween('invoice_payments.date', [$startDate, $endDate]);
            } else {
                [$s,$e] = $this->getDateRange($reportPeriod);
                $q->whereBetween('invoice_payments.date', [$s,$e]);
            }
        } else {
            if ($startDate && $endDate) {
                $q->whereBetween('invoices.issue_date', [$startDate, $endDate]);
            } else {
                [$s,$e] = $this->getDateRange($reportPeriod);
                $q->whereBetween('invoices.issue_date', [$s,$e]);
            }
        }

        // Filters
        if (!empty($selectedCustomer)) $q->where('customers.name','like','%'.$selectedCustomer.'%');
        if (!empty($selectedCategory)) $q->where('product_services.category_id',$selectedCategory);
        if (!empty($selectedType))     $q->where('product_services.type',$selectedType);
        if (!empty($selectedProdName)) $q->where('product_services.name','like','%'.$selectedProdName.'%');

        return $q->orderBy('product_services.name');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('taxable-sales-summary-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('t')
            ->paging(false)
            ->ordering(false);
    }

    protected function getColumns(): array
    {
        return [
            Column::computed('control')->title('')->width(90)->addClass('text-start'),
            Column::make('product_service')->title(__('Product/Service'))->addClass('text-start'),
            Column::make('taxable_amount')->title(__('Taxable Amount'))->addClass('text-end'),
            Column::make('tax_amount')->title(__('Tax'))->addClass('text-end'),
        ];
    }

    protected function filename(): string
    {
        return 'TaxableSalesSummary_'.date('YmdHis');
    }

    /** Build children map: one row per (product × invoice) with taxable & tax totals */
    private function childrenData(): array
    {
        $user = Auth::user();
        $ownerId     = $user->type === 'company' ? $user->creatorId() : $user->ownedId();
        $ownerColumn = $user->type === 'company' ? 'invoices.created_by' : 'invoices.owned_by';

        $startDate        = $this->request()->get('start_date');
        $endDate          = $this->request()->get('end_date');
        $reportPeriod     = $this->request()->get('report_period', 'all_dates');
        $accountingMethod = $this->request()->get('accounting_method', 'accrual');
        $selectedCustomer = $this->request()->get('customer_name');
        $selectedCategory = $this->request()->get('category');
        $selectedType     = $this->request()->get('type');
        $selectedProdName = $this->request()->get('product_name');

        $q = DB::table('invoices')
            ->where($ownerColumn, $ownerId)
            // ->whereIn('invoices.status', [1,2,3,4])
            ->join('invoice_products','invoices.id','=','invoice_products.invoice_id')
            ->join('product_services','invoice_products.product_id','=','product_services.id')
            ->join('customers','invoices.customer_id','=','customers.id')
            ->whereNotNull('invoice_products.tax')
            ->where('invoice_products.tax','!=','')
            ->selectRaw('
                product_services.id   as product_service_id,
                product_services.name as product_service,
                invoices.id           as invoice_db_id,
                invoices.invoice_id   as invoice_number,
                invoices.issue_date   as issue_date,
                customers.name        as customer_name,
                SUM(invoice_products.price * invoice_products.quantity) as taxable_amount,
                SUM(
                    (invoice_products.price * invoice_products.quantity) *
                    (
                        (SELECT COALESCE(SUM(t.rate),0)
                         FROM taxes t
                         WHERE FIND_IN_SET(t.id, invoice_products.tax)) / 100
                    )
                ) as tax_amount
            ')
            ->groupBy([
                'product_services.id', 'product_services.name',
                'invoices.id', 'invoices.invoice_id', 'invoices.issue_date',
                'customers.name',
            ]);

        // Dates
        if ($accountingMethod === 'cash') {
            $q->leftJoin('invoice_payments','invoices.id','=','invoice_payments.invoice_id');
            if ($startDate && $endDate) $q->whereBetween('invoice_payments.date', [$startDate, $endDate]);
            else { [$s,$e] = $this->getDateRange($reportPeriod); $q->whereBetween('invoice_payments.date', [$s,$e]); }
        } else {
            if ($startDate && $endDate) $q->whereBetween('invoices.issue_date', [$startDate, $endDate]);
            else { [$s,$e] = $this->getDateRange($reportPeriod); $q->whereBetween('invoices.issue_date', [$s,$e]); }
        }

        // Filters
        if (!empty($selectedCustomer)) $q->where('customers.name','like','%'.$selectedCustomer.'%');
        if (!empty($selectedCategory)) $q->where('product_services.category_id',$selectedCategory);
        if (!empty($selectedType))     $q->where('product_services.type',$selectedType);
        if (!empty($selectedProdName)) $q->where('product_services.name','like','%'.$selectedProdName.'%');

        $rows = $q->orderBy('product_services.name')->orderBy('invoices.issue_date')->get();

        // Group by product_service_id
        $map = [];
        foreach ($rows as $r) {
            $pid = $r->product_service_id;
            if (!isset($map[$pid])) $map[$pid] = [];
            $map[$pid][] = [
                'invoice_db_id'  => $r->invoice_db_id,
                'invoice_number' => $r->invoice_number,
                'issue_date'     => $r->issue_date,
                'customer_name'  => $r->customer_name,
                'taxable_amount' => (float)$r->taxable_amount,
                'tax_amount'     => (float)$r->tax_amount,
            ];
        }
        return $map;
    }

    private function getDateRange($period)
    {
        $now = now();
        return match ($period) {
            'today'        => [$now->toDateString(), $now->toDateString()],
            'this_week'    => [$now->copy()->startOfWeek()->toDateString(), $now->copy()->endOfWeek()->toDateString()],
            'this_month'   => [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()],
            'this_quarter' => [$now->copy()->startOfQuarter()->toDateString(), $now->copy()->endOfQuarter()->toDateString()],
            'this_year'    => [$now->copy()->startOfYear()->toDateString(), $now->copy()->endOfYear()->toDateString()],
            'last_week'    => [$now->copy()->subWeek()->startOfWeek()->toDateString(), $now->copy()->subWeek()->endOfWeek()->toDateString()],
            'last_month'   => [$now->copy()->subMonth()->startOfMonth()->toDateString(), $now->copy()->subMonth()->endOfMonth()->toDateString()],
            'last_quarter' => [$now->copy()->subQuarter()->startOfQuarter()->toDateString(), $now->copy()->subQuarter()->endOfQuarter()->toDateString()],
            'last_year'    => [$now->copy()->subYear()->startOfYear()->toDateString(), $now->copy()->subYear()->endOfYear()->toDateString()],
            default        => ['2000-01-01', $now->toDateString()],
        };
    }
}
