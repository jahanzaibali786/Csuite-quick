<?php

namespace App\DataTables;

use App\Models\ChartOfAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Illuminate\Support\Collection;

class BalanceSheetComparisonDataTable extends DataTable
{
    protected $asOfDate1;
    protected $asOfDate2;
    protected $companyId;
    protected $owner;

    public function __construct()
    {
        parent::__construct();

        $this->asOfDate1 = request('asOfDate1')
            ? Carbon::parse(request('asOfDate1'))->endOfDay()
            : Carbon::now()->endOfDay();

        $this->asOfDate2 = request('asOfDate2')
            ? Carbon::parse(request('asOfDate2'))->endOfDay()
            : $this->asOfDate1->copy()->subYear()->endOfDay();

        $this->companyId = \Auth::user()->type === 'company' ? \Auth::user()->creatorId() : \Auth::user()->ownedId();
        $this->owner = \Auth::user()->type === 'company' ? 'created_by' : 'owned_by';
    }

    public function dataTable($query)
    {
        return datatables()
            ->collection($query)
            ->addColumn('account', function ($row) {
                if ($row->is_section_header ?? false) {
                    return '<strong class="section-header">' . e($row->name) . '</strong>';
                }
                
                // Sub Type Headers with carets
                if ($row->is_subtype_header ?? false) {
                    $hasChildren = $row->has_children ?? false;
                    $subtypeId = $row->subtype_id ?? 'subtype_' . str_replace(' ', '_', strtolower($row->name));
                    $caret = '';
                    
                    if ($hasChildren) {
                        $caret = '<i class="fas fa-caret-down caret-icon" data-parent-type="subtype" data-parent-id="' . $subtypeId . '" style="margin-right: 8px; cursor: pointer;"></i>';
                    }
                    
                    $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', (int) ($row->depth ?? 0));
                    return $indent . $caret . '<strong class="subtotal-label">' . e($row->name) . '</strong>';
                }
                
                if ($row->is_subtotal ?? false) {
                    $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', (int) ($row->depth ?? 0));
                    return $indent . '<strong class="subtotal-label">' . e($row->name) . '</strong>';
                }
                
                if ($row->is_total ?? false) {
                    return '<strong class="total-label">' . e($row->name) . '</strong>';
                }

                // Individual account rows
                $depth = (int) ($row->depth ?? 0);
                $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', max(0, $depth));
                return $indent . e($row->name);
            })
            ->addColumn('amount', function ($row) {
                if ($row->is_section_header ?? false) {
                    return '';
                }

                $amount = (float) ($row->amount ?? 0);

                if ($amount == 0 && !($row->is_subtotal ?? false) && !($row->is_total ?? false)) {
                    return '';
                }

                if ($row->is_subtotal ?? false) {
                    return '<strong class="subtotal-amount">' . number_format($amount, 2) . '</strong>';
                }

                if ($row->is_total ?? false) {
                    return '<strong class="total-amount">' . number_format($amount, 2) . '</strong>';
                }

                return '<span class="amount-cell">' . number_format($amount, 2) . '</span>';
            })
            ->addColumn('amount2', function ($row2) {
                if ($row2->is_section_header ?? false) {
                    return '';
                }

                $amount2 = (float) ($row2->amount2 ?? 0);

                if ($amount2 == 0 && !($row2->is_subtotal ?? false) && !($row2->is_total ?? false)) {
                    return '';
                }

                if ($row2->is_subtotal ?? false) {
                    return '<strong class="subtotal-amount">' . number_format($amount2, 2) . '</strong>';
                }

                if ($row2->is_total ?? false) {
                    return '<strong class="total-amount">' . number_format($amount2, 2) . '</strong>';
                }

                return '<span class="amount-cell">' . number_format($amount2, 2) . '</span>';
            })

            ->addColumn('DT_RowClass', function($row) {
                $classes = [];
                
                if ($row->is_section_header ?? false) {
                    $classes[] = 'section-header-row';
                }
                
                if ($row->is_subtype_header ?? false) {
                    $classes[] = 'subtype-header-row';
                    $subtypeId = $row->subtype_id ?? 'subtype_' . str_replace(' ', '_', strtolower($row->name));
                    $classes[] = 'parent-subtype-' . $subtypeId;
                }
                
                if ($row->is_subtotal ?? false) {
                    $classes[] = 'subtotal-row';
                    if ($row->parent_subtype_id ?? false) {
                        $classes[] = 'child-of-subtype-' . $row->parent_subtype_id;
                    }
                }
                
                if ($row->is_total ?? false) {
                    $classes[] = 'total-row';
                }
                
                // Individual account rows
                if (($row->depth ?? 0) > 1 && !($row->is_subtotal ?? false) && !($row->is_total ?? false) && !($row->is_section_header ?? false) && !($row->is_subtype_header ?? false)) {
                    $classes[] = 'child-row';
                    if ($row->parent_subtype_id ?? false) {
                        $classes[] = 'child-of-subtype-' . $row->parent_subtype_id;
                    }
                }
                
                return implode(' ', $classes);
            })
            ->addColumn('DT_RowData', function($row) {
                $data = [];
                if ($row->subtype_id ?? false) {
                    $data['subtype-id'] = $row->subtype_id;
                }
                return $data;
            })
            ->rawColumns(['account', 'amount', 'amount2']);
    }

