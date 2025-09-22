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

        .section-row {
            background-color: #f2f2f2 !important;
            font-weight: bold;
        }
    </style>
    <x-filters.filter-box>
        <div class="select-box d-flex pr-2 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.date')</p>
            <div class="select-status d-flex">
                <input type="text"
                    class="position-relative text-dark form-control border-0 p-2 text-left f-14 f-w-500 border-additional-grey"
                    id="datatableRange" placeholder="@lang('placeholders.dateRange')">
            </div>
        </div>

        <x-slot name="action">
            <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0">
                <x-forms.button-secondary class="btn-xs d-none d-lg-block d-md-block mr-2" id="reset-filters"
                    icon="times-circle">
                    @lang('app.clearFilters')
                </x-forms.button-secondary>
                <x-forms.button-secondary class="btn-xs d-none d-lg-block d-md-block mr-2" id="expand-all"
                    icon="expand-alt">
                    Expand All
                </x-forms.button-secondary>
                <x-forms.button-secondary class="btn-xs d-none d-lg-block d-md-block" id="collapse-all" icon="compress-alt">
                    Collapse All
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
                    Profit & Loss Statement
                    <span class="text-lightest f-12 ml-2" id="date-range-display"></span>
                </h4>
            </div>

            <div class="table-responsive">
                {!! $dataTable->table(['class' => 'table table-hover border-0 w-100 profit-loss-table']) !!}
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('sections.datatable_js')

    <script>
        $('#profit-loss-table').on('preXhr.dt', function(e, settings, data) {
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
            window.LaravelDataTables["profit-loss-table"].draw(false);
        }

        $('#datatableRange').daterangepicker({
            locale: daterangeLocale,
            linkedCalendars: false,
            startDate: moment().startOf('year'),
            endDate: moment(),
            ranges: daterangeConfig
        });

        $('#datatableRange').on('apply.daterangepicker', function(ev, picker) {
            $('#date-range-display').text(picker.startDate.format('MMM DD, YYYY') + ' - ' + picker.endDate.format(
                'MMM DD, YYYY'));
            showTable();
        });

        $('#reset-filters').click(function() {
            $('#datatableRange').val('');
            $('#datatableRange').data('daterangepicker').setStartDate(moment().startOf('year'));
            $('#datatableRange').data('daterangepicker').setEndDate(moment());
            $('#date-range-display').text('');
            showTable();
        });

        // Initialize date display
        $('#date-range-display').text(moment().startOf('year').format('MMM DD, YYYY') + ' - ' + moment().format(
            'MMM DD, YYYY'));

        // Enhanced hierarchical expand/collapse functionality
        $(document).on('click', '.toggle-section', function(e) {
            e.preventDefault();

            let $this = $(this);
            let group = $this.data('group');
            let $row = $this.closest('tr'); // the section row
            let $chevron = $this.find('.toggle-chevron');
            let $sectionTotal = $row.find('.section-total-amount[data-group="' + group + '"]');
            let $childRows = $('.group-' + group);
            if ($chevron.length === 0) return;
            if ($chevron.hasClass('fa-chevron-down')) {
                // Collapse
                $childRows.hide();
                $chevron.removeClass('fa-chevron-down').addClass('fa-chevron-right');
                $sectionTotal.show();
            } else {
                // Expand
                $childRows.show();
                $chevron.removeClass('fa-chevron-right').addClass('fa-chevron-down');
                $sectionTotal.hide();
            }
        });

        // Enhanced Expand All functionality
        $('#expand-all').click(function() {
            $('.child-row, .subtotal-row').show();
            $('.toggle-chevron').removeClass('fa-chevron-right').addClass('fa-chevron-down');
            $('.section-total-amount').css('display', 'none');
        });

        // Enhanced Collapse All functionality
        $('#collapse-all').click(function() {
            $('.child-row, .subtotal-row').hide();
            $('.toggle-chevron').removeClass('fa-chevron-down').addClass('fa-chevron-right');
            $('.section-total-amount').css('display', 'table-row');
        });

        // Function to initialize expansion state and hide section totals
        function initializeTableState() {
            // Show all child and subtotal rows
            $('.child-row, .subtotal-row').show();
            // Set all chevrons to expanded state
            $('.toggle-chevron').removeClass('fa-chevron-right').addClass('fa-chevron-down');
            // Hide all section totals (since we're showing details)
            $('.section-total-display').hide();

            // Update cursor for sections with children
            $('.toggle-section').each(function() {
                let group = $(this).data('group');
                let hasChildren = $('.group-' + group).length > 0;
                let $chevron = $(this).find('.toggle-chevron');

                if (hasChildren && $chevron.length === 0) {
                    // Add chevron if children exist but chevron is missing
                    $(this).prepend('<i class="fas fa-chevron-down toggle-chevron mr-2"></i>');
                    $(this).css('cursor', 'pointer');
                } else if (!hasChildren) {
                    // Remove chevron and change cursor if no children
                    $chevron.remove();
                    $(this).css('cursor', 'default');
                }
            });
        }

        // Initialize with all sections expanded on first load
        $(document).ready(function() {
            setTimeout(function() {
                initializeTableState();
            }, 1000); // Delay to ensure DataTable is fully loaded
        });

        // Re-initialize expansion state after DataTable redraw
        $('#profit-loss-table').on('draw.dt', function() {
            setTimeout(function() {
                initializeTableState();
            }, 100);
        });

        // Handle dynamic visibility of expand/collapse buttons
        function updateButtonVisibility() {
            let hasAnyChildren = $('.toggle-chevron').length > 0;
            if (hasAnyChildren) {
                $('#expand-all, #collapse-all').show();
            } else {
                $('#expand-all, #collapse-all').hide();
            }
        }

        // Call this after table draws
        $('#profit-loss-table').on('draw.dt', function() {
            setTimeout(function() {
                updateButtonVisibility();
            }, 150);
        });
    </script>

    <style>
        .profit-loss-table {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .profit-loss-table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
        }

        .profit-loss-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .profit-loss-table tbody tr.income-section {
            background-color: #e8f5e8;
        }

        .profit-loss-table tbody tr.expense-section {
            background-color: #ffeaea;
        }

        .text-right {
            text-align: right !important;
        }

        .section-header {
            font-weight: 700;
            font-size: 1.1em;
            color: #495057;
            text-transform: uppercase;
        }

        .section-total-amount {
            font-size: 1em;
            font-style: italic;
        }

        .toggle-section {
            user-select: none;
        }

        .toggle-section[style*="pointer"]:hover {
            color: #007bff;
        }

        .toggle-chevron {
            transition: transform 0.2s ease;
            color: #007bff;
            font-size: 12px;
        }

        .child-row {
            /* All child rows start visible */
        }

        .child-row td:first-child {
            padding-left: 30px !important;
        }

        .amount-cell {
            text-align: right;
            display: block;
        }

        .subtotal-row .total-amount {
            border-top: 1px solid #000;
            font-weight: bold;
        }

        .total-row .total-amount {
            border-top: 2px solid #000;
            border-bottom: 2px double #000;
            font-weight: bold;
        }

        .section-row {
            background-color: #f8f9fa !important;
            font-weight: bold;
        }

        .section-row:hover {
            background-color: #e9ecef !important;
        }

        /* Subtotal rows styling */
        .subtotal-row {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        /* Total rows styling */
        .total-row {
            background-color: #e9ecef;
            font-weight: bold;
            border-top: 2px solid #dee2e6;
        }

        .subtotal-label,
        .total-label {
            font-weight: bold;
        }
    </style>
@endpush
