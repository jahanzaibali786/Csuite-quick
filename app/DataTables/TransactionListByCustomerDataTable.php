<?php

namespace App\DataTables;

use App\Models\TransactionLines;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Vender;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Carbon\Carbon;

class TransactionListByCustomerDataTable extends DataTable
{
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->addColumn('date', function ($transaction) {
                return $transaction->date ? Carbon::parse($transaction->date)->format('m/d/Y') : '-';
            })
            ->addColumn('transaction_type', function ($transaction) {
                $type = $transaction->reference ?? 'Transaction';
                
                // Map references to display names
                $typeMap = [
                    'Invoice' => 'Invoice',
                    'Invoice Payment' => 'Payment',
                    'Invoice Account' => 'Invoice',
                    'Bill' => 'Bill',
                    'Bill Payment' => 'Payment',
                    'Bill Account' => 'Bill',
                    'Revenue' => 'Deposit',
                    'Payment' => 'Expense',
                    'Expense' => 'Expense',
                    'Expense Payment' => 'Expense Payment',
                    'Expense Account' => 'Expense',
                ];
                
                return $typeMap[$type] ?? ucwords(str_replace('_', ' ', $type));
            })
            ->addColumn('num', function ($transaction) {
                // Generate transaction number based on reference type and ID
                $prefix = '';
                switch ($transaction->reference) {
                    case 'Invoice':
                    case 'Invoice Payment':
                    case 'Invoice Account':
                        $prefix = 'INV';
                        break;
                    case 'Bill':
                    case 'Bill Payment':
                    case 'Bill Account':
                        $prefix = 'BILL';
                        break;
                    case 'Revenue':
                        $prefix = 'REV';
                        break;
                    case 'Payment':
                    case 'Expense':
                    case 'Expense Payment':
                    case 'Expense Account':
                        $prefix = 'EXP';
                        break;
                    default:
                        $prefix = 'TXN';
                }
                
                return $prefix . '-' . str_pad($transaction->reference_id, 4, '0', STR_PAD_LEFT);
            })
            ->addColumn('posting', function ($transaction) {
                // For now, all transactions are posted since they exist in transaction_lines
                return 'Y';
            })
            ->addColumn('memo_description', function ($transaction) {
                // Get description from the transaction or related records
                $description = '';
                
                if (!empty($transaction->description)) {
                    $description = $transaction->description;
                } elseif (!empty($transaction->memo)) {
                    $description = $transaction->memo;
                } else {
                    // Generate description based on transaction type
                    switch ($transaction->reference) {
                        case 'Invoice':
                            $description = 'Invoice to ' . ($transaction->customer_name ?? 'Customer');
                            break;
                        case 'Invoice Payment':
                            $description = 'Payment for Invoice from ' . ($transaction->customer_name ?? 'Customer');
                            break;
                        case 'Bill':
                            $description = 'Bill from ' . ($transaction->vendor_name ?? 'Vendor');
                            break;
                        case 'Bill Payment':
                            $description = 'Payment for Bill to ' . ($transaction->vendor_name ?? 'Vendor');
                            break;
                        case 'Revenue':
                            $description = 'Revenue from ' . ($transaction->customer_name ?? 'Customer');
                            break;
                        case 'Payment':
                        case 'Expense':
                            $description = 'Expense payment to ' . ($transaction->vendor_name ?? 'Vendor');
                            break;
                        default:
                            $description = ucwords($transaction->reference ?? 'Transaction');
                    }
                }
                
                return $description ?: '-';
            })
            ->addColumn('account_full_name', function ($transaction) {
                return $transaction->account_name ?? '-';
            })
            ->addColumn('amount', function ($transaction) {
                $amount = 0;
                
                // For revenue/income transactions (credit), show positive
                if ($transaction->credit > 0) {
                    $amount = $transaction->credit;
                } 
                // For expense transactions (debit), show negative
                elseif ($transaction->debit > 0) {
                    $amount = -$transaction->debit;
                }
                
                return Auth::user()->priceFormat($amount);
            })
            ->addColumn('customer_name', function ($transaction) {
                return $transaction->customer_name ?? 'Unknown Customer';
            })
            ->rawColumns(['amount']);
    }

    public function query(TransactionLines $model)
    {
        $user = Auth::user();
        $ownerId = $user->type === 'company' ? $user->creatorId() : $user->ownedId();

        // Build complex query to get transactions with customer information
        $query = $model->newQuery()
            ->select([
                'transaction_lines.id',
                'transaction_lines.date',
                'transaction_lines.reference',
                'transaction_lines.reference_id',
                'transaction_lines.reference_sub_id',
                'transaction_lines.debit',
                'transaction_lines.credit',
                'transaction_lines.created_by',
                'chart_of_accounts.name as account_name',
                'chart_of_accounts.code as account_code',
                DB::raw('COALESCE(customers.name, revenues_customers.name, payments_venders.name, bills_venders.name) as customer_name'),
                DB::raw('COALESCE(venders.name, payments_venders.name, bills_venders.name) as vendor_name'),
                DB::raw('COALESCE(invoices.customer_id, revenues.customer_id, bills.vender_id, payments.vender_id) as party_id')
            ])
            // Join with chart of accounts to get account name
            ->leftJoin('chart_of_accounts', 'transaction_lines.account_id', '=', 'chart_of_accounts.id')
            
            // Join with invoices and their customers
            ->leftJoin('invoices', function ($join) {
                $join->on('transaction_lines.reference_id', '=', 'invoices.id')
                    ->whereIn('transaction_lines.reference', ['Invoice', 'Invoice Payment', 'Invoice Account']);
            })
            ->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id')
            
            // Join with bills and their vendors
            ->leftJoin('bills', function ($join) {
                $join->on('transaction_lines.reference_id', '=', 'bills.id')
                    ->whereIn('transaction_lines.reference', ['Bill', 'Bill Payment', 'Bill Account']);
            })
            ->leftJoin('venders', 'bills.vender_id', '=', 'venders.id')
            
            // Join with revenues and their customers
            ->leftJoin('revenues', function ($join) {
                $join->on('transaction_lines.reference_id', '=', 'revenues.id')
                    ->where('transaction_lines.reference', '=', 'Revenue');
            })
            ->leftJoin('customers as revenues_customers', 'revenues.customer_id', '=', 'revenues_customers.id')
            
            // Join with payments (expenses) and their vendors
            ->leftJoin('payments', function ($join) {
                $join->on('transaction_lines.reference_id', '=', 'payments.id')
                    ->whereIn('transaction_lines.reference', ['Payment', 'Expense', 'Expense Payment', 'Expense Account']);
            })
            ->leftJoin('venders as payments_venders', 'payments.vender_id', '=', 'payments_venders.id')
            ->leftJoin('venders as bills_venders', 'bills.vender_id', '=', 'bills_venders.id')
            
            // Filter by created_by
            ->where('transaction_lines.created_by', $ownerId)
            
            // Only show transactions that have a customer/vendor relationship
            ->whereNotNull(DB::raw('COALESCE(customers.name, revenues_customers.name, payments_venders.name, bills_venders.name)'));

        // Apply filters from request
        if (request()->filled('customer_name') && request('customer_name') !== '') {
            $customerName = request('customer_name');
            $query->where(function ($q) use ($customerName) {
                $q->where('customers.name', 'LIKE', "%{$customerName}%")
                  ->orWhere('revenues_customers.name', 'LIKE', "%{$customerName}%")
                  ->orWhere('payments_venders.name', 'LIKE', "%{$customerName}%")
                  ->orWhere('bills_venders.name', 'LIKE', "%{$customerName}%");
            });
        }

        if (request()->filled('transaction_type') && request('transaction_type') !== '') {
            $transactionType = request('transaction_type');
            $query->where('transaction_lines.reference', $transactionType);
        }

        if (request()->filled('start_date') && request()->filled('end_date')) {
            $startDate = request('start_date');
            $endDate = request('end_date');
            $query->whereBetween('transaction_lines.date', [$startDate, $endDate]);
        }

        return $query->orderBy('transaction_lines.date', 'desc')
                    ->orderBy(DB::raw('COALESCE(customers.name, revenues_customers.name, payments_venders.name, bills_venders.name)'), 'asc');
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('transaction-list-table')
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
            Column::make('transaction_type')->title(__('Transaction Type')),
            Column::make('num')->title(__('Num')),
            Column::make('posting')->title(__('Posting (Y/N)'))->addClass('text-center'),
            Column::make('memo_description')->title(__('Memo/Description')),
            Column::make('account_full_name')->title(__('Account Full Name')),
            Column::make('amount')->title(__('Amount'))->addClass('text-right'),
            Column::make('customer_name')->title(__('Customer'))->visible(false), // Hidden but used for grouping
        ];
    }

    protected function filename(): string
    {
        return 'TransactionListByCustomer_' . date('YmdHis');
    }
}