<?php

namespace App\DataTables;

use App\Models\JournalItem;
use App\Models\ChartOfAccount;
use Illuminate\Support\Carbon;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class GeneralLedgerListDataTable extends DataTable
{
    public function dataTable($query)
    {
        $runningBalance = 0;
        $accountBalances = [];

        return datatables()
            ->eloquent($query)
            ->addColumn('distribution_account', function($row) {
                $account = $row->accounts;
                if (!$account) return '-';
                
                // Check if this is a parent account
                $isParent = ChartOfAccount::where('parent', $account->id)->exists();
                $indent = $account->parent != 0 ? '&nbsp;&nbsp;&nbsp;&nbsp;' : '';
                
                return $indent . $account->name . ($isParent ? ' (' . $this->getAccountEntryCount($account->id) . ')' : '');
            })
            ->addColumn('transaction_date', function($row) {
                return $row->journalEntry ? Carbon::parse($row->journalEntry->date)->format('m/d/Y') : '';
            })
            ->addColumn('memo_description', function($row) {
                return $row->description ?? '-';
            })
            ->addColumn('name', function($row) {
                return $row->journalEntry->user->name ?? '-';
            })
            ->addColumn('transaction_id', function($row) {
                return $row->journalEntry->id ?? '-';
            })
            ->addColumn('num', function($row) {
                return $row->journalEntry->reference ?? '-';
            })
            ->addColumn('balance', function($row) use (&$runningBalance, &$accountBalances) {
                $accountId = $row->account;
                
                if (!isset($accountBalances[$accountId])) {
                    $accountBalances[$accountId] = 0;
                }
                
                $accountBalances[$accountId] += ($row->debit - $row->credit);
                return number_format($accountBalances[$accountId], 2);
            })
            ->addColumn('account_type', function($row) {
                return $row->accounts->account_type ?? '';
            })
            ->addColumn('debit', function($row) {
                return number_format($row->debit, 2);
            })
            ->addColumn('credit', function($row) {
                return number_format($row->credit, 2);
            })
            ->rawColumns(['distribution_account', 'memo_description']);
    }

    private function getAccountEntryCount($accountId)
    {
        return JournalItem::where('account', $accountId)->count();
    }

    public function query(JournalItem $model)
    {
        $query = $model->with(['accounts', 'journalEntry']);

        if (request()->filled('start_date') && request()->filled('end_date')) {
            $query->whereHas('journalEntry', function($q) {
                $q->whereBetween('date', [request('start_date'), request('end_date')]);
            });
        }

        if (request()->filled('account') && request('account') != '') {
            $query->where('account', request('account'));
        }

        if (request()->filled('accounting_method')) {
            // Apply accounting method logic here if needed
        }

        return $query->orderBy('id', 'desc');
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('ledger-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('rt') // Simplified DOM to match the design
            ->orderBy(1, 'desc') // Order by transaction date
            ->parameters([
                'responsive' => true,
                'autoWidth'  => false,
                'paging'     => false,
                'searching'  => false,
                'info'       => false,
                'ordering'   => false,
            ]);
    }

    protected function getColumns()
    {
        return [
            Column::make('distribution_account')->title('Distribution Account')->width('200px'),
            Column::make('transaction_date')->title('Transaction Date')->width('120px'),
            Column::make('memo_description')->title('Memo/Description')->width('200px'),
            Column::make('name')->title('Name')->width('150px'),
            Column::make('transaction_id')->title('Transaction ID')->width('100px'),
            Column::make('num')->title('Num')->width('80px'),
            Column::make('balance')->title('Balance')->width('120px')->addClass('text-right'),
            Column::make('debit')->title('Debit')->width('120px')->addClass('text-right'),
            Column::make('credit')->title('Credit')->width('120px')->addClass('text-right'),
        ];
    }

    protected function filename(): string
    {
        return 'GeneralLedger_' . date('YmdHis');
    }
}
