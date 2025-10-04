<?php

namespace App\DataTables;

use App\Models\Proposal;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Carbon\Carbon;

class ProposalsByCustomerDataTable extends DataTable
{
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->addColumn('date', function ($proposal) {
                return $proposal->issue_date ? Carbon::parse($proposal->issue_date)->format('m/d/Y') : '-';
            })
            ->addColumn('num', function ($proposal) {
                return 'EST-' . str_pad($proposal->proposal_id, 4, '0', STR_PAD_LEFT);
            })
            ->addColumn('estimate_status', function ($proposal) {
                $statuses = Proposal::$statues;
                return isset($statuses[$proposal->status]) ? __($statuses[$proposal->status]) : 'Unknown';
            })
            ->addColumn('accepted_on', function ($proposal) {
                // Only show accepted date if status is Accepted (2)
                if ($proposal->status == 2) { // Accepted
                    return $proposal->issue_date ? Carbon::parse($proposal->issue_date)->format('m/d/Y') : '-';
                }
                return '-';
            })
            ->addColumn('accepted_by', function ($proposal) {
                // Only show accepted by if status is Accepted (2)
                if ($proposal->status == 2) { // Accepted
                    return $proposal->customer ? $proposal->customer->name : 'Customer';
                }
                return '-';
            })
            ->addColumn('expiration_date', function ($proposal) {
                // Calculate expiration date as 30 days from issue date
                if ($proposal->issue_date) {
                    $expDate = Carbon::parse($proposal->issue_date)->addDays(30);
                    return $expDate->format('m/d/Y');
                }
                return '-';
            })
            ->addColumn('invoice_number', function ($proposal) {
                // Check if proposal is converted to invoice
                if ($proposal->is_convert && $proposal->converted_invoice_id) {
                    return 'INV-' . str_pad($proposal->converted_invoice_id, 4, '0', STR_PAD_LEFT);
                }
                return '-';
            })
            ->addColumn('amount', function ($proposal) {
                $totalAmount = $proposal->getTotal();
                return Auth::user()->priceFormat($totalAmount);
            })
            ->addColumn('customer_name', function ($proposal) {
                return $proposal->customer ? $proposal->customer->name : 'Unknown Customer';
            })
            ->rawColumns(['amount']);
    }

    public function query(Proposal $model)
    {
        $user = Auth::user();
        $ownerId = $user->type === 'company' ? $user->creatorId() : $user->ownedId();
        $column = ($user->type == 'company') ? 'created_by' : 'owned_by';

        // Build query to get proposals with customer information
        $query = $model->newQuery()
            ->select([
                'proposals.id as id',
                'proposals.proposal_id as proposal_id',
                'proposals.customer_id as customer_id',
                'proposals.issue_date as issue_date',
                'proposals.status as status',
                'proposals.converted_invoice_id as converted_invoice_id',
                'proposals.is_convert as is_convert',
                'proposals.created_by as created_by',
                'customers.name as customer_name'
            ])
            // Join with customers
            ->leftJoin('customers', 'proposals.customer_id', '=', 'customers.id')
            // Filter by ownership
            ->where('proposals.' . $column, $ownerId)
            // Only show proposals that have a customer
            ->whereNotNull('customers.name');

        // Apply filters from request
        if (request()->filled('customer_name') && request('customer_name') !== '') {
            $customerName = request('customer_name');
            $query->where('customers.name', 'LIKE', "%{$customerName}%");
        }

        if (request()->filled('status') && request('status') !== '') {
            $status = request('status');
            $query->where('proposals.status', $status);
        }

        if (request()->filled('start_date') && request()->filled('end_date')) {
            $startDate = request('start_date');
            $endDate = request('end_date');
            $query->whereBetween('proposals.issue_date', [$startDate, $endDate]);
        }

        return $query->orderBy('proposals.issue_date', 'desc')
                    ->orderBy('customers.name', 'asc');
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('customer-balance-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('rt')
            ->parameters([
                'responsive' => true,
                'autoWidth' => false,
                'paging' => false,
                'searching' => false,
                'info' => false,
                'ordering' => false,
                'colReorder' => true,
                'fixedHeader' => true,
                'scrollY' => '420px',
                'scrollX' => true,
                'scrollCollapse' => true,
                'rowGroup' => [
                    'dataSrc' => 'customer_name'
                ]
            ]);
    }

    protected function getColumns()
    {
        return [
            Column::make('date')->title(__('Date'))->addClass('text-center'),
            Column::make('num')->title(__('Num')),
            Column::make('estimate_status')->title(__('Estimate Status')),
            Column::make('accepted_on')->title(__('Accepted On'))->addClass('text-center'),
            Column::make('accepted_by')->title(__('Accepted By')),
            Column::make('expiration_date')->title(__('Expiration Date'))->addClass('text-center'),
            Column::make('invoice_number')->title(__('Invoice Number')),
            Column::make('amount')->title(__('Amount'))->addClass('text-right'),
            Column::make('customer_name')->title(__('Customer'))->visible(false), // Hidden but used for grouping
        ];
    }

    protected function filename(): string
    {
        return 'ProposalsByCustomer_' . date('YmdHis');
    }
}