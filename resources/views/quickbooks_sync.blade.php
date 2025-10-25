<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>QuickBooks Sync</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <style>
        body { font-family: Arial, sans-serif; padding: 30px; }
        .btn { padding: 10px 16px; margin: 6px; border-radius: 6px; border: 1px solid #ccc; cursor: pointer; }
        .btn-primary { background: #2b6cb0; color: #fff; border-color: #2b6cb0; }
        .btn-ghost { background: #fff; color: #333; }
    </style>
</head>
<body>
    <h2>QuickBooks — Test Sync</h2>

    <div>
        <button class="btn btn-ghost" id="qb-connect" onclick="window.open('{{ route('quickbooks.connect') }}','_blank')">Connect to QuickBooks</button>
    </div>

    <div style="margin-top:12px;">
        <button class="btn btn-primary qb-btn" data-route="{{ route('quickbooks.items') }}">Get Items</button>
        <button class="btn btn-primary qb-btn" data-route="{{ route('quickbooks.api.invoices') }}">Get Invoices</button>
        <button class="btn btn-primary qb-btn" data-route="{{ route('quickbooks.invoicesWithPayments') }}">Get Invoices with Payments</button>
        <button class="btn btn-primary qb-btn" data-route="{{ route('quickbooks.api.bills') }}">Get Bills</button>
        <button class="btn btn-primary qb-btn" data-route="{{ route('quickbooks.api.billPayments') }}">Get Bill Payments</button>
        <button class="btn btn-primary qb-btn" data-route="{{ route('quickbooks.billsWithPayments') }}">Get Bills with Payments</button>
        <button class="btn btn-primary qb-btn" data-route="{{ route('quickbooks.api.customers') }}">Get Customers</button>
        <button class="btn btn-primary qb-btn" data-route="{{ route('quickbooks.api.chartOfAccounts') }}">Get Chart of Accounts</button>
        <button class="btn btn-primary qb-btn" data-route="{{ route('quickbooks.api.vendors') }}">Get Vendors</button>
        <button class="btn btn-primary qb-btn" data-route="{{ route('quickbooks.journals') }}">Get Journal Entries</button>
        <button class="btn btn-primary qb-btn" data-route="{{ route('quickbooks.journalReport') }}">Get Journal Report</button>
        <button class="btn btn-primary qb-btn" data-route="{{ route('quickbooks.journalFRReport') }}">Get Journal FR Report</button>
        <button class="btn btn-primary qb-btn" data-route="{{ route('quickbooks.deposits') }}">Get Deposits</button>
        <button class="btn btn-primary qb-btn" data-route="{{ route('quickbooks.depositsWithVoucher') }}">Get Deposits with Voucher</button>
        <button class="btn btn-primary qb-btn" data-route="{{ route('quickbooks.salesReceipts') }}">Get Sales Receipts</button>
    </div>

    <hr>
    <p>If you see a JSON error about missing tokens, click <strong>Connect to QuickBooks</strong> and complete the OAuth flow (or the JS will open it for you automatically).</p>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        $(document).on('click', '.qb-btn', function(e) {
            e.preventDefault();
            const route = $(this).data('route');

            $.post(route)
                .done(function (data, textStatus, jqXHR) {
                    // If server returned JSON (likely an error or data)
                    let contentType = jqXHR.getResponseHeader('Content-Type') || '';
                    if (contentType.includes('application/json')) {
                        // If server said auth required, open connect_url in new tab
                        if (data && data.needs_auth && data.connect_url) {
                            const w = window.open(data.connect_url, '_blank');
                            if (!w) {
                                alert('Please allow popups or click the Connect button to authenticate.');
                            }
                            return;
                        }
                        // Otherwise pretty-print JSON in new tab
                        const html = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                        const w2 = window.open('', '_blank');
                        w2.document.write(html);
                        w2.document.title = 'QuickBooks JSON Response';
                    } else {
                        // Likely server dd() HTML — open raw HTML in a new window
                        const w = window.open('', '_blank');
                        w.document.write(data);
                    }
                })
                .fail(function (jqXHR) {
                    // Try to parse JSON body
                    try {
                        const body = JSON.parse(jqXHR.responseText);
                        if (body && body.needs_auth && body.connect_url) {
                            const w = window.open(body.connect_url, '_blank');
                            if (!w) alert('Please allow popups or click the Connect button to authenticate.');
                            return;
                        }
                    } catch (e) {
                        // not JSON — open raw
                    }
                    const w = window.open('', '_blank');
                    w.document.write(jqXHR.responseText || 'Request failed. Check console for details.');
                });
        });
    </script>
</body>
</html>
