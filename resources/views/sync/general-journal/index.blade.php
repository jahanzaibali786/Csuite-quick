@extends('layouts.app')

@push('datatable-styles')
    @include('sections.datatable_css')
@endpush

@section('filter-section')
    <style>
        .content-wrapper {
            background: #fff !important;
            padding: 30px 100px !important;
        }

        .dt-buttons {
            position: fixed !important;
            top: 11% !important;
            right: 10px !important;
            z-index: 1 !important;
        }
        
    </style>
    <x-filters.filter-box>
        <div class="select-box d-flex p-3 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.date')</p>
            <div class="select-status d-flex">
                <input type="text"
                    class="position-relative text-dark form-control border-0 p-2 text-left f-14 f-w-500 border-additional-grey"
                    id="datatableRange" placeholder="@lang('placeholders.dateRange')">
            </div>
        </div>

        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <div class="select-status">
                <select class="form-control select-picker" name="status" id="status" data-live-search="true"
                    data-size="8">
                    <option value="all">@lang('app.all') @lang('app.status')</option>
                    <option value="draft">@lang('app.draft')</option>
                    <option value="approved">@lang('app.approved')</option>
                </select>
            </div>
        </div>

        <x-slot name="action">
            <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0">
                <x-forms.button-secondary class="btn-xs d-none d-lg-block d-md-block mr-2" id="reset-filters"
                    icon="times-circle">
                    @lang('app.clearFilters')
                </x-forms.button-secondary>

                <x-forms.button-secondary class="btn-xs d-none d-lg-block d-md-block mr-2" id="export-journal"
                    icon="file-export">
                    @lang('app.export')
                </x-forms.button-secondary>
            </div>
        </x-slot>
    </x-filters.filter-box>
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="d-flex flex-column w-tables rounded mt-3 bg-white">
            <div
                class="d-lg-flex d-md-flex d-block justify-content-between p-4 bg-white border-bottom-grey text-capitalize">
                <h4 class="heading-h4 mb-0">
                    General Journal
                    <span class="text-lightest f-12 ml-2" id="date-range-display"></span>
                </h4>
            </div>

            <div class="table-responsive p-20">
                {!! $dataTable->table(['class' => 'table table-hover border-0 w-100 general-journal-table']) !!}
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('sections.datatable_js')

    <script>
        $('#general-journal-table').on('preXhr.dt', function(e, settings, data) {
            var dateRangePicker = $('#datatableRange').data('daterangepicker');
            var startDate = $('#datatableRange').val();

            if (startDate == '') {
                data.startDate = null;
                data.endDate = null;
            } else {
                data.startDate = dateRangePicker.startDate.format('{!! company()->moment_date_format !!}');
                data.endDate = dateRangePicker.endDate.format('{!! company()->moment_date_format !!}');
            }

            data.status = $('#status').val();
        });

        const showTable = () => {
            window.LaravelDataTables["general-journal-table"].draw(false);
        }

        $('#datatableRange').daterangepicker({
            locale: daterangeLocale,
            linkedCalendars: false,
            startDate: moment().startOf('month'),
            endDate: moment(),
            ranges: daterangeConfig
        });

        $('#datatableRange').on('apply.daterangepicker', function(ev, picker) {
            $('#date-range-display').text(picker.startDate.format('MMM DD, YYYY') + ' - ' + picker.endDate.format(
                'MMM DD, YYYY'));
            showTable();
        });

        $('#status').on('change', function() {
            showTable();
        });

        $('#reset-filters').click(function() {
            $('#datatableRange').val('');
            $('#datatableRange').data('daterangepicker').setStartDate(moment().startOf('month'));
            $('#datatableRange').data('daterangepicker').setEndDate(moment());
            $('#status').val('all').selectpicker('refresh');
            $('#date-range-display').text('');
            showTable();
        });

        $('#export-journal').click(function() {
            var dateRangePicker = $('#datatableRange').data('daterangepicker');
            var startDate = dateRangePicker ? dateRangePicker.startDate.format('YYYY-MM-DD') : '';
            var endDate = dateRangePicker ? dateRangePicker.endDate.format('YYYY-MM-DD') : '';
            var status = $('#status').val();

            var url = '{{ route('general-journal.export') }}' +
                '?startDate=' + startDate +
                '&endDate=' + endDate +
                '&status=' + status;

            window.open(url, '_blank');
        });

        // Initialize date display
        $('#date-range-display').text(moment().startOf('month').format('MMM DD, YYYY') + ' - ' + moment().format(
            'MMM DD, YYYY'));
    </script>

    <style>
        .general-journal-table {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
        }

        .general-journal-table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            font-size: 11px;
            text-transform: uppercase;
            padding: 8px 6px;
        }

        .general-journal-table tbody tr {
            border-bottom: 1px solid #f1f1f1;
        }

        .general-journal-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .general-journal-table tbody tr.journal-header-row {
            background-color: #e9ecef;
            border-top: 2px solid #dee2e6;
            border-bottom: 1px solid #dee2e6;
        }

        .general-journal-table tbody tr.journal-header-row:hover {
            background-color: #e9ecef;
        }

        .general-journal-table tbody tr.journal-entry-row {
            background-color: #fff;
            border-bottom: 1px solid #f1f1f1;
        }

        .general-journal-table tbody tr.journal-line-row {
            background-color: #fafafa;
        }

        .general-journal-table tbody tr.journal-line-row:hover {
            background-color: #f0f0f0;
        }

        .journal-header {
            font-weight: 700;
            font-size: 13px;
            color: #495057;
            text-transform: uppercase;
        }

        .text-right {
            text-align: right !important;
        }

        /* QuickBooks-style spacing */
        .general-journal-table tbody tr td {
            padding: 6px 8px;
            vertical-align: middle;
            border-right: 1px solid #f1f1f1;
        }

        .general-journal-table tbody tr td:last-child {
            border-right: none;
        }

        .general-journal-table tbody tr.journal-header-row td {
            padding: 10px 8px;
            font-weight: bold;
            border-right: none;
        }

        /* Account indentation */
        .general-journal-table tbody tr.journal-line-row td:nth-child(7) {
            padding-left: 20px;
            font-weight: 500;
        }

        /* Debit/Credit columns */
        .general-journal-table tbody tr td:nth-child(8),
        .general-journal-table tbody tr td:nth-child(9) {
            font-family: 'Courier New', monospace;
            font-weight: 500;
        }

        /* Zebra striping for journal entries */
        .general-journal-table tbody tr:nth-child(4n+1).journal-entry-row,
        .general-journal-table tbody tr:nth-child(4n+2).journal-line-row,
        .general-journal-table tbody tr:nth-child(4n+3).journal-line-row {
            background-color: #fff;
        }

        /* Spacing between journal entries */
        .general-journal-table tbody tr[data-is-spacer="true"] {
            height: 10px;
            border: none;
        }

        .general-journal-table tbody tr[data-is-spacer="true"] td {
            border: none;
            padding: 5px;
        }

        /* Print styles */
        @media print {
            .general-journal-table {
                font-size: 10px;
            }

            .general-journal-table thead th {
                font-size: 9px;
                padding: 4px;
            }

            .general-journal-table tbody tr td {
                padding: 3px 4px;
            }
        }
    </style>
@endpush
