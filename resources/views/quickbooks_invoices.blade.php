@extends('layouts.admin')

@section('content')
    <style>
        .container {
            margin: 0 auto;
            display: flex;
            flex-direction: column;
        }
        .buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
    </style>
    <div class="container">
        <h1>QuickBooks Import</h1>

        <!-- QuickBooks Connection Status and Actions -->
        <div class="mb-4">
            @php
                $qbController = new \App\Http\Controllers\QuickBooksApiController();
                $connected = $qbController->accessToken() && $qbController->realmId();
            @endphp

            @if($connected)
                <div class="alert alert-success">
                    <strong>Connected to QuickBooks</strong>
                    <a href="{{ route('quickbooks.disconnect') }}" class="btn btn-sm btn-outline-danger ml-2">Disconnect</a>
                </div>
            @else
                <div class="alert alert-warning">
                    <strong>Not connected to QuickBooks</strong>
                    <a href="{{ route('quickbooks.connect') }}" class="btn btn-sm btn-primary ml-2">Connect to QuickBooks</a>
                </div>
            @endif
        </div>

        <div class="buttons">
            <form action="{{ route('quickbooks.import.customers') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary">Import Customers</button>
            </form>
            {{-- //import chart of accounts --}}
            <form action="{{ route('quickbooks.import.chartOfAccounts') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary">Import Chart of Accounts</button>
            </form>
            {{-- //import vendors --}}
            <form action="{{ route('quickbooks.import.vendors') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary">Import Vendors</button>
            </form>
            {{-- //import items --}}
            <form action="{{ route('quickbooks.import.items') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary">Import Items</button>
            </form>
            <form action="{{ route('quickbooks.import.invoices') }}" method="POST" id="importInvoicesForm">
                @csrf
                <button type="submit" class="btn btn-primary" id="importInvoicesBtn">Import Invoices</button>
            </form>
            {{-- //import bills --}}
            <form action="{{ route('quickbooks.import.bills') }}" method="POST" id="importBillsForm">
                @csrf
                <button type="submit" class="btn btn-success" id="importBillsBtn">Import Bills</button>
            </form>
            {{-- //import expenses --}}
            <form action="{{ route('quickbooks.import.expenses') }}" method="POST" id="importExpensesForm">
                @csrf
                <button type="submit" class="btn btn-warning" id="importExpensesBtn">Import Expenses</button>
            </form>
            {{-- journalReport --}}
            <form action="{{ route('quickbooks.import.journalReport') }}" method="POST" id="journalReportForm">
                @csrf
                <button type="submit" class="btn btn-info" id="journalReportBtn">Import Journal Report</button>
            </form>
            <div id="importResults" class="mt-4" style="display: none;">
                <h3>Import Results</h3>
                <div id="resultsContent"></div>
            </div>
        
            <script>
                // Handle Invoices Import
                document.getElementById('importInvoicesForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    handleImport('importInvoicesBtn', this.action, 'Invoices');
                });

                // Handle Bills Import
                document.getElementById('importBillsForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    handleImport('importBillsBtn', this.action, 'Bills');
                });

                // Handle Expenses Import
                document.getElementById('importExpensesForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    handleImport('importExpensesBtn', this.action, 'Expenses');
                });
                // Handle Journal Report Import
                document.getElementById('journalReportForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    handleImport('journalReportBtn', this.action, 'Journal Report');
                });
                
                function handleImport(btnId, actionUrl, type) {
                    const btn = document.getElementById(btnId);
                    const resultsDiv = document.getElementById('importResults');
                    const resultsContent = document.getElementById('resultsContent');

                    btn.disabled = true;
                    btn.textContent = `Importing ${type}...`;

                    fetch(actionUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({})
                    })
                    .then(response => response.json())
                    .then(data => {
                        resultsContent.innerHTML = `
                            <div class="alert alert-${data.status === 'success' ? 'success' : 'danger'}">
                                <h4>${type} Import Results</h4>
                                <p>${data.message}</p>
                                ${data.status === 'success' ? `
                                    <ul>
                                        <li>Imported: ${data.imported}</li>
                                        <li>Skipped: ${data.skipped}</li>
                                        <li>Failed: ${data.failed}</li>
                                    </ul>
                                ` : ''}
                            </div>
                        `;
                        resultsDiv.style.display = 'block';
                    })
                    .catch(error => {
                        resultsContent.innerHTML = `
                            <div class="alert alert-danger">
                                <h4>Error Importing ${type}</h4>
                                <p>${error.message}</p>
                            </div>
                        `;
                        resultsDiv.style.display = 'block';
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.textContent = `Import ${type}`;
                    });
                }
            </script>
        </div>
    </div>
@endsection