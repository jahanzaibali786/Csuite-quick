@extends('layouts.admin')

@section('content')
    <style>
        .section-row {
            background-color: #f2f2f2 !important;
            font-weight: bold;
        }

        .balance-sheet-table {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .balance-sheet-table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
        }

        .balance-sheet-table tbody tr:hover {
            background-color: #f8f9fa;
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
            display: none;
        }

        .toggle-section {
            user-select: none;
            cursor: pointer;
        }

        .toggle-section:hover {
            color: #007bff;
        }

        .toggle-chevron {
            transition: transform 0.2s ease;
            color: #007bff;
            font-size: 12px;
            margin-right: 8px;
            cursor: pointer;
        }

        .child-row {
            display: none;
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

        .section-header-row {
            background-color: #f8f9fa !important;
            font-weight: bold;
            cursor: pointer;
        }

        .section-header-row:hover {
            background-color: #e9ecef !important;
        }

        .subtotal-row {
            background-color: #f8f9fa;
            font-weight: bold;
            display: none;
        }

        .total-row {
            background-color: #e9ecef;
            font-weight: bold;
            border-top: 2px solid #dee2e6;
        }

        .subtotal-label,
        .total-label {
            font-weight: bold;
        }

        /* Expanded state */
        .section-expanded .child-row {
            display: table-row !important;
        }

        .section-expanded .subtotal-row {
            display: table-row !important;
        }

        .section-expanded .section-total-amount {
            display: none !important;
        }

        /* Compact view */
        .compact-view .child-row {
            display: none !important;
        }

        .compact-view .subtotal-row {
            display: none !important;
        }

        .compact-view .section-total-amount {
            display: inline-block !important;
        }

        /* Filter Controls */
        .filter-controls {
            background: white;
            padding: 20px 24px;
            border-bottom: 1px solid #e6e6e6;
        }

        .filter-item {
            display: flex;
            flex-direction: column;
            min-width: 140px;
        }

        .filter-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .form-control {
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 13px;
            background: white;
            color: #262626;
            height: 36px;
        }

        .form-control:focus {
            outline: none;
            border-color: #0969da;
            box-shadow: 0 0 0 2px rgba(9, 105, 218, 0.1);
        }

        .view-options {
            display: flex;
            align-items: center;
            position: relative;
        }

        .btn-view-options {
            background: transparent;
            color: #6b7280;
            border: 1px solid #d1d5db;
            padding: 8px 12px;
            font-size: 13px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .btn-view-options:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        /* View Options Dropdown */
        .view-options-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: none;
            min-width: 200px;
        }

        .view-option-item {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            font-size: 13px;
        }

        .view-option-item:last-child {
            border-bottom: none;
        }

        .view-option-item:hover {
            background: #f9fafb;
        }

        .view-option-item.divider {
            border-top: 1px solid #e5e7eb;
            margin-top: 4px;
            padding-top: 8px;
        }

        .view-option-item .checkmark {
            margin-right: 8px;
            color: #10b981;
            width: 16px;
            visibility: hidden;
        }

        .view-option-item.active .checkmark {
            visibility: visible;
        }

        /* Action buttons row */
        .action-buttons-row {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-outline {
            background: white;
            border: 1px solid #d1d5db;
            color: #374151;
            padding: 8px 12px;
            font-size: 13px;
            cursor: pointer;
        }

        .btn-outline:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            overflow-y: auto;
        }

        .general-options-modal,
        .filter-modal {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            width: 360px;
            background: white;
            box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid #e6e6e6;
            background: #f9fafb;
        }

        .modal-header h5 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #262626;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-close {
            background: none;
            border: none;
            font-size: 20px;
            color: #6b7280;
            cursor: pointer;
            padding: 4px;
            line-height: 1;
        }

        .btn-close:hover {
            color: #262626;
        }

        .modal-content {
            padding: 24px;
        }

        .modal-subtitle {
            color: #6b7280;
            font-size: 13px;
            margin: 0 0 24px;
        }

        /* Option Sections */
        .option-section {
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #262626;
            margin: 0 0 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
        }

        .option-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #374151;
            cursor: pointer;
            margin: 0;
        }

        .checkbox-label input[type="checkbox"] {
            margin: 0;
            width: 16px;
            height: 16px;
        }

        .negative-format-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .negative-format-group select {
            width: 80px;
            flex-shrink: 0;
        }

        .alignment-group {
            margin-top: 12px;
        }

        .alignment-label {
            display: block;
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .report-title-section {
            text-align: center;
            padding: 32px 24px 24px;
            border-bottom: 1px solid #e6e6e6;
        }

        .report-title {
            font-size: 24px;
            font-weight: 700;
            color: #262626;
            margin: 0 0 8px;
        }

        .company-name {
            font-size: 16px;
            color: #6b7280;
            margin: 0 0 12px;
        }

        .date-range {
            font-size: 14px;
            color: #374151;
            margin: 0;
        }

        /* Filter modal specific styles */
        .filter-section {
            margin-bottom: 24px;
        }

        .filter-section-title {
            font-size: 14px;
            font-weight: 600;
            color: #262626;
            margin: 0 0 16px;
        }

        .date-filter-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .date-filter-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .date-filter-label {
            font-size: 12px;
            color: #6b7280;
            font-weight: 500;
        }
    </style>

    <!-- Filter Controls -->
    <div class="filter-controls">
        <div class="filter-row">
            <div class="filter-group row mb-2">
                <div class="filter-item col-md-2">
                    <label class="filter-label">As of date</label>
                    <input type="text" id="asOfDatePicker" class="form-control date-input"
                        value="{{ \Carbon\Carbon::now()->format('M d, Y') }}">
                    <input type="hidden" id="asOfDate" name="asOfDate"
                        value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}">
                </div>

                <div class="filter-item col-md-2">
                    <label class="filter-label">Accounting method</label>
                    <select id="accounting-method" class="form-control">
                        <option value="accrual" selected>Accrual</option>
                        <option value="cash">Cash</option>
                    </select>
                </div>

                <div class="filter-item col-md-3 pt-4">
                    <div class="view-options">
                        <button class="btn btn-view-options" id="view-options-btn" type="button">
                            <i class="fa fa-eye"></i> &nbsp; View options
                        </button>
                        <div class="view-options-dropdown" id="view-options-dropdown">
                            <div class="view-option-item" data-value="normal">
                                <span class="checkmark"><i class="fa fa-check"></i></span>Normal view
                            </div>
                            <div class="view-option-item" data-value="compact">
                                <span class="checkmark"><i class="fa fa-check"></i></span>Compact view
                            </div>
                            <div class="view-option-item divider" data-value="expand">
                                <span class="checkmark"><i class="fa fa-check"></i></span>Expand all
                            </div>
                            <div class="view-option-item" data-value="collapse">
                                <span class="checkmark"><i class="fa fa-check"></i></span>Collapse all
                            </div>
                        </div>
                    </div>
                </div>
                <div class="filter-item col-md-3 pt-4">
                    <button class="btn btn-outline" id="general-options-btn" type="button">
                        <i class="fa fa-cog"></i> General options
                    </button>
                </div>
                <div class="filter-item col-md-2 pt-4">
                    <button class="btn btn-outline" id="filter-btn" type="button">
                        <i class="fa fa-filter"></i> Filter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="content-wrapper">
        <div class="d-flex flex-column w-tables rounded mt-3 bg-white" id="report-content">
            <div class="report-title-section p-2">
                <h2 class="report-title">Balance Sheet</h2>
                <p class="date-range">
                    <span class="text-lightest f-12 ml-2" id="as-of-date-display"></span>
                </p>
            </div>
            <div class="table-responsive">
                {!! $dataTable->table([
                    'class' => 'table table-hover border-0 w-100 balance-sheet-table',
                    'id' => 'balance-sheet-table',
                ]) !!}
            </div>
        </div>
    </div>

    <!-- Filter Modal -->
    <div class="modal-overlay" id="filter-overlay">
        <div class="filter-modal">
            <div class="modal-header">
                <h5>Filter Options <i class="fa fa-filter" title="Configure filters"></i></h5>
                <button type="button" class="btn-close" id="close-filter">&times;</button>
            </div>
            <div class="modal-content">
                <p class="modal-subtitle">Configure as-of date and accounting method for your balance sheet.</p>

                <!-- Date section -->
                <div class="filter-section">
                    <h6 class="filter-section-title">Balance Sheet Date</h6>
                    <div class="date-filter-group">
                        <div class="date-filter-item">
                            <label class="date-filter-label">As of Date</label>
                            <input type="text" id="modal-as-of-date" class="form-control date-input"
                                value="{{ Carbon\Carbon::now()->format('M d, Y') }}">
                        </div>

                        <div class="date-filter-item">
                            <label class="date-filter-label">Accounting Method</label>
                            <select id="modal-accounting-method" class="form-control">
                                <option value="accrual" selected>Accrual</option>
                                <option value="cash">Cash</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Apply button -->
                <div class="filter-section">
                    <button class="btn btn-success w-100" id="apply-filters">
                        Apply Filters
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- General Options Modal -->
    <div class="modal-overlay" id="general-options-overlay">
        <div class="general-options-modal">
            <div class="modal-header">
                <h5>General options <i class="fa fa-info-circle" title="Configure report settings"></i></h5>
                <button type="button" class="btn-close" id="close-general-options">&times;</button>
            </div>
            <div class="modal-content">
                <p class="modal-subtitle">Select general options for your report.</p>

                <!-- Number format section -->
                <div class="option-section">
                    <h6 class="section-title">Number format <i class="fa fa-chevron-up"></i></h6>
                    <div class="option-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="divide-by-1000"> Divide by 1000
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" id="hide-zero-amounts"> Don't show zero amounts
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" id="round-whole-numbers"> Round to the nearest whole number
                        </label>
                    </div>
                </div>

                <!-- Negative numbers section -->
                <div class="option-section">
                    <h6 class="section-title">Negative numbers</h6>
                    <div class="option-group">
                        <div class="negative-format-group">
                            <select id="negative-format" class="form-control">
                                <option value="-100" selected>-100</option>
                                <option value="(100)">(100)</option>
                                <option value="100-">100-</option>
                            </select>
                            <label class="checkbox-label">
                                <input type="checkbox" id="show-in-red"> Show in red
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Header section -->
                <div class="option-section">
                    <h6 class="section-title">Header <i class="fa fa-chevron-up"></i></h6>
                    <div class="option-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="company-logo"> Company logo
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" id="report-period" checked> Report period
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" id="company-name" checked> Company name
                        </label>
                    </div>
                    <div class="alignment-group">
                        <label class="alignment-label">Header alignment</label>
                        <select id="header-alignment" class="form-control">
                            <option value="center" selected>Center</option>
                            <option value="left">Left</option>
                            <option value="right">Right</option>
                        </select>
                    </div>
                </div>

                <!-- Footer section -->
                <div class="option-section">
                    <h6 class="section-title">Footer <i class="fa fa-chevron-up"></i></h6>
                    <div class="option-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="date-prepared" checked> Date prepared
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" id="time-prepared" checked> Time prepared
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" id="report-basis" checked> Report basis (cash vs. accrual)
                        </label>
                    </div>
                    <div class="alignment-group">
                        <label class="alignment-label">Footer alignment</label>
                        <select id="footer-alignment" class="form-control">
                            <option value="center" selected>Center</option>
                            <option value="left">Left</option>
                            <option value="right">Right</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
    @include('sections.datatable_js')

    <script>
        let balanceSheetTable;
        let expandedSections = new Set();

        // Initialize global state - default to expanded
        window.viewState = {
            viewType: 'normal', // 'normal' or 'compact'
            expandState: 'expand' // default to expand all
        };

        window.reportOptions = {
            divideBy1000: false,
            hideZeroAmounts: false,
            roundWholeNumbers: false,
            negativeFormat: '-100',
            showInRed: false,
            companyLogo: false,
            reportPeriod: true,
            companyName: true,
            headerAlignment: 'center',
            datePrepared: true,
            timePrepared: true,
            reportBasis: true,
            footerAlignment: 'center'
        };

        $(document).ready(function() {
            // Get DataTable instance
            balanceSheetTable = window.LaravelDataTables && window.LaravelDataTables["balance-sheet-table"];

            if (!balanceSheetTable) {
                console.warn('DataTable instance "balance-sheet-table" not found.');
            }

            initializeDatePickers();
            initializeModalHandlers();
            initializeViewOptions();
            setupDataTableEvents();

            // Initial display update
            updateAsOfDateDisplay();

            // Ensure default expanded on first load
            applyViewState();

            // Close dropdowns when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.view-options').length) {
                    $('#view-options-dropdown').hide();
                }
            });
        });

        function initializeDatePickers() {
            // Main as-of date picker
            $('#asOfDatePicker').daterangepicker({
                singleDatePicker: true,
                showDropdowns: true,
                locale: {
                    format: 'MMM DD, YYYY'
                },
                startDate: moment($('#asOfDate').val() || undefined)
            }, function(chosen) {
                $('#asOfDate').val(chosen.format('YYYY-MM-DD'));
                updateAsOfDateDisplay();
                refreshTable();
            });

            // Modal as-of date picker - initialize with same date as main
            $('#modal-as-of-date').daterangepicker({
                singleDatePicker: true,
                showDropdowns: true,
                locale: {
                    format: 'MMM DD, YYYY'
                },
                startDate: moment($('#asOfDate').val() || undefined)
            });

            // If user changes date inside modal, immediately reflect the outside display (live preview)
            $('#modal-as-of-date').on('apply.daterangepicker', function(ev, picker) {
                const chosen = picker.startDate;
                // Update main picker and hidden value
                if ($('#asOfDatePicker').data('daterangepicker')) {
                    $('#asOfDatePicker').data('daterangepicker').setStartDate(chosen);
                } else {
                    $('#asOfDatePicker').val(chosen.format('MMM DD, YYYY'));
                }
                $('#asOfDate').val(chosen.format('YYYY-MM-DD'));
                updateAsOfDateDisplay();
            });
        }

        function initializeModalHandlers() {
            // General Options Modal
            $('#general-options-btn').on('click', function(e) {
                e.preventDefault();
                $('#general-options-overlay').show();
            });

            $('#close-general-options').on('click', function() {
                $('#general-options-overlay').hide();
            });

            $('#general-options-overlay').on('click', function(e) {
                if (e.target === this) {
                    $('#general-options-overlay').hide();
                }
            });

            // Filter Modal
            $('#filter-btn').on('click', function(e) {
                e.preventDefault();
                // Sync modal values with main controls
                syncFilterModalValues();
                $('#filter-overlay').show();
            });

            $('#close-filter').on('click', function() {
                $('#filter-overlay').hide();
            });

            $('#filter-overlay').on('click', function(e) {
                if (e.target === this) {
                    $('#filter-overlay').hide();
                }
            });

            // Apply filters button
            $('#apply-filters').on('click', function() {
                applyFiltersFromModal();
                $('#filter-overlay').hide();
            });

            // General options change handlers
            $('.general-options-modal input, .general-options-modal select').on('change', function() {
                applyGeneralOptions();
            });
        }

        function initializeViewOptions() {
            // View Options Dropdown
            $('#view-options-btn').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('#view-options-dropdown').toggle();
            });

            // View option click handlers
            $('.view-option-item').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const value = $(this).data('value');

                if (value === 'compact' || value === 'normal') {
                    setViewType(value);
                } else if (value === 'expand' || value === 'collapse') {
                    setExpandState(value);
                }

                applyViewState();
                updateViewCheckmarks();
                $('#view-options-dropdown').hide();
            });

            // Initialize view state and UI
            setExpandState('expand');
            applyViewState();
            updateViewCheckmarks();
        }

        function setupDataTableEvents() {
            if (balanceSheetTable) {
                // Handle table redraw
                $('#balance-sheet-table').on('draw.dt', function() {
                    attachToggleHandlers();
                    // ensure view state (preserve expand/collapse on redraw)
                    applyViewState();
                });

                // Send additional data with Ajax requests
                $('#balance-sheet-table').on('preXhr.dt', function(e, settings, data) {
                    data.asOfDate = $('#asOfDate').val() || moment().format('YYYY-MM-DD');
                    data.accounting_method = $('#accounting-method').val();
                });

                // initial attach if table already drawn
                attachToggleHandlers();
            }
        }
        $(document).on('click', '.toggle-section', handleSectionToggle);

        function handleSectionToggle(e) {
            e.preventDefault();

            // Don't allow toggle in compact view
            if (window.viewState.viewType === 'compact') {
                return;
            }

            const $this = $(this);
            const group = $this.closest('tr').data('row-id');
            const $row = $this.closest('tr');
            const $chevron = $this.find('.toggle-chevron');
            const $sectionTotal = $row.find('.section-total-amount[data-group="' + group + '"]');
            const $childRows = $('.group-' + group);

            if ($chevron.length === 0) return;
            if ($chevron.hasClass('fa-chevron-down')) {
                // Collapse section
                $childRows.hide();
                $chevron.removeClass('fa-chevron-down').addClass('fa-chevron-right');
                $sectionTotal.show();
            } else {
                // Expand section
                $childRows.show();
                $chevron.removeClass('fa-chevron-right').addClass('fa-chevron-down');
                $sectionTotal.hide();
            }
        }

        function toggleSection($headerRow) {
            if (window.viewState.viewType === 'compact') {
                return; // Don't allow toggle in compact view
            }

            const sectionId = $headerRow.attr('data-row-id');
            if (!sectionId) return;

            if ($headerRow.hasClass('section-expanded')) {
                // Collapse section
                collapseSection(sectionId);
            } else {
                // Expand section
                expandSection(sectionId);
            }
        }

        function expandSection(sectionId) {
            const $headerRow = $('[data-row-id="' + sectionId + '"]');
            const $childRows = $('[data-parent="' + sectionId + '"]');

            $headerRow.addClass('section-expanded');
            $childRows.addClass('section-expanded').show();
            $headerRow.find('.toggle-chevron').removeClass('fa-chevron-right').addClass('fa-chevron-down');
            $headerRow.find('.section-total-amount').hide();

            expandedSections.add(sectionId);
        }

        function collapseSection(sectionId) {
            const $headerRow = $('[data-row-id="' + sectionId + '"]');
            const $childRows = $('[data-parent="' + sectionId + '"]');

            $headerRow.removeClass('section-expanded');
            $childRows.removeClass('section-expanded').hide();
            $headerRow.find('.toggle-chevron').removeClass('fa-chevron-down').addClass('fa-chevron-right');
            $headerRow.find('.section-total-amount').show();

            expandedSections.delete(sectionId);
        }

        function setViewType(type) {
            if (type === 'compact') {
                window.viewState.viewType = 'compact';
                window.viewState.expandState = 'collapse';
            } else if (type === 'normal') {
                window.viewState.viewType = 'normal';
                // keep existing expandState
            }
        }

        function setExpandState(state) {
            if (state === 'collapse') {
                window.viewState.expandState = 'collapse';
                if (window.viewState.viewType !== 'compact') {
                    window.viewState.viewType = 'normal';
                }
            } else if (state === 'expand') {
                window.viewState.expandState = 'expand';
                window.viewState.viewType = 'normal';
            }
        }

        function applyViewState() {
            const $reportContent = $('#report-content');
            $reportContent.removeClass('compact-view');

            if (window.viewState.viewType === 'compact') {
                $reportContent.addClass('compact-view');
                expandedSections.clear();
                // set all header icons to collapsed state visually
                $('.section-header-row').each(function() {
                    $(this).removeClass('section-expanded').find('.toggle-chevron').removeClass('fa-chevron-down')
                        .addClass('fa-chevron-right');
                });
                // hide child rows
                $('.child-row').hide();
                $('.subtotal-row').hide();
                $('.section-total-amount').show();
            } else {
                if (window.viewState.expandState === 'expand') {
                    expandAllSections();
                } else {
                    collapseAllSections();
                }
            }
        }

        function expandAllSections() {
            $('.section-header-row').each(function() {
                const sectionId = $(this).attr('data-row-id');
                if (sectionId) {
                    expandSection(sectionId);
                }
            });
        }

        function collapseAllSections() {
            $('.section-header-row').each(function() {
                const sectionId = $(this).attr('data-row-id');
                if (sectionId) {
                    collapseSection(sectionId);
                }
            });
        }

        function updateViewCheckmarks() {
            $('.view-option-item').removeClass('active');
            // view type
            $('.view-option-item[data-value="' + window.viewState.viewType + '"]').addClass('active');
            // expand/collapse
            $('.view-option-item[data-value="' + window.viewState.expandState + '"]').addClass('active');
        }

        function syncFilterModalValues() {
            // set modal datepicker start to main date
            const mainPicker = $('#asOfDatePicker').data('daterangepicker');
            if (mainPicker) {
                $('#modal-as-of-date').data('daterangepicker').setStartDate(mainPicker.startDate);
            } else {
                $('#modal-as-of-date').val($('#asOfDatePicker').val());
            }

            $('#modal-accounting-method').val($('#accounting-method').val());
        }

        function applyFiltersFromModal() {
            // Get values from modal
            const modalPicker = $('#modal-as-of-date').data('daterangepicker');
            const modalAsOfDate = modalPicker ? modalPicker.startDate : moment($('#modal-as-of-date').val(),
                'MMM DD, YYYY');
            const modalAccountingMethod = $('#modal-accounting-method').val();

            // Update main controls
            if ($('#asOfDatePicker').data('daterangepicker')) {
                $('#asOfDatePicker').data('daterangepicker').setStartDate(modalAsOfDate);
            } else {
                $('#asOfDatePicker').val(modalAsOfDate.format('MMM DD, YYYY'));
            }
            $('#asOfDate').val(modalAsOfDate.format('YYYY-MM-DD'));
            $('#accounting-method').val(modalAccountingMethod);

            // Update display and refresh table
            updateAsOfDateDisplay();
            refreshTable();
        }

        function updateAsOfDateDisplay() {
            const asOfDate = $('#asOfDate').val();
            if (asOfDate) {
                $('#as-of-date-display').text('As of ' + moment(asOfDate).format('MMM DD, YYYY'));
            } else {
                $('#as-of-date-display').text('As of ' + moment().format('MMM DD, YYYY'));
            }
        }

        function refreshTable() {
            if (balanceSheetTable) {
                balanceSheetTable.draw(false);
            }
        }

        function applyGeneralOptions() {
            // Update global options object
            window.reportOptions.divideBy1000 = $('#divide-by-1000').prop('checked');
            window.reportOptions.hideZeroAmounts = $('#hide-zero-amounts').prop('checked');
            window.reportOptions.roundWholeNumbers = $('#round-whole-numbers').prop('checked');
            window.reportOptions.negativeFormat = $('#negative-format').val();
            window.reportOptions.showInRed = $('#show-in-red').prop('checked');
            window.reportOptions.companyLogo = $('#company-logo').prop('checked');
            window.reportOptions.reportPeriod = $('#report-period').prop('checked');
            window.reportOptions.companyName = $('#company-name').prop('checked');
            window.reportOptions.headerAlignment = $('#header-alignment').val();
            window.reportOptions.datePrepared = $('#date-prepared').prop('checked');
            window.reportOptions.timePrepared = $('#time-prepared').prop('checked');
            window.reportOptions.reportBasis = $('#report-basis').prop('checked');
            window.reportOptions.footerAlignment = $('#footer-alignment').val();

            // Apply formatting
            applyNumberFormatting();
            applyHeaderFooterSettings();
            applyCellFormatting();
            refreshTable();
        }

        function applyNumberFormatting() {
            // Remove existing custom styles
            $('#custom-number-format').remove();

            let customCSS = '<style id="custom-number-format">';

            if (window.reportOptions.showInRed) {
                customCSS += '.negative-amount { color: #dc2626 !important; }';
            }

            if (window.reportOptions.hideZeroAmounts) {
                customCSS += '.zero-amount { display: none !important; }';
            }

            customCSS += '</style>';
            $('head').append(customCSS);
        }

        function applyHeaderFooterSettings() {
            // Update header alignment
            $('.report-title-section').css('text-align', window.reportOptions.headerAlignment);

            // Show/hide header elements
            $('.company-name').toggle(window.reportOptions.companyName);
            $('.date-range').toggle(window.reportOptions.reportPeriod);

            // Handle footer
            updateReportFooter();
        }

        function updateReportFooter() {
            $('.report-footer').remove();

            if (window.reportOptions.datePrepared || window.reportOptions.timePrepared || window.reportOptions
                .reportBasis) {
                const currentDate = new Date();
                const dateStr = currentDate.toLocaleDateString();
                const timeStr = currentDate.toLocaleTimeString();
                const basisStr = $('#accounting-method').val() === 'accrual' ? 'Accrual Basis' : 'Cash Basis';

                let footerHTML =
                    '<div class="report-footer" style="padding: 20px; border-top: 1px solid #e6e6e6; text-align: ' +
                    window.reportOptions.footerAlignment + '; font-size: 12px; color: #6b7280;">';

                if (window.reportOptions.datePrepared) {
                    footerHTML += '<div>Date Prepared: ' + dateStr + '</div>';
                }

                if (window.reportOptions.timePrepared) {
                    footerHTML += '<div>Time Prepared: ' + timeStr + '</div>';
                }

                if (window.reportOptions.reportBasis) {
                    footerHTML += '<div>Report Basis: ' + basisStr + '</div>';
                }

                footerHTML += '</div>';
                $('#report-content').append(footerHTML);
            }
        }

        function applyCellFormatting() {
            $('.balance-sheet-table tbody tr').each(function() {
                const $row = $(this);

                $row.find('td').each(function() {
                    const $cell = $(this);
                    const text = $cell.text().trim();

                    if (text && !isNaN(text.replace(/[,$()]/g, '')) && text.replace(/[,$()]/g, '') !== '') {
                        let value = parseFloat(text.replace(/[,$()]/g, ''));

                        if (window.reportOptions.hideZeroAmounts && value === 0) {
                            $cell.addClass('zero-amount');
                            return;
                        }

                        if (window.reportOptions.divideBy1000) {
                            value = value / 1000;
                        }

                        if (window.reportOptions.roundWholeNumbers) {
                            value = Math.round(value);
                        }

                        // Format negatives
                        if (value < 0) {
                            $cell.addClass('negative-amount');
                            switch (window.reportOptions.negativeFormat) {
                                case '(100)':
                                    $cell.text('(' + Math.abs(value).toLocaleString() + ')');
                                    break;
                                case '100-':
                                    $cell.text(Math.abs(value).toLocaleString() + '-');
                                    break;
                                default:
                                    $cell.text('-' + Math.abs(value).toLocaleString());
                            }
                        } else if (value > 0) {
                            $cell.text(value.toLocaleString());
                        }
                    }
                });
            });
        }

        // Format numbers after table draw
        $(document).on('draw.dt', '#balance-sheet-table', function() {
            applyCellFormatting();
        });

        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'e' || e.key === 'E')) {
                e.preventDefault();
                if (window.viewState.viewType !== 'compact') {
                    setExpandState('expand');
                    applyViewState();
                    updateViewCheckmarks();
                }
            }
            if ((e.ctrlKey || e.metaKey) && (e.key === 'c' || e.key === 'C')) {
                e.preventDefault();
                setExpandState('collapse');
                applyViewState();
                updateViewCheckmarks();
            }
        });

        //set time for 4 sec to run expand all function
        setTimeout(function() {
            expandAllSections();
        }, 4000);
    </script>

    {!! $dataTable->scripts() !!}
@endpush
