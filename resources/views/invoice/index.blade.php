@extends('layouts.admin')
@section('page-title')
    {{ __('Manage Invoices') }}
@endsection
@push('css-page')
    <link rel="stylesheet" href="{{ asset('css/datatable/buttons.dataTables.min.css') }}">
    <style>
        .financial-accordion {
            max-width: 100%;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .accordion-header {
            padding: 20px 25px;
            /* border-bottom: 1px solid #e9ecef; */
            cursor: pointer;
            position: relative;
        }

        .accordion-header:hover {
            background-color: #f8f9fa;
        }

        .chevron {
            position: absolute;
            right: 25px;
            top: 50%;
            transform: translateY(-50%);
            transition: transform 0.3s ease;
            color: #6c757d;
        }

        .chevron.rotated {
            transform: translateY(-50%) rotate(180deg);
        }

        .accordion-content {
            padding: 25px;
            display: none;
        }

        .accordion-content.show {
            display: block;
        }

        .financial-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 60px;
        }

        .financial-section {
            flex: 1;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .amount-large {
            font-size: 16px;
            font-weight: 500;
            color: #666;
        }

        .period-text {
            font-size: 14px;
            color: #999;
        }

        .main-amount {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
            line-height: 1;
        }

        .status-text {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
        }

        .progress-container {
            display: flex;
            gap: 0;
            height: 20px;
            border-radius: 5px;
            overflow: hidden;
            background-color: #e9ecef;
            margin-top: 10px;
        }
        .progress-container2 {
            display: flex;
            gap: 0;
            height: 20px;
            border-radius: 5px;
            overflow: hidden;
            background-color: #65f394;
            margin-top: 10px;
        }

        .progress-bar-orange {
            background-color: #FF8000;
            flex: 0 0 30%;
        }

        .progress-bar-gray {
            background-color: #e0e0e0;
            flex: 1;
        }

        .progress-bar-light-green {
            background-color: #4CAF50;
            flex: 0 0 55%;
        }

        .progress-bar-dark-green {
            background-color: #2E7D32;
            flex: 0 0 45%;
        }

        .amounts-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .amount-item {
            flex: 1;
        }

        .amount-item:first-child {
            text-align: left;
        }

        .amount-item:last-child {
            text-align: right;
        }

        .amount-value {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
            line-height: 1;
        }

        .amount-label {
            font-size: 14px;
            color: #666;
        }

        .right-section .progress-container {
            margin-top: 0;
        }

        @media (max-width: 768px) {
            .financial-row {
                flex-direction: column;
                gap: 30px;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .amounts-row {
                flex-direction: column;
                gap: 15px;
            }

            .amount-item:last-child {
                text-align: left;
            }
        }
    </style>
@endpush

@push('script-page')
    <script>
        function copyToClipboard(element) {

            var copyText = element.id;
            navigator.clipboard.writeText(copyText);
            // document.addEventListener('copy', function (e) {
            //     e.clipboardData.setData('text/plain', copyText);
            //     e.preventDefault();
            // }, true);
            //
            // document.execCommand('copy');
            show_toastr('success', 'Url copied to clipboard', 'success');
        }

        function toggleAccordion() {
            const content = document.getElementById('accordionContent');
            const chevron = document.getElementById('chevron');

            if (content.classList.contains('show')) {
                content.classList.remove('show');
                chevron.classList.add('rotated');
            } else {
                content.classList.add('show');
                chevron.classList.remove('rotated');
            }
        }
    </script>
@endpush


@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Invoice') }}</li>
@endsection

@section('action-btn')
    <div class="float-end">
        {{--        <a class="btn btn-sm btn-primary" data-bs-toggle="collapse" href="#multiCollapseExample1" role="button" aria-expanded="false" aria-controls="multiCollapseExample1" data-bs-toggle="tooltip" title="{{__('Filter')}}"> --}}
        {{--            <i class="ti ti-filter"></i> --}}
        {{--        </a> --}}

        {{-- <a href="{{ route('invoice.export') }}" class="btn btn-sm btn-primary" data-bs-toggle="tooltip"
            title="{{ __('Export') }}">
            <i class="ti ti-file-export"></i>
        </a> --}}

        {{-- @can('create invoice')
            <a href="{{ route('invoice.create', 0) }}" class="btn btn-sm btn-primary" data-bs-toggle="tooltip"
                title="{{ __('Create') }}">
                <i class="ti ti-plus"></i>
            </a>
        @endcan --}}
    </div>
@endsection



@section('content')
    {{-- Include Sales Tabs --}}
    @include('transaction.sales-tabs')

    {{-- Financial Accordion --}}
    <div class="financial-accordion mt-2 mb-2">
        <div class="accordion-header" onclick="toggleAccordion()">
            {{-- <h5 class="mb-0">{{ __('Payment Overview') }}</h5> --}}
            <i class="fas fa-chevron-up chevron" id="chevron"></i>
        </div>

        <div class="accordion-content show" id="accordionContent">
            <div class="financial-row">
                <!-- Left Section - Unpaid -->
                <div class="financial-section">
                    <div class="section-header">
                        <span class="amount-large">{{ Auth::user()->priceFormat(($invoiceData['unpaid']['amount'] ?? 0) + ($invoiceData['overdue']['amount'] ?? 0)) }} {{ __('Unpaid') }}</span>
                        <span class="period-text">{{ __('Last 365 days') }}</span>
                    </div>

                    <div class="main-amount">{{ Auth::user()->priceFormat($invoiceData['overdue']['amount'] ?? 0) }}</div>
                    <div class="status-text">{{ __('Overdue') }}</div>

                    <div class="progress-container">
                        <div class="progress-bar-orange" style="flex: {{ max(0.3, ($invoiceData['overdue']['amount'] ?? 0) / max(1, ($invoiceData['overdue']['amount'] ?? 0) + ($invoiceData['sent']['amount'] ?? 0))) }}"></div>
                        {{-- <div class="progress-bar-gray" style="flex: {{ max(0.1, ($invoiceData['sent']['amount'] ?? 0) / max(1, ($invoiceData['overdue']['amount'] ?? 0) + ($invoiceData['sent']['amount'] ?? 0))) }}"></div> --}}
                    </div>
                </div>

                <!-- Right Section - Paid -->
                <div class="financial-section right-section">
                    <div class="section-header">
                        <span class="amount-large">{{ Auth::user()->priceFormat($invoiceData['paid']['amount'] ?? 0) }} {{ __('Paid') }}</span>
                        <span class="period-text">{{ __('Last 30 days') }}</span>
                    </div>

                    <div class="amounts-row">
                        <div class="amount-item">
                            <div class="amount-value">{{ Auth::user()->priceFormat($invoiceData['partially_paid']['amount'] ?? 0) }}</div>
                            <div class="amount-label">{{ __('Not deposited') }}</div>
                        </div>
                        <div class="amount-item">
                            <div class="amount-value">{{ Auth::user()->priceFormat($invoiceData['paid']['amount'] ?? 0) }}</div>
                            <div class="amount-label">{{ __('Deposited') }}</div>
                        </div>
                    </div>

                    <div class="progress-container2">
                        {{-- <div class="progress-bar-light-green" style="flex: {{ max(0.1, ($invoiceData['partially_paid']['amount'] ?? 0) / max(1, ($invoiceData['partially_paid']['amount'] ?? 0) + ($invoiceData['paid']['amount'] ?? 0))) }}"></div> --}}
                        <div class="progress-bar-dark-green" style="flex: {{ max(0.6, ($invoiceData['paid']['amount'] ?? 0) / max(1, ($invoiceData['partially_paid']['amount'] ?? 0) + ($invoiceData['paid']['amount'] ?? 0))) }}"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <div class="row">
            <div class="col-sm-12">
                <div class="mt-2 mb-2" id="multiCollapseExample1">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="card-body">
                            {{ Form::open(['route' => ['invoice.index'], 'method' => 'GET', 'id' => 'customer_submit']) }}
                            <div class="row d-flex align-items-center justify-content-start">
                                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12 mr-2">
                                    <div class="btn-box">
                                        {{ Form::label('issue_date', __('Issue Date'), ['class' => 'form-label']) }}
                                        {{ Form::date('issue_date', isset($_GET['issue_date']) ? $_GET['issue_date'] : '', ['class' => 'form-control month-btn', 'id' => 'pc-daterangepicker-1']) }}
                                    </div>
                                </div>
                                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12 mr-2">
                                    <div class="btn-box">
                                        {{ Form::label('customer', __('Customer'), ['class' => 'form-label']) }}
                                        {{ Form::select('customer', $customer, isset($_GET['customer']) ? $_GET['customer'] : '', ['class' => 'form-control select auto-filter']) }}
                                    </div>
                                </div>
                                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
                                    <div class="btn-box">
                                        {{ Form::label('status', __('Status'), ['class' => 'form-label']) }}
                                        {{ Form::select('status', ['' => 'Select Status'] + $status, isset($_GET['status']) ? $_GET['status'] : '', ['class' => 'form-control select auto-filter']) }}
                                    </div>
                                </div>
                                {{-- <div class="col-auto float-end ms-2 mt-4">
                                <a href="#" class="btn btn-sm btn-primary"
                                    onclick="document.getElementById('customer_submit').submit(); return false;"
                                    data-toggle="tooltip" data-original-title="{{ __('apply') }}">
                                    <span class="btn-inner--icon"><i class="ti ti-search"></i></span>
                                </a>
                                <a href="{{ route('invoice.index') }}" class="btn btn-sm btn-danger" data-toggle="tooltip"
                                    data-original-title="{{ __('Reset') }}">
                                    <span class="btn-inner--icon"><i class="ti ti-trash-off text-white-off"></i></span>
                                </a>
                            </div> --}}
                            </div>
                            {{ Form::close() }}
                        </div>
                        <div class="col-auto mt-4">
                            @can('create invoice')
                                <a href="{{ route('invoice.create', 0) }}" class="btn btn-sm btn-primary"
                                    data-bs-toggle="tooltip" title="{{ __('Create') }}">
                                    {{ __('Create Invoice') }}
                                    <i class="ti ti-plus"></i>
                                </a>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Auto-submit form when select filters change (customer, status)
                const selectFilterElements = document.querySelectorAll('select.auto-filter');

                selectFilterElements.forEach(function(element) {
                    element.addEventListener('change', function() {
                        document.getElementById('customer_submit').submit();
                    });
                });

                // Handle date field with delay to allow proper date selection
                const dateField = document.getElementById('pc-daterangepicker-1');
                let dateTimeout;

                if (dateField) {
                    dateField.addEventListener('change', function() {
                        // Clear any existing timeout
                        clearTimeout(dateTimeout);

                        // Set a delay to allow user to finish selecting date
                        dateTimeout = setTimeout(function() {
                            document.getElementById('customer_submit').submit();
                        }, 1000); // 1 second delay
                    });

                    // Also submit when user clicks away from the date field (blur event)
                    dateField.addEventListener('blur', function() {
                        clearTimeout(dateTimeout);
                        if (this.value) {
                            document.getElementById('customer_submit').submit();
                        }
                    });
                }
            });
        </script>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body table-border-style">
                        <h5></h5>
                        <div class="table-responsive">
                            <table class="table datatable">
                                <thead>
                                    <tr>
                                        <th> {{ __('Invoice') }}</th>
                                        {{--                                @if (!\Auth::guard('customer')->check()) --}}
                                        {{--                                    <th>{{ __('Customer') }}</th> --}}
                                        {{--                                @endif --}}
                                        <th>{{ __('Issue Date') }}</th>
                                        <th>{{ __('Due Date') }}</th>
                                        <th>{{ __('Due Amount') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        @if (Gate::check('edit invoice') || Gate::check('delete invoice') || Gate::check('show invoice'))
                                            <th>{{ __('Action') }}</th>
                                        @endif
                                        {{-- <th>
                                <td class="barcode">
                                    {!! DNS1D::getBarcodeHTML($invoice->sku, "C128",1.4,22) !!}
                                    <p class="pid">{{$invoice->sku}}</p>
                                </td>
                            </th> --}}
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach ($invoices as $invoice)
                                        <tr>
                                            <td class="Id">
                                                <a href="{{ route('invoice.show', \Crypt::encrypt($invoice->id)) }}"
                                                    style="padding-top: 11px;"
                                                    class="btn btn-outline-primary">{{ AUth::user()->invoiceNumberFormat($invoice->invoice_id) }}
                                                    @if ($invoice->updated_at < \Carbon\Carbon::now()->subHours(96) && $invoice->status != 4)
                                                        <small
                                                            style="position: absolute; position: absolute; margin-top: -15px; margin-left: -24px;"><span
                                                                class="badge bg-danger" data-bs-toggle="tooltip"
                                                                title="{{ __('No Activity for the Last 96 Hours ') }}">
                                                                {{ __('Follow Up') }}</span></small>
                                                </a>
                                    @endif
                                    </td>
                                    <td>{{ Auth::user()->dateFormat($invoice->issue_date) }}</td>
                                    <td>
                                        @if ($invoice->due_date < date('Y-m-d'))
                                            <p class="text-danger mt-3">
                                                {{ \Auth::user()->dateFormat($invoice->due_date) }}</p>
                                        @else
                                            {{ \Auth::user()->dateFormat($invoice->due_date) }}
                                        @endif
                                    </td>
                                    <td>{{ \Auth::user()->priceFormat($invoice->getDue()) }}</td>
                                    <td>
                                        @if ($invoice->status == 0)
                                            <span
                                                class="status_badge badge bg-secondary p-2 px-3 rounded">{{ __(\App\Models\Invoice::$statues[$invoice->status]) }}</span>
                                        @elseif($invoice->status == 1)
                                            <span
                                                class="status_badge badge bg-warning p-2 px-3 rounded">{{ __(\App\Models\Invoice::$statues[$invoice->status]) }}</span>
                                        @elseif($invoice->status == 2)
                                            <span
                                                class="status_badge badge bg-danger p-2 px-3 rounded">{{ __(\App\Models\Invoice::$statues[$invoice->status]) }}</span>
                                        @elseif($invoice->status == 3)
                                            <span
                                                class="status_badge badge bg-info p-2 px-3 rounded">{{ __(\App\Models\Invoice::$statues[$invoice->status]) }}</span>
                                        @elseif($invoice->status == 4)
                                            <span
                                                class="status_badge badge bg-primary p-2 px-3 rounded">{{ __(\App\Models\Invoice::$statues[$invoice->status]) }}</span>
                                        @endif
                                    </td>
                                    @if (Gate::check('edit invoice') || Gate::check('delete invoice') || Gate::check('show invoice'))
                                        <td class="Action">
                                            <span>
                                                @php $invoiceID= Crypt::encrypt($invoice->id); @endphp

                                                @can('copy invoice')
                                                    <div class="action-btn bg-warning ms-2">
                                                        <a href="#" id="{{ route('invoice.link.copy', [$invoiceID]) }}"
                                                            class="mx-3 btn btn-sm align-items-center"
                                                            onclick="copyToClipboard(this)" data-bs-toggle="tooltip"
                                                            title="{{ __('Copy Invoice') }}"
                                                            data-original-title="{{ __('Copy Invoice') }}"><i
                                                                class="ti ti-link text-white"></i></a>
                                                    </div>
                                                @endcan
                                                @can('duplicate invoice')
                                                    <div class="action-btn bg-primary ms-2">
                                                        {!! Form::open([
                                                            'method' => 'get',
                                                            'route' => ['invoice.duplicate', $invoice->id],
                                                            'id' => 'duplicate-form-' . $invoice->id,
                                                        ]) !!}

                                                        <a href="#"
                                                            class="mx-3 btn btn-sm align-items-center bs-pass-para"
                                                            data-toggle="tooltip" data-original-title="{{ __('Duplicate') }}"
                                                            data-bs-toggle="tooltip" title="Duplicate Invoice"
                                                            data-original-title="{{ __('Delete') }}"
                                                            data-confirm="You want to confirm this action. Press Yes to continue or Cancel to go back"
                                                            data-confirm-yes="document.getElementById('duplicate-form-{{ $invoice->id }}').submit();">
                                                            <i class="ti ti-copy text-white"></i>
                                                            {!! Form::open([
                                                                'method' => 'get',
                                                                'route' => ['invoice.duplicate', $invoice->id],
                                                                'id' => 'duplicate-form-' . $invoice->id,
                                                            ]) !!}
                                                            {!! Form::close() !!}
                                                        </a>
                                                    </div>
                                                @endcan
                                                @can('show invoice')
                                                    {{--                                                        @if (\Auth::guard('customer')->check()) --}}
                                                    {{--                                                            <div class="action-btn bg-info ms-2"> --}}
                                                    {{--                                                                    <a href="{{ route('customer.invoice.show', \Crypt::encrypt($invoice->id)) }}" --}}
                                                    {{--                                                                       class="mx-3 btn btn-sm align-items-center" data-bs-toggle="tooltip" title="Show " --}}
                                                    {{--                                                                       data-original-title="{{ __('Detail') }}"> --}}
                                                    {{--                                                                        <i class="ti ti-eye text-white"></i> --}}
                                                    {{--                                                                    </a> --}}
                                                    {{--                                                                </div> --}}
                                                    {{--                                                        @else --}}
                                                    <div class="action-btn bg-info ms-2">
                                                        <a href="{{ route('invoice.show', \Crypt::encrypt($invoice->id)) }}"
                                                            class="mx-3 btn btn-sm align-items-center" data-bs-toggle="tooltip"
                                                            title="Show " data-original-title="{{ __('Detail') }}">
                                                            <i class="ti ti-eye text-white"></i>
                                                        </a>
                                                    </div>
                                                    {{--                                                        @endif --}}
                                                @endcan
                                                @can('edit invoice')
                                                    <div class="action-btn bg-primary ms-2">
                                                        <a href="{{ route('invoice.edit', \Crypt::encrypt($invoice->id)) }}"
                                                            class="mx-3 btn btn-sm align-items-center" data-bs-toggle="tooltip"
                                                            title="Edit " data-original-title="{{ __('Edit') }}">
                                                            <i class="ti ti-pencil text-white"></i>
                                                        </a>
                                                    </div>
                                                @endcan
                                                @can('delete invoice')
                                                    <div class="action-btn bg-danger ms-2">
                                                        {!! Form::open([
                                                            'method' => 'DELETE',
                                                            'route' => ['invoice.destroy', $invoice->id],
                                                            'id' => 'delete-form-' . $invoice->id,
                                                        ]) !!}
                                                        <a href="#"
                                                            class="mx-3 btn btn-sm align-items-center bs-pass-para "
                                                            data-bs-toggle="tooltip" title="{{ __('Delete') }}"
                                                            data-original-title="{{ __('Delete') }}"
                                                            data-confirm="{{ __('Are You Sure?') . '|' . __('This action can not be undone. Do you want to continue?') }}"
                                                            data-confirm-yes="document.getElementById('delete-form-{{ $invoice->id }}').submit();">
                                                            <i class="ti ti-trash text-white"></i>
                                                        </a>
                                                        {!! Form::close() !!}
                                                    </div>
                                                @endcan
                                            </span>
                                        </td>
                                    @endif
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
