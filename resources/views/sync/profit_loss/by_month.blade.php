@extends('layouts.admin')

@section('content')
    <style>
        .sticky-column {
            position: sticky !important;
            left: 0;
            z-index: 10;
            background-color: white !important;
        }

        .section-row .sticky-column {
            background-color: #f8f9fa !important;
        }

        .subtotal-row .sticky-column,
        .total-row .sticky-column {
            background-color: #f8f9fa !important;
        }

        thead th.sticky-column {
            z-index: 11 !important;
            background-color: #f8f9fa !important;
        }

        .section-row {
            background-color: #f8f9fa !important;
            font-weight: bold;
        }

        .section-row:hover {
            background-color: #e9ecef !important;
        }

        .section-row:hover .sticky-column {
            background-color: #e9ecef !important;
        }

        .section-month-total,
        .section-total-amount {
            font-weight: bold;
            color: #495057;
            font-style: italic;
        }

        .profit-loss-table {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            border-collapse: separate !important;
            border-spacing: 0;
        }

        .profit-loss-table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            white-space: nowrap;
            padding: 12px 8px;
        }

        .profit-loss-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .profit-loss-table tbody tr:hover .sticky-column {
            background-color: #f8f9fa;
        }

        .profit-loss-table tbody td {
            padding: 8px;
            border-bottom: 1px solid #f0f0f0;
        }

        .text-right {
            text-align: right !important;
        }

        .section-header {
            font-weight: 700;
            font-size: 1.05em;
            color: #495057;
            text-transform: uppercase;
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

        .child-row td:first-child {
            padding-left: 30px !important;
        }

        .amount-cell {
            text-align: right;
            display: block;
        }

        .subtotal-row {
            background-color: #f8f9fa;
            font-weight: bold;
            border-top: 1px solid #000 !important;
        }

        .subtotal-row td {
            border-top: 1px solid #000 !important;
        }

        .total-row {
            background-color: #e9ecef;
            font-weight: bold;
            border-top: 2px solid #000 !important;
        }

        .total-row td {
            border-top: 1px solid #000 !important;
        }

        .subtotal-label,
        .total-label {
            font-weight: bold;
        }

        .total-amount {
            font-weight: bold;
        }

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
        }

        .btn-view-options {
            background: transparent;
            color: #6b7280;
            border: 1px solid #d1d5db;
            padding: 8px 12px;
            font-size: 13px;
            position: relative;
            width: 100%;
        }

        .btn-view-options:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

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

        .date-range {
            font-size: 14px;
            color: #374151;
            margin: 0;
        }

        /* Compact view styles */
        .compact-view .child-row {
            display: none !important;
        }

        .compact-view .subtotal-row {
            display: none !important;
        }

        .compact-view .toggle-chevron {
            transform: rotate(-90deg);
        }
    </style>

    <div class="filter-controls">
        <div class="filter-row">
            <div class="filter-group row mb-2">
                <div class="filter-item col-md-3">
                    <label class="filter-label">From Date</label>
                    <input type="date" id="filter-start-date" class="form-control" 
                        value="{{ request('startDate', Carbon\Carbon::now()->subMonths(2)->startOfMonth()->format('Y-m-d')) }}">
                </div>

                <div class="filter-item col-md-3">
                    <label class="filter-label">To Date</label>
                    <input type="date" id="filter-end-date" class="form-control" 
                        value="{{ request('endDate', Carbon\Carbon::now()->format('Y-m-d')) }}">
                </div>

                <div class="filter-item col-md-3">
                    <label class="filter-label">Accounting method</label>
                    <select id="accounting-method" class="form-control">
                        <option value="accrual" {{ request('accountingMethod', 'accrual') == 'accrual' ? 'selected' : '' }}>Accrual</option>
                        <option value="cash" {{ request('accountingMethod') == 'cash' ? 'selected' : '' }}>Cash</option>
                    </select>
                </div>

                <div class="filter-item col-md-3 pt-4">
                    <div class="view-options" style="position: relative;">
                        <button class="btn btn-view-options" id="view-options-btn">
                            <i class="fa fa-eye"></i> View options
                        </button>
                        <div class="view-options-dropdown" id="view-options-dropdown">
                            <div class="view-option-item" data-value="normal">
                                <span class="checkmark"><i class="fa fa-check"></i></span>
                                Normal view
                            </div>
                            <div class="view-option-item" data-value="compact">
                                <span class="checkmark"><i class="fa fa-check"></i></span>
                                Compact view
                            </div>
                            <div class="view-option-item divider" data-value="expand">
                                <span class="checkmark"><i class="fa fa-check"></i></span>
                                Expand
                            </div>
                            <div class="view-option-item" data-value="collapse">
                                <span class="checkmark"><i class="fa fa-check"></i></span>
                                Collapse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-wrapper">
        <div class="d-flex flex-column w-tables rounded mt-3 bg-white">
            <div class="report-title-section p-2">
                <h2 class="report-title">Profit & Loss by Month</h2>
                <p class="date-range">
                    <span id="date-range-display">
                        @php
                            $displayStart = request('startDate', Carbon\Carbon::now()->subMonths(2)->startOfMonth()->format('Y-m-d'));
                            $displayEnd = request('endDate', Carbon\Carbon::now()->format('Y-m-d'));
                            echo Carbon\Carbon::parse($displayStart)->format('F j, Y') . ' - ' . Carbon\Carbon::parse($displayEnd)->format('F j, Y');
                        @endphp
                    </span>
                </p>
            </div>
            <div class="table-responsive p-3" id="report-content">
                {!! $dataTable->table(['class' => 'table table-hover border-0 w-100 profit-loss-table']) !!}
            </div>
        </div>
    </div>
@endsection

