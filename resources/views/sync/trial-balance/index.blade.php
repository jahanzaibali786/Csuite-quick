@extends('layouts.admin')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css" />
    <style>
        :root {
            --qb-primary: #2ca01c;
            --qb-primary-hover: #248f17;
            --qb-muted: #f5f6f8;
            --qb-border: #e6e8eb;
            --qb-text: #333333;
            --qb-accent: #003366;
        }

        body {
            background: var(--qb-muted);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        /* Report Container */
        .report-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .report-header {
            text-align: center;
        }

        /* Filter Card */
        .filter-card {
            background: #fff;
            border-radius: 6px;
            border: 1px solid var(--qb-border);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        /* Form Controls */
        .form-select,
        .form-control {
            border: 1px solid #dde2e7;
            border-radius: 4px;
            padding: 8px 12px;
            height: 38px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-select:focus,
        .form-control:focus {
            border-color: var(--qb-primary);
            box-shadow: 0 0 0 2px rgba(44, 160, 28, 0.25);
            outline: none;
        }

        .muted-label {
            display: block;
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 4px;
            font-weight: 500;
        }

        /* Radio buttons styling */
        .form-check-input {
            margin-right: 6px;
        }

        .form-check-label {
            font-size: 14px;
            color: var(--qb-text);
            margin-bottom: 0;
        }

        .form-check-inline {
            margin-right: 15px;
        }

        /* Buttons */
        .btn {
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 4px;
            transition: all 0.2s;
            border: 1px solid transparent;
            cursor: pointer;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-primary {
            background: var(--qb-primary);
            border-color: var(--qb-primary);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: var(--qb-primary-hover);
            border-color: var(--qb-primary-hover);
        }

        .btn-outline-secondary {
            background: white;
            border-color: #dde2e7;
            color: #444;
        }

        .btn-outline-secondary:hover:not(:disabled) {
            background: #f8f9fa;
            border-color: #bbb;
        }

        .btn-success {
            background: var(--qb-primary);
            border-color: var(--qb-primary);
            color: white;
        }

        .btn-success:hover:not(:disabled) {
            background: var(--qb-primary-hover);
            border-color: var(--qb-primary-hover);
        }

        .btn-white {
            background: white;
            border: 1px solid #dde2e7;
            padding: 6px 10px;
            font-size: 14px;
        }

        .btn-white:hover:not(:disabled) {
            background: #f8f9fa;
            border-color: #bbb;
        }

        .btn-short {
            padding: 6px 12px;
            font-size: 14px;
        }

        /* Report Header */
        .report-card {
            background: #fff;
            border-radius: 6px;
            border: 1px solid var(--qb-border);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .report-toolbar {
            padding: 12px 16px;
            border-bottom: 1px solid #f0f1f3;
            display: flex;
            flex-direction: column;
            gap: 8px;
            text-align: center !important;
        }

        .toolbar-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .report-title {
            text-align: center;
            padding: 12px 8px;
        }

        .report-title h4 {
            margin-bottom: 4px;
            font-weight: 600;
            color: var(--qb-text);
        }

        .report-divider {
            height: 1px;
            background: var(--qb-border);
        }

        /* Table */
        .table-card {
            padding: 0;
            background: #fff;
            border-radius: 0 0 6px 6px;
        }

        .table {
            font-size: 13.5px;
            margin: 0;
            width: 100%;
        }

        .table thead th {
            padding: 10px 12px;
            font-weight: 700;
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 3;
            border-bottom: 1px solid #eceff2;
            color: var(--qb-text);
        }

        .table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #f5f6f8;
            vertical-align: middle;
            color: var(--qb-text);
        }

        .table tbody tr:hover {
            background: #fbfcfe;
        }

        /* Toggle Button */
        .toggle-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 3px;
            background: #f1f3f5;
            border: 0;
            margin-right: 8px;
            color: #495057;
            cursor: pointer;
            transition: all 0.2s;
        }

        .toggle-btn:hover {
            background: #e9ecef;
        }

        .toggle-btn .toggle-icon {
            transition: transform 0.2s;
        }

        .toggle-btn.expanded .toggle-icon {
            transform: rotate(90deg);
        }

        /* Account Rows */
        .hidden-row {
            display: none !important;
        }

        .account-detail td:first-child {
            padding-left: 36px !important;
        }

        .col-amount {
            text-align: right;
            white-space: nowrap;
        }

        /* Indentation */
        .indent-spacer {
            display: inline-block;
            width: 20px;
        }

        /* Group Headers */
        .account-header {
            background: #f8f9fa;
            font-weight: 600;
        }

        .account-header:hover {
            background: #f1f3f5 !important;
        }

        .account-header .account-header-text {
            color: #333;
            font-weight: 700;
        }

        /* Subtotals */
        .account-subtotal {
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }

        /* Grand Total */
        .grand-total {
            font-weight: 800;
            background: #f8f9fa;
            border-top: 2px solid var(--qb-border);
        }

        /* Net Income */
        .net-income {
            background: #f0f8ff;
            font-weight: 600;
        }

        /* Amount Formatting */
        .text-success {
            color: #28a745 !important;
        }

        .text-danger {
            color: #dc3545 !important;
        }

        /* Loading State */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Error States */
        .is-invalid {
            border-color: #dc3545;
        }

        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875rem;
            color: #dc3545;
        }

        /* Time Period Columns */
        .month-debit,
        .month-credit,
        .quarter-debit,
        .quarter-credit,
        .year-debit,
        .year-credit {
            font-size: 13px;
            text-align: right;
            min-width: 100px;
        }

        /* Alternating column coloring */
        .month-debit,
        .quarter-debit,
        .year-debit {
            background-color: rgba(240, 248, 255, 0.3);
        }

        .month-credit,
        .quarter-credit,
        .year-credit {
            background-color: rgba(255, 240, 245, 0.3);
        }
         #switch-view-btn:hover , #customize-btn:hover {
        background-color: #206029 !important; /* Bootstrap primary */
        color: #fff !important;
        border-color: #206029 !important;
    }
        /* Responsive */
        @media (max-width: 992px) {
            .toolbar-row {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }

            .report-container {
                padding: 0 10px;
            }

            .table-responsive {
                font-size: 12px;
            }
        }

        @media (max-width: 768px) {
            .form-check-inline {
                display: block;
                margin-right: 0;
                margin-bottom: 5px;
            }
        }
    </style>

    <div class="container-fluid py-3">
        <div class="report-container">
            <!-- FILTER CARD -->
            <div class="filter-card card mb-3">
                <form id="filter-form" class="p-3">
                    @csrf
                    <div class="row gy-3 gx-3 align-items-end justify-content-end">
                        <!-- Row 1 -->
                        <div class="col-md-3">
                            <label class="muted-label" for="report-period">Report period</label>
                            <select id="report-period" name="reportPeriod" class="form-select filter-control">
                                <option value="today" {{ request('reportPeriod') == 'today' ? 'selected' : '' }}>Today
                                </option>
                                <option value="yesterday" {{ request('reportPeriod') == 'yesterday' ? 'selected' : '' }}>
                                    Yesterday</option>
                                <option value="this-week" {{ request('reportPeriod') == 'this-week' ? 'selected' : '' }}>
                                    This week</option>
                                <option value="last-week" {{ request('reportPeriod') == 'last-week' ? 'selected' : '' }}>
                                    Last week</option>
                                <option value="this-month"
                                    {{ request('reportPeriod', 'this-month') == 'this-month' ? 'selected' : '' }}>This
                                    month-to-date</option>
                                <option value="last-month" {{ request('reportPeriod') == 'last-month' ? 'selected' : '' }}>
                                    Last month</option>
                                <option value="this-quarter"
                                    {{ request('reportPeriod') == 'this-quarter' ? 'selected' : '' }}>This quarter</option>
                                <option value="last-quarter"
                                    {{ request('reportPeriod') == 'last-quarter' ? 'selected' : '' }}>Last quarter</option>
                                <option value="this-year" {{ request('reportPeriod') == 'this-year' ? 'selected' : '' }}>
                                    This year</option>
                                <option value="last-year" {{ request('reportPeriod') == 'last-year' ? 'selected' : '' }}>
                                    Last year</option>
                                <option value="custom" {{ request('reportPeriod') == 'custom' ? 'selected' : '' }}>Custom
                                </option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="muted-label" for="date-from">From</label>
                            <input id="date-from" name="dateFrom" type="date" class="form-control filter-control"
                                value="{{ request('dateFrom', \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d')) }}">
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-3">
                            <label class="muted-label" for="date-to">To</label>
                            <input id="date-to" name="dateTo" type="date" class="form-control filter-control"
                                value="{{ request('dateTo', \Carbon\Carbon::now()->format('Y-m-d')) }}">
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-3">
                            <label class="muted-label">Accounting method</label>
                            <div class="d-flex align-items-center mt-1">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input filter-control" type="radio" name="accountingMethod"
                                        id="method-cash" value="cash"
                                        {{ request('accountingMethod') == 'cash' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="method-cash">Cash</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input filter-control" type="radio" name="accountingMethod"
                                        id="method-accrual" value="accrual"
                                        {{ request('accountingMethod', 'accrual') == 'accrual' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="method-accrual">Accrual</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row gy-3 gx-3 align-items-end justify-content-end mt-3">
                        <!-- Row 2 -->
                        <div class="col-md-3">
                            <label class="muted-label" for="display-columns">Display columns by</label>
                            <select id="display-columns" name="displayColumns" class="form-select filter-control">
                                <option value="total-only"
                                    {{ request('displayColumns', 'total-only') == 'total-only' ? 'selected' : '' }}>Total
                                    Only</option>
                                <option value="months" {{ request('displayColumns') == 'months' ? 'selected' : '' }}>Months
                                </option>   
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="muted-label">Show Options</label>
                            <div class="mt-1">
                                <select class="form-control filter-control" id="showOptions" name="showOptions">
                                    <optgroup label="Show Rows">
                                        <option value="rows-active"
                                            {{ request('showRows', 'active') == 'active' ? 'selected' : '' }}>Active
                                        </option>
                                        <option value="rows-all" {{ request('showRows') == 'all' ? 'selected' : '' }}>All
                                        </option>
                                        <option value="rows-nonzero"
                                            {{ request('showRows') == 'non-zero' ? 'selected' : '' }}>Non-zero</option>
                                    </optgroup>
                                    <optgroup label="Show Columns">
                                        <option value="cols-active"
                                            {{ request('showColumns', 'active') == 'active' ? 'selected' : '' }}>Active
                                        </option>
                                        <option value="cols-all" {{ request('showColumns') == 'all' ? 'selected' : '' }}>
                                            All</option>
                                        <option value="cols-nonzero"
                                            {{ request('showColumns') == 'non-zero' ? 'selected' : '' }}>Non-zero</option>
                                    </optgroup>
                                </select>
                            </div>
                        </div>



                        <div class="col-md-3">
                            <label class="muted-label" for="account-filter">Account</label>
                            <select id="account-filter" name="accountType" class="form-select filter-control">
                                <option value="all" {{ request('accountType', 'all') == 'all' ? 'selected' : '' }}>All
                                </option>
                                <option value="Asset" {{ request('accountType') == 'Asset' ? 'selected' : '' }}>Assets
                                </option>
                                <option value="Liability" {{ request('accountType') == 'Liability' ? 'selected' : '' }}>
                                    Liabilities</option>
                                <option value="Equity" {{ request('accountType') == 'Equity' ? 'selected' : '' }}>Equity
                                </option>
                                <option value="Income" {{ request('accountType') == 'Income' ? 'selected' : '' }}>Income
                                </option>
                                <option value="Expense" {{ request('accountType') == 'Expense' ? 'selected' : '' }}>
                                    Expense</option>
                            </select>
                        </div>
                         <div class="col-md-3">
                            <button type="button" id="run-report" class="btn btn-primary">
                                Run report
                            </button>
                        </div>
                    </div>

                    <div class="row gy-3 gx-3 align-items-end justify-content-end mt-3">
                        <!-- Row 3 - Buttons -->
                       
                        <div class="col-auto">
                            <button type="button" class="btn btn-outline-secondary btn-short" id="customize-btn">
                                Customize
                            </button>
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-outline-secondary hover:btn-primary btn-short" id="switch-view-btn">
                                Modern view
                            </button>
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-success btn-short " id="save-btn">
                                Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- REPORT CARD -->
            <div class="report-card card">
                <div class="report-toolbar">
                    <div class="toolbar-row">
                        <div class="small text-muted">Add notes</div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <!-- Icon buttons -->
                            <button type="button" class="btn btn-white" id="email-btn" title="Email">
                                <i class="fa fa-envelope"></i>
                            </button>
                            <button type="button" class="btn btn-white" id="print-btn" title="Print">
                                <i class="fa fa-print"></i>
                            </button>
                            <button type="button" class="btn btn-white" id="export-btn" title="Export">
                                <i class="fa fa-file-export"></i>
                            </button>
                            <button type="button" class="btn btn-white" id="settings-btn" title="Settings">
                                <i class="fa-solid fa-gear"></i>
                            </button>
                        </div>
                    </div>

                    <div class="report-header">
                        <div class="report-title">
                            <h4 id="company-name">{{ $companyName ?? "Craig's Design and Landscaping Services" }}</h4>
                            <div class="small text-muted">Trial Balance</div>
                            <div class="small text-muted">As of <span
                                    id="report-asof">{{ request('dateTo') ? \Carbon\Carbon::parse(request('dateTo'))->format('F d, Y') : \Carbon\Carbon::now()->format('F d, Y') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="report-divider"></div>

                <!-- TABLE CARD -->
                <div class="table-card p-3">
                    <div class="table-responsive p-3" style="max-height:560px; overflow:auto;">
                        {!! $dataTable->table(['class' => 'table table-hover', 'id' => 'trial-balance-table']) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
    <!-- Required scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <!-- Enhanced Trial Balance JavaScript -->
    <script>
        // Fixed Trial Balance JavaScript
        (function($) {
            "use strict";

            let dataTable = null;

            // Initialize toggle controls for hierarchical display
            function initializeToggleControls() {
                // Remove existing event listeners to prevent duplicates
                $(document).off('click.toggle', '.toggle-btn');

                // Add new event listener
                $(document).on('click.toggle', '.toggle-btn', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const $btn = $(this);
                    const targetGroup = $btn.data('target');
                    const $icon = $btn.find('.toggle-icon');
                    const isCollapsed = $btn.hasClass('collapsed');

                    if (isCollapsed) {
                        // Expand: show child rows
                        $btn.removeClass('collapsed').addClass('expanded');
                        $icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
                        $(`.child-of-${targetGroup}`).removeClass('hidden-row').show();

                        // Show header amounts when expanded
                        $btn.closest('tr').find('.header-amount').show();
                    } else {
                        // Collapse: hide child rows
                        $btn.removeClass('expanded').addClass('collapsed');
                        $icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
                        $(`.child-of-${targetGroup}`).addClass('hidden-row').hide();

                        // Hide header amounts when collapsed
                        $btn.closest('tr').find('.header-amount').hide();
                    }
                });

                // Initialize all toggle buttons as collapsed by default
                $('.toggle-btn').addClass('collapsed');
                $('.toggle-btn .toggle-icon').removeClass('fa-chevron-down').addClass('fa-chevron-right');

                // Hide all child rows initially
                $('.child-row').addClass('hidden-row').hide();

                // Hide header amounts initially
                $('.header-amount').hide();
            }

            // Make function globally available
            window.initializeToggleControls = initializeToggleControls;

            // Get unified filter value based on row and column settings
            function getShowFilterValue() {
                const showRows = $('input[name="showRows"]:checked').val();
                const showCols = $('input[name="showColumns"]:checked').val();

                // Map the radio button values to a unified parameter
                if (showRows === 'non-zero' || showCols === 'non-zero') {
                    return 'non-zero';
                } else if (showRows === 'all' && showCols === 'all') {
                    return 'all';
                } else {
                    return 'active';
                }
            }

            // Enhanced refresh table function
            function refreshTable() {
                // Show loading state
                const $runBtn = $('#run-report');
                const originalText = $runBtn.html();
                $runBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i>Loading...');

                try {
                    // Collect all filter values
                    const filters = {
                        reportPeriod: $('#report-period').val(),
                        dateFrom: $('#date-from').val(),
                        dateTo: $('#date-to').val(),
                        accountType: $('#account-filter').val(),
                        accountingMethod: $('input[name="accountingMethod"]:checked').val(),
                        displayColumns: $('#display-columns').val(),
                        showRows: $('input[name="showRows"]:checked').val(),
                        showColumns: $('input[name="showColumns"]:checked').val(),
                        showNonZero: getShowFilterValue(),
                        _token: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()
                    };

                    // Validate required filters
                    if (!filters.dateFrom || !filters.dateTo) {
                        alert('Please select valid date range');
                        return;
                    }

                    if (new Date(filters.dateFrom) > new Date(filters.dateTo)) {
                        alert('Start date cannot be after end date');
                        return;
                    }

                    // Update date display
                    const displayDate = moment(filters.dateTo).format('MMMM D, YYYY');
                    $('#report-asof').text(displayDate);

                    // Update last updated timestamp
                    const currentTime = moment().format('h:mm A, MMM D, YYYY');
                    if ($('#last-updated').length) {
                        $('#last-updated').text('Last updated — ' + currentTime);
                    } else {
                        $('.report-title').append('<div class="small text-muted" id="last-updated">Last updated — ' +
                            currentTime + '</div>');
                    }

                    // Get DataTable instance
                    const table = getDataTableInstance();

                    if (table && table.ajax) {
                        // Update DataTable with new parameters
                        table.off('preXhr.dt');
                        table.on('preXhr.dt', function(e, settings, data) {
                            // Add all filters to the ajax request
                            Object.assign(data, filters);
                        });

                        // Redraw table with new data
                        table.ajax.reload(function() {
                            // Reinitialize toggle controls after table refresh
                            setTimeout(() => {
                                initializeToggleControls();
                                applyRowVisibilityFilters();
                            }, 100);
                        }, false);
                    } else {
                        // Fallback: reload page with query parameters
                        const queryString = new URLSearchParams(filters).toString();
                        const newUrl = window.location.pathname + '?' + queryString;
                        window.location.href = newUrl;
                    }

                } catch (error) {
                    console.error('Error refreshing table:', error);
                    alert('Error refreshing report. Please try again.');
                } finally {
                    // Restore button state
                    setTimeout(() => {
                        $runBtn.prop('disabled', false).html(originalText);
                    }, 500);
                }
            }

            // Apply row visibility filters
            function applyRowVisibilityFilters() {
                const showRows = $('input[name="showRows"]:checked').val();

                if (showRows === 'non-zero') {
                    // Hide rows with zero debit and credit
                    $('#trial-balance-table tbody tr').each(function() {
                        const $row = $(this);
                        const debitText = $row.find('td:nth-child(4)').text().replace(/[^\d.-]/g, '');
                        const creditText = $row.find('td:nth-child(5)').text().replace(/[^\d.-]/g, '');
                        const debit = parseFloat(debitText) || 0;
                        const credit = parseFloat(creditText) || 0;

                        if (debit === 0 && credit === 0 && !$row.hasClass('account-header') && !$row.hasClass(
                                'grand-total')) {
                            $row.hide();
                        } else {
                            $row.show();
                        }
                    });
                } else {
                    // Show all rows (except those hidden by toggle)
                    $('#trial-balance-table tbody tr').each(function() {
                        const $row = $(this);
                        if (!$row.hasClass('hidden-row')) {
                            $row.show();
                        }
                    });
                }
            }

            // Get DataTable instance
            function getDataTableInstance() {
                // Try Laravel DataTables global first
                if (window.LaravelDataTables && window.LaravelDataTables["trial-balance-table"]) {
                    return window.LaravelDataTables["trial-balance-table"];
                }

                // Try jQuery DataTables
                if ($.fn.dataTable.isDataTable('#trial-balance-table')) {
                    return $('#trial-balance-table').DataTable();
                }

                return null;
            }

            // Update date inputs based on period selection
            function updateDateInputs() {
                const period = $('#report-period').val();
                let startDate, endDate;

                const now = moment();

                switch (period) {
                    case 'today':
                        startDate = endDate = now.format('YYYY-MM-DD');
                        break;
                    case 'yesterday':
                        startDate = endDate = now.subtract(1, 'day').format('YYYY-MM-DD');
                        break;
                    case 'this-week':
                        startDate = now.startOf('week').format('YYYY-MM-DD');
                        endDate = moment().format('YYYY-MM-DD');
                        break;
                    case 'last-week':
                        startDate = now.subtract(1, 'week').startOf('week').format('YYYY-MM-DD');
                        endDate = now.endOf('week').format('YYYY-MM-DD');
                        break;
                    case 'this-month':
                        startDate = now.startOf('month').format('YYYY-MM-DD');
                        endDate = moment().format('YYYY-MM-DD');
                        break;
                    case 'last-month':
                        startDate = now.subtract(1, 'month').startOf('month').format('YYYY-MM-DD');
                        endDate = now.endOf('month').format('YYYY-MM-DD');
                        break;
                    case 'this-quarter':
                        startDate = now.startOf('quarter').format('YYYY-MM-DD');
                        endDate = moment().format('YYYY-MM-DD');
                        break;
                    case 'last-quarter':
                        startDate = now.subtract(1, 'quarter').startOf('quarter').format('YYYY-MM-DD');
                        endDate = now.endOf('quarter').format('YYYY-MM-DD');
                        break;
                    case 'this-year':
                        startDate = now.startOf('year').format('YYYY-MM-DD');
                        endDate = moment().format('YYYY-MM-DD');
                        break;
                    case 'last-year':
                        startDate = now.subtract(1, 'year').startOf('year').format('YYYY-MM-DD');
                        endDate = now.endOf('year').format('YYYY-MM-DD');
                        break;
                    case 'custom':
                        // Don't change date inputs for custom selection
                        refreshTable();
                        return;
                    default:
                        return;
                }

                // Update the date input fields
                $('#date-from').val(startDate);
                $('#date-to').val(endDate);

                // Trigger table refresh after updating dates
                refreshTable();
            }

            // Export functionality
            function exportToExcel() {
                try {
                    const table = document.getElementById('trial-balance-table');
                    if (!table) {
                        alert('Table not found');
                        return;
                    }

                    // Clone table to avoid modifying original
                    const clonedTable = table.cloneNode(true);

                    // Remove any hidden rows or columns
                    $(clonedTable).find('.hidden-row').remove();
                    $(clonedTable).find('th, td').filter(':hidden').remove();

                    // Clean up HTML formatting for Excel
                    $(clonedTable).find('td, th').each(function() {
                        const $cell = $(this);
                        const text = $cell.text().trim();
                        $cell.html(text);
                    });

                    const workbook = XLSX.utils.table_to_book(clonedTable, {
                        sheet: "Trial Balance"
                    });

                    const filename = 'Trial_Balance_' + moment().format('YYYY-MM-DD') + '.xlsx';
                    XLSX.writeFile(workbook, filename);

                } catch (error) {
                    console.error('Export error:', error);
                    alert('Error exporting file. Please try again.');
                }
            }

            // Print functionality
            function printReport() {
                try {
                    const table = document.getElementById('trial-balance-table');
                    if (!table) {
                        alert('Table not found');
                        return;
                    }

                    const printWindow = window.open('', '_blank');
                    const companyName = $('#company-name').text() || "Company Name";
                    const reportDate = $('#report-asof').text() || moment().format('MMMM D, YYYY');

                    const printStyles = `
                body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }
                .report-header { text-align: center; margin-bottom: 30px; }
                .report-header h2 { margin-bottom: 5px; font-size: 18px; }
                .report-header p { margin: 2px 0; color: #666; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { padding: 8px 12px; border-bottom: 1px solid #ddd; text-align: left; }
                th { background-color: #f8f9fa; font-weight: bold; border-bottom: 2px solid #333; }
                .text-end { text-align: right; }
                .account-header { background-color: #f0f0f0; font-weight: bold; }
                .account-subtotal { background-color: #f5f5f5; font-weight: bold; }
                .grand-total { font-weight: bold; border-top: 2px solid #333; background-color: #e9e9e9; }
                .hidden-row { display: none; }
                .text-success { color: #28a745; }
                .text-danger { color: #dc3545; }
                .toggle-btn { display: none; }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
            `;

                    const printHTML = `
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <title>Trial Balance Report</title>
                    <style>${printStyles}</style>
                </head>
                <body>
                    <div class="report-header">
                        <h2>${companyName}</h2>
                        <p>Trial Balance</p>
                        <p>As of ${reportDate}</p>
                    </div>
                    ${table.outerHTML}
                </body>
                </html>
            `;

                    printWindow.document.write(printHTML);
                    printWindow.document.close();

                    setTimeout(() => {
                        printWindow.print();
                        setTimeout(() => printWindow.close(), 1000);
                    }, 500);

                } catch (error) {
                    console.error('Print error:', error);
                    alert('Error printing report. Please try again.');
                }
            }

            // Email functionality
            function emailReport() {
                const subject = encodeURIComponent('Trial Balance Report');
                const reportDate = $('#report-asof').text();
                const body = encodeURIComponent('Please find the Trial Balance report as of ' + reportDate + '.');
                const mailto = 'mailto:?subject=' + subject + '&body=' + body;

                if (confirm('This will open your default email client. Do you want to continue?')) {
                    window.location.href = mailto;
                }
            }

            // Date validation
            function validateDateInputs() {
                const fromDate = $('#date-from').val();
                const toDate = $('#date-to').val();

                $('#date-from, #date-to').removeClass('is-invalid');
                $('.invalid-feedback').text('');

                if (!fromDate || !toDate) {
                    if (!fromDate) {
                        $('#date-from').addClass('is-invalid');
                        $('#date-from').siblings('.invalid-feedback').text('Please select a start date');
                    }
                    if (!toDate) {
                        $('#date-to').addClass('is-invalid');
                        $('#date-to').siblings('.invalid-feedback').text('Please select an end date');
                    }
                    return false;
                }

                if (new Date(fromDate) > new Date(toDate)) {
                    $('#date-from').addClass('is-invalid');
                    $('#date-from').siblings('.invalid-feedback').text('Start date cannot be after end date');
                    return false;
                }

                return true;
            }

            // Initialize everything when document is ready
            $(document).ready(function() {
                // Initialize DataTable reference
                setTimeout(() => {
                    dataTable = getDataTableInstance();
                }, 1000);

                // Run report button
                $('#run-report').on('click', function(e) {
                    e.preventDefault();
                    if (validateDateInputs()) {
                        refreshTable();
                    }
                });

                // Auto-refresh on filter changes (with debouncing)
                let filterTimeout;
                $(document).on('change', '.filter-control', function() {
                    clearTimeout(filterTimeout);

                    const $element = $(this);

                    // If period dropdown changes, update dates first
                    if ($element.attr('id') === 'report-period') {
                        updateDateInputs();
                        return;
                    }

                    // Debounce other filter changes
                    filterTimeout = setTimeout(() => {
                        if (validateDateInputs()) {
                            refreshTable();
                        }
                    }, 300);
                });

                // Period dropdown change handler
                $('#report-period').on('change', updateDateInputs);

                // Date input validation
                $('#date-from, #date-to').on('change', function() {
                    // Auto-update period to custom when dates are manually changed
                    $('#report-period').val('custom');

                    // Clear any existing validation errors
                    $(this).removeClass('is-invalid');
                    $(this).siblings('.invalid-feedback').text('');
                });

                // Export button
                $('#export-btn').on('click', function(e) {
                    e.preventDefault();
                    exportToExcel();
                });

                // Print button
                $('#print-btn').on('click', function(e) {
                    e.preventDefault();
                    printReport();
                });

                // Email button
                $('#email-btn').on('click', function(e) {
                    e.preventDefault();
                    emailReport();
                });

                // Settings button (placeholder)
                $('#settings-btn').on('click', function(e) {
                    e.preventDefault();
                    alert('Settings feature will be implemented here');
                });

                // Customize button (placeholder)
                $('#customize-btn').on('click', function(e) {
                    e.preventDefault();
                    alert('Customize report feature will be implemented here');
                });

                // Save customization button (placeholder)
                $('#save-btn').on('click', function(e) {
                    e.preventDefault();
                    alert('Save customization feature will be implemented here');
                });

                // Switch view button (placeholder)
                $('#switch-view-btn').on('click', function(e) {
                    e.preventDefault();
                    const $btn = $(this);
                    const currentText = $btn.html();
                    const isModern = currentText.includes('modern');
                    const newText = isModern ?
                        '<i class="fa fa-exchange-alt me-1"></i>Switch to classic view' :
                        '<i class="fa fa-exchange-alt me-1"></i>Switch to modern view';
                    $btn.html(newText);
                    alert('View switching feature will be implemented here');
                });

                // Handle DataTable draw events
                $(document).on('draw.dt', '#trial-balance-table', function() {
                    setTimeout(() => {
                        initializeToggleControls();
                        applyRowVisibilityFilters();
                    }, 100);
                });

                // Initialize toggle controls on page load
                setTimeout(() => {
                    initializeToggleControls();
                    applyRowVisibilityFilters();
                }, 1500);

                // Handle window resize for responsive table
                $(window).on('resize', function() {
                    const table = getDataTableInstance();
                    if (table && table.columns) {
                        table.columns.adjust();
                    }
                });

                // Add keyboard shortcuts
                $(document).on('keydown', function(e) {
                    // Ctrl+R or F5 to refresh report
                    if ((e.ctrlKey && e.keyCode === 82) || e.keyCode === 116) {
                        e.preventDefault();
                        if (validateDateInputs()) {
                            refreshTable();
                        }
                    }

                    // Ctrl+P to print
                    if (e.ctrlKey && e.keyCode === 80) {
                        e.preventDefault();
                        printReport();
                    }

                    // Ctrl+E to export
                    if (e.ctrlKey && e.keyCode === 69) {
                        e.preventDefault();
                        exportToExcel();
                    }
                });

                // Form submission handling
                $('#filter-form').on('submit', function(e) {
                    e.preventDefault();
                    if (validateDateInputs()) {
                        refreshTable();
                    }
                });
            });

            // Make functions globally available
            window.refreshTrialBalance = refreshTable;
            window.exportTrialBalance = exportToExcel;
            window.printTrialBalance = printReport;

        })(jQuery);
    </script>
    <!-- Yajra DataTables scripts -->
    {!! $dataTable->scripts() !!}

    <!-- Custom initialization script for DataTables -->
    <script>
        $(document).ready(function() {
            // Wait for DataTables to initialize, then setup toggle controls
            setTimeout(function() {
               /// expand all items               
               $('.toggle-btn').click();
               //toggle icon rotate 360 deg
               $('.toggle-icon').css('transform', 'rotate(360deg)');
            }, 5000);
        });
    </script>
@endpush
