<?php

namespace App\Http\Controllers\sync;
use App\Http\Controllers\Controller;
use App\DataTables\TrialBalanceDataTable;
use App\Models\ChartOfAccount;
use Carbon\Carbon;

class TrialBalanceController extends Controller
{
    public function __construct()
    {
        // parent::__construct();
        $this->pageTitle = 'Trial Balance';
        // $this->middleware(function ($request, $next) {
        //     abort_403(!in_array('accounting', $this->user->modules));
        //     return $next($request);
        // });
    }

    public function index(TrialBalanceDataTable $dataTable)
    {
        // $viewPermission = user()->permission('view_accounting');
        // abort_403(!in_array($viewPermission, ['all', 'added', 'owned', 'both']));

        $this->accounts = ChartOfAccount::where('company_id', 2)
            ->orderBy('type')
            ->orderBy('sub_type')
            ->get();

        return $dataTable->render('sync.trial-balance.index', $this->data);
    }
}