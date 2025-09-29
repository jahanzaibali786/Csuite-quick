<?php

namespace App\DataTables;

use App\Models\BillProduct;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ExpensesByVendorSummary extends DataTable
{
    public function dataTable($query)
    {
        $data = collect($query->get());

        $finalData = collect();
        $grandTotal = 0;

        // Group by vendor
        $vendors = $data->groupBy('vendor_name');

        foreach ($vendors as $vendor => $rows) {
            $vendorTotal = 0;

            foreach ($rows as $row) {
                $amount = ($row->price * $row->quantity) - ($row->discount ?? 0) + ($row->tax_amount ?? 0);
                $vendorTotal += $amount;
            }

            $finalData->push((object) [
                'vendor_name' => $vendor,
                'total' => $vendorTotal,
                'isDetail' => true,
            ]);

            $grandTotal += $vendorTotal;
        }

        // Add grand total row
        $finalData->push((object) [
            'vendor_name' => "<strong>Grand Total</strong>",
            'total' => $grandTotal,
            'isGrandTotal' => true,
        ]);

        return datatables()
            ->collection($finalData)
            ->editColumn('total', fn($row) => number_format((float) $row->total, 2))
            ->setRowClass(function ($row) {
                if (isset($row->isGrandTotal)) {
                    return 'grandtotal-row';
                }
                return 'detail-row';
            })
            ->rawColumns(['vendor_name']);
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
                'bills.bill_date as transaction_date',
                'venders.name as vendor_name',
                DB::raw('(SELECT IFNULL(SUM((bp.price * bp.quantity - bp.discount) * (taxes.rate / 100)),0)
                FROM bill_products bp
                LEFT JOIN taxes ON FIND_IN_SET(taxes.id, bp.tax) > 0
                WHERE bp.id = bill_products.id) as tax_amount')
            )
            ->join('bills', 'bills.id', '=', 'bill_products.bill_id')
            ->join('venders', 'venders.id', '=', 'bills.vender_id')
            ->where('bills.created_by', \Auth::user()->creatorId())
            ->whereBetween('bills.bill_date', [$start, $end]);
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
                'responsive' => true,
            ]);
    }

    protected function getColumns()
    {
        return [
            Column::make('vendor_name')->title('Vendor'),
            Column::make('total')->title('Total'),
        ];
    }
}
