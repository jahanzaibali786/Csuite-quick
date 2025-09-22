@extends('layouts.app')

@push('datatable-styles')
    @include('sections.datatable_css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
@endpush

@section('filter-section')
    <style>
        .content-wrapper {
            background: #fff !important;
            padding: 10px 100px 100px !important;
        }

        .dt-buttons {
            position: fixed !important;
            top: 11% !important;
            right: 10px !important;
            z-index: 1 !important;
        }

        .table-scroll {
            max-height: 600px;
            overflow: auto;
            position: relative;
        }

        .balance-sheet-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            table-layout: auto;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .balance-sheet-table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
            padding: 12px 8px;
        }

        /* Row Styling */
        .balance-sheet-table tbody tr {
            background-clip: padding-box;
            transition: all 0.2s ease;
        }

        .balance-sheet-table tbody tr:hover {
            background-color: #f8f9fa !important;
        }

        /* Section header rows */
        .section-header-row {
            background-color: #e9ecef !important;
            font-weight: bold;
            border-top: 2px solid #adb5bd;
            border-bottom: 1px solid #dee2e6;
        }

        .section-header-row td {
            padding: 12px 8px !important;
            background-color: #e9ecef !important;
        }

        .section-header {
            color: #495057;
            font-size: 14px;
            letter-spacing: 0.5px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .section-total {
            font-weight: 700 !important;
            font-size: 14px;
            color: #495057;
        }

        /* Detail account rows */
        .account-detail {
            background-color: #ffffff;
        }

        .account-detail.hidden-row {
            display: none !important;
        }

        .account-detail td {
            padding: 8px 8px 8px 30px !important;
            border-bottom: 1px solid #f1f1f1;
        }

        .account-name {
            color: #495057;
            font-size: 13px;
        }

        /* Subtotal rows */
        .subtotal-row {
            background-color: #f8f9fa !important;
            border-top: 1px solid #dee2e6;
            border-bottom: 1px solid #dee2e6;
        }

        .subtotal-row.hidden-row {
            display: none !important;
        }

        .subtotal-row td>.indent-spacer {
            display: none;
        }

        .subtotal-row td {
            padding: 10px 8px 10px 30px !important;
            font-weight: 600;
        }
        tr[data-row-id='net-profit'] td .subtotal-label {
            margin-left: -20px;
        }
        .subtotal-label {
            font-weight: 700;
            color: #007bff;
        
            font-size: 14px;
        }

        .subtotal-amount {
            font-weight: 700;
            color: #007bff;
            font-size: 14px;
            border-top: 1px solid #000;
        }

        /* Total row */
        .total-row {
            background-color: #f1f3f4 !important;
            border-top: 3px solid #6c757d;
            border-bottom: 3px solid #6c757d;
        }

        .total-row td {
            padding: 15px 8px !important;
            font-weight: bold;
        }

        .total-label {
            font-weight: 700;
            color: #212529;
            font-size: 14px;
            text-transform: uppercase;
        }

        .total-amount {
            font-weight: 700;
            color: #212529;
            border-top: 2px solid #000;
            border-bottom: 3px double #000;
            padding-top: 2px;
            font-size: 14px;
        }

        /* Toggle button styling */
        .toggle-btn {
            cursor: pointer;
            margin-right: 8px;
            display: inline-block;
            width: 16px;
            text-align: center;
            transition: all 0.2s ease;
            transform: rotate(90deg);
        }

        .toggle-btn:hover {
            color: #007bff;
        }

        .toggle-icon {
            font-size: 12px;
            color: #6c757d;
            transition: all 0.2s ease;
        }

        .toggle-btn.expanded .toggle-icon {
            color: #007bff;
        }

        .toggle-btn.collapsed .fa-chevron-right:before {
            content: "\f054";
            /* fa-chevron-right */
        }

        .toggle-btn.expanded .fa-chevron-right:before {
            content: "\f078";
            /* fa-chevron-down */
        }

        /* Indentation */
        .indent-spacer {
            display: inline-block;
            width: 20px;
        }

        /* Amount styling */
        .amount-cell {
            color: #495057;
            font-weight: 500;
        }

        /* Text alignment */
        .text-right {
            text-align: right !important;
        }

        /* Expand/Collapse control buttons in header */
        .expand-collapse-controls {
            gap: 0;
        }

        .expand-collapse-controls .btn {
            font-size: 12px;
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .expand-collapse-controls .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .expand-collapse-controls .btn i {
            font-size: 10px;
            margin-right: 4px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .content-wrapper {
                padding: 15px !important;
            }

            .account-detail td {
                padding: 6px 4px 6px 20px !important;
                font-size: 13px;
            }

            .toggle-btn {
                margin-right: 4px;
            }

            .expand-collapse-controls {
                margin-top: 10px;
                justify-content: flex-start !important;
            }

            .expand-collapse-controls .btn {
                font-size: 11px;
                padding: 4px 8px;
            }
        }

        /* Expand/Collapse animation */
        .balance-sheet-table tbody tr {
            opacity: 1;
            transform: translateY(0);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .balance-sheet-table tbody tr.hidden-row {
            opacity: 0;
            transform: translateY(-10px);
        }

        /* Loading state */
        .dataTables_processing {
            background: rgba(255, 255, 255, 0.9) !important;
            color: #6c757d !important;
            border: 1px solid #dee2e6 !important;
        }

        /* Hide borders on empty rows */
        .balance-sheet-table tbody tr td:empty {
            border: none;
            padding: 4px;
        }

        /* Enhanced hover effects */
        .table-hover-effect {
            background-color: #f8f9fa !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Print styles */
        @media print {

            .toggle-btn,
            .dt-buttons,
            .filter-section,
            .expand-collapse-controls {
                display: none !important;
            }

            .balance-sheet-table tbody tr.hidden-row {
                display: table-row !important;
            }

            .content-wrapper {
                padding: 0 !important;
            }
        }
    </style>

    <x-filters.filter-box>
        <div class="select-box d-flex p-2 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.asOfDate')</p>
            <div class="select-status d-flex">
                <input type="text"
                    class="position-relative text-dark form-control border-0 p-2 text-left f-14 f-w-500 border-additional-grey"
                    id="asOfDatePicker" placeholder="@lang('placeholders.date')">
            </div>
        </div>

        <x-slot name="action">
            <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0">
                <x-forms.button-secondary class="btn-xs d-none d-lg-block d-md-block mr-2" id="reset-filters"
                    icon="times-circle">
                    @lang('app.clearFilters')
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
                <h4 class="heading-h4 mb-0 d-flex align-items-center">
                    Balance Sheet
                    <span class="text-lightest f-12 ml-2" id="as-of-date-display"></span>
                </h4>
                <div class="d-flex align-items-center expand-collapse-controls">
                    <button type="button" title="Expand All" class="btn btn-sm btn-outline-primary mr-2" id="expand-all-header">
                        <i class="fa fa-expand"></i>
                    </button>
                    <button type="button" title="Collapse All" class="btn btn-sm btn-outline-secondary" id="collapse-all-header">
                        <i class="fa fa-compress"></i>
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                {!! $dataTable->table(['class' => 'table table-hover border-0 w-100 balance-sheet-table']) !!}
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('sections.datatable_js')

    <script>
        let balanceSheetTable;
        let expandedSections = new Set(); // Track which sections are expanded

        // Initialize DataTable
        $(document).ready(function() {
            balanceSheetTable = window.LaravelDataTables["balance-sheet-table"];

            // Initially collapse all detail rows
            initializeExpandedState();
        });

        // Push extra filters to DataTable Ajax
        $('#balance-sheet-table').on('preXhr.dt', function(e, settings, data) {
            var asOfDate = $('#asOfDatePicker').val();
            data.asOfDate = asOfDate || moment().format('{!! company()->moment_date_format !!}');
        });

        // Initialize collapsed state after table draw
        $('#trial-balance-table').on('draw.dt', function() {
            initializeExpandedState();
            restoreExpandedState();
        });

        function initializeExpandedState() {
            // Hide all child rows initially
            $('#trial-balance-table tbody tr.child-row').removeClass('hidden-row');
            $('.toggle-btn').removeClass('expanded').addClass('collapsed');
        }

        function restoreExpandedState() {
            // Restore previously expanded sections
            expandedSections.forEach(function(sectionId) {
                expandSection(sectionId, false); // false = don't add to Set again
            });
        }

        function expandSection(sectionId, updateSet = true) {
            const $toggleBtn = $(`.toggle-btn[data-target="${sectionId}"]`);
            const $childRows = $(`.parent-${sectionId}`);

            // Show child rows with animation
            $childRows.removeClass('hidden-row');

            // Update toggle button state
            $toggleBtn.removeClass('collapsed').addClass('expanded');
            $toggleBtn.css('transform', 'rotate(90deg)'); // ▼

            // Track expanded state
            if (updateSet) {
                expandedSections.add(sectionId);
            }

        }

        function collapseSection(sectionId, updateSet = true) {
            const $toggleBtn = $(`.toggle-btn[data-target="${sectionId}"]`);
            const $childRows = $(`.parent-${sectionId}`);

            // Hide child rows
            $childRows.addClass('hidden-row');

            // Update toggle button state
            $toggleBtn.removeClass('expanded').addClass('collapsed');
            $toggleBtn.css('transform', 'rotate(0deg)'); // ►

            // Remove from expanded state
            if (updateSet) {
                expandedSections.delete(sectionId);
            }
        }

        // Toggle section expansion on click
        $(document).on('click', '.toggle-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const sectionId = $(this).data('target');
            const isExpanded = $(this).hasClass('expanded');

            if (isExpanded) {
                collapseSection(sectionId);
            } else {
                expandSection(sectionId);
            }
        });

        // Expand all sections
        $('#expand-all-header').on('click', function() {
            $('.toggle-btn').each(function() {
                const sectionId = $(this).data('target');
                expandSection(sectionId);
            });
        });

        // Collapse all sections
        $('#collapse-all-header').on('click', function() {
            $('.toggle-btn').each(function() {
                const sectionId = $(this).data('target');
                collapseSection(sectionId);
            });
        });

        const showTable = () => {
            balanceSheetTable.draw(false);
        }

        $('#asOfDatePicker').daterangepicker({
            locale: daterangeLocale,
            singleDatePicker: true,
            startDate: moment(),
            showDropdowns: true
        });

        $('#asOfDatePicker').on('apply.daterangepicker', function(ev, picker) {
            $('#as-of-date-display').text('As of ' + picker.startDate.format('MMM DD, YYYY'));
            showTable();
        });

        $('#reset-filters').click(function() {
            $('#asOfDatePicker').val('');
            $('#asOfDatePicker').data('daterangepicker').setStartDate(moment());
            $('#as-of-date-display').text('As of ' + moment().format('MMM DD, YYYY'));

            // Clear expanded sections
            expandedSections.clear();

            showTable();
        });

        // Initialize date display
        $('#as-of-date-display').text('As of ' + moment().format('MMM DD, YYYY'));

        // Enhanced row hover effects
        $(document).on('mouseenter', '#balance-sheet-table tbody tr', function() {
            $(this).addClass('table-hover-effect');
        }).on('mouseleave', '#balance-sheet-table tbody tr', function() {
            $(this).removeClass('table-hover-effect');
        });

        // Double-click to toggle (alternative interaction)
        $(document).on('dblclick', '.section-header-row', function() {
            const $toggleBtn = $(this).find('.toggle-btn');
            if ($toggleBtn.length) {
                $toggleBtn.trigger('click');
            }
        });

        // Keyboard navigation support
        $(document).on('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 'e':
                    case 'E':
                        e.preventDefault();
                        $('#expand-all-header').trigger('click');
                        break;
                    case 'c':
                    case 'C':
                        e.preventDefault();
                        $('#collapse-all-header').trigger('click');
                        break;
                }
            }
        });

        // Context menu for additional options (right-click)
        $(document).on('contextmenu', '.section-header-row', function(e) {
            e.preventDefault();

            const $row = $(this);
            const $toggleBtn = $row.find('.toggle-btn');
            const sectionId = $toggleBtn.data('target');
            const isExpanded = $toggleBtn.hasClass('expanded');

            // Simple context action - toggle expansion
            if (isExpanded) {
                collapseSection(sectionId);
            } else {
                expandSection(sectionId);
            }
        });

        // Export functionality enhancement
        $(document).on('click', '.dt-button', function() {
            // Temporarily expand all sections for complete export
            const wasCollapsed = [];
            $('.toggle-btn.collapsed').each(function() {
                const sectionId = $(this).data('target');
                wasCollapsed.push(sectionId);
                expandSection(sectionId, false);
            });

            // After a short delay, restore the previous state
            setTimeout(function() {
                wasCollapsed.forEach(function(sectionId) {
                    collapseSection(sectionId, false);
                });
            }, 1000);
        });

        // Responsive mobile handling
        function handleMobileView() {
            const isMobile = $(window).width() < 768;

            if (isMobile) {
                // On mobile, show more compact view
                $('#balance-sheet-table').addClass('mobile-view');

                // Auto-collapse all on mobile for better performance
                if ($('.toggle-btn.expanded').length > 1) {
                    $('#collapse-all-header').trigger('click');
                }
            } else {
                $('#balance-sheet-table').removeClass('mobile-view');
            }
        }

        // Handle window resize
        $(window).on('resize', function() {
            handleMobileView();
        });

        // Initial mobile check
        handleMobileView();
    </script>

    <style>
        /* Additional mobile styles for balance sheet */
        @media (max-width: 767px) {
            .mobile-view .account-detail td {
                font-size: 12px !important;
                padding: 4px 2px 4px 15px !important;
            }

            .mobile-view .toggle-btn {
                margin-right: 2px;
            }

            .mobile-view .indent-spacer {
                width: 10px;
            }

            .expand-collapse-controls {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 5px;
            }

            .expand-collapse-controls .btn {
                font-size: 11px;
                padding: 4px 8px;
                width: 100px;
            }
        }

        /* Loading animation for row transitions */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .balance-sheet-table tbody tr:not(.hidden-row) {
            animation: fadeInUp 0.3s ease-out;
        }
    </style>
@endpush
