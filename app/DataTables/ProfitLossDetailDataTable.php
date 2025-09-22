<?php

namespace App\DataTables;

use App\Models\ChartOfAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ProfitLossDetailDataTable extends DataTable
{
    protected $fromDate;
    protected $toDate;

    public function __construct()
    {
        parent::__construct();

        $this->fromDate = request('fromDate')
            ? Carbon::parse(request('fromDate'))->startOfDay()
            : Carbon::now()->startOfMonth();

        $this->toDate = request('toDate')
            ? Carbon::parse(request('toDate'))->endOfDay()
            : Carbon::now()->endOfDay();
    }

    /**
     * Build DataTable response.
     */
    public function dataTable($query)
    {
        return datatables()
            ->collection($query)
            ->editColumn('name', function ($row) {
                $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $row->depth ?? 0);
                $label = $row->name ?? '';

                if (!empty($row->is_total)) {
                    return "<strong>{$indent}{$label}</strong>";
                }

                return $indent . $label;
            })
            ->editColumn('amount', fn($row) => number_format($row->amount ?? 0, 2))
            ->editColumn('balance', fn($row) => number_format($row->balance ?? 0, 2))
            ->rawColumns(['name']);
    }

    /**
     * Main query and report builder.
     */
    public function query()
    {
        $accounts = ChartOfAccount::where('chart_of_accounts.company_id', company()->id)
            ->leftJoin('chart_of_account_sub_types', 'chart_of_accounts.chart_of_account_sub_type_id', '=', 'chart_of_account_sub_types.id')
            ->leftJoin('chart_of_account_types', 'chart_of_account_sub_types.chart_of_account_type_id', '=', 'chart_of_account_types.id')
            ->leftJoin('journal_entry_lines', 'chart_of_accounts.id', '=', 'journal_entry_lines.chart_of_account_id')
            ->leftJoin('journal_entries', function ($join) {
                $join->on('journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                    ->where('journal_entries.company_id', company()->id)
                    ->whereBetween('journal_entries.date', [$this->fromDate, $this->toDate])
                    ->where('journal_entries.status', '!=', 'draft'); // ✅ ignore draft
            })
            ->whereIn('chart_of_account_types.name', ['Income', 'Expense', 'Cost of Sales', 'Other Income', 'Other Expense'])
            ->select([
                'chart_of_accounts.id',
                'chart_of_accounts.name',
                'chart_of_accounts.code',
                'chart_of_accounts.parent_id',
                'chart_of_account_sub_types.name as sub_type_name',
                'chart_of_account_types.name as type_name',
                DB::raw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit'),
            ])
            ->groupBy(
                'chart_of_accounts.id',
                'chart_of_accounts.name',
                'chart_of_accounts.code',
                'chart_of_accounts.parent_id',
                'chart_of_account_sub_types.name',
                'chart_of_account_types.name'
            )
            ->get();

        // Calculate balances (Income = credit - debit, Expenses = debit - credit)
        $accounts = $accounts->map(function ($acc) {
            $acc->balance = in_array($acc->type_name, ['Income', 'Other Income'])
                ? $acc->total_credit - $acc->total_debit
                : $acc->total_debit - $acc->total_credit;
            return $acc;
        });

        // Get all transactions for these accounts
        $accountTransactions = $this->getAccountTransactions($accounts->pluck('id')->toArray());

        // 🔑 Build the report
        $report = collect();

        foreach ($accounts->whereNull('parent_id') as $account) {
            $report = $report->merge(
                $this->buildAccountTreeWithTransactions($account, $accounts, $accountTransactions, 0)
            );
        }

        // 🔑 Summary rows
        $totalIncome   = $accounts->where('type_name', 'Income')->sum('balance');
        $totalCOGS     = $accounts->where('type_name', 'Cost of Sales')->sum('balance');
        $grossProfit   = $totalIncome - $totalCOGS;
        $totalExpense  = $accounts->where('type_name', 'Expense')->sum('balance');
        $netOrdinary   = $grossProfit - $totalExpense;
        $otherIncome   = $accounts->where('type_name', 'Other Income')->sum('balance');
        $otherExpense  = $accounts->where('type_name', 'Other Expense')->sum('balance');
        $netOther      = $otherIncome - $otherExpense;
        $netIncome     = $netOrdinary + $netOther;

        $summaryRows = [
            (object)['name' => 'Net Ordinary Income', 'amount' => $netOrdinary, 'balance' => $netOrdinary, 'depth' => 0, 'is_total' => true],
            (object)['name' => 'Net Other Income', 'amount' => $netOther, 'balance' => $netOther, 'depth' => 0, 'is_total' => true],
            (object)['name' => 'Net Income', 'amount' => $netIncome, 'balance' => $netIncome, 'depth' => 0, 'is_total' => true],
        ];

        return $report->merge($summaryRows);
    }

    /**
     * Get transaction details
     */
    private function getAccountTransactions($accountIds)
    {
        return DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'journal_entry_lines.chart_of_account_id', '=', 'chart_of_accounts.id')
            ->leftJoin('chart_of_accounts as split_accounts', 'journal_entry_lines.chart_of_account_id', '=', 'split_accounts.id')
            ->whereIn('journal_entry_lines.chart_of_account_id', $accountIds)
            ->where('journal_entries.company_id', company()->id)
            ->whereBetween('journal_entries.date', [$this->fromDate, $this->toDate])
            ->where('journal_entries.status', '!=', 'draft')
            ->select([
                'journal_entry_lines.chart_of_account_id',
                'journal_entries.date',
                'journal_entries.voucher_type as transaction_type',
                'journal_entries.number as num',
                'journal_entries.memo',
                'journal_entry_lines.debit',
                'journal_entry_lines.credit',
                DB::raw('CONCAT(split_accounts.code, " - ", split_accounts.name) as split_account'),
            ])
            ->orderBy('journal_entries.date')
            ->get()
            ->groupBy('chart_of_account_id');
    }

    /**
     * Recursively build account tree
     */
    private function buildAccountTreeWithTransactions($account, $allAccounts, $accountTransactions, $depth)
    {
        $rows = collect();
        $hasTransactions = isset($accountTransactions[$account->id]) && $accountTransactions[$account->id]->count() > 0;

        // Account header
        $rows->push((object)[
            'name' => $account->name,
            'amount' => $account->balance ?? 0,
            'balance' => $account->balance ?? 0,
            'depth' => $depth,
            'is_transaction' => false,
            'is_total' => false,
        ]);

        // Transactions
        if ($hasTransactions) {
            foreach ($accountTransactions[$account->id] as $txn) {
                $rows->push((object)[
                    'transaction_date' => $txn->date,
                    'transaction_type' => $txn->transaction_type,
                    'num' => $txn->num,
                    'memo' => $txn->memo,
                    'split_account' => $txn->split_account,
                    'debit' => $txn->debit ?? 0,
                    'credit' => $txn->credit ?? 0,
                    'amount' => ($txn->credit ?? 0) - ($txn->debit ?? 0),
                    'balance' => 0,
                    'depth' => $depth + 1,
                    'is_transaction' => true,
                    'is_total' => false,
                ]);
            }
        }

        // Children
        foreach ($allAccounts->where('parent_id', $account->id) as $child) {
            $rows = $rows->merge($this->buildAccountTreeWithTransactions($child, $allAccounts, $accountTransactions, $depth + 1));
        }

        // Total for account
        $rows->push((object)[
            'name' => "Total for {$account->name}",
            'amount' => $account->balance ?? 0,
            'balance' => $account->balance ?? 0,
            'depth' => $depth,
            'is_transaction' => false,
            'is_total' => true,
        ]);

        return $rows;
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('profit-loss-detail-table')
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
            Column::make('transaction_date')->title('Transaction Date'),
            Column::make('transaction_type')->title('Transaction Type'),
            Column::make('num')->title('Num'),
            Column::make('name')->title('Name'),
            Column::make('memo')->title('Memo/Description'),
            Column::make('split_account')->title('Split Account'),
            Column::make('amount')->title('Amount')->addClass('text-right'),
            Column::make('balance')->title('Balance')->addClass('text-right'),
        ];
    }
}
