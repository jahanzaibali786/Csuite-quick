<?php

namespace App\DataTables;

use App\Models\ChartOfAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ProfitLossDataTable extends DataTable
{
    protected $startDate;
    protected $endDate;

    public function __construct()
    {
        parent::__construct();

        $this->startDate = request('startDate')
            ? Carbon::parse(request('startDate'))->startOfDay()
            : Carbon::now()->startOfYear();

        $this->endDate = request('endDate')
            ? Carbon::parse(request('endDate'))->endOfDay()
            : Carbon::now()->endOfDay();
    }

    public function dataTable($query)
    {
        return datatables()
            ->collection($query)
            ->addColumn('account_name', function ($row) {
                // Section header with conditional chevron
                if ($row->is_section_header ?? false) {
                    $chevronHtml = '';
                    if ($row->has_children ?? false) {
                        $chevronHtml = '<i class="fas fa-chevron-down toggle-chevron mr-2"></i>';
                    }
                    
                    return '<span class="toggle-section" data-group="' . $row->group_key . '" style="cursor: ' . ($row->has_children ? 'pointer' : 'default') . ';">
                        ' . $chevronHtml . '
                        <strong class="section-header">' . e($row->name) . '</strong>
                        <span class="section-total-display" data-group="' . $row->group_key . '" style="display: none; font-weight: normal; color: #6c757d; margin-left: 10px;">
                            (' . number_format($row->section_total ?? 0, 2) . ')
                        </span>
                    </span>';
                }

                // Totals
                if ($row->is_total ?? false) {
                    return '<strong class="total-label">' . e($row->name) . '</strong>';
                }
                if ($row->is_subtotal ?? false) {
                    return '<strong class="subtotal-label">' . e($row->name) . '</strong>';
                }

                // Child account row with code + name
                return ($row->is_child ? '&nbsp;&nbsp;&nbsp;&nbsp;' : '')
                    . ($row->code ? '<span class="account-code">' . e($row->code) . ' - </span> ' : '')
                    . e($row->name);
            })
            ->addColumn('amount', function ($row) {
                if ($row->is_section_header ?? false) {
                    // Show section total when collapsed, empty when expanded
                    if ($row->has_children ?? false) {
                        return '<span class="section-total-amount" data-group="' . $row->group_key . '" style="display: none; font-weight: bold; color: #6c757d;">
                            ' . number_format($row->section_total ?? 0, 2) . '
                        </span>';
                    }
                    return '';
                }

                if ($row->is_total ?? false || $row->is_subtotal ?? false) {
                    return '<strong class="total-amount">' . number_format($row->net, 2) . '</strong>';
                }

                $net = $row->amount ?? ($row->total_credit - $row->total_debit);
                if ($net == 0) {
                    return '';
                }

                return '<span class="amount-cell">' . number_format($net, 2) . '</span>';
            })
            ->setRowAttr([
                'class' => function ($row) {
                    if ($row->is_section_header ?? false) {
                        return 'section-row';
                    }
                    if ($row->is_child ?? false) {
                        return 'child-row group-' . $row->group_key;
                    }
                    if ($row->is_total ?? false) {
                        return 'total-row group-' . ($row->group_key ?? '');
                    }
                    if ($row->is_subtotal ?? false) {
                        return 'subtotal-row group-' . ($row->group_key ?? '');
                    }
                    return '';
                }
            ])
            ->rawColumns(['account_name', 'amount']);
    }

    public function query()
    {
        $accounts = ChartOfAccount::where('chart_of_accounts.company_id', company()->id)
            ->leftJoin('chart_of_account_types', 'chart_of_accounts.chart_of_account_type_id', '=', 'chart_of_account_types.id')
            ->leftJoin('chart_of_account_sub_types', 'chart_of_accounts.chart_of_account_sub_type_id', '=', 'chart_of_account_sub_types.id')
            ->leftJoin('journal_entry_lines', 'chart_of_accounts.id', '=', 'journal_entry_lines.chart_of_account_id')
            ->leftJoin('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', company()->id)
            ->whereBetween('journal_entries.date', [$this->startDate, $this->endDate])
            ->where('journal_entries.status', 'draft')
            ->select([
                'chart_of_accounts.id',
                'chart_of_accounts.name',
                'chart_of_accounts.code',
                'chart_of_account_types.name as account_type',
                'chart_of_account_sub_types.code as sub_type_code',
                DB::raw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit'),
            ])
            ->whereIn('chart_of_account_types.name', ['Income', 'Expense', 'Cost of Sales'])
            ->groupBy(
                'chart_of_accounts.id',
                'chart_of_accounts.name',
                'chart_of_accounts.code',
                'chart_of_account_types.name',
                'chart_of_account_sub_types.code'
            )
            ->orderBy('chart_of_account_types.name')
            ->orderBy('chart_of_accounts.code')
            ->get();

        $report = collect();

        // ---------------- INCOME ----------------
        $incomeAccounts = $accounts->where('account_type', 'Income')->map(function ($acc) {
            $acc->group_key = 'income';
            $acc->is_child = true;
            $acc->amount = $acc->total_credit - $acc->total_debit;
            return $acc;
        });
        $incomeTotal = $incomeAccounts->sum('amount');

        $report->push((object) [
            'name' => 'Income',
            'is_section_header' => true,
            'group_key' => 'income',
            'has_children' => $incomeAccounts->count() > 0,
            'section_total' => $incomeTotal
        ]);
        $report = $report->merge($incomeAccounts);
        $report->push((object) [
            'name' => 'Total Income',
            'account_type' => 'subtotal',
            'net' => $incomeTotal,
            'is_subtotal' => true,
            'group_key' => 'income'
        ]);

        // ---------------- COGS ----------------
        $cogsAccounts = $accounts->filter(function ($acc) {
            return $acc->account_type === 'Cost of Sales' ||
                ($acc->account_type === 'Expense' && $acc->sub_type_code === 'COGS');
        })->map(function ($acc) {
            $acc->group_key = 'cogs';
            $acc->is_child = true;
            $acc->amount = $acc->total_debit - $acc->total_credit;
            return $acc;
        });
        $cogsTotal = $cogsAccounts->sum('amount');

        $report->push((object) [
            'name' => 'Cost of Goods Sold',
            'is_section_header' => true,
            'group_key' => 'cogs',
            'has_children' => $cogsAccounts->count() > 0,
            'section_total' => $cogsTotal
        ]);
        $report = $report->merge($cogsAccounts);
        $report->push((object) [
            'name' => 'Total Cost of Goods Sold',
            'account_type' => 'subtotal',
            'net' => $cogsTotal,
            'is_subtotal' => true,
            'group_key' => 'cogs'
        ]);

        // ---------------- GROSS PROFIT ----------------
        $report->push((object) [
            'name' => 'Gross Profit',
            'account_type' => 'gross_profit',
            'net' => $incomeTotal - $cogsTotal,
            'is_total' => true
        ]);

        // ---------------- EXPENSES ----------------
        $expenseAccounts = $accounts->filter(function ($acc) {
            return $acc->account_type === 'Expense' &&
                ($acc->sub_type_code !== 'COGS' || is_null($acc->sub_type_code));
        })->map(function ($acc) {
            $acc->group_key = 'expenses';
            $acc->is_child = true;
            $acc->amount = $acc->total_debit - $acc->total_credit;
            return $acc;
        });
        $expenseTotal = $expenseAccounts->sum('amount');

        $report->push((object) [
            'name' => 'Expenses',
            'is_section_header' => true,
            'group_key' => 'expenses',
            'has_children' => $expenseAccounts->count() > 0,
            'section_total' => $expenseTotal
        ]);
        $report = $report->merge($expenseAccounts);
        $report->push((object) [
            'name' => 'Total Expenses',
            'account_type' => 'subtotal',
            'net' => $expenseTotal,
            'is_subtotal' => true,
            'group_key' => 'expenses'
        ]);

        // ---------------- NET OPERATING INCOME ----------------
        $report->push((object) [
            'name' => 'Net Operating Income',
            'account_type' => 'net_operating_income',
            'net' => ($incomeTotal - $cogsTotal) - $expenseTotal,
            'is_total' => true
        ]);

        // ---------------- OTHER INCOME / EXPENSES ----------------
        $report->push((object) [
            'name' => 'Other Income',
            'is_section_header' => true,
            'group_key' => 'other_income',
            'has_children' => false,
            'section_total' => 0
        ]);
        $report->push((object) [
            'name' => 'Other Expenses',
            'is_section_header' => true,
            'group_key' => 'other_expenses',
            'has_children' => false,
            'section_total' => 0
        ]);

        // ---------------- NET INCOME ----------------
        $report->push((object) [
            'name' => 'NET INCOME',
            'account_type' => 'net_income',
            'net' => ($incomeTotal - $cogsTotal) - $expenseTotal,
            'is_total' => true
        ]);

        return $report;
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('profit-loss-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('Bfrtip')
            ->parameters([
                'paging' => false,
                'searching' => false,
                'info' => false,
                'ordering' => false,
            ]);
    }

    protected function getColumns()
    {
        return [
            Column::make('account_name')->title('Account')->width('70%'),
            Column::make('amount')->title('Amount')->width('30%')->addClass('text-right'),
        ];
    }
}