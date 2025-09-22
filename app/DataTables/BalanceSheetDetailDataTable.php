<?php

namespace App\DataTables;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class BalanceSheetDetailDataTable extends DataTable
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
                $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $row->level ?? 0);
                
                // Main Type Headers (Asset, Liability, Equity)
                if ($row->is_type_header ?? false) {
                    return '<h4><strong>' . e($row->account_name) . '</strong></h4>';
                }
                
                // Sub Type Headers (Accounts Receivable, Cash, etc.) - Level 1
                if ($row->is_subtype_header ?? false) {
                    $hasChildren = $row->has_children ?? false;
                    $subtypeId = $row->subtype_id ?? 'subtype_' . str_replace(' ', '_', strtolower($row->account_name));
                    $chevron = '';
                    
                    if ($hasChildren) {
                        $chevron = '<i class="fas fa-chevron-down chevron-icon" data-parent-type="subtype" data-parent-id="' . $subtypeId . '" style="margin-right: 8px; cursor: pointer; color: #007bff;"></i>';
                    }
                    
                    return $indent . $chevron . '<strong>' . e($row->account_name) . '</strong>';
                }
                
                // Chart of Account Headers (1100 - Accounts Receivable, etc.) - Level 2
                if ($row->is_account_header ?? false) {
                    $hasChildren = $row->has_children ?? false;
                    $accountId = $row->account_id ?? 0;
                    $chevron = '';
                    
                    if ($hasChildren) {
                        $chevron = '<i class="fas fa-chevron-down chevron-icon" data-parent-type="account" data-parent-id="' . $accountId . '" style="margin-right: 8px; cursor: pointer; color: #007bff;"></i>';
                    } else {
                        $chevron = '<span style="margin-right: 20px;"></span>';
                    }
                    
                    return $indent . $chevron . e($row->account_code . ' - ' . $row->account_name);
                }
                
                // Total rows
                if ($row->is_account_total ?? false) {
                    return '<strong>' . $indent . 'Total ' . e($row->account_name) . '</strong>';
                }
                if ($row->is_subtype_total ?? false) {
                    return '<strong>' . $indent . 'Total ' . e($row->account_name) . '</strong>';
                }
                if ($row->is_type_total ?? false) {
                    return '<strong>Total ' . e($row->account_name) . '</strong>';
                }

                return '';
            })
            ->addColumn('date', fn($row) => $row->date ?? '')
            ->addColumn('transaction_type', fn($row) => $row->transaction_type ?? '')
            ->addColumn('num', fn($row) => $row->num ?? '')
            ->addColumn('memo', fn($row) => $row->memo ?? '')
            ->addColumn('split', fn($row) => $row->split_account ?? '')
            ->addColumn('debit', fn($row) => ($row->debit ?? null) ? number_format($row->debit, 2) : '')
            ->addColumn('credit', fn($row) => ($row->credit ?? null) ? number_format($row->credit, 2) : '')
            ->addColumn('amount', fn($row) => isset($row->amount) ? number_format($row->amount, 2) : '')
            ->addColumn('balance', fn($row) => isset($row->balance) ? number_format($row->balance, 2) : '')
            ->addColumn('DT_RowClass', function($row) {
                $classes = [];
                
                if ($row->is_type_header ?? false) $classes[] = 'type-header-row';
                if ($row->is_subtype_header ?? false) {
                    $classes[] = 'subtype-header-row';
                    $subtypeId = $row->subtype_id ?? 'subtype_' . str_replace(' ', '_', strtolower($row->account_name));
                    $classes[] = 'parent-subtype-' . $subtypeId;
                }
                if ($row->is_account_header ?? false) {
                    $classes[] = 'account-header-row';
                    $classes[] = 'parent-account-' . ($row->account_id ?? 0);
                    if ($row->parent_subtype_id ?? false) {
                        $classes[] = 'child-of-subtype-' . $row->parent_subtype_id;
                    }
                }
                if ($row->is_account_total ?? false) {
                    $classes[] = 'account-total-row';
                    if ($row->parent_account_id ?? false) {
                        $classes[] = 'child-of-account-' . $row->parent_account_id;
                    }
                    if ($row->parent_subtype_id ?? false) {
                        $classes[] = 'child-of-subtype-' . $row->parent_subtype_id;
                    }
                }
                if ($row->is_subtype_total ?? false) {
                    $classes[] = 'subtype-total-row';
                    if ($row->parent_subtype_id ?? false) {
                        $classes[] = 'child-of-subtype-' . $row->parent_subtype_id;
                    }
                }
                if ($row->is_type_total ?? false) $classes[] = 'type-total-row';
                if ($row->is_transaction ?? false) {
                    $classes[] = 'transaction-row';
                    if ($row->parent_account_id ?? false) {
                        $classes[] = 'child-of-account-' . $row->parent_account_id;
                    }
                    if ($row->parent_subtype_id ?? false) {
                        $classes[] = 'child-of-subtype-' . $row->parent_subtype_id;
                    }
                }
                
                return implode(' ', $classes);
            })
            ->addColumn('DT_RowData', function($row) {
                $data = [];
                
                if ($row->is_transaction ?? false) {
                    $data['transaction-id'] = $row->transaction_id ?? 0;
                }
                if ($row->account_id ?? false) {
                    $data['account-id'] = $row->account_id;
                }
                if ($row->subtype_id ?? false) {
                    $data['subtype-id'] = $row->subtype_id;
                }
                
                return $data;
            })
            ->rawColumns(['account']);
    }

    public function query()
    {
        $entries = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'journal_entry_lines.chart_of_account_id', '=', 'chart_of_accounts.id')
            ->leftJoin('chart_of_account_sub_types', 'chart_of_accounts.chart_of_account_sub_type_id', '=', 'chart_of_account_sub_types.id')
            ->leftJoin('chart_of_account_types', 'chart_of_account_sub_types.chart_of_account_type_id', '=', 'chart_of_account_types.id')
            ->where('journal_entries.company_id', company()->id)
            ->where('journal_entries.date', '<=', $this->asOfDate)
            ->where('journal_entries.status', 'draft')
            ->whereIn('chart_of_account_types.name', ['Asset', 'Liability', 'Equity'])
            ->select([
                'journal_entries.id as journal_id',
                'journal_entries.date',
                'journal_entries.voucher_type as transaction_type',
                'journal_entries.number as num',
                'journal_entries.memo as memo',
                'journal_entry_lines.debit',
                'journal_entry_lines.credit',
                DB::raw('0 as amount'),
                'chart_of_accounts.id as account_id',
                'chart_of_accounts.name as account_name',
                'chart_of_accounts.code as account_code',
                'chart_of_accounts.parent_id as parent_id',
                'chart_of_account_sub_types.id as subtype_db_id',
                'chart_of_account_sub_types.name as subtype_name',
                'chart_of_account_types.name as type_name',
            ])
            ->orderBy('chart_of_account_types.name')
            ->orderBy('chart_of_account_sub_types.name')
            ->orderBy('chart_of_accounts.parent_id')
            ->orderBy('chart_of_accounts.name')
            ->orderBy('journal_entries.date')
            ->get();

        $groupedByType = $entries->groupBy('type_name');
        $report = collect();

        $totalLiabilities = 0;
        $totalEquity = 0;

        foreach ($groupedByType as $typeName => $typeEntries) {
            // === Type Header (Asset, Liability, Equity) ===
            $report->push((object)[
                'is_type_header' => true,
                'account_name' => $typeName,
                'level' => 0,
            ]);

            $groupedBySubType = $typeEntries->groupBy('subtype_name');
            foreach ($groupedBySubType as $subTypeName => $subEntries) {
                $subtypeId = 'subtype_' . str_replace(' ', '_', strtolower($subTypeName ?: 'uncategorized'));
                
                // Check if this subtype has accounts
                $hasAccounts = $subEntries->groupBy('account_id')->count() > 0;
                
                // === SubType Header (Accounts Receivable, Cash, etc.) ===
                $report->push((object)[
                    'is_subtype_header' => true,
                    'account_name' => $subTypeName ?: 'Uncategorized',
                    'subtype_id' => $subtypeId,
                    'has_children' => $hasAccounts,
                    'level' => 1,
                ]);

                // Process accounts within this subtype
                $report = $this->processAccounts($subEntries, $report, null, 2, $subtypeId);

                // === SubType Total ===
                $subTypeTotal = $subEntries->sum(function ($l) use ($subEntries) {
                    $accountType = $subEntries->first()->type_name;
                    return in_array($accountType, ['Asset', 'Expense'])
                        ? (($l->debit ?? 0) - ($l->credit ?? 0))
                        : (($l->credit ?? 0) - ($l->debit ?? 0));
                });

                $report->push((object)[
                    'is_subtype_total' => true,
                    'account_name' => $subTypeName ?: 'Uncategorized',
                    'parent_subtype_id' => $subtypeId,
                    'balance' => $subTypeTotal,
                    'level' => 1,
                ]);
            }

            // === Type Total ===
            $typeTotal = $typeEntries->sum(function ($l) use ($typeEntries) {
                $accountType = $typeEntries->first()->type_name;
                return in_array($accountType, ['Asset', 'Expense'])
                    ? (($l->debit ?? 0) - ($l->credit ?? 0))
                    : (($l->credit ?? 0) - ($l->debit ?? 0));
            });

            $report->push((object)[
                'is_type_total' => true,
                'account_name' => $typeName,
                'balance' => $typeTotal,
                'level' => 0,
            ]);

            if ($typeName === 'Liability') {
                $totalLiabilities = $typeTotal;
            }

            if ($typeName === 'Equity') {
                $totalEquity = $typeTotal;

                // === Profit/Loss Section ===
                $plEntries = DB::table('journal_entry_lines')
                    ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                    ->join('chart_of_accounts', 'journal_entry_lines.chart_of_account_id', '=', 'chart_of_accounts.id')
                    ->leftJoin('chart_of_account_sub_types', 'chart_of_accounts.chart_of_account_sub_type_id', '=', 'chart_of_account_sub_types.id')
                    ->leftJoin('chart_of_account_types', 'chart_of_account_sub_types.chart_of_account_type_id', '=', 'chart_of_account_types.id')
                    ->where('journal_entries.company_id', company()->id)
                    ->where('journal_entries.date', '<=', $this->asOfDate)
                    ->where('journal_entries.status', 'draft')
                    ->whereIn('chart_of_account_types.name', ['Income', 'Expense'])
                    ->select([
                        'journal_entries.id as journal_id',
                        'journal_entries.date',
                        'journal_entries.voucher_type as transaction_type',
                        'journal_entries.number as num',
                        'journal_entries.memo as memo',
                        'journal_entry_lines.debit',
                        'journal_entry_lines.credit',
                        DB::raw('0 as amount'),
                        'chart_of_accounts.id as account_id',
                        'chart_of_accounts.name as account_name',
                        'chart_of_accounts.code as account_code',
                        'chart_of_accounts.parent_id as parent_id',
                        'chart_of_account_sub_types.id as subtype_db_id',
                        'chart_of_account_sub_types.name as subtype_name',
                        'chart_of_account_types.name as type_name',
                    ])
                    ->orderBy('chart_of_account_types.name')
                    ->orderBy('chart_of_account_sub_types.name')
                    ->orderBy('chart_of_accounts.parent_id')
                    ->orderBy('chart_of_accounts.name')
                    ->orderBy('journal_entries.date')
                    ->get();

                $netProfit = $plEntries->sum(function ($l) {
                    if ($l->type_name === 'Income') {
                        return ($l->credit ?? 0) - ($l->debit ?? 0);
                    } elseif ($l->type_name === 'Expense') {
                        return -1 * (($l->debit ?? 0) - ($l->credit ?? 0));
                    }
                    return 0;
                });

                // === Show Accumulated Profit/Loss Section ===
                $plSubtypeId = 'subtype_accumulated_profit_loss';
                $report->push((object)[
                    'is_subtype_header' => true,
                    'account_name' => "Accumulated (Loss) / Profit",
                    'subtype_id' => $plSubtypeId,
                    'has_children' => $plEntries->count() > 0,
                    'level' => 1,
                ]);

                $report = $this->processAccounts($plEntries, $report, null, 2, $plSubtypeId);

                $report->push((object)[
                    'is_subtype_total' => true,
                    'account_name' => "Accumulated (Loss) / Profit",
                    'parent_subtype_id' => $plSubtypeId,
                    'balance' => $netProfit,
                    'level' => 1,
                ]);

                $totalEquity += $netProfit;

                // spacing
                $report->push((object)[
                    'account_name' => '',
                    'balance' => null,
                ]);

                // === FINAL LIABILITIES & EQUITY TOTAL ===
                $report->push((object)[
                    'is_type_total' => true,
                    'account_name' => "TOTAL LIABILITIES & EQUITY",
                    'balance' => $totalLiabilities + $totalEquity,
                    'level' => 0,
                ]);
            }
        }

        return $report;
    }

    // Enhanced recursive function to process account tree with hierarchy tracking
    protected function processAccounts($accounts, $report, $parentId = null, $level = 0, $subtypeId = null)
    {
        $grouped = $accounts->where('parent_id', $parentId)->groupBy('account_id');

        foreach ($grouped as $accountId => $lines) {
            $balance = 0;
            
            // Check if this account has child transactions
            $hasTransactions = $lines->count() > 0;
            
            // Check if this account has child accounts
            $hasChildAccounts = $accounts->where('parent_id', $accountId)->count() > 0;
            
            $hasChildren = $hasTransactions || $hasChildAccounts;

            // === Account Header (1100 - Accounts Receivable, etc.) ===
            $report->push((object)[
                'is_account_header' => true,
                'account_name' => $lines->first()->account_name,
                'account_code' => $lines->first()->account_code,
                'account_id' => $accountId,
                'parent_subtype_id' => $subtypeId,
                'has_children' => $hasChildren,
                'level' => $level,
            ]);

            // Add individual transaction lines
            foreach ($lines as $line) {
                $accountType = $lines->first()->type_name;

                if (in_array($accountType, ['Asset', 'Expense'])) {
                    $amount = (($line->debit ?? 0) - ($line->credit ?? 0));
                    $balance += $amount;
                } else {
                    $amount = (($line->credit ?? 0) - ($line->debit ?? 0));
                    $balance += $amount;
                }

                // Find opposite (split) accounts
                $splitAccounts = DB::table('journal_entry_lines')
                    ->join('chart_of_accounts', 'journal_entry_lines.chart_of_account_id', '=', 'chart_of_accounts.id')
                    ->where('journal_entry_lines.journal_entry_id', $line->journal_id)
                    ->where('journal_entry_lines.chart_of_account_id', '!=', $line->account_id)
                    ->pluck('chart_of_accounts.name')
                    ->implode(', ');

                $report->push((object)[
                    'is_transaction' => true,
                    'parent_account_id' => $accountId,
                    'parent_subtype_id' => $subtypeId, // CRITICAL: Add subtype relationship to transactions
                    'transaction_id' => $line->journal_id,
                    'date' => $line->date,
                    'transaction_type' => $line->transaction_type,
                    'num' => $line->num,
                    'memo' => $line->memo,
                    'split_account' => $splitAccounts,
                    'debit' => (float) ($line->debit ?? 0),
                    'credit' => (float) ($line->credit ?? 0),
                    'amount' => (float) $amount,
                    'balance' => $balance,
                    'level' => $level + 1,
                ]);
            }

            // === Account Total ===
            $report->push((object)[
                'is_account_total' => true,
                'account_name' => $lines->first()->account_name,
                'parent_account_id' => $accountId,
                'parent_subtype_id' => $subtypeId, // CRITICAL: Add subtype relationship to account totals
                'balance' => $balance,
                'level' => $level,
            ]);

            // === Process Child Accounts recursively ===
            $childAccounts = $accounts->where('parent_id', $accountId);
            if ($childAccounts->count() > 0) {
                $report = $this->processAccounts($accounts, $report, $accountId, $level + 1, $subtypeId);
            }
        }

        return $report;
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('balance-sheet-detail-table')
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
            Column::make('account')->title('Account'),
            Column::make('date')->title('Date'),
            Column::make('transaction_type')->title('Type'),
            Column::make('num')->title('Num'),
            Column::make('memo')->title('Memo/Description'),
            Column::make('split')->title('Split Account'),
            Column::make('debit')->title('Debit')->addClass('text-right'),
            Column::make('credit')->title('Credit')->addClass('text-right'),
            Column::make('amount')->title('Amount')->addClass('text-right'),
            Column::make('balance')->title('Balance')->addClass('text-right'),
        ];
    }
}