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
    protected $companyId;
    protected $owner;
    protected $accountingMethod;

    public function __construct()
    {
        parent::__construct();

        $this->asOfDate = request('endDate')
            ? Carbon::parse(request('endDate'))->endOfDay()
            : Carbon::now()->endOfDay();

        $this->companyId = \Auth::user()->type === 'company' ? \Auth::user()->creatorId() : \Auth::user()->ownedId();
        $this->owner = \Auth::user()->type === 'company' ? 'created_by' : 'owned_by';
        $this->accountingMethod = request('accounting_method', 'accrual'); // default accrual
    }

    public function dataTable($query)
    {
        return datatables()
            ->collection($query)
            ->addColumn('account', function ($row) {
                if ($row->is_section_header ?? false) {
                    return '<strong class="section-header">' . e($row->name) . '</strong>';
                }

                if ($row->is_subtype_header ?? false) {
                    $hasChildren = $row->has_children ?? false;
                    $subtypeId = $row->subtype_id ?? 'subtype_' . str_replace(' ', '_', strtolower($row->name));
                    $chevron = '';

                    if ($hasChildren) {
                        $chevron = '<i class=" chevron-icon" data-parent-type="subtype" data-parent-id="' . $subtypeId . '" style="margin-right: 8px; cursor: pointer;">▼</i>';
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
            ->addColumn('DT_RowClass', function ($row) {
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

                if (($row->depth ?? 0) > 1 && !($row->is_subtotal ?? false) && !($row->is_total ?? false) && !($row->is_section_header ?? false) && !($row->is_subtype_header ?? false)) {
                    $classes[] = 'child-row';
                    if ($row->parent_subtype_id ?? false) {
                        $classes[] = 'child-of-subtype-' . $row->parent_subtype_id;
                    }
                }

                return implode(' ', $classes);
            })
            ->addColumn('DT_RowData', function ($row) {
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
    $cashSubTypes = ['bank', 'cash'];
    $allPaymentVouchers = [];

    if ($this->accountingMethod == 'cash') {
        $invoicePaymentVouchers = DB::table('invoice_payments')
            ->where('date', '<=', $this->asOfDate->format('Y-m-d 23:59:59'))
            ->whereNotNull('voucher_id')
            ->pluck('voucher_id')
            ->toArray();

        $billPaymentVouchers = DB::table('bill_payments')
            ->where('date', '<=', $this->asOfDate->format('Y-m-d 23:59:59'))
            ->whereNotNull('voucher_id')
            ->pluck('voucher_id')
            ->toArray();

        $allPaymentVouchers = array_values(array_unique(array_merge($invoicePaymentVouchers, $billPaymentVouchers)));
    }

    $query = ChartOfAccount::where('chart_of_accounts.created_by', $this->companyId)
        ->leftJoin('chart_of_account_types', 'chart_of_accounts.type', '=', 'chart_of_account_types.id')
        ->leftJoin('chart_of_account_sub_types', 'chart_of_accounts.sub_type', '=', 'chart_of_account_sub_types.id') // ✅ added correct join
        ->leftJoin('journal_items', 'chart_of_accounts.id', '=', 'journal_items.account')
        ->leftJoin('journal_entries', 'journal_items.journal', '=', 'journal_entries.id')
        ->where("journal_entries.{$this->owner}", $this->companyId)
        ->where('journal_entries.date', '<=', $this->asOfDate->format('Y-m-d 23:59:59'));

    if ($this->accountingMethod == 'cash') {
        $cashSqlVariants = array_merge($cashSubTypes, array_map('ucfirst', $cashSubTypes));

        $query->where(function ($q) use ($allPaymentVouchers, $cashSqlVariants) {
            if (!empty($allPaymentVouchers)) {
                $q->whereIn('journal_entries.id', $allPaymentVouchers)
                    ->orWhereIn('chart_of_accounts.sub_type', $cashSqlVariants);
            } else {
                $q->whereIn('chart_of_accounts.sub_type', $cashSqlVariants);
            }
        });
    }

    $accounts = $query->select([
            'chart_of_accounts.id',
            'chart_of_accounts.name',
            'chart_of_accounts.parent',
            'chart_of_accounts.sub_type',
            'chart_of_account_sub_types.name as subtype_name', // ✅ corrected alias
            'chart_of_account_types.name as account_type',
            DB::raw('COALESCE(SUM(journal_items.debit), 0) as total_debit'),
            DB::raw('COALESCE(SUM(journal_items.credit), 0) as total_credit'),
        ])
        ->whereIn('chart_of_account_types.name', ['Assets', 'Liabilities', 'Equity'])
        ->groupBy(
            'chart_of_accounts.id',
            'chart_of_accounts.name',
            'chart_of_accounts.parent',
            'chart_of_accounts.sub_type',
            'chart_of_account_sub_types.name',
            'chart_of_account_types.name'
        )
        ->orderBy('chart_of_account_types.name')
        ->orderBy('chart_of_accounts.name')
        ->get();

    $accounts = $accounts->map(function ($acc) {
        if (($acc->account_type ?? '') === 'Assets') {
            $acc->balance = (float)$acc->total_debit - (float)$acc->total_credit;
        } else {
            $acc->balance = (float)$acc->total_credit - (float)$acc->total_debit;
        }
        return $acc;
    });

    if ($this->accountingMethod == 'cash') {
        $cashSubTypesNorm = array_map('strtolower', $cashSubTypes);

        $accounts = $accounts->filter(function ($acc) use ($cashSubTypesNorm) {
            $acctType = $acc->account_type ?? '';
            $subType = strtolower($acc->sub_type ?? '');
            $name = strtolower($acc->name ?? '');

            if ($acctType === 'Assets') {
                return in_array($subType, $cashSubTypesNorm);
            }

            if ($acctType === 'Liabilities') {
                if (stripos($name, 'payable') !== false || stripos($subType, 'payable') !== false) {
                    return false;
                }
                return true;
            }

            if ($acctType === 'Equity') {
                return true;
            }

            return false;
        })->values();
    }

    return $this->buildHierarchicalBalanceSheet($accounts, $allPaymentVouchers, $cashSubTypes);
}


    /**
     * Build hierarchical report rows (sections, subtypes, accounts, subtotals, totals)
     *
     * @param Collection $accounts  collection of accounts (with id, name, parent, sub_type, account_type, balance)
     * @param array $allPaymentVouchers (optional) used for cash-based P&L filtering
     * @param array $cashSubTypes (optional) canonical cash subtypes (lowercase)
     * @return Collection
     */
    private function buildHierarchicalBalanceSheet(Collection $accounts, array $allPaymentVouchers = [], array $cashSubTypes = ['bank','cash'])
    {
        $report = collect();

        // group by account_type
        $types = $accounts->groupBy('account_type');

        $totalAssets = $types->get('Assets', collect())->sum('balance');
        $totalLiabilities = $types->get('Liabilities', collect())->sum('balance');
        $totalEquity = $types->get('Equity', collect())->sum('balance');

        foreach (['Assets', 'Liabilities', 'Equity'] as $typeName) {
            $typeAccounts = $types->get($typeName, collect());

            // skip empty types except Equity (so Equity shows even if empty)
            if ($typeAccounts->isEmpty() && $typeName !== 'Equity') {
                continue;
            }

            // Section Header
            $report->push((object)[
                'name' => strtoupper($typeName),
                'depth' => 0,
                'is_section_header' => true,
            ]);

            // group by sub_type
            // dd($typeAccounts);
            $subTypes = $typeAccounts->groupBy('subtype_name');
            foreach ($subTypes as $subTypeName => $subTypeAccounts) {
                $subtypeId = 'subtype_' . str_replace(' ', '_', strtolower($subTypeName ?: 'uncategorized'));

                $hasAccounts = $subTypeAccounts->count() > 0;

                // SubType Header
                $report->push((object)[
                    'name' => $subTypeName ?: 'Uncategorized',
                    'depth' => 1,
                    'is_subtype_header' => true,
                    'subtype_id' => $subtypeId,
                    'has_children' => $hasAccounts,
                ]);

                // Build tree: pick roots (accounts whose parent is not within this subtype)
                $roots = $subTypeAccounts->filter(function ($acc) use ($subTypeAccounts) {
                    return empty($acc->parent) || !$subTypeAccounts->contains('id', $acc->parent);
                });

                foreach ($roots as $root) {
                    $accountRows = $this->buildAccountTree($root, $subTypeAccounts, 2, $subtypeId);
                    $report = $report->merge($accountRows);
                }

                // Subtype total
                $subTypeTotal = $subTypeAccounts->sum('balance');
                $report->push((object)[
                    'name' => "Total " . ($subTypeName ?: 'Uncategorized'),
                    'amount' => $subTypeTotal,
                    'depth' => 1,
                    'is_subtotal' => true,
                    'parent_subtype_id' => $subtypeId,
                ]);
            }

            // Type total
            $report->push((object)[
                'name' => "Total " . $typeName,
                'amount' => $typeAccounts->sum('balance'),
                'depth' => 0,
                'is_total' => true,
            ]);

            $report->push((object)['name' => '', 'amount' => null]); // spacing
        }

        // ---------------------------
        // Accumulated P&L (always show)
        // ---------------------------
        // Build P&L query and apply cash filter similarly
        $plQuery = DB::table('journal_items')
            ->join('journal_entries', 'journal_items.journal', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'journal_items.account', '=', 'chart_of_accounts.id')
            ->join('chart_of_account_sub_types', 'chart_of_accounts.sub_type', '=', 'chart_of_account_sub_types.id')
            ->join('chart_of_account_types', 'chart_of_account_sub_types.type', '=', 'chart_of_account_types.id')
            ->where("journal_entries.{$this->owner}", $this->companyId)
            ->where('journal_entries.date', '<=', $this->asOfDate->format('Y-m-d 23:59:59'))
            ->whereIn('chart_of_account_types.name', ['Income', 'Expenses', 'Costs of Goods Sold']);

        // apply cash filter: restrict to payment vouchers OR lines touching cash/bank accounts
        if ($this->accountingMethod == 'cash') {
            $cashSubTypesNorm = array_map('strtolower', $cashSubTypes);
            if (!empty($allPaymentVouchers)) {
                $plQuery->where(function ($q) use ($allPaymentVouchers, $cashSubTypesNorm) {
                    $q->whereIn('journal_entries.id', $allPaymentVouchers)
                        ->orWhereIn(DB::raw('LOWER(chart_of_accounts.sub_type)'), $cashSubTypesNorm);
                });
            } else {
                $plQuery->whereIn(DB::raw('LOWER(chart_of_accounts.sub_type)'), $cashSubTypesNorm);
            }
        }

        $plRows = $plQuery
            ->select('chart_of_account_types.name as type_name', 'journal_items.debit', 'journal_items.credit')
            ->get();

        // netProfit = sum(credit - debit) across Income/Expenses/COGS (this yields +ve for net profit)
        $netProfit = $plRows->sum(function ($r) {
            return (float) ($r->credit ?? 0) - (float) ($r->debit ?? 0);
        });

        // Add Accumulated (Loss) / Profit as a separate row (not nested under Equity)
        $report->push((object)[
            'name' => "Accumulated (Loss) / Profit",
            'amount' => $netProfit,
            'depth' => 1,
        ]);

        // include net profit into equity for final total calculation
        $totalEquity += $netProfit;

        $report->push((object)['name' => '', 'amount' => null]); // spacing

        // Final TOTAL LIABILITIES & EQUITY
        $report->push((object)[
            'name' => "TOTAL LIABILITIES & EQUITY",
            'amount' => $totalLiabilities + $totalEquity,
            'depth' => 0,
            'is_total' => true,
        ]);

        return $report;
    }

    /**
     * Recursive helper to build parent-child hierarchy for accounts
     *
     * @param object $account
     * @param Collection $allAccounts
     * @param int $depth
     * @param string|null $subtypeId
     * @return Collection
     */
    private function buildAccountTree($account, Collection $allAccounts, $depth = 2, $subtypeId = null)
    {
        $rows = collect();

        // Push parent account row
        $rows->push((object)[
            'name' => $account->name,
            'amount' => (float) ($account->balance ?? 0),
            'depth' => $depth,
            'parent_subtype_id' => $subtypeId,
        ]);

        // children (accounts where parent == this account id)
        $children = $allAccounts->filter(function ($a) use ($account) {
            return (string) ($a->parent ?? '') === (string) $account->id;
        });

        foreach ($children as $child) {
            $rows = $rows->merge($this->buildAccountTree($child, $allAccounts, $depth + 1, $subtypeId));
        }

        return $rows;
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('customer-balance-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
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
