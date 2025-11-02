<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class QuickBooksApiController extends Controller
{
    protected $clientId;
    protected $clientSecret;
    protected $authUrl;
    protected $tokenUrl;
    protected $scope;
    protected $redirectUri;
    protected $baseUrl;

    public function __construct()
    {
        // Directly read from env to avoid config caching issues
        // $this->clientId     = env('QB_CLIENT_ID');
        $this->clientId     = 'ABpCTnsvhjnEcBTWVIofKoQ482JGuH6yXpb4ARb4uFvefO145m';
        $this->clientSecret = 'gUVkoksUL0busJJRj8WNEj7BEjnCveF4EoWGU2xp';
        $this->authUrl      = env('QB_AUTH_URL', 'https://appcenter.intuit.com/connect/oauth2');
        $this->tokenUrl     = env('QB_TOKEN_URL', 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer');
        $this->scope        = env('QB_SCOPE', 'com.intuit.quickbooks.accounting openid profile email');
        $this->redirectUri  = env('QB_REDIRECT_URI', 'https://localhost/Csuite-quick/quickbooks/callback');
        $this->baseUrl      = env('QB_BASE_URL', 'https://sandbox-quickbooks.api.intuit.com');
    }

    protected function accessToken()
    {
        return Session::get('qb_access_token');
    }

    protected function realmId()
    {
        return Session::get('qb_realm_id');
    }

    /**
     * Redirect user to QuickBooks login/consent screen.
     */
    public function connect()
    {
        // dd($this->redirectUri,$this->clientId,$this->scope,$this->authUrl,$this->tokenUrl);
        $params = http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'scope' => $this->scope,
            'redirect_uri' => $this->redirectUri,
            'state' => csrf_token(),
        ]);

        return redirect("{$this->authUrl}?{$params}");
    }

    /**
     * Handle the callback from QuickBooks OAuth.
     */
    public function callback(Request $request)
    {
        $code = $request->query('code');
        $realmId = $request->query('realmId');

        if (!$code) {
            return response()->json(['error' => 'No authorization code returned'], 400);
        }

        // Exchange authorization code for tokens
        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->post($this->tokenUrl, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri,
            ]);

        if ($response->failed()) {
            return response()->json([
                'error' => 'Token exchange failed',
                'details' => $response->json(),
            ], 400);
        }

        $data = $response->json();

        // Store access token and realmId in session
        Session::put('qb_access_token', $data['access_token']);
        Session::put('qb_refresh_token', $data['refresh_token']);
        Session::put('qb_realm_id', $realmId);

        return redirect()->route('quickbooks.sync')->with('success', 'QuickBooks connected successfully!');
    }

    /**
     * Helper to run a QuickBooks query.
     */
    protected function runQuery(string $query)
    {
        $token = $this->accessToken();
        $realm = $this->realmId();

        if (!$token || !$realm) {
            return response()->json([
                'error' => true,
                'message' => 'Missing QuickBooks connection. Please connect first.',
            ], 401);
        }

        $url = "{$this->baseUrl}/v3/company/{$realm}/query?query=" . urlencode($query);

        $response = Http::withToken($token)
            ->accept('application/json')
            ->get($url);

        if ($response->status() === 401) {
            return response()->json([
                'error' => true,
                'message' => 'Unauthorized (401). Access token may be expired. Please reconnect.',
            ], 401);
        }

        return $response->json();
    }

    /**
     * View with buttons for actions
     */
    public function index()
    {
        $connected = $this->accessToken() && $this->realmId();
        return view('quickbooks_sync', compact('connected'));
    }

    public function invoices()
    {
        $data = $this->runQuery("SELECT * FROM Invoice STARTPOSITION 1 MAXRESULTS 50");
        dd($data);
    }

    public function bills()
    {
        $data = $this->runQuery("SELECT * FROM Bill STARTPOSITION 1 MAXRESULTS 50");
        dd($data);
    }

    public function customers()
    {
        $data = $this->runQuery("SELECT * FROM Customer STARTPOSITION 1 MAXRESULTS 50");
        dd($data);
    }

    public function chartOfAccounts()
    {
        $data = $this->runQuery("SELECT * FROM Account STARTPOSITION 1 MAXRESULTS 100");
        dd($data);
    }

    public function vendors()
    {
        $data = $this->runQuery("SELECT * FROM Vendor STARTPOSITION 1 MAXRESULTS 50");
        dd($data);
    }
    public function journalEntries()
    {
        $data = $this->runQuery("SELECT * FROM JournalEntry STARTPOSITION 1 MAXRESULTS 100");
        dd($data);
    }
    // 📊 Fetch Journal Entries (with all available fields)
    // public function journalReport()
    // {
    //     $query = "SELECT Id, SyncToken, MetaData, DocNumber, TxnDate, PrivateNote,Line, ExchangeRate, Adjustment, TxnSource, Domain, sparse
    //             FROM JournalEntry STARTPOSITION 1 MAXRESULTS 500";

    //     $data = $this->runQuery($query);
    //     dd($data); // dump journal entries for now
    // }


    // 📘 Fetch QuickBooks “Journal Report” (Financial Report API)
    public function journalFRReport(Request $request)
    {
        $token = $this->accessToken();
        $realm = $this->realmId();

        // Optional query parameters
        $startDate = $request->input('start_date', '2025-10-01');
        $endDate   = $request->input('end_date', now()->format('Y-m-d'));
        $accountingMethod = $request->input('accounting_method', 'Accrual');
        $url = "{$this->baseUrl}/v3/company/{$realm}/reports/JournalReport"
            . "?start_date={$startDate}&end_date={$endDate}&accounting_method={$accountingMethod}";

        $response = Http::withToken($token)
            ->accept('application/json')
            ->get($url);

        $data = $response->json();
        dd($data);
    }
    // 💰 Fetch Bill Payments (Vendor Payments)
    public function billPayments()
    {
        $query = "SELECT *
              FROM BillPayment STARTPOSITION 1 MAXRESULTS 200";

        $data = $this->runQuery($query);

        dd($data); // for now, dump response to see structure
    }
    public function billsWithPayments()
    {
        try {
            $billsResponse = $this->runQuery("SELECT * FROM Bill");
            $paymentsResponse = $this->runQuery("SELECT * FROM BillPayment");

            // Get bills from QueryResponse
            $bills = collect($billsResponse['QueryResponse']['Bill'] ?? [])->map(function ($bill) {
                return [
                    'BillId' => $bill['Id'] ?? null,
                    'VendorName' => $bill['VendorRef']['name'] ?? null,
                    'VendorId' => $bill['VendorRef']['value'] ?? null,
                    'TxnDate' => $bill['TxnDate'] ?? null,
                    'DueDate' => $bill['DueDate'] ?? null,
                    'TotalAmount' => $bill['TotalAmt'] ?? 0,
                    'Balance' => $bill['Balance'] ?? 0,
                    'Currency' => $bill['CurrencyRef']['name'] ?? null,
                    'Address' => $bill['VendorAddr']['Line1'] ?? null,
                    'Payments' => [] // Placeholder for linked payments
                ];
            });

            // Get payments from QueryResponse
            $payments = collect($paymentsResponse['QueryResponse']['BillPayment'] ?? [])->map(function ($payment) {
                return [
                    'PaymentId' => $payment['Id'] ?? null,
                    'VendorId' => $payment['VendorRef']['value'] ?? null,
                    'VendorName' => $payment['VendorRef']['name'] ?? null,
                    'TxnDate' => $payment['TxnDate'] ?? null,
                    'TotalAmount' => $payment['TotalAmt'] ?? 0,
                    'PayType' => $payment['PayType'] ?? null,
                    'LinkedTxn' => collect($payment['Line'] ?? [])
                        ->pluck('LinkedTxn')
                        ->flatten(1)
                        ->toArray(),
                ];
            });

            // Link payments to corresponding bills
            $billsWithPayments = $bills->map(function ($bill) use ($payments) {
                $linkedPayments = $payments->filter(function ($payment) use ($bill) {
                    return collect($payment['LinkedTxn'])->contains(function ($txn) use ($bill) {
                        return isset($txn['TxnType'], $txn['TxnId'])
                            && $txn['TxnType'] === 'Bill'
                            && $txn['TxnId'] == $bill['BillId'];
                    });
                })->values();

                $bill['Payments'] = $linkedPayments;
                return $bill;
            });

            return response()->json([
                'status' => 'success',
                'count' => $billsWithPayments->count(),
                'data' => $billsWithPayments->values(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }
    public function invoicesWithPayments()
    {
        try {
            // Step 1: Fetch Invoices and Invoice Payments
            $invoices = $this->runQuery("SELECT * FROM Invoice");
            $payments = $this->runQuery("SELECT * FROM Payment"); // QuickBooks calls it "Payment"
            // dd($payments);
            // Step 2: Normalize invoices
            $invoices = collect($invoices['QueryResponse']['Invoice'] ?? [])->map(function ($invoice) {
                return [
                    'InvoiceId' => $invoice['Id'] ?? null,
                    'CustomerName' => $invoice['CustomerRef']['name'] ?? null,
                    'CustomerId' => $invoice['CustomerRef']['value'] ?? null,
                    'TxnDate' => $invoice['TxnDate'] ?? null,
                    'DueDate' => $invoice['DueDate'] ?? null,
                    'TotalAmount' => $invoice['TotalAmt'] ?? 0,
                    'Balance' => $invoice['Balance'] ?? 0,
                    'Currency' => $invoice['CurrencyRef']['name'] ?? null,
                    'Payments' => [] // Placeholder for linked payments
                ];
            });

            // Step 3: Normalize payments
            $payments = collect($payments['QueryResponse']['Payment'] ?? [])->map(function ($payment) {
                return [
                    'PaymentId' => $payment['Id'] ?? null,
                    'CustomerId' => $payment['CustomerRef']['value'] ?? null,
                    'CustomerName' => $payment['CustomerRef']['name'] ?? null,
                    'TxnDate' => $payment['TxnDate'] ?? null,
                    'TotalAmount' => $payment['TotalAmt'] ?? 0,
                    'PaymentMethod' => $payment['PaymentMethodRef']['name'] ?? null,
                    'LinkedTxn' => collect($payment['Line'] ?? [])->pluck('LinkedTxn')->flatten(1)->toArray(),
                ];
            });

            // Step 4: Link payments to corresponding invoices
            $invoicesWithPayments = $invoices->map(function ($invoice) use ($payments) {
                $linkedPayments = $payments->filter(function ($payment) use ($invoice) {
                    // dd($payment['LinkedTxn'],$payment);
                    return collect($payment['LinkedTxn'])->contains(function ($txn) use ($invoice) {
                        return isset($txn['TxnType'], $txn['TxnId'])
                            && $txn['TxnType'] == 'Invoice'
                            && $txn['TxnId'] == $invoice['InvoiceId'];
                    });
                })->values();

                $invoice['Payments'] = $linkedPayments;
                return $invoice;
            });

            return response()->json([
                'status' => 'success',
                'count' => $invoicesWithPayments->count(),
                'data' => $invoicesWithPayments->values(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }
    public function items()
{
    try {
        $itemsResponse = $this->runQuery("SELECT * FROM Item STARTPOSITION 1 MAXRESULTS 200");

        // Check if the response is already a JsonResponse (error from runQuery)
        if ($itemsResponse instanceof \Illuminate\Http\JsonResponse) {
            return $itemsResponse; // just return the error response
        }

        // QuickBooks wraps results inside QueryResponse
        $itemsData = $itemsResponse['QueryResponse']['Item'] ?? [];

        $items = collect($itemsData)->map(function ($item) {
            return [
                'ItemId' => $item['Id'] ?? null,
                'Name' => $item['Name'] ?? null,
                'Description' => $item['Description'] ?? null,
                'Type' => $item['Type'] ?? null,
                'IncomeAccount' => $item['IncomeAccountRef']['name'] ?? null,
                'ExpenseAccount' => $item['ExpenseAccountRef']['name'] ?? null,
                'AssetAccount' => $item['AssetAccountRef']['name'] ?? null,
                'UnitPrice' => $item['UnitPrice'] ?? 0,
                'QtyOnHand' => $item['QtyOnHand'] ?? 0,
                'TrackQtyOnHand' => $item['TrackQtyOnHand'] ?? false,
            ];
        });

        return response()->json([
            'status' => 'success',
            'count' => $items->count(),
            'data' => $items->values(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}
public function deposits()
{
    try {
        // 1️⃣ Fetch all deposits (up to 200 records)
        $depositResponse = $this->runQuery("SELECT * FROM Deposit STARTPOSITION 1 MAXRESULTS 200");

        // 2️⃣ Handle token/connection errors
        if ($depositResponse instanceof \Illuminate\Http\JsonResponse) {
            return $depositResponse; // early return if token expired or API error
        }

        // 3️⃣ Extract data safely from QuickBooks QueryResponse
        $depositsData = $depositResponse['QueryResponse']['Deposit'] ?? [];

        // 4️⃣ Map data into a clean, readable format
        $deposits = collect($depositsData)->map(function ($deposit) {
            return [
                'DepositId'     => $deposit['Id'] ?? null,
                'TxnDate'       => $deposit['TxnDate'] ?? null,
                'TotalAmt'      => $deposit['TotalAmt'] ?? 0,
                'PrivateNote'   => $deposit['PrivateNote'] ?? null,
                'Currency'      => $deposit['CurrencyRef']['name'] ?? null,
                'DepositTo'     => $deposit['DepositToAccountRef']['name'] ?? null,
                'LineCount'     => isset($deposit['Line']) ? count($deposit['Line']) : 0,
                'Lines'         => collect($deposit['Line'] ?? [])->map(function ($line) {
                    return [
                        'Amount' => $line['Amount'] ?? null,
                        'DetailType' => $line['DetailType'] ?? null,
                        'Entity' => $line['DepositLineDetail']['Entity']['name'] ?? null,
                        'Account' => $line['DepositLineDetail']['AccountRef']['name'] ?? null,
                        'PaymentMethod' => $line['DepositLineDetail']['PaymentMethodRef']['name'] ?? null,
                    ];
                }),
            ];
        });

        // 5️⃣ Return formatted response
        return response()->json([
            'status' => 'success',
            'count'  => $deposits->count(),
            'data'   => $deposits->values(),
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}
public function depositsWithVoucher()
{
    try {
        // 1️⃣ Fetch deposits (limit to 200 for now)
        $depositResponse = $this->runQuery("SELECT * FROM Deposit STARTPOSITION 1 MAXRESULTS 200");
        $deposits = $depositResponse['QueryResponse']['Deposit'] ?? [];

        // 2️⃣ Fetch all accounts for mapping
        $accountResponse = $this->runQuery("SELECT * FROM Account STARTPOSITION 1 MAXRESULTS 500");
        $accounts = collect($accountResponse['QueryResponse']['Account'] ?? [])->keyBy('Id');

        // 3️⃣ Determine earliest & latest deposit dates
        $dates = collect($deposits)->pluck('TxnDate')->filter()->sort();
        $startDate = $dates->first() ?? now()->subMonths(1)->format('Y-m-d');
        $endDate = $dates->last() ?? now()->format('Y-m-d');

        // 4️⃣ Fetch Journal Entries within that date range
        $journalResponse = $this->runQuery("SELECT * FROM JournalEntry WHERE TxnDate >= '$startDate' AND TxnDate <= '$endDate'");
        $journalEntries = collect($journalResponse['QueryResponse']['JournalEntry'] ?? []);

        // 5️⃣ Build combined vouchers
        $combined = collect($deposits)->map(function ($deposit) use ($accounts, $journalEntries) {
            $depositId = $deposit['Id'] ?? null;
            $txnDate = $deposit['TxnDate'] ?? null;
            $total = $deposit['TotalAmt'] ?? 0;

            // Find possible related journal entry (by date and amount)
            $relatedJE = $journalEntries->first(function ($je) use ($txnDate, $total) {
                return ($je['TxnDate'] ?? null) === $txnDate && (float)($je['TotalAmt'] ?? 0) === (float)$total;
            });

            // Build voucher lines (both sides)
            $voucherLines = collect($relatedJE['Line'] ?? [])->map(function ($line) use ($accounts) {
                $accId = $line['JournalEntryLineDetail']['AccountRef']['value'] ?? null;
                $account = $accounts[$accId] ?? null;
                return [
                    'PostingType' => $line['JournalEntryLineDetail']['PostingType'] ?? null,
                    'Account' => [
                        'id' => $accId,
                        'name' => $account['Name'] ?? null,
                        'type' => $account['AccountType'] ?? null,
                    ],
                    'Amount' => $line['Amount'] ?? null,
                ];
            });

            return [
                'VoucherNo' => $depositId,
                'TxnDate' => $txnDate,
                'TotalAmt' => $total,
                'PrivateNote' => $deposit['PrivateNote'] ?? null,
                'DepositTo' => [
                    'id' => $deposit['DepositToAccountRef']['value'] ?? null,
                    'name' => $deposit['DepositToAccountRef']['name'] ?? null,
                ],
                'VoucherLines' => $voucherLines->isNotEmpty()
                    ? $voucherLines
                    : collect($deposit['Line'] ?? [])->map(function ($line) use ($accounts) {
                        $accId = $line['DepositLineDetail']['AccountRef']['value'] ?? null;
                        $account = $accounts[$accId] ?? null;
                        return [
                            'PostingType' => 'Credit', // Default for deposit line
                            'Account' => [
                                'id' => $accId,
                                'name' => $account['Name'] ?? null,
                                'type' => $account['AccountType'] ?? null,
                            ],
                            'Amount' => $line['Amount'] ?? null,
                        ];
                    }),
            ];
        });

        // 6️⃣ Return response
        return response()->json([
            'status' => 'success',
            'count' => $combined->count(),
            'data' => $combined->values(),
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}
public function getSalesReceipts()
{
    try {
        // Initialize QuickBooks service and get access token
        $accessToken = $this->initializeQuickBooksService();

        if (!$accessToken) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access token not found. Please reauthorize QuickBooks.'
            ], 401);
        }

        $realmId = $this->realmId(); // Company ID (Realm ID)
        $baseUrl = $this->baseUrl;   // Base URL for environment (sandbox or production)

        // Construct the API URL for SalesReceipts
        $url = "{$baseUrl}/v3/company/{$realmId}/query?minorversion=75";
        $query = "SELECT * FROM SalesReceipt"; // Get all sales receipts

        // Send GET request to QuickBooks API
        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/text'
            ])
            ->post($url, $query);

        // Decode JSON response
        $data = $response->json();

        // Check if data exists
        if (isset($data['QueryResponse']['SalesReceipt'])) {
            $salesReceipts = collect($data['QueryResponse']['SalesReceipt'])->map(function ($receipt) {
                return [
                    'Id' => $receipt['Id'] ?? null,
                    'DocNumber' => $receipt['DocNumber'] ?? null,
                    'TxnDate' => $receipt['TxnDate'] ?? null,
                    'CustomerRef' => $receipt['CustomerRef']['name'] ?? null,
                    'TotalAmt' => $receipt['TotalAmt'] ?? null,
                    'PrivateNote' => $receipt['PrivateNote'] ?? null,
                    'PaymentMethodRef' => $receipt['PaymentMethodRef']['name'] ?? null,
                    'DepositToAccountRef' => $receipt['DepositToAccountRef']['name'] ?? null,
                    'LineItems' => collect($receipt['Line'] ?? [])->map(function ($line) {
                        return [
                            'LineNum' => $line['LineNum'] ?? null,
                            'Description' => $line['Description'] ?? null,
                            'Amount' => $line['Amount'] ?? null,
                            'DetailType' => $line['DetailType'] ?? null,
                            'ItemRef' => $line['SalesItemLineDetail']['ItemRef']['name'] ?? null,
                            'Qty' => $line['SalesItemLineDetail']['Qty'] ?? null,
                            'UnitPrice' => $line['SalesItemLineDetail']['UnitPrice'] ?? null,
                        ];
                    }),
                ];
            });

            return response()->json([
                'status' => 'success',
                'count' => $salesReceipts->count(),
                'data' => $salesReceipts
            ]);
        }

        // Return empty data if no records
        return response()->json([
            'status' => 'success',
            'count' => 0,
            'data' => []
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
}

}
