@extends('layouts.admin')
@section('page-title')
    {{ __('Manage Reports') }}
@endsection
@push('script-page')
@endpush
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('All Reports') }}</li>
@endsection
@push('style')
    <style>
        /* Custom styles for the reports accordion */
        .accordion-item {
            border: 1px solid #e0e0e0;
            margin-bottom: 0.7rem;
        }

        .accordion-button {
            background-color: #f8f9fa;
            color: #495057;
            font-weight: 500;
            padding: 1rem 1.25rem;
            border: none;
            box-shadow: none;
        }

        .accordion-button:not(.collapsed) {
            background-color: #e3f2fd;
            color: #1976d2;
            box-shadow: none;
        }

        .accordion-button:focus {
            box-shadow: none;
            border-color: transparent;
        }

        .accordion-button::after {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23212529'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
        }

        .accordion-body {
            background-color: #ffffff;
        }

        .report-item {
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s ease;
        }

        .report-item:last-child {
            border-bottom: none;
        }

        .report-item:hover {
            background-color: #f8f9fa;
        }

        .report-item.active {
            background-color: #e3f2fd;
        }

        .report-item.active .report-link {
            color: #1976d2;
            font-weight: 500;
        }

        .report-link {
            text-decoration: none;
            color: #495057;
            transition: color 0.2s ease;
        }

        .report-link:hover {
            text-decoration: none;
            color: #1976d2;
        }

        .report-actions i {
            font-size: 0.875rem;
            opacity: 0.6;
            transition: opacity 0.2s ease;
        }

        .report-item:hover .report-actions i {
            opacity: 1;
        }

        /* Remove default Bootstrap accordion borders for seamless look */
        .accordion {
            --bs-accordion-border-width: 0;
            --bs-accordion-border-radius: 0.375rem;
            padding-bottom: 1rem;
            border-top-left-radius: 0.375rem;
            border-top-right-radius: 0.375rem;
            border-bottom-left-radius: 0.375rem;
            border-bottom-right-radius: 0.375rem;
        }

        /* .accordion-item:first-child .accordion-button {
                                            border-top-left-radius: 0.375rem;
                                            border-top-right-radius: 0.375rem;
                                        }

                                        .accordion-item:last-child .accordion-button.collapsed {
                                            border-bottom-left-radius: 0.375rem;
                                            border-bottom-right-radius: 0.375rem;
                                        } */

        /* Responsive grid adjustments */
        @media (max-width: 767.98px) {
            .accordion-body {
                padding: 1rem !important;
            }

            .report-link {
                padding: 0.75rem !important;
                font-size: 0.9rem;
            }
        }

        @media (min-width: 768px) {
            .report-item {
                border-right: 1px solid #f0f0f0;
            }

            .row .col-md-6:nth-child(even) .report-item {
                border-right: none;
            }

            /* Add bottom border for proper grid layout */
            .row:not(:last-child) .report-item {
                border-bottom: 1px solid #f0f0f0;
            }
        }

        .accordion-item:first-child .accordion-button {
            border-top-left-radius: 0.375rem;
            border-top-right-radius: 0.375rem;
        }

        .accordion-item:last-child .accordion-button.collapsed {
            border-bottom-left-radius: 0.375rem;
            border-bottom-right-radius: 0.375rem;
        }
    </style>
@endpush
@push('script-page')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Open every accordion item
            document.querySelectorAll('#reportsAccordion .accordion-collapse').forEach(function(el) {
                el.classList.add('show');
            });
            // Make all headers look "expanded"
            document.querySelectorAll('#reportsAccordion .accordion-button').forEach(function(btn) {
                btn.classList.remove('collapsed');
                btn.setAttribute('aria-expanded', 'true');
            });
        });
    </script>
@endpush


