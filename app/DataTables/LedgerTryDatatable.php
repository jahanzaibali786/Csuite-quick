<?php

namespace App\DataTables;

use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use App\Models\JournalItem;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;

class LedgerTryDataTable extends DataTable
{
    public $accountId1;
    protected $companyId;
    protected $owner;
    protected $page = 1;
    protected $perPage = 100;
    protected $totalRecords = 0;
    protected $filteredRecords = 0;

    public function __construct()
    {
        $this->companyId = \Auth::user()->type === 'company' ? \Auth::user()->creatorId() : \Auth::user()->ownedId();
        $this->owner = \Auth::user()->type === 'company' ? 'created_by' : 'owned_by';
    }

    /** Call this from the controller */
    public function setAccountId($accountId1): self
    {
        $this->accountId1 = $accountId1 ? $accountId1 : 'all';
        return $this;
    }

    public function ajax(): JsonResponse
    {
        $this->page = (int) request()->get('page', 1);
        $this->perPage = (int) request()->get('per_page', 100);
        
        $query = $this->query();
        
        // Get total counts
        $this->totalRecords = $this->getTotalRecordsCount();
        $this->filteredRecords = $query->count();

        // Apply pagination
        $offset = ($this->page - 1) * $this->perPage;
        $entries = $query->offset($offset)->limit($this->perPage)->get();

        $data = $this->transformData($entries, $offset);

        // Correct has_more calculation
        $recordsLoaded = $this->page * $this->perPage;
        $hasMore = $recordsLoaded < $this->filteredRecords;

        // Add pagination metadata
        $data['draw'] = request()->get('draw', 0);
        $data['recordsTotal'] = $this->totalRecords;
        $data['recordsFiltered'] = $this->filteredRecords;
        $data['current_page'] = $this->page;
        $data['per_page'] = $this->perPage;
        $data['last_page'] = ceil($this->filteredRecords / $this->perPage);
        $data['has_more'] = $hasMore;
        $data['total_records'] = $this->filteredRecords;

        return new JsonResponse($data);
    }