  public function query()
{
    $asOfDate1   = request()->get('endDate') ? Carbon::parse(request()->get('endDate'))->endOfDay()->format('Y-m-d 23:59:59') : Carbon::now()->endOfDay()->format('Y-m-d 23:59:59');
    $asOfDate2   = Carbon::parse($asOfDate1)->subYear()->endOfDay()->format('Y-m-d 23:59:59');
    // dd(request()->all(), $asOfDate1, $asOfDate2);

    // Single query with conditional aggregation for both dates
    $accounts = ChartOfAccount::where('chart_of_accounts.created_by', $this->companyId)
        ->leftJoin('chart_of_account_sub_types', 'chart_of_accounts.sub_type', '=', 'chart_of_account_sub_types.id')
        ->leftJoin('chart_of_account_types', 'chart_of_account_sub_types.type', '=', 'chart_of_account_types.id')
        ->leftJoin('journal_items', 'chart_of_accounts.id', '=', 'journal_items.account')
        ->leftJoin('journal_entries', function ($join) {
            $join->on('journal_items.journal', '=', 'journal_entries.id')
                 ->where("journal_entries.{$this->owner}", $this->companyId);
                 // ->where('journal_entries.status', 'posted'); // uncomment if needed
        })
        ->select([
            'chart_of_accounts.id',
            'chart_of_accounts.name',
            'chart_of_accounts.parent',
            'chart_of_account_sub_types.id as sub_type_id',
            'chart_of_account_sub_types.name as sub_type_name',
            'chart_of_account_types.id as type_id',
            'chart_of_account_types.name as type_name',

            // Period 1 totals
            DB::raw("COALESCE(SUM(CASE WHEN journal_items.created_at <= '$asOfDate1' THEN journal_items.debit ELSE 0 END), 0) as total_debit_1"),
            DB::raw("COALESCE(SUM(CASE WHEN journal_items.created_at <= '$asOfDate1' THEN journal_items.credit ELSE 0 END), 0) as total_credit_1"),

            // Period 2 totals
            DB::raw("COALESCE(SUM(CASE WHEN journal_items.created_at <= '$asOfDate2' THEN journal_items.debit ELSE 0 END), 0) as total_debit_2"),
            DB::raw("COALESCE(SUM(CASE WHEN journal_items.created_at <= '$asOfDate2' THEN journal_items.credit ELSE 0 END), 0) as total_credit_2"),
        ])
        ->groupBy(
            'chart_of_accounts.id',
            'chart_of_accounts.name',
            'chart_of_accounts.parent',
            'chart_of_account_sub_types.id',
            'chart_of_account_sub_types.name',
            'chart_of_account_types.id',
            'chart_of_account_types.name'
        )
        ->get();

    // Compute balances for both periods
    $accounts = $accounts->map(function ($acc) {
        if ($acc->type_name === 'Asset') {
            $acc->balance  = $acc->total_debit_1 - $acc->total_credit_1;
            $acc->balance2 = $acc->total_debit_2 - $acc->total_credit_2;
        } else {
            $acc->balance  = $acc->total_credit_1 - $acc->total_debit_1;
            $acc->balance2 = $acc->total_credit_2 - $acc->total_debit_2;
        }
        return $acc;
    });

    // Now pass this unified $accounts to your existing report-building logic
    $report = collect();

    $types = $accounts->groupBy('type_name');
    $totalAssets = $totalAssets2 = 0;
    $totalLiabilities = $totalLiabilities2 = 0;
    $totalEquity = $totalEquity2 = 0;

    foreach (['Assets', 'Liabilities', 'Equity'] as $typeName) {
        $typeAccounts = $types->get($typeName, collect());

        if ($typeAccounts->isEmpty() && $typeName !== 'Equity') {
            continue;
        }

        // Section Header
        $report->push((object)[
            'name' => strtoupper($typeName),
            'depth' => 0,
            'is_section_header' => true,
        ]);

        // SubTypes
        $subTypes = $typeAccounts->groupBy('sub_type_name');
        foreach ($subTypes as $subTypeName => $subTypeAccounts) {
            $subtypeId = 'subtype_' . str_replace(' ', '_', strtolower($subTypeName ?: 'uncategorized'));
            $report->push((object)[
                'name' => $subTypeName,
                'depth' => 1,
                'is_subtype_header' => true,
                'subtype_id' => $subtypeId,
                'has_children' => $subTypeAccounts->count() > 0,
            ]);

            // Root accounts only
            $roots = $subTypeAccounts->filter(function ($acc) use ($subTypeAccounts) {
                return !$subTypeAccounts->contains('id', $acc->parent);
            });

            foreach ($roots as $root) {
                $accountRows = $this->buildAccountTree($root, $subTypeAccounts, 2, $subtypeId);
                $report = $report->merge($accountRows);
            }

            // SubType Total
            $subTypeTotal  = $subTypeAccounts->sum('balance');
            $subTypeTotal2 = $subTypeAccounts->sum('balance2');
            $report->push((object)[
                'name' => "Total " . $subTypeName,
                'amount' => $subTypeTotal,
                'amount2' => $subTypeTotal2,
                'depth' => 1,
                'is_subtotal' => true,
                'parent_subtype_id' => $subtypeId,
            ]);
        }

        // Type Total
        $typeTotal  = $typeAccounts->sum('balance');
        $typeTotal2 = $typeAccounts->sum('balance2');

        if ($typeName === 'Assets') {
            $totalAssets = $typeTotal;
            $totalAssets2 = $typeTotal2;
        } elseif ($typeName === 'Liabilities') {
            $totalLiabilities = $typeTotal;
            $totalLiabilities2 = $typeTotal2;
        } elseif ($typeName === 'Equity') {
            $totalEquity = $typeTotal;
            $totalEquity2 = $typeTotal2;
        }

        $report->push((object)[
            'name' => "Total " . $typeName,
            'amount' => $typeTotal,
            'amount2' => $typeTotal2,
            'depth' => 0,
            'is_total' => true,
        ]);

        $report->push((object)['name' => '', 'amount' => null, 'amount2' => null]); // spacing
         // ---- Net Profit / Loss injected into Equity ----
        //  at the last of loop 
        if ($typeName === 'Equity') {
        $netProfit = DB::table('journal_items')
            ->join('chart_of_accounts', 'journal_items.account', '=', 'chart_of_accounts.id')
            ->join('chart_of_account_types', 'chart_of_accounts.type', '=', 'chart_of_account_types.id')
            ->join('journal_entries', 'journal_items.journal', '=', 'journal_entries.id')
            ->where("journal_entries.{$this->owner}", $this->companyId)
            ->where('journal_items.created_at', '<=', date('Y-m-d 23:59:59', strtotime($asOfDate1)))
            ->whereIn('chart_of_account_types.name', ['Income', 'Expenses', 'Costs of Goods Sold'])
            ->selectRaw('SUM(journal_items.credit - journal_items.debit) as net_profit')
            ->value('net_profit') ?? 0;
        $netProfit2 = DB::table('journal_items')
            ->join('chart_of_accounts', 'journal_items.account', '=', 'chart_of_accounts.id')
            ->join('chart_of_account_types', 'chart_of_accounts.type', '=', 'chart_of_account_types.id')
            ->join('journal_entries', 'journal_items.journal', '=', 'journal_entries.id')
            ->where("journal_entries.{$this->owner}", $this->companyId)
            ->where('journal_items.created_at', '<=', date('Y-m-d 23:59:59', strtotime($asOfDate2)))
            ->whereIn('chart_of_account_types.name', ['Income', 'Expenses', 'Costs of Goods Sold'])
            ->selectRaw('SUM(journal_items.credit - journal_items.debit) as net_profit')
            ->value('net_profit') ?? 0;
            $report->push((object)[
                'name' => "Accumulated (Loss) / Profit",
                'amount' => $netProfit,
                'amount2' => $netProfit2,
                'depth' => 0,
                'is_total' => true,
            ]);
            // TOTAL LIABILITIES & EQUITY row also add netProfit
            $report->push((object)[
                'name' => "TOTAL LIABILITIES & EQUITY",
                'amount' => $totalLiabilities + $totalEquity + $netProfit,
                'amount2' => $totalLiabilities2 + $totalEquity2 + $netProfit2,
                'depth' => 0,
                'is_total' => true,
            ]);
            $report->push((object)['name' => '', 'amount' => null, 'amount2' => null]); // spacing
        }
    }

    return $report;
}