@section('content')
    {{-- Filter for reports --}}
    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                {{-- <div class="card-header">
                    <h5 class="mb-0">{{ __('Filter') }}</h5>
                </div> --}}
                {{-- <div class="row"> --}}
                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
                    @include('allReports.report-selector')
                </div>
            </div>
        </div>
    </div>
    </div>

    {{-- ACCORDIANS --}}
    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body table-border-style">

                    {{-- Reports Menu with Bootstrap Accordions --}}

                    {{-- ================== Business overview ================== --}}
                    <div class="accordion pb-2" id="reportsAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="businessOverviewHeading">
                                <button class="accordion-button {{ Request::segment(1) == 'report' ? '' : 'collapsed' }}"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#businessOverviewCollapse"
                                    aria-expanded="{{ Request::segment(1) == 'report' ? 'true' : 'false' }}"
                                    aria-controls="businessOverviewCollapse">
                                    <i class="bi bi-file-earmark-text me-2"></i>
                                    {{ __('Business Overview') }}
                                </button>
                            </h2>
                            <div id="businessOverviewCollapse"
                                class="accordion-collapse collapse {{ Request::segment(1) == 'report' ? 'show' : '' }}"
                                aria-labelledby="businessOverviewHeading" data-bs-parent="#reportsAccordion">
                                <div class="accordion-body p-0">
                                    <ul class="list-unstyled mb-0 row g-0">

                                        {{-- ================== Existing Reports ================== --}}
                                        <li
                                            class="col-6 report-item {{ Request::route()->getName() == 'report.balance.sheet' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.balance.sheet') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Balance Sheet') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>
                                        <li
                                            class="col-6 report-item {{ Request::route()->getName() == 'balance-sheet-detail.index' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('balance-sheet-detail.index') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Balance Sheet Detail') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>
                                        <li
                                            class="col-6 report-item {{ Request::route()->getName() == 'balance-sheet-standard.index' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('balance-sheet-standard.index') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Balance Sheet Summary') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>
                                        <li
                                            class="col-6 report-item {{ Request::route()->getName() == 'cash-flow.index' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('cash-flow.index') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Statement of Cash Flows') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>
                                        <li
                                            class="col-6 report-item {{ Request::route()->getName() == 'reports.profit_loss' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('reports.profit_loss') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Profit and Loss') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>
                                        <li
                                            class="col-6 report-item {{ Request::route()->getName() == 'profit-loss-detail.index' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('profit-loss-detail.index') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Profit and Loss Detail') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>


                    {{-- ================== Who owes you ================== --}}
                    <div class="accordion pb-2" id="reportsAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="whoOwesYouHeading">
                                <button class="accordion-button {{ Request::segment(1) == 'report' ? '' : 'collapsed' }}"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#whoOwesYouCollapse"
                                    aria-expanded="{{ Request::segment(1) == 'report' ? 'true' : 'false' }}"
                                    aria-controls="whoOwesYouCollapse">
                                    <i class="bi bi-file-earmark-text me-2"></i>
                                    {{ __('Who Owes You') }}
                                </button>
                            </h2>
                            <div id="whoOwesYouCollapse"
                                class="accordion-collapse collapse {{ Request::segment(1) == 'report' ? 'show' : '' }}"
                                aria-labelledby="whoOwesYouHeading" data-bs-parent="#reportsAccordion">
                                <div class="accordion-body p-0">
                                    <ul class="list-unstyled mb-0 row g-0">

                                        @php
                                            $receivableReports = [
                                                [
                                                    'route' => 'receivables.aging_summary',
                                                    'label' => 'Accounts receivable aging summary',
                                                ],
                                                [
                                                    'route' => 'receivables.aging_details',
                                                    'label' => 'Accounts receivable aging details',
                                                ],
                                                [
                                                    'route' => 'receivables.collection_details',
                                                    'label' => 'Collection details',
                                                ],
                                                [
                                                    'route' => 'receivables.customer_balance_detail',
                                                    'label' => 'Customer balance detail report',
                                                ],
                                                [
                                                    'route' => 'receivables.customer_balance',
                                                    'label' => 'Customer balance',
                                                ],
                                                ['route' => 'receivables.invoice_list', 'label' => 'Invoice list'],
                                                [
                                                    'route' => 'receivables.open_invoice_list',
                                                    'label' => 'Open invoice list',
                                                ],
                                                [
                                                    'route' => 'receivables.invoices_received_payments',
                                                    'label' => 'Invoices and received payments',
                                                ],
                                            ];
                                        @endphp

                                        @foreach ($receivableReports as $report)
                                            <li
                                                class="col-6 report-item {{ Request::route()->getName() == $report['route'] ? 'active' : '' }}">
                                                <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                    href="{{ route($report['route']) }}">
                                                    <span class="d-flex align-items-center">
                                                        <i class="bi bi-file-earmark-text me-2"></i>
                                                        {{ __($report['label']) }}
                                                    </span>
                                                    <span class="report-actions">
                                                        <i class="bi bi-star text-muted"></i>
                                                        <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                    </span>
                                                </a>
                                            </li>
                                        @endforeach

                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ================== What You Owe ================== --}}
                    <div class="accordion pb-2" id="reportsAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="whoYouOweHeading">
                                <button class="accordion-button {{ Request::segment(1) == 'report' ? '' : 'collapsed' }}"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#whoYouOweCollapse"
                                    aria-expanded="{{ Request::segment(1) == 'report' ? 'true' : 'false' }}"
                                    aria-controls="whoYouOweCollapse">
                                    <i class="bi bi-file-earmark-text me-2"></i>
                                    {{ __('Who You Owe') }}
                                </button>
                            </h2>
                            <div id="whoYouOweCollapse"
                                class="accordion-collapse collapse {{ Request::segment(1) == 'report' ? 'show' : '' }}"
                                aria-labelledby="whoYouOweHeading" data-bs-parent="#reportsAccordion">
                                <div class="accordion-body p-0">
                                    <ul class="list-unstyled mb-0 row g-0">

                                        @php
                                            $payableReports = [
                                                [
                                                    'route' => 'payables.aging_summary',
                                                    'label' => 'Accounts payable aging summary',
                                                ],
                                                [
                                                    'route' => 'payables.aging_details',
                                                    'label' => 'Accounts payable aging details',
                                                ],
                                                ['route' => 'payables.bills_payments', 'label' => 'Bills and payments'],
                                                [
                                                    'route' => 'payables.vendor_balance_summary',
                                                    'label' => 'Vendor balance summary',
                                                ],
                                                [
                                                    'route' => 'payables.vendor_balance_detail',
                                                    'label' => 'Vendor balance detail',
                                                ],
                                                [
                                                    'route' => 'payables.unpaid_bills_report',
                                                    'label' => 'Unpaid Bills',
                                                ],
                                            ];
                                        @endphp

                                        @foreach ($payableReports as $report)
                                            <li
                                                class="col-6 report-item {{ Request::route()->getName() == $report['route'] ? 'active' : '' }}">
                                                <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                    href="{{ route($report['route']) }}">
                                                    <span class="d-flex align-items-center">
                                                        <i class="bi bi-file-earmark-text me-2"></i>
                                                        {{ __($report['label']) }}
                                                    </span>
                                                    <span class="report-actions">
                                                        <i class="bi bi-star text-muted"></i>
                                                        <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                    </span>
                                                </a>
                                            </li>
                                        @endforeach

                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>





                    {{-- ================== ACCOUNTING ================== --}}
                    <div class="accordion pb-2" id="reportsAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="accountingHeading">
                                <button class="accordion-button {{ Request::segment(1) == 'report' ? '' : 'collapsed' }}"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#accountingCollapse"
                                    aria-expanded="{{ Request::segment(1) == 'report' ? 'true' : 'false' }}"
                                    aria-controls="accountingCollapse">
                                    <i class="bi bi-file-earmark-text me-2"></i>
                                    {{ __('Accounting Reports') }}
                                </button>
                            </h2>
                            <div id="accountingCollapse"
                                class="accordion-collapse collapse {{ Request::segment(1) == 'report' ? 'show' : '' }}"
                                aria-labelledby="accountingHeading" data-bs-parent="#reportsAccordion">
                                <div class="accordion-body p-0">
                                    <ul class="list-unstyled mb-0 row g-0">

                                        {{-- ================== Existing Reports ================== --}}
                                        <li
                                            class="col-6 report-item {{ Request::route()->getName() == 'report.account.statement' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.account.statement') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Account Statement') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        <li
                                            class="col-6 report-item {{ Request::route()->getName() == 'report.invoice.summary' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.invoice.summary') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Invoice Summary') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        <li class="col-6 report-item">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.sales') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Sales Report') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        <li class="col-6 report-item">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.receivables') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Receivables') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        <li class="col-6 report-item">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.payables') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Payables') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        <li
                                            class="col-6 report-item {{ Request::route()->getName() == 'report.bill.summary' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.bill.summary') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Bill Summary') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        <li
                                            class="col-6 report-item {{ Request::route()->getName() == 'report.product.stock.report' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.product.stock.report') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Product Stock') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        <li
                                            class="col-6 report-item {{ request()->is('reports-monthly-cashflow') || request()->is('reports-quarterly-cashflow') ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.monthly.cashflow') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Cash Flow') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        <li
                                            class="col-6 report-item {{ Request::route()->getName() == 'report.income.summary' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.income.summary') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Income Summary') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        <li
                                            class="col-6 report-item {{ Request::route()->getName() == 'report.expense.summary' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.expense.summary') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Expense Summary') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        <li
                                            class="col-6 report-item {{ Request::route()->getName() == 'report.income.vs.expense.summary' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.income.vs.expense.summary') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Income VS Expense') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        <li
                                            class="col-6 report-item {{ Request::route()->getName() == 'report.tax.summary' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.tax.summary') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Tax Summary') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        {{-- ================== Added From Sidebar ================== --}}
                                        {{-- Transactions --}}
                                        <li
                                            class="col-6 report-item {{ Request::route()->getName() == 'transaction.index' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('transaction.index') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('All Transactions') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        <li
                                            class="col-6 report-item {{ Request::route()->getName() == 'transaction.bankTransactions' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('transaction.bankTransactions') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Bank Transactions') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        <li
                                            class="col-6 report-item {{ Request::route()->getName() == 'reciept.index' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('reciept.index') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Receipts') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        {{-- Double Entry --}}
                                        <li
                                            class="col-6 report-item {{ Request::route()->getName() == 'report.ledger' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.ledger', 0) }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Ledger Summary') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>


                                        <li
                                            class="col-6 report-item {{ Request::route()->getName() == 'report.profit.loss' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.profit.loss') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Profit & Loss') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        <li
                                            class="col-6 report-item {{ Request::route()->getName() == 'trial.balance' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('trial.balance') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Trial Balance') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        {{-- Budget & Goal --}}
                                        <li
                                            class="col-6 report-item {{ Request::segment(1) == 'budget' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('budget.index') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Budget Planner') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        <li class="col-6 report-item {{ Request::segment(1) == 'goal' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('goal.index') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Financial Goal') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ================== HRM ================== --}}
                    <div class="accordion pb-2" id="reportsAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="hrmHeading">
                                <button
                                    class="accordion-button {{ Request::segment(1) == 'reports-payroll' || Request::segment(1) == 'reports-leave' || Request::segment(1) == 'reports-monthly-attendance' ? '' : 'collapsed' }}"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#hrmCollapse"
                                    aria-expanded="{{ Request::segment(1) == 'reports-payroll' || Request::segment(1) == 'reports-leave' || Request::segment(1) == 'reports-monthly-attendance' ? 'true' : 'false' }}"
                                    aria-controls="hrmCollapse">
                                    <i class="bi bi-people me-2"></i>
                                    {{ __('HRM Reports') }}
                                </button>
                            </h2>
                            <div id="hrmCollapse"
                                class="accordion-collapse collapse {{ Request::segment(1) == 'reports-payroll' || Request::segment(1) == 'reports-leave' || Request::segment(1) == 'reports-monthly-attendance' ? 'show' : '' }}"
                                aria-labelledby="hrmHeading" data-bs-parent="#reportsAccordion">
                                <div class="accordion-body p-0">
                                    <ul class="list-unstyled mb-0 row g-0">
                                        {{-- Payroll --}}
                                        <li
                                            class="col-6 report-item {{ request()->is('reports-payroll') ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.payroll') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Payroll') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        {{-- Leave --}}
                                        <li
                                            class="col-6 report-item {{ request()->is('reports-leave') ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.leave') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Leave') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        {{-- Monthly Attendance --}}
                                        <li
                                            class="col-6 report-item {{ request()->is('reports-monthly-attendance') ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.monthly.attendance') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Monthly Attendance') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ================== CRM ================== --}}
                    <div class="accordion pb-2" id="reportsAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="crmHeading">
                                <button
                                    class="accordion-button {{ Request::segment(1) == 'reports-lead' || Request::segment(1) == 'reports-deal' ? '' : 'collapsed' }}"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#crmCollapse"
                                    aria-expanded="{{ Request::segment(1) == 'reports-lead' || Request::segment(1) == 'reports-deal' ? 'true' : 'false' }}"
                                    aria-controls="crmCollapse">
                                    <i class="bi bi-person-check me-2"></i>
                                    {{ __('CRM Reports') }}
                                </button>
                            </h2>
                            <div id="crmCollapse"
                                class="accordion-collapse collapse {{ Request::segment(1) == 'reports-lead' || Request::segment(1) == 'reports-deal' ? 'show' : '' }}"
                                aria-labelledby="crmHeading" data-bs-parent="#reportsAccordion">
                                <div class="accordion-body p-0">
                                    <ul class="list-unstyled mb-0 row g-0">
                                        {{-- Lead Report --}}
                                        <li
                                            class="col-6 report-item {{ request()->is('reports-lead') ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.lead') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Lead') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        {{-- Deal Report --}}
                                        <li
                                            class="col-6 report-item {{ request()->is('reports-deal') ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.deal') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Deal') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ================== PROJECT ================== --}}
                    <div class="accordion pb-2" id="reportsAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="projectHeading">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#projectCollapse" aria-expanded="false"
                                    aria-controls="projectCollapse">
                                    <i class="bi bi-kanban me-2"></i>
                                    {{ __('Project Reports') }}
                                </button>
                            </h2>
                            <div id="projectCollapse" class="accordion-collapse collapse"
                                aria-labelledby="projectHeading" data-bs-parent="#reportsAccordion">
                                <div class="accordion-body p-0">
                                    <ul class="list-unstyled mb-0">
                                        <li
                                            class="report-item {{ Request::route()->getName() == 'project.dashboard' ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('project.dashboard') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Project Dashboard') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ================== POS ================== --}}
                    <div class="accordion pb-2" id="reportsAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="posHeading">
                                <button
                                    class="accordion-button {{ Request::segment(1) == 'reports-warehouse' || Request::segment(1) == 'reports-daily-purchase' || Request::segment(1) == 'reports-monthly-purchase' || Request::segment(1) == 'reports-daily-pos' || Request::segment(1) == 'reports-monthly-pos' || Request::segment(1) == 'reports-pos-vs-purchase' ? '' : 'collapsed' }}"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#posCollapse"
                                    aria-expanded="{{ Request::segment(1) == 'reports-warehouse' || Request::segment(1) == 'reports-daily-purchase' || Request::segment(1) == 'reports-monthly-purchase' || Request::segment(1) == 'reports-daily-pos' || Request::segment(1) == 'reports-monthly-pos' || Request::segment(1) == 'reports-pos-vs-purchase' ? 'true' : 'false' }}"
                                    aria-controls="posCollapse">
                                    <i class="bi bi-shop me-2"></i>
                                    {{ __('POS Reports') }}
                                </button>
                            </h2>
                            <div id="posCollapse"
                                class="accordion-collapse collapse {{ Request::segment(1) == 'reports-warehouse' || Request::segment(1) == 'reports-daily-purchase' || Request::segment(1) == 'reports-monthly-purchase' || Request::segment(1) == 'reports-daily-pos' || Request::segment(1) == 'reports-monthly-pos' || Request::segment(1) == 'reports-pos-vs-purchase' ? 'show' : '' }}"
                                aria-labelledby="posHeading" data-bs-parent="#reportsAccordion">
                                <div class="accordion-body p-0">
                                    <ul class="list-unstyled mb-0 row g-0">
                                        {{-- Warehouse Report --}}
                                        <li
                                            class="col-6 report-item {{ request()->is('reports-warehouse') ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.warehouse') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Warehouse Report') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        {{-- Purchase Daily/Monthly Report --}}
                                        <li
                                            class="col-6 report-item {{ request()->is('reports-daily-purchase') || request()->is('reports-monthly-purchase') ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.daily.purchase') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('Purchase Daily/Monthly Report') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        {{-- POS Daily/Monthly Report --}}
                                        <li
                                            class="col-6 report-item {{ request()->is('reports-daily-pos') || request()->is('reports-monthly-pos') ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.daily.pos') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('POS Daily/Monthly Report') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>

                                        {{-- POS VS Purchase Report --}}
                                        <li
                                            class="col-6 report-item {{ request()->is('reports-pos-vs-purchase') ? 'active' : '' }}">
                                            <a class="report-link d-flex align-items-center justify-content-between p-3"
                                                href="{{ route('report.pos.vs.purchase') }}">
                                                <span class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    {{ __('POS VS Purchase Report') }}
                                                </span>
                                                <span class="report-actions">
                                                    <i class="bi bi-star text-muted"></i>
                                                    <i class="bi bi-three-dots-vertical text-muted ms-1"></i>
                                                </span>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                {{-- accordians end --}}
            </div>
        </div>
    </div>
    </div>
@endsection
