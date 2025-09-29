@extends('layouts.admin')
@section('content')
    <style>
        /* same ledger look */
        body{background:#f8f9fa;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:14px}
        .main-container{background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.1)}
        .header-section{padding:16px 24px;border-bottom:1px solid #e9ecef;display:flex;justify-content:space-between;align-items:center}
        .header-left h4{margin:0;font-size:16px;font-weight:500;color:#333}
        .header-right{display:flex;align-items:center;gap:16px}
        .btn-icon{width:32px;height:32px;border:none;background:none;color:#666;border-radius:4px;display:flex;align-items:center;justify-content:center}
        .btn-icon:hover{background:#f1f3f4;color:#333}
        .btn-save{background:#1a73e8;color:#fff;border:none;padding:6px 16px;border-radius:4px;font-size:13px;font-weight:500}
        .filter-section{display:flex;justify-content:space-between;align-items:center;padding:16px 24px;border-bottom:1px solid #e9ecef;background:#fafbfc}
        .filter-row{display:flex;align-items:end;gap:16px;margin-bottom:12px}
        .filter-group{display:flex;align-items:end;gap:12px}
        .filter-item{display:flex;flex-direction:column}
        .filter-label{font-size:12px;color:#5f6368;margin-bottom:4px;font-weight:500}
        .form-control,.form-select{height:32px;font-size:13px;border:1px solid #dadce0;border-radius:4px;padding:0 8px}
        .options-row{display:flex;align-items:center;gap:16px}
        .columns-btn,.filter-btn,.general-options{display:flex;align-items:center;gap:6px;border-radius:4px;font-size:13px;color:#3c4043;text-decoration:none}
        .columns-btn:hover,.filter-btn:hover,.general-options:hover{background:#f8f9fa}
        .report-content{padding:24px}
        .report-header{text-align:center;margin-bottom:32px}
        .report-title{font-size:24px;font-weight:600;color:#202124;margin-bottom:8px}
        .company-name{font-size:16px;color:#5f6368;margin-bottom:4px}
        .date-range{font-size:14px;color:#5f6368}
        .table-container{margin-top:24px;overflow-x:auto;width:100%}
        .ledger-table{width:100%;font-size:13px;white-space:nowrap}
        .ledger-table thead th{background:#f8f9fa;border-bottom:2px solid #dee2e6;font-weight:600;color:#5f6368;padding:12px 8px;font-size:12px;text-transform:uppercase;letter-spacing:.5px}
        .ledger-table tbody td{padding:8px;border-bottom:1px solid #e9ecef;vertical-align:middle}
        /* drawers */
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1060}
        .drawer{position:fixed;top:0;right:0;bottom:0;width:360px;max-width:90vw;background:#fff;box-shadow:-2px 0 10px rgba(0,0,0,.15);overflow-y:auto;z-index:1070}
        .modal-header{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid #e6e6e6;background:#f9fafb}
        .modal-content{padding:16px 20px 24px}
    </style>

    <div class="main-container">
        <!-- Header -->
        <div class="header-section">
            <div class="header-left">
                <h4>{{ __('Inventory Valuation Summary') }}</h4>
            </div>
            <div class="header-right">
                <button class="btn-icon" title="{{ __('Refresh') }}" onclick="refreshData()"><i class="fas fa-sync-alt"></i></button>
                <button class="btn-icon" title="{{ __('Print') }}" onclick="window.print()"><i class="fas fa-print"></i></button>
                <a class="btn-icon" title="{{ __('Export') }}" href="{{ route('productservice.export') }}"><i class="fas fa-file-export"></i></a>
                <a class="btn-icon" title="{{ __('Import') }}" href="#" data-url="{{ route('productservice.file.import') }}" data-ajax-popup="true"><i class="fas fa-file-import"></i></a>
                <button class="btn-save">{{ __('Save As') }}</button>
            </div>
        </div>

        <!-- Filter row (Report period / From / To) -->
        <div class="filter-section">
            <div class="filter-row">
                <div class="filter-group">
                    <div class="filter-item">
                        <label class="filter-label">{{ __('Report period') }}</label>
                        <select class="form-select" id="report-period" style="width: 160px;">
                            <option value="all_dates" {{ ($filter['reportPeriod'] ?? '')=='all_dates' ? 'selected':'' }}>{{ __('All Dates') }}</option>
                            <option value="today">{{ __('Today') }}</option>
                            <option value="this_week">{{ __('This week') }}</option>
                            <option value="this_month" {{ ($filter['reportPeriod'] ?? '')=='this_month' ? 'selected':'' }}>{{ __('This month') }}</option>
                            <option value="last_month">{{ __('Last month') }}</option>
                            <option value="this_quarter">{{ __('This quarter') }}</option>
                            <option value="this_year">{{ __('This year') }}</option>
                            <option value="last_year">{{ __('Last year') }}</option>
                            <option value="custom">{{ __('Custom') }}</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label class="filter-label">{{ __('From') }}</label>
                        <input type="date" class="form-control" id="start-date" value="{{ $filter['startDateRange'] }}" style="width: 140px;">
                    </div>
                    <div class="filter-item">
                        <label class="filter-label">{{ __('To') }}</label>
                        <input type="date" class="form-control" id="end-date" value="{{ $filter['endDateRange'] }}" style="width: 140px;">
                    </div>
                </div>
            </div>

            <div class="options-row mt-1">
                <a href="#" class="columns-btn" id="columns-btn"><i class="fas fa-columns"></i> {{ __('Columns') }}</a>
                <a href="#" class="filter-btn" id="filter-btn"><i class="fas fa-filter"></i> {{ __('Filter') }}</a>
                <a href="#" class="general-options" id="general-options-btn"><i class="bi bi-sliders2-vertical"></i> {{ __('General options') }}</a>
            </div>
        </div>

        <!-- Report -->
        <div class="report-content">
            <div class="report-header">
                <h1 class="report-title">{{ __('Inventory Valuation Summary') }}</h1>
                <p class="company-name">{{ \Auth::user()->name ?? config('app.name') }}</p>
                <p class="date-range">
                    <span id="display-date-range">
                        {{ \Carbon\Carbon::parse($filter['startDateRange'])->format('F j, Y') }} -
                        {{ \Carbon\Carbon::parse($filter['endDateRange'])->format('F j, Y') }}
                    </span>
                </p>
            </div>

            <div class="table-container">
                <table class="table ledger-table" id="inventory-table">
                    <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Sku') }}</th>
                        <th>{{ __('Sale Price') }}</th>
                        <th>{{ __('Purchase Price') }}</th>
                        <th>{{ __('Tax') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Unit') }}</th>
                        <th>{{ __('Quantity') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- FILTER DRAWER: ONLY Category/Type -->
    <div class="modal-overlay" id="filter-overlay" style="display:none;">
        <div class="drawer">
            <div class="modal-header">
                <h5>{{ __('Filter') }}</h5>
                <button type="button" class="btn-close" id="close-filter">&times;</button>
            </div>
            <div class="modal-content">
                <div class="filter-item mb-3">
                    <label class="filter-label">{{ __('Category') }}</label>
                    {{ Form::select('category', $category, $filter['selectedCategory'] ?? '', ['class' => 'form-select', 'id' => 'filter-category', 'style' => 'width:100%']) }}
                </div>
                <div class="filter-item">
                    <label class="filter-label">{{ __('Type') }}</label>
                    {{ Form::select('type', $types, $filter['selectedType'] ?? '', ['class' => 'form-select', 'id' => 'filter-type', 'style' => 'width:100%']) }}
                </div>
            </div>
        </div>
    </div>

    <!-- COLUMNS / GENERAL OPTIONS DRAWERS (optional UI) -->
    <div class="modal-overlay" id="columns-overlay" style="display:none;">
        <div class="drawer">
            <div class="modal-header">
                <h5>{{ __('Columns') }}</h5>
                <button type="button" class="btn-close" id="close-columns">&times;</button>
            </div>
            <div class="modal-content">
                <div class="columns-list">
                    <div class="column-item" data-column="0"><label><input type="checkbox" checked> {{ __('Name') }}</label></div>
                    <div class="column-item" data-column="1"><label><input type="checkbox" checked> {{ __('Sku') }}</label></div>
                    <div class="column-item" data-column="2"><label><input type="checkbox" checked> {{ __('Sale Price') }}</label></div>
                    <div class="column-item" data-column="3"><label><input type="checkbox" checked> {{ __('Purchase Price') }}</label></div>
                    <div class="column-item" data-column="4"><label><input type="checkbox" checked> {{ __('Tax') }}</label></div>
                    <div class="column-item" data-column="5"><label><input type="checkbox" checked> {{ __('Category') }}</label></div>
                    <div class="column-item" data-column="6"><label><input type="checkbox" checked> {{ __('Unit') }}</label></div>
                    <div class="column-item" data-column="7"><label><input type="checkbox" checked> {{ __('Quantity') }}</label></div>
                    <div class="column-item" data-column="8"><label><input type="checkbox" checked> {{ __('Type') }}</label></div>
                    <div class="column-item" data-column="9"><label><input type="checkbox" checked> {{ __('Action') }}</label></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="general-options-overlay" style="display:none;">
        <div class="drawer">
            <div class="modal-header">
                <h5>{{ __('General options') }}</h5>
                <button type="button" class="btn-close" id="close-general-options">&times;</button>
            </div>
            <div class="modal-content">
                <label><input type="checkbox" id="hide-zero-amounts"> {{ __("Don't show zero amounts") }}</label>
                <label class="ms-3"><input type="checkbox" id="show-in-red"> {{ __('Show negatives in red') }}</label>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>

    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/colreorder/1.7.0/js/dataTables.colReorder.min.js"></script>
    <script src="https://cdn.datatables.net/fixedheader/3.4.0/js/dataTables.fixedHeader.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/colreorder/1.7.0/css/colReorder.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/fixedheader/3.4.0/css/fixedHeader.dataTables.min.css">

    <script>
        $(function () {
            const table = $('#inventory-table').DataTable({
                processing: true,
                serverSide: true,
                colReorder: true,
                scrollX: true,
                responsive: false,
                scrollY: '420px',
                scrollCollapse: true,
                fixedHeader: true,
                ajax: {
                    url: "{{ route('productservice.inventoryValuationSummary') }}",
                    data: function(d){
                        d.report_period = $('#report-period').val() || '';
                        d.start_date    = $('#start-date').val() || '';
                        d.end_date      = $('#end-date').val() || '';
                        d.category      = $('#filter-category').val() || '';
                        d.type          = $('#filter-type').val() || '';
                    }
                },
                columns: [
                    {data:'name', name:'name'},
                    {data:'sku', name:'sku'},
                    {data:'sale_price', name:'sale_price', className:'text-right'},
                    {data:'purchase_price', name:'purchase_price', className:'text-right'},
                    {data:'tax', name:'tax'},
                    {data:'category', name:'category'},
                    {data:'unit', name:'unit'},
                    {data:'quantity', name:'quantity', className:'text-right'},
                    {data:'type', name:'type'},
                    {data:'action', name:'action', orderable:false, searchable:false},
                ],
                dom: 't',
                paging: false,
                searching: false,
                info: false,
                ordering: false
            });

            // Drawer open/close
            $('#filter-btn').on('click', e => { e.preventDefault(); $('#filter-overlay').show(); });
            $('#close-filter, #filter-overlay').on('click', function(e){
                if (e.target.id === 'filter-overlay' || e.target.id === 'close-filter') $('#filter-overlay').hide();
            });
            $('#columns-btn').on('click', e => { e.preventDefault(); $('#columns-overlay').show(); });
            $('#close-columns, #columns-overlay').on('click', function(e){
                if (e.target.id === 'columns-overlay' || e.target.id === 'close-columns') $('#columns-overlay').hide();
            });
            $('#general-options-btn').on('click', e => { e.preventDefault(); $('#general-options-overlay').show(); });
            $('#close-general-options, #general-options-overlay').on('click', function(e){
                if (e.target.id === 'general-options-overlay' || e.target.id === 'close-general-options') $('#general-options-overlay').hide();
            });

            // Report-period changes set dates & auto-draw
            $('#report-period').on('change', function(){
                const now = moment();
                let s, e;
                switch ($(this).val()) {
                    case 'today':        s = now.clone(); e = now.clone(); break;
                    case 'this_week':    s = now.clone().startOf('week'); e = now.clone().endOf('week'); break;
                    case 'this_month':   s = now.clone().startOf('month'); e = now.clone().endOf('month'); break;
                    case 'last_month':   s = now.clone().subtract(1,'month').startOf('month'); e = now.clone().subtract(1,'month').endOf('month'); break;
                    case 'this_quarter': s = now.clone().startOf('quarter'); e = now.clone().endOf('quarter'); break;
                    case 'this_year':    s = now.clone().startOf('year'); e = now.clone().endOf('year'); break;
                    case 'last_year':    s = now.clone().subtract(1,'year').startOf('year'); e = now.clone().subtract(1,'year').endOf('year'); break;
                    case 'all_dates':    s = moment('1900-01-01'); e = now.clone(); break;
                    default: return; // custom -> manual date changes
                }
                $('#start-date').val(s.format('YYYY-MM-DD'));
                $('#end-date').val(e.format('YYYY-MM-DD'));
                updateHeaderDate();
                table.draw();
            });

            // From/To auto-apply
            $('#start-date, #end-date').on('change', function(){
                updateHeaderDate();
                table.draw();
            });

            // Category/Type auto-apply inside drawer
            $('#filter-category, #filter-type').on('change', function(){
                table.draw();
            });

            // Column toggles with colReorder awareness
            function getDT(cb){
                const tryGet = function(n){
                    const dt = $.fn.dataTable.isDataTable('#inventory-table') ? $('#inventory-table').DataTable() : null;
                    if (dt) cb(dt); else if (n>0) setTimeout(()=>tryGet(n-1), 100);
                };
                tryGet(30);
            }
            $('.columns-list input[type="checkbox"]').on('change', function(){
                const originalIndex = $(this).closest('.column-item').data('column');
                const isVisible = $(this).prop('checked');
                if (originalIndex === undefined) return;
                getDT(function(dt){
                    const currentIndex = dt.colReorder && typeof dt.colReorder.transpose === 'function'
                        ? dt.colReorder.transpose(originalIndex, 'toCurrent')
                        : originalIndex;
                    dt.column(currentIndex).visible(isVisible, false);
                    dt.columns.adjust().draw(false);
                });
            });

            function updateHeaderDate(){
                const s = new Date($('#start-date').val());
                const e = new Date($('#end-date').val());
                if (isNaN(s) || isNaN(e)) return;
                const opts = {year:'numeric', month:'long', day:'numeric'};
                $('#display-date-range').text(
                    s.toLocaleDateString('en-US', opts) + ' - ' + e.toLocaleDateString('en-US', opts)
                );
            }
        });

        function refreshData(){ $('#inventory-table').DataTable().ajax.reload(); }
    </script>
@endpush
