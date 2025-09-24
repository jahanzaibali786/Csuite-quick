<?php

namespace App\Http\Controllers\sync;
use App\DataTables\LedgerDataTable;
use App\Http\Controllers\Controller;
use App\DataTables\VouchersDataTable;
use App\Helper\Reply;
use App\Http\Requests\Voucher\StoreVoucherRequest;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VoucherController extends Controller
{
    public function __construct()
    {
        // parent::__construct();
        $this->pageTitle = 'Vouchers';
        // $this->middleware(function ($request, $next) {
        //     abort_403(!in_array('vouchers', $this->user->modules));
        //     return $next($request);
        // });
    }

    // ----------------- INDEX -----------------
    public function index(VouchersDataTable $dataTable)
    {
        // $viewPermission = user()->permission('view_vouchers');
        // abort_403(!in_array($viewPermission, ['all', 'added', 'owned', 'both']));

        if (request()->ajax()) {
            return $dataTable->ajax();
        }
        $account = ChartOfAccount::where('company_id', company()->id)->get();
        $this->accounts = $account;

        return $dataTable->render('vouchers.index', $this->data);
    }

    // ----------------- CREATE -----------------
    public function create()
    {
        // $this->addPermission = user()->permission('add_vouchers');
        // abort_403(!in_array($this->addPermission, ['all', 'added']));

        $this->pageTitle = __('modules.vouchers.addVoucher');
        $this->view = 'vouchers.ajax.create';
        $account = ChartOfAccount::where('company_id', company()->id)->get();
        $this->accounts = $account;
        if (request()->ajax()) {
            return $this->returnAjax($this->view, $this->data);
        }

        return view('vouchers.show', $this->data);
    }

    // ----------------- STORE -----------------
    public function store(StoreVoucherRequest $request)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            // 1. Create Journal Entry
            $journalEntry = JournalEntry::create([
                'company_id' => company()->id,
                'date' => date('Y-m-d', strtotime($data['date'])),
                'number' => $data['number'],
                'memo' => $data['memo'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'check_number' => $data['check_number'] ?? null,
                'bank_reference' => $data['bank_reference'] ?? null,
                'deposit_slip' => $data['deposit_slip'] ?? null,
                'cashier_info' => $data['cashier_info'] ?? null,
                'posted_at' => now(),
                'voucher_type' => $data['voucher_type'],
                'status' => 'posted',
                'created_by' => user()->id,
            ]);
            // dd($data);
            // 2. Create Journal Entry Lines
            foreach ($data['lines'] as $line) {
                $src = JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'company_id' => company()->id,
                    'chart_of_account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'memo' => $line['memo'] ?? null,
                ]);
                $sec = $src;
                $sec->source_line_type = 'App\Models\JournalEntryLine';
                $sec->source_line_id = $src->id;
                $sec->save();
            }

            $journalEntry->update([
                'source_id' => $journalEntry->id,
                'source_type' => 'App\Models\JournalEntry'
            ]);

            $this->voucher = $journalEntry;
        });

        return Reply::successWithData(__('messages.recordSaved'), ['redirectUrl' => route('vouchers.index')]);
    }

    // ----------------- SHOW -----------------
    public function show($id)
    {
        $this->voucher = JournalEntry::with(['lines'])->findOrFail($id);
        $this->pageTitle = __('modules.vouchers.viewVoucher');

        $this->view = 'vouchers.ajax.show';

        if (request()->ajax()) {
            return $this->returnAjax($this->view);
        }

        return view('vouchers.show', $this->data);
    }

    // ----------------- EDIT -----------------
    public function edit($id)
    {
        $this->voucher = JournalEntry::with(['lines'])->findOrFail($id);
        // $this->editPermission = user()->permission('edit_vouchers');
        // abort_403(!in_array($this->editPermission, ['all', 'added']));

        $this->pageTitle = __('modules.vouchers.updateVoucher');
        $this->view = 'vouchers.ajax.edit';
        $account = ChartOfAccount::where('company_id', company()->id)->get();
        $this->accounts = $account;
        if (request()->ajax()) {
            return $this->returnAjax($this->view);
        }

        return view('vouchers.show', $this->data);
    }

    // ----------------- UPDATE -----------------
    public function update(StoreVoucherRequest $request, $id)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $id) {
            // 1. Find Journal Entry
            $journalEntry = JournalEntry::where('company_id', company()->id)->find($id);

            if (!$journalEntry) {
                abort(404, 'Voucher not found.');
            }

            // 2. Update header fields
            $journalEntry->update([
                'date' => date('Y-m-d', strtotime($data['date'])),
                'number' => $data['number'],
                'memo' => $data['memo'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'check_number' => $data['check_number'] ?? null,
                'bank_reference' => $data['bank_reference'] ?? null,
                'deposit_slip' => $data['deposit_slip'] ?? null,
                'cashier_info' => $data['cashier_info'] ?? null,
                'voucher_type' => $data['voucher_type'],
                'updated_by' => user()->id,
            ]);

            // 3. Delete old lines
            $journalEntry->lines()->delete();

            // 4. Insert new lines
            foreach ($data['lines'] as $line) {
                $src = JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'company_id' => company()->id,
                    'chart_of_account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'memo' => $line['memo'] ?? null,
                ]);

                $src->source_line_type = JournalEntryLine::class;
                $src->source_line_id = $src->id;
                $src->save();
            }

            // 5. Update source reference
            $journalEntry->update([
                'source_id' => $journalEntry->id,
                'source_type' => JournalEntry::class
            ]);

            $this->voucher = $journalEntry;
        });

        return Reply::successWithData(__('messages.recordUpdated'), [
            'redirectUrl' => route('vouchers.index')
        ]);
    }


    // ----------------- DESTROY -----------------
    public function destroy($id)
    {
        // dd($id);
        $voucher = JournalEntry::findOrFail($id);
        // $this->deletePermission = user()->permission('delete_vouchers');
        // abort_403(!in_array($this->deletePermission, ['all', 'added']));
        $voucher->lines()->delete();
        $voucher->delete();

        return Reply::success(__('messages.deleteSuccess'));
    }

    // ----------------- QUICK ACTION -----------------
    public function applyQuickAction(Request $request)
    {
        switch ($request->action_type) {
            case 'delete':
                $this->deleteRecords($request);
                return Reply::success(__('messages.deleteSuccess'));
            case 'change-status':
                $this->changeBulkStatus($request);
                return Reply::success(__('messages.updateSuccess'));
            default:
                return Reply::error(__('messages.selectAction'));
        }
    }

    protected function deleteRecords($request)
    {
        JournalEntry::whereIn('id', explode(',', $request->row_ids))->delete();
    }

    protected function changeBulkStatus($request)
    {
        JournalEntry::whereIn('id', explode(',', $request->row_ids))
            ->update(['status' => $request->status]);
    }

    public function ledger(LedgerDataTable $dataTable, Request $request)
    {
        $this->pageTitle = 'General Ledger';
        $accountId = $request->get('account_id', 'all');
        $dataTable->setAccountId($accountId);

        if (request()->ajax()) {
            return $dataTable->ajax();
        }

        $accounts = ChartOfAccount::where('company_id', 2)->get();

        return $dataTable->render('sync.ledger.index', [
            'accounts' => $accounts,
            'accountId' => $accountId,
            'pageTitle' => $this->pageTitle
        ]);
    }

    public function profitLoss(\App\DataTables\ProfitLossDataTable $dataTable, Request $request)
    {
        $this->pageTitle = 'Profit & Loss Statement';

        if ($request->ajax()) {
            return $dataTable->ajax();
        }

        return $dataTable->render('sync.profit_loss.index', $this->data);
    }

    public function profitLossDetail(\App\DataTables\ProfitLossDetailDataTable $dataTable, Request $request)
    {
        $this->pageTitle = 'Profit and Loss - Detail';

        if ($request->ajax()) {
            return $dataTable->ajax();
        }

        return $dataTable->render('accounting.profit-loss-detail.index', $this->data);
    }

    public function balanceSheet(\App\DataTables\BalanceSheetDataTable $dataTable, Request $request)
    {
        $this->pageTitle = 'Balance Sheet';

        if ($request->ajax()) {
            return $dataTable->ajax();
        }

        return $dataTable->render('accounting.balance-sheet.index', $this->data);
    }

    public function balanceSheetStandard(\App\DataTables\BalanceSheetStandardDataTable $dataTable, Request $request)
    {
        $this->pageTitle = 'Balance Sheet - Standard';

        if ($request->ajax()) {
            return $dataTable->ajax();
        }

        return $dataTable->render('accounting.balance-sheet-standard.index', $this->data);
    }

    public function balanceSheetDetail(\App\DataTables\BalanceSheetDetailDataTable $dataTable, Request $request)
    {
        $this->pageTitle = 'Balance Sheet - Detail';

        if ($request->ajax()) {
            return $dataTable->ajax();
        }

        return $dataTable->render('accounting.balance-sheet-detail.index', $this->data);
    }

    public function cashFlow(\App\DataTables\CashFlowDataTable $dataTable, Request $request)
    {
        $this->pageTitle = 'Cash Flow Statement';

        if ($request->ajax()) {
            return $dataTable->ajax();
        }

        return $dataTable->render('accounting.cash-flow.index', $this->data);
    }

    public function generalJournal(\App\DataTables\GeneralJournalDataTable $dataTable, Request $request)
    {
        $this->pageTitle = 'General Journal';

        if ($request->ajax()) {
            return $dataTable->ajax();
        }

        return $dataTable->render('accounting.general-journal.index', $this->data);
    }

    public function exportGeneralJournal(Request $request)
    {
        $startDate = $request->startDate ? Carbon::parse($request->startDate) : Carbon::now()->startOfMonth();
        $endDate = $request->endDate ? Carbon::parse($request->endDate) : Carbon::now();
        $status = $request->status ?? 'all';

        $journalEntries = JournalEntry::with(['lines.chartOfAccount'])
            ->where('company_id', company()->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->when($status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('accounting.general-journal.pdf', [
            'journalEntries' => $journalEntries,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'company' => company()
        ]);

        return $pdf->download('general-journal-' . $startDate->format('Y-m-d') . '-to-' . $endDate->format('Y-m-d') . '.pdf');
    }

    public function exportBalanceSheetStandard(Request $request)
    {
        $asOfDate = $request->asOfDate ? Carbon::parse($request->asOfDate) : Carbon::now();

        $accounts = ChartOfAccount::where('chart_of_accounts.company_id', company()->id)
            ->leftJoin('chart_of_account_types', 'chart_of_accounts.chart_of_account_type_id', '=', 'chart_of_account_types.id')
            ->leftJoin('journal_entry_lines', 'chart_of_accounts.id', '=', 'journal_entry_lines.chart_of_account_id')
            ->leftJoin('journal_entries', function ($join) use ($asOfDate) {
                $join->on('journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                    ->where('journal_entries.company_id', company()->id)
                    ->where('journal_entries.date', '<=', $asOfDate)
                    ->where('journal_entries.status', 'approved');
            })
            ->select([
                'chart_of_accounts.id',
                'chart_of_accounts.name',
                'chart_of_accounts.code',
                'chart_of_account_types.name as account_type',
                DB::raw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit'),
            ])
            ->whereIn('chart_of_account_types.name', ['Asset', 'Liability', 'Equity'])
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.name', 'chart_of_accounts.code', 'chart_of_account_types.name')
            ->orderBy('chart_of_account_types.name')
            ->orderBy('chart_of_accounts.name')
            ->get();

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('accounting.balance-sheet-standard.pdf', [
            'accounts' => $accounts,
            'asOfDate' => $asOfDate,
            'company' => company()
        ]);

        return $pdf->download('balance-sheet-standard-' . $asOfDate->format('Y-m-d') . '.pdf');
    }

    public function exportBalanceSheetDetail(Request $request)
    {
        $asOfDate = $request->asOfDate ? Carbon::parse($request->asOfDate) : Carbon::now();

        $accounts = ChartOfAccount::where('chart_of_accounts.company_id', company()->id)
            ->leftJoin('chart_of_account_types', 'chart_of_accounts.chart_of_account_type_id', '=', 'chart_of_account_types.id')
            ->leftJoin('journal_entry_lines', 'chart_of_accounts.id', '=', 'journal_entry_lines.chart_of_account_id')
            ->leftJoin('journal_entries', function ($join) use ($asOfDate) {
                $join->on('journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                    ->where('journal_entries.company_id', company()->id)
                    ->where('journal_entries.date', '<=', $asOfDate)
                    ->where('journal_entries.status', 'draft');
            })
            ->select([
                'chart_of_accounts.id',
                'chart_of_accounts.name',
                'chart_of_accounts.code',
                'chart_of_account_types.name as account_type',
                DB::raw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit'),
            ])
            ->whereIn('chart_of_account_types.name', ['Asset', 'Liability', 'Equity'])
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.name', 'chart_of_accounts.code', 'chart_of_account_types.name')
            ->orderBy('chart_of_account_types.name')
            ->orderBy('chart_of_accounts.name')
            ->get();

        // Get transaction details
        $transactions = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_entry_lines.chart_of_account_id', $accounts->pluck('id'))
            ->where('journal_entries.company_id', company()->id)
            ->where('journal_entries.date', '<=', $asOfDate)
            ->where('journal_entries.status', 'draft')
            ->select([
                'journal_entry_lines.chart_of_account_id',
                'journal_entries.date',
                'journal_entries.description',
                'journal_entries.reference',
                'journal_entry_lines.debit',
                'journal_entry_lines.credit',
                'journal_entry_lines.memo'
            ])
            ->orderBy('journal_entries.date')
            ->get()
            ->groupBy('chart_of_account_id');

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('accounting.balance-sheet-detail.pdf', [
            'accounts' => $accounts,
            'transactions' => $transactions,
            'asOfDate' => $asOfDate,
            'company' => company()
        ]);

        return $pdf->download('balance-sheet-detail-' . $asOfDate->format('Y-m-d') . '.pdf');
    }

    public function exportProfitLossDetail(Request $request)
    {
        $fromDate = $request->fromDate ? Carbon::parse($request->fromDate) : Carbon::now()->startOfMonth();
        $toDate = $request->toDate ? Carbon::parse($request->toDate) : Carbon::now();

        $accounts = ChartOfAccount::where('chart_of_accounts.company_id', company()->id)
            ->leftJoin('chart_of_account_types', 'chart_of_accounts.chart_of_account_type_id', '=', 'chart_of_account_types.id')
            ->leftJoin('journal_entry_lines', 'chart_of_accounts.id', '=', 'journal_entry_lines.chart_of_account_id')
            ->leftJoin('journal_entries', function ($join) use ($fromDate, $toDate) {
                $join->on('journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                    ->where('journal_entries.company_id', company()->id)
                    ->whereBetween('journal_entries.date', [$fromDate, $toDate])
                    ->where('journal_entries.status', 'draft');
            })
            ->select([
                'chart_of_accounts.id',
                'chart_of_accounts.name',
                'chart_of_accounts.code',
                'chart_of_account_types.name as type_name',
                DB::raw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit'),
            ])
            ->whereIn('chart_of_account_types.name', ['Income', 'Expense', 'Cost of Sales'])
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.name', 'chart_of_accounts.code', 'chart_of_account_types.name')
            ->orderBy('chart_of_account_types.name')
            ->orderBy('chart_of_accounts.name')
            ->get();

        // Get transaction details
        $transactions = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_entry_lines.chart_of_account_id', $accounts->pluck('id'))
            ->where('journal_entries.company_id', company()->id)
            ->whereBetween('journal_entries.date', [$fromDate, $toDate])
            ->where('journal_entries.status', 'draft')
            ->select([
                'journal_entry_lines.chart_of_account_id',
                'journal_entries.date',
                'journal_entries.description',
                'journal_entries.reference',
                'journal_entry_lines.debit',
                'journal_entry_lines.credit',
                'journal_entry_lines.memo'
            ])
            ->orderBy('journal_entries.date')
            ->get()
            ->groupBy('chart_of_account_id');

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('accounting.profit-loss-detail.pdf', [
            'accounts' => $accounts,
            'transactions' => $transactions,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'company' => company()
        ]);

        return $pdf->download('profit-loss-detail-' . $fromDate->format('Y-m-d') . '-to-' . $toDate->format('Y-m-d') . '.pdf');
    }
}


