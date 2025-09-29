@extends('layouts.admin')
@section('content')
    <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
        }

        .dash-content {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .main-container {
            background: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .1);
        }

        .header-section {
            padding: 16px 24px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h4 {
            margin: 0;
            font-size: 16px;
            font-weight: 500;
            color: #333;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .last-updated {
            color: #666;
            font-size: 13px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border: none;
            background: none;
            color: #666;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-icon:hover {
            background-color: #f1f3f4;
            color: #333;
        }

        .btn-save {
            background-color: #1a73e8;
            color: #fff;
            border: none;
            padding: 6px 16px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 500;
        }

        .btn-save:hover {
            background-color: #1557b0;
        }

        .filter-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            border-bottom: 1px solid #e9ecef;
            background-color: #fafbfc;
        }

        .filter-row {
            display: flex;
            align-items: end;
            gap: 16px;
            margin-bottom: 12px;
        }

        .filter-group {
            display: flex;
            align-items: end;
            gap: 12px;
        }

        .filter-item {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-size: 12px;
            color: #5f6368;
            margin-bottom: 4px;
            font-weight: 500;
        }

        .form-control,
        .form-select {
            height: 32px;
            font-size: 13px;
            border: 1px solid #dadce0;
            border-radius: 4px;
            padding: 0 8px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #1a73e8;
            box-shadow: 0 0 0 1px #1a73e8;
        }

        .options-row {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .view-options,
        .columns-btn,
        .filter-btn,
        .general-options {
            display: flex;
            align-items: center;
            gap: 6px;
            border-radius: 4px;
            font-size: 13px;
            color: #3c4043;
            text-decoration: none;
        }

        .view-options:hover,
        .columns-btn:hover,
        .filter-btn:hover,
        .general-options:hover {
            background-color: #f8f9fa;
            color: #3c4043;
            text-decoration: none;
        }

        .filter-count {
            background-color: #1a73e8;
            color: #fff;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 4px;
        }

        .report-content {
            padding: 24px;
        }

        .report-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .report-title {
            font-size: 24px;
            font-weight: 600;
            color: #202124;
            margin-bottom: 8px;
        }

        .company-name {
            font-size: 16px;
            color: #5f6368;
            margin-bottom: 4px;
        }

        .date-range {
            font-size: 14px;
            color: #5f6368;
        }

        .table-container {
            margin-top: 24px;
            overflow-x: auto;
            width: 100%;
        }

        .ledger-table {
            width: 100%;
            font-size: 13px;
            white-space: nowrap;
        }

        .dataTables_wrapper .dataTables_scroll {
            width: 100% !important;
        }

        .dataTables_wrapper .dataTables_scrollHead {
            overflow: hidden !important;
        }

        .dataTables_wrapper .dataTables_scrollBody {
            overflow-y: auto !important;
            overflow-x: auto !important;
        }

        .ledger-table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #5f6368;
            padding: 12px 8px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .ledger-table tbody td {
            padding: 8px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }

        .ledger-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .account-parent {
            font-weight: 600;
            color: #202124;
        }

        .account-child {
            padding-left: 20px;
            color: #5f6368;
        }

        .balance-positive {
            color: #137333;
        }

        .balance-negative {
            color: #d93025;
        }

        .text-right {
            text-align: right;
        }

        .expandable-row {
            cursor: pointer;
        }

        .expand-icon {
            margin-right: 8px;
            transition: transform .2s;
        }

        .expand-icon.expanded {
            transform: rotate(90deg);
        }

        /* Right-side drawer modals */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .35);
            z-index: 1060;
        }

        .general-options-modal,
        .columns-modal {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            width: 360px;
            max-width: 90vw;
            background: #fff;
            box-shadow: -2px 0 10px rgba(0, 0, 0, .15);
            overflow-y: auto;
            z-index: 1070;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #e6e6e6;
            background: #f9fafb;
        }

        .modal-content {
            padding: 16px 20px 24px;
        }

        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                flex-direction: column;
            }

            .options-row {
                flex-wrap: wrap;
            }
        }
    </style>

    <div class="main-container">
        <!-- Header Section -->
        <div class="header-section">
            <div class="header-left">
                <h4>Sales by Customer Type Detail</h4>
            </div>
            <div class="header-right">
                <span class="last-updated">Last updated <span id="last-updated-mins">just now</span></span>
                <div class="header-actions">
                    <button class="btn-icon" title="Refresh" onclick="refreshData()"><i class="fas fa-sync-alt"></i></button>
                    <button class="btn-icon" title="Print" onclick="printReport()"><i class="fas fa-print"></i></button>
                    <button class="btn-icon" title="Export" onclick="exportReport()"><i
                            class="fas fa-external-link-alt"></i></button>
                    <button class="btn-icon" title="More options"><i class="fas fa-ellipsis-v"></i></button>
                    <button class="btn-save">Save As</button>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-row">
                <div class="filter-group">
                    <div class="filter-item">
                        <label class="filter-label">Report period</label>
                        <select class="form-select" id="report-period" style="width: 160px;">
                            <option value="all_dates">All Dates</option>
                            <option value="today">Today</option>
                            <option value="this_week">This week</option>
                            <option value="this_month">This month</option>
                            <option value="this_quarter">This quarter</option>
                            <option value="this_year">This year</option>
                            <option value="last_week">Last week</option>
                            <option value="last_month">Last month</option>
                            <option value="last_quarter">Last quarter</option>
                            <option value="last_year">Last year</option>
                            <option value="last_7_days">Last 7 days</option>
                            <option value="last_30_days">Last 30 days</option>
                            <option value="last_90_days">Last 90 days</option>
                            <option value="last_12_months">Last 12 months</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label class="filter-label">From</label>
                        <input type="date" class="form-control" id="start-date" value="{{ $filter['startDateRange'] }}"
                            style="width: 140px;">
                    </div>

                    <div class="filter-item">
                        <label class="filter-label">To</label>
                        <input type="date" class="form-control" id="end-date" value="{{ $filter['endDateRange'] }}"
                            style="width: 140px;">
                    </div>

                    <div class="filter-item">
                        <label class="filter-label">Accounting method</label>
                        <select class="form-select" id="accounting-method" style="width: 120px;">
                            <option value="accrual"
                                {{ ($filter['accountingMethod'] ?? 'accrual') == 'accrual' ? 'selected' : '' }}>Accrual
                            </option>
                            <option value="cash"
                                {{ ($filter['accountingMethod'] ?? 'accrual') == 'cash' ? 'selected' : '' }}>Cash</option>
                        </select>
                    </div>

                    <div class="filter-item">
                        <a href="#" class="view-options" id="view-options-btn" style="width: 120px;">
                            <label class="filter-label"></label>
                            <i class="fas fa-eye"></i>
                            View options
                        </a>
                    </div>
                </div>
            </div>

            <div class="options-row mt-1">
                <a href="#" class="columns-btn" id="columns-btn">
                    <i class="fas fa-columns"></i>
                    Columns
                </a>
                <a href="#" class="filter-btn" id="filter-btn">
                    <i class="fas fa-filter"></i>
                    Filter
                </a>
                <a href="#" class="general-options" id="general-options-btn">
                    <i class="bi bi-sliders2-vertical"></i>
                    General options
                </a>
            </div>
        </div>

        <!-- Report Content -->
        <div class="report-content">
            <div class="report-header">
                <h1 class="report-title">Sales by Customer Type Detail</h1>
                <p class="company-name">{{ $user->name ?? "Craig's Design and Landscaping Services" }}</p>
                <p class="date-range">
                    <span id="display-date-range">
                        {{ \Carbon\Carbon::parse($filter['startDateRange'])->format('F j, Y') }} -
                        {{ \Carbon\Carbon::parse($filter['endDateRange'])->format('F j, Y') }}
                    </span>
                </p>
            </div>

            <div class="table-container">
                <table class="table ledger-table" id="ledger-table">
                    <thead>
                        <tr>
                            <th>Transaction Type</th>
                            <th>Transaction Date</th>
                            <th>Invoice Number / Num</th>
                            <th>Memo/Description</th>
                            <th>Customer Name</th>
                            <th class="text-right">Quantity</th>
                            <th class="text-right">Sales Price</th>
                            <th class="text-right">Amount</th>
                            <th class="text-right">Balance</th>
                            <th class="text-right">Sales With Tax</th>
                        </tr>
                    </thead>
                    <tbody><!-- DataTables loads rows --></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- General Options Drawer -->
    <div class="modal-overlay" id="general-options-overlay" style="display:none;">
        <div class="general-options-modal">
            <div class="modal-header">
                <h5>General options</h5>
                <button type="button" class="btn-close" id="close-general-options">&times;</button>
            </div>
            <div class="modal-content">
                <div class="option-section">
                    <h6 class="section-title">Number format</h6>
                    <div class="option-group">
                        <label class="checkbox-label"><input type="checkbox" id="divide-by-1000"> Divide by 1000</label>
                        <label class="checkbox-label"><input type="checkbox" id="hide-zero-amounts"> Don't show zero
                            amounts</label>
                        <label class="checkbox-label"><input type="checkbox" id="round-whole-numbers"> Round to the
                            nearest whole number</label>
                        <div class="negative-format-group">
                            <select id="negative-format" class="form-control" style="width:100px;">
                                <option value="-100" selected>-100</option>
                                <option value="(100)">(100)</option>
                                <option value="100-">100-</option>
                            </select>
                            <label class="checkbox-label"><input type="checkbox" id="show-in-red"> Show in red</label>
                        </div>
                    </div>
                </div>

                <div class="option-section">
                    <h6 class="section-title">Header</h6>
                    <div class="option-group">
                        <label class="checkbox-label"><input type="checkbox" id="company-logo"> Company logo</label>
                        <label class="checkbox-label"><input type="checkbox" id="report-period-checkbox" checked> Report
                            period</label>
                        <label class="checkbox-label"><input type="checkbox" id="company-name-checkbox" checked> Company
                            name</label>
                        <label class="alignment-label">Header alignment</label>
                        <select id="header-alignment" class="form-control" style="max-width:140px;">
                            <option value="center" selected>Center</option>
                            <option value="left">Left</option>
                            <option value="right">Right</option>
                        </select>
                    </div>
                </div>

                <div class="option-section">
                    <h6 class="section-title">Footer</h6>
                    <div class="option-group">
                        <label class="checkbox-label"><input type="checkbox" id="date-prepared" checked> Date
                            prepared</label>
                        <label class="checkbox-label"><input type="checkbox" id="time-prepared" checked> Time
                            prepared</label>
                        <label class="checkbox-label"><input type="checkbox" id="report-basis" checked> Report
                            basis</label>
                        <label class="alignment-label">Footer alignment</label>
                        <select id="footer-alignment" class="form-control" style="max-width:140px;">
                            <option value="center" selected>Center</option>
                            <option value="left">Left</option>
                            <option value="right">Right</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Columns Drawer -->
    <div class="modal-overlay" id="columns-overlay" style="display:none;">
        <div class="columns-modal">
            <div class="modal-header">
                <h5>Columns</h5>
                <button type="button" class="btn-close" id="close-columns">&times;</button>
            </div>
            <div class="modal-content">
                <div class="columns-list">
                    <div class="column-item" data-column="0"><label class="checkbox-label"><input type="checkbox"
                                checked> Transaction Type</label></div>
                    <div class="column-item" data-column="1"><label class="checkbox-label"><input type="checkbox"
                                checked> Transaction Date</label></div>
                    <div class="column-item" data-column="2"><label class="checkbox-label"><input type="checkbox"
                                checked> Invoice Number / Num</label></div>
                    <div class="column-item" data-column="3"><label class="checkbox-label"><input type="checkbox"
                                checked> Memo/Description</label></div>
                    <div class="column-item" data-column="4"><label class="checkbox-label"><input type="checkbox"
                                checked> Customer Name</label></div>
                    <div class="column-item" data-column="5"><label class="checkbox-label"><input type="checkbox"
                                checked> Quantity</label></div>
                    <div class="column-item" data-column="6"><label class="checkbox-label"><input type="checkbox"
                                checked> Sales Price</label></div>
                    <div class="column-item" data-column="7"><label class="checkbox-label"><input type="checkbox"
                                checked> Amount</label></div>
                    <div class="column-item" data-column="8"><label class="checkbox-label"><input type="checkbox"
                                checked> Balance</label></div>
                    <div class="column-item" data-column="9"><label class="checkbox-label"><input type="checkbox"
                                checked> Sales With Tax</label></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/colreorder/1.7.0/js/dataTables.colReorder.min.js"></script>
    <script src="https://cdn.datatables.net/fixedheader/3.4.0/js/dataTables.fixedHeader.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/colreorder/1.7.0/css/colReorder.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/fixedheader/3.4.0/css/fixedHeader.dataTables.min.css">

    <script>
        $(document).ready(function() {
            // Initialize DataTable
            let table = $('#ledger-table').DataTable({
                processing: true,
                serverSide: true,
                colReorder: true,
                scrollX: true,
                responsive: false,
                scrollY: '420px',
                scrollCollapse: true,
                fixedHeader: true,
                ajax: {
                    url: "{{ route('report.salesbyCustomerTypeDetail') }}",
                    data: function(d) {
                        d.start_date = $('#start-date').val();
                        d.end_date = $('#end-date').val();
                        d.accounting_method = $('#accounting-method').val();
                        d.report_period = $('#report-period').val();
                        d.reportOptions = window.reportOptions || {};
                    },
                    dataSrc: function(json) {
                        console.log(json); // 🔎 See if yajra returns data or errors
                        return json.data;
                    }
                },

                columns: [{
                        data: 'transaction_type',
                        name: 'transaction_type'
                    },
                    {
                        data: 'transaction_date',
                        name: 'transaction_date'
                    },
                    {
                        data: 'invoice_number',
                        name: 'invoice_number'
                    },
                    {
                        data: 'memo_description',
                        name: 'memo_description'
                    },
                    {
                        data: 'customer_name',
                        name: 'customer_name'
                    },
                    {
                        data: 'quantity',
                        name: 'quantity',
                        className: 'text-right'
                    },
                    {
                        data: 'sales_price',
                        name: 'sales_price',
                        className: 'text-right'
                    },
                    {
                        data: 'amount',
                        name: 'amount',
                        className: 'text-right'
                    },
                    {
                        data: 'balance',
                        name: 'balance',
                        className: 'text-right'
                    },
                    {
                        data: 'sales_with_tax',
                        name: 'sales_with_tax',
                        className: 'text-right'
                    },
                ],
                dom: 't',
                paging: false,
                searching: false,
                info: false,
                ordering: false
            });

            // Update “last updated” ticker
            let lastUpdatedStart = Date.now();
            setInterval(function() {
                let mins = Math.max(0, Math.floor((Date.now() - lastUpdatedStart) / 60000));
                $('#last-updated-mins').text(mins === 0 ? 'just now' : (mins + ' minutes ago'));
            }, 30000);

            // Filter interactions
            $('#start-date, #end-date').on('change', function() {
                updateHeaderDate();
                table.draw();
            });
            $('#accounting-method').on('change', function() {
                table.draw();
            });

            $('#report-period').on('change', function() {
                let period = $(this).val();
                let today = moment(),
                    s = null,
                    e = null;

                switch (period) {
                    case 'today':
                        s = today.clone().startOf('day');
                        e = today.clone().endOf('day');
                        break;
                    case 'this_week':
                        s = today.clone().startOf('week');
                        e = today.clone().endOf('week');
                        break;
                    case 'this_month':
                        s = today.clone().startOf('month');
                        e = today.clone().endOf('month');
                        break;
                    case 'this_quarter':
                        s = today.clone().startOf('quarter');
                        e = today.clone().endOf('quarter');
                        break;
                    case 'this_year':
                        s = today.clone().startOf('year');
                        e = today.clone().endOf('year');
                        break;
                    case 'last_week':
                        s = today.clone().subtract(1, 'week').startOf('week');
                        e = today.clone().subtract(1, 'week').endOf('week');
                        break;
                    case 'last_month':
                        s = today.clone().subtract(1, 'month').startOf('month');
                        e = today.clone().subtract(1, 'month').endOf('month');
                        break;
                    case 'last_quarter':
                        s = today.clone().subtract(1, 'quarter').startOf('quarter');
                        e = today.clone().subtract(1, 'quarter').endOf('quarter');
                        break;
                    case 'last_year':
                        s = today.clone().subtract(1, 'year').startOf('year');
                        e = today.clone().subtract(1, 'year').endOf('year');
                        break;
                    case 'last_7_days':
                        s = today.clone().subtract(6, 'days').startOf('day');
                        e = today.clone().endOf('day');
                        break;
                    case 'last_30_days':
                        s = today.clone().subtract(29, 'days').startOf('day');
                        e = today.clone().endOf('day');
                        break;
                    case 'last_90_days':
                        s = today.clone().subtract(89, 'days').startOf('day');
                        e = today.clone().endOf('day');
                        break;
                    case 'last_12_months':
                        s = today.clone().subtract(11, 'months').startOf('month');
                        e = today.clone().endOf('month');
                        break;
                    case 'all_dates':
                        s = moment('2000-01-01');
                        e = today.clone().endOf('day');
                        break;
                    case 'custom':
                    default:
                        return; // leave as-is
                }

                if (s && e) {
                    $('#start-date').val(s.format('YYYY-MM-DD'));
                    $('#end-date').val(e.format('YYYY-MM-DD'));
                    updateHeaderDate();
                    table.draw();
                }
            });

            function updateHeaderDate() {
                const s = new Date($('#start-date').val());
                const e = new Date($('#end-date').val());
                const options = {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                };
                $('#display-date-range').text(
                    s.toLocaleDateString('en-US', options) + ' - ' + e.toLocaleDateString('en-US', options)
                );
            }

            // General options state + application
            window.reportOptions = window.reportOptions || {
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

            function applyGeneralOptions() {
                if (!$('.general-options-modal').length) return;
                $('#custom-number-format').remove();
                let css = '<style id="custom-number-format">';
                if (window.reportOptions.showInRed) css += '.negative-amount{color:#dc2626!important}';
                if (window.reportOptions.hideZeroAmounts) css += '.zero-amount{display:none!important}';
                css += '</style>';
                $('head').append(css);

                $('.report-title-section').css('text-align', window.reportOptions.headerAlignment);
                $('.company-name').toggle(!!window.reportOptions.companyName);
                $('.date-range').toggle(!!window.reportOptions.reportPeriod);

                if (!$('.report-footer').length) {
                    $('.report-content').append(`
                        <div class="report-footer" style="padding:12px 20px; border-top:1px solid #e6e6e6; font-size:12px; color:#6b7280;"></div>
                    `);
                }
                const now = new Date();
                const footerParts = [];
                if (window.reportOptions.reportBasis) {
                    footerParts.push($('#accounting-method').val() === 'accrual' ? 'Accrual basis' : 'Cash basis');
                }
                if (window.reportOptions.datePrepared || window.reportOptions.timePrepared) {
                    const fullDateTime = now.toLocaleString('en-US', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: false,
                        timeZoneName: 'shortOffset'
                    });
                    footerParts.push(`|\n${fullDateTime}`);
                }
                $('.report-footer')
                    .css('text-align', window.reportOptions.footerAlignment)
                    .html(footerParts.join(' '));

                table.draw(false);
            }

            if ($('.general-options-modal').length) {
                $('.general-options-modal input, .general-options-modal select').on('change', function() {
                    window.reportOptions.divideBy1000 = $('#divide-by-1000').prop('checked');
                    window.reportOptions.hideZeroAmounts = $('#hide-zero-amounts').prop('checked');
                    window.reportOptions.roundWholeNumbers = $('#round-whole-numbers').prop('checked');
                    window.reportOptions.negativeFormat = $('#negative-format').val();
                    window.reportOptions.showInRed = $('#show-in-red').prop('checked');
                    window.reportOptions.companyLogo = $('#company-logo').prop('checked');
                    window.reportOptions.reportPeriod = $('#report-period-checkbox').prop('checked');
                    window.reportOptions.companyName = $('#company-name-checkbox').prop('checked');
                    window.reportOptions.headerAlignment = $('#header-alignment').val();
                    window.reportOptions.datePrepared = $('#date-prepared').prop('checked');
                    window.reportOptions.timePrepared = $('#time-prepared').prop('checked');
                    window.reportOptions.reportBasis = $('#report-basis').prop('checked');
                    window.reportOptions.footerAlignment = $('#footer-alignment').val();
                    applyGeneralOptions();
                });

                $('#general-options-btn').on('click', function(e) {
                    e.preventDefault();
                    $('#general-options-overlay').show();
                });
                $('#close-general-options, #general-options-overlay').on('click', function(e) {
                    if (e.target.id === 'general-options-overlay' || e.target.id ===
                        'close-general-options') $('#general-options-overlay').hide();
                });
                applyGeneralOptions();
            }

            // Columns drawer show/hide
            $('#columns-btn').on('click', function(e) {
                e.preventDefault();
                $('#columns-overlay').show();
            });
            $('#close-columns, #columns-overlay').on('click', function(e) {
                if (e.target.id === 'columns-overlay' || e.target.id === 'close-columns') $(
                    '#columns-overlay').hide();
            });

            // Column visibility bindings (map from drawer to DT)
            $('.columns-list .column-item input[type="checkbox"]').on('change', function() {
                const originalIndex = $(this).closest('.column-item').data(
                'column'); // index in header order
                const isVisible = $(this).prop('checked');
                if (originalIndex === undefined) return;

                // If ColReorder is enabled, transpose from original to current
                const currentIndex = table.colReorder && typeof table.colReorder.transpose === 'function' ?
                    table.colReorder.transpose(originalIndex, 'toCurrent') :
                    originalIndex;

                table.column(currentIndex).visible(isVisible, false);
                table.columns.adjust().draw(false);
            });
        });

        function refreshData() {
            $('#ledger-table').DataTable().ajax.reload(null, false);
        }

        function printReport() {
            window.print();
        }

        function exportReport() {
            alert('Export functionality would be implemented here');
        }
    </script>
@endpush
