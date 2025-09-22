@extends('layouts.app')

@push('datatable-styles')
    @include('sections.datatable_css')
@endpush

@section('filter-section')
<x-filters.filter-box>
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
    <div class="select-box d-flex p-2 border-right-grey border-right-grey-sm-0">
        <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.asOf')</p>
        <div class="select-status d-flex">
            <input type="text" class="position-relative text-dark form-control border-0 p-2 text-left f-14 f-w-500 border-additional-grey"
                id="asOfDate" placeholder="@lang('placeholders.date')">
        </div>
    </div>

    <x-slot name="action">
        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0">
            <x-forms.button-secondary class="btn-xs d-none d-lg-block d-md-block mr-2" id="expand-all" icon="expand-arrows-alt">
                Expand All
            </x-forms.button-secondary>
            
            <x-forms.button-secondary class="btn-xs d-none d-lg-block d-md-block mr-2" id="collapse-all" icon="compress-arrows-alt">
                Collapse All
            </x-forms.button-secondary>
            
            <x-forms.button-secondary class="btn-xs d-none d-lg-block d-md-block mr-2" id="reset-filters" icon="times-circle">
                @lang('app.clearFilters')
            </x-forms.button-secondary>
            
            <x-forms.button-secondary class="btn-xs d-none d-lg-block d-md-block mr-2" id="export-balance-sheet" icon="file-export">
                @lang('app.export')
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
                Balance Sheet - Standard
                <span class="text-lightest f-12 ml-2" id="as-of-date-display"></span>
            </h4>
        </div>

        <div class="table-responsive p-2">
            {!! $dataTable->table(['class' => 'table table-hover border-0 w-100 balance-sheet-standard-table']) !!}
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @include('sections.datatable_js')
    
    <script>
        $('#balance-sheet-standard-table').on('preXhr.dt', function (e, settings, data) {
            var asOfDate = $('#asOfDate').val();
            data.asOfDate = asOfDate || moment().format('{!! company()->moment_date_format !!}');
        });

        const showTable = () => {
            window.LaravelDataTables["balance-sheet-standard-table"].draw(false);
        }

        $('#asOfDate').daterangepicker({
            locale: daterangeLocale,
            singleDatePicker: true,
            showDropdowns: true,
            startDate: moment(),
        });

        $('#asOfDate').on('apply.daterangepicker', function (ev, picker) {
            $('#as-of-date-display').text('as of ' + picker.startDate.format('MMM DD, YYYY'));
            showTable();
        });

        $('#reset-filters').click(function () {
            $('#asOfDate').val('');
            $('#asOfDate').data('daterangepicker').setStartDate(moment());
            $('#as-of-date-display').text('as of ' + moment().format('MMM DD, YYYY'));
            showTable();
        });

        $('#export-balance-sheet').click(function() {
            var asOfDate = $('#asOfDate').val() || moment().format('YYYY-MM-DD');
            var url = '{{ route("balance-sheet-standard.export") }}?asOfDate=' + asOfDate;
            window.open(url, '_blank');
        });

        // Hierarchical Expand/Collapse functionality
        $(document).on('click', '.chevron-icon', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $icon = $(this);
            var parentType = $icon.data('parent-type');
            var parentId = $icon.data('parent-id');
            var $row = $icon.closest('tr');
            var isExpanded = $icon.hasClass('fa-chevron-down');
            
            console.log('Chevron clicked:', parentType, parentId, 'isExpanded:', isExpanded);
            
            if (isExpanded) {
                // Collapse
                $icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
                collapseHierarchy(parentType, parentId);
                showCollapsedBalance($row, parentType, parentId);
            } else {
                // Expand
                $icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
                expandHierarchy(parentType, parentId);
                hideCollapsedBalance($row);
            }
        });

        // Collapse hierarchy function
        function collapseHierarchy(parentType, parentId) {
            console.log('Collapsing:', parentType, parentId);
            
            if (parentType === 'subtype') {
                // Hide all children of this subtype
                var childrenSelector = '.child-of-subtype-' + parentId;
                console.log('Looking for children with selector:', childrenSelector);
                
                var $children = $(childrenSelector);
                console.log('Found', $children.length, 'children');
                
                $children.hide().addClass('collapsed');
            }
        }

        // Expand hierarchy function
        function expandHierarchy(parentType, parentId) {
            console.log('Expanding:', parentType, parentId);
            
            if (parentType === 'subtype') {
                // Show all children of this subtype
                var childrenSelector = '.child-of-subtype-' + parentId;
                console.log('Looking for children with selector:', childrenSelector);
                
                var $children = $(childrenSelector);
                console.log('Found', $children.length, 'children to expand');
                
                $children.show().removeClass('collapsed');
            }
        }

        // Show collapsed balance next to parent name
        function showCollapsedBalance($row, parentType, parentId) {
            var totalBalance = calculateTotalBalance(parentType, parentId);
            var $accountCell = $row.find('td').first();
            
            if (!$accountCell.find('.collapsed-balance').length && totalBalance !== null) {
                $accountCell.append(' <span class="collapsed-balance">(' + formatCurrency(totalBalance) + ')</span>');
            }
        }

        // Remove collapsed balance display
        function hideCollapsedBalance($row) {
            $row.find('.collapsed-balance').remove();
        }

        // Calculate total balance for collapsed sections
        function calculateTotalBalance(parentType, parentId) {
            var total = 0;
            var found = false;
            
            if (parentType === 'subtype') {
                // Look for the subtotal row that belongs to this subtype
                $('.subtotal-row.child-of-subtype-' + parentId).each(function() {
                    var balanceText = $(this).find('td').eq(1).text().trim(); // Amount column
                    console.log('Found subtotal balance text:', balanceText);
                    if (balanceText) {
                        total = parseFloat(balanceText.replace(/[^0-9.-]+/g, ''));
                        found = true;
                        return false; // Break loop
                    }
                });
            }
            
            console.log('Calculated balance:', total, 'found:', found);
            return found ? total : null;
        }

        // Format currency for display
        function formatCurrency(amount) {
            if (isNaN(amount)) return '0.00';
            return new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(Math.abs(amount));
        }

        // Global expand all functionality
        $('#expand-all').click(function() {
            console.log('Expand all clicked');
            $('.chevron-icon').removeClass('fa-chevron-right').addClass('fa-chevron-down');
            $('.child-row, .subtotal-row').show().removeClass('collapsed');
            $('.collapsed-balance').remove();
        });

        // Global collapse all functionality
        $('#collapse-all').click(function() {
            console.log('Collapse all clicked');
            $('.chevron-icon').removeClass('fa-chevron-down').addClass('fa-chevron-right');
            
            // Collapse at subtype level
            $('.chevron-icon[data-parent-type="subtype"]').each(function() {
                var subtypeId = $(this).data('parent-id');
                var $row = $(this).closest('tr');
                console.log('Collapsing subtype:', subtypeId);
                collapseHierarchy('subtype', subtypeId);
                showCollapsedBalance($row, 'subtype', subtypeId);
            });
        });

        // Initialize with collapsed state on load
        $(document).ready(function() {
            // Wait for DataTable to load
            setTimeout(function() {
                console.log('Auto-collapse on load');
                if ($('#balance-sheet-standard-table tbody tr').length > 0) {
                    $('#collapse-all').click();
                }
            }, 1500);
        });

        // Re-collapse after table reload
        $('#balance-sheet-standard-table').on('draw.dt', function() {
            setTimeout(function() {
                console.log('Re-collapse after table reload');
                $('#collapse-all').click();
            }, 500);
        });

        // Initialize date display
        $('#as-of-date-display').text('as of ' + moment().format('MMM DD, YYYY'));
    </script>

    <style>
        /* Enhanced hierarchical styles for Standard Balance Sheet */
        .balance-sheet-standard-table {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .balance-sheet-standard-table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            font-size: 14px;
        }
        
        .balance-sheet-standard-table tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.1);
        }
        
        /* Section Headers (ASSET, LIABILITY, EQUITY) */
        .balance-sheet-standard-table tbody tr.section-header-row {
            background-color: #e9ecef;
            font-weight: bold;
        }
        
        .balance-sheet-standard-table tbody tr.section-header-row:hover {
            background-color: #e9ecef;
        }
        
        /* Subtype Headers (Accounts Receivable, Cash, etc.) */
        .balance-sheet-standard-table tbody tr.subtype-header-row {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            font-weight: 600;
        }
        
        .balance-sheet-standard-table tbody tr.subtype-header-row:hover {
            background-color: #f1f3f4;
        }
        
        /* Individual Account Rows */
        .balance-sheet-standard-table tbody tr.child-row {
            background-color: #ffffff;
            border-left: 3px solid #007bff;
        }
        
        .balance-sheet-standard-table tbody tr.child-row:hover {
            background-color: #f8f9fa;
        }
        
        .balance-sheet-standard-table tbody tr.child-row.collapsed {
            display: none !important;
        }
        
        
        .balance-sheet-standard-table tbody tr.subtotal-row.collapsed {
            display: none !important;
        }
        
        /* Total rows */
        .balance-sheet-standard-table tbody tr.total-row {
            /* background-color: #d1ecf1; */
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            font-weight: 700;
        }
        
        /* .balance-sheet-standard-table tbody tr.total-row:hover {
            background-color: #d1ecf1;
        } */
        
        /* Chevron icons */
        .chevron-icon {
            transition: transform 0.2s ease;
            cursor: pointer;
            color: #007bff;
            font-size: 12px;
        }
        
        .chevron-icon:hover {
            color: #0056b3;
            transform: scale(1.1);
        }
        
        /* Collapsed balance display */
        .collapsed-balance {
            font-style: italic;
            font-size: 11px;
            color: #6c757d !important;
            margin-left: 10px;
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
        
        .text-right {
            text-align: right !important;
        }
        
        /* Professional spacing */
        .balance-sheet-standard-table tbody tr td {
            padding: 8px 12px;
            vertical-align: middle;
            border-top: 1px solid #f1f1f1;
        }
        
        .balance-sheet-standard-table tbody tr.section-header-row td {
            padding: 12px;
            font-weight: bold;
        }
        
        .balance-sheet-standard-table tbody tr:first-child td {
            border-top: none;
        }
        
        /* Hide borders on empty rows */
        .balance-sheet-standard-table tbody tr td:empty {
            border: none;
            padding: 4px;
        }
        
        /* Animation for smooth collapse/expand */
        .balance-sheet-standard-table tbody tr {
            transition: all 0.3s ease;
        }
        
        /* Print styles */
        @media print {
            .balance-sheet-standard-table {
                font-size: 11px;
            }
            
            .chevron-icon {
                display: none;
            }
            
            .collapsed-balance {
                display: none;
            }
            
            .balance-sheet-standard-table tbody tr.collapsed {
                display: table-row !important;
            }
            
            .balance-sheet-standard-table thead th {
                font-size: 10px;
                padding: 4px;
            }
            
            .balance-sheet-standard-table tbody tr td {
                padding: 3px 8px;
            }
        }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .chevron-icon {
                font-size: 14px;
            }
            
            .collapsed-balance {
                display: block;
                margin-top: 4px;
                margin-left: 0;
            }
            
            .balance-sheet-standard-table {
                font-size: 12px;
            }
        }
        
        /* Loading state */
        .balance-sheet-standard-table.loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        /* Additional debugging styles */
        .debug-border {
            border: 2px solid red !important;
        }
    </style>
@endpush