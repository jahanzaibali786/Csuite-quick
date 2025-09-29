@extends('layouts.admin')

@section('content')
    <style>
        .section-row {
            background-color: #f2f2f2 !important;
            font-weight: bold;
        }

        .balance-sheet-standard-table {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .balance-sheet-standard-table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
        }

        .balance-sheet-standard-table tbody tr:hover {
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
            border-radius: 4px;
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

        .view-options-dropdown.show {
            display: block;
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
            border-radius: 4px;
        }

        .btn-outline:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        /* Side Modal Styles */
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

        .modal-overlay.show {
            display: block;
        }

        .general-options-modal,
        .filter-modal {
            position: fixed;
            top: 0;
            right: -400px;
            bottom: 0;
            width: 400px;
            background: white;
            box-shadow: -2px 0 10px rgba(0, 0, 0, 0.2);
            overflow-y: auto;
            transition: right 0.3s ease-in-out;
        }

        .modal-overlay.show .general-options-modal,
        .modal-overlay.show .filter-modal {
            right: 0;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid #e6e6e6;
            background: #f9fafb;
            position: sticky;
            top: 0;
            z-index: 1;
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
            font-size: 24px;
            color: #6b7280;
            cursor: pointer;
            padding: 4px 8px;
            line-height: 1;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .btn-close:hover {
            color: #262626;
            background: #e5e7eb;
        }

        .modal-content {
            padding: 24px;
            height: calc(100% - 80px);
            overflow-y: auto;
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
            width: 120px;
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

        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            border: none;
        }

        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }

        .w-100 {
            width: 100%;
        }

        /* Date picker styling */
        .input-group {
            position: relative;
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
        }

        .input-group-append {
            margin-left: -1px;
        }

        .input-group-text {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            margin-bottom: 0;
            font-size: 13px;
            font-weight: 400;
            line-height: 1.5;
            color: #495057;
            text-align: center;
            white-space: nowrap;
            background-color: #e9ecef;
            border: 1px solid #ced4da;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
        }

        .input-group-text:hover {
            background-color: #dee2e6;
        }

        #asOfDatePicker {
            cursor: pointer;
            background-color: white;
        }

        #asOfDatePicker[readonly] {
            background-color: white;
        }

        .daterangepicker {
            z-index: 3000 !important;
        }

        .datepicker {
            z-index: 3000 !important;
        }

        input[type="date"] {
            position: relative;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 13px;
            background: white;
            color: #262626;
            height: 36px;
        }

        input[type="date"]:focus {
            outline: none;
            border-color: #0969da;
            box-shadow: 0 0 0 2px rgba(9, 105, 218, 0.1);
        }

        /* Enhanced hierarchical table styles */
        #balance-sheet-standard-table td {
            padding: 6px 8px;
            vertical-align: middle;
        }

        #balance-sheet-standard-table .text-right {
            text-align: right;
        }

        #balance-sheet-standard-table strong {
            font-weight: bold;
        }

        .balance-sheet-standard-table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            font-size: 14px;
        }

        .balance-sheet-standard-table tbody tr.type-header-row {
            background-color: #e9ecef;
            font-weight: bold;
        }

        .balance-sheet-standard-table tbody tr.subtype-header-row {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            font-weight: 600;
        }

        .balance-sheet-standard-table tbody tr.subtype-header-row:hover {
            background-color: #f1f3f4;
        }

        .balance-sheet-standard-table tbody tr.account-header-row {
            background-color: #ffffff;
            border-left: 3px solid #007bff;
            font-weight: 500;
        }

        .balance-sheet-standard-table tbody tr.account-header-row:hover {
            background-color: #f8f9fa;
        }

        .balance-sheet-standard-table tbody tr.account-header-row.collapsed {
            display: none !important;
        }

        .balance-sheet-standard-table tbody tr.transaction-row {
            background-color: #f9f9f9;
            font-size: 12px;
            color: #666;
            border-left: 3px solid #28a745;
        }

        .balance-sheet-standard-table tbody tr.transaction-row:hover {
            background-color: #f0f0f0;
        }

        .balance-sheet-standard-table tbody tr.transaction-row.collapsed {
            display: none !important;
        }

        .balance-sheet-standard-table tbody tr.account-total-row {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            border-left: 3px solid #007bff;
            font-weight: 600;
        }

        .balance-sheet-standard-table tbody tr.account-total-row.collapsed {
            display: none !important;
        }

        .balance-sheet-standard-table tbody tr.subtype-total-row {
            background-color: #e9ecef;
            border-top: 2px solid #dee2e6;
            font-weight: 700;
        }

        .balance-sheet-standard-table tbody tr.subtype-total-row.collapsed {
            display: none !important;
        }

        .balance-sheet-standard-table tbody tr.type-total-row {
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            font-weight: 700;
        }

        .chevron-icon {
            transition: transform 0.2s ease;
            cursor: pointer;
            color: #007bff;
            font-size: 12px;
        }

        .chevron-icon:hover {
            color: #0056b3;
            transform: scale(1.1);
        }

        .collapsed-balance {
            font-style: italic;
            font-size: 11px;
            color: #6c757d !important;
            margin-left: 10px;
        }

        .balance-sheet-standard-table tbody tr td:first-child {
            padding-left: 12px;
        }

        .amount-cell {
            text-align: right;
            font-family: 'Courier New', monospace;
            font-size: 11px;
        }

        .transaction-debit {
            color: #dc3545;
            font-weight: 500;
        }

        .transaction-credit {
            color: #28a745;
            font-weight: 500;
        }

        .balance-sheet-standard-table tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.1);
        }

        .balance-sheet-standard-table tbody tr td:empty {
            border: none;
            padding: 4px;
        }

        .balance-sheet-standard-table tbody tr td {
            border-top: 1px solid #f1f1f1;
        }

        .balance-sheet-standard-table tbody tr:first-child td {
            border-top: none;
        }

        .balance-sheet-standard-table tbody tr {
            transition: all 0.3s ease;
        }

        @media print {
            .balance-sheet-standard-table {
                font-size: 10px;
            }

            .chevron-icon {
                display: none;
            }

            .collapsed-balance {
                display: none;
            }

            .balance-sheet-standard-table tbody tr.collapsed {
                display: table-row !important;
            }
        }

        @media (max-width: 768px) {
            .chevron-icon {
                font-size: 14px;
            }

            .collapsed-balance {
                display: block;
                margin-top: 4px;
                margin-left: 0;
            }

            .balance-sheet-standard-table {
                font-size: 12px;
            }

            .general-options-modal,
            .filter-modal {
                width: 100%;
                right: -100%;
            }

            .modal-overlay.show .general-options-modal,
            .modal-overlay.show .filter-modal {
                right: 0;
            }

            .modal-content {
                padding: 16px;
            }

            .modal-header {
                padding: 16px;
            }
        }

        .balance-sheet-standard-table.loading {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>

    <!-- Filter Controls -->
    <div class="filter-controls">
        <div class="filter-row">
            <div class="filter-group row mb-2">
                <div class="filter-item col-md-2">
                    <label class="filter-label">As of date</label>
                    <div class="input-group">
                        <input type="text" id="asOfDatePicker" class="form-control date-input"
                            value="{{ \Carbon\Carbon::now()->format('M d, Y') }}" readonly>
                    </div>
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
                <h2 class="report-title">Balance Sheet Standard</h2>
                <p class="date-range">
                    <span class="text-lightest f-12 ml-2" id="as-of-date-display"></span>
                </p>
            </div>
            <div class="table-responsive p-2">
                {!! $dataTable->table(['class' => 'table table-hover border-0 w-100 balance-sheet-standard-table', 'id' => 'balance-sheet-standard-table']) !!}
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
        (function($, moment) {
            "use strict";

            // Core Variables and State Management
            window.balanceSheetState = {
                balanceSheetTable: null,
                expandedSections: new Set(),
                viewState: {
                    viewType: 'normal',
                    expandState: 'expand'
                },
                reportOptions: {
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
                },
                filterState: {
                    asOfDate: $('#asOfDate').val() || moment().format('YYYY-MM-DD'),
                    accountingMethod: $('#accounting-method').val() || 'accrual',
                    viewOption: 'normal'
                }
            };

            window.balanceSheetConstants = {
                TABLE_SELECTOR: '#balance-sheet-standard-table',
                DT_NAME: "balance-sheet-standard-table",
                getDt: () => window.LaravelDataTables && window.LaravelDataTables[window.balanceSheetConstants.DT_NAME]
            };

            // Debounce utility
            if (!window.debounce) {
                window.debounce = function(fn, wait) {
                    let t;
                    return function() {
                        const args = arguments;
                        clearTimeout(t);
                        t = setTimeout(() => fn.apply(this, args), wait);
                    };
                };
            }

            // Modal Manager - FIXED
            window.ModalManager = {
                initialize: function() {
                    // General Options Modal
                    $('#general-options-btn').on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('General options button clicked');
                        $('#general-options-overlay').addClass('show');
                    });

                    $('#close-general-options').on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        $('#general-options-overlay').removeClass('show');
                    });

                    // Filter Modal
                    $('#filter-btn').on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('Filter button clicked');
                        ModalManager.syncFilterModalValues();
                        $('#filter-overlay').addClass('show');
                    });

                    $('#close-filter').on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        $('#filter-overlay').removeClass('show');
                    });

                    // Apply filters
                    $('#apply-filters').on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        ModalManager.applyFiltersFromModal();
                        $('#filter-overlay').removeClass('show');
                    });

                    // Close modals when clicking overlay
                    $('#general-options-overlay, #filter-overlay').on('click', function(e) {
                        if (e.target === this) {
                            $(this).removeClass('show');
                        }
                    });

                    // General options change handler
                    $('.general-options-modal input, .general-options-modal select').on('change', function() {
                        window.ReportFormatter.applyGeneralOptions();
                    });

                    // ESC key to close modals
                    $(document).on('keydown', function(e) {
                        if (e.key === 'Escape') {
                            $('.modal-overlay').removeClass('show');
                        }
                    });
                },

                syncFilterModalValues: function() {
                    const state = window.balanceSheetState.filterState;
                    $('#modal-as-of-date').val(moment(state.asOfDate).format('MMM D, YYYY'));
                    $('#modal-accounting-method').val(state.accountingMethod);
                },

                applyFiltersFromModal: function() {
                    const modalDate = $('#modal-as-of-date').val();
                    const modalMethod = $('#modal-accounting-method').val();
                    
                    if (modalDate && moment(modalDate, 'MMM D, YYYY').isValid()) {
                        const newDate = moment(modalDate, 'MMM D, YYYY').format('YYYY-MM-DD');
                        window.balanceSheetState.filterState.asOfDate = newDate;
                        $('#asOfDate').val(newDate);
                        $('#asOfDatePicker').val(modalDate);
                    }
                    
                    window.balanceSheetState.filterState.accountingMethod = modalMethod;
                    $('#accounting-method').val(modalMethod);
                    
                    window.FilterManager.updateAsOfDisplay();
                    window.TableManager.safeDraw();
                }
            };

            // Filter Manager
            window.FilterManager = {
                initialize: function() {
                    FilterManager.initializeDatePicker();
                    FilterManager.setupEventHandlers();
                    FilterManager.updateAsOfDisplay();
                },

                initializeDatePicker: function() {
                    const state = window.balanceSheetState.filterState;

                    // Try daterangepicker first
                    if ($.fn.daterangepicker) {
                        $('#asOfDatePicker').daterangepicker({
                            singleDatePicker: true,
                            showDropdowns: true,
                            startDate: state.asOfDate ? moment(state.asOfDate) : moment(),
                            locale: {
                                format: 'MMM D, YYYY'
                            }
                        });

                        $('#asOfDatePicker').on('apply.daterangepicker', function(ev, picker) {
                            const newDate = picker.startDate.format('YYYY-MM-DD');
                            state.asOfDate = newDate;
                            $('#asOfDate').val(newDate);
                            FilterManager.updateAsOfDisplay();
                            window.TableManager.safeDraw();
                        });
                    }
                    // Fallback to Bootstrap datepicker
                    else if ($.fn.datepicker) {
                        $('#asOfDatePicker').datepicker({
                            format: 'M d, yyyy',
                            autoclose: true,
                            todayHighlight: true
                        }).on('changeDate', function(e) {
                            const newDate = moment(e.date).format('YYYY-MM-DD');
                            state.asOfDate = newDate;
                            $('#asOfDate').val(newDate);
                            FilterManager.updateAsOfDisplay();
                            window.TableManager.safeDraw();
                        });
                    }
                    // HTML5 date input fallback
                    else {
                        $('#asOfDatePicker').attr('type', 'date').val(state.asOfDate);
                        $('#asOfDatePicker').on('change', function() {
                            const newDate = $(this).val();
                            if (newDate && moment(newDate).isValid()) {
                                state.asOfDate = newDate;
                                $('#asOfDate').val(newDate);
                                FilterManager.updateAsOfDisplay();
                                window.TableManager.safeDraw();
                            }
                        });
                    }

                    // Ensure hidden field has correct value
                    if ($('#asOfDate').length) {
                        $('#asOfDate').val(state.asOfDate || moment().format('YYYY-MM-DD'));
                    }
                },

                setupEventHandlers: function() {
                    const $table = $(window.balanceSheetConstants.TABLE_SELECTOR);
                    const state = window.balanceSheetState.filterState;

                    $table.on('preXhr.dt', function(e, settings, data) {
                        data.asOfDate = state.asOfDate || moment().format('YYYY-MM-DD');
                        data.accountingMethod = state.accountingMethod || $('#accounting-method').val() || 'accrual';
                        data.accounting_method = state.accountingMethod || $('#accounting-method').val() || 'accrual';
                        data.viewOption = state.viewOption || 'normal';
                    });

                    $('#accounting-method').on('change', function() {
                        state.accountingMethod = $(this).val();
                        window.TableManager.safeDraw();
                    });
                },

                updateAsOfDisplay: function() {
                    const asOfDate = window.balanceSheetState.filterState.asOfDate;
                    const display = asOfDate ? moment(asOfDate).format('MMM DD, YYYY') : moment().format('MMM DD, YYYY');
                    $('#as-of-date-display').text('as of ' + display);
                }
            };

            // View State Manager - FIXED
            window.ViewStateManager = {
                initializeViewOptions: function() {
                    $('#view-options-btn').on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        $('#view-options-dropdown').toggleClass('show');
                    });

                    $(document).on('click', '.view-option-item', function(e) {
                        e.preventDefault();
                        e.stopPropagation();

                        const value = $(this).data('value');

                        if (value === 'compact' || value === 'normal') {
                            ViewStateManager.setViewType(value);
                        } else if (value === 'expand' || value === 'collapse') {
                            ViewStateManager.setExpandState(value);
                        }

                        ViewStateManager.applyGlobalViewState();
                        ViewStateManager.updateViewCheckmarks();
                        $('#view-options-dropdown').removeClass('show');

                        // Only refresh table for view type changes
                        if (value === 'compact' || value === 'normal') {
                            window.TableManager.safeDraw();
                        }
                    });

                    // Close dropdown when clicking outside
                    $(document).on('click', function(e) {
                        if (!$(e.target).closest('.view-options').length) {
                            $('#view-options-dropdown').removeClass('show');
                        }
                    });

                    ViewStateManager.setExpandState('expand');
                    ViewStateManager.applyGlobalViewState();
                    ViewStateManager.updateViewCheckmarks();
                },

                updateViewCheckmarks: function() {
                    $('.view-option-item').removeClass('active');
                    $('.view-option-item[data-value="' + window.balanceSheetState.viewState.viewType + '"]').addClass('active');
                    $('.view-option-item[data-value="' + window.balanceSheetState.viewState.expandState + '"]').addClass('active');
                },

                setViewType: function(type) {
                    if (type === 'compact') {
                        window.balanceSheetState.viewState.viewType = 'compact';
                        window.balanceSheetState.viewState.expandState = 'collapse';
                    } else if (type === 'normal') {
                        window.balanceSheetState.viewState.viewType = 'normal';
                    }
                },

                setExpandState: function(state) {
                    if (state === 'collapse') {
                        window.balanceSheetState.viewState.expandState = 'collapse';
                        if (window.balanceSheetState.viewState.viewType !== 'compact') {
                            window.balanceSheetState.viewState.viewType = 'normal';
                        }
                    } else if (state === 'expand') {
                        window.balanceSheetState.viewState.expandState = 'expand';
                        window.balanceSheetState.viewState.viewType = 'normal';
                    }
                },

                applyGlobalViewState: function() {
                    const $reportContent = $('#report-content');
                    $reportContent.removeClass('compact-view');

                    if (window.balanceSheetState.viewState.viewType === 'compact') {
                        $reportContent.addClass('compact-view');
                        ViewStateManager.collapseAllSections();
                        window.balanceSheetState.expandedSections.clear();
                    } else {
                        if (window.balanceSheetState.viewState.expandState === 'expand' && !ViewStateManager._isTableRedraw) {
                            ViewStateManager.expandAllSections();
                        } else if (window.balanceSheetState.viewState.expandState === 'collapse' && !ViewStateManager._isTableRedraw) {
                            ViewStateManager.collapseAllSections();
                        }
                    }

                    ViewStateManager._isTableRedraw = false;
                },

                _isTableRedraw: false,

                expandAllSections: function() {
                    $('.chevron-icon[data-parent-type="subtype"]').each(function() {
                        const parentId = $(this).data('parent-id');
                        if (parentId === undefined) return;
                        const key = 'subtype:' + parentId;
                        window.HierarchyManager.expandHierarchy('subtype', parentId);
                        window.balanceSheetState.expandedSections.add(key);
                        $(this).removeClass('fa-chevron-right').addClass('fa-chevron-down');
                    });

                    $('.chevron-icon[data-parent-type="account"]').each(function() {
                        const parentId = $(this).data('parent-id');
                        if (parentId === undefined) return;
                        const key = 'account:' + parentId;
                        window.HierarchyManager.expandHierarchy('account', parentId);
                        window.balanceSheetState.expandedSections.add(key);
                        $(this).removeClass('fa-chevron-right').addClass('fa-chevron-down');
                    });

                    $('.section-header-row').each(function() {
                        $(this).addClass('section-expanded').find('.toggle-chevron')
                            .removeClass('fa-chevron-right').addClass('fa-chevron-down');
                        $(this).find('.section-total-amount').hide();
                    });
                },

                collapseAllSections: function() {
                    $('.chevron-icon[data-parent-type="account"]').each(function() {
                        const parentId = $(this).data('parent-id');
                        if (parentId === undefined) return;
                        const key = 'account:' + parentId;
                        window.HierarchyManager.collapseHierarchy('account', parentId);
                        window.balanceSheetState.expandedSections.delete(key);
                        $(this).removeClass('fa-chevron-down').addClass('fa-chevron-right');
                    });

                    $('.chevron-icon[data-parent-type="subtype"]').each(function() {
                        const parentId = $(this).data('parent-id');
                        if (parentId === undefined) return;
                        const key = 'subtype:' + parentId;
                        window.HierarchyManager.collapseHierarchy('subtype', parentId);
                        window.balanceSheetState.expandedSections.delete(key);
                        $(this).removeClass('fa-chevron-down').addClass('fa-chevron-right');
                    });

                    $('.section-header-row').each(function() {
                        $(this).removeClass('section-expanded').find('.toggle-chevron')
                            .removeClass('fa-chevron-down').addClass('fa-chevron-right');
                        $(this).find('.section-total-amount').show();
                    });
                }
            };

            // Hierarchy Manager
            window.HierarchyManager = {
                collapseHierarchy: function(parentType, parentId) {
                    if (!parentType || parentId === undefined) return;

                    if (parentType === 'subtype') {
                        $('.child-of-subtype-' + parentId).hide().addClass('collapsed');
                        $('.chevron-icon[data-parent-type="account"]').each(function() {
                            const $c = $(this);
                            const pid = $c.data('parent-id');
                            if ($('.child-of-account-' + pid).length) {
                                $c.removeClass('fa-chevron-down').addClass('fa-chevron-right');
                            }
                        });
                    } else if (parentType === 'account') {
                        $('.child-of-account-' + parentId).hide().addClass('collapsed');
                        $('.chevron-icon[data-parent-type="account"][data-parent-id="' + parentId + '"]')
                            .removeClass('fa-chevron-down').addClass('fa-chevron-right');
                    }
                },

                expandHierarchy: function(parentType, parentId) {
                    if (!parentType || parentId === undefined) return;

                    if (parentType === 'subtype') {
                        $('.child-of-subtype-' + parentId).each(function() {
                            const $el = $(this);
                            if ($el.hasClass('account-header-row') || $el.hasClass('subtype-total-row')) {
                                $el.show().removeClass('collapsed');
                            } else {
                                const classes = ($el.attr('class') || '').split(/\s+/);
                                const accountClass = classes.find(c => c.indexOf('child-of-account-') === 0);
                                if (accountClass) {
                                    const accountId = accountClass.replace('child-of-account-', '');
                                    const $accChevron = $('.chevron-icon[data-parent-type="account"][data-parent-id="' + accountId + '"]');
                                    if ($accChevron.length && $accChevron.hasClass('fa-chevron-down')) {
                                        $el.show().removeClass('collapsed');
                                    }
                                } else {
                                    $el.show().removeClass('collapsed');
                                }
                            }
                        });
                        $('.chevron-icon[data-parent-type="subtype"][data-parent-id="' + parentId + '"]')
                            .removeClass('fa-chevron-right').addClass('fa-chevron-down');
                    } else if (parentType === 'account') {
                        $('.child-of-account-' + parentId).show().removeClass('collapsed');
                        $('.chevron-icon[data-parent-type="account"][data-parent-id="' + parentId + '"]')
                            .removeClass('fa-chevron-right').addClass('fa-chevron-down');
                    }
                },

                reapplyPersistedState: function() {
                    if (window.balanceSheetState.viewState.viewType === 'compact') {
                        return;
                    }

                    // Restore subtype states
                    $('.chevron-icon[data-parent-type="subtype"]').each(function() {
                        const parentId = $(this).data('parent-id');
                        if (parentId === undefined) return;
                        const key = 'subtype:' + parentId;
                        const $row = $(this).closest('tr');

                        if (window.balanceSheetState.expandedSections.has(key)) {
                            HierarchyManager.expandHierarchy('subtype', parentId);
                            window.BalanceHelpers.hideCollapsedBalance($row);
                            $(this).removeClass('fa-chevron-right').addClass('fa-chevron-down');
                        } else {
                            HierarchyManager.collapseHierarchy('subtype', parentId);
                            window.BalanceHelpers.showCollapsedBalance($row, 'subtype', parentId);
                            $(this).removeClass('fa-chevron-down').addClass('fa-chevron-right');
                        }
                    });

                    // Restore account states
                    $('.chevron-icon[data-parent-type="account"]').each(function() {
                        const parentId = $(this).data('parent-id');
                        if (parentId === undefined) return;
                        const key = 'account:' + parentId;
                        const $row = $(this).closest('tr');

                        if (window.balanceSheetState.expandedSections.has(key)) {
                            HierarchyManager.expandHierarchy('account', parentId);
                            window.BalanceHelpers.hideCollapsedBalance($row);
                            $(this).removeClass('fa-chevron-right').addClass('fa-chevron-down');
                        } else {
                            HierarchyManager.collapseHierarchy('account', parentId);
                            window.BalanceHelpers.showCollapsedBalance($row, 'account', parentId);
                            $(this).removeClass('fa-chevron-down').addClass('fa-chevron-right');
                        }
                    });

                    // Update section headers
                    $('.section-header-row').each(function() {
                        const sectionId = $(this).attr('data-row-id');
                        if (!sectionId) return;
                        const anyChildVisible = $('[data-parent="' + sectionId + '"]').filter(':visible').length > 0;
                        if (anyChildVisible) {
                            $(this).addClass('section-expanded').find('.section-total-amount').hide();
                            $(this).find('.toggle-chevron').removeClass('fa-chevron-right').addClass('fa-chevron-down');
                        } else {
                            $(this).removeClass('section-expanded').find('.section-total-amount').show();
                            $(this).find('.toggle-chevron').removeClass('fa-chevron-down').addClass('fa-chevron-right');
                        }
                    });
                },

                initializeChevronHandlers: function() {
                    $(document).on('click', '.chevron-icon', function(e) {
                        e.preventDefault();
                        e.stopPropagation();

                        const $icon = $(this);
                        const parentType = $icon.data('parent-type');
                        const parentId = $icon.data('parent-id');
                        const $row = $icon.closest('tr');

                        if (parentType === undefined || parentId === undefined) return;

                        const key = parentType + ':' + parentId;

                        if ($icon.hasClass('fa-chevron-down')) {
                            // Collapse - NO TABLE REFRESH
                            $icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
                            HierarchyManager.collapseHierarchy(parentType, parentId);
                            window.BalanceHelpers.showCollapsedBalance($row, parentType, parentId);
                            window.balanceSheetState.expandedSections.delete(key);
                        } else {
                            // Expand - NO TABLE REFRESH
                            $icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
                            HierarchyManager.expandHierarchy(parentType, parentId);
                            window.BalanceHelpers.hideCollapsedBalance($row);
                            window.balanceSheetState.expandedSections.add(key);
                        }

                        HierarchyManager.updateParentSectionState($row);
                    });
                },

                updateParentSectionState: function($row) {
                    const $section = $row.closest('[data-row-id]').length ?
                        $row.closest('[data-row-id]') :
                        $row.prevAll('[data-row-id]:first');

                    if ($section.length) {
                        const sectionId = $section.attr('data-row-id');
                        if (sectionId) {
                            const anyChildVisible = $('[data-parent="' + sectionId + '"]').filter(':visible').length > 0;
                            if (anyChildVisible) {
                                $section.addClass('section-expanded').find('.section-total-amount').hide();
                                $section.find('.toggle-chevron').removeClass('fa-chevron-right').addClass('fa-chevron-down');
                            } else {
                                $section.removeClass('section-expanded').find('.section-total-amount').show();
                                $section.find('.toggle-chevron').removeClass('fa-chevron-down').addClass('fa-chevron-right');
                            }
                        }
                    }
                }
            };

            // Balance Calculation Helpers
            window.BalanceHelpers = {
                calculateTotalBalance: function(parentType, parentId) {
                    let total = 0;
                    let found = false;

                    if (parentType === 'subtype') {
                        $('.subtype-total-row').each(function() {
                            if ($(this).hasClass('child-of-subtype-' + parentId)) {
                                const balanceText = $(this).find('td').eq(9).text().trim();
                                if (balanceText) {
                                    total = parseFloat(balanceText.replace(/[^0-9.-]+/g, '')) || 0;
                                    found = true;
                                    return false;
                                }
                            }
                        });
                    } else if (parentType === 'account') {
                        $('.account-total-row').each(function() {
                            if ($(this).hasClass('child-of-account-' + parentId)) {
                                const balanceText = $(this).find('td').eq(9).text().trim();
                                if (balanceText) {
                                    total = parseFloat(balanceText.replace(/[^0-9.-]+/g, '')) || 0;
                                    found = true;
                                    return false;
                                }
                            }
                        });
                    }

                    return found ? total : null;
                },

                showCollapsedBalance: function($row, parentType, parentId) {
                    if (!$row || !$row.length) return;
                    const totalBalance = BalanceHelpers.calculateTotalBalance(parentType, parentId);
                    const $accountCell = $row.find('td').first();
                    if (totalBalance === null) return;
                    if (!$accountCell.find('.collapsed-balance').length) {
                        $accountCell.append(' <span class="collapsed-balance">(' +
                            BalanceHelpers.formatCurrency(totalBalance) + ')</span>');
                    }
                },

                hideCollapsedBalance: function($row) {
                    if (!$row || !$row.length) return;
                    $row.find('.collapsed-balance').remove();
                },

                formatCurrency: function(amount) {
                    const num = typeof amount === 'number' ? amount : parseFloat(amount) || 0;
                    return num.toLocaleString('en');
                }
            };

            // Report Formatting
            window.ReportFormatter = {
                applyGeneralOptions: function() {
                    const options = window.balanceSheetState.reportOptions;

                    options.divideBy1000 = $('#divide-by-1000').prop('checked');
                    options.hideZeroAmounts = $('#hide-zero-amounts').prop('checked');
                    options.roundWholeNumbers = $('#round-whole-numbers').prop('checked');
                    options.negativeFormat = $('#negative-format').val();
                    options.showInRed = $('#show-in-red').prop('checked');
                    options.companyLogo = $('#company-logo').prop('checked');
                    options.reportPeriod = $('#report-period').prop('checked');
                    options.companyName = $('#company-name').prop('checked');
                    options.headerAlignment = $('#header-alignment').val();
                    options.datePrepared = $('#date-prepared').prop('checked');
                    options.timePrepared = $('#time-prepared').prop('checked');
                    options.reportBasis = $('#report-basis').prop('checked');
                    options.footerAlignment = $('#footer-alignment').val();

                    ReportFormatter.applyNumberFormatting();
                    ReportFormatter.applyHeaderFooterSettings();
                    ReportFormatter.applyCellFormatting();
                    window.TableManager.refreshTable();
                },

                applyNumberFormatting: function() {
                    $('#custom-number-format').remove();
                    let customCSS = '<style id="custom-number-format">';
                    if (window.balanceSheetState.reportOptions.showInRed) {
                        customCSS += '.negative-amount { color: #dc2626 !important; }';
                    }
                    if (window.balanceSheetState.reportOptions.hideZeroAmounts) {
                        customCSS += '.zero-amount { display: none !important; }';
                    }
                    customCSS += '</style>';
                    $('head').append(customCSS);
                },

                applyHeaderFooterSettings: function() {
                    const options = window.balanceSheetState.reportOptions;
                    $('.report-title-section').css('text-align', options.headerAlignment);
                    $('.company-name').toggle(options.companyName);
                    $('.date-range').toggle(options.reportPeriod);
                    ReportFormatter.updateReportFooter();
                },

                updateReportFooter: function() {
                    const options = window.balanceSheetState.reportOptions;
                    $('.report-footer').remove();

                    if (options.datePrepared || options.timePrepared || options.reportBasis) {
                        const currentDate = new Date();
                        const dateStr = currentDate.toLocaleDateString();
                        const timeStr = currentDate.toLocaleTimeString();
                        const basisStr = $('#accounting-method').val() === 'accrual' ? 'Accrual Basis' : 'Cash Basis';

                        let footerHTML = '<div class="report-footer" style="padding: 20px; border-top: 1px solid #e6e6e6; text-align: ' +
                            options.footerAlignment + '; font-size: 12px; color: #6b7280;">';

                        if (options.datePrepared) footerHTML += '<div>Date Prepared: ' + dateStr + '</div>';
                        if (options.timePrepared) footerHTML += '<div>Time Prepared: ' + timeStr + '</div>';
                        if (options.reportBasis) footerHTML += '<div>Report Basis: ' + basisStr + '</div>';
                        footerHTML += '</div>';
                        $('#report-content').append(footerHTML);
                    }
                },

                applyCellFormatting: function() {
                    const $table = $(window.balanceSheetConstants.TABLE_SELECTOR);
                    const options = window.balanceSheetState.reportOptions;

                    $table.find('tbody tr').each(function() {
                        const $row = $(this);
                        $row.find('td').each(function() {
                            const $cell = $(this);
                            const text = $cell.text().trim();
                            const numText = text.replace(/[,$()]/g, '');

                            if (text && !isNaN(numText) && numText !== '') {
                                let value = parseFloat(numText);

                                if (options.hideZeroAmounts && value === 0) {
                                    $cell.addClass('zero-amount');
                                    return;
                                }

                                if (options.divideBy1000) value = value / 1000;
                                if (options.roundWholeNumbers) value = Math.round(value);

                                if (value < 0) {
                                    $cell.addClass('negative-amount');
                                    switch (options.negativeFormat) {
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
                                } else {
                                    $cell.text((0).toLocaleString());
                                }
                            }
                        });
                    });
                }
            };

            // Table Management
            window.TableManager = {
                refreshTable: function() {
                    const dt = window.balanceSheetConstants.getDt();
                    if (dt && typeof dt.draw === 'function') {
                        dt.draw(false);
                    }
                },

                safeDraw: function() {
                    TableManager.safeDrawDebounced();
                },

                safeDrawDebounced: window.debounce(function() {
                    const dt = window.balanceSheetConstants.getDt();
                    if (dt && typeof dt.draw === 'function') {
                        dt.draw(false);
                    }
                }, 250),

                setupDataTableEvents: function() {
                    const $table = $(window.balanceSheetConstants.TABLE_SELECTOR);
                    window.balanceSheetState.balanceSheetTable = window.balanceSheetConstants.getDt();

                    if (window.balanceSheetState.balanceSheetTable) {
                        $table.on('draw.dt', function() {
                            if (typeof attachToggleHandlers === 'function') {
                                attachToggleHandlers();
                            }

                            window.ViewStateManager._isTableRedraw = true;
                            window.ViewStateManager.applyGlobalViewState();
                            window.HierarchyManager.reapplyPersistedState();
                            window.ReportFormatter.applyCellFormatting();
                        });

                        $table.on('preXhr.dt', function(e, settings, data) {
                            data.asOfDate = $('#asOfDate').val() || moment().format('YYYY-MM-DD');
                            data.accounting_method = $('#accounting-method').val();
                            data.accountingMethod = $('#accounting-method').val();
                        });

                        if (typeof attachToggleHandlers === 'function') {
                            attachToggleHandlers();
                        }
                    }
                }
            };

            // Legacy Section Handlers
            window.LegacySectionHandlers = {
                initialize: function() {
                    $(document).on('click', '.section-toggle', function(e) {
                        LegacySectionHandlers.handleSectionToggle.call(this, e);
                    });
                },

                handleSectionToggle: function(e) {
                    e.preventDefault();
                    if (window.balanceSheetState.viewState.viewType === 'compact') return;

                    const $this = $(this);
                    const group = $this.closest('tr').data('row-id');
                    const $row = $this.closest('tr');
                    const $chevron = $this.find('.toggle-chevron');
                    const $sectionTotal = $row.find('.section-total-amount[data-group="' + group + '"]');
                    const $childRows = $('.group-' + group);

                    if ($chevron.length === 0) return;

                    if ($chevron.hasClass('fa-chevron-down')) {
                        $childRows.hide();
                        $chevron.removeClass('fa-chevron-down').addClass('fa-chevron-right');
                        $sectionTotal.show();
                    } else {
                        $childRows.show();
                        $chevron.removeClass('fa-chevron-right').addClass('fa-chevron-down');
                        $sectionTotal.hide();
                    }
                },

                toggleSection: function($headerRow) {
                    if (window.balanceSheetState.viewState.viewType === 'compact') return;
                    const sectionId = $headerRow.attr('data-row-id');
                    if (!sectionId) return;

                    if ($headerRow.hasClass('section-expanded')) {
                        LegacySectionHandlers.collapseSection(sectionId);
                    } else {
                        LegacySectionHandlers.expandSection(sectionId);
                    }
                },

                expandSection: function(sectionId) {
                    const $headerRow = $('[data-row-id="' + sectionId + '"]');
                    const $childRows = $('[data-parent="' + sectionId + '"]');

                    $headerRow.addClass('section-expanded');
                    $childRows.addClass('section-expanded').show();
                    $headerRow.find('.toggle-chevron').removeClass('fa-chevron-right').addClass('fa-chevron-down');
                    $headerRow.find('.section-total-amount').hide();
                    window.balanceSheetState.expandedSections.add('section:' + sectionId);
                },

                collapseSection: function(sectionId) {
                    const $headerRow = $('[data-row-id="' + sectionId + '"]');
                    const $childRows = $('[data-parent="' + sectionId + '"]');

                    $headerRow.removeClass('section-expanded');
                    $childRows.removeClass('section-expanded').hide();
                    $headerRow.find('.toggle-chevron').removeClass('fa-chevron-down').addClass('fa-chevron-right');
                    $headerRow.find('.section-total-amount').show();
                    window.balanceSheetState.expandedSections.delete('section:' + sectionId);
                }
            };

            // Public API and Event Handlers
            window.BalanceSheetAPI = {
                setAsOfDate: function(date) {
                    const m = moment(date);
                    if (m.isValid()) {
                        window.balanceSheetState.filterState.asOfDate = m.format('YYYY-MM-DD');
                        $('#asOfDate').val(window.balanceSheetState.filterState.asOfDate);
                        window.FilterManager.updateAsOfDisplay();
                        window.TableManager.safeDraw();
                    }
                },

                setAccountingMethod: function(method) {
                    window.balanceSheetState.filterState.accountingMethod = method;
                    $('#accounting-method').val(method);
                    window.TableManager.safeDraw();
                },

                setViewOption: function(view) {
                    $('#view-options-dropdown .view-option-item').filter(function() {
                        return $(this).data('value') === view;
                    }).trigger('click');
                }
            };

            window.BalanceSheetEventHandlers = {
                initialize: function() {
                    // Close dropdowns when clicking outside
                    $(document).on('click', function(e) {
                        if (!$(e.target).closest('.view-options').length) {
                            $('#view-options-dropdown').removeClass('show');
                        }
                    });
                }
            };

            // Expose API globally
            window.BalanceSheetFilters = window.BalanceSheetAPI;

            // Main Initialization Function
            function initializeBalanceSheet() {
                console.log('Initializing Balance Sheet...');
                
                // Initialize all managers in correct order
                window.ModalManager.initialize();
                window.ViewStateManager.initializeViewOptions();
                window.HierarchyManager.initializeChevronHandlers();
                window.FilterManager.initialize();
                window.LegacySectionHandlers.initialize();
                window.BalanceSheetEventHandlers.initialize();
                window.TableManager.setupDataTableEvents();

                // Set initial state
                window.FilterManager.updateAsOfDisplay();
                window.ViewStateManager.applyGlobalViewState();
                window.HierarchyManager.reapplyPersistedState();

                console.log('Balance Sheet initialized successfully');
            }

            // Initialize when document ready
            $(document).ready(function() {
                // Wait a bit for DataTable to be fully loaded
                setTimeout(function() {
                    window.balanceSheetState.balanceSheetTable = window.balanceSheetConstants.getDt();
                    initializeBalanceSheet();
                }, 100);
            });

        })(jQuery, moment);
    </script>

    {!! $dataTable->scripts() !!}
@endpush