    /**
     * Transform data to match OLD table behavior (period-only, no opening balances)
     */
    protected function transformData($entries, $offset = 0)
    {
        $data = collect();

        // If specific account is selected, or we have entries
        if ((!empty($entries) && $entries->count() > 0) || $this->accountId1 !== 'all') {
            // If specific account is selected
            if ($this->accountId1 !== 'all') {
                $account = ChartOfAccount::find($this->accountId1);

                if ($account) {
                    // Get entries for this account
                    $accountEntries = $entries->where('account', $this->accountId1);
                    
                    // Calculate period total (debit - credit) for this account
                    $periodTotal = $accountEntries->sum('debit') - $accountEntries->sum('credit');

                    // Add account header - MATCH OLD FORMAT
                    $data->push([
                        'id' => 'group-' . $account->id,
                        'date' => '',
                        'voucher_no' => '',
                        'account_name' => $account->name . ' (' . $accountEntries->count() . ')',
                        'debit' => '',
                        'credit' => '',
                        'memo' => '',
                        'running_balance' => $this->formatNumber($periodTotal),
                        'DT_RowClass' => 'account-group',
                        'DT_RowData' => ['account-id' => $account->id],
                        'is_header' => true
                    ]);

                    // Calculate running balance for period only (start from 0)
                    $runningBalance = 0;

                    // Add transaction rows
                    foreach ($accountEntries->sortBy(function ($item) {
                        return optional($item->journalEntry)->date ?? '';
                    }) as $entry) {
                        $runningBalance += ($entry->debit - $entry->credit);
                        $journalEntry = $entry->journalEntry;

                        $data->push([
                            'id' => $entry->id,
                            'date' => $journalEntry ? Carbon::parse($journalEntry->date)->format('m/d/Y') : '',
                            'voucher_no' => $journalEntry ? $journalEntry->reference : '',
                            'account_name' => $account->name,
                            'debit' => $this->formatNumber($entry->debit, true),
                            'credit' => $this->formatNumber($entry->credit, true),
                            'memo' => $entry->description,
                            'running_balance' => $this->formatNumber($runningBalance),
                            'DT_RowClass' => 'account-row',
                            'DT_RowData' => ['parent' => $account->id],
                            'is_transaction' => true
                        ]);
                    }
                }
            } else {
                // Handle "all accounts" case - MATCH OLD FORMAT
                $accountIds = $entries->pluck('account')->unique();
                $accounts = ChartOfAccount::whereIn('id', $accountIds->filter())->get()->keyBy('id');

                foreach ($accountIds as $accountId) {
                    if (!$accountId) continue;

                    $account = $accounts->get($accountId);
                    if (!$account) continue;

                    $accountEntries = $entries->where('account', $accountId);
                    
                    // Check if we need to show account header (first time this account appears)
                    $showHeader = $this->shouldShowAccountHeader($accountId, $offset);
                    
                    if ($showHeader) {
                        // Calculate period total for this account
                        $periodTotal = $accountEntries->sum('debit') - $accountEntries->sum('credit');

                        // Add account header - MATCH OLD FORMAT
                        $data->push([
                            'id' => 'group-' . $accountId,
                            'date' => '',
                            'voucher_no' => '',
                            'account_name' => $account->name . ' (' . $accountEntries->count() . ')',
                            'debit' => '',
                            'credit' => '',
                            'memo' => '',
                            'running_balance' => $this->formatNumber($periodTotal),
                            'DT_RowClass' => 'account-group',
                            'DT_RowData' => ['account-id' => $accountId],
                            'is_header' => true
                        ]);
                    }

                    // Calculate running balance for period only (start from 0)
                    $runningBalance = 0;

                    // Add detail rows
                    foreach ($accountEntries->sortBy(function ($item) {
                        return optional($item->journalEntry)->date ?? '';
                    }) as $entry) {
                        $runningBalance += ($entry->debit - $entry->credit);
                        $journalEntry = $entry->journalEntry;

                        $data->push([
                            'id' => $entry->id,
                            'date' => $journalEntry ? Carbon::parse($journalEntry->date)->format('m/d/Y') : '',
                            'voucher_no' => $journalEntry ? $journalEntry->reference : '',
                            'account_name' => $account->name,
                            'debit' => $this->formatNumber($entry->debit, true),
                            'credit' => $this->formatNumber($entry->credit, true),
                            'memo' => $entry->description,
                            'running_balance' => $this->formatNumber($runningBalance),
                            'DT_RowClass' => 'account-row',
                            'DT_RowData' => ['parent' => $accountId],
                            'is_transaction' => true
                        ]);
                    }
                }
            }
        } else {
            // No data case - only show on first page
            if ($this->page === 1) {
                $data->push([
                    'id' => 'no-data',
                    'date' => '',
                    'voucher_no' => '',
                    'account_name' => 'No transactions found for the selected period.',
                    'debit' => '',
                    'credit' => '',
                    'memo' => '',
                    'running_balance' => '',
                    'DT_RowClass' => 'no-data-row'
                ]);
            }
        }

        return ['data' => $data->toArray()];
    }

    /**
     * Format numbers to match old table (no thousands separator, 2 decimal places, empty if zero)
     */
    protected function formatNumber($value, $allowEmpty = false)
    {
        if ($value == 0 && $allowEmpty) {
            return '';
        }
        
        // Match old format: no thousands separator, always 2 decimal places
        return number_format($value, 2, '.', '');
    }

    public function query()
    {
        $query = JournalItem::query()
            ->with([
                'accounts:id,name,type,sub_type',
                'accounts.types:id,name',
                'accounts.subType:id,name',
                'journalEntry:id,date,reference,journal_id,owned_by'
            ])
            ->whereHas('journalEntry', function ($q) {
                $q->where("journal_entries.{$this->owner}", $this->companyId);
            });

        if (request()->filled('account_id') && request('account_id') !== 'all') {
            $query->where('account', request('account_id'));
        } elseif ($this->accountId1 !== 'all') {
            $query->where('account', $this->accountId1);
        }

        // Filter by date range - CRITICAL: This is what makes it period-only
        if (request()->filled('startDate') && request()->filled('endDate')) {
            try {
                $start = Carbon::parse(request('startDate'))->startOfDay();
                $end = Carbon::parse(request('endDate'))->endOfDay();

                $query->whereHas('journalEntry', fn($q) => $q->whereBetween('date', [$start, $end]));
            } catch (\Exception $e) {
                // Ignore invalid dates
            }
        }

        return $query->select([
            'journal_items.id as line_id',
            'journal_items.journal',
            'journal_items.account',
            'journal_items.debit',
            'journal_items.credit',
            'journal_items.description as memo',
        ])
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_items.account')
            ->join('chart_of_account_sub_types', 'chart_of_account_sub_types.id', '=', 'chart_of_accounts.sub_type')
            ->join('chart_of_account_types', 'chart_of_account_types.id', '=', 'chart_of_account_sub_types.type')
            ->where("journal_entries.{$this->owner}", $this->companyId)
            ->orderBy('chart_of_account_types.name', 'asc')
            ->orderBy('chart_of_account_sub_types.name', 'asc')
            ->orderBy('chart_of_accounts.name', 'asc')
            ->orderBy('journal_entries.date', 'asc');
    }

