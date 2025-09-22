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
        <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.asOfDate')</p>
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
            
            <x-forms.button-secondary class="btn-xs d-none d-lg-block d-md-block mr-2" id="export-balance-sheet-detail" icon="file-export">
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
                Balance Sheet - Detail
                <span class="text-lightest f-12 ml-2" id="as-of-date-display"></span>
            </h4>
        </div>

        <div class="table-responsive p-2">
            {!! $dataTable->table(['class' => 'table table-hover border-0 w-100 balance-sheet-detail-table']) !!}
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @include('sections.datatable_js')
    <!-- DataTables core -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">

<!-- Buttons extension -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">

<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

<!-- Export dependencies -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

    <script>
        $('#balance-sheet-detail-table').on('preXhr.dt', function (e, settings, data) {
            var asOfDate = $('#asOfDate').val();
            data.asOfDate = asOfDate || moment().format('{!! company()->moment_date_format !!}');
        });

        const showTable = () => {
            window.LaravelDataTables["balance-sheet-detail-table"].draw(false);
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

        $('#export-balance-sheet-detail').click(function() {
            var asOfDate = $('#asOfDate').val() || moment().format('YYYY-MM-DD');
            var url = '{{ route("balance-sheet-detail.export") }}?asOfDate=' + asOfDate;
            window.open(url, '_blank');
        });

        // Enhanced Hierarchical Expand/Collapse functionality
        $(document).on('click', '.chevron-icon', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $icon = $(this);
            var parentType = $icon.data('parent-type');
            var parentId = $icon.data('parent-id');
            var $row = $icon.closest('tr');
            var isExpanded = $icon.hasClass('fa-chevron-down');
            
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

        // Fixed collapse hierarchy - now properly handles all children
        function collapseHierarchy(parentType, parentId) {
            if (parentType === 'subtype') {
                // Hide ALL children of this subtype (accounts, transactions, totals)
                $('.child-of-subtype-' + parentId).hide().addClass('collapsed');
                
                // Also collapse all account chevrons within this subtype
                $('.child-of-subtype-' + parentId).find('.chevron-icon[data-parent-type="account"]')
                    .removeClass('fa-chevron-down').addClass('fa-chevron-right');
            } else if (parentType === 'account') {
                // Hide all children of this account (transactions and totals)
                $('.child-of-account-' + parentId).hide().addClass('collapsed');
            }
        }

        // Fixed expand hierarchy - respects individual account states
        function expandHierarchy(parentType, parentId) {
            if (parentType === 'subtype') {
                // Show account headers and subtype totals first
                $('.child-of-subtype-' + parentId).each(function() {
                    var $element = $(this);
                    
                    // Always show account headers and subtype totals
                    if ($element.hasClass('account-header-row') || $element.hasClass('subtype-total-row')) {
                        $element.show().removeClass('collapsed');
                    }
                    // For transactions and account totals, only show if their account is expanded
                    else if ($element.hasClass('transaction-row') || $element.hasClass('account-total-row')) {
                        // Find which account this belongs to
                        var classes = $element.attr('class').split(' ');
                        var accountClass = classes.find(cls => cls.startsWith('child-of-account-'));
                        
                        if (accountClass) {
                            var accountId = accountClass.replace('child-of-account-', '');
                            var $accountChevron = $('.chevron-icon[data-parent-type="account"][data-parent-id="' + accountId + '"]');
                            
                            // Only show if the account chevron is expanded
                            if ($accountChevron.length && $accountChevron.hasClass('fa-chevron-down')) {
                                $element.show().removeClass('collapsed');
                            }
                            // Keep hidden if account is collapsed
                        }
                    }
                });
            } else if (parentType === 'account') {
                // Show all children of this account
                $('.child-of-account-' + parentId).show().removeClass('collapsed');
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
                // Find the subtype total row
                $('.subtype-total-row').each(function() {
                    if ($(this).hasClass('child-of-subtype-' + parentId)) {
                        var balanceText = $(this).find('td').eq(9).text().trim(); // Balance column
                        if (balanceText) {
                            total = parseFloat(balanceText.replace(/[^0-9.-]+/g, ''));
                            found = true;
                            return false; // Break loop
                        }
                    }
                });
            } else if (parentType === 'account') {
                // Find the account total row
                $('.account-total-row').each(function() {
                    if ($(this).hasClass('child-of-account-' + parentId)) {
                        var balanceText = $(this).find('td').eq(9).text().trim(); // Balance column
                        if (balanceText) {
                            total = parseFloat(balanceText.replace(/[^0-9.-]+/g, ''));
                            found = true;
                            return false; // Break loop
                        }
                    }
                });
            }
            
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
            $('.chevron-icon').removeClass('fa-chevron-right').addClass('fa-chevron-down');
            $('.transaction-row, .account-total-row, .account-header-row, .subtype-total-row').show().removeClass('collapsed');
            $('.collapsed-balance').remove();
        });

        // Global collapse all functionality
        $('#collapse-all').click(function() {
            $('.chevron-icon').removeClass('fa-chevron-down').addClass('fa-chevron-right');
            
            // Collapse at subtype level - hide all accounts, transactions, and totals
            $('.chevron-icon[data-parent-type="subtype"]').each(function() {
                var subtypeId = $(this).data('parent-id');
                var $row = $(this).closest('tr');
                collapseHierarchy('subtype', subtypeId);
                showCollapsedBalance($row, 'subtype', subtypeId);
            });
        });

        // Initialize with collapsed state on load
        $(document).ready(function() {
            // Wait for DataTable to load
            setTimeout(function() {
                if ($('#balance-sheet-detail-table tbody tr').length > 0) {
                    $('#collapse-all').click();
                }
            }, 1500);
        });

        // Re-collapse after table reload
        $('#balance-sheet-detail-table').on('draw.dt', function() {
            setTimeout(function() {
                $('#collapse-all').click();
            }, 500);
        });

        // Initialize date display
        $('#as-of-date-display').text('as of ' + moment().format('MMM DD, YYYY'));
    </script>

    <style>
        /* Enhanced hierarchical styles */
        #balance-sheet-detail-table td {
            padding: 6px 8px;
            vertical-align: middle;
        }
        
        #balance-sheet-detail-table .text-right {
            text-align: right;
        }
        
        #balance-sheet-detail-table strong {
            font-weight: bold;
        }
        
        #balance-sheet-detail-table h4 {
            margin: 0;
            padding-top: 8px;
        }

        .balance-sheet-detail-table {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .balance-sheet-detail-table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            font-size: 14px;
        }
        
        /* Main Type Headers (Asset, Liability, Equity) */
        .balance-sheet-detail-table tbody tr.type-header-row {
            background-color: #e9ecef;
            font-weight: bold;
        }
        
        .balance-sheet-detail-table tbody tr.type-header-row:hover {
            background-color: #e9ecef;
        }
        
        /* Sub Type Headers (Accounts Receivable, Cash, etc.) */
        .balance-sheet-detail-table tbody tr.subtype-header-row {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            font-weight: 600;
        }
        
        .balance-sheet-detail-table tbody tr.subtype-header-row:hover {
            background-color: #f1f3f4;
        }
        
        /* Account Headers (1100 - Accounts Receivable, etc.) */
        .balance-sheet-detail-table tbody tr.account-header-row {
            background-color: #ffffff;
            border-left: 3px solid #007bff;
            font-weight: 500;
        }
        
        .balance-sheet-detail-table tbody tr.account-header-row:hover {
            background-color: #f8f9fa;
        }
        
        .balance-sheet-detail-table tbody tr.account-header-row.collapsed {
            display: none !important;
        }
        
        /* Transaction rows */
        .balance-sheet-detail-table tbody tr.transaction-row {
            background-color: #f9f9f9;
            font-size: 12px;
            color: #666;
            border-left: 3px solid #28a745;
        }
        
        .balance-sheet-detail-table tbody tr.transaction-row:hover {
            background-color: #f0f0f0;
        }
        
        .balance-sheet-detail-table tbody tr.transaction-row.collapsed {
            display: none !important;
        }
        
        /* Account total rows */
        .balance-sheet-detail-table tbody tr.account-total-row {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            border-left: 3px solid #007bff;
            font-weight: 600;
        }
        
        .balance-sheet-detail-table tbody tr.account-total-row:hover {
            background-color: #f8f9fa;
        }
        
        .balance-sheet-detail-table tbody tr.account-total-row.collapsed {
            display: none !important;
        }
        
        /* Subtype total rows */
        .balance-sheet-detail-table tbody tr.subtype-total-row {
            background-color: #e9ecef;
            border-top: 2px solid #dee2e6;
            font-weight: 700;
        }
        
        .balance-sheet-detail-table tbody tr.subtype-total-row:hover {
            background-color: #e9ecef;
        }
        
        .balance-sheet-detail-table tbody tr.subtype-total-row.collapsed {
            display: none !important;
        }
        
        /* Type total rows */
        .balance-sheet-detail-table tbody tr.type-total-row {
            /* background-color: #d1ecf1; */
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            font-weight: 700;
        }
        
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
        
        .fa-chevron-right {
            transform: rotate(0deg);
        }
        
        .fa-chevron-down {
            transform: rotate(0deg);
        }
        
        /* Collapsed balance display */
        .collapsed-balance {
            font-style: italic;
            font-size: 11px;
            color: #6c757d !important;
            margin-left: 10px;
        }
        
        /* Indentation levels */
        .balance-sheet-detail-table tbody tr td:first-child {
            padding-left: 12px;
        }
        
        /* Amount cell formatting */
        .amount-cell {
            text-align: right;
            font-family: 'Courier New', monospace;
            font-size: 11px;
        }
        
        /* Debit/Credit coloring */
        .transaction-debit {
            color: #dc3545;
            font-weight: 500;
        }
        
        .transaction-credit {
            color: #28a745;
            font-weight: 500;
        }
        
        /* Hover effects */
        .balance-sheet-detail-table tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.1);
        }
        
        /* Hide empty cells */
        .balance-sheet-detail-table tbody tr td:empty {
            border: none;
            padding: 4px;
        }
        
        /* Professional spacing */
        .balance-sheet-detail-table tbody tr td {
            border-top: 1px solid #f1f1f1;
        }
        
        .balance-sheet-detail-table tbody tr:first-child td {
            border-top: none;
        }
        
        /* Animation for smooth collapse/expand */
        .balance-sheet-detail-table tbody tr {
            transition: all 0.3s ease;
        }
        
        /* Print styles */
        @media print {
            .balance-sheet-detail-table {
                font-size: 10px;
            }
            
            .chevron-icon {
                display: none;
            }
            
            .collapsed-balance {
                display: none;
            }
            
            .balance-sheet-detail-table tbody tr.collapsed {
                display: table-row !important;
            }
            
            .balance-sheet-detail-table thead th {
                font-size: 9px;
                padding: 4px;
            }
            
            .balance-sheet-detail-table tbody tr td {
                padding: 2px 6px;
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
            
            .balance-sheet-detail-table {
                font-size: 12px;
            }
        }
        
        /* Loading state */
        .balance-sheet-detail-table.loading {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
@endpush