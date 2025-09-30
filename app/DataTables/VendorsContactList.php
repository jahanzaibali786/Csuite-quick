<?php

namespace App\DataTables;

use App\Models\Vender;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class VendorsContactList extends DataTable
{
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->editColumn('name', fn($row) => $row->name)
            ->editColumn('contact', fn($row) => $row->contact ?? '-')
            ->editColumn('email', fn($row) => $row->email ?? '-')
            ->editColumn('billing_name', fn($row) => $row->billing_name ?? '-')
            ->editColumn('billing_address', fn($row) => $row->billing_address ?? '-')
            ->editColumn('vender_id', fn($row) => $row->vender_id ?: '-');
    }

    public function query(Vender $model)
    {
        return $model->newQuery()
            ->select('id', 'vender_id', 'name', 'contact', 'email', 'billing_name', 'billing_address')
            ->whereNotNull('name')
            ->where('name', '!=', '');
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
            Column::make('name')->title('Vendor'),
            Column::make('contact')->title('Phone Number'),
            Column::make('email')->title('Email'),
            Column::make('billing_name')->title('Full Name'),
            Column::make('billing_address')->title('Billing Address'),
            Column::make('vender_id')->title('Account #'),
        ];
    }
}
