@extends('layouts.admin')

@section('content')
    @php
        use Carbon\Carbon;

        // Prefer request() values if present
        $reqStart = request()->filled('startDate') ? request('startDate') : null;
        $reqEnd   = request()->filled('endDate') ? request('endDate') : null;

        // Visible format for daterangepicker text input (m/d/Y)
        $visibleStart = $reqStart ? Carbon::parse($reqStart)->format('m/d/Y') : Carbon::now()->startOfYear()->format('m/d/Y');
        $visibleEnd   = $reqEnd ? Carbon::parse($reqEnd)->format('m/d/Y') : Carbon::now()->format('m/d/Y');

        // Hidden inputs for JS/server (Y-m-d)
        $hiddenStart = $reqStart ? Carbon::parse($reqStart)->format('Y-m-d') : Carbon::now()->startOfYear()->format('Y-m-d');
        $hiddenEnd   = $reqEnd ? Carbon::parse($reqEnd)->format('Y-m-d') : Carbon::now()->format('Y-m-d');

        // Selected period and accounting method from request or defaults
        $selectedPeriod = request('period', 'this_year');
        $selectedAccounting = request('accountingMethod', 'accrual');

        // Title friendly dates
        $titleStart = $reqStart ? Carbon::parse($reqStart)->format('F j, Y') : Carbon::now()->startOfYear()->format('F j, Y');
        $titleEnd = $reqEnd ? Carbon::parse($reqEnd)->format('F j, Y') : Carbon::now()->format('F j, Y');
    @endphp

   <style>
    /* General Table Styles */
    .section-row { background-color: #f2f2f2 !important; font-weight: bold; }
    .profit-loss-table { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; position: relative; }
    .profit-loss-table thead th { background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; font-weight: 600; color: #495057; }
    .profit-loss-table tbody tr:hover { background-color: #f8f9fa; }
    .text-right { text-align: right !important; }
    .section-header { font-weight: 700; font-size: 1.1em; color: #495057; text-transform: uppercase; }
    .toggle-section { user-select: none; }
    .toggle-section i { margin-right: 10px; }
    .toggle-section[style*="pointer"]:hover { color: #007bff; }
    .toggle-chevron { transition: transform 0.2s ease; color: #007bff; font-size: 12px; }
    .child-row td:first-child { padding-left: 30px !important; }
    .amount-cell { text-align: right; display: block; }
    .subtotal-row .total-amount { border-top: 1px solid #000; font-weight: bold; }
    .total-row .total-amount { border-top: 2px solid #000; border-bottom: 2px double #000; font-weight: bold; }
    .section-row { background-color: #f8f9fa !important; font-weight: bold; }
    .section-row:hover { background-color: #e9ecef !important; }
    .subtotal-row { background-color: #f8f9fa; font-weight: bold; }
    .total-row { background-color: #e9ecef; font-weight: bold; border-top: 2px solid #dee2e6; }
    .subtotal-label, .total-label { font-weight: bold; }

    /* Fixed Column Styles */
    .profit-loss-table thead th:first-child,
    .profit-loss-table tbody td:first-child { position: sticky !important; left: 0; z-index: 10; background-color: white !important; }
    .profit-loss-table thead th:first-child { z-index: 11 !important; background-color: #f8f9fa !important; }
    .section-row td:first-child { background-color: #f8f9fa !important; }
    .section-row:hover td:first-child { background-color: #e9ecef !important; }
    .subtotal-row td:first-child { background-color: #f8f9fa !important; }
    .total-row td:first-child { background-color: #e9ecef !important; }
    .profit-loss-table thead th:first-child::after,
    .profit-loss-table tbody td:first-child::after { content: ''; position: absolute; top: 0; right: 0; bottom: 0; width: 1px; background: linear-gradient(to right, rgba(0,0,0,0.1), transparent); pointer-events: none; }
    .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }

    /* Filter Controls */
    .filter-controls { background: white; padding: 20px 24px; border-bottom: 1px solid #e6e6e6; }
    .filter-item { display: flex; flex-direction: column; min-width: 140px; }
    .filter-label { font-size: 12px; color: #6b7280; margin-bottom: 6px; font-weight: 500; }
    .form-control { border: 1px solid #d1d5db; border-radius: 4px; padding: 8px 12px; font-size: 13px; background: white; color: #262626; height: 36px; }
    .form-control:focus { outline: none; border-color: #0969da; box-shadow: 0 0 0 2px rgba(9, 105, 218, 0.1); }
    .report-title-section { text-align: center; padding: 32px 24px 24px; border-bottom: 1px solid #e6e6e6; }
    .report-title { font-size: 24px; font-weight: 700; color: #262626; margin: 0 0 8px; }
    .date-range { font-size: 14px; color: #374151; margin: 0; }
    .compact-view .child-row { display: none !important; }
    .compact-view .subtotal-row { display: none !important; }
    .action-buttons-row { display: flex; justify-content: flex-end; gap: 12px; }
    .btn-outline { background: white; border: 1px solid #d1d5db; color: #374151; padding: 8px 12px; font-size: 13px; }
    .btn-outline:hover { background: #f9fafb; border-color: #9ca3af; }
</style>
    <!-- Filter Controls -->
    <div class="filter-controls">
        <div class="filter-row">
            <div class="filter-group row mb-2">
                <div class="filter-item col-md-3">
                    <label class="filter-label">Report period</label>
                    <select id="filter-period" class="form-control">
                        <option value="this_year" {{ $selectedPeriod === 'this_year' ? 'selected' : '' }}>This year</option>
                        <option value="today" {{ $selectedPeriod === 'today' ? 'selected' : '' }}>Today</option>
                        <option value="this_week" {{ $selectedPeriod === 'this_week' ? 'selected' : '' }}>This week</option>
                        <option value="this_month" {{ $selectedPeriod === 'this_month' ? 'selected' : '' }}>This month</option>
                        <option value="this_quarter" {{ $selectedPeriod === 'this_quarter' ? 'selected' : '' }}>This quarter</option>
                        <option value="last_month" {{ $selectedPeriod === 'last_month' ? 'selected' : '' }}>Last month</option>
                        <option value="last_quarter" {{ $selectedPeriod === 'last_quarter' ? 'selected' : '' }}>Last quarter</option>
                        <option value="last_year" {{ $selectedPeriod === 'last_year' ? 'selected' : '' }}>Last year</option>
                        <option value="custom_date" {{ $selectedPeriod === 'custom_date' ? 'selected' : '' }}>Custom dates</option>
                    </select>
                </div>

                <div class="filter-item col-md-3">
                    <label class="filter-label">Date Range</label>
                    <input type="text" id="daterange" class="form-control date-input"
                        value="{{ $visibleStart }} - {{ $visibleEnd }}">
                    <input type="hidden" id="filter-start-date" value="{{ $hiddenStart }}">
                    <input type="hidden" id="filter-end-date" value="{{ $hiddenEnd }}">
                </div>

                <div class="filter-item col-md-3">
                    <label class="filter-label">Accounting method</label>
                    <select id="accounting-method" class="form-control">
                        <option value="accrual" {{ $selectedAccounting === 'accrual' ? 'selected' : '' }}>Accrual</option>
                        <option value="cash" {{ $selectedAccounting === 'cash' ? 'selected' : '' }}>Cash</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="content-wrapper">
        <div class="d-flex flex-column w-tables rounded mt-3 bg-white">
            <div class="report-title-section p-2">
                <h2 class="report-title">Profit & Loss Statement (Quarterly)</h2>
                <p class="date-range">
                    <span id="date-range-display">{{ $titleStart }} - {{ $titleEnd }}</span>
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
            if (typeof moment === 'undefined' || typeof $.fn.daterangepicker === 'undefined') {
                console.error('Required libraries not loaded');
                return;
            }

            setupEventListeners();
            initDaterangepicker();   // initialize from hidden inputs (server-provided)
            updateDateDisplay();     // update title & label from hidden inputs
            setTimeout(initializeTableState, 1000);
        });

        function setupEventListeners() {
            $('#profit-loss-table').on('preXhr.dt', handleDataTablePreXhr);
            $('#profit-loss-table').on('xhr.dt', handleDataTableXhr); // normalize server JSON
            $('#profit-loss-table').on('draw.dt', handleDataTableDraw);
            $(document).on('click', '.toggle-section', handleSectionToggle);

            $('#accounting-method').on('change', refreshTable);
            $('#filter-period').on('change', function() { updateDateRange($(this).val()); });
        }

        function handleDataTablePreXhr(e, settings, data) {
            data.startDate = $('#filter-start-date').val();
            data.endDate = $('#filter-end-date').val();
            data.accountingMethod = $('#accounting-method').val();
        }

        /**
         * Normalize AJAX JSON so DataTables never sees missing keys.
         * Ensures every row has each expected column and *_display keys.
         * Also ensures numeric keys exist and display shows 0.00 when empty/zero.
         */
        function handleDataTableXhr(e, settings, json) {
            try {
                if (!json || !Array.isArray(json.data)) return;

                var expectedColumns = (settings.aoColumns || []).map(function(col) {
                    return col.mData !== undefined ? col.mData : (col.data !== undefined ? col.data : col.name);
                }).filter(Boolean);

                if (expectedColumns.length === 0) {
                    expectedColumns = [];
                    $('#profit-loss-table thead th').each(function() {
                        var key = $(this).data('key') || $(this).attr('data-key') || $(this).attr('data-column');
                        if (key) expectedColumns.push(key);
                    });
                }

                json.data.forEach(function(row) {
                    row.is_section_header = row.is_section_header || false;
                    row.is_total = row.is_total || false;
                    row.is_subtotal = row.is_subtotal || false;

                    expectedColumns.forEach(function(key) {
                        if (!(key in row)) row[key] = '';

                        if (typeof key === 'string' && key.endsWith('_display')) {
                            var base = key.slice(0, -8);
                            if (!(base in row)) row[base] = 0;

                            if (row.is_section_header) {
                                row[key] = '';
                            } else {
                                row[key] = numberFormatDisplay(row[base]); // always show 0.00 for empty/zero
                            }
                        }
                    });

                    if (!('account_name' in row)) row.account_name = row.name ? row.name : '';

                    if (!('total' in row) || row.total === null || row.total === undefined) row.total = 0;
                    if (row.is_section_header) {
                        row.total_display = row.total_display || '';
                    } else {
                        row.total_display = numberFormatDisplay(row.total);
                    }
                });
            } catch (err) {
                console.error('Error normalizing DataTable JSON:', err);
            }
        }

        function numberFormatDisplay(amount) {
            if (amount === null || amount === undefined) amount = 0;
            var n = Number(amount) || 0;
            return '<span class="amount-cell">' + n.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) + '</span>';
        }

        function handleDataTableDraw() {
            setTimeout(initializeTableState, 100);
        }

        function handleSectionToggle(e) {
            e.preventDefault();
            const $this = $(this);
            const group = $this.data('group');
            const $chevron = $this.find('.toggle-chevron');
            const $childRows = $('.group-' + group);

            if ($chevron.length === 0) return;
            if ($chevron.hasClass('fa-chevron-down')) {
                $childRows.hide();
                $chevron.removeClass('fa-chevron-down').addClass('fa-chevron-right');
            } else {
                $childRows.show();
                $chevron.removeClass('fa-chevron-right').addClass('fa-chevron-down');
            }
        }

        function initializeTableState() {
            $('.child-row, .subtotal-row').show();
            $('.toggle-chevron').removeClass('fa-chevron-right').addClass('fa-chevron-down');
        }

        /**
         * Initialize daterangepicker from the server-provided hidden inputs.
         * We DO NOT overwrite server values on page load.
         */
        function initDaterangepicker() {
            var start = $('#filter-start-date').val();
            var end = $('#filter-end-date').val();

            $('#daterange').daterangepicker({
                startDate: moment(start, 'YYYY-MM-DD'),
                endDate: moment(end, 'YYYY-MM-DD'),
                opens: 'left',
                autoApply: true,
                locale: { format: 'MM/DD/YYYY' }
            }, function(startMoment, endMoment) {
                $('#filter-start-date').val(startMoment.format('YYYY-MM-DD'));
                $('#filter-end-date').val(endMoment.format('YYYY-MM-DD'));
                updateDateDisplay();
                refreshTable();
            });
        }

        function updateDateRange(period) {
            const today = moment();
            let startDate, endDate;

            switch (period) {
                case 'today':
                    startDate = today.clone(); endDate = today.clone(); break;
                case 'this_week':
                    startDate = today.clone().startOf('week'); endDate = today.clone().endOf('week'); break;
                case 'this_month':
                    startDate = today.clone().startOf('month'); endDate = today.clone().endOf('month'); break;
                case 'this_quarter':
                    startDate = today.clone().startOf('quarter'); endDate = today.clone().endOf('quarter'); break;
                case 'this_year':
                    startDate = today.clone().startOf('year'); endDate = today.clone().endOf('year'); break;
                case 'last_month':
                    startDate = today.clone().subtract(1, 'month').startOf('month'); endDate = today.clone().subtract(1, 'month').endOf('month'); break;
                case 'last_quarter':
                    startDate = today.clone().subtract(1, 'quarter').startOf('quarter'); endDate = today.clone().subtract(1, 'quarter').endOf('quarter'); break;
                case 'last_year':
                    startDate = today.clone().subtract(1, 'year').startOf('year'); endDate = today.clone().subtract(1, 'year').endOf('year'); break;
                default:
                    startDate = today.clone().startOf('year'); endDate = today.clone();
            }

            $('#filter-start-date').val(startDate.format('YYYY-MM-DD'));
            $('#filter-end-date').val(endDate.format('YYYY-MM-DD'));
            $('#daterange').data('daterangepicker').setStartDate(startDate);
            $('#daterange').data('daterangepicker').setEndDate(endDate);

            updateDateDisplay();
            refreshTable();
        }

        function updateDateDisplay() {
            const startDate = moment($('#filter-start-date').val(), 'YYYY-MM-DD');
            const endDate = moment($('#filter-end-date').val(), 'YYYY-MM-DD');
            $('#date-range-display').text(startDate.format('MMMM D, YYYY') + ' - ' + endDate.format('MMMM D, YYYY'));
        }

        /**
         * Reload page with query params so server re-renders initial values (title + hidden inputs).
         * If you prefer Ajax update, I can change this to table.ajax.reload(null, false) instead.
         */
        function refreshTable() {
            let startDate = $('#filter-start-date').val();
            let endDate = $('#filter-end-date').val();
            let accountingMethod = $('#accounting-method').val();
            let reportPeriod = $('#filter-period').val();

            let params = new URLSearchParams(window.location.search);
            params.set('startDate', startDate);
            params.set('endDate', endDate);
            params.set('accountingMethod', accountingMethod);
            params.set('period', reportPeriod);

            // Reload page so server handles initial render (report title, filter defaults)
            window.location.search = params.toString();
        }
    </script>

    {!! $dataTable->scripts() !!}
@endpush
