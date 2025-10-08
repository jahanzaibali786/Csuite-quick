<?php

namespace App\DataTables;

use App\Models\BillProduct;
use Carbon\Carbon;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Illuminate\Support\Facades\DB;
use App\Models\PurchaseProduct;

class OpenPurchaseOrderDetail extends DataTable
{
    public function dataTable($query)
    {
        $data = collect($query->get());

        $grandTotal = [
            'quantity' => 0,
            'received_quantity' => 0,
            'backordered_quantity' => 0,
            'total_amount' => 0,
            'received_amount' => 0,
            'open_balance' => 0,
        ];

        $finalData = collect();
        $categories = $data->groupBy('category_name');

        foreach ($categories as $category => $rowsByCategory) {
            $categoryTotals = [
                'quantity' => 0,
                'received_quantity' => 0,
                'backordered_quantity' => 0,
                'total_amount' => 0,
                'received_amount' => 0,
                'open_balance' => 0,
            ];

            // Category header
            $finalData->push((object) [
                'transaction_date' => '',
                'category' => $category,
                'transaction' => '<span class="" data-bucket="' . \Str::slug($category) . '"> <span class="icon">▼</span> <strong>' . $category . '</strong></span>',
                'vendor_name' => '',
                'product_name' => '',
                'full_name' => '',
                'quantity' => '',
                'received_quantity' => '',
                'backordered_quantity' => '',
                'total_amount' => '',
                'received_amount' => '',
                'open_balance' => '',
                'isParent' => true,
                'isCategory' => true,
            ]);

            $products = $rowsByCategory->groupBy('product_name');

            foreach ($products as $product => $rowsByProduct) {
                $productTotals = [
                    'quantity' => 0,
                    'received_quantity' => 0,
                    'backordered_quantity' => 0,
                    'total_amount' => 0,
                    'received_amount' => 0,
                    'open_balance' => 0,
                ];

                // Product header
                $finalData->push((object) [
                    'transaction_date' => '',
                    'category' => $category,
                    'transaction' => "&nbsp;&nbsp;<strong>{$product}</strong>",
                    'vendor_name' => '',
                    'product_name' => '',
                    'full_name' => '',
                    'quantity' => '',
                    'received_quantity' => '',
                    'backordered_quantity' => '',
                    'total_amount' => '',
                    'received_amount' => '',
                    'open_balance' => '',
                    // 'isParent' => true,
                    'isProduct' => true,
                ]);

                foreach ($rowsByProduct as $row) {
                    $row->transaction = \Auth::user()->billNumberFormat($row->bill ?? $row->bill_id);
                    $row->full_name = $row->full_name ?? '';
                    $row->backordered_quantity = $row->quantity - $row->received_quantity;
                    $row->total_amount = ($row->price * $row->quantity) - ($row->discount ?? 0) + ($row->tax_amount ?? 0);
                    $row->received_amount = $row->price * $row->received_quantity;
                    $row->open_balance = $row->total_amount - $row->received_amount;
                    $row->category = $category;
                    $productTotals['quantity'] += $row->quantity;
                    $productTotals['received_quantity'] += $row->received_quantity;
                    $productTotals['backordered_quantity'] += $row->backordered_quantity;
                    $productTotals['total_amount'] += $row->total_amount;
                    $productTotals['received_amount'] += $row->received_amount;
                    $productTotals['open_balance'] += $row->open_balance;

                    $finalData->push($row);
                }

                // Product subtotal
                $finalData->push((object) [
                    'transaction_date' => '',
                    'category' => $category,
                    'transaction' => "<strong>Subtotal for {$product}</strong>",
                    'vendor_name' => '',
                    'product_name' => '',
                    'full_name' => '',
                    'quantity' => $productTotals['quantity'],
                    'received_quantity' => $productTotals['received_quantity'],
                    'backordered_quantity' => $productTotals['backordered_quantity'],
                    'total_amount' => $productTotals['total_amount'],
                    'received_amount' => $productTotals['received_amount'],
                    'open_balance' => $productTotals['open_balance'],
                    'isSubtotal' => true,
                ]);

                foreach ($productTotals as $key => $val) {
                    $categoryTotals[$key] += $val;
                }
            }

            // Category subtotal
            $finalData->push((object) [
                'transaction_date' => '',
                'transaction' => "<strong>Subtotal for {$category}</strong>",
                'vendor_name' => '',
                'product_name' => '',
                'full_name' => '',
                'category' => $category,
                'quantity' => $categoryTotals['quantity'],
                'received_quantity' => $categoryTotals['received_quantity'],
                'backordered_quantity' => $categoryTotals['backordered_quantity'],
                'total_amount' => $categoryTotals['total_amount'],
                'received_amount' => $categoryTotals['received_amount'],
                'open_balance' => $categoryTotals['open_balance'],
                'isSubtotal' => true,
            ]);

            // Empty placeholder row
            $finalData->push((object) [
                'transaction_date' => '',
                'transaction' => '',
                'vendor_name' => '',
                'product_name' => '',
                'full_name' => '',
                'quantity' => '',
                'received_quantity' => '',
                'backordered_quantity' => '',
                'total_amount' => '',
                'received_amount' => '',
                'open_balance' => '',
                'isPlaceholder' => true,
            ]);

            foreach ($categoryTotals as $key => $val) {
                $grandTotal[$key] += $val;
            }
        }

        // Grand total
        $finalData->push((object) [
            'transaction_date' => '',
            'transaction' => '<strong>Grand Total</strong>',
            'vendor_name' => '',
            'product_name' => '',
            'full_name' => '',
            'quantity' => $grandTotal['quantity'],
            'received_quantity' => $grandTotal['received_quantity'],
            'backordered_quantity' => $grandTotal['backordered_quantity'],
            'total_amount' => $grandTotal['total_amount'],
            'received_amount' => $grandTotal['received_amount'],
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

            ->editColumn(
                'vendor_name',
                fn($row) =>
                isset($row->isSubtotal) || isset($row->isParent) || isset($row->isGrandTotal) || isset($row->isPlaceholder)
                ? ''
                : $row->vendor_name
            )
            ->setRowClass(function ($row) {
                if (isset($row->isCategory))
                    return 'category-row';
                if (isset($row->isProduct))
                    return 'product-row';
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
                    return 'parent-row toggle-bucket bucket-' . \Str::slug($row->category ?? 'na');
                }

                if (property_exists($row, 'isSubtotal') && $row->isSubtotal && !property_exists($row, 'isGrandTotal')) {
                    return 'subtotal-row bucket-' . \Str::slug($row->category ?? 'na');
                }

                if (
                    !property_exists($row, 'isParent') &&
                    !property_exists($row, 'isSubtotal') &&
                    !property_exists($row, 'isGrandTotal') &&
                    !property_exists($row, 'isPlaceholder')
                ) {
                    return 'child-row bucket-' . \Str::slug($row->category ?? 'na');
                }

                if (property_exists($row, 'isGrandTotal') && $row->isGrandTotal) {
                    return 'grandtotal-row';
                }

                return '';
            })
            ->rawColumns(['transaction']);
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
                'venders.name as vendor_name',
                'product_services.name as product_name',
                'product_service_categories.name as category_name',
                'product_services.name as full_name',
                DB::raw('(SELECT IFNULL(SUM((pp.price * pp.quantity - pp.discount) * (taxes.rate / 100)),0)
                FROM purchase_products pp
                LEFT JOIN taxes ON FIND_IN_SET(taxes.id, pp.tax) > 0
                WHERE pp.id = purchase_products.id) as tax_amount')
            )
            ->join('purchases', 'purchases.id', '=', 'purchase_products.purchase_id')
            ->join('venders', 'venders.id', '=', 'purchases.vender_id')
            ->join('product_services', 'product_services.id', '=', 'purchase_products.product_id')
            ->join('product_service_categories', 'product_service_categories.id', '=', 'product_services.category_id')
            ->where('purchases.created_by', \Auth::user()->creatorId())
            ->whereBetween('purchases.purchase_date', [$start, $end])
            ->groupBy(
                'purchase_products.id',
                'purchases.purchase_id',
                'purchases.purchase_date',
                'venders.name',
                'product_services.name',
                'product_service_categories.name'
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
            Column::make('vendor_name')->title('Vendor Name'),
            Column::make('product_name')->title('Product/Service Name'),
            Column::make('full_name')->title('Full Name'),
            Column::make('quantity')->title('Quantity'),
            Column::make('received_quantity')->title('Received Quantity')->addClass('default-hidden'),
            Column::make('backordered_quantity')->title('Backordered Quantity'),
            Column::make('total_amount')->title('Total Amount'),
            Column::make('received_amount')->title('Received Amount'),
            Column::make('open_balance')->title('PO Open Balance'),
        ];
    }
}
