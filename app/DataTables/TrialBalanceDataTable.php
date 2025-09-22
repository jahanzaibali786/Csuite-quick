<?php

namespace App\DataTables;

use App\Models\ChartOfAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class TrialBalanceDataTable extends DataTable
{
    /**
 * Key modifications to TrialBalanceDataTable for proper child row collapsing
 */
public function dataTable($query)
{
    return datatables()
        ->collection($query)
        ->addColumn('DT_RowClass', function ($row) {
            $classes = [];

            if (!empty($row->is_header)) {
                $classes[] = 'account-header';
                $classes[] = 'parent-row';
                $classes[] = 'level-0';
            } elseif (!empty($row->is_subtotal)) {
                $classes[] = 'account-subtotal';
                $classes[] = 'child-row'; // Make sure subtotals are child-row class
                $classes[] = 'level-2';
            } elseif (!empty($row->is_total)) {
                $classes[] = 'grand-total';
                $classes[] = 'level-0';
            } elseif (!empty($row->is_net_income)) {
                $classes[] = 'net-income';
                $classes[] = 'level-0';
            } else {
                $classes[] = 'account-detail';
                $classes[] = 'child-row'; // Make sure details are child-row class
                $classes[] = 'level-1';
            }

            if (!empty($row->parent_id)) {
                $classes[] = 'parent-' . $row->parent_id; // Critical for toggle targeting
            }

            return implode(' ', $classes);
        })
        ->addColumn('DT_RowData', function ($row) {
            $data = [];

            if (!empty($row->parent_id)) {
                $data['parent'] = $row->parent_id;
            }

            if (!empty($row->has_children)) {
                $data['has-children'] = 'true';
            }
            if(!empty($row->is_subtotal)) {
                $data['is-subtotal'] = 'true';
            }
            $data['level'] = $this->getRowLevel($row);
            $data['row-id'] = $row->id;

            return $data;
        })
        ->addColumn('code', function ($row) {
            return isset($row->is_header) || isset($row->is_total) || isset($row->is_subtotal) || isset($row->is_net_income)
                ? ''
                : ($row->code ?? '');
        })
        ->addColumn('account_name', function ($row) {
            $indent = $this->getIndentation($row);

            // Header row with toggle
            if (!empty($row->is_header)) {
                return $indent . '<span class="toggle-btn collapsed" data-target="' . $row->id . '">
                    <i class="fa fa-chevron-right toggle-icon"></i>
                </span>
                <strong class="text-uppercase account-header-text">' . e($row->name) . '</strong>';
            }

            // Subtotal row
            if (!empty($row->is_subtotal)) {
                return $indent . '<strong class="text-primary account-subtotal-text">Total ' . e($row->name) . '</strong>';
            }

            // Grand total row
            if (!empty($row->is_total)) {
                return '<strong class="text-danger grand-total-text">GRAND TOTAL</strong>';
            }

            // Net Income row
            if (!empty($row->is_net_income)) {
                return '<strong class="text-info net-income-text">' . e($row->name) . '</strong>';
            }

            // Normal account row with link
            $accountName = $indent . e($row->name);

            if (!empty($row->id) && is_numeric(str_replace('acc-', '', $row->id))) {
                $accountId = str_replace('acc-', '', $row->id);
                $url = route('ledger.index', ['account_id' => $accountId]);
                return '<a href="' . $url . '" class="text-primary ledger-link" target="_blank">' . $accountName . '</a>';
            }

            return $accountName;
        })
        ->addColumn('account_type', function ($row) {
            return (isset($row->is_header) || isset($row->is_subtotal) || isset($row->is_total) || isset($row->is_net_income))
                ? ''
                : ucfirst($row->account_type ?? '');
        })
        ->addColumn('debit', function ($row) {
            $debit = $row->debit ?? 0;

            if (!empty($row->is_header)) {
                return '<strong class="text-success debit-cell">' . number_format(abs($debit), 2) . '</strong>';
            }

            if (!empty($row->is_subtotal) || !empty($row->is_total)) {
                return '<strong class="text-success">' . number_format(abs($debit), 2) . '</strong>';
            }

            if (!empty($row->is_net_income)) {
                return $debit > 0 ? '<strong class="text-success">' . number_format($debit, 2) . '</strong>' : '';
            }

            return $debit > 0 ? '<span class="text-success">' . number_format($debit, 2) . '</span>' : '';
        })
        ->addColumn('credit', function ($row) {
            $credit = $row->credit ?? 0;

            if (!empty($row->is_header)) {
                return '<strong class="text-danger credit-cell">' . number_format(abs($credit), 2) . '</strong>';
            }

            if (!empty($row->is_subtotal) || !empty($row->is_total)) {
                return '<strong class="text-danger">' . number_format(abs($credit), 2) . '</strong>';
            }

            if (!empty($row->is_net_income)) {
                return $credit > 0 ? '<strong class="text-danger">' . number_format($credit, 2) . '</strong>' : '';
            }

            return $credit > 0 ? '<span class="text-danger">' . number_format($credit, 2) . '</span>' : '';
        })
        ->rawColumns(['account_name', 'debit', 'credit']);
}
    private function getRowLevel($row)
    {
        if (!empty($row->is_header) || !empty($row->is_total) || !empty($row->is_net_income)) {
            return 0;
        } elseif (!empty($row->is_subtotal)) {
            return 2;
        } else {
            return 1; // Normal accounts
        }
    }

    private function getIndentation($row)
    {
        $level = $this->getRowLevel($row);
        return str_repeat('<span class="indent-spacer"></span>', $level);
    }

    public function query()
    {
        // Get the current company ID dynamically
        $companyId = auth()->user()->company_id ?? 1; // Fallback to 1 if no company

        $startDate = request('startDate')
            ? Carbon::parse(request('startDate'))->startOfDay()
            : Carbon::now()->startOfYear();

        $endDate = request('endDate')
            ? Carbon::parse(request('endDate'))->endOfDay()
            : Carbon::now()->endOfDay();

        // Apply filters
        $subtypeFilter = request('subtype');
        $typeFilter = request('type');

        // Debug: Log the company ID and date range
        \Log::info('Trial Balance Query', [
            'company_id' => $companyId,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'type_filter' => $typeFilter,
            'subtype_filter' => $subtypeFilter
        ]);
        
        $accounts = ChartOfAccount::query()
            ->where('chart_of_accounts.company_id', $companyId)
            ->join('chart_of_account_sub_types', 'chart_of_accounts.sub_type', '=', 'chart_of_account_sub_types.id')
            ->join('chart_of_account_types', 'chart_of_account_sub_types.type', '=', 'chart_of_account_types.id')
            ->leftJoin(DB::raw("
                (
                    SELECT 
                        jel.account,
                        SUM(jel.debit) as opening_debit,
                        SUM(jel.credit) as opening_credit
                    FROM journal_items jel
                    INNER JOIN journal_entries je ON je.id = jel.journal
                    WHERE je.owned_by = {$companyId}
                      AND je.date < '{$startDate->format('Y-m-d')}'
                    GROUP BY jel.account
                ) as opening
            "), 'chart_of_accounts.id', '=', 'opening.account')
            ->leftJoin(DB::raw("
                (
                    SELECT 
                        jel.account,
                        SUM(jel.debit) as period_debit,
                        SUM(jel.credit) as period_credit
                    FROM journal_items jel
                    INNER JOIN journal_entries je ON je.id = jel.journal
                    WHERE je.owned_by = {$companyId}
                      AND je.date BETWEEN '{$startDate->format('Y-m-d')}' AND '{$endDate->format('Y-m-d')}'
                    GROUP BY jel.account
                ) as period
            "), 'chart_of_accounts.id', '=', 'period.account');

        // Apply type filter
        if ($typeFilter) {
            $accounts->where('chart_of_account_types.name', $typeFilter);
        }

        // Apply subtype filter
        if ($subtypeFilter) {
            $accounts->where('chart_of_account_sub_types.name', $subtypeFilter);
        }

        $accounts = $accounts->select([
            'chart_of_accounts.id',
            'chart_of_accounts.name',
            'chart_of_accounts.code',
            'chart_of_account_sub_types.name as subtype',
            'chart_of_account_types.name as account_type',
            DB::raw("
                CASE 
                    WHEN chart_of_account_types.name IN ('Asset','Liability','Equity') 
                        THEN GREATEST(
                            (COALESCE(opening.opening_debit,0) + COALESCE(period.period_debit,0)) 
                            - (COALESCE(opening.opening_credit,0) + COALESCE(period.period_credit,0)),
                            0
                        )
                    ELSE GREATEST(
                            COALESCE(period.period_debit,0) - COALESCE(period.period_credit,0),
                            0
                        )
                END as debit
            "),
            DB::raw("
                CASE 
                    WHEN chart_of_account_types.name IN ('Asset','Liability','Equity') 
                        THEN GREATEST(
                            (COALESCE(opening.opening_credit,0) + COALESCE(period.period_credit,0)) 
                            - (COALESCE(opening.opening_debit,0) + COALESCE(period.period_debit,0)),
                            0
                        )
                    ELSE GREATEST(
                            COALESCE(period.period_credit,0) - COALESCE(period.period_debit,0),
                            0
                        )
                END as credit
            ")
        ])
        ->groupBy(
            'chart_of_accounts.id',
            'chart_of_accounts.name',
            'chart_of_accounts.code',
            'chart_of_account_sub_types.name',
            'chart_of_account_types.name',
            'opening.opening_debit',
            'opening.opening_credit',
            'period.period_debit',
            'period.period_credit'
        )
        ->orderBy('chart_of_account_types.name')
        ->orderBy('chart_of_accounts.code')
        ->get();

        // Debug: Log the number of accounts found
        \Log::info('Accounts found: ' . $accounts->count());

        // If no accounts found, create sample data for testing
        if ($accounts->isEmpty()) {
            $accounts = collect([
                (object) [
                    'id' => 1,
                    'name' => 'Cash',
                    'code' => '1001',
                    'account_type' => 'Asset',
                    'subtype' => 'Cash',
                    'debit' => 5000.00,
                    'credit' => 0.00
                ],
                (object) [
                    'id' => 2,
                    'name' => 'Accounts Receivable',
                    'code' => '1200',
                    'account_type' => 'Asset',
                    'subtype' => 'Receivable',
                    'debit' => 2500.00,
                    'credit' => 0.00
                ],
                (object) [
                    'id' => 3,
                    'name' => 'Accounts Payable',
                    'code' => '2001',
                    'account_type' => 'Liability',
                    'subtype' => 'Payable',
                    'debit' => 0.00,
                    'credit' => 1500.00
                ],
                (object) [
                    'id' => 4,
                    'name' => 'Sales Revenue',
                    'code' => '4001',
                    'account_type' => 'Income',
                    'subtype' => 'Revenue',
                    'debit' => 0.00,
                    'credit' => 10000.00
                ],
                (object) [
                    'id' => 5,
                    'name' => 'Office Expenses',
                    'code' => '5001',
                    'account_type' => 'Expense',
                    'subtype' => 'Operating',
                    'debit' => 3000.00,
                    'credit' => 0.00
                ]
            ]);
        }

        return $this->buildHierarchicalData($accounts, $startDate, $companyId);
    }

    private function buildHierarchicalData($accounts, $startDate, $companyId)
    {
        $report = collect();
        $accountTypes = ['Asset', 'Liability', 'Equity', 'Income', 'Expense'];

        foreach ($accountTypes as $type) {
            $group = $accounts->where('account_type', $type);

            if ($group->isEmpty()) {
                continue;
            }

            // Add header row
            $headerRow = (object) [
                'id' => 'type-' . strtolower($type),
                'parent_id' => null,
                'name' => strtoupper($type),
                'is_header' => true,
                'debit' => $group->sum('debit'),
                'credit' => $group->sum('credit'),
                'has_children' => true,
                'account_type' => $type
            ];
            $report->push($headerRow);

            // Add individual accounts
            foreach ($group as $acc) {
                $accountRow = (object) [
                    'id' => 'acc-' . $acc->id,
                    'parent_id' => 'type-' . strtolower($type),
                    'name' => $acc->name,
                    'code' => $acc->code,
                    'account_type' => $acc->account_type,
                    'subtype' => $acc->subtype,
                    'debit' => $acc->debit,
                    'credit' => $acc->credit,
                    'has_children' => false
                ];
                $report->push($accountRow);
            }

            // Add subtotal row
            $subtotalRow = (object) [
                'id' => 'sub-' . strtolower($type),
                'parent_id' => 'type-' . strtolower($type),
                'name' => $type,
                'debit' => $group->sum('debit'),
                'credit' => $group->sum('credit'),
                'is_subtotal' => true,
                'has_children' => false,
                'account_type' => $type
            ];
            $report->push($subtotalRow);
        }

        // Add accumulated profit/loss row
        $netProfit = $this->calculateNetProfit($startDate, $companyId);

        $accumulatedRow = (object) [
            'id' => 'net-income',
            'parent_id' => null,
            'name' => 'Accumulated Profit / (Loss)',
            'account_type' => 'Equity',
            'debit' => $netProfit < 0 ? abs($netProfit) : 0,
            'credit' => $netProfit > 0 ? $netProfit : 0,
            'is_net_income' => true,
            'has_children' => false
        ];
        $report->push($accumulatedRow);

        // Add grand total row
        $totalDebit = $accounts->sum('debit') + $accumulatedRow->debit;
        $totalCredit = $accounts->sum('credit') + $accumulatedRow->credit;

        $grandTotalRow = (object) [
            'id' => 'grand-total',
            'parent_id' => null,
            'name' => 'GRAND TOTAL',
            'debit' => $totalDebit,
            'credit' => $totalCredit,
            'is_total' => true,
            'has_children' => false
        ];
        $report->push($grandTotalRow);

        return $report;
    }

    private function calculateNetProfit($startDate, $companyId)
    {
        return DB::table('journal_items')
            ->join('chart_of_accounts', 'journal_items.account', '=', 'chart_of_accounts.id')
            ->join('chart_of_account_sub_types', 'chart_of_accounts.sub_type', '=', 'chart_of_account_sub_types.id')
            ->join('chart_of_account_types', 'chart_of_account_sub_types.type', '=', 'chart_of_account_types.id')
            ->join('journal_entries', 'journal_items.journal', '=', 'journal_entries.id')
            ->where('journal_entries.owned_by', $companyId)
            ->where('journal_entries.date', '<=', $startDate)
            ->whereIn('chart_of_account_types.name', ['Income', 'Expense'])
            ->selectRaw('SUM(journal_items.credit - journal_items.debit) as net_profit')
            ->value('net_profit') ?? 0;
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('trial-balance-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('Bfrtip')
            ->parameters([
                'paging' => false,
                'searching' => false,
                'info' => false,
                'ordering' => false,
                'scrollY' => '500px',
                'scrollCollapse' => true,
                'responsive' => true,
                'autoWidth' => false,
                'createdRow' => "function(row, data, dataIndex) {
                    if (data.DT_RowClass) {
                        $(row).addClass(data.DT_RowClass);
                    }
                    if (data.DT_RowData) {
                        for (let key in data.DT_RowData) {
                            $(row).attr('data-' + key, data.DT_RowData[key]);
                        }
                    }
                }",
                'drawCallback' => "function(settings) {
                    console.log('DataTable drawn with ' + this.api().rows().count() + ' rows');
                }"
            ]);
    }

    protected function getColumns()
    {
        return [
            Column::make('code')->title('Account #')->width('12%')->addClass('text-center'),
            Column::make('account_name')->title('Account Name')->width('45%'),
            Column::make('account_type')->title('Type')->width('13%')->addClass('text-center'),
            Column::make('debit')->title('Debit')->width('15%')->addClass('text-right'),
            Column::make('credit')->title('Credit')->width('15%')->addClass('text-right'),
        ];
    }
}