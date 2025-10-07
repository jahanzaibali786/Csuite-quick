<?php

namespace App\DataTables;

use App\Models\BillProduct;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use App\Models\PurchaseProduct;


class TransactionListByVendor extends DataTable
{
    public function dataTable($query)
    {
        $data = collect($query->get());

        $grandTotal = 0;
        $finalData = collect();

        // Group by vendor
        $vendors = $data->groupBy('vendor_name');

        foreach ($vendors as $vendor => $rows) {
            $vendorSubtotal = 0;

            // Vendor header row
            $finalData->push((object) [
                'transaction_date' => '',
                'transaction_type' => '<span class="" data-bucket="' . \Str::slug($vendor) . '"> <span class="icon">▼</span> <strong>' . $vendor . '</strong></span>',
                'transaction' => '',
                'posting_status' => '',
                'memo' => '',
                'account_full_name' => '',
                'amount' => 0,
                'vendor_name' => $vendor,
                'isVendorHeader' => true,
                'isParent' => true
            ]);

            foreach ($rows as $row) {
                $amount = ($row->price * $row->quantity) - ($row->discount ?? 0) + ($row->tax_amount ?? 0);

                $vendorSubtotal += $amount;

                $finalData->push((object) [
                    'transaction_date' => $row->transaction_date,
                    'transaction_type' => 'Bill',
                    'transaction' => \Auth::user()->billNumberFormat($row->bill),
                    'posting_status' => 'Y',
                    'memo' => $row->description,
                    'account_full_name' => $row->account_full_name,
                    'amount' => $amount,
                    'vendor_name' => $vendor,
                    'isDetail' => true,
                ]);
            }

            // Vendor subtotal row
            $finalData->push((object) [
                'transaction_date' => '',
                'transaction_type' => "<strong>Subtotal for {$vendor}</strong>",
                'transaction' => '',
                'posting_status' => '',
                'memo' => '',
                'account_full_name' => '',
                'amount' => $vendorSubtotal,
                'vendor_name' => $vendor,
                'isSubtotal' => true,
            ]);

            // Placeholder row
            $finalData->push((object) [
                'transaction_date' => '',
                'transaction_type' => '',
                'transaction' => '',
                'posting_status' => '',
                'memo' => '',
                'account_full_name' => '',
                'amount' => 0,
                'vendor_name' => $vendor,
                'isPlaceholder' => true,
            ]);

            $grandTotal += $vendorSubtotal;
        }

        // Grand total row
        $finalData->push((object) [
            'transaction_date' => '',
            'transaction_type' => 'Grand Total',
            'transaction' => '',
            'posting_status' => '',
            'memo' => '',
            'account_full_name' => '',
            'amount' => $grandTotal,
            'vendor_name' => '',
            'isGrandTotal' => true,
        ]);

        return datatables()
            ->collection($finalData)
            ->editColumn('transaction_date', fn($row) => isset($row->isDetail) ? $row->transaction_date : '')
            ->editColumn('transaction', fn($row) => $row->transaction ?? '')
            ->editColumn('memo', fn($row) => isset($row->isDetail) ? $row->memo : '')
            ->editColumn('account_full_name', fn($row) => isset($row->isDetail) ? $row->account_full_name : '')
            ->editColumn('amount', function ($row) {
                if ((isset($row->isVendorHeader) && $row->isVendorHeader) || (isset($row->isPlaceholder) && $row->isPlaceholder)) {
                    return '';
                }

                return number_format((float) $row->amount, 2);
            })
            ->setRowClass(function ($row) {
                $vendorSlug = $row->vendor_name
                    ? \Str::slug($row->vendor_name)
                    : 'no-vendor'; // 👈 fallback class name
    
                if (property_exists($row, 'isVendorHeader') && $row->isVendorHeader) {
                    return 'parent-row toggle-bucket bucket-' . $vendorSlug;
                }

                if (property_exists($row, 'isSubtotal') && $row->isSubtotal && !property_exists($row, 'isGrandTotal')) {
                    return 'subtotal-row bucket-' . $vendorSlug;
                }

                if (
                    !property_exists($row, 'isVendorHeader') &&
                    !property_exists($row, 'isSubtotal') &&
                    !property_exists($row, 'isGrandTotal') &&
                    !property_exists($row, 'isPlaceholder')
                ) {
                    return 'child-row bucket-' . $vendorSlug;
                }

                if (property_exists($row, 'isGrandTotal') && $row->isGrandTotal) {
                    return 'grandtotal-row';
                }

                return '';
            })

            ->rawColumns(['transaction', 'transaction_type']);
    }

    public function query(PurchaseProduct $model)
    {
        $start = request()->get('start_date')
            ?? request()->get('startDate')
            ?? Carbon::now()->startOfYear()->format('Y-m-d');

        $end = request()->get('end_date')
            ?? request()->get('endDate')
            ?? Carbon::now()->endOfDay()->format('Y-m-d');

        return $model->newQuery()
            ->select(
                'purchase_products.*',
                'purchases.purchase_id as purchase',
                'purchases.purchase_date as transaction_date',
                'purchase_products.description as description',
                'venders.name as vendor_name',
                'bank_accounts.bank_name as account_full_name',
                DB::raw('(SELECT IFNULL(SUM((pp.price * pp.quantity - pp.discount) * (taxes.rate / 100)),0)
                FROM purchase_products pp
                LEFT JOIN taxes ON FIND_IN_SET(taxes.id, pp.tax) > 0
                WHERE pp.id = purchase_products.id) as tax_amount')
            )
            ->join('purchases', 'purchases.id', '=', 'purchase_products.purchase_id')
            ->join('venders', 'venders.id', '=', 'purchases.vender_id')
            ->join('product_services', 'product_services.id', '=', 'purchase_products.product_id')
            ->leftJoin('purchase_payments', 'purchase_payments.purchase_id', '=', 'purchases.id')
            ->leftJoin('bank_accounts', 'bank_accounts.id', '=', 'purchase_payments.account_id')
            ->where('purchases.created_by', \Auth::user()->creatorId())
            ->whereBetween('purchases.purchase_date', [$start, $end]);
    }



    public function html()
    {
        return $this->builder()
            ->setTableId('customer-balance-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(0, 'asc')
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
            Column::make('transaction_date')->title('Date'),
            Column::make('transaction_type')->title('Transaction Type'),
            Column::make('transaction')->title('Transaction'),
            Column::make('posting_status')->title('Posting Y/N')->addClass('default-hidden'),
            Column::make('memo')->title('Memo / Description'),
            Column::make('account_full_name')->title('Account Full Name')->addClass('default-hidden'),
            Column::make('amount')->title('Amount'),
        ];
    }
}
