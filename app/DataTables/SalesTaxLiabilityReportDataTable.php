<?php

namespace App\DataTables;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesTaxLiabilityReportDataTable extends DataTable
{
    /**
     * Build DataTable response.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $dt = new EloquentDataTable($query);

        // Build children (one row per invoice under each product/service)
        $children = $this->childrenData();

        return $dt
            // small caret button to expand/collapse
            ->addColumn('control', function ($r) {
                return '<button class="btn btn-sm btn-light toggle-child" '
                    .'data-product-id="'.$r->product_service_id.'">'
                    .'<i class="fa fa-angle-right me-1"></i></button>';
            })
            ->editColumn('product_service', fn($r) => $r->product_service)
            ->editColumn('amount',      fn($r) => (float)$r->amount)       // raw numbers; formatted on client
            ->editColumn('tax_amount',  fn($r) => (float)$r->tax_amount)
            ->rawColumns(['control'])
            ->escapeColumns([])
            ->with([
                'children'        => $children,                      // map: product_service_id => rows[]
                'currency_symbol' => Auth::user()->currencySymbol(), // for client-side formatting
            ]);
    }

    /**
     * Parent query: one row per product/service with totals across invoices.
     */
    public function query(): QueryBuilder
    {
        $user         = Auth::user();
        $ownerId      = $user->type === 'company' ? $user->creatorId() : $user->ownedId();
        $ownerColumn  = $user->type === 'company' ? 'invoices.created_by' : 'invoices.owned_by';

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
            // include ALL invoices (draft, partial, paid, etc.) – no status filter
            ->join('invoice_products', 'invoices.id', '=', 'invoice_products.invoice_id')
            ->join('product_services', 'invoice_products.product_id', '=', 'product_services.id')
            ->join('customers', 'invoices.customer_id', '=', 'customers.id')
            ->whereNotNull('invoice_products.tax')
            ->where('invoice_products.tax', '!=', '')
            ->selectRaw('
                product_services.id   as product_service_id,
                product_services.name as product_service,
                SUM(invoice_products.price * invoice_products.quantity) as amount,
                SUM(
                    (invoice_products.price * invoice_products.quantity) *
                    (
                        (SELECT COALESCE(SUM(t.rate),0)
                         FROM taxes t
                         WHERE FIND_IN_SET(t.id, invoice_products.tax)) / 100
                    )
                ) as tax_amount
            ')
            ->groupBy('product_services.id', 'product_services.name');

        // Date filters, honoring accounting method
        if ($accountingMethod === 'cash') {
            $q->leftJoin('invoice_payments', 'invoices.id', '=', 'invoice_payments.invoice_id');
            if ($startDate && $endDate) {
                $q->whereBetween('invoice_payments.date', [$startDate, $endDate]);
            } else {
                [$s, $e] = $this->getDateRange($reportPeriod);
                $q->whereBetween('invoice_payments.date', [$s, $e]);
            }
        } else { // accrual
            if ($startDate && $endDate) {
                $q->whereBetween('invoices.issue_date', [$startDate, $endDate]);
            } else {
                [$s, $e] = $this->getDateRange($reportPeriod);
                $q->whereBetween('invoices.issue_date', [$s, $e]);
            }
        }

        // Additional filters
        if (!empty($selectedCustomer)) $q->where('customers.name', 'like', '%'.$selectedCustomer.'%');
        if (!empty($selectedCategory)) $q->where('product_services.category_id', $selectedCategory);
        if (!empty($selectedType))     $q->where('product_services.type', $selectedType);
        if (!empty($selectedProdName)) $q->where('product_services.name', 'like', '%'.$selectedProdName.'%');

        return $q->orderBy('product_services.name');
    }

    /**
     * HTML builder remains as you had it (buttons etc.)
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('sales-tax-liability-report-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(1)
            ->selectStyleSingle()
            ->buttons([
                Button::make('excel'),
                Button::make('csv'),
                Button::make('pdf'),
                Button::make('print'),
                Button::make('reset'),
                Button::make('reload')
            ]);
    }

    /**
     * DataTable columns. We add a (blank) control column for the expand button.
     */
    public function getColumns(): array
    {
        return [
            Column::computed('control')->title('')->width(60)->addClass('text-start'),
            Column::make('product_service')->title(__('Product/Service')),
            Column::make('amount')->title(__('Taxable Amount'))->addClass('text-end'),
            Column::make('tax_amount')->title(__('Tax'))->addClass('text-end'),
        ];
    }

    protected function filename(): string
    {
        return 'SalesTaxLiabilityReport_' . date('YmdHis');
    }

    /**
     * Build the children map used by the front-end to render invoice rows under each product.
     * Returns: [ product_service_id => [ {invoice_db_id, invoice_number, issue_date, customer_name, taxable_amount, tax_amount}, ... ] ]
     */
    private function childrenData(): array
    {
        $user         = Auth::user();
        $ownerId      = $user->type === 'company' ? $user->creatorId() : $user->ownedId();
        $ownerColumn  = $user->type === 'company' ? 'invoices.created_by' : 'invoices.owned_by';

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
            ->where('invoices.status', 4)
            ->join('invoice_products', 'invoices.id', '=', 'invoice_products.invoice_id')
            ->join('product_services', 'invoice_products.product_id', '=', 'product_services.id')
            ->join('customers', 'invoices.customer_id', '=', 'customers.id')
            ->whereNotNull('invoice_products.tax')
            ->where('invoice_products.tax', '!=', '')
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

        // Dates (same rules as parent)
        if ($accountingMethod === 'cash') {
            $q->leftJoin('invoice_payments', 'invoices.id', '=', 'invoice_payments.invoice_id');
            if ($startDate && $endDate) {
                $q->whereBetween('invoice_payments.date', [$startDate, $endDate]);
            } else {
                [$s, $e] = $this->getDateRange($reportPeriod);
                $q->whereBetween('invoice_payments.date', [$s, $e]);
            }
        } else {
            if ($startDate && $endDate) {
                $q->whereBetween('invoices.issue_date', [$startDate, $endDate]);
            } else {
                [$s, $e] = $this->getDateRange($reportPeriod);
                $q->whereBetween('invoices.issue_date', [$s, $e]);
            }
        }

        // Filters
        if (!empty($selectedCustomer)) $q->where('customers.name', 'like', '%'.$selectedCustomer.'%');
        if (!empty($selectedCategory)) $q->where('product_services.category_id', $selectedCategory);
        if (!empty($selectedType))     $q->where('product_services.type', $selectedType);
        if (!empty($selectedProdName)) $q->where('product_services.name', 'like', '%'.$selectedProdName.'%');

        $rows = $q->orderBy('product_services.name')
                  ->orderBy('invoices.issue_date')
                  ->get();

        // Group rows by product_service_id
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

    /**
     * Same date range helper you already had.
     */
    private function getDateRange($reportPeriod): array
    {
        $today = now();
        switch ($reportPeriod) {
            case 'today':         return [$today->toDateString(), $today->toDateString()];
            case 'this_week':     return [$today->copy()->startOfWeek()->toDateString(), $today->copy()->endOfWeek()->toDateString()];
            case 'this_month':    return [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()];
            case 'this_quarter':  return [$today->copy()->startOfQuarter()->toDateString(), $today->copy()->endOfQuarter()->toDateString()];
            case 'this_year':     return [$today->copy()->startOfYear()->toDateString(), $today->copy()->endOfYear()->toDateString()];
            case 'last_week':     return [$today->copy()->subWeek()->startOfWeek()->toDateString(), $today->copy()->subWeek()->endOfWeek()->toDateString()];
            case 'last_month':    return [$today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()];
            case 'last_quarter':  return [$today->copy()->subQuarter()->startOfQuarter()->toDateString(), $today->copy()->subQuarter()->endOfQuarter()->toDateString()];
            case 'last_year':     return [$today->copy()->subYear()->startOfYear()->toDateString(), $today->copy()->subYear()->endOfYear()->toDateString()];
            case 'last_7_days':   return [$today->copy()->subDays(6)->toDateString(), $today->toDateString()];
            case 'last_30_days':  return [$today->copy()->subDays(29)->toDateString(), $today->toDateString()];
            case 'last_90_days':  return [$today->copy()->subDays(89)->toDateString(), $today->toDateString()];
            case 'last_12_months':return [$today->copy()->subYear()->startOfMonth()->toDateString(), $today->toDateString()];
            case 'all_dates':
            default:              return ['1900-01-01', $today->toDateString()];
        }
    }
}
