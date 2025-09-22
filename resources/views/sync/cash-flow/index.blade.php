@extends('layouts.app')

@push('datatable-styles')
    @include('sections.datatable_css')
@endpush

@section('filter-section')
<x-filters.filter-box>
    <div class="select-box d-flex pr-2 border-right-grey border-right-grey-sm-0">
        <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.date')</p>
        <div class="select-status d-flex">
            <input type="text" class="position-relative text-dark form-control border-0 p-2 text-left f-14 f-w-500 border-additional-grey"
                id="datatableRange" placeholder="@lang('placeholders.dateRange')">
        </div>
    </div>

    <x-slot name="action">
        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0">
            <x-forms.button-secondary class="btn-xs d-none d-lg-block d-md-block mr-2" id="reset-filters" icon="times-circle">
                @lang('app.clearFilters')
            </x-forms.button-secondary>
        </div>
    </x-slot>
</x-filters.filter-box>
@endsection

@section('content')
<div class="content-wrapper">
    <div class="d-flex flex-column w-tables rounded mt-3 bg-white">
        <div class="d-lg-flex d-md-flex d-block justify-content-between p-4 bg-white border-bottom-grey text-capitalize">
            <h4 class="heading-h4 mb-0">
                Cash Flow Statement
                <span class="text-lightest f-12 ml-2" id="date-range-display"></span>
            </h4>
        </div>

        <div class="table-responsive p-20">
            {!! $dataTable->table(['class' => 'table table-hover border-0 w-100 cash-flow-table']) !!}
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @include('sections.datatable_js')
    
    <script>
        $('#cash-flow-table').on('preXhr.dt', function (e, settings, data) {
            var dateRangePicker = $('#datatableRange').data('daterangepicker');
            var startDate = $('#datatableRange').val();

            if (startDate == '') {
                data.startDate = null;
                data.endDate = null;
            } else {
                data.startDate = dateRangePicker.startDate.format('{!! company()->moment_date_format !!}');
                data.endDate = dateRangePicker.endDate.format('{!! company()->moment_date_format !!}');
            }
        });

        const showTable = () => {
            window.LaravelDataTables["cash-flow-table"].draw(false);
        }

        $('#datatableRange').daterangepicker({
            locale: daterangeLocale,
            linkedCalendars: false,
            startDate: moment().startOf('month'),
            endDate: moment(),
            ranges: daterangeConfig
        });

        $('#datatableRange').on('apply.daterangepicker', function (ev, picker) {
            $('#date-range-display').text(picker.startDate.format('MMM DD, YYYY') + ' - ' + picker.endDate.format('MMM DD, YYYY'));
            showTable();
        });

        $('#reset-filters').click(function () {
            $('#datatableRange').val('');
            $('#datatableRange').data('daterangepicker').setStartDate(moment().startOf('month'));
            $('#datatableRange').data('daterangepicker').setEndDate(moment());
            $('#date-range-display').text('');
            showTable();
        });

        // Initialize date display
        $('#date-range-display').text(moment().startOf('month').format('MMM DD, YYYY') + ' - ' + moment().format('MMM DD, YYYY'));
    </script>

    <style>
        .cash-flow-table {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .cash-flow-table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            font-size: 14px;
        }
        
        .cash-flow-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .cash-flow-table tbody tr.section-header-row {
            background-color: #e9ecef;
        }
        
        .cash-flow-table tbody tr.section-header-row:hover {
            background-color: #e9ecef;
        }
        
        .cash-flow-table tbody tr.subtotal-row {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
        
        .cash-flow-table tbody tr.subtotal-row:hover {
            background-color: #f8f9fa;
        }
        
        .cash-flow-table tbody tr.total-row {
            background-color: #e9ecef;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
        }
        
        .cash-flow-table tbody tr.total-row:hover {
            background-color: #e9ecef;
        }
        
        .section-header {
            font-weight: 700;
            font-size: 14px;
            color: #495057;
            text-transform: uppercase;
        }
        
        .subtotal-label {
            font-weight: 600;
            color: #495057;
            font-size: 13px;
        }
        
        .total-label {
            font-weight: 700;
            color: #212529;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .subtotal-amount {
            font-weight: 600;
            color: #495057;
            border-top: 1px solid #000;
        }
        
        .total-amount {
            font-weight: 700;
            color: #212529;
            border-top: 2px solid #000;
            border-bottom: 3px double #000;
            padding-top: 2px;
        }
        
        .amount-cell {
            color: #495057;
        }
        
        .child-row td {
            padding-left: 20px;
        }
        
        .text-right {
            text-align: right !important;
        }
        
        /* QuickBooks-style spacing */
        .cash-flow-table tbody tr td {
            padding: 8px 12px;
            vertical-align: middle;
        }
        
        .cash-flow-table tbody tr.section-header-row td {
            padding: 12px;
            font-weight: bold;
        }
        
        /* Hide borders on empty rows */
        .cash-flow-table tbody tr td:empty {
            border: none;
            padding: 4px;
        }
        
        /* Negative amounts in parentheses */
        .amount-cell:contains('-') {
            color: #dc3545;
        }
        
        .amount-cell:contains('-'):before {
            content: '(';
        }
        
        .amount-cell:contains('-'):after {
            content: ')';
        }
    </style>
@endpush