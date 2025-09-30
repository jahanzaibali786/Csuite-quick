<?php

namespace App\DataTables;

use App\Models\ChartOfAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class TrialBalanceDataTable extends DataTable
{
    /**
     * Build DataTable instance.
     *
     * @param mixed $query Results from query() method.
     * @return \Yajra\DataTables\DataTableAbstract
     */
    public function dataTable($query)
    {
        $displayColumns = request('displayColumns', 'total-only');
        
        $datatable = datatables()
            ->collection($query)
            ->addColumn('DT_RowClass', function ($row) {
                $classes = [];

                if (!empty($row->is_header)) {
                    $classes[] = 'account-header';
                    $classes[] = 'parent-row';
                    $classes[] = 'level-0';
                    $classes[] = 'group-header';
                } elseif (!empty($row->is_subtotal)) {
                    $classes[] = 'account-subtotal';
                    $classes[] = 'child-row';
                    $classes[] = 'level-2';
                } elseif (!empty($row->is_total)) {
                    $classes[] = 'grand-total';
                    $classes[] = 'level-0';
                } elseif (!empty($row->is_net_income)) {
                    $classes[] = 'net-income';
                    $classes[] = 'level-0';
                } else {
                    $classes[] = 'account-detail';
                    $classes[] = 'child-row';
                    $classes[] = 'level-1';
                }

                if (!empty($row->parent_id)) {
                    $classes[] = 'child-of-' . $row->parent_id;
                }

                // Add visibility classes based on filters
                $showRows = request('showRows', 'active');
                $showColumns = request('showColumns', 'active');
                
                if ($showRows === 'non-zero' && empty($row->debit) && empty($row->credit)) {
                    $classes[] = 'hidden-row';
                }

                return implode(' ', $classes);
            })
            ->addColumn('DT_RowData', function ($row) {
                $data = [];

                // Child rows get data-parent-id
                if (!empty($row->parent_id)) {
                    $data['parent-id'] = $row->parent_id;
                }

                // Header rows get group-id
                if (!empty($row->is_header)) {
                    $data['group-id'] = $row->id;
                    $data['is-header'] = 'true';
                }

                if (!empty($row->has_children)) {
                    $data['has-children'] = 'true';
                }

                if (!empty($row->is_subtotal)) {
                    $data['is-subtotal'] = 'true';
                }

                // Always include a row-id
                $data['row-id'] = $row->id;

                return $data;
            })
            ->addColumn('code', function ($row) {
                return (!empty($row->is_header) || !empty($row->is_total) || !empty($row->is_subtotal) || !empty($row->is_net_income))
                    ? ''
                    : ($row->code ?? '');
            })
            ->addColumn('account_name', function ($row) {
                $indent = $this->getIndentation($row);

                if (!empty($row->is_header)) {
                    // Header shows toggle button + name
                    return $indent . '<span class="toggle-btn collapsed" data-target="' . $row->id . '">
                        <i class="fa fa-chevron-right toggle-icon"></i>
                    </span>
                    <strong class="account-header-text">' . e($row->name) . '</strong>';
                }

                if (!empty($row->is_subtotal)) {
                    return $indent . '<strong class="account-subtotal-text">Total ' . e($row->name) . '</strong>';
                }

                if (!empty($row->is_total)) {
                    return '<strong class="grand-total-text">GRAND TOTAL</strong>';
                }

                if (!empty($row->is_net_income)) {
                    return '<strong class="net-income-text">' . e($row->name) . '</strong>';
                }

                $accountName = $indent . e($row->name);

                // If numeric account id pattern (acc-...), link to ledger
                if (!empty($row->id) && is_string($row->id) && strpos($row->id, 'acc-') === 0) {
                    $accountId = str_replace('acc-', '', $row->id);
                        $url = route('ledger.index', ['account_id' => $accountId]);
                        return '<a href="' . $url . '" class="text-primary ledger-link" target="_blank">' . $accountName . '</a>';
                }

                return $accountName;
            })
            ->addColumn('account_type', function ($row) {
                return (!empty($row->is_header) || !empty($row->is_subtotal) || !empty($row->is_total) || !empty($row->is_net_income))
                    ? ''
                    : ucfirst($row->account_type ?? '');
            })
            ->addColumn('debit', function ($row) {
                $debit = $row->debit ?? 0;

                if (!empty($row->is_header)) {
                    return '<strong class="text-success debit-cell header-amount" style="display:none;">' . number_format(abs($debit), 2) . '</strong>';
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
                    return '<strong class="text-danger credit-cell header-amount" style="display:none;">' . number_format(abs($credit), 2) . '</strong>';
                }

                if (!empty($row->is_subtotal) || !empty($row->is_total)) {
                    return '<strong class="text-danger">' . number_format(abs($credit), 2) . '</strong>';
                }

                if (!empty($row->is_net_income)) {
                    return $credit > 0 ? '<strong class="text-danger">' . number_format($credit, 2) . '</strong>' : '';
                }

                return $credit > 0 ? '<span class="text-danger">' . number_format($credit, 2) . '</span>' : '';
            });

        // Add dynamic columns based on display selection
        if ($displayColumns === 'months') {
            $datatable = $this->addMonthlyColumns($datatable);
        } elseif ($displayColumns === 'by-quarter') {
            $datatable = $this->addQuarterlyColumns($datatable);
        } elseif ($displayColumns === 'by-year') {
            $datatable = $this->addYearlyColumns($datatable);
        }

        return $datatable->rawColumns(['account_name', 'debit', 'credit']);
    }

    /**
     * Add monthly columns to datatable
     */
    private function addMonthlyColumns($datatable)
    {
        $dateRange = $this->getDateRange();
        $endDate = $dateRange['end'];
        
        // Generate last 3 months from end date
        for ($i = 2; $i >= 0; $i--) {
            $monthDate = $endDate->copy()->subMonths($i);
            $monthName = $monthDate->format('M Y');
            $monthKey = $monthDate->format('Y-m');
            
            $datatable->addColumn("month_{$monthKey}_debit", function ($row) use ($monthKey) {
                $amount = $row->monthly_data[$monthKey]['debit'] ?? 0;
                return $amount > 0 ? '<span class="text-success">' . number_format($amount, 2) . '</span>' : '';
            });
            
            $datatable->addColumn("month_{$monthKey}_credit", function ($row) use ($monthKey) {
                $amount = $row->monthly_data[$monthKey]['credit'] ?? 0;
                return $amount > 0 ? '<span class="text-danger">' . number_format($amount, 2) . '</span>' : '';
            });
        }
        
        return $datatable;
    }

    /**
     * Add quarterly columns to datatable
     */
    private function addQuarterlyColumns($datatable)
    {
        $currentYear = Carbon::now()->year;
        $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
        
        foreach ($quarters as $quarter) {
            $quarterKey = $currentYear . '_' . $quarter;
            
            $datatable->addColumn("quarter_{$quarterKey}_debit", function ($row) use ($quarterKey) {
                $amount = $row->quarterly_data[$quarterKey]['debit'] ?? 0;
                return $amount > 0 ? '<span class="text-success">' . number_format($amount, 2) . '</span>' : '';
            });
            
            $datatable->addColumn("quarter_{$quarterKey}_credit", function ($row) use ($quarterKey) {
                $amount = $row->quarterly_data[$quarterKey]['credit'] ?? 0;
                return $amount > 0 ? '<span class="text-danger">' . number_format($amount, 2) . '</span>' : '';
            });
        }
        
        return $datatable;
    }

    /**
     * Add yearly columns to datatable
     */
    private function addYearlyColumns($datatable)
    {
        $currentYear = Carbon::now()->year;
        
        // Add current year and previous 2 years
        for ($i = 2; $i >= 0; $i--) {
            $year = $currentYear - $i;
            
            $datatable->addColumn("year_{$year}_debit", function ($row) use ($year) {
                $amount = $row->yearly_data[$year]['debit'] ?? 0;
                return $amount > 0 ? '<span class="text-success">' . number_format($amount, 2) . '</span>' : '';
            });
            
            $datatable->addColumn("year_{$year}_credit", function ($row) use ($year) {
                $amount = $row->yearly_data[$year]['credit'] ?? 0;
                return $amount > 0 ? '<span class="text-danger">' . number_format($amount, 2) . '</span>' : '';
            });
        }
        
        return $datatable;
    }

    /**
     * Get row indentation based on level
     */
    private function getRowLevel($row)
    {
        if (!empty($row->is_header) || !empty($row->is_total) || !empty($row->is_net_income)) {
            return 0;
        } elseif (!empty($row->is_subtotal)) {
            return 2;
        } else {
            return 1;
        }
    }

    /**
     * Generate indentation HTML
     */
    private function getIndentation($row)
    {
        $level = $this->getRowLevel($row);
        return str_repeat('<span class="indent-spacer"></span>', $level);
    }

    /**
     * Get query source of dataTable.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        $companyId = auth()->user()->company_id ?? 2;

        $dateRange = $this->getDateRange();
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        $typeFilter = request('accountType', 'all');
        $displayColumns = request('displayColumns', 'total-only');
        $accountingMethod = request('accountingMethod', 'accrual');
        $showRows = request('showRows', 'active');
        $showColumns = request('showColumns', 'active');

        $accountsQuery = ChartOfAccount::query()
            ->where('chart_of_accounts.company_id', $companyId)
            ->join('chart_of_account_sub_types', 'chart_of_accounts.sub_type', '=', 'chart_of_account_sub_types.id')
            ->join('chart_of_account_types', 'chart_of_account_sub_types.type', '=', 'chart_of_account_types.id');

        // Apply account type filter
        if ($typeFilter && $typeFilter !== 'all') {
            $accountsQuery->where('chart_of_account_types.name', $typeFilter);
        }

        // Apply accounting method filter
        $journalCondition = "je.owned_by = {$companyId}";
        if ($accountingMethod === 'cash') {
            // For cash accounting, only include paid transactions
            $journalCondition .= " AND (je.payment_status = 'paid' OR je.payment_status IS NULL)";
        }

        // Add opening balance subquery
        $accountsQuery->leftJoin(DB::raw("
            (
                SELECT 
                    jel.account,
                    SUM(jel.debit) as opening_debit,
                    SUM(jel.credit) as opening_credit
                FROM journal_items jel
                INNER JOIN journal_entries je ON je.id = jel.journal
                WHERE {$journalCondition}
                  AND je.date < '{$startDate->format('Y-m-d')}'
                GROUP BY jel.account
            ) as opening
        "), 'chart_of_accounts.id', '=', 'opening.account');

        // Add period balance subquery
        $accountsQuery->leftJoin(DB::raw("
            (
                SELECT 
                    jel.account,
                    SUM(jel.debit) as period_debit,
                    SUM(jel.credit) as period_credit
                FROM journal_items jel
                INNER JOIN journal_entries je ON je.id = jel.journal
                WHERE {$journalCondition}
                  AND je.date BETWEEN '{$startDate->format('Y-m-d')}' AND '{$endDate->format('Y-m-d')}'
                GROUP BY jel.account
            ) as period
        "), 'chart_of_accounts.id', '=', 'period.account');

        // Apply show rows filter
        if ($showRows === 'active') {
            // $accountsQuery->where('chart_of_accounts.is_active', 1);
        } elseif ($showRows === 'non-zero') {
            $accountsQuery->where(function($query) {
                $query->whereRaw('COALESCE(opening.opening_debit, 0) + COALESCE(period.period_debit, 0) > 0')
                    ->orWhereRaw('COALESCE(opening.opening_credit, 0) + COALESCE(period.period_credit, 0) > 0');
            });
        }

        $accounts = $accountsQuery->select([
            'chart_of_accounts.id',
            'chart_of_accounts.name',
            'chart_of_accounts.code',
            // 'chart_of_accounts.is_active',
            'chart_of_account_sub_types.name as subtype',
            'chart_of_account_types.name as account_type',
            DB::raw("
                CASE 
                    WHEN chart_of_account_types.name IN ('Asset', 'Expense') 
                        THEN GREATEST(
                            (COALESCE(opening.opening_debit, 0) + COALESCE(period.period_debit, 0)) 
                            - (COALESCE(opening.opening_credit, 0) + COALESCE(period.period_credit, 0)),
                            0
                        )
                    ELSE GREATEST(
                            COALESCE(period.period_debit, 0),
                            0
                        )
                END as debit
            "),
            DB::raw("
                CASE 
                    WHEN chart_of_account_types.name IN ('Liability', 'Equity', 'Income') 
                        THEN GREATEST(
                            (COALESCE(opening.opening_credit, 0) + COALESCE(period.period_credit, 0)) 
                            - (COALESCE(opening.opening_debit, 0) + COALESCE(period.period_debit, 0)),
                            0
                        )
                    ELSE GREATEST(
                            COALESCE(period.period_credit, 0),
                            0
                        )
                END as credit
            ")
        ])
        ->groupBy(
            'chart_of_accounts.id',
            'chart_of_accounts.name',
            'chart_of_accounts.code',
            // 'chart_of_accounts.is_active',
            'chart_of_account_sub_types.name',
            'chart_of_account_types.name',
            'opening.opening_debit',
            'opening.opening_credit',
            'period.period_debit',
            'period.period_credit'
        )
        ->orderBy('chart_of_account_types.name')
        ->orderBy('chart_of_account_sub_types.name')
        ->orderBy('chart_of_accounts.code')
        ->get();

        // Add time-based data if needed
        if ($displayColumns !== 'total-only') {
            $accounts = $this->addTimePeriodData($accounts, $companyId, $accountingMethod, $displayColumns);
        }

        return $this->buildHierarchicalData($accounts, $startDate, $endDate, $companyId, $accountingMethod);
    }

    /**
     * Add time period data for monthly/quarterly/yearly display
     */
    private function addTimePeriodData($accounts, $companyId, $accountingMethod, $displayColumns)
    {
        $journalCondition = "je.owned_by = {$companyId}";
        if ($accountingMethod === 'cash') {
            $journalCondition .= " AND (je.payment_status = 'paid' OR je.payment_status IS NULL)";
        }

        foreach ($accounts as $account) {
            if ($displayColumns === 'months') {
                $account->monthly_data = $this->getMonthlyData($account->id, $companyId, $journalCondition);
            } elseif ($displayColumns === 'by-quarter') {
                $account->quarterly_data = $this->getQuarterlyData($account->id, $companyId, $journalCondition);
            } elseif ($displayColumns === 'by-year') {
                $account->yearly_data = $this->getYearlyData($account->id, $companyId, $journalCondition);
            }
        }

        return $accounts;
    }

    /**
     * Get monthly data for an account
     */
    private function getMonthlyData($accountId, $companyId, $journalCondition)
    {
        $data = [];
        $endDate = $this->getDateRange()['end'];
        
        for ($i = 2; $i >= 0; $i--) {
            $monthDate = $endDate->copy()->subMonths($i);
            $monthKey = $monthDate->format('Y-m');
            $startOfMonth = $monthDate->copy()->startOfMonth();
            $endOfMonth = $monthDate->copy()->endOfMonth();
            
            $result = DB::select("
                SELECT 
                    SUM(jel.debit) as debit,
                    SUM(jel.credit) as credit
                FROM journal_items jel
                INNER JOIN journal_entries je ON je.id = jel.journal
                WHERE jel.account = ? 
                  AND {$journalCondition}
                  AND je.date BETWEEN ? AND ?
            ", [$accountId, $startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')]);
            
            $data[$monthKey] = [
                'debit' => $result[0]->debit ?? 0,
                'credit' => $result[0]->credit ?? 0
            ];
        }
        
        return $data;
    }

    /**
     * Get quarterly data for an account
     */
    private function getQuarterlyData($accountId, $companyId, $journalCondition)
    {
        $data = [];
        $currentYear = Carbon::now()->year;
        $quarters = [
            'Q1' => ['01-01', '03-31'],
            'Q2' => ['04-01', '06-30'],
            'Q3' => ['07-01', '09-30'],
            'Q4' => ['10-01', '12-31']
        ];
        
        foreach ($quarters as $quarter => $dates) {
            $quarterKey = $currentYear . '_' . $quarter;
            $startDate = "{$currentYear}-{$dates[0]}";
            $endDate = "{$currentYear}-{$dates[1]}";
            
            $result = DB::select("
                SELECT 
                    SUM(jel.debit) as debit,
                    SUM(jel.credit) as credit
                FROM journal_items jel
                INNER JOIN journal_entries je ON je.id = jel.journal
                WHERE jel.account = ? 
                  AND {$journalCondition}
                  AND je.date BETWEEN ? AND ?
            ", [$accountId, $startDate, $endDate]);
            
            $data[$quarterKey] = [
                'debit' => $result[0]->debit ?? 0,
                'credit' => $result[0]->credit ?? 0
            ];
        }
        
        return $data;
    }

    /**
     * Get yearly data for an account
     */
    private function getYearlyData($accountId, $companyId, $journalCondition)
    {
        $data = [];
        $currentYear = Carbon::now()->year;
        
        for ($i = 2; $i >= 0; $i--) {
            $year = $currentYear - $i;
            $startDate = "{$year}-01-01";
            $endDate = "{$year}-12-31";
            
            $result = DB::select("
                SELECT 
                    SUM(jel.debit) as debit,
                    SUM(jel.credit) as credit
                FROM journal_items jel
                INNER JOIN journal_entries je ON je.id = jel.journal
                WHERE jel.account = ? 
                  AND {$journalCondition}
                  AND je.date BETWEEN ? AND ?
            ", [$accountId, $startDate, $endDate]);
            
            $data[$year] = [
                'debit' => $result[0]->debit ?? 0,
                'credit' => $result[0]->credit ?? 0
            ];
        }
        
        return $data;
    }
    
    /**
     * Get date range based on user selection
     */
    private function getDateRange()
    {
        $reportPeriod = request('reportPeriod', 'this-month');
        $dateFrom = request('dateFrom');
        $dateTo = request('dateTo');

        switch($reportPeriod) {
            case 'today':
                return ['start' => Carbon::today(), 'end' => Carbon::today()->endOfDay()];
            case 'yesterday':
                return ['start' => Carbon::yesterday(), 'end' => Carbon::yesterday()->endOfDay()];
            case 'this-week':
                return ['start' => Carbon::now()->startOfWeek(), 'end' => Carbon::now()->endOfDay()];
            case 'last-week':
                return ['start' => Carbon::now()->subWeek()->startOfWeek(), 'end' => Carbon::now()->subWeek()->endOfWeek()];
            case 'this-month':
                return ['start' => Carbon::now()->startOfMonth(), 'end' => Carbon::now()->endOfDay()];
            case 'last-month':
                return ['start' => Carbon::now()->subMonth()->startOfMonth(), 'end' => Carbon::now()->subMonth()->endOfMonth()];
            case 'this-quarter':
                return ['start' => Carbon::now()->startOfQuarter(), 'end' => Carbon::now()->endOfDay()];
            case 'last-quarter':
                return ['start' => Carbon::now()->subQuarter()->startOfQuarter(), 'end' => Carbon::now()->subQuarter()->endOfQuarter()];
            case 'this-year':
                return ['start' => Carbon::now()->startOfYear(), 'end' => Carbon::now()->endOfDay()];
            case 'last-year':
                return ['start' => Carbon::now()->subYear()->startOfYear(), 'end' => Carbon::now()->subYear()->endOfYear()];
            case 'custom':
            default:
                return [
                    'start' => $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : Carbon::now()->startOfMonth(),
                    'end' => $dateTo ? Carbon::parse($dateTo)->endOfDay() : Carbon::now()->endOfDay()
                ];
        }
    }

    /**
     * Build hierarchical data structure for the trial balance
     */
    private function buildHierarchicalData($accounts, $startDate, $endDate, $companyId, $accountingMethod = 'accrual')
    {
        $report = collect();
        $accountTypes = ['Asset', 'Liability', 'Equity', 'Income', 'Expense'];
        $showRows = request('showRows', 'active');
        $showColumns = request('showColumns', 'active');

        foreach ($accountTypes as $type) {
            $group = $accounts->where('account_type', $type);
            
            // Apply show columns filter
            if ($showColumns === 'active') {
                // $group = $group->where('is_active', 1);
            } elseif ($showColumns === 'non-zero') {
                $group = $group->filter(function($acc) {
                    return $acc->debit > 0 || $acc->credit > 0;
                });
            }
            
            if ($group->isEmpty()) continue;

            $parentId = 'type-' . strtolower($type);

            $headerRow = (object) [
                'id' => $parentId,
                'parent_id' => null,
                'name' => strtoupper($type),
                'is_header' => true,
                'debit' => $group->sum('debit'),
                'credit' => $group->sum('credit'),
                'has_children' => true,
                'account_type' => $type,
                // 'is_active' => 1
            ];
            $report->push($headerRow);

            // Group by subtype for more organization
            $subtypes = $group->pluck('subtype')->unique();
            
            foreach ($subtypes as $subtype) {
                $subtypeAccounts = $group->where('subtype', $subtype);
                
                foreach ($subtypeAccounts as $acc) {
                    $accountRow = (object) [
                        'id' => 'acc-' . $acc->id,
                        'parent_id' => $parentId,
                        'name' => $acc->name,
                        'code' => $acc->code,
                        'account_type' => $acc->account_type,
                        'subtype' => $acc->subtype,
                        'debit' => $acc->debit,
                        'credit' => $acc->credit,
                        'has_children' => false,
                        // 'is_active' => $acc->is_active,

                        'monthly_data' => $acc->monthly_data ?? [],
                        'quarterly_data' => $acc->quarterly_data ?? [],
                        'yearly_data' => $acc->yearly_data ?? []
                    ];
                    $report->push($accountRow);
                }
            }

            $subtotalRow = (object) [
                'id' => 'sub-' . strtolower($type),
                'parent_id' => $parentId,
                'name' => $type,
                'debit' => $group->sum('debit'),
                'credit' => $group->sum('credit'),
                'is_subtotal' => true,
                'has_children' => false,
                'account_type' => $type,
                // 'is_active' => 1
            ];
            $report->push($subtotalRow);
        }

        // Calculate net profit for the period
        $netProfit = $this->calculateNetProfit($startDate, $endDate, $companyId, $accountingMethod);

        // Add accumulated profit/loss row
        $accumulatedRow = (object) [
            'id' => 'net-income',
            'parent_id' => null,
            'name' => 'Accumulated Profit / (Loss)',
            'account_type' => 'Equity',
            'debit' => $netProfit < 0 ? abs($netProfit) : 0,
            'credit' => $netProfit > 0 ? $netProfit : 0,
            'is_net_income' => true,
            'has_children' => false,
            // 'is_active' => 1
        ];
        $report->push($accumulatedRow);

        // Add grand total row
        $totalDebit = $accounts->sum('debit') + ($netProfit < 0 ? abs($netProfit) : 0);
        $totalCredit = $accounts->sum('credit') + ($netProfit > 0 ? $netProfit : 0);

        $grandTotalRow = (object) [
            'id' => 'grand-total',
            'parent_id' => null,
            'name' => 'GRAND TOTAL',
            'debit' => $totalDebit,
            'credit' => $totalCredit,
            'is_total' => true,
            'has_children' => false,
            // 'is_active' => 1
        ];
        $report->push($grandTotalRow);

        return $report;
    }

    /**
     * Calculate net profit for the period
     */
    private function calculateNetProfit($startDate, $endDate, $companyId, $accountingMethod = 'accrual')
    {        
        $journalCondition = "journal_entries.owned_by = {$companyId}";
        if ($accountingMethod === 'cash') {
            $journalCondition .= " AND (journal_entries.payment_status = 'paid' OR journal_entries.payment_status IS NULL)";
        }
        
        $query = DB::table('journal_items')
            ->join('chart_of_accounts', 'journal_items.account', '=', 'chart_of_accounts.id')
            ->join('chart_of_account_sub_types', 'chart_of_accounts.sub_type', '=', 'chart_of_account_sub_types.id')
            ->join('chart_of_account_types', 'chart_of_account_sub_types.type', '=', 'chart_of_account_types.id')
            ->join('journal_entries', 'journal_items.journal', '=', 'journal_entries.id')
            ->whereRaw($journalCondition)
            ->whereRaw("journal_entries.date <= '{$endDate->format('Y-m-d')}'")
            ->whereIn('chart_of_account_types.name', ['Income', 'Expense']);
            
        return $query->selectRaw('SUM(CASE WHEN chart_of_account_types.name = "Income" THEN journal_items.credit ELSE 0 END) - 
                     SUM(CASE WHEN chart_of_account_types.name = "Expense" THEN journal_items.debit ELSE 0 END) as net_profit')
            ->value('net_profit') ?? 0;
    }

    /**
     * Get DataTables HTML builder instance.
     *
     * @return \Yajra\DataTables\Html\Builder
     */
    public function html()
    {
        $displayColumns = request('displayColumns', 'total-only');
        $columns = $this->getColumns();
        
        // Add dynamic columns based on display selection
        if ($displayColumns === 'months') {
            $columns = array_merge($columns, $this->getMonthlyColumns());
        } elseif ($displayColumns === 'by-quarter') {
            $columns = array_merge($columns, $this->getQuarterlyColumns());
        } elseif ($displayColumns === 'by-year') {
            $columns = array_merge($columns, $this->getYearlyColumns());
        }

        return $this->builder()
            ->setTableId('trial-balance-table')
            ->columns($columns)
            ->minifiedAjax()
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
                    if (typeof window.initializeToggleControls === 'function') {
                        try { 
                            window.initializeToggleControls(); 
                        } catch(e) { 
                            console.error('Toggle controls initialization error:', e); 
                        }
                    }
                }"
            ]);
    }

    /**
     * Get monthly columns for DataTable
     */
    private function getMonthlyColumns()
    {
        $columns = [];
        $dateRange = $this->getDateRange();
        $endDate = $dateRange['end'];
        
        for ($i = 2; $i >= 0; $i--) {
            $monthDate = $endDate->copy()->subMonths($i);
            $monthName = $monthDate->format('M Y');
            $monthKey = $monthDate->format('Y-m');
            
            $columns[] = Column::make("month_{$monthKey}_debit")
                ->title($monthName . ' Debit')
                ->width('10%')
                ->addClass('text-end month-debit');
                
            $columns[] = Column::make("month_{$monthKey}_credit")
                ->title($monthName . ' Credit')
                ->width('10%')
                ->addClass('text-end month-credit');
        }
        
        return $columns;
    }

    /**
     * Get quarterly columns for DataTable
     */
    private function getQuarterlyColumns()
    {
        $columns = [];
        $currentYear = Carbon::now()->year;
        $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
        
        foreach ($quarters as $quarter) {
            $quarterKey = $currentYear . '_' . $quarter;
            
            $columns[] = Column::make("quarter_{$quarterKey}_debit")
                ->title($quarter . ' ' . $currentYear . ' Debit')
                ->width('10%')
                ->addClass('text-end quarter-debit');
                
            $columns[] = Column::make("quarter_{$quarterKey}_credit")
                ->title($quarter . ' ' . $currentYear . ' Credit')
                ->width('10%')
                ->addClass('text-end quarter-credit');
        }
        
        return $columns;
    }

    /**
     * Get yearly columns for DataTable
     */
    private function getYearlyColumns()
    {
        $columns = [];
        $currentYear = Carbon::now()->year;
        
        for ($i = 2; $i >= 0; $i--) {
            $year = $currentYear - $i;
            
            $columns[] = Column::make("year_{$year}_debit")
                ->title($year . ' Debit')
                ->width('10%')
                ->addClass('text-end year-debit');
                
            $columns[] = Column::make("year_{$year}_credit")
                ->title($year . ' Credit')
                ->width('10%')
                ->addClass('text-end year-credit');
        }
        
        return $columns;
    }

    /**
     * Get columns definition.
     *
     * @return array
     */
    protected function getColumns()
    {
        return [
            Column::make('code')->title('Account #')->width('15%')->addClass('text-left'),
            Column::make('account_name')->title('Account Name')->width('40%'),
            Column::make('account_type')->title('Type')->width('15%')->addClass('text-center'),
            Column::make('debit')->title('Debit')->width('15%')->addClass('text-end'),
            Column::make('credit')->title('Credit')->width('15%')->addClass('text-end'),
        ];
    }

    /**
     * Check if route exists
     */
    // private function route_exists($name)
    // {
    //     try {
    //         return !is_null(route($name, [], false));
    //     } catch (\Exception $e) {
    //         return false;
    //     }
    // }
}