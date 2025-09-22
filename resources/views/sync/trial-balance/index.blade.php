@extends('layouts.admin')

@push('datatable-styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
@endpush

@section('filter-section')
    <style>
        /* Enhanced Trial Balance Styling */
        .trial-balance-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .trial-balance-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-bottom: none;
        }

        .trial-balance-header h4 {
            margin: 0;
            font-weight: 600;
            font-size: 1.5rem;
        }

        .date-range-display {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 5px;
        }

        .table-container {
            padding: 0;
            overflow: hidden;
        }

        .table-scroll {
            max-height: 600px;
            overflow: auto;
            position: relative;
        }

        .trial-balance-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .trial-balance-table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 2px solid #dee2e6;
            font-weight: 700;
            color: #495057;
            padding: 15px 12px;
            text-align: center;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Row styling */
        .trial-balance-table tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid #f1f3f4;
        }

        .trial-balance-table tbody tr:hover {
            background-color: #f8f9fa !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* Account Header Rows */
        .account-header {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%) !important;
            font-weight: 700;
            border-top: 3px solid #2196f3;
            cursor: pointer;
        }

        .account-header td {
            padding: 15px 12px !important;
            font-size: 0.95rem;
        }

        .account-header-text {
            color: #1976d2;
            font-size: 1rem;
            letter-spacing: 1px;
        }

        /* Detail Account Rows */
        .account-detail {
            background-color: #ffffff;
        }

        .account-detail td {
            padding: 12px 12px 12px 40px !important;
            border-bottom: 1px solid #f8f9fa;
        }

        /* Subtotal Rows */
        .account-subtotal {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%) !important;
            border-top: 2px solid #ff9800;
            border-bottom: 2px solid #ff9800;
        }

        .account-subtotal td {
            padding: 14px 12px 14px 40px !important;
            font-weight: 700;
        }

        .account-subtotal-text {
            color: #f57c00;
            font-size: 0.95rem;
        }

        /* Grand Total Row */
        .grand-total {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%) !important;
            border-top: 3px solid #e91e63;
            border-bottom: 3px solid #e91e63;
        }

        .grand-total td {
            padding: 18px 12px !important;
            font-weight: 800;
        }

        .grand-total-text {
            color: #c2185b;
            font-size: 1.1rem;
            font-weight: 800;
        }

        /* Net Income Row */
        .net-income {
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%) !important;
            border-top: 2px solid #4caf50;
        }

        .net-income td {
            padding: 15px 12px !important;
        }

        .net-income-text {
            color: #388e3c;
            font-weight: 700;
        }

        /* Toggle Button Styling */
        .toggle-btn {
            cursor: pointer;
            margin-right: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: rgba(33, 150, 243, 0.1);
            transition: all 0.3s ease;
        }

        .toggle-btn:hover {
            background: rgba(33, 150, 243, 0.2);
            transform: scale(1.1);
        }

        .toggle-icon {
            font-size: 12px;
            color: #2196f3;
            transition: all 0.3s ease;
        }

        .toggle-btn.expanded .toggle-icon {
            transform: rotate(90deg);
        }

        /* Hidden Row Styling - CRITICAL for toggle functionality */
        .hidden-row {
            display: none !important;
        }

        /* Animation for row expansion */
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Indentation */
        .indent-spacer {
            display: inline-block;
            width: 25px;
        }

        /* Amount Styling */
        .text-right {
            text-align: right !important;
        }

        .text-center {
            text-align: center !important;
        }

        .text-success {
            color: #2e7d32 !important;
            font-weight: 600;
        }

        .text-danger {
            color: #c62828 !important;
            font-weight: 600;
        }

        /* Hover styles for header rows */
        .account-header:hover {
            background: linear-gradient(135deg, #bbdefb 0%, #90caf9 100%) !important;
        }
    </style>

    <div class="filter-section">
        <div class="filter-box">
            <div class="d-flex flex-wrap align-items-end">
                <div class="filter-item">
                    <label for="datatableRange">
                        <i class="fa fa-calendar-alt"></i> Date Range
                    </label>
                    <input type="text" class="form-control" id="datatableRange" placeholder="Select date range" readonly>
                </div>

                <div class="filter-item">
                    <label for="filter-type">
                        <i class="fa fa-tags"></i> Account Type
                    </label>
                    <select id="filter-type" class="form-control">
                        <option value="">All Types</option>
                        <option value="Asset">Asset</option>
                        <option value="Liability">Liability</option>
                        <option value="Equity">Equity</option>
                        <option value="Income">Income</option>
                        <option value="Expense">Expense</option>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="filter-subtype">
                        <i class="fa fa-layer-group"></i> Sub Type
                    </label>
                    <select id="filter-subtype" class="form-control">
                        <option value="">All Sub Types</option>
                        <option value="Cash">Cash</option>
                        <option value="Bank">Bank</option>
                        <option value="Receivable">Receivable</option>
                        <option value="Payable">Payable</option>
                        <option value="Inventory">Inventory</option>
                        <option value="Fixed Asset">Fixed Asset</option>
                    </select>
                </div>

                <div class="filter-item">
                    <button type="button" class="btn btn-filter btn-secondary" id="reset-filters">
                        <i class="fa fa-times-circle"></i> Clear Filters
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="trial-balance-container">
            <div class="trial-balance-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h4 class="mb-1">
                            <i class="fa fa-balance-scale"></i> Trial Balance
                        </h4>
                        <div class="date-range-display" id="date-range-display"></div>
                    </div>

                    <div class="header-controls">
                        <button type="button" title="Expand All" class="btn btn-header" id="expand-all">
                            <i class="fa fa-expand"></i> Expand All
                        </button>
                        <button type="button" title="Collapse All" class="btn btn-header" id="collapse-all">
                            <i class="fa fa-compress"></i> Collapse All
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <div class="table-scroll">
                    {!! $dataTable->table(['class' => 'table trial-balance-table', 'id' => 'trial-balance-table']) !!}
                </div>
            </div>
        </div>
    </div>
    {!! $dataTable->scripts() !!}
@endsection

@push('script-page')
    <!-- Include jQuery and required libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <script>
        // Trial Balance Toggle Functionality
        $(document).ready(function() {
            console.log('Document ready, initializing trial balance components...');

            // Initialize date range picker
            initializeDateRangePicker();

            // Wait for table to be fully loaded
            const checkTableLoaded = setInterval(function() {
                if ($('#trial-balance-table').length && window.LaravelDataTables) {
                    clearInterval(checkTableLoaded);
                    console.log('Table found, initializing toggle functionality');
                    initializeToggleControls();
                }
            }, 200);

            // Handle toggle buttons on rows
            $(document).on('click', '.toggle-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const groupId = $(this).data('target');
                console.log('Toggle clicked for group:', groupId);

                if ($(this).hasClass('expanded')) {
                    collapseGroup(groupId);
                } else {
                    expandGroup(groupId);
                }
            });

            // Handle expand/collapse all buttons
            $('#expand-all').on('click', function() {
                console.log('Expanding all groups');
                $('.toggle-btn.collapsed').each(function() {
                    const groupId = $(this).data('target');
                    expandGroup(groupId);
                });
            });

            $('#collapse-all').on('click', function() {
                console.log('Collapsing all groups');
                $('.toggle-btn.expanded').each(function() {
                    const groupId = $(this).data('target');
                    collapseGroup(groupId);
                });
            });

            // Redraw event handler
            $('#trial-balance-table').on('draw.dt', function() {
                console.log('Table redrawn, reinitializing toggle states');
                initializeToggleControls();
            });
        });

        // Initialize toggle controls
        function initializeToggleControls() {
            // Set initial states for all toggle buttons (collapsed by default)
            $('.toggle-btn').addClass('collapsed').removeClass('expanded');
            $('.toggle-btn .toggle-icon').removeClass('fa-chevron-down').addClass('fa-chevron-right');

            // Hide all child rows initially
            $('.child-row').addClass('hidden-row');

            // Show headers
            $('.parent-row').removeClass('hidden-row');

            // Make sure the total rows are visible
            $('.grand-total, .net-income').removeClass('hidden-row');

            // Ensure totals are visible for collapsed groups
            $('.account-header .debit-cell, .account-header .credit-cell').show();
        }

        // Expand a group
        function expandGroup(groupId) {
            console.log('Expanding group:', groupId);

            // Update toggle button state
            const $toggleBtn = $(`.toggle-btn[data-target="${groupId}"]`);
            $toggleBtn.removeClass('collapsed').addClass('expanded');
            $toggleBtn.find('.toggle-icon').removeClass('fa-chevron-right').addClass('fa-chevron-down');

            // Hide debit/credit cells in the header row when expanded
            $toggleBtn.closest('tr').find('.debit-cell, .credit-cell').hide();

            // Show all child rows for this group
            $(`.parent-${groupId}`).removeClass('hidden-row');

            // Add nice animation
            $(`.parent-${groupId}`).css({
                'animation': 'slideDown 0.3s ease-out'
            });
        }

        // Collapse a group
        function collapseGroup(groupId) {
            console.log('Collapsing group:', groupId);

            // Update toggle button state
            const $toggleBtn = $(`.toggle-btn[data-target="${groupId}"]`);
            $toggleBtn.removeClass('expanded').addClass('collapsed');
            $toggleBtn.find('.toggle-icon').removeClass('fa-chevron-down').addClass('fa-chevron-right');

            // Show debit/credit cells in the header row when collapsed
            $toggleBtn.closest('tr').find('.debit-cell, .credit-cell').show();

            // Hide all child rows for this group
            $(`.parent-${groupId}`).addClass('hidden-row');
        }

        // Initialize date range picker
        function initializeDateRangePicker() {
            $('#datatableRange').daterangepicker({
                locale: {
                    format: 'MM/DD/YYYY',
                    separator: ' - ',
                    applyLabel: 'Apply',
                    cancelLabel: 'Cancel',
                },
                startDate: moment().startOf('year'),
                endDate: moment(),
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month')
                        .endOf('month')
                    ],
                    'This Year': [moment().startOf('year'), moment().endOf('year')]
                }
            });

            // Update date display and refresh table on date change
            $('#datatableRange').on('apply.daterangepicker', function(ev, picker) {
                const dateDisplay = picker.startDate.format('MMM DD, YYYY') + ' - ' + picker.endDate.format(
                    'MMM DD, YYYY');
                $('#date-range-display').text(dateDisplay);
                refreshTable();
            });

            // Set initial date display
            const defaultDisplay = moment().startOf('year').format('MMM DD, YYYY') + ' - ' + moment().format(
                'MMM DD, YYYY');
            $('#date-range-display').text(defaultDisplay);
        }

        // Refresh the table with filters
        function refreshTable() {
            // Get filter values
            const dateRangePicker = $('#datatableRange').data('daterangepicker');
            const startDate = dateRangePicker ? dateRangePicker.startDate.format('YYYY-MM-DD') : null;
            const endDate = dateRangePicker ? dateRangePicker.endDate.format('YYYY-MM-DD') : null;
            const subtype = $('#filter-subtype').val();
            const type = $('#filter-type').val();

            // Check if we have the DataTable object
            if (window.LaravelDataTables && window.LaravelDataTables["trial-balance-table"]) {
                const table = window.LaravelDataTables["trial-balance-table"];

                // Set the filter values for the AJAX request
                table.on('preXhr.dt', function(e, settings, data) {
                    data.startDate = startDate;
                    data.endDate = endDate;
                    data.subtype = subtype;
                    data.type = type;
                });

                // Reload the table
                table.draw();
            } else {
                console.error('DataTable not available');

                // Fallback: reload the page with parameters
                window.location.href = window.location.pathname +
                    '?startDate=' + encodeURIComponent(startDate) +
                    '&endDate=' + encodeURIComponent(endDate) +
                    '&subtype=' + encodeURIComponent(subtype) +
                    '&type=' + encodeURIComponent(type);
            }
        }

        // Filter change handlers
        $(document).ready(function() {
            $('#filter-subtype, #filter-type').on('change', function() {
                refreshTable();
            });

            $('#reset-filters').on('click', function() {
                // Reset date range
                const dateRangePicker = $('#datatableRange').data('daterangepicker');
                if (dateRangePicker) {
                    dateRangePicker.setStartDate(moment().startOf('year'));
                    dateRangePicker.setEndDate(moment());
                    $('#date-range-display').text(
                        moment().startOf('year').format('MMM DD, YYYY') + ' - ' + moment().format(
                            'MMM DD, YYYY')
                    );
                }

                // Reset dropdowns
                $('#filter-subtype, #filter-type').val('');

                // Refresh table
                refreshTable();
            });
        });
    </script>
@endpush
