<?php

namespace App\DataTables;

use App\Models\ChartOfAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class BalanceSheetDataTable extends DataTable
{
    protected $asOfDate;
    protected $companyId;
    protected $owner;

    public function __construct()
    {
        $this->asOfDate = request('asOfDate')
            ? Carbon::parse(request('asOfDate'))->endOfDay()->format('Y-m-d')
            : Carbon::now()->endOfDay()->format('Y-m-d');
        
        $this->companyId = \Auth::user()->type === 'company' ? \Auth::user()->creatorId() : \Auth::user()->ownedId();
        $this->owner = \Auth::user()->type === 'company' ? 'created_by' : 'owned_by';
    }

    public function dataTable($query)
    {
        return datatables()
            ->collection($query)
            ->addColumn('DT_RowClass', function ($row) {
                $classes = [];
                
                if (!empty($row->is_section_header)) {
                    $classes[] = 'section-header-row';
                    $classes[] = 'parent-row';
                } elseif (!empty($row->is_subtotal)) {
                    $classes[] = 'subtotal-row';
                    $classes[] = 'child-row';
                } elseif (!empty($row->is_total)) {
                    $classes[] = 'total-row';
                } elseif (!empty($row->is_child)) {
                    $classes[] = 'account-detail';
                    $classes[] = 'child-row';
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
                
                $data['row-id'] = $row->id ?? 'row-' . uniqid();
                
                return $data;
            })
            ->addColumn('account_name', function ($row) {
                $indent = $this->getIndentation($row);
                
                // Section header with toggle functionality
                if ($row->is_section_header) {
                    $sectionTotal = isset($row->section_total) ? number_format(abs($row->section_total), 2) : '0.00';
                    
                    return $indent . '
                        <div class="toggle-section" data-section="' . $row->id . '">
                            <i class="fa fa-chevron-right toggle-chevron"></i>
                            <strong class="section-header">' . e($row->name) . '</strong>
                            <span class="section-total-amount" data-group="' . $row->id . '"> - ' . $sectionTotal . '</span>
                        </div>';
                }
                
                // Total row
                if ($row->is_total) {
                    return '<strong class="total-label">' . e($row->name) . '</strong>';
                }
                
                // Subtotal row
                if ($row->is_subtotal) {
                    return $indent . '<strong class="subtotal-label">' . e($row->name) . '</strong>';
                }
                
                // Child account row
                if ($row->is_child) {
                    return $indent . '<span class="account-name">' . e($row->name) . '</span>';
                }
                
                // Empty row
                return '';
            })
            ->addColumn('amount', function ($row) {
                if ($row->is_section_header) {
                    // Don't show amount in header - it's shown in the account name column
                    return '';
                }
                
                if ($row->is_total) {
                    $amount = isset($row->net) ? abs($row->net) : 0;
                    return '<strong class="total-amount">' . number_format($amount, 2) . '</strong>';
                }
                
                if ($row->is_subtotal) {
                    $amount = isset($row->net) ? abs($row->net) : 0;
                    return '<strong class="subtotal-amount">' . number_format($amount, 2) . '</strong>';
                }
                
                if ($row->is_child && isset($row->amount) && $row->amount != 0) {
                    return '<span class="amount-cell">' . number_format(abs($row->amount), 2) . '</span>';
                }
                
                return '';
            })
            ->rawColumns(['account_name', 'amount']);
    }

    private function getIndentation($row)
    {
        if (!empty($row->is_section_header) || !empty($row->is_total)) {
            return '';
        } elseif (!empty($row->is_subtotal)) {
            return '<span class="indent-spacer"></span>';
        } elseif (!empty($row->is_child)) {
            return '<span class="indent-spacer"></span><span class="indent-spacer"></span>';
        }
        
        return '';
    }

    public function query()
    {
        $accounts = ChartOfAccount::where('chart_of_accounts.created_by', $this->companyId)
            ->leftJoin('chart_of_account_types', 'chart_of_accounts.type', '=', 'chart_of_account_types.id')
            ->leftJoin('journal_items', 'chart_of_accounts.id', '=', 'journal_items.account')
            ->leftJoin('journal_entries', 'journal_items.journal', '=', 'journal_entries.id')
            ->where("journal_entries.{$this->owner}", $this->companyId)
            ->where('journal_entries.date', '<=', $this->asOfDate)
            ->select([
                'chart_of_accounts.id',
                'chart_of_accounts.name',
                'chart_of_account_types.name as account_type',
                DB::raw('COALESCE(SUM(journal_items.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_items.credit), 0) as total_credit'),
            ])
            ->whereIn('chart_of_account_types.name', ['Asset', 'Liability', 'Equity'])
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.name', 'chart_of_account_types.name')
            ->orderBy('chart_of_account_types.name')
            ->orderBy('chart_of_accounts.name')
            ->get();

        return $this->buildHierarchicalBalanceSheet($accounts);
    }

    private function buildHierarchicalBalanceSheet($accounts)
    {
        $report = collect();

        $emptyRow = function ($name = '', $amount = 0, $net = 0, $flags = []) {
            return (object) array_merge([
                'id' => 'row-' . uniqid(),
                'parent_id' => null,
                'name' => $name,
                'amount' => $amount,
                'net' => $net,
                'is_section_header' => false,
                'is_subtotal' => false,
                'is_total' => false,
                'is_child' => false,
                'has_children' => false,
            ], $flags);
        };

        // ---------- Assets Section ----------
        $assetAccounts = $accounts->where('account_type', 'Asset')->map(function ($acc) {
            $amount = $acc->total_debit - $acc->total_credit;
            return (object) [
                'id' => 'asset-acc-' . $acc->id,
                'parent_id' => 'assets-section',
                'name' => $acc->name,
                'amount' => $amount,
                'net' => $amount,
                'is_child' => true,
                'is_section_header' => false,
                'is_total' => false,
                'is_subtotal' => false,
                'has_children' => false,
            ];
        });
        $totalAssets = $assetAccounts->sum(fn($acc) => $acc->amount);

        // Add Assets section header
        $report->push($emptyRow('ASSETS', 0, 0, [
            'id' => 'assets-section',
            'is_section_header' => true,
            'has_children' => true,
            'section_total' => $totalAssets
        ]));
        
        // Add asset accounts (initially hidden)
        $report = $report->merge($assetAccounts);
        
        // Add assets subtotal (initially hidden)
        $report->push($emptyRow('Total Assets', 0, $totalAssets, [
            'id' => 'assets-subtotal',
            'parent_id' => 'assets-section',
            'is_subtotal' => true
        ]));
        
        // Empty row for spacing
        $report->push($emptyRow(''));

        // ---------- Liabilities Section ----------
        $liabilityAccounts = $accounts->where('account_type', 'Liability')->map(function ($acc) {
            $amount = $acc->total_credit - $acc->total_debit;
            return (object) [
                'id' => 'liability-acc-' . $acc->id,
                'parent_id' => 'liabilities-section',
                'name' => $acc->name,
                'amount' => $amount,
                'net' => $amount,
                'is_child' => true,
                'is_section_header' => false,
                'is_total' => false,
                'is_subtotal' => false,
                'has_children' => false,
            ];
        });
        $totalLiabilities = $liabilityAccounts->sum(fn($acc) => $acc->amount);

        // Add Liabilities section header
        $report->push($emptyRow('LIABILITIES', 0, 0, [
            'id' => 'liabilities-section',
            'is_section_header' => true,
            'has_children' => true,
            'section_total' => $totalLiabilities
        ]));
        
        // Add liability accounts (initially hidden)
        $report = $report->merge($liabilityAccounts);
        
        // Add liabilities subtotal (initially hidden)
        $report->push($emptyRow('Total Liabilities', 0, $totalLiabilities, [
            'id' => 'liabilities-subtotal',
            'parent_id' => 'liabilities-section',
            'is_subtotal' => true
        ]));
        
        // Empty row for spacing
        $report->push($emptyRow(''));

        // ---------- Equity Section ----------
        $equityAccounts = $accounts->where('account_type', 'Equity')->map(function ($acc) {
            $amount = $acc->total_credit - $acc->total_debit;
            return (object) [
                'id' => 'equity-acc-' . $acc->id,
                'parent_id' => 'equity-section',
                'name' => $acc->name,
                'amount' => $amount,
                'net' => $amount,
                'is_child' => true,
                'is_section_header' => false,
                'is_total' => false,
                'is_subtotal' => false,
                'has_children' => false,
            ];
        });
        $totalEquity = $equityAccounts->sum(fn($acc) => $acc->amount);

        // Add Equity section header
        $report->push($emptyRow('EQUITY', 0, 0, [
            'id' => 'equity-section',
            'is_section_header' => true,
            'has_children' => true,
            'section_total' => $totalEquity
        ]));
        
        // Add equity accounts (initially hidden)
        $report = $report->merge($equityAccounts);
        
        // Add equity subtotal (initially hidden)
        $report->push($emptyRow('Total Equity', 0, $totalEquity, [
            'id' => 'equity-subtotal',
            'parent_id' => 'equity-section',
            'is_subtotal' => true
        ]));

        // ---------- Net Profit/Loss ----------
        $netProfit = $this->calculateNetProfit();
        
        $report->push($emptyRow('Retained Earnings', 0, $netProfit, [
            'id' => 'retained-earnings',
            'is_subtotal' => true
        ]));

        // Empty row for spacing
        $report->push($emptyRow(''));

        // ---------- Final Total ----------
        $grandTotal = $totalLiabilities + $totalEquity + $netProfit;
        $report->push($emptyRow('TOTAL LIABILITIES & EQUITY', 0, $grandTotal, [
            'id' => 'grand-total',
            'is_total' => true
        ]));

        return $report;
    }

    private function calculateNetProfit()
    {
        $netProfit = DB::table('journal_items')
            ->join('chart_of_accounts', 'journal_items.account', '=', 'chart_of_accounts.id')
            ->join('chart_of_account_types', 'chart_of_accounts.type', '=', 'chart_of_account_types.id')
            ->join('journal_entries', 'journal_items.journal', '=', 'journal_entries.id')
            ->where("journal_entries.{$this->owner}", $this->companyId)
            ->where('journal_entries.date', '<=', $this->asOfDate)
            ->whereIn('chart_of_account_types.name', ['Income', 'Expense'])
            ->selectRaw('SUM(journal_items.credit - journal_items.debit) as net_profit')
            ->value('net_profit');

        return $netProfit ?? 0;
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('balance-sheet-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->parameters([
                'paging' => false,
                'searching' => false,
                'info' => false,
                'ordering' => false,
                'scrollY' => '600px',
                'scrollCollapse' => true,
                'autoWidth' => false,
                'responsive' => true,
                'createdRow' => "function(row, data, dataIndex) {
                    // Add CSS classes
                    if (data.DT_RowClass) {
                        $(row).addClass(data.DT_RowClass);
                    }
                    
                    // Add data attributes
                    if (data.DT_RowData) {
                        for (let key in data.DT_RowData) {
                            $(row).attr('data-' + key, data.DT_RowData[key]);
                        }
                    }
                    
                    // Add group class for parent-child relationship
                    if (data.DT_RowData && data.DT_RowData.parent) {
                        $(row).addClass('group-' + data.DT_RowData.parent);
                    }
                }",
                'drawCallback' => "function(settings) {
                    // Trigger custom draw event for our JavaScript
                    $('#balance-sheet-table').trigger('table-redrawn');
                }"
            ]);
    }

    protected function getColumns()
    {
        return [
            Column::make('account_name')
                ->title('Account')
                ->width('70%')
                ->addClass('account-name-col'),
            Column::make('amount')
                ->title('Amount')
                ->width('30%')
                ->addClass('text-right amount-col'),
        ];
    }
}