    /**
     * Get total records count for the query
     */
    protected function getTotalRecordsCount(): int
    {
        $query = JournalItem::query()
            ->whereHas('journalEntry', function ($q) {
                $q->where("journal_entries.{$this->owner}", $this->companyId);
            });

        if (request()->filled('account_id') && request('account_id') !== 'all') {
            $query->where('account', request('account_id'));
        } elseif ($this->accountId1 !== 'all') {
            $query->where('account', $this->accountId1);
        }

        if (request()->filled('startDate') && request()->filled('endDate')) {
            try {
                $start = Carbon::parse(request('startDate'))->startOfDay();
                $end = Carbon::parse(request('endDate'))->endOfDay();
                $query->whereHas('journalEntry', fn($q) => $q->whereBetween('date', [$start, $end]));
            } catch (\Exception $e) {
                // Ignore invalid dates
            }
        }

        return $query->count();
    }

    /**
     * Get record count for a specific account
     */
    protected function getAccountRecordCount($accountId): int
    {
        $query = JournalItem::query()
            ->where('account', $accountId)
            ->whereHas('journalEntry', function ($q) {
                $q->where("journal_entries.{$this->owner}", $this->companyId);
            });

        if (request()->filled('startDate') && request()->filled('endDate')) {
            try {
                $start = Carbon::parse(request('startDate'))->startOfDay();
                $end = Carbon::parse(request('endDate'))->endOfDay();
                $query->whereHas('journalEntry', fn($q) => $q->whereBetween('date', [$start, $end]));
            } catch (\Exception $e) {
                // Ignore invalid dates
            }
        }

        return $query->count();
    }

    /**
     * Check if we should show account header based on offset
     */
    protected function shouldShowAccountHeader($accountId, $offset): bool
    {
        if ($offset === 0) return true;

        // Count how many records this account has before current offset
        $query = JournalItem::query()
            ->where('account', $accountId)
            ->whereHas('journalEntry', function ($q) {
                $q->where("journal_entries.{$this->owner}", $this->companyId);
            });

        if (request()->filled('startDate') && request()->filled('endDate')) {
            try {
                $start = Carbon::parse(request('startDate'))->startOfDay();
                $end = Carbon::parse(request('endDate'))->endOfDay();
                $query->whereHas('journalEntry', fn($q) => $q->whereBetween('date', [$start, $end]));
            } catch (\Exception $e) {
                // Ignore invalid dates
            }
        }

        $recordsBefore = $query->count();
        
        // Show header if current offset is exactly where this account starts
        return $offset < $recordsBefore && ($offset + $this->perPage) >= $recordsBefore;
    }

    /**
     * Calculate running balance up to current offset for an account (period only)
     */
    protected function getRunningBalanceUpToOffset($accountId, $offset): float
    {
        $query = JournalItem::query()
            ->where('account', $accountId)
            ->whereHas('journalEntry', function ($q) {
                $q->where("journal_entries.{$this->owner}", $this->companyId);
            });

        if (request()->filled('startDate') && request()->filled('endDate')) {
            try {
                $start = Carbon::parse(request('startDate'))->startOfDay();
                $end = Carbon::parse(request('endDate'))->endOfDay();
                $query->whereHas('journalEntry', fn($q) => $q->whereBetween('date', [$start, $end]));
            } catch (\Exception $e) {
                // Ignore invalid dates
            }
        }

        $previousEntries = $query->limit($offset)->get();
        
        // Start from 0 for period-only calculation
        $runningBalance = 0;

        foreach ($previousEntries as $entry) {
            $runningBalance += ($entry->debit - $entry->credit);
        }

        return $runningBalance;
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('ledger-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->parameters([
                'processing' => true,
                'serverSide' => true,
                'paging' => false,
                'searching' => false,
                'ordering' => false,
                'info' => true,
                'scrollY' => '500px',
                'scrollCollapse' => true
            ]);
    }

    protected function getColumns()
    {
        return [
            Column::make('id')->title('ID')->visible(false),
            Column::make('date')->title('Date'),
            Column::make('voucher_no')->title('Reference'),
            Column::make('account_name')->title('Account'),
            Column::make('debit')->title('Debit'),
            Column::make('credit')->title('Credit'),
            Column::make('memo')->title('Memo/Description'),
            Column::make('running_balance')->title('Balance'),
        ];
    }
}