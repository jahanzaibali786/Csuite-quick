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
    public $baseUrl;

    // public function __construct() // production
    // {
    //     $this->clientId = 'AByYeIrpQQktbXur2EwxXINJWZzJTJrkuH8BRb7P5I2p9L4qrL';
    //     $this->clientSecret = 'uBFqiKdEr9UvCps9SvmZh6ggRiu0CJxjPjMwhW4y';

    //     $this->authUrl = 'https://appcenter.intuit.com/connect/oauth2';
    //     $this->tokenUrl = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer';
    //     $this->scope = 'com.intuit.quickbooks.accounting openid profile email';
    //     $this->redirectUri = 'https://update.creativesuite.co/quickbooks/callback';
    //     $this->baseUrl = 'https://quickbooks.api.intuit.com';
    // }
    // public function __construct()
    // {
    //     // Directly read from env to avoid config caching issues
    //     // $this->clientId     = env('QB_CLIENT_ID');
    //     $this->clientId = 'AB91apFaxICw2LLUpMSqbaTj639nwk7xsDO3zLL9dFOee9lUYI';
    //     $this->clientSecret = 'VynpkqKTBrOaQE10eFqqwSgNGBFf9Wsc6ANcS3Vl';
    //     $this->authUrl = env('QB_AUTH_URL', 'https://appcenter.intuit.com/connect/oauth2');
    //     $this->tokenUrl = env('QB_TOKEN_URL', 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer');
    //     $this->scope = env('QB_SCOPE', 'com.intuit.quickbooks.accounting com.intuit.quickbooks.payment openid profile email');
    //     $this->redirectUri = env('QB_REDIRECT_URI', 'http://localhost:8012/csuitequickbook/quickbooks/callback');
    //     $this->baseUrl = env('QB_BASE_URL', 'https://sandbox-quickbooks.api.intuit.com');
    // }
    public function __construct() //my
    {
        // Directly read from env to avoid config caching issues
        // $this->clientId     = env('QB_CLIENT_ID');
        $this->clientId = 'ABpCTnsvhjnEcBTWVIofKoQ482JGuH6yXpb4ARb4uFvefO145m';
        $this->clientSecret = 'gUVkoksUL0busJJRj8WNEj7BEjnCveF4EoWGU2xp';
        $this->authUrl = env('QB_AUTH_URL', 'https://appcenter.intuit.com/connect/oauth2');
        $this->tokenUrl = env('QB_TOKEN_URL', 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer');
        $this->scope = env('QB_SCOPE', 'com.intuit.quickbooks.accounting com.intuit.quickbooks.payment openid profile email');
        $this->redirectUri = env('QB_REDIRECT_URI', 'http://localhost:8012/csuite/new/quickbooks/callback');
        $this->baseUrl = env('QB_BASE_URL', 'https://sandbox-quickbooks.api.intuit.com');
    }
    
    public function license()
    {
        return view('license');
    }

    public function privacyPolicy()
    {
        return view('privacy-policy');
    }
    public function accessToken()
    {
        return Session::get('qb_access_token');
    }

    public function realmId()
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
    public function disconnect()
    {
        $access = Session::pull('qb_access_token');
        $refresh = Session::pull('qb_refresh_token');
        Session::forget(['qb_realm_id','qb_token_expires_at','qb_oauth_state']);

        if ($access) {
            Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->post('https://developer.api.intuit.com/v2/oauth2/tokens/revoke', [
                    'token' => $access
                ]);
        }

        return redirect()->route('quickbooks.sync')->with('success', 'Disconnected from QuickBooks.');
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
    public function runQuery(string $query)
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
        dd($data, collect($data['QueryResponse']['Invoice'])->first());
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
        $endDate = $request->input('end_date', now()->format('Y-m-d'));
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
    try {
        $query = "SELECT * FROM BillPayment STARTPOSITION 1 MAXRESULTS 200";
        $response = $this->runQuery($query);

        // ✅ If it's a JsonResponse (expired token, etc.), just return it
        if ($response instanceof \Illuminate\Http\JsonResponse) {
            return $response;
        }

        // ✅ If QuickBooks returned a fault (error)
        if (isset($response['Fault'])) {
            throw new \Exception($response['Fault']['Error'][0]['Message'] ?? 'Error fetching BillPayments');
        }

        // ✅ Extract the data safely
        $billPayments = collect($response['QueryResponse']['BillPayment'] ?? []);

        // ✅ Get one sample record
        $first = $billPayments->first();

        // ✅ Dump a clean, structured view
        return dd([
            'status' => 'success',
            'count' => $billPayments->count(),
            'sample' => $first,
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}

    // public function billsWithPayments()
    // {
    //     try {
    //         // Step 1: Fetch Bills and Bill Payments
    //         $billsResponse = $this->runQuery("SELECT * FROM Bill");
    //         $paymentsResponse = $this->runQuery("SELECT * FROM BillPayment");

    //         // Step 2: Map Bills with Expense Accounts
    //         $bills = collect($billsResponse['QueryResponse']['Bill'] ?? [])->map(function ($bill) {
    //             // Expense (debit) accounts
    //             $expenseAccounts = collect($bill['Line'] ?? [])
    //                 ->map(function ($line) {
    //                     if (isset($line['AccountBasedExpenseLineDetail']['AccountRef'])) {
    //                         return [
    //                             'Id' => $line['AccountBasedExpenseLineDetail']['AccountRef']['value'] ?? null,
    //                             'Name' => $line['AccountBasedExpenseLineDetail']['AccountRef']['name'] ?? null,
    //                             'Amount' => $line['Amount'] ?? 0,
    //                             'Description' => $line['Description'] ?? null,
    //                         ];
    //                     }
    //                     return null;
    //                 })
    //                 ->filter()
    //                 ->values()
    //                 ->toArray();

    //             // A/P (credit) account
    //             $apAccount = [
    //                 'Id' => $bill['APAccountRef']['value'] ?? null,
    //                 'Name' => $bill['APAccountRef']['name'] ?? null,
    //             ];

    //             return [
    //                 'BillId' => $bill['Id'] ?? null,
    //                 'VendorName' => $bill['VendorRef']['name'] ?? null,
    //                 'VendorId' => $bill['VendorRef']['value'] ?? null,
    //                 'TxnDate' => $bill['TxnDate'] ?? null,
    //                 'DueDate' => $bill['DueDate'] ?? null,
    //                 'TotalAmount' => $bill['TotalAmt'] ?? 0,
    //                 'Balance' => $bill['Balance'] ?? 0,
    //                 'Currency' => $bill['CurrencyRef']['name'] ?? null,
    //                 'Address' => $bill['VendorAddr']['Line1'] ?? null,
    //                 'ExpenseAccounts' => $expenseAccounts,
    //                 'APAccount' => $apAccount,
    //                 'Payments' => [],
    //             ];
    //         });

    //         // Step 3: Map Bill Payments (with payment account)
    //         $payments = collect($paymentsResponse['QueryResponse']['BillPayment'] ?? [])->map(function ($payment) {
    //             // Detect payment source account
    //             $paymentAccount = null;
    //             if (isset($payment['CreditCardPayment']['CCAccountRef'])) {
    //                 $paymentAccount = $payment['CreditCardPayment']['CCAccountRef'];
    //             } elseif (isset($payment['CheckPayment']['BankAccountRef'])) {
    //                 $paymentAccount = $payment['CheckPayment']['BankAccountRef'];
    //             } elseif (isset($payment['PayFromAccountRef'])) {
    //                 $paymentAccount = $payment['PayFromAccountRef'];
    //             }

    //             return [
    //                 'PaymentId' => $payment['Id'] ?? null,
    //                 'VendorId' => $payment['VendorRef']['value'] ?? null,
    //                 'VendorName' => $payment['VendorRef']['name'] ?? null,
    //                 'TxnDate' => $payment['TxnDate'] ?? null,
    //                 'TotalAmount' => $payment['TotalAmt'] ?? 0,
    //                 'PayType' => $payment['PayType'] ?? null,
    //                 'PaymentAccount' => $paymentAccount ? [
    //                     'Id' => $paymentAccount['value'] ?? null,
    //                     'Name' => $paymentAccount['name'] ?? null,
    //                 ] : null,
    //                 'LinkedTxn' => collect($payment['Line'] ?? [])
    //                     ->pluck('LinkedTxn')
    //                     ->flatten(1)
    //                     ->toArray(),
    //             ];
    //         });

    //         // Step 4: Attach Payments to Their Corresponding Bills
    //         $billsWithPayments = $bills->map(function ($bill) use ($payments) {
    //             $linkedPayments = $payments->filter(function ($payment) use ($bill) {
    //                 return collect($payment['LinkedTxn'])->contains(function ($txn) use ($bill) {
    //                     return isset($txn['TxnType'], $txn['TxnId'])
    //                         && $txn['TxnType'] === 'Bill'
    //                         && $txn['TxnId'] == $bill['BillId'];
    //                 });
    //             })->values();

    //             $bill['Payments'] = $linkedPayments;
    //             return $bill;
    //         });

    //         // Step 5: Return response
    //         return dd([
    //             'status' => 'success',
    //             'count' => $billsWithPayments->count(),
    //             'data' => $billsWithPayments->values(),
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $e->getMessage(),
    //         ]);
    //     }
    // }
    public function billsWithPayments()
{
    try {
        // Step 1: Fetch Bills and Bill Payments
        $billsResponse = $this->runQuery("SELECT * FROM Bill");
        $paymentsResponse = $this->runQuery("SELECT * FROM BillPayment");

        // Step 2: Map Bills (with expense + A/P accounts)
        $bills = collect($billsResponse['QueryResponse']['Bill'] ?? [])->map(function ($bill) {
            $expenseAccounts = collect($bill['Line'] ?? [])
                ->map(function ($line) {
                    if (isset($line['AccountBasedExpenseLineDetail']['AccountRef'])) {
                        return [
                            'Id' => $line['AccountBasedExpenseLineDetail']['AccountRef']['value'] ?? null,
                            'Name' => $line['AccountBasedExpenseLineDetail']['AccountRef']['name'] ?? null,
                            'Amount' => $line['Amount'] ?? 0,
                            'Description' => $line['Description'] ?? null,
                        ];
                    }
                    return null;
                })
                ->filter()
                ->values()
                ->toArray();

            $apAccount = [
                'Id' => $bill['APAccountRef']['value'] ?? null,
                'Name' => $bill['APAccountRef']['name'] ?? null,
            ];

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
                'ExpenseAccounts' => $expenseAccounts,
                'APAccount' => $apAccount,
                'Payments' => [],
            ];
        });

        // Step 3: Map Payments (with payment account)
        $payments = collect($paymentsResponse['QueryResponse']['BillPayment'] ?? [])->map(function ($payment) {
            $paymentAccount = null;
            if (isset($payment['CreditCardPayment']['CCAccountRef'])) {
                $paymentAccount = $payment['CreditCardPayment']['CCAccountRef'];
            } elseif (isset($payment['CheckPayment']['BankAccountRef'])) {
                $paymentAccount = $payment['CheckPayment']['BankAccountRef'];
            } elseif (isset($payment['PayFromAccountRef'])) {
                $paymentAccount = $payment['PayFromAccountRef'];
            }

            return [
                'PaymentId' => $payment['Id'] ?? null,
                'VendorId' => $payment['VendorRef']['value'] ?? null,
                'VendorName' => $payment['VendorRef']['name'] ?? null,
                'TxnDate' => $payment['TxnDate'] ?? null,
                'TotalAmount' => $payment['TotalAmt'] ?? 0,
                'PayType' => $payment['PayType'] ?? null,
                'PaymentAccount' => $paymentAccount ? [
                    'Id' => $paymentAccount['value'] ?? null,
                    'Name' => $paymentAccount['name'] ?? null,
                ] : null,
                'LinkedTxn' => collect($payment['Line'] ?? [])
                    ->pluck('LinkedTxn')
                    ->flatten(1)
                    ->toArray(),
            ];
        });

        // Step 4: Attach payments to bills
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
        })
        // ✅ Only keep bills that actually have payments
        ->filter(function ($bill) {
            return count($bill['Payments']) > 0;
        })
        ->values();

        // ✅ Return only one bill (the first one with payments)
        $singleBill = $billsWithPayments->first();

        // Step 5: Return response
        return dd([
            'status' => 'success',
            'total_with_payments' => $billsWithPayments->count(),
            'single_bill' => $singleBill,
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

    public function expensesWithPayments()
    {
        try {
            // --- CONFIG ---
            // Payment-like types to fetch
            $typesToQuery = [
                'Payment',
                'Check',
                'BillPayment',
                'CreditCardCredit',
                'VendorCredit',
                'Deposit',
                'Purchase', // include as candidate payment
            ];

            // Fuzzy fallback config (only used when no explicit LinkedTxn matches found)
            $useFuzzyFallback = true;
            $fuzzyDateWindowDays = 7;      // +/- days
            $fuzzyAmountTolerance = 0.5;   // tolerance in currency units (adjust as needed)

            // --- 1) Fetch purchases (expenses) ---
            $purchasesResp = $this->runQuery("SELECT * FROM Purchase");
            $rawPurchases = $purchasesResp['QueryResponse']['Purchase'] ?? [];

            // --- 2) Fetch all payment-like types and merge ---
            $allRawPayments = collect();
            foreach ($typesToQuery as $type) {
                try {
                    $resp = $this->runQuery("SELECT * FROM {$type}");
                    $items = $resp['QueryResponse'][$type] ?? [];
                    $allRawPayments = $allRawPayments->merge(collect($items));
                } catch (\Exception $e) {
                    \Log::warning("Failed to fetch {$type}: " . $e->getMessage());
                }
            }

            // --- Helper: Extract & normalize LinkedTxn entries robustly ---
            $extractLinkedTxn = function ($raw) {
                $linked = [];

                // 1) Top-level LinkedTxn
                if (!empty($raw['LinkedTxn']) && is_array($raw['LinkedTxn'])) {
                    $linked = array_merge($linked, $raw['LinkedTxn']);
                }

                // 2) Inside Line[].LinkedTxn
                if (!empty($raw['Line']) && is_array($raw['Line'])) {
                    $fromLines = collect($raw['Line'])
                        ->pluck('LinkedTxn')
                        ->flatten(1)
                        ->filter()
                        ->values()
                        ->toArray();
                    $linked = array_merge($linked, $fromLines);
                }

                // 3) Apply / ApplyTo / AppliedToTxn / ApplyToTxn - common alternative names
                if (!empty($raw['Apply']) && is_array($raw['Apply'])) {
                    $linked = array_merge($linked, $raw['Apply']);
                }
                if (!empty($raw['AppliedToTxn']) && is_array($raw['AppliedToTxn'])) {
                    $linked = array_merge($linked, $raw['AppliedToTxn']);
                }

                // 4) Also check for shapes like ['TxnId'] / ['Id'] pairs directly on the raw (rare)
                if (isset($raw['TxnId']) && isset($raw['TxnType'])) {
                    $linked[] = ['TxnId' => $raw['TxnId'], 'TxnType' => $raw['TxnType']];
                }

                // Normalize each entry to have TxnId and TxnType keys (when possible)
                $normalized = [];
                foreach ($linked as $l) {
                    if (!is_array($l))
                        continue;

                    // possible keys in different shapes
                    $txnId = $l['TxnId'] ?? $l['Id'] ?? $l['AppliedToTxnId'] ?? $l['AppliedToTxnId'] ?? null;
                    $txnType = $l['TxnType'] ?? $l['TxnTypeName'] ?? $l['Type'] ?? $l['TxnType'] ?? null;

                    // some shapes use 'TxnId' numeric etc. cast to string for consistent comparison
                    if ($txnId !== null) {
                        $normalized[] = [
                            'TxnId' => (string) $txnId,
                            'TxnType' => $txnType ? (string) $txnType : null,
                        ];
                    }
                }

                // dedupe
                $unique = [];
                foreach ($normalized as $n) {
                    $key = ($n['TxnId'] ?? '') . '|' . ($n['TxnType'] ?? '');
                    if (!isset($unique[$key]))
                        $unique[$key] = $n;
                }

                return array_values($unique);
            };

            // --- Helper: detect payment account and vendor info ---
            $detectPaymentAccount = function ($raw) {
                if (!empty($raw['CreditCardPayment']['CCAccountRef']))
                    return $raw['CreditCardPayment']['CCAccountRef'];
                if (!empty($raw['CheckPayment']['BankAccountRef']))
                    return $raw['CheckPayment']['BankAccountRef'];
                if (!empty($raw['BankAccountRef']))
                    return $raw['BankAccountRef'];
                if (!empty($raw['PayFromAccountRef']))
                    return $raw['PayFromAccountRef'];
                if (!empty($raw['DepositToAccountRef']))
                    return $raw['DepositToAccountRef'];
                if (!empty($raw['CCAccountRef']))
                    return $raw['CCAccountRef'];
                if (!empty($raw['AccountRef']))
                    return $raw['AccountRef'];
                return null;
            };

            // --- 3) Normalize all payments ---
            $normalizedPayments = $allRawPayments->map(function ($raw) use ($extractLinkedTxn, $detectPaymentAccount) {
                // vendor detection
                $vendorId = $raw['VendorRef']['value'] ?? $raw['EntityRef']['value'] ?? $raw['PayeeRef']['value'] ?? $raw['CustomerRef']['value'] ?? null;
                $vendorName = $raw['VendorRef']['name'] ?? $raw['EntityRef']['name'] ?? $raw['PayeeRef']['name'] ?? $raw['CustomerRef']['name'] ?? null;

                $paymentAccount = $detectPaymentAccount($raw);

                $total = $raw['TotalAmt'] ?? $raw['Amount'] ?? $raw['TotalAmount'] ?? null;

                return [
                    'Raw' => $raw,
                    'PaymentId' => $raw['Id'] ?? ($raw['PaymentId'] ?? null),
                    'TxnTypeRaw' => $raw['TxnType'] ?? null,
                    'TxnDate' => $raw['TxnDate'] ?? null,
                    'DocNumber' => $raw['DocNumber'] ?? null,
                    'TotalAmount' => $total !== null ? (float) $total : null,
                    'PaymentAccount' => $paymentAccount ? [
                        'Id' => $paymentAccount['value'] ?? null,
                        'Name' => $paymentAccount['name'] ?? null,
                    ] : null,
                    'VendorId' => $vendorId ? (string) $vendorId : null,
                    'VendorName' => $vendorName ?? null,
                    'LinkedTxn' => $extractLinkedTxn($raw),
                ];
            })->values();

            // diagnostics pre-checks
            $diag = [
                'purchases_count' => count($rawPurchases),
                'raw_payments_count' => $allRawPayments->count(),
                'normalized_payments_count' => $normalizedPayments->count(),
                'normalized_payments_with_linkedtxn' => $normalizedPayments->filter(fn($p) => !empty($p['LinkedTxn']))->count(),
                'sample_normalized_payment' => $normalizedPayments->first() ? [
                    'PaymentId' => $normalizedPayments->first()['PaymentId'] ?? null,
                    'VendorId' => $normalizedPayments->first()['VendorId'] ?? null,
                    'TotalAmount' => $normalizedPayments->first()['TotalAmount'] ?? null,
                    'LinkedTxn' => $normalizedPayments->first()['LinkedTxn'] ?? [],
                ] : null,
            ];

            // --- 4) Normalize purchases (expenses) ---
            $expenses = collect($rawPurchases)->map(function ($purchase) {
                $expenseAccounts = collect($purchase['Line'] ?? [])->map(function ($line) {
                    if (!empty($line['AccountBasedExpenseLineDetail']['AccountRef'])) {
                        $acct = $line['AccountBasedExpenseLineDetail']['AccountRef'];
                    } elseif (!empty($line['AccountRef'])) {
                        $acct = $line['AccountRef'];
                    } else {
                        return null;
                    }
                    return [
                        'Id' => $acct['value'] ?? null,
                        'Name' => $acct['name'] ?? null,
                        'Amount' => $line['Amount'] ?? 0,
                        'Description' => $line['Description'] ?? null,
                    ];
                })->filter()->values()->toArray();

                $mainAccount = null;
                if (!empty($purchase['AccountRef'])) {
                    $mainAccount = [
                        'Id' => $purchase['AccountRef']['value'] ?? null,
                        'Name' => $purchase['AccountRef']['name'] ?? null,
                    ];
                }

                return [
                    'ExpenseId' => $purchase['Id'] ?? null,
                    'VendorName' => $purchase['VendorRef']['name'] ?? ($purchase['EntityRef']['name'] ?? null),
                    'VendorId' => $purchase['VendorRef']['value'] ?? ($purchase['EntityRef']['value'] ?? null),
                    'TxnDate' => $purchase['TxnDate'] ?? null,
                    'TotalAmount' => (float) ($purchase['TotalAmt'] ?? ($purchase['Amount'] ?? 0)),
                    'Currency' => $purchase['CurrencyRef']['name'] ?? null,
                    'Memo' => $purchase['Memo'] ?? null,
                    'MainAccount' => $mainAccount,
                    'ExpenseAccounts' => $expenseAccounts,
                    'Payments' => [],
                ];
            });

            // --- 5) Link payments to expenses (explicit LinkedTxn) + fuzzy fallback ---
            $expensesWithPayments = $expenses->map(function ($exp) use ($normalizedPayments, $useFuzzyFallback, $fuzzyDateWindowDays, $fuzzyAmountTolerance) {
                // exact matches by LinkedTxn
                $linkedExact = $normalizedPayments->filter(function ($p) use ($exp) {
                    if (empty($p['LinkedTxn']))
                        return false;
                    return collect($p['LinkedTxn'])->contains(function ($txn) use ($exp) {
                        if (empty($txn['TxnId']))
                            return false;
                        // match by TxnId (type may vary or be null) — string compare
                        return (string) $txn['TxnId'] === (string) $exp['ExpenseId'];
                    });
                })->values();

                // If no explicit linked payments and fuzzy fallback enabled, perform vendor+amount+date heuristic
                if ($linkedExact->isEmpty() && $useFuzzyFallback) {
                    $expDate = $exp['TxnDate'] ? strtotime($exp['TxnDate']) : null;
                    $linkedFuzzy = $normalizedPayments->filter(function ($p) use ($exp, $expDate, $fuzzyDateWindowDays, $fuzzyAmountTolerance) {
                        // vendor must match if present
                        if (!empty($exp['VendorId']) && !empty($p['VendorId']) && (string) $exp['VendorId'] !== (string) $p['VendorId']) {
                            return false;
                        }
                        // amount must be similar within tolerance
                        if ($p['TotalAmount'] === null)
                            return false;
                        if (abs($p['TotalAmount'] - $exp['TotalAmount']) > $fuzzyAmountTolerance) {
                            return false;
                        }
                        // if both have dates, require within date window
                        if ($expDate && !empty($p['TxnDate'])) {
                            $pDate = strtotime($p['TxnDate']);
                            $deltaDays = abs(($pDate - $expDate) / 86400);
                            if ($deltaDays > $fuzzyDateWindowDays)
                                return false;
                        }
                        return true;
                    })->values();

                    // prefer exact (none here), otherwise use fuzzy set
                    $finalLinked = $linkedFuzzy;
                } else {
                    $finalLinked = $linkedExact;
                }

                $exp['Payments'] = $finalLinked;
                return $exp;
            });

            // --- 6) Add diagnostics about matches ---
            $diag['expenses_count'] = $expenses->count();
            $diag['expenses_with_any_payment'] = $expensesWithPayments->filter(fn($e) => !empty($e['Payments']))->count();
            $diag['example_expense_with_payment'] = $expensesWithPayments->first(function ($e) {
                return !empty($e['Payments']);
            });

            // --- 7) Return response (includes diagnostics) ---
            return response()->json([
                'status' => 'success',
                'count' => $expensesWithPayments->count(),
                'data' => $expensesWithPayments->values(),
                'single' => collect($expensesWithPayments->values())->first(),
                'diagnostics' => $diag,
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
            // -----------------------
            // 1) Fetch core data
            // -----------------------
            $invoicesRaw = $this->runQuery("SELECT * FROM Invoice");
            $paymentsRaw = $this->runQuery("SELECT * FROM Payment");
            $itemsRaw = $this->runQuery("SELECT * FROM Item");
            $accountsRaw = $this->runQuery("SELECT * FROM Account");

            $invoicesList = collect($invoicesRaw['QueryResponse']['Invoice'] ?? []);
            $paymentsList = collect($paymentsRaw['QueryResponse']['Payment'] ?? []);
            $itemsList = collect($itemsRaw['QueryResponse']['Item'] ?? []);
            $accountsList = collect($accountsRaw['QueryResponse']['Account'] ?? []);

            $itemsMap = $itemsList->keyBy(fn($it) => $it['Id'] ?? null)->toArray();
            $accountsMap = $accountsList->keyBy(fn($a) => $a['Id'] ?? null)->toArray();

            // -----------------------
            // helpers
            // -----------------------
            $findARAccount = function () use ($accountsList) {
                $ar = $accountsList->first(fn($a) => isset($a['AccountType']) && strcasecmp($a['AccountType'], 'AccountsReceivable') === 0);
                if ($ar)
                    return ['Id' => $ar['Id'], 'Name' => $ar['Name'] ?? null];
                $ar = $accountsList->first(fn($a) => stripos($a['Name'] ?? '', 'receivable') !== false);
                return $ar ? ['Id' => $ar['Id'], 'Name' => $ar['Name'] ?? null] : null;
            };
            $findTaxPayableAccount = function () use ($accountsList) {
                $found = $accountsList->first(function ($a) {
                    if (isset($a['AccountType']) && strcasecmp($a['AccountType'], 'OtherCurrentLiability') === 0) {
                        return (stripos($a['Name'] ?? '', 'tax') !== false) || (stripos($a['Name'] ?? '', 'payable') !== false);
                    }
                    return false;
                });
                if ($found)
                    return ['Id' => $found['Id'], 'Name' => $found['Name'] ?? null];
                $found = $accountsList->first(fn($a) => stripos($a['Name'] ?? '', 'tax') !== false);
                return $found ? ['Id' => $found['Id'], 'Name' => $found['Name'] ?? null] : null;
            };

            $arAccount = $findARAccount();
            $taxAccount = $findTaxPayableAccount();

            // A small helper to detect the account for a sales-item line
            $detectAccountForSalesItem = function ($sid) use ($itemsMap, $accountsMap) {
                // sid = SalesItemLineDetail
                if (!empty($sid['ItemAccountRef']['value'])) {
                    return [
                        'AccountId' => $sid['ItemAccountRef']['value'],
                        'AccountName' => $sid['ItemAccountRef']['name'] ?? ($accountsMap[$sid['ItemAccountRef']['value']]['Name'] ?? null)
                    ];
                }
                if (!empty($sid['ItemRef']['value'])) {
                    $itemId = $sid['ItemRef']['value'];
                    $item = $itemsMap[$itemId] ?? null;
                    if ($item) {
                        if (!empty($item['IncomeAccountRef']['value'])) {
                            return ['AccountId' => $item['IncomeAccountRef']['value'], 'AccountName' => $item['IncomeAccountRef']['name'] ?? ($accountsMap[$item['IncomeAccountRef']['value']]['Name'] ?? null)];
                        }
                        if (!empty($item['ExpenseAccountRef']['value'])) {
                            return ['AccountId' => $item['ExpenseAccountRef']['value'], 'AccountName' => $item['ExpenseAccountRef']['name'] ?? ($accountsMap[$item['ExpenseAccountRef']['value']]['Name'] ?? null)];
                        }
                        if (!empty($item['AssetAccountRef']['value'])) {
                            return ['AccountId' => $item['AssetAccountRef']['value'], 'AccountName' => $item['AssetAccountRef']['name'] ?? ($accountsMap[$item['AssetAccountRef']['value']]['Name'] ?? null)];
                        }
                    }
                }
                return ['AccountId' => null, 'AccountName' => null];
            };

            // Parse one invoice line (handles GroupLine by expanding children).
            $parseInvoiceLine = function ($line) use ($detectAccountForSalesItem, $itemsMap, $accountsMap) {
                $out = [];
                $detailType = $line['DetailType'] ?? null;

                // Expand GroupLine children (if present). This prevents having a summary line and also child lines counted twice.
                if (!empty($line['GroupLineDetail']) && !empty($line['GroupLineDetail']['Line'])) {
                    foreach ($line['GroupLineDetail']['Line'] as $child) {
                        // recursively parse each child (but avoid infinite recursion by not re-expanding groups in child)
                        if (!empty($child['SalesItemLineDetail'])) {
                            $sid = $child['SalesItemLineDetail'];
                            $acc = $detectAccountForSalesItem($sid);
                            $out[] = [
                                'DetailType' => $child['DetailType'] ?? 'SalesItemLineDetail',
                                'Description' => $child['Description'] ?? $sid['ItemRef']['name'] ?? null,
                                'Amount' => $child['Amount'] ?? 0,
                                'AccountId' => $acc['AccountId'],
                                'AccountName' => $acc['AccountName'],
                                'RawLine' => $child,
                            ];
                        } else {
                            // for non-sales child lines, attempt to capture amount but leave account null (we'll surface these as unmapped)
                            $out[] = [
                                'DetailType' => $child['DetailType'] ?? null,
                                'Description' => $child['Description'] ?? null,
                                'Amount' => $child['Amount'] ?? 0,
                                'AccountId' => null,
                                'AccountName' => null,
                                'RawLine' => $child,
                            ];
                        }
                    }
                    return $out;
                }

                // Normal single line
                if (!empty($line['SalesItemLineDetail'])) {
                    $sid = $line['SalesItemLineDetail'];
                    $acc = $detectAccountForSalesItem($sid);
                    $out[] = [
                        'DetailType' => $line['DetailType'] ?? 'SalesItemLineDetail',
                        'Description' => $line['Description'] ?? ($sid['ItemRef']['name'] ?? null),
                        'Amount' => $line['Amount'] ?? 0,
                        'AccountId' => $acc['AccountId'],
                        'AccountName' => $acc['AccountName'],
                        'RawLine' => $line,
                    ];
                    return $out;
                }

                // TaxLine -> handled separately by TaxTotal; still return it so we can notice unmapped tax lines if present
                if (!empty($line['TaxLineDetail']) || stripos($detailType ?? '', 'Tax') !== false) {
                    $out[] = [
                        'DetailType' => $detailType,
                        'Description' => $line['Description'] ?? null,
                        'Amount' => $line['Amount'] ?? 0,
                        'AccountId' => null,
                        'AccountName' => null,
                        'RawLine' => $line,
                    ];
                    return $out;
                }

                // Subtotal/Description/Other lines -> return as unmapped to avoid double counting
                $out[] = [
                    'DetailType' => $detailType,
                    'Description' => $line['Description'] ?? null,
                    'Amount' => $line['Amount'] ?? 0,
                    'AccountId' => null,
                    'AccountName' => null,
                    'RawLine' => $line,
                ];
                return $out;
            };

            // -----------------------
            // 2) Build invoice objects + invoice-line-level parsed lines
            // -----------------------
            $invoices = $invoicesList->map(function ($invoice) use ($parseInvoiceLine, $accountsMap, $arAccount, $taxAccount) {
                $parsedLines = [];
                foreach ($invoice['Line'] ?? [] as $line) {
                    $parsedLines = array_merge($parsedLines, $parseInvoiceLine($line));
                }

                // collect unmapped (AccountId === null) separately
                $unmapped = array_values(array_filter($parsedLines, fn($l) => empty($l['AccountId']) && (float) $l['Amount'] != 0.0));

                // Invoice tax detection (TxnTaxDetail or TotalTax)
                $taxTotal = 0;
                if (!empty($invoice['TxnTaxDetail']['TotalTax']))
                    $taxTotal = $invoice['TxnTaxDetail']['TotalTax'];
                elseif (!empty($invoice['TotalTax']))
                    $taxTotal = $invoice['TotalTax'];

                $totalAmount = (float) ($invoice['TotalAmt'] ?? 0);

                // Build reconstructed journal from invoice lines BUT only include lines with detected accountId (avoid double-counting)
                $journalLines = [];

                // Debit AR (invoice total)
                if ($arAccount) {
                    $journalLines[] = [
                        'AccountId' => $arAccount['Id'],
                        'AccountName' => $arAccount['Name'],
                        'Debit' => $totalAmount,
                        'Credit' => 0.0,
                        'Note' => 'Accounts Receivable (invoice total)'
                    ];
                } else {
                    $journalLines[] = [
                        'AccountId' => null,
                        'AccountName' => 'Accounts Receivable (not found)',
                        'Debit' => $totalAmount,
                        'Credit' => 0.0,
                        'Note' => 'Accounts Receivable (invoice total, account not auto-detected)'
                    ];
                }

                // Credit per parsed line only if AccountId is present (this prevents adding subtotal/group duplicates)
                foreach ($parsedLines as $pl) {
                    if ((float) $pl['Amount'] == 0.0)
                        continue;
                    if (empty($pl['AccountId']))
                        continue; // skip unmapped lines here
                    $journalLines[] = [
                        'AccountId' => $pl['AccountId'],
                        'AccountName' => $pl['AccountName'] ?? null,
                        'Debit' => 0.0,
                        'Credit' => (float) $pl['Amount'],
                        'Note' => $pl['Description'] ?? 'Sales / line item'
                    ];
                }

                // Tax payable (heuristic) — keep as separate credit so total credits + tax = AR debit
                if ($taxTotal > 0) {
                    $journalLines[] = [
                        'AccountId' => $taxAccount['Id'] ?? null,
                        'AccountName' => $taxAccount['Name'] ?? 'Sales Tax Payable (heuristic)',
                        'Debit' => 0.0,
                        'Credit' => (float) $taxTotal,
                        'Note' => 'Sales/Tax payable'
                    ];
                }

                $sumDebits = array_sum(array_map(fn($l) => $l['Debit'] ?? 0, $journalLines));
                $sumCredits = array_sum(array_map(fn($l) => $l['Credit'] ?? 0, $journalLines));
                $balanced = abs($sumDebits - $sumCredits) < 0.01;

                return [
                    'InvoiceId' => (string) ($invoice['Id'] ?? null),
                    'Id' => $invoice['Id'] ?? null,
                    'DocNumber' => $invoice['DocNumber'] ?? null,
                    'CustomerName' => $invoice['CustomerRef']['name'] ?? null,
                    'CustomerId' => $invoice['CustomerRef']['value'] ?? null,
                    'TxnDate' => $invoice['TxnDate'] ?? null,
                    'DueDate' => $invoice['DueDate'] ?? null,
                    'TotalAmount' => $totalAmount,
                    'Balance' => $invoice['Balance'] ?? 0,
                    'Currency' => $invoice['CurrencyRef']['name'] ?? null,
                    'Payments' => [],
                    'ParsedLines' => $parsedLines,
                    'UnmappedInvoiceLines' => $unmapped,
                    'TaxTotal' => (float) $taxTotal,
                    'ReconstructedJournal' => [
                        'Source' => 'InvoiceLines',
                        'Lines' => $journalLines,
                        'SumDebits' => (float) $sumDebits,
                        'SumCredits' => (float) $sumCredits,
                        'Balanced' => $balanced,
                    ],
                    'RawInvoice' => $invoice,
                ];
            });

            // -----------------------
            // 3) Normalize payments and attach them to invoices
            // -----------------------
            $payments = $paymentsList->map(function ($payment) {
                $linked = [];
                foreach ($payment['Line'] ?? [] as $l) {
                    if (!empty($l['LinkedTxn'])) {
                        if (isset($l['LinkedTxn'][0]))
                            $linked = array_merge($linked, $l['LinkedTxn']);
                        else
                            $linked[] = $l['LinkedTxn'];
                    }
                }
                return [
                    'PaymentId' => $payment['Id'] ?? null,
                    'CustomerId' => $payment['CustomerRef']['value'] ?? null,
                    'CustomerName' => $payment['CustomerRef']['name'] ?? null,
                    'TxnDate' => $payment['TxnDate'] ?? null,
                    'TotalAmount' => $payment['TotalAmt'] ?? 0,
                    'PaymentMethod' => $payment['PaymentMethodRef']['name'] ?? null,
                    'LinkedTxn' => $linked,
                    'RawPayment' => $payment,
                ];
            });

            $invoicesById = $invoices->keyBy('InvoiceId')->toArray();
            foreach ($invoicesById as $invId => &$inv) {
                $inv['Payments'] = collect($payments)->filter(function ($p) use ($invId) {
                    return collect($p['LinkedTxn'])->contains(fn($txn) => isset($txn['TxnType'], $txn['TxnId']) && strcasecmp($txn['TxnType'], 'Invoice') === 0 && (string) $txn['TxnId'] === (string) $invId);
                })->values()->toArray();
            }
            $invoicesWithPayments = collect($invoicesById);

            // -----------------------
            // 4) Fetch JournalEntries (paginated) for invoice date range
            // -----------------------
            $txnDates = $invoicesList->pluck('TxnDate')->filter()->values();
            if ($txnDates->isEmpty()) {
                $minDate = date('Y-m-d', strtotime('-90 days'));
                $maxDate = date('Y-m-d');
            } else {
                $minDate = $txnDates->min();
                $maxDate = $txnDates->max();
            }

            $startPosition = 1;
            $maxResults = 1000;
            $allJournalEntries = [];
            while (true) {
                $q = "SELECT * FROM JournalEntry WHERE TxnDate >= '{$minDate}' AND TxnDate <= '{$maxDate}' STARTPOSITION {$startPosition} MAXRESULTS {$maxResults}";
                $jesRaw = $this->runQuery($q);
                $jesPage = $jesRaw['QueryResponse']['JournalEntry'] ?? [];
                if (empty($jesPage))
                    break;
                foreach ($jesPage as $je)
                    $allJournalEntries[] = $je;
                if (count($jesPage) < $maxResults)
                    break;
                $startPosition += $maxResults;
            }

            // Index JEs by any LinkedTxn.TxnId found (may be DocNumber or Id)
            $jeByLinkedTxn = [];
            foreach ($allJournalEntries as $je) {
                foreach ($je['Line'] ?? [] as $line) {
                    $linked = $line['LinkedTxn'] ?? $line['JournalEntryLineDetail']['LinkedTxn'] ?? null;
                    if (empty($linked))
                        continue;
                    if (isset($linked['TxnId']) && isset($linked['TxnType']))
                        $linked = [$linked];
                    foreach ($linked as $lt) {
                        if (isset($lt['TxnType'], $lt['TxnId']) && strcasecmp($lt['TxnType'], 'Invoice') === 0) {
                            $key = (string) $lt['TxnId'];
                            if (!isset($jeByLinkedTxn[$key]))
                                $jeByLinkedTxn[$key] = [];
                            $jeByLinkedTxn[$key][] = $je;
                        }
                    }
                }
            }

            // -----------------------
            // 5) For invoices with no explicit LinkedTxn, attempt heuristic: match JE with AR line = invoice total ± tolerance, same customer (if present), date ±1 day
            // -----------------------
            $tolerance = 0.01;
            foreach ($invoicesWithPayments as $invKey => &$invoice) {
                $invoiceId = (string) $invoice['InvoiceId'];
                $docNum = (string) ($invoice['DocNumber'] ?? '');
                $total = (float) $invoice['TotalAmount'];
                $invDate = $invoice['TxnDate'] ?? null;
                $custId = (string) ($invoice['CustomerId'] ?? '');

                // Try explicit linked txn (by Id or DocNumber)
                $matchedJEs = $jeByLinkedTxn[$invoiceId] ?? [];
                if (empty($matchedJEs) && $docNum !== '')
                    $matchedJEs = $jeByLinkedTxn[$docNum] ?? [];

                // Heuristic scan if none found
                if (empty($matchedJEs)) {
                    foreach ($allJournalEntries as $je) {
                        // date within +/-1 day
                        if ($invDate && !empty($je['TxnDate'])) {
                            if (abs(strtotime($je['TxnDate']) - strtotime($invDate)) > 86400)
                                continue;
                        }
                        // customer match if JE has CustomerRef
                        if (!empty($je['CustomerRef']['value']) && $custId !== '') {
                            if ((string) $je['CustomerRef']['value'] !== $custId)
                                continue;
                        }

                        // find AR line in JE with amount ~= total
                        $hasMatchingAR = false;
                        foreach ($je['Line'] ?? [] as $jl) {
                            $acctId = $jl['AccountRef']['value'] ?? ($jl['JournalEntryLineDetail']['AccountRef']['value'] ?? null);
                            $amount = isset($jl['Amount']) ? (float) $jl['Amount'] : 0.0;
                            $postingType = $jl['JournalEntryLineDetail']['PostingType'] ?? null;

                            if ($arAccount && $acctId && (string) $acctId === (string) $arAccount['Id']) {
                                if ($postingType && strcasecmp($postingType, 'Debit') === 0 && abs($amount - $total) <= $tolerance) {
                                    $hasMatchingAR = true;
                                    break;
                                }
                                if ($postingType === null && abs($amount - $total) <= $tolerance) {
                                    $hasMatchingAR = true;
                                    break;
                                }
                            } else {
                                // check accountsMap for AR-typed acct
                                if ($acctId && isset($accountsMap[$acctId]) && isset($accountsMap[$acctId]['AccountType']) && strcasecmp($accountsMap[$acctId]['AccountType'], 'AccountsReceivable') === 0) {
                                    if ($postingType && strcasecmp($postingType, 'Debit') === 0 && abs($amount - $total) <= $tolerance) {
                                        $hasMatchingAR = true;
                                        break;
                                    }
                                    if ($postingType === null && abs($amount - $total) <= $tolerance) {
                                        $hasMatchingAR = true;
                                        break;
                                    }
                                }
                            }
                        }

                        if ($hasMatchingAR) {
                            $matchedJEs[] = $je;
                            break; // use first match (can be changed to collect multiple)
                        }
                    }
                }

                // If matched JEs found, parse the JE lines into authoritative ReconstructedJournal
                $invoice['LinkedJournalEntries'] = [];
                $invoice['HasLinkedJournalEntry'] = !empty($matchedJEs);

                if (!empty($matchedJEs)) {
                    // compact summaries
                    $invoice['LinkedJournalEntries'] = array_map(fn($je) => [
                        'JournalEntryId' => $je['Id'] ?? null,
                        'TxnDate' => $je['TxnDate'] ?? null,
                        'TotalAmt' => $je['TotalAmt'] ?? null,
                        'RawJournalEntry' => $je,
                    ], $matchedJEs);

                    // parse lines
                    $mergedJournalLines = [];
                    foreach ($matchedJEs as $je) {
                        foreach ($je['Line'] ?? [] as $jl) {
                            $amount = isset($jl['Amount']) ? (float) $jl['Amount'] : 0.0;
                            $acctId = $jl['AccountRef']['value'] ?? ($jl['JournalEntryLineDetail']['AccountRef']['value'] ?? null);
                            $acctName = $jl['AccountRef']['name'] ?? ($jl['JournalEntryLineDetail']['AccountRef']['name'] ?? ($accountsMap[$acctId]['Name'] ?? null));
                            $postingType = $jl['JournalEntryLineDetail']['PostingType'] ?? null;

                            $debit = 0.0;
                            $credit = 0.0;
                            if ($postingType) {
                                if (strcasecmp($postingType, 'Debit') === 0)
                                    $debit = $amount;
                                elseif (strcasecmp($postingType, 'Credit') === 0)
                                    $credit = $amount;
                            } else {
                                $acctInfo = $accountsMap[$acctId] ?? null;
                                if ($acctInfo && isset($acctInfo['AccountType']) && strcasecmp($acctInfo['AccountType'], 'AccountsReceivable') === 0)
                                    $debit = $amount;
                                else
                                    $credit = $amount;
                            }

                            $mergedJournalLines[] = [
                                'AccountId' => $acctId,
                                'AccountName' => $acctName,
                                'Debit' => $debit,
                                'Credit' => $credit,
                                'Note' => $jl['Description'] ?? ($jl['JournalEntryLineDetail']['Memo'] ?? null),
                            ];
                        }
                    }

                    $sumDebits = array_sum(array_map(fn($l) => $l['Debit'] ?? 0, $mergedJournalLines));
                    $sumCredits = array_sum(array_map(fn($l) => $l['Credit'] ?? 0, $mergedJournalLines));
                    $balanced = abs($sumDebits - $sumCredits) < 0.01;

                    // Replace invoice reconstructed journal with authoritative JE lines
                    $invoice['ReconstructedJournal'] = [
                        'Source' => 'JournalEntry',
                        'Lines' => $mergedJournalLines,
                        'SumDebits' => (float) $sumDebits,
                        'SumCredits' => (float) $sumCredits,
                        'Balanced' => $balanced,
                    ];
                }
                // else: keep InvoiceLines-based ReconstructedJournal (and UnmappedInvoiceLines will show lines we could not map)
            }

            // -----------------------
            // 6) Return
            // -----------------------
            // return response()->json([
            //     'status' => 'success',
            //     'count' => $invoicesWithPayments->count(),
            //     'data' => array_values($invoicesWithPayments),
            // ]);
            return $invoicesWithPayments;
            // dd($invoicesWithPayments->last(),$invoicesWithPayments->first(), $invoicesWithPayments);
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
                    'DepositId' => $deposit['Id'] ?? null,
                    'TxnDate' => $deposit['TxnDate'] ?? null,
                    'TotalAmt' => $deposit['TotalAmt'] ?? 0,
                    'PrivateNote' => $deposit['PrivateNote'] ?? null,
                    'Currency' => $deposit['CurrencyRef']['name'] ?? null,
                    'DepositTo' => $deposit['DepositToAccountRef']['name'] ?? null,
                    'LineCount' => isset($deposit['Line']) ? count($deposit['Line']) : 0,
                    'Lines' => collect($deposit['Line'] ?? [])->map(function ($line) {
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
                'count' => $deposits->count(),
                'data' => $deposits->values(),
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
                    return ($je['TxnDate'] ?? null) === $txnDate && (float) ($je['TotalAmt'] ?? 0) === (float) $total;
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
            $realmId = $this->realmId(); // Company ID (Realm ID)
            $baseUrl = $this->baseUrl;   // Base URL for environment (sandbox or production)

            // Construct the API URL for SalesReceipts
            $url = "{$baseUrl}/v3/company/{$realmId}/query?minorversion=75";
            $query = "SELECT * FROM SalesReceipt"; // Get all sales receipts

            $data = $this->runQuery($query);

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



    /**
     * 💰 Sales Tax Payments - Payments made to tax authorities
     */
    public function salesTaxPayments()
    {
        try {
            // Fetch "Purchase" entries where PaymentType is Check
            $query = "SELECT * FROM Purchase WHERE PaymentType = 'Check' STARTPOSITION 1 MAXRESULTS 200";
            $response = $this->runQuery($query);

            if (isset($response['Fault'])) {
                \Log::error('QuickBooks Tax Payment fetch error', [
                    'fault' => $response['Fault'],
                    'query' => $query,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => $response['Fault']['Error'][0]['Detail'] ?? 'Query failed',
                ], 400);
            }

            $payments = collect($response['QueryResponse']['Purchase'] ?? [])->map(function ($purchase) {
                return [
                    'Id' => $purchase['Id'] ?? null,
                    'DocNumber' => $purchase['DocNumber'] ?? null,
                    'TxnDate' => $purchase['TxnDate'] ?? null,
                    'TotalAmount' => $purchase['TotalAmt'] ?? 0,
                    'PaymentType' => $purchase['PaymentType'] ?? null,
                    'Payee' => $purchase['EntityRef']['name'] ?? null,
                    'PayeeId' => $purchase['EntityRef']['value'] ?? null,
                    'Account' => [
                        'Id' => $purchase['AccountRef']['value'] ?? null,
                        'Name' => $purchase['AccountRef']['name'] ?? null,
                    ],
                    'PrivateNote' => $purchase['PrivateNote'] ?? null,
                    'Currency' => $purchase['CurrencyRef']['name'] ?? null,
                    'Lines' => collect($purchase['Line'] ?? [])->map(function ($line) {
                        return [
                            'Amount' => $line['Amount'] ?? 0,
                            'Description' => $line['Description'] ?? null,
                            'DetailType' => $line['DetailType'] ?? null,
                            'AccountRef' => $line['AccountBasedExpenseLineDetail']['AccountRef']['name'] ?? null,
                        ];
                    }),
                    'RawData' => $purchase,
                ];
            });

            return response()->json([
                'status' => 'success',
                'count' => $payments->count(),
                'data' => $payments->values(),
            ]);

        } catch (\Exception $e) {
            \Log::error('QuickBooks salesTaxPayments exception', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }



    /**
     * 🔄 Refunds - Customer refunds/returns with payment reversals
     */
    public function refunds()
    {
        try {
            // 1️⃣ Fetch all refunds
            $refundResponse = $this->runQuery("SELECT * FROM RefundReceipt STARTPOSITION 1 MAXRESULTS 200");
            dd($refundResponse);
            if ($refundResponse instanceof \Illuminate\Http\JsonResponse) {
                return $refundResponse;
            }

            $refunds = collect($refundResponse['QueryResponse']['Refund'] ?? [])
                ->map(function ($refund) {
                    // Extract refund lines with accounts
                    $refundLines = collect($refund['Line'] ?? [])->map(function ($line) {
                        $accountRef = null;

                        if (!empty($line['SalesItemLineDetail']['ItemAccountRef'])) {
                            $accountRef = $line['SalesItemLineDetail']['ItemAccountRef'];
                        } elseif (!empty($line['SalesItemLineDetail']['ItemRef'])) {
                            // May need to look up item to get account
                            $accountRef = $line['SalesItemLineDetail']['ItemRef'];
                        }

                        return [
                            'DetailType' => $line['DetailType'] ?? null,
                            'Description' => $line['Description'] ?? null,
                            'Amount' => $line['Amount'] ?? 0,
                            'Account' => $accountRef ? [
                                'Id' => $accountRef['value'] ?? null,
                                'Name' => $accountRef['name'] ?? null,
                            ] : null,
                            'ItemRef' => $line['SalesItemLineDetail']['ItemRef']['name'] ?? null,
                            'Quantity' => $line['SalesItemLineDetail']['Qty'] ?? null,
                            'UnitPrice' => $line['SalesItemLineDetail']['UnitPrice'] ?? null,
                        ];
                    });

                    // Tax info
                    $taxTotal = 0;
                    if (!empty($refund['TxnTaxDetail']['TotalTax'])) {
                        $taxTotal = $refund['TxnTaxDetail']['TotalTax'];
                    } elseif (!empty($refund['TotalTax'])) {
                        $taxTotal = $refund['TotalTax'];
                    }

                    return [
                        'RefundId' => $refund['Id'] ?? null,
                        'DocNumber' => $refund['DocNumber'] ?? null,
                        'CustomerName' => $refund['CustomerRef']['name'] ?? null,
                        'CustomerId' => $refund['CustomerRef']['value'] ?? null,
                        'TxnDate' => $refund['TxnDate'] ?? null,
                        'DueDate' => $refund['DueDate'] ?? null,
                        'TotalAmount' => (float) ($refund['TotalAmt'] ?? 0),
                        'TaxTotal' => (float) $taxTotal,
                        'Currency' => $refund['CurrencyRef']['name'] ?? null,
                        'PaymentMethod' => $refund['PaymentMethodRef']['name'] ?? null,
                        'DepositToAccount' => [
                            'Id' => $refund['DepositToAccountRef']['value'] ?? null,
                            'Name' => $refund['DepositToAccountRef']['name'] ?? null,
                        ],
                        'PrivateNote' => $refund['PrivateNote'] ?? null,
                        'RefundLines' => $refundLines,
                        'RawRefund' => $refund,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'count' => $refunds->count(),
                'data' => $refunds->values(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 📝 Credit Memos - Customer credits for returns, discounts, or adjustments
     */
    public function creditMemos()
    {
        try {
            // 1️⃣ Fetch all credit memos
            $creditMemoResponse = $this->runQuery("SELECT * FROM CreditMemo STARTPOSITION 1 MAXRESULTS 200");

            if ($creditMemoResponse instanceof \Illuminate\Http\JsonResponse) {
                return $creditMemoResponse;
            }

            // 2️⃣ Fetch accounts to find A/R if missing
            $accountResponse = $this->runQuery("SELECT * FROM Account WHERE AccountType = 'Accounts Receivable'");

            $defaultARAccount = null;
            if (!($accountResponse instanceof \Illuminate\Http\JsonResponse) && isset($accountResponse['QueryResponse']['Account'])) {
                $defaultARAccount = collect($accountResponse['QueryResponse']['Account'])->first();
            }

            $creditMemos = collect($creditMemoResponse['QueryResponse']['CreditMemo'] ?? [])->map(function ($memo) use ($defaultARAccount) {
                // 🧾 Parse line-level accounts (Debit side)
                $memoLines = collect($memo['Line'] ?? [])->map(function ($line) {
                    $accountRef = null;

                    if (!empty($line['SalesItemLineDetail']['ItemAccountRef'])) {
                        $accountRef = $line['SalesItemLineDetail']['ItemAccountRef'];
                    } elseif (!empty($line['SalesItemLineDetail']['ItemRef'])) {
                        $accountRef = $line['SalesItemLineDetail']['ItemRef'];
                    } elseif (!empty($line['AccountBasedExpenseLineDetail']['AccountRef'])) {
                        $accountRef = $line['AccountBasedExpenseLineDetail']['AccountRef'];
                    }

                    return [
                        'DetailType' => $line['DetailType'] ?? null,
                        'Description' => $line['Description'] ?? null,
                        'Amount' => $line['Amount'] ?? 0,
                        'PostingType' => 'Debit', // ✅ Debit side
                        'Account' => $accountRef ? [
                            'Id' => $accountRef['value'] ?? null,
                            'Name' => $accountRef['name'] ?? null,
                        ] : null,
                        'ItemRef' => $line['SalesItemLineDetail']['ItemRef']['name'] ?? null,
                        'Quantity' => $line['SalesItemLineDetail']['Qty'] ?? null,
                        'UnitPrice' => $line['SalesItemLineDetail']['UnitPrice'] ?? null,
                    ];
                });

                // 💰 Ensure A/R account (Credit side)
                $arAccountRef = $memo['ARAccountRef'] ?? null;
                $arAccount = [
                    'PostingType' => 'Credit',
                    'Account' => [
                        'Id' => $arAccountRef['value']
                            ?? $defaultARAccount['Id']
                            ?? null,
                        'Name' => $arAccountRef['name']
                            ?? $defaultARAccount['Name']
                            ?? 'Accounts Receivable',
                    ],
                    'Amount' => $memo['TotalAmt'] ?? 0,
                ];

                // ➕ Combine both sides
                $journalEntries = $memoLines->push($arAccount)->values();

                // 📑 Tax details
                $taxTotal = 0;
                if (!empty($memo['TxnTaxDetail']['TotalTax'])) {
                    $taxTotal = $memo['TxnTaxDetail']['TotalTax'];
                } elseif (!empty($memo['TotalTax'])) {
                    $taxTotal = $memo['TotalTax'];
                }

                // 🔗 Linked Transactions
                $linkedTxns = [];
                foreach ($memo['Line'] ?? [] as $line) {
                    if (!empty($line['LinkedTxn'])) {
                        $linkedTxns = array_merge($linkedTxns, (array) $line['LinkedTxn']);
                    }
                }

                // 📦 Final structured object
                return [
                    'CreditMemoId' => $memo['Id'] ?? null,
                    'DocNumber' => $memo['DocNumber'] ?? null,
                    'CustomerName' => $memo['CustomerRef']['name'] ?? null,
                    'CustomerId' => $memo['CustomerRef']['value'] ?? null,
                    'TxnDate' => $memo['TxnDate'] ?? null,
                    'TotalAmount' => (float) ($memo['TotalAmt'] ?? 0),
                    'TaxTotal' => (float) $taxTotal,
                    'Balance' => (float) ($memo['Balance'] ?? 0),
                    'Currency' => $memo['CurrencyRef']['name'] ?? null,
                    'PrivateNote' => $memo['PrivateNote'] ?? null,
                    'Reason' => $memo['Reason'] ?? null,
                    'JournalEntries' => $journalEntries, // ✅ Debit + Credit sides
                    'LinkedTransactions' => $linkedTxns,
                ];
            });

            return response()->json([
                'status' => 'success',
                'count' => $creditMemos->count(),
                'data' => $creditMemos->values(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }



    /**
     * 💳 Credit Card Credits - Vendor credits via credit card (accounts payable reduction)
     */
    private function runEntity($entity)
    {
        $accessToken = $this->accessToken();
            $realmId = $this->realmId();

        $url = "https://quickbooks.api.intuit.com/v3/company/{$realmId}/{$entity}";
        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->get($url);

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'QuickBooks API request failed',
                'details' => $response->json(),
            ], $response->status());
        }

        return $response->json();
    }

   public function creditCardCredits(Request $request)
{
    try {
        $startDate = $request->get('start_date', '2010-01-01');
        $endDate   = $request->get('end_date', date('Y-m-d'));

        // 1️⃣ Fetch report from QuickBooks (TransactionList for CreditCardCredit)
        $token = $this->accessToken();
        $realm = $this->realmId();
        $url = "https://quickbooks.api.intuit.com/v3/company/{$realm}/reports/TransactionList" .
               "?start_date={$startDate}&end_date={$endDate}&transaction_type=CreditCardCredit&minorversion=65";

        $response = Http::withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->get($url)
            ->throw()
            ->json();

        $columns = collect($response['Columns']['Column'] ?? [])->pluck('ColTitle')->toArray();
        $rows = collect($response['Rows']['Row'] ?? []);

        // 2️⃣ Map each row into a key-value structure
        $entries = $rows->map(function ($row) use ($columns) {
            $colData = $row['ColData'] ?? [];
            $mapped = [];
            foreach ($colData as $index => $col) {
                $key = $columns[$index] ?? "Column_{$index}";
                $mapped[$key] = $col['value'] ?? null;
            }
            return $mapped;
        })->filter(function ($entry) {
            return ($entry['Transaction Type'] ?? '') === 'Credit Card Credit';
        })->values();

        if ($entries->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'count' => 0,
                'data' => [],
                'message' => 'No Credit Card Credit entries found for this date range.',
            ]);
        }

        // 3️⃣ Collect unique account and split names for lookup
        $accountNames = collect($entries)->pluck('Account')
            ->merge($entries->pluck('Split'))
            ->unique()
            ->filter()
            ->values();

        // 4️⃣ Fetch all account details in batches (QuickBooks limits query size)
        $accountDetails = collect();
        foreach ($accountNames->chunk(20) as $chunk) {
            $query = "SELECT Id, Name, AccountType, AccountSubType, Classification 
                      FROM Account WHERE Name IN ('" .
                      implode("','", $chunk->map(fn($n) => addslashes($n))->toArray()) . "')";
            
            $resp = $this->runQuery($query);

            // Some QuickBooks responses may come back as JsonResponse, so handle that
            if ($resp instanceof \Illuminate\Http\JsonResponse) {
                $resp = $resp->getData(true);
            }

            $accountDetails = $accountDetails->merge($resp['QueryResponse']['Account'] ?? []);
        }

        // 5️⃣ Create lookup table by Name
        $accountsByName = $accountDetails->keyBy('Name');

        // 6️⃣ Merge account info back into each entry
        $detailedEntries = $entries->map(function ($e) use ($accountsByName) {
            $account = $accountsByName[$e['Account']] ?? null;
            $split   = $accountsByName[$e['Split']] ?? null;

            return [
                'Date' => $e['Date'] ?? null,
                'TransactionType' => $e['Transaction Type'] ?? null,
                'Name' => $e['Name'] ?? null,
                'Memo' => $e['Memo/Description'] ?? null,
                'Amount' => $e['Amount'] ?? null,
                'Posting' => $e['Posting'] ?? null,
                'Account' => $account ? [
                    'Id' => $account['Id'] ?? null,
                    'Name' => $account['Name'] ?? null,
                    'AccountType' => $account['AccountType'] ?? null,
                    'AccountSubType' => $account['AccountSubType'] ?? null,
                    'Classification' => $account['Classification'] ?? null,
                ] : [
                    'Id' => null,
                    'Name' => $e['Account'] ?? null
                ],
                'Split' => $split ? [
                    'Id' => $split['Id'] ?? null,
                    'Name' => $split['Name'] ?? null,
                    'AccountType' => $split['AccountType'] ?? null,
                    'AccountSubType' => $split['AccountSubType'] ?? null,
                    'Classification' => $split['Classification'] ?? null,
                ] : [
                    'Id' => null,
                    'Name' => $e['Split'] ?? null
                ],
            ];
        });

        // ✅ 7️⃣ Return complete formatted response
        return response()->json([
            'status' => 'success',
            'count' => $detailedEntries->count(),
            'data' => $detailedEntries->values(),
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}



    /**
     * 📊 Credit Card Credits with Bill Links - Enhanced version showing bill applications
     */
    public function creditCardCreditsWithBills()
    {
        try {
            // Fetch credit card credits and bills
            $creditsResponse = $this->runQuery("SELECT * FROM CreditCardCredit STARTPOSITION 1 MAXRESULTS 200");
            $billsResponse = $this->runQuery("SELECT * FROM Bill STARTPOSITION 1 MAXRESULTS 200");

            if ($creditsResponse instanceof \Illuminate\Http\JsonResponse) {
                return $creditsResponse;
            }

            $credits = collect($creditsResponse['QueryResponse']['CreditCardCredit'] ?? []);
            $bills = collect($billsResponse['QueryResponse']['Bill'] ?? []);

            // Normalize credits with linked bills
            $creditsWithBills = $credits->map(function ($credit) use ($bills) {
                $linkedBills = [];

                foreach ($credit['Line'] ?? [] as $line) {
                    if (!empty($line['LinkedTxn'])) {
                        $linkedArray = is_array($line['LinkedTxn']) ? $line['LinkedTxn'] : [$line['LinkedTxn']];
                        foreach ($linkedArray as $linked) {
                            if (isset($linked['TxnType'], $linked['TxnId']) && strcasecmp($linked['TxnType'], 'Bill') === 0) {
                                $bill = $bills->first(fn($b) => (string) $b['Id'] === (string) $linked['TxnId']);
                                if ($bill) {
                                    $linkedBills[] = [
                                        'BillId' => $bill['Id'] ?? null,
                                        'DocNumber' => $bill['DocNumber'] ?? null,
                                        'VendorName' => $bill['VendorRef']['name'] ?? null,
                                        'BillAmount' => (float) ($bill['TotalAmt'] ?? 0),
                                        'Balance' => (float) ($bill['Balance'] ?? 0),
                                        'TxnDate' => $bill['TxnDate'] ?? null,
                                    ];
                                }
                            }
                        }
                    }
                }

                return [
                    'CreditId' => $credit['Id'] ?? null,
                    'DocNumber' => $credit['DocNumber'] ?? null,
                    'VendorName' => $credit['VendorRef']['name'] ?? null,
                    'VendorId' => $credit['VendorRef']['value'] ?? null,
                    'TxnDate' => $credit['TxnDate'] ?? null,
                    'TotalAmount' => (float) ($credit['TotalAmt'] ?? 0),
                    'CreditCardAccount' => [
                        'Id' => $credit['CCAccountRef']['value'] ?? null,
                        'Name' => $credit['CCAccountRef']['name'] ?? null,
                    ],
                    'LinkedBills' => $linkedBills,
                    'RawCredit' => $credit,
                ];
            });

            return response()->json([
                'status' => 'success',
                'count' => $creditsWithBills->count(),
                'data' => $creditsWithBills->values(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getEstimates($start = 1, $max = 200)
    {
        try {
            // Run QuickBooks query for Estimate objects
            $query = "SELECT * FROM Estimate STARTPOSITION {$start} MAXRESULTS {$max}";
            $response = $this->runQuery($query);

            // Handle validation or empty responses
            if (isset($response['Fault'])) {
                \Log::error('QuickBooks Estimate fetch error', [
                    'fault' => $response['Fault'],
                    'query' => $query,
                ]);
                return [
                    'success' => false,
                    'message' => $response['Fault']['Error'][0]['Detail'] ?? 'Unknown error',
                ];
            }

            // Extract Estimate data
            $estimates = $response['QueryResponse']['Estimate'] ?? [];

            return [
                'success' => true,
                'count' => count($estimates),
                'data' => $estimates,
            ];

        } catch (\Exception $e) {
            \Log::error('QuickBooks getEstimates exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
    private function runPayrollGraphQL(string $query, array $variables = [])
    {
        try {
            // Get tokens and realm ID like you already do for QBO REST
            $accessToken = $this->accessToken(); // Your existing function
            $realmId = $this->realmId();         // Your existing function

            if (!$accessToken || !$realmId) {
                throw new \Exception('Missing QuickBooks authorization.');
            }

            $url = "https://sandbox-graphql.qbo.intuit.com/v1/graphql";

            $payload = [
                'query' => $query,
                'variables' => (object) $variables,
            ];

            $client = new \GuzzleHttp\Client();

            $response = $client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => json_encode($payload),
            ]);

            return json_decode($response->getBody()->getContents(), true);

        } catch (\Exception $e) {
            \Log::error('QuickBooks Payroll GraphQL failed', [
                'message' => $e->getMessage(),
            ]);

            return [
                'errors' => [
                    ['message' => $e->getMessage()],
                ],
            ];
        }
    }

    public function getPayrollRuns($limit = 50, $cursor = null)
    {
        try {
            // GraphQL query for payroll runs (payslips) – adjust fields as needed
            $query = <<<'GRAPHQL'
        query GetPayrollRuns($limit: Int!, $cursor: String) {
          payrollRuns(limit: $limit, cursor: $cursor) {
            edges {
              node {
                id
                startDate
                endDate
                payDate
                totalGross
                totalNet
                employeeCount
              }
            }
            pageInfo {
              endCursor
              hasNextPage
            }
          }
        }
GRAPHQL;

            $variables = ['limit' => $limit, 'cursor' => $cursor];

            $response = $this->runPayrollGraphQL($query, $variables);

            if (isset($response['errors'])) {
                \Log::error('QuickBooks Payroll Runs fetch error', [
                    'errors' => $response['errors'],
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => $response['errors'][0]['message'] ?? 'Unknown error',
                ], 400);
            }

            $runs = collect($response['data']['payrollRuns']['edges'])
                ->map(fn($edge) => $edge['node']);

            return response()->json([
                'status' => 'success',
                'data' => $runs,
                'pageInfo' => $response['data']['payrollRuns']['pageInfo'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function getPayrollAdjustments($limit = 50, $cursor = null)
    {
        try {
            // GraphQL query for payroll adjustments – fields may vary
            $query = <<<'GRAPHQL'
        query GetPayrollAdjustments($limit: Int!, $cursor: String) {
          payrollAdjustments(limit: $limit, cursor: $cursor) {
            edges {
              node {
                id
                payrollRunId
                employeeId
                adjustmentType
                amount
                reason
                effectiveDate
              }
            }
            pageInfo {
              endCursor
              hasNextPage
            }
          }
        }
GRAPHQL;

            $variables = ['limit' => $limit, 'cursor' => $cursor];

            $response = $this->runPayrollGraphQL($query, $variables);

            if (isset($response['errors'])) {
                \Log::error('QuickBooks Payroll Adjustments fetch error', [
                    'errors' => $response['errors'],
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => $response['errors'][0]['message'] ?? 'Unknown error',
                ], 400);
            }

            $adjustments = collect($response['data']['payrollAdjustments']['edges'])
                ->map(fn($edge) => $edge['node']);

            return response()->json([
                'status' => 'success',
                'data' => $adjustments,
                'pageInfo' => $response['data']['payrollAdjustments']['pageInfo'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function getTransfers()
    {
        try {
            // 🔹 Run a QuickBooks Query to fetch up to 200 transfers
            $transferResponse = $this->runQuery("SELECT * FROM Transfer STARTPOSITION 1 MAXRESULTS 500");
            dd($transferResponse);
            // 🔹 Handle QuickBooks or connection errors
            if ($transferResponse instanceof \Illuminate\Http\JsonResponse) {
                return $transferResponse;
            }

            // 🔹 Handle Faults from QuickBooks
            if (isset($transferResponse['Fault'])) {
                return response()->json([
                    'status' => 'error',
                    'error' => $transferResponse['Fault']['Error'][0]['Detail'] ?? 'Unknown QuickBooks error',
                    'raw' => $transferResponse,
                ], 400);
            }

            // 🔹 Parse Transfer Data
            $transfers = collect($transferResponse['QueryResponse']['Transfer'] ?? [])->map(function ($transfer) {
                return [
                    'TransferId' => $transfer['Id'] ?? null,
                    'TxnDate' => $transfer['TxnDate'] ?? null,
                    'Amount' => $transfer['Amount'] ?? 0,
                    'FromAccount' => [
                        'Id' => $transfer['FromAccountRef']['value'] ?? null,
                        'Name' => $transfer['FromAccountRef']['name'] ?? null,
                    ],
                    'ToAccount' => [
                        'Id' => $transfer['ToAccountRef']['value'] ?? null,
                        'Name' => $transfer['ToAccountRef']['name'] ?? null,
                    ],
                    'PrivateNote' => $transfer['PrivateNote'] ?? null,
                    'Currency' => $transfer['CurrencyRef']['name'] ?? null,
                    'ExchangeRate' => $transfer['ExchangeRate'] ?? null,
                    'RawTransfer' => $transfer,
                ];
            });

            // 🔹 Return a clean JSON response
            return response()->json([
                'status' => 'success',
                'count' => $transfers->count(),
                'data' => $transfers->values(),
            ]);

        } catch (\Exception $e) {
            dd($e);
            // 🔹 Fallback on any unexpected errors
            \Log::error('QuickBooks Transfer Fetch Failed', ['message' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
// public function getAllTransactionsGrouped(Request $request)
// {
//     try {
//         $start = (int) $request->get('start', 1);
//         $max = (int) $request->get('max', 50);

//         $types = [
//             'Invoice', 'Bill', 'Payment', 'Expense', 'JournalEntry',
//             'Deposit', 'Transfer', 'CreditMemo', 'Purchase', 'Estimate',
//             'VendorCredit', 'SalesReceipt', 'RefundReceipt', 'PurchaseOrder',
//             'TimeActivity'
//         ];

//         $grouped = [];

//         foreach ($types as $type) {
//             try {
//                 $query = "SELECT * FROM {$type} STARTPOSITION {$start} MAXRESULTS {$max}";
//                 $response = $this->runQuery($query);

//                 // Handle token or connection issues
//                 if ($response instanceof \Illuminate\Http\JsonResponse) {
//                     continue;
//                 }

//                 // Skip if Fault
//                 if (isset($response['Fault'])) {
//                     $grouped[$type] = [
//                         'status' => 'error',
//                         'message' => $response['Fault']['Error'][0]['Message'] ?? 'Unknown error',
//                     ];
//                     continue;
//                 }

//                 // Extract transactions of this type
//                 $data = collect($response['QueryResponse'][$type] ?? []);

//                 $grouped[$type] = [
//                     'count' => $data->count(),
//                     'data' => $data->values(),
//                 ];
//             } catch (\Exception $innerEx) {
//                 $grouped[$type] = [
//                     'status' => 'error',
//                     'message' => $innerEx->getMessage(),
//                 ];
//             }
//         }

//         return dd([
//             'status' => 'success',
//             'types_count' => count($types),
//             'data' => $grouped,
//         ]);

//     } catch (\Exception $e) {
//         return response()->json([
//             'status' => 'error',
//             'message' => $e->getMessage(),
//         ], 500);
//     }
// }
  public function getAllTransactionsGrouped(Request $request)
{
    try {
        $start = (int) $request->get('start', 1);
        $max = (int) $request->get('max', 100);

        // 1️⃣ Fetch Invoices
        $invoiceQuery = "SELECT * FROM Invoice STARTPOSITION {$start} MAXRESULTS {$max}";
        $invoiceResponse = $this->runQuery($invoiceQuery);

        if ($invoiceResponse instanceof \Illuminate\Http\JsonResponse) return $invoiceResponse;
        if (isset($invoiceResponse['Fault'])) {
            throw new \Exception($invoiceResponse['Fault']['Error'][0]['Message'] ?? 'Error fetching invoices');
        }

        $invoices = collect($invoiceResponse['QueryResponse']['Invoice'] ?? []);

        // 2️⃣ Fetch Payments
        $paymentQuery = "SELECT * FROM Payment STARTPOSITION {$start} MAXRESULTS {$max}";
        $paymentResponse = $this->runQuery($paymentQuery);

        if ($paymentResponse instanceof \Illuminate\Http\JsonResponse) return $paymentResponse;
        if (isset($paymentResponse['Fault'])) {
            throw new \Exception($paymentResponse['Fault']['Error'][0]['Message'] ?? 'Error fetching payments');
        }

        $payments = collect($paymentResponse['QueryResponse']['Payment'] ?? []);

        // 3️⃣ Fetch Accounts
        $accountQuery = "SELECT * FROM Account STARTPOSITION 1 MAXRESULTS 500";
        $accountResponse = $this->runQuery($accountQuery);

        if ($accountResponse instanceof \Illuminate\Http\JsonResponse) return $accountResponse;
        if (isset($accountResponse['Fault'])) {
            throw new \Exception($accountResponse['Fault']['Error'][0]['Message'] ?? 'Error fetching accounts');
        }

        $accounts = collect($accountResponse['QueryResponse']['Account'] ?? []);

        // 4️⃣ Combine — only invoices that have payments
        $invoicePayments = collect();

        foreach ($payments as $payment) {
            if (!isset($payment['Line'])) continue;

            foreach ($payment['Line'] as $line) {
                if (!isset($line['LinkedTxn'])) continue;

                foreach ($line['LinkedTxn'] as $txn) {
                    if ($txn['TxnType'] === 'Invoice') {
                        $invoiceId = $txn['TxnId'];
                        $invoice = $invoices->firstWhere('Id', $invoiceId);
                        if (!$invoice) continue;

                        // Invoice account
                        $invoiceAccountId = $invoice['ARAccountRef']['value'] ?? null;
                        $invoiceAccount = $accounts->firstWhere('Id', $invoiceAccountId);

                        // Payment account
                        $paymentAccountId = $payment['DepositToAccountRef']['value'] ?? null;
                        $paymentAccount = $accounts->firstWhere('Id', $paymentAccountId);

                        $invoicePayments->push([
                            'invoice' => $invoice,
                            'invoice_account' => $invoiceAccount ?? null,
                            'payment' => $payment,
                            'payment_account' => $paymentAccount ?? null,
                        ]);
                    }
                }
            }
        }

        // 5️⃣ Show only a single clean linked record
        $first = $invoicePayments->first();

        return dd([
            'status' => 'success',
            'count' => $invoicePayments->count(),
            'linked_record' => $first
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}
}
