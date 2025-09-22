<?php

namespace App\DataTables;

use App\Models\ChartOfAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Illuminate\Support\Collection;

class BalanceSheetStandardDataTable extends DataTable
{
    protected $asOfDate;

    public function __construct()
    {
        parent::__construct();

        $this->asOfDate = request('asOfDate')
            ? Carbon::parse(request('asOfDate'))->endOfDay()
            : Carbon::now()->endOfDay();
    }

    public function dataTable($query)
    {
        return datatables()
            ->collection($query)
            ->addColumn('account', function ($row) {
                if ($row->is_section_header ?? false) {
                    return '<strong class="section-header">' . e($row->name) . '</strong>';
                }
                
                // Sub Type Headers with chevrons
                if ($row->is_subtype_header ?? false) {
                    $hasChildren = $row->has_children ?? false;
                    $subtypeId = $row->subtype_id ?? 'subtype_' . str_replace(' ', '_', strtolower($row->name));
                    $chevron = '';
                    
                    if ($hasChildren) {
                        $chevron = '<i class="fas fa-chevron-down chevron-icon" data-parent-type="subtype" data-parent-id="' . $subtypeId . '" style="margin-right: 8px; cursor: pointer; color: #007bff;"></i>';
                    }
                    
                    $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', (int) ($row->depth ?? 0));
                    return $indent . $chevron . '<strong class="subtotal-label">' . e($row->name) . '</strong>';
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
            ->rawColumns(['account', 'amount']);
    }

    public function query()
    {
        // Get all accounts with balances
        $accounts = ChartOfAccount::where('chart_of_accounts.company_id', company()->id)
            ->leftJoin('chart_of_account_sub_types', 'chart_of_accounts.chart_of_account_sub_type_id', '=', 'chart_of_account_sub_types.id')
            ->leftJoin('chart_of_account_types', 'chart_of_account_sub_types.chart_of_account_type_id', '=', 'chart_of_account_types.id')
            ->leftJoin('journal_entry_lines', 'chart_of_accounts.id', '=', 'journal_entry_lines.chart_of_account_id')
            ->leftJoin('journal_entries', function($join) {
                $join->on('journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                    ->where('journal_entries.company_id', company()->id)
                    ->where('journal_entries.date', '<=', $this->asOfDate)
                    ->where('journal_entries.status', 'draft');
            })
            ->select([
                'chart_of_accounts.id',
                'chart_of_accounts.name',
                'chart_of_accounts.parent_id',
                'chart_of_account_sub_types.id as sub_type_id',
                'chart_of_account_sub_types.name as sub_type_name',
                'chart_of_account_types.id as type_id',
                'chart_of_account_types.name as type_name',
                DB::raw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit'),
            ])
            ->groupBy(
                'chart_of_accounts.id',
                'chart_of_accounts.name',
                'chart_of_accounts.parent_id',
                'chart_of_account_sub_types.id',
                'chart_of_account_sub_types.name',
                'chart_of_account_types.id',
                'chart_of_account_types.name'
            )
            ->get();

        // Calculate balances
        $accounts = $accounts->map(function($acc) {
            if ($acc->type_name === 'Asset') {
                $acc->balance = $acc->total_debit - $acc->total_credit;
            } else {
                $acc->balance = $acc->total_credit - $acc->total_debit;
            }
            return $acc;
        });

        $report = collect();

        // Build sections (Assets, Liabilities, Equity)
        $types = $accounts->groupBy('type_name');
        $totalAssets = 0;
        $totalLiabilities = 0;
        $totalEquity = 0;

        foreach (['Asset', 'Liability', 'Equity'] as $typeName) {
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

            // SubTypes inside this Type
            $subTypes = $typeAccounts->groupBy('sub_type_name');
            foreach ($subTypes as $subTypeName => $subTypeAccounts) {
                $subtypeId = 'subtype_' . str_replace(' ', '_', strtolower($subTypeName ?: 'uncategorized'));
                
                // Check if this subtype has accounts
                $hasAccounts = $subTypeAccounts->count() > 0;
                
                // SubType Header with chevron functionality
                $report->push((object)[
                    'name' => $subTypeName,
                    'depth' => 1,
                    'is_subtype_header' => true,
                    'subtype_id' => $subtypeId,
                    'has_children' => $hasAccounts,
                ]);

                // Find only parent/root accounts for this subtype
                $roots = $subTypeAccounts->filter(function($acc) use ($subTypeAccounts) {
                    return !$subTypeAccounts->contains('id', $acc->parent_id);
                });

                foreach ($roots as $root) {
                    $accountRows = $this->buildAccountTree($root, $subTypeAccounts, 2, $subtypeId);
                    $report = $report->merge($accountRows);
                }

                // SubType Total
                $subTypeTotal = $subTypeAccounts->sum('balance');
                $report->push((object)[
                    'name' => "Total " . $subTypeName,
                    'amount' => $subTypeTotal,
                    'depth' => 1,
                    'is_subtotal' => true,
                    'parent_subtype_id' => $subtypeId,
                ]);
            }

            // Type Total
            $typeTotal = $typeAccounts->sum('balance');
            if ($typeName === 'Asset') {
                $totalAssets = $typeTotal;
            } elseif ($typeName === 'Liability') {
                $totalLiabilities = $typeTotal;
            } elseif ($typeName === 'Equity') {
                $totalEquity = $typeTotal;
            }

            $report->push((object)[
                'name' => "Total " . $typeName,
                'amount' => $typeTotal,
                'depth' => 0,
                'is_total' => true,
            ]);

            $report->push((object)['name' => '', 'amount' => null]); // spacing
        }

        // Add Net Profit / Loss into Equity
        $netProfit = DB::table('journal_entry_lines')
            ->join('chart_of_accounts', 'journal_entry_lines.chart_of_account_id', '=', 'chart_of_accounts.id')
            ->join('chart_of_account_types', 'chart_of_accounts.chart_of_account_type_id', '=', 'chart_of_account_types.id')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', company()->id)
            ->where('journal_entries.date', '<=', $this->asOfDate)
            ->where('journal_entries.status', 'draft')
            ->whereIn('chart_of_account_types.name', ['Income', 'Expense'])
            ->selectRaw('SUM(journal_entry_lines.credit - journal_entry_lines.debit) as net_profit')
            ->value('net_profit') ?? 0;

        $report->push((object)[
            'name' => "Accumulated (Loss) / Profit",
            'amount' => $netProfit,
            'depth' => 1,
        ]);

        $totalEquity += $netProfit;
        $report->push((object)['name' => '', 'amount' => null]); // spacing

        // Final TOTAL LIABILITIES + EQUITY
        $report->push((object)[
            'name' => "TOTAL LIABILITIES & EQUITY",
            'amount' => $totalLiabilities + $totalEquity,
            'depth' => 0,
            'is_total' => true,
        ]);

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
            'depth' => $depth,
            'parent_subtype_id' => $subtypeId,
        ]);

        // Push children recursively
        $children = $allAccounts->where('parent_id', $account->id);
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
            ->dom('Bfrtip')
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
        return [
            Column::make('account')->title('')->width('70%'),
            Column::make('amount')->title('TOTAL')->width('30%')->addClass('text-right'),
        ];
    }
}