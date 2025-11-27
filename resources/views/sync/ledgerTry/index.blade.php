@extends('layouts.admin')

@section('page-title', $pageTitle)

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">{{ $pageTitle }}</h4>
                        <div class="card-tools">
                            <span id="record-counter" class="badge badge-info">Loading records...</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="account_id">Account</label>
                                <select name="account_id" id="account_id" class="form-control">
                                    <option value="all">All Accounts</option>
                                    @foreach($accounts as $account)
                                        <option value="{{ $account->id }}" {{ $accountId == $account->id ? 'selected' : '' }}>
                                            {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date_range">Date Range</label>
                                <input type="text" name="date_range" id="date_range" class="form-control"
                                    value="{{ request('startDate', Carbon\Carbon::now()->startOfMonth()->format('m/d/Y')) }} - {{ request('endDate', Carbon\Carbon::now()->format('m/d/Y')) }}">
                            </div>
                            <div class="col-md-2">
                                <label>&nbsp;</label>
                                <button type="button" id="apply_filters" class="btn btn-primary form-control">Apply</button>
                            </div>
                            <div class="col-md-2">
                                <label>&nbsp;</label>
                                <button type="button" id="reset_filters"
                                    class="btn btn-secondary form-control">Reset</button>
                            </div>
                            <div class="col-md-2">
                                <label>&nbsp;</label>
                                <button type="button" id="export_btn" class="btn btn-success form-control">Export</button>
                            </div>
                        </div>

                        <!-- DataTable with Scroll -->
                        <div class="table-container-wrapper">
                            <div class="table-container">
                                <table class="table table-bordered table-striped table-hover" id="ledger-table">
                                    <thead class="thead-light">
                                        <tr>
                                            <th width="8%">Date</th>
                                            <th width="12%">Reference</th>
                                            <th width="20%">Account</th>
                                            <th width="12%" class="text-right">Debit</th>
                                            <th width="12%" class="text-right">Credit</th>
                                            <th width="18%">Memo/Description</th>
                                            <th width="18%" class="text-right">Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ledger-tbody">
                                        <!-- Data will be loaded and appended here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Loading Indicator -->
                        <div id="loading-indicator" class="text-center mt-3" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-2">Loading more records...</p>
                        </div>

                        <!-- Status Information -->
                        <div id="status-info" class="text-center mt-3">
                            <p class="text-muted">
                                <span id="loaded-count">0</span> rows loaded of
                                <span id="total-count">0</span> total records
                                <br>
                                <small>Scroll down to load more records</small>
                            </p>
                        </div>

                        <!-- No more records indicator -->
                        <div id="no-more-records" class="text-center mt-3" style="display: none;">
                            <p class="text-success">
                                <i class="fas fa-check-circle"></i>
                                All records loaded successfully
                            </p>
                        </div>

                        <!-- Debug Information (remove in production) -->
                        <div id="debug-info" class="text-center mt-3" style="display: none;">
                            <p class="text-warning">
                                <small>Debug: <span id="debug-data"></span></small>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
    <!-- Include required libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        /* Your existing CSS styles remain the same */
        .table-container-wrapper {
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background: #fff;
        }

        .table-container {
            max-height: 600px;
            overflow: auto;
            position: relative;
        }

        #ledger-table thead th {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 10;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
        }

        .account-group {
            background-color: #e3f2fd !important;
            font-weight: bold;
            cursor: pointer;
            position: sticky;
            z-index: 5;
        }

        .account-group:hover {
            background-color: #bbdefb !important;
        }

        .account-row {
            background-color: #ffffff;
        }

        .account-row:hover {
            background-color: #f8f9fa;
        }

        .opening-balance {
            background-color: #f1f8e9;
            font-style: italic;
        }

        .text-right {
            text-align: right !important;
        }

        .negative-amount {
            color: #dc3545;
            font-weight: bold;
        }

        .expand-icon {
            margin-right: 8px;
            display: inline-block;
            transition: transform 0.3s ease;
            font-size: 12px;
            width: 16px;
            text-align: center;
        }

        .account-group.collapsed .expand-icon {
            transform: rotate(-90deg);
        }

        #loading-indicator,
        #no-more-records,
        #debug-info {
            display: none;
        }

        .table-container::-webkit-scrollbar {
            width: 12px;
            height: 12px;
        }

        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        #ledger-table {
            width: 100%;
            margin-bottom: 0;
        }

        #ledger-table th,
        #ledger-table td {
            vertical-align: middle;
            padding: 8px 12px;
        }

        /* Fixed first column */
        #ledger-table th:first-child,
        #ledger-table td:first-child {
            position: sticky;
            left: 0;
            background: inherit;
            z-index: 2;
            border-right: 2px solid #dee2e6;
        }

        #ledger-table thead th:first-child {
            z-index: 11;
        }

        .account-group td:first-child {
            background: #e3f2fd;
        }

        .account-group:hover td:first-child {
            background: #bbdefb;
        }

        #record-counter {
            font-size: 0.9em;
        }

        .no-data-row {
            text-align: center;
            color: #6c757d;
            font-style: italic;
        }

        #status-info {
            font-size: 0.9em;
        }

        /* Add this to your existing CSS to match old table number formatting */
        .text-right {
            text-align: right !important;
        }

        .negative-amount {
            color: #dc3545;
        }

        /* Ensure numbers don't have thousands separators */
        #ledger-table td.text-right {
            font-family: monospace;
            white-space: nowrap;
        }
    </style>

    <script>
        $(document).ready(function () {
            // Initialize date range picker with proper format to fix moment warning
            $('#date_range').daterangepicker({
                opens: 'left',
                startDate: moment('{{ request('startDate', Carbon\Carbon::now()->startOfMonth()->format('m/d/Y')) }}', 'MM/DD/YYYY'),
                endDate: moment('{{ request('endDate', Carbon\Carbon::now()->format('m/d/Y')) }}', 'MM/DD/YYYY'),
                locale: {
                    format: 'MM/DD/YYYY'
                }
            });

            let currentPage = 1;
            let isLoading = false;
            let hasMore = true;
            let totalRecords = 0;
            let loadedRecords = 0;

            // Load initial data
            loadData(1, true);

            // Apply filters
            $('#apply_filters').on('click', function () {
                resetLoadingState();
                loadData(1, true);
            });

            // Reset filters
            $('#reset_filters').on('click', function () {
                $('#account_id').val('all');
                $('#date_range').val('');
                resetLoadingState();
                loadData(1, true);
            });

            function resetLoadingState() {
                currentPage = 1;
                hasMore = true;
                loadedRecords = 0;
                $('#ledger-tbody').empty();
                $('#no-more-records').hide();
                $('#record-counter').text('Loading records...');
                $('#loaded-count').text('0');
                $('#total-count').text('0');
            }

            // Infinite scroll on table container
            $('.table-container').on('scroll', function () {
                if (isLoading || !hasMore) return;

                const scrollTop = $(this).scrollTop();
                const scrollHeight = $(this)[0].scrollHeight;
                const clientHeight = $(this)[0].clientHeight;
                const threshold = 500;

                if (scrollTop + clientHeight >= scrollHeight - threshold) {
                    // FIX: Use proper sequential page numbers (2, 3, 4, 5...)
                    loadData(currentPage + 1, false);
                }
            });

            function loadData(page, clear = false) {
                if (isLoading) return;

                // FIX: Ensure page is treated as integer
                page = parseInt(page);

                isLoading = true;
                $('#loading-indicator').show();
                $('#record-counter').text(`Loading page ${page}...`);

                console.log('Loading page:', page, 'Clear:', clear, 'Current page:', currentPage);

                $.ajax({
                    url: '{{ route("ledger.index") }}',
                    type: 'GET',
                    data: {
                        account_id: $('#account_id').val(),
                        startDate: $('#date_range').data('daterangepicker').startDate.format('YYYY-MM-DD'),
                        endDate: $('#date_range').data('daterangepicker').endDate.format('YYYY-MM-DD'),
                        page: page,
                        per_page: 100
                    },
                    success: function (response) {
                        console.log('Page', page, 'Response:', response);

                        if (response.data && response.data.length > 0) {
                            if (clear) {
                                $('#ledger-tbody').empty();
                                loadedRecords = 0;
                                $('.table-container').scrollTop(0);
                            }

                            const rowsAdded = appendDataToTable(response.data);

                            // FIX: Use the response page number, but ensure it's sequential
                            currentPage = parseInt(response.current_page);
                            hasMore = response.has_more === true;
                            totalRecords = parseInt(response.recordsFiltered);
                            loadedRecords += rowsAdded;

                            updateCounters();

                            if (!hasMore) {
                                showNoMoreRecords();
                            }

                            console.log(`Page ${page} complete. Current: ${currentPage}, HasMore: ${hasMore}, Total: ${totalRecords}, Loaded: ${loadedRecords}`);

                        } else if (page === 1) {
                            $('#ledger-tbody').html(
                                '<tr class="no-data-row">' +
                                '<td colspan="7">No transactions found for the selected period.</td>' +
                                '</tr>'
                            );
                            updateCounters();
                        } else {
                            // Empty page but not first page - assume no more data
                            showNoMoreRecords();
                        }

                        attachEventHandlers();
                    },
                    error: function (xhr, status, error) {
                        console.error('Error loading page', page, ':', error);
                        if (page === 1) {
                            $('#ledger-tbody').html(
                                '<tr class="no-data-row">' +
                                '<td colspan="7">Error loading data. Please try again.</td>' +
                                '</tr>'
                            );
                        }
                        $('#record-counter').text('Error loading records');

                        // On error, allow continuing
                        hasMore = true;
                    },
                    complete: function () {
                        isLoading = false;
                        $('#loading-indicator').hide();
                    }
                });
            }

            function appendDataToTable(data) {
                const tbody = $('#ledger-tbody');
                let rowsAdded = 0;

                data.forEach(function (row) {
                    // Skip duplicate headers (except on first page)
                    if (row.is_header && currentPage > 1) {
                        const existingHeader = $(`[data-account-id="${row.DT_RowData['account-id']}"]`);
                        if (existingHeader.length > 0) {
                            return;
                        }
                    }

                    const tr = $('<tr></tr>');

                    if (row.DT_RowClass) {
                        tr.addClass(row.DT_RowClass);
                    }
                    if (row.DT_RowData) {
                        for (let key in row.DT_RowData) {
                            tr.attr('data-' + key, row.DT_RowData[key]);
                        }
                    }

                    let rowHtml = '';
                    rowHtml += `<td>${escapeHtml(row.date || '')}</td>`;
                    rowHtml += `<td>${escapeHtml(row.voucher_no || '')}</td>`;

                    if (row.DT_RowClass && row.DT_RowClass.includes('account-group')) {
                        rowHtml += `<td><span class="expand-icon">▼</span>${escapeHtml(row.account_name || '')}</td>`;
                    } else {
                        rowHtml += `<td>${escapeHtml(row.account_name || '')}</td>`;
                    }

                    rowHtml += `<td class="text-right">${escapeHtml(row.debit || '')}</td>`;
                    rowHtml += `<td class="text-right">${escapeHtml(row.credit || '')}</td>`;
                    rowHtml += `<td>${escapeHtml(row.memo || '')}</td>`;

                    let balanceClass = 'text-right';
                    if (row.running_balance && row.running_balance.toString().includes('-')) {
                        balanceClass += ' negative-amount';
                    }
                    rowHtml += `<td class="${balanceClass}">${escapeHtml(row.running_balance || '')}</td>`;

                    tr.html(rowHtml);
                    tbody.append(tr);
                    rowsAdded++;
                });

                console.log('Added', rowsAdded, 'rows to table');
                return rowsAdded;
            }

            function updateCounters() {
                if (totalRecords > 0) {
                    const percentage = Math.min(100, Math.round((loadedRecords / totalRecords) * 100));
                    $('#record-counter').text(`${loadedRecords}/${totalRecords} (${percentage}%)`);
                } else {
                    $('#record-counter').text(`${loadedRecords} rows loaded`);
                }

                $('#loaded-count').text(loadedRecords);
                $('#total-count').text(totalRecords);

                const percentage = totalRecords > 0 ? Math.min(100, Math.round((loadedRecords / totalRecords) * 100)) : 0;
                $('.card-title').html(
                    `{{ $pageTitle }} <small class="text-muted">(${percentage}% loaded)</small>`
                );

                if (hasMore) {
                    $('#status-info').html(
                        `<p class="text-info">
                            <span id="loaded-count">${loadedRecords}</span> rows loaded of 
                            <span id="total-count">${totalRecords}</span> total records
                            <br>
                            <small><i class="fas fa-arrow-down"></i> Scroll down to load more records</small>
                        </p>`
                    );
                }
            }

            function showNoMoreRecords() {
                hasMore = false;
                $('#no-more-records').show();
                $('#status-info').html(
                    `<p class="text-success">
                        <span id="loaded-count">${loadedRecords}</span> rows loaded of 
                        <span id="total-count">${totalRecords}</span> total records
                        <br>
                        <small><i class="fas fa-check"></i> All records loaded successfully</small>
                    </p>`
                );
            }

            function attachEventHandlers() {
                $('.account-group').off('click').on('click', function () {
                    const accountId = $(this).data('account-id');
                    const isCollapsed = $(this).hasClass('collapsed');

                    if (isCollapsed) {
                        $(this).removeClass('collapsed');
                        $(`[data-parent="${accountId}"]`).show();
                        $(this).find('.expand-icon').text('▼');
                    } else {
                        $(this).addClass('collapsed');
                        $(`[data-parent="${accountId}"]`).hide();
                        $(this).find('.expand-icon').text('▶');
                    }
                });

                $('.account-group').not('.collapsed').not('.initialized').each(function () {
                    $(this).addClass('initialized');
                    const accountId = $(this).data('account-id');
                    $(`[data-parent="${accountId}"]`).show();
                });
            }

            function escapeHtml(text) {
                if (!text) return '';
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, function (m) { return map[m]; });
            }

            // Debug function
            window.debugLedger = function () {
                const actualRows = $('#ledger-tbody tr').length;
                console.log('=== DEBUG INFO ===');
                console.log('Actual rows in table:', actualRows);
                console.log('Loaded records counter:', loadedRecords);
                console.log('Total records in DB:', totalRecords);
                console.log('Current page:', currentPage);
                console.log('Has more:', hasMore);
                console.log('Is loading:', isLoading);

                if (hasMore && !isLoading) {
                    console.log('Forcing load of next page...');
                    loadData(currentPage + 1, false);
                }

                return actualRows;
            };

            window.loadMore = function () {
                if (!isLoading && hasMore) {
                    loadData(currentPage + 1, false);
                }
            };
        });
    </script>
@endpush