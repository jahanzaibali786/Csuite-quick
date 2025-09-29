<?php

namespace App\DataTables;

use App\Models\BillProduct;
use Carbon\Carbon;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Illuminate\Support\Facades\DB;

class OpenPurchaseOrderList extends DataTable
{
    public function dataTable($query)
    {
        $data = collect($query->get());

        $grandTotal = [
            'amount' => 0,
            'open_balance' => 0,
        ];

        $finalData = collect();
        $vendors = $data->groupBy('vendor_name');

        foreach ($vendors as $vendor => $rowsByVendor) {
            $vendorTotals = [
                'amount' => 0,
                'open_balance' => 0,
            ];

            // Vendor header
            $finalData->push((object) [
                'transaction_date' => '',
                'vendor' => $vendor,
                'transaction' => '<span class="" data-bucket="' . \Str::slug($vendor) . '"> <span class="icon">▼</span> <strong>' . $vendor . '</strong></span>',
                'memo' => '',
                'ship_via' => '',
                'amount' => '',
                'open_balance' => '',
                'isParent' => true,
                'isVendor' => true,
            ]);

            foreach ($rowsByVendor as $row) {
                $row->transaction = \Auth::user()->billNumberFormat($row->bill ?? $row->bill_id);
                $row->memo = $row->description ?? '';
                $row->ship_via = ''; // leave empty since column doesn't exist

                $row->amount = ($row->price * $row->quantity) - ($row->discount ?? 0) + ($row->tax_amount ?? 0);
                $row->received_amount = $row->price * $row->received_quantity;
                $row->open_balance = $row->amount - $row->received_amount;

                $vendorTotals['amount'] += $row->amount;
                $vendorTotals['open_balance'] += $row->open_balance;
                $row->vendor = $vendor;
                $finalData->push($row);
            }

            // Vendor subtotal
            $finalData->push((object) [
                'vendor' => $vendor,
                'transaction_date' => '',
                'transaction' => "<strong>Subtotal for {$vendor}</strong>",
                'memo' => '',
                'ship_via' => '',
                'amount' => $vendorTotals['amount'],
                'open_balance' => $vendorTotals['open_balance'],
                'isSubtotal' => true,
            ]);

            foreach ($vendorTotals as $key => $val) {
                $grandTotal[$key] += $val;
            }

            // Empty row for spacing
            $finalData->push((object) [
                'vendor' => $vendor,
                'transaction_date' => '',
                'transaction' => '',
                'memo' => '',
                'ship_via' => '',
                'amount' => '',
                'open_balance' => '',
                'isPlaceholder' => true,
            ]);
        }

        // Grand total
        $finalData->push((object) [
            'transaction_date' => '',
            'transaction' => '<strong>Grand Total</strong>',
            'memo' => '',
            'ship_via' => '',
            'amount' => $grandTotal['amount'],
            'open_balance' => $grandTotal['open_balance'],
            'isGrandTotal' => true,
        ]);

        return datatables()
            ->collection($finalData)
            ->editColumn(
                'transaction_date',
                fn($row) =>
                isset($row->isSubtotal) || isset($row->isParent) || isset($row->isGrandTotal) || isset($row->isPlaceholder)
                ? ''
                : ($row->transaction_date ? Carbon::parse($row->transaction_date)->format('Y-m-d') : '')
            )
            ->setRowClass(function ($row) {
                if (isset($row->isVendor))
                    return 'vendor-row';
                if (isset($row->isSubtotal))
                    return 'subtotal-row';
                if (isset($row->isGrandTotal))
                    return 'grandtotal-row';
                if (isset($row->isPlaceholder))
                    return 'placeholder-row';
                return 'detail-row';
            })
            ->setRowClass(function ($row) {
                if (property_exists($row, 'isParent') && $row->isParent) {
                    return 'parent-row toggle-bucket bucket-' . \Str::slug($row->vendor ?? 'na');
                }

                if (property_exists($row, 'isSubtotal') && $row->isSubtotal && !property_exists($row, 'isGrandTotal')) {
                    return 'subtotal-row bucket-' . \Str::slug($row->vendor ?? 'na');
                }

                if (
                    !property_exists($row, 'isParent') &&
                    !property_exists($row, 'isSubtotal') &&
                    !property_exists($row, 'isGrandTotal') &&
                    !property_exists($row, 'isPlaceholder')
                ) {
                    return 'child-row bucket-' . \Str::slug($row->vendor ?? 'na');
                }

                if (property_exists($row, 'isGrandTotal') && $row->isGrandTotal) {
                    return 'grandtotal-row';
                }

                return '';
            })
            ->rawColumns(['transaction']);
    }

    public function query(BillProduct $model)
    {
        $start = request()->get('start_date')
            ?? request()->get('startDate')
            ?? Carbon::now()->startOfYear()->format('Y-m-d');

        $end = request()->get('end_date')
            ?? request()->get('endDate')
            ?? Carbon::now()->endOfDay()->format('Y-m-d');

        return $model->newQuery()
            ->select(
                'bill_products.*',
                'bills.bill_id as bill',
                'bills.bill_date as transaction_date',
                'bill_products.description',
                'venders.name as vendor_name',
                DB::raw('IFNULL(SUM(invoice_products.quantity),0) as received_quantity'),
                DB::raw('(SELECT IFNULL(SUM((bp.price * bp.quantity - bp.discount) * (taxes.rate / 100)),0) 
                FROM bill_products bp
                LEFT JOIN taxes ON FIND_IN_SET(taxes.id, bp.tax) > 0
                WHERE bp.id = bill_products.id) as tax_amount')
            )
            ->join('bills', 'bills.id', '=', 'bill_products.bill_id')
            ->join('venders', 'venders.id', '=', 'bills.vender_id')
            ->leftJoin('invoice_products', 'invoice_products.product_id', '=', 'bill_products.product_id')
            ->where('bills.created_by', \Auth::user()->creatorId())
            ->whereBetween('bills.bill_date', [$start, $end])
            ->groupBy(
                'bill_products.id',
                'bills.bill_id',
                'bills.bill_date',
                'bill_products.description',
                'venders.name'
            );
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
            Column::make('transaction')->title('Transaction'),
            Column::make('memo')->title('Memo / Description'),
            Column::make('ship_via')->title('Ship Via'),
            Column::make('amount')->title('Amount'),
            Column::make('open_balance')->title('PO Open Balance'),
        ];
    }
}