@push('script-page')
    @include('sections.datatable_js')
    <script>
        $(document).ready(function() {
            // Initialize view state
            window.viewState = {
                viewType: 'normal',
                expandState: 'expand'
            };

            initializeViewOptions();
            setupEventListeners();

            // Initialize table state after DataTable loads
            setTimeout(function() {
                initializeTableState();
                applyViewState();
            }, 500);
        });

        function initializeViewOptions() {
            updateViewCheckmarks();
        }

        function setupEventListeners() {
            // Date filter changes
            $('#filter-start-date, #filter-end-date, #accounting-method').on('change', function() {
                const startDate = $('#filter-start-date').val();
                const endDate = $('#filter-end-date').val();
                const accountingMethod = $('#accounting-method').val();

                if (startDate && endDate) {
                    if (new Date(startDate) > new Date(endDate)) {
                        alert('Start date must be before end date');
                        return;
                    }

                    const url = new URL(window.location.href);
                    url.searchParams.set('startDate', startDate);
                    url.searchParams.set('endDate', endDate);
                    url.searchParams.set('accountingMethod', accountingMethod);
                    window.location.href = url.toString();
                }
            });

            // View options dropdown
            $('#view-options-btn').on('click', function(e) {
                e.stopPropagation();
                $('#view-options-dropdown').toggle();
            });

            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.view-options').length) {
                    $('#view-options-dropdown').hide();
                }
            });

            // View option selection
            $('.view-option-item').off('click.viewOptions').on('click.viewOptions', function(e) {
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

            // Section toggle click
            $(document).on('click', '.toggle-section', handleSectionToggle);

            // DataTable events
            $('#profit-loss-table').on('draw.dt', function() {
                setTimeout(function() {
                    initializeTableState();
                    applyViewState();
                }, 100);
            });
        }

        function setViewType(type) {
            if (type === 'compact') {
                window.viewState.viewType = 'compact';
                window.viewState.expandState = 'collapse';
            } else if (type === 'normal') {
                window.viewState.viewType = 'normal';
                window.viewState.expandState = 'expand';
            }
        }

        function setExpandState(state) {
            if (state === 'collapse') {
                window.viewState.expandState = 'collapse';
                window.viewState.viewType = 'compact';
            } else if (state === 'expand') {
                window.viewState.expandState = 'expand';
                window.viewState.viewType = 'normal';
            }
        }

        function applyViewState() {
            const $reportContent = $('#report-content');
            
            // Remove compact view class
            $reportContent.removeClass('compact-view');

            // Apply view type
            if (window.viewState.viewType === 'compact') {
                $reportContent.addClass('compact-view');
            }

            // Apply expand/collapse state
            if (window.viewState.expandState === 'expand') {
                handleExpandAll();
            } else {
                handleCollapseAll();
            }
        }

        function updateViewCheckmarks() {
            $('.view-option-item').removeClass('active');
            
            // Mark view type as active
            $('.view-option-item[data-value="' + window.viewState.viewType + '"]').addClass('active');
            
            // Mark expand state as active
            $('.view-option-item[data-value="' + window.viewState.expandState + '"]').addClass('active');
        }

        function handleExpandAll() {
            $('.child-row, .subtotal-row').show();
            $('.toggle-chevron').removeClass('fa-chevron-right').addClass('fa-chevron-down');
            
            // Hide section totals when expanded
            $('.section-month-total, .section-total-amount').hide();
        }

        function handleCollapseAll() {
            $('.child-row, .subtotal-row').hide();
            $('.toggle-chevron').removeClass('fa-chevron-down').addClass('fa-chevron-right');
            
            // Show section totals when collapsed
            $('.section-month-total, .section-total-amount').show();
        }

        function handleSectionToggle(e) {
            e.preventDefault();

            // Don't allow manual toggle in compact view
            if (window.viewState.viewType === 'compact') {
                return;
            }

            const $this = $(this);
            const group = $this.data('group');
            const $chevron = $this.find('.toggle-chevron');
            const $childRows = $('.group-' + group);
            
            // Get section totals for this group
            const $sectionMonthTotals = $('.section-month-total[data-group="' + group + '"]');
            const $sectionTotalAmount = $('.section-total-amount[data-group="' + group + '"]');

            if ($chevron.length === 0) return;

            if ($chevron.hasClass('fa-chevron-down')) {
                // Collapse this section
                $childRows.hide();
                $chevron.removeClass('fa-chevron-down').addClass('fa-chevron-right');
                
                // Show section totals when collapsed
                $sectionMonthTotals.show();
                $sectionTotalAmount.show();
            } else {
                // Expand this section
                $childRows.show();
                $chevron.removeClass('fa-chevron-right').addClass('fa-chevron-down');
                
                // Hide section totals when expanded
                $sectionMonthTotals.hide();
                $sectionTotalAmount.hide();
            }
        }

        function initializeTableState() {
            // Show all rows by default
            $('.child-row, .subtotal-row').show();
            
            // Set all chevrons to expanded state
            $('.toggle-chevron').removeClass('fa-chevron-right').addClass('fa-chevron-down');

            // Add chevrons to sections that have children
            $('.toggle-section').each(function() {
                const group = $(this).data('group');
                const hasChildren = $('.group-' + group).length > 0;
                const $chevron = $(this).find('.toggle-chevron');

                if (hasChildren && $chevron.length === 0) {
                    $(this).prepend('<i class="fas fa-chevron-down toggle-chevron mr-2"></i>');
                    $(this).css('cursor', 'pointer');
                } else if (!hasChildren) {
                    $chevron.remove();
                    $(this).css('cursor', 'default');
                }
            });
        }
    </script>
    {!! $dataTable->scripts() !!}
@endpush