    /**
     * Recursive helper to build parent-child hierarchy
     */
    private function buildAccountTree($account, $allAccounts, $depth, $subtypeId = null)
    {
        $rows = collect();

        // Push parent account
        $rows->push((object)[
            'name' => $account->name,
            'amount' => (float) ($account->balance ?? 0),
            'amount2' => (float) ($account->balance2 ?? 0),
            'depth' => $depth,
            'parent_subtype_id' => $subtypeId,
        ]);

        // Push children recursively
        $children = $allAccounts->where('parent', $account->id);
        foreach ($children as $child) {
            $rows = $rows->merge($this->buildAccountTree($child, $allAccounts, $depth + 1, $subtypeId));
        }

        return $rows;
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('balance-sheet-standard-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            // ->dom('Bfrtip')
            ->parameters([
                'paging' => false,
                'searching' => false,
                'info' => false,
                'ordering' => false,
                'scrollY' => '600px',
                'scrollCollapse' => true,
                'createdRow' => "function(row, data, dataIndex) {
                    if (data.DT_RowClass) {
                        $(row).addClass(data.DT_RowClass);
                    }
                    if (data.DT_RowData) {
                        for (let key in data.DT_RowData) {
                            $(row).attr('data-' + key, data.DT_RowData[key]);
                        }
                    }
                }"
            ]);
    }

    protected function getColumns()
    {
        $date1 = optional($this->asOfDate1)->format('M d, Y') ?? 'Current Period';
        $date2 = optional($this->asOfDate2)->format('M d, Y') ?? 'Previous Period';
        return [
            Column::make('account')->title('Account')->width('40%'),
            Column::make('amount')->title('As of ' . $date1)->width('30%')->addClass('text-right'),
            Column::make('amount2')->title('As of ' . $date2)->width('30%')->addClass('text-right'),
        ];
    }
}