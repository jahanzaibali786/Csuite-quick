<?php

namespace App\DataTables;

use App\Models\ProductService;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class InventoryValuationSummaryDataTable extends DataTable
{
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->addColumn('sale_price', fn($r) => \Auth::user()->priceFormat($r->sale_price))
            ->addColumn('purchase_price', fn($r) => \Auth::user()->priceFormat($r->purchase_price))
            ->addColumn('category', fn($r) => $r->category->name ?? '-')
            ->addColumn('unit', fn($r) => $r->unit->name ?? '-')
            ->addColumn('tax', function ($r) {
                if (empty($r->tax_id)) return '-';
                $out = [];
                $taxData = \App\Models\Utility::getTaxData();
                foreach (explode(',', $r->tax_id) as $id) {
                    if (!isset($taxData[$id])) continue;
                    $out[] = $taxData[$id]['name'].' ('.$taxData[$id]['rate'].'%)';
                }
                return implode('<br>', $out);
            })
            ->addColumn('quantity', fn($r) => $r->type === 'product' ? $r->quantity : '-')
            ->addColumn('type', fn($r) => ucwords($r->type))
            ->addColumn('action', function ($r) {
                $html = '';
                if (\Gate::check('edit product & service') || \Gate::check('delete product & service')) {
                    $html .= '<div class="action-btn bg-warning ms-2">
                        <a href="#" class="mx-3 btn btn-sm align-items-center"
                           data-url="'.route('productservice.detail', $r->id).'"
                           data-ajax-popup="true"
                           data-title="'.__('Warehouse Details').'"
                           data-bs-toggle="tooltip" title="'.__('Warehouse Details').'">
                           <i class="ti ti-eye text-white"></i>
                        </a></div>';
                    if (\Gate::check('edit product & service')) {
                        $html .= '<div class="action-btn bg-info ms-2">
                            <a href="#" class="mx-3 btn btn-sm align-items-center"
                               data-url="'.route('productservice.edit', $r->id).'"
                               data-ajax-popup="true" data-size="lg"
                               data-title="'.__('Edit Product').'"
                               data-bs-toggle="tooltip" title="'.__('Edit').'">
                               <i class="ti ti-pencil text-white"></i>
                            </a></div>';
                    }
                    if (\Gate::check('delete product & service')) {
                        $html .= '<div class="action-btn bg-danger ms-2">
                            <form method="POST" action="'.route('productservice.destroy', $r->id).'" id="delete-form-'.$r->id.'">
                                '.csrf_field().method_field('DELETE').'
                                <a href="#" class="mx-3 btn btn-sm align-items-center bs-pass-para"
                                   data-bs-toggle="tooltip" title="'.__('Delete').'">
                                   <i class="ti ti-trash text-white"></i>
                                </a>
                            </form></div>';
                    }
                }
                return $html;
            })
            ->rawColumns(['tax','action']);
    }

    public function query(ProductService $model)
    {
        $user = Auth::user();
        $ownerId = $user->type === 'company' ? $user->creatorId() : $user->ownedId();

        $q = $model->with(['category','unit'])
            ->where('created_by', $ownerId);

        // Date filter (adjust column if needed)
        if (request()->filled('start_date') && request()->filled('end_date')) {
            $q->whereBetween('created_at', [
                request('start_date').' 00:00:00',
                request('end_date').' 23:59:59',
            ]);
        }

        if (request()->filled('category') && request('category') !== '') {
            $q->where('category_id', request('category'));
        }
        if (request()->filled('type') && request('type') !== '') {
            $q->where('type', request('type'));
        }

        return $q;
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('inventory-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('rt')
            ->parameters([
                'responsive' => true,
                'autoWidth'  => false,
                'paging'     => false,
                'searching'  => false,
                'info'       => false,
                'ordering'   => false,
                'colReorder' => true,
                'fixedHeader'=> true,
                'scrollY'    => '420px',
                'scrollX'    => true,
                'scrollCollapse' => true,
            ]);
    }

    protected function getColumns()
    {
        return [
            Column::make('name')->title(__('Name')),
            Column::make('sku')->title(__('Sku')),
            Column::make('sale_price')->title(__('Sale Price'))->addClass('text-right'),
            Column::make('purchase_price')->title(__('Purchase Price'))->addClass('text-right'),
            Column::make('tax')->title(__('Tax')),
            Column::make('category')->title(__('Category')),
            Column::make('unit')->title(__('Unit')),
            Column::make('quantity')->title(__('Quantity'))->addClass('text-right'),
            Column::make('type')->title(__('Type')),
            Column::computed('action')->title(__('Action'))->exportable(false)->printable(false)->width(120),
        ];
    }

    protected function filename(): string
    {
        return 'InventoryValuationSummary_'.date('YmdHis');
    }
}
