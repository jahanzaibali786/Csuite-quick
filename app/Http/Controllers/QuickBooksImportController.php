<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Invoice;
use App\Models\InvoiceProduct;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Vender;
use App\Models\ProductService;
use App\Models\ChartOfAccount;
use App\Models\ProductServiceCategory;
use App\Models\ProductServiceUnit;
use App\Models\ChartOfAccountType;
use App\Models\ChartOfAccountSubType;
use App\Models\ChartOfAccountParent;
use App\Models\Bill;
use App\Models\BillProduct;
use App\Models\BillPayment;
use App\Models\BillAccount;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\TransactionLines;
use Illuminate\Support\Facades\Auth;
class QuickBooksImportController extends Controller
{
    protected $qbController;

    public function __construct()
    {
        $this->qbController = new QuickBooksApiController();
    }

    public function showImportView()
    {
        return view('quickbooks_invoices');
    }


    public function importInvoices(Request $request)
    {
        try {
            // Fetch all invoices with pagination
            $allInvoices = collect();
            $startPosition = 1;
            $maxResults = 50; // Adjust batch size as needed

            do {
                // Fetch paginated batch
                $query = "SELECT * FROM Invoice STARTPOSITION {$startPosition} MAXRESULTS {$maxResults}";
                $invoicesResponse = $this->qbController->runQuery($query);

                // Handle API errors
                if ($invoicesResponse instanceof \Illuminate\Http\JsonResponse) {
                    return $invoicesResponse;
                }

                // Get invoices from response
                $invoicesData = $invoicesResponse['QueryResponse']['Invoice'] ?? [];

                // Merge entire objects (keep all keys)
                $allInvoices = $allInvoices->merge($invoicesData);

                // Move to next page
                $fetchedCount = count($invoicesData);
                $startPosition += $fetchedCount;
            } while ($fetchedCount === $maxResults); // continue if page is full

            // Fetch all payments with pagination
            $allPayments = collect();
            $startPosition = 1;

            do {
                // Fetch paginated batch
                $query = "SELECT * FROM Payment STARTPOSITION {$startPosition} MAXRESULTS {$maxResults}";
                $paymentsResponse = $this->qbController->runQuery($query);

                // Handle API errors
                if ($paymentsResponse instanceof \Illuminate\Http\JsonResponse) {
                    return $paymentsResponse;
                }

                // Get payments from response
                $paymentsData = $paymentsResponse['QueryResponse']['Payment'] ?? [];

                // Merge entire objects (keep all keys)
                $allPayments = $allPayments->merge($paymentsData);

                // Move to next page
                $fetchedCount = count($paymentsData);
                $startPosition += $fetchedCount;
            } while ($fetchedCount === $maxResults); // continue if page is full

            // Fetch items and accounts (these are usually smaller datasets)
            $itemsRaw = $this->qbController->runQuery("SELECT * FROM Item STARTPOSITION 1 MAXRESULTS 500");
            $accountsRaw = $this->qbController->runQuery("SELECT * FROM Account STARTPOSITION 1 MAXRESULTS 500");

            $itemsList = collect($itemsRaw['QueryResponse']['Item'] ?? []);
            $accountsList = collect($accountsRaw['QueryResponse']['Account'] ?? []);

            $itemsMap = $itemsList->keyBy(fn($it) => $it['Id'] ?? null)->toArray();
            $accountsMap = $accountsList->keyBy(fn($a) => $a['Id'] ?? null)->toArray();

            // Helper functions as in the original
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

            $detectAccountForSalesItem = function ($sid) use ($itemsMap, $accountsMap) {
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

            $parseInvoiceLine = function ($line) use ($detectAccountForSalesItem, $itemsMap, $accountsMap) {
                $out = [];
                $detailType = $line['DetailType'] ?? null;

                if (!empty($line['GroupLineDetail']) && !empty($line['GroupLineDetail']['Line'])) {
                    foreach ($line['GroupLineDetail']['Line'] as $child) {
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

            $invoices = $allInvoices->map(function ($invoice) use ($parseInvoiceLine, $accountsMap, $arAccount, $taxAccount, &$invoicesList) {
                $parsedLines = [];
                foreach ($invoice['Line'] ?? [] as $line) {
                    $parsedLines = array_merge($parsedLines, $parseInvoiceLine($line));
                }

                $unmapped = array_values(array_filter($parsedLines, fn($l) => empty($l['AccountId']) && (float) $l['Amount'] != 0.0));

                $taxTotal = 0;
                if (!empty($invoice['TxnTaxDetail']['TotalTax']))
                    $taxTotal = $invoice['TxnTaxDetail']['TotalTax'];
                elseif (!empty($invoice['TotalTax']))
                    $taxTotal = $invoice['TotalTax'];

                $totalAmount = (float) ($invoice['TotalAmt'] ?? 0);

                $journalLines = [];

                if ($arAccount) {
                    $journalLines[] = [
                        'AccountId' => $arAccount['Id'],
                        'AccountName' => $arAccount['Name'],
                        'Debit' => $totalAmount,
                        'Credit' => 0.0,
                        'Note' => 'Accounts Receivable (invoice total)'
                    ];
                } else {
                    dd($arAccount);
                    // $journalLines[] = [
                    //     'AccountId' => null,
                    //     'AccountName' => 'Accounts Receivable (not found)',
                    //     'Debit' => $totalAmount,
                    //     'Credit' => 0.0,
                    //     'Note' => 'Accounts Receivable (invoice total, account not auto-detected)'
                    // ];
                }

                foreach ($parsedLines as $pl) {
                    if ((float) $pl['Amount'] == 0.0)
                        continue;
                    if (empty($pl['AccountId']))
                        continue;
                    $journalLines[] = [
                        'AccountId' => $pl['AccountId'],
                        'AccountName' => $pl['AccountName'] ?? null,
                        'Debit' => 0.0,
                        'Credit' => (float) $pl['Amount'],
                        'Note' => $pl['Description'] ?? 'Sales / line item'
                    ];
                }

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

            $payments = $allPayments->map(function ($payment) use (&$paymentsList) {
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
            // dd($invoicesWithPayments->first());
            // Now, import logic
            $imported = 0;
            $skipped = 0;
            $failed = 0;

            DB::beginTransaction();
            try {
                foreach ($invoicesWithPayments as $qbInvoice) {
                    $qbId = $qbInvoice['InvoiceId'];

                    // Check for duplicate
                    $existing = Invoice::where('invoice_id', $qbId)->first();
                    if ($existing) {
                        $skipped++;
                        continue;
                    }

                    // Map customer_id - assuming CustomerRef value maps to local customer id, but need to handle
                    // For simplicity, assume customer_id is the QB CustomerRef value, but in reality, you might need to map QB customers to local customers
                    $customerId = $qbInvoice['CustomerId']; // This might need adjustment

                    // Insert invoice
                    $invoice = Invoice::create([
                        'invoice_id' => $qbId,
                        'customer_id' => $customerId,
                        'issue_date' => $qbInvoice['TxnDate'],
                        'due_date' => $qbInvoice['DueDate'],
                        'ref_number' => $qbInvoice['DocNumber'],
                        'status' => 2, // default
                        // other fields as needed
                        'created_by' => \Auth::user()->creatorId(),
                        'owned_by' => \Auth::user()->ownedId(),
                    ]);

                    // Insert products
                    foreach ($qbInvoice['ParsedLines'] as $line) {
                        if (empty($line['AccountId']))
                            continue; // Skip unmapped

                        // Map to product by name - create if doesn't exist
                        $itemName = $line['RawLine']['SalesItemLineDetail']['ItemRef']['name'] ?? null;
                        if (!$itemName)
                            continue;

                        $product = ProductService::where('name', $itemName)
                            ->where('created_by', \Auth::user()->creatorId())
                            ->first();

                        if (!$product) {
                            // Create product if it doesn't exist
                            $unit = ProductServiceUnit::firstOrCreate(
                                ['name' => 'pcs'],
                                ['created_by' => \Auth::user()->creatorId()]
                            );

                            $productCategory = ProductServiceCategory::firstOrCreate(
                                [
                                    'name' => 'Product',
                                    'created_by' => \Auth::user()->creatorId(),
                                ],
                                [
                                    'color' => '#4CAF50',
                                    'type' => 'Product',
                                    'chart_account_id' => 0,
                                    'created_by' => \Auth::user()->creatorId(),
                                    'owned_by' => \Auth::user()->ownedId(),
                                ]
                            );

                            $productData = [
                                'name' => $itemName,
                                'sku' => $itemName,
                                'sale_price' => $line['Amount'] ?? 0,
                                'purchase_price' => 0,
                                'quantity' => 0,
                                'unit_id' => $unit->id,
                                'type' => 'product',
                                'category_id' => $productCategory->id,
                                'created_by' => \Auth::user()->creatorId(),
                            ];

                            // Map chart accounts if available
                            if (!empty($line['AccountId'])) {
                                $account = ChartOfAccount::where('code', $line['AccountId'])
                                    ->where('created_by', \Auth::user()->creatorId())
                                    ->first();
                                if ($account) {
                                    $productData['sale_chartaccount_id'] = $account->id;
                                }
                            }

                            $product = ProductService::create($productData);
                        }
                        // dd($line,$product,$qbInvoice);
                        InvoiceProduct::create([
                            'invoice_id' => $invoice->id,
                            'product_id' => $product->id,
                            'quantity' => $line['RawLine']['SalesItemLineDetail']['Qty'] ?? 1,
                            'price' => $line['Amount'],
                            'description' => $line['Description'],
                        ]);
                    }

                    // Insert payments
                    foreach ($qbInvoice['Payments'] as $payment) {
                        // Determine payment method based on payment data
                        $paymentMethod = $payment['PaymentMethod'];

                        // If payment method is null, try to determine from payment type or account
                        if (!$paymentMethod) {
                            // Check if it's a credit card payment
                            if (isset($payment['RawPayment']['CreditCardPayment'])) {
                                $paymentMethod = 'Credit Card';
                            }
                            // Check if it's a check payment
                            elseif (isset($payment['RawPayment']['CheckPayment'])) {
                                $paymentMethod = 'Check';
                            }
                            // Check deposit account type
                            elseif (isset($payment['RawPayment']['DepositToAccountRef'])) {
                                $accountId = $payment['RawPayment']['DepositToAccountRef']['value'];
                                $account = collect($accountsList)->firstWhere('Id', $accountId);
                                if ($account) {
                                    $accountType = strtolower($account['AccountType'] ?? '');
                                    if (strpos($accountType, 'bank') !== false || strpos($accountType, 'checking') !== false) {
                                        $paymentMethod = 'Bank Transfer';
                                    } elseif (strpos($accountType, 'credit') !== false) {
                                        $paymentMethod = 'Credit Card';
                                    } else {
                                        $paymentMethod = 'Cash';
                                    }
                                } else {
                                    $paymentMethod = 'Cash';
                                }
                            } else {
                                $paymentMethod = 'Cash';
                            }
                        }

                        InvoicePayment::create([
                            'invoice_id' => $invoice->id,
                            'date' => $payment['TxnDate'],
                            'amount' => $payment['TotalAmount'],
                            'payment_method' => $paymentMethod,
                            'txn_id' => $payment['PaymentId'],
                            'currency' => 'USD', // default
                            'reference' => $payment['PaymentId'],
                            'description' => 'Payment for Invoice ' . $qbInvoice['DocNumber'],
                        ]);
                    }
                    if (!empty($qbInvoice['Payments'])) {
                        $invoice->status = 4;
                        $invoice->send_date = $qbInvoice['TxnDate'];

                    }
                    $invoice->save();
                    $imported++;
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                dd($e);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Import failed: ' . $e->getMessage(),
                ], 500);
            }

            return response()->json([
                'status' => 'success',
                'message' => "Invoices import completed. Imported: {$imported}, Skipped: {$skipped}, Failed: {$failed}",
                'imported' => $imported,
                'skipped' => $skipped,
                'failed' => $failed,
            ]);

        } catch (\Exception $e) {
            dd($e);
            return response()->json([
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function customers()
    {
        try {
            $allCustomers = collect();
            $startPosition = 1;
            $maxResults = 50; // Adjust batch size as needed

            do {
                // Fetch paginated batch
                $query = "SELECT * FROM Customer STARTPOSITION {$startPosition} MAXRESULTS {$maxResults}";
                $customersResponse = $this->qbController->runQuery($query);

                // Handle API errors
                if ($customersResponse instanceof \Illuminate\Http\JsonResponse) {
                    return $customersResponse;
                }
                // Get customers from response
                $customersData = $customersResponse['QueryResponse']['Customer'] ?? [];

                // Merge entire objects (keep all keys)
                $allCustomers = $allCustomers->merge($customersData);

                // Move to next page
                $fetchedCount = count($customersData);
                $startPosition += $fetchedCount;
            } while ($fetchedCount === $maxResults); // continue if page is full

            // Import customers to local database
            $importedCount = 0;
            $updatedCount = 0;
            $errors = [];

            foreach ($allCustomers as $qbCustomer) {
                try {
                    // dd($allCustomers);
                    // Check if customer already exists (by QB ID or email)
                    $existingCustomer = Customer::Where('email', $qbCustomer['PrimaryEmailAddr']['Address'] ?? null)
                        ->where('created_by', \Auth::user()->creatorId())->first();
                    if ($existingCustomer) {
                        // Update existing customer
                        $existingCustomer->update([
                            'name' => $qbCustomer['Name'] ?? $qbCustomer['FullyQualifiedName'] ?? '',
                            'email' => $qbCustomer['PrimaryEmailAddr']['Address'] ?? null,
                            'contact' => $qbCustomer['PrimaryPhone']['FreeFormNumber'] ?? null,
                            'billing_name' => $qbCustomer['BillAddr']['Line1'] ?? null,
                            'billing_city' => $qbCustomer['BillAddr']['City'] ?? null,
                            'billing_state' => $qbCustomer['BillAddr']['CountrySubDivisionCode'] ?? null,
                            'billing_country' => $qbCustomer['BillAddr']['Country'] ?? null,
                            'billing_zip' => $qbCustomer['BillAddr']['PostalCode'] ?? null,
                            'billing_address' => implode(', ', array_filter([
                                $qbCustomer['BillAddr']['Line1'] ?? null,
                                $qbCustomer['BillAddr']['Line2'] ?? null,
                                $qbCustomer['BillAddr']['City'] ?? null,
                                $qbCustomer['BillAddr']['CountrySubDivisionCode'] ?? null,
                                $qbCustomer['BillAddr']['PostalCode'] ?? null,
                                $qbCustomer['BillAddr']['Country'] ?? null,
                            ])),
                            'shipping_name' => $qbCustomer['ShipAddr']['Line1'] ?? null,
                            'shipping_city' => $qbCustomer['ShipAddr']['City'] ?? null,
                            'shipping_state' => $qbCustomer['ShipAddr']['CountrySubDivisionCode'] ?? null,
                            'shipping_country' => $qbCustomer['ShipAddr']['Country'] ?? null,
                            'shipping_zip' => $qbCustomer['ShipAddr']['PostalCode'] ?? null,
                            'shipping_address' => implode(', ', array_filter([
                                $qbCustomer['ShipAddr']['Line1'] ?? null,
                                $qbCustomer['ShipAddr']['Line2'] ?? null,
                                $qbCustomer['ShipAddr']['City'] ?? null,
                                $qbCustomer['ShipAddr']['CountrySubDivisionCode'] ?? null,
                                $qbCustomer['ShipAddr']['PostalCode'] ?? null,
                                $qbCustomer['ShipAddr']['Country'] ?? null,
                            ])),
                        ]);
                        $updatedCount++;
                    } else {
                        // Create new customer
                        $customer = Customer::create([
                            'customer_id' => $qbCustomer['Id'],
                            'name' => $qbCustomer['Name'] ?? $qbCustomer['FullyQualifiedName'] ?? '',
                            'email' => $qbCustomer['PrimaryEmailAddr']['Address'] ?? null,
                            'contact' => $qbCustomer['PrimaryPhone']['FreeFormNumber'] ?? null,
                            'is_active' => 1,
                            'created_by' => \Auth::user()->creatorId(),
                            'owned_by' => \Auth::user()->ownedId(),
                            'billing_name' => $qbCustomer['BillAddr']['Line1'] ?? null,
                            'billing_city' => $qbCustomer['BillAddr']['City'] ?? null,
                            'billing_state' => $qbCustomer['BillAddr']['CountrySubDivisionCode'] ?? null,
                            'billing_country' => $qbCustomer['BillAddr']['Country'] ?? null,
                            'billing_zip' => $qbCustomer['BillAddr']['PostalCode'] ?? null,
                            'billing_address' => implode(', ', array_filter([
                                $qbCustomer['BillAddr']['Line1'] ?? null,
                                $qbCustomer['BillAddr']['Line2'] ?? null,
                                $qbCustomer['BillAddr']['City'] ?? null,
                                $qbCustomer['BillAddr']['CountrySubDivisionCode'] ?? null,
                                $qbCustomer['BillAddr']['PostalCode'] ?? null,
                                $qbCustomer['BillAddr']['Country'] ?? null,
                            ])),
                            'shipping_name' => $qbCustomer['ShipAddr']['Line1'] ?? null,
                            'shipping_city' => $qbCustomer['ShipAddr']['City'] ?? null,
                            'shipping_state' => $qbCustomer['ShipAddr']['CountrySubDivisionCode'] ?? null,
                            'shipping_country' => $qbCustomer['ShipAddr']['Country'] ?? null,
                            'shipping_zip' => $qbCustomer['ShipAddr']['PostalCode'] ?? null,
                            'shipping_address' => implode(', ', array_filter([
                                $qbCustomer['ShipAddr']['Line1'] ?? null,
                                $qbCustomer['ShipAddr']['Line2'] ?? null,
                                $qbCustomer['ShipAddr']['City'] ?? null,
                                $qbCustomer['ShipAddr']['CountrySubDivisionCode'] ?? null,
                                $qbCustomer['ShipAddr']['PostalCode'] ?? null,
                                $qbCustomer['ShipAddr']['Country'] ?? null,
                            ])),
                        ]);
                        $customer->save();
                        $importedCount++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Error importing customer {$qbCustomer['Id']}: " . $e->getMessage();
                }
            }
            return response()->json([
                'status' => 'success',
                'message' => "Customers import completed. Imported: {$importedCount}, Updated: {$updatedCount}",
                'imported' => $importedCount,
                'updated' => $updatedCount,
                'errors' => $errors,
                'total_fetched' => $allCustomers->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function vendors()
    {
        try {
            $allVendors = collect();
            $startPosition = 1;
            $maxResults = 50; // Adjust batch size as needed

            do {
                // Fetch paginated batch
                $query = "SELECT * FROM Vendor STARTPOSITION {$startPosition} MAXRESULTS {$maxResults}";
                $vendorsResponse = $this->qbController->runQuery($query);

                // Handle API errors
                if ($vendorsResponse instanceof \Illuminate\Http\JsonResponse) {
                    return $vendorsResponse;
                }
                // Get vendors from response
                $vendorsData = $vendorsResponse['QueryResponse']['Vendor'] ?? [];

                // Merge entire objects (keep all keys)
                $allVendors = $allVendors->merge($vendorsData);

                // Move to next page
                $fetchedCount = count($vendorsData);
                $startPosition += $fetchedCount;
            } while ($fetchedCount === $maxResults); // continue if page is full

            // Import vendors to local database
            $importedCount = 0;
            $updatedCount = 0;
            $errors = [];
            // dd($allVendors);
            foreach ($allVendors as $qbVendor) {
                try {
                    // Check if vendor already exists (by email)
                    $existingVendor = Vender::where('name', $qbVendor['DisplayName'] ?? null)
                        ->where('created_by', \Auth::user()->creatorId())->first();
                    if ($existingVendor) {
                        // Update existing vendor
                        $existingVendor->update([
                            'name' => $qbVendor['Name'] ?? $qbVendor['DisplayName'] ?? '',
                            'email' => $qbVendor['PrimaryEmailAddr']['Address'] ?? null,
                            'contact' => $qbVendor['PrimaryPhone']['FreeFormNumber'] ?? null,
                            'billing_name' => $qbVendor['BillAddr']['Line1'] ?? null,
                            'billing_city' => $qbVendor['BillAddr']['City'] ?? null,
                            'billing_state' => $qbVendor['BillAddr']['CountrySubDivisionCode'] ?? null,
                            'billing_country' => $qbVendor['BillAddr']['Country'] ?? null,
                            'billing_zip' => $qbVendor['BillAddr']['PostalCode'] ?? null,
                            'billing_address' => implode(', ', array_filter([
                                $qbVendor['BillAddr']['Line1'] ?? null,
                                $qbVendor['BillAddr']['Line2'] ?? null,
                                $qbVendor['BillAddr']['City'] ?? null,
                                $qbVendor['BillAddr']['CountrySubDivisionCode'] ?? null,
                                $qbVendor['BillAddr']['PostalCode'] ?? null,
                                $qbVendor['BillAddr']['Country'] ?? null,
                            ])),
                            'shipping_name' => $qbVendor['ShipAddr']['Line1'] ?? null,
                            'shipping_city' => $qbVendor['ShipAddr']['City'] ?? null,
                            'shipping_state' => $qbVendor['ShipAddr']['CountrySubDivisionCode'] ?? null,
                            'shipping_country' => $qbVendor['ShipAddr']['Country'] ?? null,
                            'shipping_zip' => $qbVendor['ShipAddr']['PostalCode'] ?? null,
                            'shipping_address' => implode(', ', array_filter([
                                $qbVendor['ShipAddr']['Line1'] ?? null,
                                $qbVendor['ShipAddr']['Line2'] ?? null,
                                $qbVendor['ShipAddr']['City'] ?? null,
                                $qbVendor['ShipAddr']['CountrySubDivisionCode'] ?? null,
                                $qbVendor['ShipAddr']['PostalCode'] ?? null,
                                $qbVendor['ShipAddr']['Country'] ?? null,
                            ])),
                        ]);
                        $updatedCount++;
                    } else {
                        // Create new vendor
                        $vendor = Vender::create([
                            'vender_id' => $qbVendor['Id'],
                            'name' => $qbVendor['Name'] ?? $qbVendor['DisplayName'] ?? '',
                            'email' => $qbVendor['PrimaryEmailAddr']['Address'] ?? null,
                            'contact' => $qbVendor['PrimaryPhone']['FreeFormNumber'] ?? null,
                            'is_active' => 1,
                            'created_by' => \Auth::user()->creatorId(),
                            'owned_by' => \Auth::user()->ownedId(),
                            'billing_name' => $qbVendor['BillAddr']['Line1'] ?? null,
                            'billing_city' => $qbVendor['BillAddr']['City'] ?? null,
                            'billing_state' => $qbVendor['BillAddr']['CountrySubDivisionCode'] ?? null,
                            'billing_country' => $qbVendor['BillAddr']['Country'] ?? null,
                            'billing_zip' => $qbVendor['BillAddr']['PostalCode'] ?? null,
                            'billing_address' => implode(', ', array_filter([
                                $qbVendor['BillAddr']['Line1'] ?? null,
                                $qbVendor['BillAddr']['Line2'] ?? null,
                                $qbVendor['BillAddr']['City'] ?? null,
                                $qbVendor['BillAddr']['CountrySubDivisionCode'] ?? null,
                                $qbVendor['BillAddr']['PostalCode'] ?? null,
                                $qbVendor['BillAddr']['Country'] ?? null,
                            ])),
                            'shipping_name' => $qbVendor['ShipAddr']['Line1'] ?? null,
                            'shipping_city' => $qbVendor['ShipAddr']['City'] ?? null,
                            'shipping_state' => $qbVendor['ShipAddr']['CountrySubDivisionCode'] ?? null,
                            'shipping_country' => $qbVendor['ShipAddr']['Country'] ?? null,
                            'shipping_zip' => $qbVendor['ShipAddr']['PostalCode'] ?? null,
                            'shipping_address' => implode(', ', array_filter([
                                $qbVendor['ShipAddr']['Line1'] ?? null,
                                $qbVendor['ShipAddr']['Line2'] ?? null,
                                $qbVendor['ShipAddr']['City'] ?? null,
                                $qbVendor['ShipAddr']['CountrySubDivisionCode'] ?? null,
                                $qbVendor['ShipAddr']['PostalCode'] ?? null,
                                $qbVendor['ShipAddr']['Country'] ?? null,
                            ])),
                        ]);
                        $vendor->save();
                        $importedCount++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Error importing vendor {$qbVendor['Id']}: " . $e->getMessage();
                }
            }
            return response()->json([
                'status' => 'success',
                'message' => "Vendors import completed. Imported: {$importedCount}, Updated: {$updatedCount}",
                'imported' => $importedCount,
                'updated' => $updatedCount,
                'errors' => $errors,
                'total_fetched' => $allVendors->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function chartOfAccounts()
    {
        try {
            $allAccounts = collect();
            $startPosition = 1;
            $maxResults = 100;
            $importedCount = 0;

            // 🌀 Fetch all accounts from QuickBooks in batches
            do {
                $query = "SELECT * FROM Account STARTPOSITION {$startPosition} MAXRESULTS {$maxResults}";
                $accountsResponse = $this->qbController->runQuery($query);

                if ($accountsResponse instanceof \Illuminate\Http\JsonResponse) {
                    return $accountsResponse;
                }

                $accountsData = $accountsResponse['QueryResponse']['Account'] ?? [];
                $allAccounts = $allAccounts->merge($accountsData);

                $fetchedCount = count($accountsData);
                $startPosition += $fetchedCount;
            } while ($fetchedCount === $maxResults);

            // Sort accounts numerically by ID
            $allAccounts = $allAccounts->sortBy(fn($a) => (int) $a['Id'])->values();

            // 🧭 Import each account
            foreach ($allAccounts as $account) {
                $localAccount = $this->ensureChartOfAccount(
                    $account['Name'] ?? '',
                    $account['Classification'] ?? '',
                    $account['AccountSubType'] ?? 'Other',
                    $account
                );

                if (!$localAccount) {
                    continue; // Skip unmapped or invalid accounts
                }

                // Handle parent relationship
                $parentId = 0;
                if (isset($account['ParentRef']['value'])) {
                    $parentQBCode = $account['ParentRef']['value'];
                    $parentAccount = ChartOfAccount::where('code', $parentQBCode)
                        ->where('created_by', auth()->user()->creatorId())
                        ->first();
                    // dd($parentAccount->id);
                    if ($parentAccount) {
                        $parentRecord = ChartOfAccountParent::firstOrCreate(
                            [
                                'name' => $parentAccount->name,
                                'created_by' => auth()->user()->creatorId(),
                                'sub_type' => $parentAccount->sub_type ?? null,
                                'type' => $parentAccount->type ?? null,
                                'account' => $parentAccount->id,
                            ]
                        );

                        $parentId = $parentRecord->id;
                    }
                }

                // Update QuickBooks-specific info
                $localAccount->code = $account['Id'] ?? '';
                $localAccount->parent = $parentId;
                $localAccount->description = $account['AccountType'] ?? null;
                $localAccount->is_enabled = 1;
                $localAccount->save();

                $importedCount++;
            }

            return response()->json([
                'status' => 'success',
                'count' => $allAccounts->count(),
                'imported' => $importedCount,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }
    private function ensureChartOfAccount($fullName, $distributionAccountType, $detailType = 'Other', $qbAccountData = null)
    {
        // 🔹 Map QuickBooks account types to your system's main account categories
        $typeMapping = [
            // Liabilities
            'accounts payable (a/p)' => 'Liabilities',
            'accounts payable' => 'Liabilities',
            'credit card' => 'Liabilities',
            'long term liabilities' => 'Liabilities',
            'other current liabilities' => 'Liabilities',
            'loan payable' => 'Liabilities',
            'notes payable' => 'Liabilities',
            'board of equalization payable' => 'Liabilities',
            'Other Current Liability' => 'Liabilities',
            'Liability' => 'Liabilities',
            'liability' => 'Liabilities',

            // Assets
            'accounts receivable (a/r)' => 'Assets',
            'accounts receivable' => 'Assets',
            'bank' => 'Assets',
            'checking' => 'Assets',
            'savings' => 'Assets',
            'undeposited funds' => 'Assets',
            'inventory asset' => 'Assets',
            'other current assets' => 'Assets',
            'fixed assets' => 'Assets',
            'truck' => 'Assets',
            'Asset' => 'Assets',
            'asset' => 'Assets',
            'Other Current Asset' => 'Assets',

            // Equity
            'equity' => 'Equity',
            'opening balance equity' => 'Equity',
            'retained earnings' => 'Equity',
            'equity' => 'Equity',
            'Equity' => 'Equity',

            // Income
            'income' => 'Income',
            'other income' => 'Income',
            'sales of product income' => 'Income',
            'service/fee income' => 'Income',
            'sales' => 'Income',
            'revenue' => 'Income',
            'Revenue' => 'Income',

            // COGS
            'cost of goods sold' => 'Costs of Goods Sold',
            'cogs' => 'Costs of Goods Sold',

            // Expenses
            'expenses' => 'Expenses',
            'expense' => 'Expenses',
            'Expense' => 'Expenses',
            'other expense' => 'Expenses',
            'marketing' => 'Expenses',
            'insurance' => 'Expenses',
            'utilities' => 'Expenses',
            'rent or lease' => 'Expenses',
            'meals and entertainment' => 'Expenses',
            'bank charges' => 'Expenses',
            'depreciation' => 'Expenses',
        ];

        $typeName = strtolower(trim($distributionAccountType));
        $creatorId = \Auth::user()->creatorId();

        if (!isset($typeMapping[$typeName])) {
            \Log::warning("Unmapped QuickBooks type: '{$distributionAccountType}' for account '{$fullName}'");
            dd($qbAccountData);
            return null; // Skip unmapped
        }

        // 🏷️ Create/find ChartOfAccountType
        $systemTypeName = $typeMapping[$typeName];
        $type = ChartOfAccountType::firstOrCreate(
            ['name' => $systemTypeName, 'created_by' => $creatorId]
        );

        // 🧩 Create/find SubType
        $subType = ChartOfAccountSubType::firstOrCreate(
            [
                'type' => $type->id,
                'name' => $detailType ?: 'Other',
                'created_by' => $creatorId,
            ]
        );

        // 🧾 Create/find ChartOfAccount
        $account = ChartOfAccount::firstOrCreate(
            [
                'name' => $fullName,
                'code' => $qbAccountData['Id'] ?? '',
                'description' => $qbAccountData['AccountType'] ?? null,
                'type' => $type->id,
                'sub_type' => $subType->id,
                'created_by' => $creatorId,
            ]
        );

        return $account;
    }
    private function mapProductAccounts($accountName, $qbAccountData = null)
    {
        $account = ChartOfAccount::where('name', $accountName)->first();
        if (!$account) {
            //create new custom account
            dd($accountName);
        }
        return $account;
    }
    public function items()
    {
        try {
            DB::beginTransaction();
            $allItems = collect();
            $startPosition = 1;
            $maxResults = 10;
            $importedCount = 0;

            do {
                // Fetch paginated batch
                $query = "SELECT * FROM Item STARTPOSITION {$startPosition} MAXRESULTS {$maxResults}";
                $itemsResponse = $this->qbController->runQuery($query);

                // Handle API errors
                if ($itemsResponse instanceof \Illuminate\Http\JsonResponse) {
                    return $itemsResponse;
                }

                // Get items from response
                $itemsData = $itemsResponse['QueryResponse']['Item'] ?? [];

                // Merge entire objects (keep all keys)
                $allItems = $allItems->merge($itemsData);

                // Move to next page
                $fetchedCount = count($itemsData);
                $startPosition += $fetchedCount;
            } while ($fetchedCount === $maxResults); // continue if page is full
            // dd($allItems);
            // Import items into ProductService
            $unit = ProductServiceUnit::firstOrCreate(
                ['name' => 'pcs'],
                ['created_by' => auth()->user()->creatorId() ?? 2] // optional
            );
            $productCategory = ProductServiceCategory::firstOrCreate(
                [
                    'name' => 'Product',
                    'created_by' => \Auth::user()->creatorId(),
                ],
                [
                    'color' => '#4CAF50',
                    'type' => 'Product',
                    'chart_account_id' => 0,
                    'created_by' => \Auth::user()->creatorId(),
                    'owned_by' => \Auth::user()->ownedId(),
                ]
            );

            $serviceCategory = ProductServiceCategory::firstOrCreate(
                [
                    'name' => 'Service',
                    'created_by' => \Auth::user()->creatorId(),
                ],
                [
                    'color' => '#2196F3',
                    'type' => 'Service',
                    'chart_account_id' => 0,
                    'created_by' => \Auth::user()->creatorId(),
                    'owned_by' => \Auth::user()->ownedId(),
                ]
            );

            // 🟡 Step 2: Store IDs for reuse
            $productCategoryId = $productCategory->id;
            $serviceCategoryId = $serviceCategory->id;

            foreach ($allItems as $item) {
                $isInventory = strtolower($item['Type'] ?? '') === 'inventory';

                // Determine values based on type
                $type = $isInventory ? 'product' : 'service';
                $categoryId = $isInventory ? $productCategoryId : $serviceCategoryId;
                $productData = [
                    'name' => $item['Name'] ?? '',
                    'sku' => $item['Name'] ?? '',
                    'sale_price' => $item['UnitPrice'] ?? 0,
                    'purchase_price' => $item['PurchaseCost'] ?? 0,
                    'quantity' => $item['QtyOnHand'] ?? 0,
                    'unit_id' => $unit->id ?? 1,
                    'type' => $type,
                    'category_id' => $categoryId,
                    'created_by' => auth()->user()->creatorId(),
                ];

                // Map chart accounts if available
                // Map chart accounts if available (with database mapping)
                if (isset($item['IncomeAccountRef']['name'])) {
                    $incomeName = $item['IncomeAccountRef']['name'];
                    $incomeAccount = $this->mapProductAccounts($incomeName);
                    $productData['sale_chartaccount_id'] = $incomeAccount ? $incomeAccount->id : null;
                }

                if (isset($item['ExpenseAccountRef']['name'])) {
                    $expenseName = $item['ExpenseAccountRef']['name'];
                    $expenseAccount = $this->mapProductAccounts($expenseName);
                    $productData['expense_chartaccount_id'] = $expenseAccount ? $expenseAccount->id : null;
                }
                if (isset($item['AssetAccountRef']['name'])) {
                    $assetName = $item['AssetAccountRef']['name'];
                    $assetAccount = $this->mapProductAccounts($assetName);
                    $productData['asset_chartaccount_id'] = $assetAccount ? $assetAccount->id : null;
                }
                if (isset($item['COGSAccountRef']['name'])) {
                    $cogsName = $item['COGSAccountRef']['name'];
                    $cogsAccount = $this->mapProductAccounts($cogsName);
                    $productData['cogs_chartaccount_id'] = $cogsAccount ? $cogsAccount->id : null;
                }
                // dd($productData,$item);
                // Use updateOrCreate to avoid duplicates (based on name and created_by)
                ProductService::updateOrCreate(
                    ['name' => $productData['name'], 'created_by' => $productData['created_by']],
                    $productData
                );
                DB::commit();
                $importedCount++;
            }

            return response()->json([
                'status' => 'success',
                'count' => $allItems->count(),
                'imported' => $importedCount,
                'data' => $allItems->values(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            dd($e);
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    // public function importExpenses(Request $request)
    // {
    //     try {
    //         // Fetch all expenses with pagination
    //         $allExpenses = collect();
    //         $startPosition = 1;
    //         $maxResults = 50; // Adjust batch size as needed

    //         do {
    //             // Fetch paginated batch
    //             $query = "SELECT * FROM Purchase STARTPOSITION {$startPosition} MAXRESULTS {$maxResults}";
    //             $expensesResponse = $this->qbController->runQuery($query);

    //             // Handle API errors
    //             if ($expensesResponse instanceof \Illuminate\Http\JsonResponse) {
    //                 return $expensesResponse;
    //             }

    //             // Get expenses from response
    //             $expensesData = $expensesResponse['QueryResponse']['Purchase'] ?? [];

    //             // Merge entire objects (keep all keys)
    //             $allExpenses = $allExpenses->merge($expensesData);

    //             // Move to next page
    //             $fetchedCount = count($expensesData);
    //             $startPosition += $fetchedCount;
    //         } while ($fetchedCount === $maxResults); // continue if page is full

    //         // Fetch all expense payments with pagination (using the same logic as expensesWithPayments)
    //         $allExpensePayments = collect();
    //         $typesToQuery = [
    //             'Payment',
    //             'Check',
    //             'BillPayment',
    //             'CreditCardCredit',
    //             'VendorCredit',
    //             'Deposit',
    //             'Purchase', // include as candidate payment
    //         ];

    //         foreach ($typesToQuery as $type) {
    //             try {
    //                 $startPosition = 1;
    //                 do {
    //                     $query = "SELECT * FROM {$type} STARTPOSITION {$startPosition} MAXRESULTS {$maxResults}";
    //                     $paymentsResponse = $this->qbController->runQuery($query);

    //                     if ($paymentsResponse instanceof \Illuminate\Http\JsonResponse) {
    //                         continue;
    //                     }

    //                     $paymentsData = $paymentsResponse['QueryResponse'][$type] ?? [];
    //                     $allExpensePayments = $allExpensePayments->merge(collect($paymentsData));

    //                     $fetchedCount = count($paymentsData);
    //                     $startPosition += $fetchedCount;
    //                 } while ($fetchedCount === $maxResults);
    //             } catch (\Exception $e) {
    //                 \Log::warning("Failed to fetch {$type}: " . $e->getMessage());
    //             }
    //         }

    //         // Fetch items and accounts (these are usually smaller datasets)
    //         $itemsRaw = $this->qbController->runQuery("SELECT * FROM Item STARTPOSITION 1 MAXRESULTS 500");
    //         $accountsRaw = $this->qbController->runQuery("SELECT * FROM Account STARTPOSITION 1 MAXRESULTS 500");

    //         $itemsList = collect($itemsRaw['QueryResponse']['Item'] ?? []);
    //         $accountsList = collect($accountsRaw['QueryResponse']['Account'] ?? []);

    //         $itemsMap = $itemsList->keyBy(fn($it) => $it['Id'] ?? null)->toArray();
    //         $accountsMap = $accountsList->keyBy(fn($a) => $a['Id'] ?? null)->toArray();

    //         // Helper functions as in the original
    //         $findAPAccount = function () use ($accountsList) {
    //             $ap = $accountsList->first(fn($a) => isset($a['AccountType']) && strcasecmp($a['AccountType'], 'AccountsPayable') === 0);
    //             if ($ap)
    //                 return ['Id' => $ap['Id'], 'Name' => $ap['Name'] ?? null];
    //             $ap = $accountsList->first(fn($a) => stripos($a['Name'] ?? '', 'payable') !== false);
    //             return $ap ? ['Id' => $ap['Id'], 'Name' => $ap['Name'] ?? null] : null;
    //         };

    //         $apAccount = $findAPAccount();

    //         $detectAccountForExpenseItem = function ($sid) use ($itemsMap, $accountsMap) {
    //             if (!empty($sid['AccountRef']['value'])) {
    //                 return [
    //                     'AccountId' => $sid['AccountRef']['value'],
    //                     'AccountName' => $sid['AccountRef']['name'] ?? ($accountsMap[$sid['AccountRef']['value']]['Name'] ?? null)
    //                 ];
    //             }
    //             if (!empty($sid['ItemRef']['value'])) {
    //                 $itemId = $sid['ItemRef']['value'];
    //                 $item = $itemsMap[$itemId] ?? null;
    //                 if ($item) {
    //                     if (!empty($item['ExpenseAccountRef']['value'])) {
    //                         return ['AccountId' => $item['ExpenseAccountRef']['value'], 'AccountName' => $item['ExpenseAccountRef']['name'] ?? ($accountsMap[$item['ExpenseAccountRef']['value']]['Name'] ?? null)];
    //                     }
    //                     if (!empty($item['AssetAccountRef']['value'])) {
    //                         return ['AccountId' => $item['AssetAccountRef']['value'], 'AccountName' => $item['AssetAccountRef']['name'] ?? ($accountsMap[$item['AssetAccountRef']['value']]['Name'] ?? null)];
    //                     }
    //                 }
    //             }
    //             return ['AccountId' => null, 'AccountName' => null];
    //         };

    //         $parseExpenseLine = function ($line) use ($detectAccountForExpenseItem, $itemsMap, $accountsMap) {
    //             $out = [];
    //             $detailType = $line['DetailType'] ?? null;

    //             if (!empty($line['GroupLineDetail']) && !empty($line['GroupLineDetail']['Line'])) {
    //                 foreach ($line['GroupLineDetail']['Line'] as $child) {
    //                     if (!empty($child['ItemBasedExpenseLineDetail'])) {
    //                         $sid = $child['ItemBasedExpenseLineDetail'];
    //                         $acc = $detectAccountForExpenseItem($sid);
    //                         $out[] = [
    //                             'DetailType' => $child['DetailType'] ?? 'ItemBasedExpenseLineDetail',
    //                             'Description' => $child['Description'] ?? $sid['ItemRef']['name'] ?? null,
    //                             'Amount' => $child['Amount'] ?? 0,
    //                             'AccountId' => $acc['AccountId'],
    //                             'AccountName' => $acc['AccountName'],
    //                             'RawLine' => $child,
    //                             'HasProduct' => true,
    //                         ];
    //                     } elseif (!empty($child['AccountBasedExpenseLineDetail'])) {
    //                         $accDetail = $child['AccountBasedExpenseLineDetail'];
    //                         $out[] = [
    //                             'DetailType' => $child['DetailType'] ?? 'AccountBasedExpenseLineDetail',
    //                             'Description' => $child['Description'] ?? null,
    //                             'Amount' => $child['Amount'] ?? 0,
    //                             'AccountId' => $accDetail['AccountRef']['value'] ?? null,
    //                             'AccountName' => $accDetail['AccountRef']['name'] ?? null,
    //                             'RawLine' => $child,
    //                             'HasProduct' => false,
    //                         ];
    //                     } else {
    //                         $out[] = [
    //                             'DetailType' => $child['DetailType'] ?? null,
    //                             'Description' => $child['Description'] ?? null,
    //                             'Amount' => $child['Amount'] ?? 0,
    //                             'AccountId' => null,
    //                             'AccountName' => null,
    //                             'RawLine' => $child,
    //                             'HasProduct' => false,
    //                         ];
    //                     }
    //                 }
    //                 return $out;
    //             }

    //             if (!empty($line['ItemBasedExpenseLineDetail'])) {
    //                 $sid = $line['ItemBasedExpenseLineDetail'];
    //                 $acc = $detectAccountForExpenseItem($sid);
    //                 $out[] = [
    //                     'DetailType' => $line['DetailType'] ?? 'ItemBasedExpenseLineDetail',
    //                     'Description' => $line['Description'] ?? ($sid['ItemRef']['name'] ?? null),
    //                     'Amount' => $line['Amount'] ?? 0,
    //                     'AccountId' => $acc['AccountId'],
    //                     'AccountName' => $acc['AccountName'],
    //                     'RawLine' => $line,
    //                     'HasProduct' => true,
    //                 ];
    //                 return $out;
    //             }

    //             if (!empty($line['AccountBasedExpenseLineDetail'])) {
    //                 $accDetail = $line['AccountBasedExpenseLineDetail'];
    //                 $out[] = [
    //                     'DetailType' => $line['DetailType'] ?? 'AccountBasedExpenseLineDetail',
    //                     'Description' => $line['Description'] ?? null,
    //                     'Amount' => $line['Amount'] ?? 0,
    //                     'AccountId' => $accDetail['AccountRef']['value'] ?? null,
    //                     'AccountName' => $accDetail['AccountRef']['name'] ?? null,
    //                     'RawLine' => $line,
    //                     'HasProduct' => false,
    //                 ];
    //                 return $out;
    //             }

    //             $out[] = [
    //                 'DetailType' => $detailType,
    //                 'Description' => $line['Description'] ?? null,
    //                 'Amount' => $line['Amount'] ?? 0,
    //                 'AccountId' => null,
    //                 'AccountName' => null,
    //                 'RawLine' => $line,
    //                 'HasProduct' => false,
    //             ];
    //             return $out;
    //         };

    //         // Helper: Extract & normalize LinkedTxn entries robustly
    //         $extractLinkedTxn = function ($raw) {
    //             $linked = [];

    //             // 1) Top-level LinkedTxn
    //             if (!empty($raw['LinkedTxn']) && is_array($raw['LinkedTxn'])) {
    //                 $linked = array_merge($linked, $raw['LinkedTxn']);
    //             }

    //             // 2) Inside Line[].LinkedTxn
    //             if (!empty($raw['Line']) && is_array($raw['Line'])) {
    //                 $fromLines = collect($raw['Line'])
    //                     ->pluck('LinkedTxn')
    //                     ->flatten(1)
    //                     ->filter()
    //                     ->values()
    //                     ->toArray();
    //                 $linked = array_merge($linked, $fromLines);
    //             }

    //             // 3) Apply / ApplyTo / AppliedToTxn / ApplyToTxn - common alternative names
    //             if (!empty($raw['Apply']) && is_array($raw['Apply'])) {
    //                 $linked = array_merge($linked, $raw['Apply']);
    //             }
    //             if (!empty($raw['AppliedToTxn']) && is_array($raw['AppliedToTxn'])) {
    //                 $linked = array_merge($linked, $raw['AppliedToTxn']);
    //             }

    //             // 4) Also check for shapes like ['TxnId'] / ['Id'] pairs directly on the raw (rare)
    //             if (isset($raw['TxnId']) && isset($raw['TxnType'])) {
    //                 $linked[] = ['TxnId' => $raw['TxnId'], 'TxnType' => $raw['TxnType']];
    //             }

    //             // Normalize each entry to have TxnId and TxnType keys (when possible)
    //             $normalized = [];
    //             foreach ($linked as $l) {
    //                 if (!is_array($l))
    //                     continue;

    //                 // possible keys in different shapes
    //                 $txnId = $l['TxnId'] ?? $l['Id'] ?? $l['AppliedToTxnId'] ?? $l['AppliedToTxnId'] ?? null;
    //                 $txnType = $l['TxnType'] ?? $l['TxnTypeName'] ?? $l['Type'] ?? $l['TxnType'] ?? null;

    //                 // some shapes use 'TxnId' numeric etc. cast to string for consistent comparison
    //                 if ($txnId !== null) {
    //                     $normalized[] = [
    //                         'TxnId' => (string) $txnId,
    //                         'TxnType' => $txnType ? (string) $txnType : null,
    //                     ];
    //                 }
    //             }

    //             // dedupe
    //             $unique = [];
    //             foreach ($normalized as $n) {
    //                 $key = ($n['TxnId'] ?? '') . '|' . ($n['TxnType'] ?? '');
    //                 if (!isset($unique[$key]))
    //                     $unique[$key] = $n;
    //             }

    //             return array_values($unique);
    //         };

    //         // Helper: detect payment account and vendor info
    //         $detectPaymentAccount = function ($raw) {
    //             if (!empty($raw['CreditCardPayment']['CCAccountRef']))
    //                 return $raw['CreditCardPayment']['CCAccountRef'];
    //             if (!empty($raw['CheckPayment']['BankAccountRef']))
    //                 return $raw['CheckPayment']['BankAccountRef'];
    //             if (!empty($raw['BankAccountRef']))
    //                 return $raw['BankAccountRef'];
    //             if (!empty($raw['PayFromAccountRef']))
    //                 return $raw['PayFromAccountRef'];
    //             if (!empty($raw['DepositToAccountRef']))
    //                 return $raw['DepositToAccountRef'];
    //             if (!empty($raw['CCAccountRef']))
    //                 return $raw['CCAccountRef'];
    //             if (!empty($raw['AccountRef']))
    //                 return $raw['AccountRef'];
    //             return null;
    //         };

    //         // Normalize all payments
    //         $normalizedPayments = $allExpensePayments->map(function ($raw) use ($extractLinkedTxn, $detectPaymentAccount) {
    //             // vendor detection
    //             $vendorId = $raw['VendorRef']['value'] ?? $raw['EntityRef']['value'] ?? $raw['PayeeRef']['value'] ?? $raw['CustomerRef']['value'] ?? null;
    //             $vendorName = $raw['VendorRef']['name'] ?? $raw['EntityRef']['name'] ?? $raw['PayeeRef']['name'] ?? $raw['CustomerRef']['name'] ?? null;

    //             $paymentAccount = $detectPaymentAccount($raw);

    //             $total = $raw['TotalAmt'] ?? $raw['Amount'] ?? $raw['TotalAmount'] ?? null;

    //             return [
    //                 'Raw' => $raw,
    //                 'PaymentId' => $raw['Id'] ?? ($raw['PaymentId'] ?? null),
    //                 'TxnTypeRaw' => $raw['TxnType'] ?? null,
    //                 'TxnDate' => $raw['TxnDate'] ?? null,
    //                 'DocNumber' => $raw['DocNumber'] ?? null,
    //                 'TotalAmount' => $total !== null ? (float) $total : null,
    //                 'PaymentAccount' => $paymentAccount ? [
    //                     'Id' => $paymentAccount['value'] ?? null,
    //                     'Name' => $paymentAccount['name'] ?? null,
    //                 ] : null,
    //                 'VendorId' => $vendorId ? (string) $vendorId : null,
    //                 'VendorName' => $vendorName ?? null,
    //                 'LinkedTxn' => $extractLinkedTxn($raw),
    //             ];
    //         })->values();

    //         // Normalize expenses
    //         $expenses = $allExpenses->map(function ($expense) use ($parseExpenseLine) {
    //             $parsedLines = [];
    //             foreach ($expense['Line'] ?? [] as $line) {
    //                 $parsedLines = array_merge($parsedLines, $parseExpenseLine($line));
    //             }

    //             $mainAccount = null;
    //             if (!empty($expense['AccountRef'])) {
    //                 $mainAccount = [
    //                     'Id' => $expense['AccountRef']['value'] ?? null,
    //                     'Name' => $expense['AccountRef']['name'] ?? null,
    //                 ];
    //             }

    //             return [
    //                 'ExpenseId' => $expense['Id'] ?? null,
    //                 'VendorName' => $expense['VendorRef']['name'] ?? ($expense['EntityRef']['name'] ?? null),
    //                 'VendorId' => $expense['VendorRef']['value'] ?? ($expense['EntityRef']['value'] ?? null),
    //                 'TxnDate' => $expense['TxnDate'] ?? null,
    //                 'TotalAmount' => (float) ($expense['TotalAmt'] ?? ($expense['Amount'] ?? 0)),
    //                 'Currency' => $expense['CurrencyRef']['name'] ?? null,
    //                 'Memo' => $expense['Memo'] ?? null,
    //                 'MainAccount' => $mainAccount,
    //                 'ParsedLines' => $parsedLines,
    //                 'Payments' => [],
    //                 'RawExpense' => $expense,
    //             ];
    //         });

    //         // Link payments to expenses (explicit LinkedTxn) + fuzzy fallback
    //         $expensesWithPayments = $expenses->map(function ($exp) use ($normalizedPayments) {
    //             // exact matches by LinkedTxn
    //             $linkedExact = $normalizedPayments->filter(function ($p) use ($exp) {
    //                 if (empty($p['LinkedTxn']))
    //                     return false;
    //                 return collect($p['LinkedTxn'])->contains(function ($txn) use ($exp) {
    //                     if (empty($txn['TxnId']))
    //                         return false;
    //                     // match by TxnId (type may vary or be null) — string compare
    //                     return (string) $txn['TxnId'] === (string) $exp['ExpenseId'];
    //                 });
    //             })->values();

    //             $exp['Payments'] = $linkedExact;
    //             return $exp;
    //         });

    //         // Now, import logic - use Bill table for expenses
    //         $imported = 0;
    //         $skipped = 0;
    //         $failed = 0;
    //         dd($expensesWithPayments,$expensesWithPayments->last());
    //         DB::beginTransaction();
    //         try {
    //             foreach ($expensesWithPayments as $qbExpense) {
    //                 $qbId = $qbExpense['ExpenseId'];

    //                 // Check for duplicate
    //                 $existing = Bill::where('bill_id', $qbId)->first();
    //                 if ($existing) {
    //                     $skipped++;
    //                     continue;
    //                 }

    //                 // Map vendor_id - find local vendor by name from QuickBooks
    //                 $vendorName = $qbExpense['VendorName'];
    //                 $vendor = Vender::where('name', $vendorName)
    //                     ->where('created_by', \Auth::user()->creatorId())
    //                     ->first();

    //                 if (!$vendor) {
    //                     // Skip this expense if vendor doesn't exist in local DB
    //                     $skipped++;
    //                     continue;
    //                 }

    //                 $vendorId = $vendor->id;

    //                 // Insert expense as bill (type = 'Expense')
    //                 $bill = Bill::create([
    //                     'bill_id' => $qbId ?: 0, // Generate unique ID if QB ID is missing
    //                     'vender_id' => $vendorId,
    //                     'bill_date' => $qbExpense['TxnDate'],
    //                     'due_date' => $qbExpense['TxnDate'], // Same as bill date for expenses
    //                     'order_number' => $qbId, // Use expense ID as order number
    //                     'status' => 3, // default
    //                     'created_by' => \Auth::user()->creatorId(),
    //                     'owned_by' => \Auth::user()->ownedId(),
    //                     'type' => 'Expense', // Mark as expense type
    //                     'user_type' => 'Vendor'
    //                 ]);

    //                 // Process lines: products vs accounts
    //                 foreach ($qbExpense['ParsedLines'] as $line) {
    //                     if (empty($line['AccountId']))
    //                         continue; // Skip unmapped

    //                     if ($line['HasProduct']) {
    //                         // This is a product line - insert into bill_products
    //                         $itemName = $line['RawLine']['ItemBasedExpenseLineDetail']['ItemRef']['name'] ?? null;
    //                         if (!$itemName) continue;

    //                         $product = ProductService::where('name', $itemName)
    //                             ->where('created_by', \Auth::user()->creatorId())
    //                             ->first();

    //                         if (!$product) {
    //                             // Create product if it doesn't exist
    //                             $unit = ProductServiceUnit::firstOrCreate(
    //                                 ['name' => 'pcs'],
    //                                 ['created_by' => \Auth::user()->creatorId()]
    //                             );

    //                             $productCategory = ProductServiceCategory::firstOrCreate(
    //                                 [
    //                                     'name' => 'Product',
    //                                     'created_by' => \Auth::user()->creatorId(),
    //                                 ],
    //                                 [
    //                                     'color' => '#4CAF50',
    //                                     'type' => 'Product',
    //                                     'chart_account_id' => 0,
    //                                     'created_by' => \Auth::user()->creatorId(),
    //                                     'owned_by' => \Auth::user()->ownedId(),
    //                                 ]
    //                             );

    //                             $productData = [
    //                                 'name' => $itemName,
    //                                 'sku' => $itemName,
    //                                 'sale_price' => 0,
    //                                 'purchase_price' => $line['Amount'] ?? 0,
    //                                 'quantity' => 0,
    //                                 'unit_id' => $unit->id,
    //                                 'type' => 'product',
    //                                 'category_id' => $productCategory->id,
    //                                 'created_by' => \Auth::user()->creatorId(),
    //                             ];

    //                             // Map chart accounts if available
    //                             if (!empty($line['AccountId'])) {
    //                                 $account = ChartOfAccount::where('code', $line['AccountId'])
    //                                     ->where('created_by', \Auth::user()->creatorId())
    //                                     ->first();
    //                                 if ($account) {
    //                                     $productData['expense_chartaccount_id'] = $account->id;
    //                                 }
    //                             }

    //                             $product = ProductService::create($productData);
    //                         }

    //                         BillProduct::create([
    //                             'bill_id' => $bill->id,
    //                             'product_id' => $product->id,
    //                             'quantity' => $line['RawLine']['ItemBasedExpenseLineDetail']['Qty'] ?? 1,
    //                             'price' => $line['Amount'],
    //                             'description' => $line['Description'],
    //                             // tax, discount as needed
    //                         ]);
    //                     } else {
    //                         // This is an account line - insert into bill_accounts
    //                         $account = ChartOfAccount::where('code', $line['AccountId'])
    //                             ->where('created_by', \Auth::user()->creatorId())
    //                             ->first();

    //                         if ($account) {
    //                             BillAccount::create([
    //                                 'chart_account_id' => $account->id,
    //                                 'price' => $line['Amount'],
    //                                 'description' => $line['Description'],
    //                                 'type' => 'Expense',
    //                                 'ref_id' => $bill->id,
    //                             ]);
    //                         }
    //                     }
    //                 }

    //                 // Insert payments
    //                 foreach ($qbExpense['Payments'] as $payment) {
    //                     // Determine payment method based on payment data
    //                     $paymentMethod = $payment['TxnTypeRaw'] ?? 'Cash';

    //                     // Map account_id from QuickBooks payment account
    //                     $accountId = 0; // Default to 0
    //                     if ($payment['PaymentAccount'] && isset($payment['PaymentAccount']['Id'])) {
    //                         $qbAccountId = $payment['PaymentAccount']['Id'];
    //                         $localAccount = ChartOfAccount::where('code', $qbAccountId)
    //                             ->where('created_by', \Auth::user()->creatorId())
    //                             ->first();
    //                         if ($localAccount) {
    //                             $accountId = $localAccount->id;
    //                         }
    //                     }

    //                     BillPayment::create([
    //                         'bill_id' => $bill->id,
    //                         'date' => $payment['TxnDate'],
    //                         'amount' => $payment['TotalAmount'],
    //                         'account_id' => $accountId,
    //                         'payment_method' => $paymentMethod,
    //                         'reference' => $payment['PaymentId'],
    //                         'description' => 'QuickBooks Expense Payment',
    //                     ]);
    //                 }

    //                 if($qbExpense['Payments']->isNotEmpty()){
    //                     $bill->status = 4;
    //                     $bill->send_date = $qbExpense['TxnDate'];
    //                     $bill->save();
    //                 }

    //                 $imported++;
    //             }

    //             DB::commit();
    //         } catch (\Exception $e) {
    //             DB::rollBack();
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'Import failed: ' . $e->getMessage(),
    //             ], 500);
    //         }

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => "Expenses import completed. Imported: {$imported}, Skipped: {$skipped}, Failed: {$failed}",
    //             'imported' => $imported,
    //             'skipped' => $skipped,
    //             'failed' => $failed,
    //         ]);

    //     } catch (\Exception $e) {
    //         dd($e);
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Error: ' . $e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function importExpenses(Request $request)
    {
        try {
            // Fetch expenses with payments using existing function
            $response = $this->qbController->expensesWithPayments();

            // Decode JsonResponse safely
            if ($response instanceof \Illuminate\Http\JsonResponse) {
                $responseData = json_decode($response->getContent(), true);
            } else {
                $responseData = $response;
            }

            // Validate structure
            if (!is_array($responseData) || !isset($responseData['status']) || $responseData['status'] !== 'success') {
                return response()->json([
                    'status' => 'error',
                    'message' => $responseData['message'] ?? 'Failed to fetch expenses',
                ], 400);
            }

            // Now it's safe to access data
            $expensesData = collect($responseData['data'] ?? []);
            // dd($expensesData->first());
            // Fetch chart accounts for mapping
            $accountsRaw = $this->qbController->runQuery("SELECT * FROM Account STARTPOSITION 1 MAXRESULTS 500");
            $accountsList = collect($accountsRaw['QueryResponse']['Account'] ?? []);
            $accountsMap = $accountsList->keyBy(fn($a) => $a['Id'] ?? null)->toArray();

            // Counters
            $imported = 0;
            $skipped = 0;
            $failed = 0;

            DB::beginTransaction();
            try {
                foreach ($expensesData as $qbExpense) {
                    try {
                        $qbId = $qbExpense['ExpenseId'];

                        // Check for duplicate
                        $existing = Bill::where('bill_id', $qbId)->first();
                        if ($existing) {
                            $skipped++;
                            continue;
                        }

                        // Skip if no vendor (cannot link to system vendor)
                        $vendorName = $qbExpense['VendorName'] ?? null;
                        $vendor = null;

                        if ($vendorName) {
                            $vendor = Vender::where('name', $vendorName)
                                ->where('created_by', \Auth::user()->creatorId())
                                ->first();

                            if (!$vendor) {
                                $skipped++;
                                continue;
                            }
                        } else {
                            // Create generic vendor if none exists
                            $vendor = Vender::firstOrCreate(
                                [
                                    'name' => 'Generic Vendor',
                                    'created_by' => \Auth::user()->creatorId(),
                                ],
                                [
                                    'email' => 'generic@vendor.local',
                                    'created_by' => \Auth::user()->creatorId(),
                                    'owned_by' => \Auth::user()->ownedId(),
                                ]
                            );
                        }

                        $vendorId = $vendor->id;

                        // Create bill record
                        $bill = Bill::create([
                            'bill_id' => $qbId ?: 0,
                            'vender_id' => $vendorId,
                            'bill_date' => $qbExpense['TxnDate'],
                            'due_date' => $qbExpense['TxnDate'],
                            'order_number' => $qbId,
                            'status' => 3,
                            'created_by' => \Auth::user()->creatorId(),
                            'owned_by' => \Auth::user()->ownedId(),
                            'type' => 'Expense',
                            'user_type' => 'Vendor'
                        ]);

                        // Process expense accounts from ExpenseAccounts array
                        if (!empty($qbExpense['ExpenseAccounts']) && is_array($qbExpense['ExpenseAccounts'])) {
                            foreach ($qbExpense['ExpenseAccounts'] as $expenseAccount) {
                                $accountQbId = $expenseAccount['Id'] ?? null;

                                if (!$accountQbId)
                                    continue;

                                // Find local chart account by QB ID
                                $account = ChartOfAccount::where('code', $accountQbId)
                                    ->where('created_by', \Auth::user()->creatorId())
                                    ->first();

                                if (!$account) {
                                    // Try to find by name
                                    $account = ChartOfAccount::where('name', $expenseAccount['Name'] ?? '')
                                        ->where('created_by', \Auth::user()->creatorId())
                                        ->first();
                                }

                                if ($account) {
                                    BillAccount::create([
                                        'bill_id' => $bill->id,
                                        'chart_account_id' => $account->id,
                                        'price' => $expenseAccount['Amount'] ?? 0,
                                        'description' => $expenseAccount['Description'] ?? '',
                                        'type' => 'Expense',
                                        'ref_id' => $bill->id,
                                    ]);
                                }
                            }
                        }

                        // Process payments if exist
                        $payments = $qbExpense['Payments'] ?? null;
                        if ($payments) {
                            // Handle if it's a Collection or array
                            $paymentsArray = $payments instanceof \Illuminate\Support\Collection
                                ? $payments->toArray()
                                : (is_array($payments) ? $payments : []);

                            if (!empty($paymentsArray)) {
                                foreach ($paymentsArray as $payment) {
                                    // Map payment account
                                    $accountId = 0;
                                    if (!empty($payment['PaymentAccount']['Id'])) {
                                        $qbAccountId = $payment['PaymentAccount']['Id'];
                                        $localAccount = ChartOfAccount::where('code', $qbAccountId)
                                            ->where('created_by', \Auth::user()->creatorId())
                                            ->first();

                                        if (!$localAccount) {
                                            $localAccount = ChartOfAccount::where('name', $payment['PaymentAccount']['Name'] ?? '')
                                                ->where('created_by', \Auth::user()->creatorId())
                                                ->first();
                                        }

                                        if ($localAccount) {
                                            $accountId = $localAccount->id;
                                        }
                                    }

                                    // Determine payment method
                                    $paymentMethod = $payment['TxnTypeRaw'] ?? 'Other';
                                    if (isset($payment['Raw']['PaymentType'])) {
                                        $paymentMethod = $payment['Raw']['PaymentType'];
                                    }

                                    BillPayment::create([
                                        'bill_id' => $bill->id,
                                        'date' => $payment['TxnDate'] ?? $qbExpense['TxnDate'],
                                        'amount' => $payment['TotalAmount'] ?? 0,
                                        'account_id' => $accountId,
                                        'payment_method' => $paymentMethod,
                                        'reference' => $payment['PaymentId'],
                                        'description' => 'QB Expense Payment',
                                    ]);
                                }

                                // Mark as paid if payments exist
                                $bill->status = 4;
                                $bill->send_date = $qbExpense['TxnDate'];
                                $bill->save();
                            }
                        }

                        $imported++;

                    } catch (\Exception $e) {
                        \Log::error("Failed to import expense {$qbId}: " . $e->getMessage());
                        $failed++;
                        continue;
                    }
                }

                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error("Import transaction failed: " . $e->getMessage());
                throw $e;
            }

            return response()->json([
                'status' => 'success',
                'message' => "Import completed. Imported: {$imported}, Skipped: {$skipped}, Failed: {$failed}",
                'imported' => $imported,
                'skipped' => $skipped,
                'failed' => $failed,
            ]);

        } catch (\Exception $e) {
            \Log::error("Import expenses error: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function importBills(Request $request)
    {
        try {
            // Fetch all bills with pagination
            $allBills = collect();
            $startPosition = 1;
            $maxResults = 50; // Adjust batch size as needed

            do {
                // Fetch paginated batch
                $query = "SELECT * FROM Bill STARTPOSITION {$startPosition} MAXRESULTS {$maxResults}";
                $billsResponse = $this->qbController->runQuery($query);

                // Handle API errors
                if ($billsResponse instanceof \Illuminate\Http\JsonResponse) {
                    return $billsResponse;
                }

                // Get bills from response
                $billsData = $billsResponse['QueryResponse']['Bill'] ?? [];

                // Merge entire objects (keep all keys)
                $allBills = $allBills->merge($billsData);

                // Move to next page
                $fetchedCount = count($billsData);
                $startPosition += $fetchedCount;
            } while ($fetchedCount === $maxResults); // continue if page is full

            // Fetch all bill payments with pagination
            $allBillPayments = collect();
            $startPosition = 1;

            do {
                // Fetch paginated batch
                $query = "SELECT * FROM BillPayment STARTPOSITION {$startPosition} MAXRESULTS {$maxResults}";
                $billPaymentsResponse = $this->qbController->runQuery($query);

                // Handle API errors
                if ($billPaymentsResponse instanceof \Illuminate\Http\JsonResponse) {
                    return $billPaymentsResponse;
                }

                // Get bill payments from response
                $billPaymentsData = $billPaymentsResponse['QueryResponse']['BillPayment'] ?? [];

                // Merge entire objects (keep all keys)
                $allBillPayments = $allBillPayments->merge($billPaymentsData);

                // Move to next page
                $fetchedCount = count($billPaymentsData);
                $startPosition += $fetchedCount;
            } while ($fetchedCount === $maxResults); // continue if page is full

            // Fetch items and accounts (these are usually smaller datasets)
            $itemsRaw = $this->qbController->runQuery("SELECT * FROM Item STARTPOSITION 1 MAXRESULTS 500");
            $accountsRaw = $this->qbController->runQuery("SELECT * FROM Account STARTPOSITION 1 MAXRESULTS 500");

            $itemsList = collect($itemsRaw['QueryResponse']['Item'] ?? []);
            $accountsList = collect($accountsRaw['QueryResponse']['Account'] ?? []);

            $itemsMap = $itemsList->keyBy(fn($it) => $it['Id'] ?? null)->toArray();
            $accountsMap = $accountsList->keyBy(fn($a) => $a['Id'] ?? null)->toArray();

            // Helper functions as in the original
            $findAPAccount = function () use ($accountsList) {
                $ap = $accountsList->first(fn($a) => isset($a['AccountType']) && strcasecmp($a['AccountType'], 'AccountsPayable') === 0);
                if ($ap)
                    return ['Id' => $ap['Id'], 'Name' => $ap['Name'] ?? null];
                $ap = $accountsList->first(fn($a) => stripos($a['Name'] ?? '', 'payable') !== false);
                return $ap ? ['Id' => $ap['Id'], 'Name' => $ap['Name'] ?? null] : null;
            };

            $apAccount = $findAPAccount();

            $detectAccountForExpenseItem = function ($sid) use ($itemsMap, $accountsMap) {
                if (!empty($sid['AccountRef']['value'])) {
                    return [
                        'AccountId' => $sid['AccountRef']['value'],
                        'AccountName' => $sid['AccountRef']['name'] ?? ($accountsMap[$sid['AccountRef']['value']]['Name'] ?? null)
                    ];
                }
                if (!empty($sid['ItemRef']['value'])) {
                    $itemId = $sid['ItemRef']['value'];
                    $item = $itemsMap[$itemId] ?? null;
                    if ($item) {
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

            $parseBillLine = function ($line) use ($detectAccountForExpenseItem, $itemsMap, $accountsMap) {
                $out = [];
                $detailType = $line['DetailType'] ?? null;

                if (!empty($line['GroupLineDetail']) && !empty($line['GroupLineDetail']['Line'])) {
                    foreach ($line['GroupLineDetail']['Line'] as $child) {
                        if (!empty($child['ItemBasedExpenseLineDetail'])) {
                            $sid = $child['ItemBasedExpenseLineDetail'];
                            $acc = $detectAccountForExpenseItem($sid);
                            $out[] = [
                                'DetailType' => $child['DetailType'] ?? 'ItemBasedExpenseLineDetail',
                                'Description' => $child['Description'] ?? $sid['ItemRef']['name'] ?? null,
                                'Amount' => $child['Amount'] ?? 0,
                                'AccountId' => $acc['AccountId'],
                                'AccountName' => $acc['AccountName'],
                                'RawLine' => $child,
                                'HasProduct' => true,
                            ];
                        } elseif (!empty($child['AccountBasedExpenseLineDetail'])) {
                            $accDetail = $child['AccountBasedExpenseLineDetail'];
                            $out[] = [
                                'DetailType' => $child['DetailType'] ?? 'AccountBasedExpenseLineDetail',
                                'Description' => $child['Description'] ?? null,
                                'Amount' => $child['Amount'] ?? 0,
                                'AccountId' => $accDetail['AccountRef']['value'] ?? null,
                                'AccountName' => $accDetail['AccountRef']['name'] ?? null,
                                'RawLine' => $child,
                                'HasProduct' => false,
                            ];
                        } else {
                            $out[] = [
                                'DetailType' => $child['DetailType'] ?? null,
                                'Description' => $child['Description'] ?? null,
                                'Amount' => $child['Amount'] ?? 0,
                                'AccountId' => null,
                                'AccountName' => null,
                                'RawLine' => $child,
                                'HasProduct' => false,
                            ];
                        }
                    }
                    return $out;
                }

                if (!empty($line['ItemBasedExpenseLineDetail'])) {
                    $sid = $line['ItemBasedExpenseLineDetail'];
                    $acc = $detectAccountForExpenseItem($sid);
                    $out[] = [
                        'DetailType' => $line['DetailType'] ?? 'ItemBasedExpenseLineDetail',
                        'Description' => $line['Description'] ?? ($sid['ItemRef']['name'] ?? null),
                        'Amount' => $line['Amount'] ?? 0,
                        'AccountId' => $acc['AccountId'],
                        'AccountName' => $acc['AccountName'],
                        'RawLine' => $line,
                        'HasProduct' => true,
                    ];
                    return $out;
                }

                if (!empty($line['AccountBasedExpenseLineDetail'])) {
                    $accDetail = $line['AccountBasedExpenseLineDetail'];
                    $out[] = [
                        'DetailType' => $line['DetailType'] ?? 'AccountBasedExpenseLineDetail',
                        'Description' => $line['Description'] ?? null,
                        'Amount' => $line['Amount'] ?? 0,
                        'AccountId' => $accDetail['AccountRef']['value'] ?? null,
                        'AccountName' => $accDetail['AccountRef']['name'] ?? null,
                        'RawLine' => $line,
                        'HasProduct' => false,
                    ];
                    return $out;
                }

                $out[] = [
                    'DetailType' => $detailType,
                    'Description' => $line['Description'] ?? null,
                    'Amount' => $line['Amount'] ?? 0,
                    'AccountId' => null,
                    'AccountName' => null,
                    'RawLine' => $line,
                    'HasProduct' => false,
                ];
                return $out;
            };

            $bills = $allBills->map(function ($bill) use ($parseBillLine, $accountsMap, $apAccount) {
                $parsedLines = [];
                foreach ($bill['Line'] ?? [] as $line) {
                    $parsedLines = array_merge($parsedLines, $parseBillLine($line));
                }

                return [
                    'BillId' => (string) ($bill['Id'] ?? null),
                    'Id' => $bill['Id'] ?? null,
                    'DocNumber' => $bill['DocNumber'] ?? null,
                    'VendorName' => $bill['VendorRef']['name'] ?? null,
                    'VendorId' => $bill['VendorRef']['value'] ?? null,
                    'TxnDate' => $bill['TxnDate'] ?? null,
                    'DueDate' => $bill['DueDate'] ?? null,
                    'TotalAmount' => (float) ($bill['TotalAmt'] ?? 0),
                    'Balance' => $bill['Balance'] ?? 0,
                    'Currency' => $bill['CurrencyRef']['name'] ?? null,
                    'Payments' => [],
                    'ParsedLines' => $parsedLines,
                    'RawBill' => $bill,
                ];
            });

            $billPayments = $allBillPayments->map(function ($payment) {
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
                    'VendorId' => $payment['VendorRef']['value'] ?? null,
                    'VendorName' => $payment['VendorRef']['name'] ?? null,
                    'TxnDate' => $payment['TxnDate'] ?? null,
                    'TotalAmount' => $payment['TotalAmt'] ?? 0,
                    'PaymentMethod' => $payment['PayType'] ?? null,
                    'LinkedTxn' => $linked,
                    'RawPayment' => $payment,
                ];
            });

            $billsById = $bills->keyBy('BillId')->toArray();
            foreach ($billsById as $billId => &$bill) {
                $bill['Payments'] = collect($billPayments)->filter(function ($p) use ($billId) {
                    return collect($p['LinkedTxn'])->contains(fn($txn) => isset($txn['TxnType'], $txn['TxnId']) && strcasecmp($txn['TxnType'], 'Bill') === 0 && (string) $txn['TxnId'] === (string) $billId);
                })->values()->toArray();
            }
            $billsWithPayments = collect($billsById);
            // dd($billsWithPayments);
            // Now, import logic
            $imported = 0;
            $skipped = 0;
            $failed = 0;

            DB::beginTransaction();
            try {
                foreach ($billsWithPayments as $qbBill) {
                    $qbId = $qbBill['BillId'];

                    // Check for duplicate
                    $existing = Bill::where('bill_id', $qbId)->first();
                    if ($existing) {
                        $skipped++;
                        continue;
                    }

                    // Map vendor_id - find local vendor by name from QuickBooks
                    $vendorName = $qbBill['VendorName'];
                    $vendor = Vender::where('name', $vendorName)
                        ->where('created_by', \Auth::user()->creatorId())
                        ->first();

                    if (!$vendor) {
                        // Skip this bill if vendor doesn't exist in local DB
                        $skipped++;
                        continue;
                    }

                    $vendorId = $vendor->id;

                    // Insert bill
                    $bill = Bill::create([
                        'bill_id' => $qbId ?: 0, // Generate unique ID if QB ID is missing
                        'vender_id' => $vendorId,
                        'bill_date' => $qbBill['TxnDate'],
                        'due_date' => $qbBill['DueDate'],
                        'order_number' => $qbBill['DocNumber'] ?? 0,
                        'status' => 3, // default
                        'created_by' => \Auth::user()->creatorId(),
                        'owned_by' => \Auth::user()->ownedId(),
                        'type' => 'Bill',
                        'user_type' => 'Vendor'
                    ]);

                    // Process lines: products vs accounts
                    foreach ($qbBill['ParsedLines'] as $line) {
                        if (empty($line['AccountId']))
                            continue; // Skip unmapped

                        if ($line['HasProduct']) {
                            // This is a product line - insert into bill_products
                            $itemName = $line['RawLine']['ItemBasedExpenseLineDetail']['ItemRef']['name'] ?? null;
                            if (!$itemName)
                                continue;

                            $product = ProductService::where('name', $itemName)
                                ->where('created_by', \Auth::user()->creatorId())
                                ->first();

                            if (!$product) {
                                // Create product if it doesn't exist
                                $unit = ProductServiceUnit::firstOrCreate(
                                    ['name' => 'pcs'],
                                    ['created_by' => \Auth::user()->creatorId()]
                                );

                                $productCategory = ProductServiceCategory::firstOrCreate(
                                    [
                                        'name' => 'Product',
                                        'created_by' => \Auth::user()->creatorId(),
                                    ],
                                    [
                                        'color' => '#4CAF50',
                                        'type' => 'Product',
                                        'chart_account_id' => 0,
                                        'created_by' => \Auth::user()->creatorId(),
                                        'owned_by' => \Auth::user()->ownedId(),
                                    ]
                                );

                                $productData = [
                                    'name' => $itemName,
                                    'sku' => $itemName,
                                    'sale_price' => 0,
                                    'purchase_price' => $line['Amount'] ?? 0,
                                    'quantity' => 0,
                                    'unit_id' => $unit->id,
                                    'type' => 'product',
                                    'category_id' => $productCategory->id,
                                    'created_by' => \Auth::user()->creatorId(),
                                ];

                                // Map chart accounts if available
                                if (!empty($line['AccountId'])) {
                                    $account = ChartOfAccount::where('code', $line['AccountId'])
                                        ->where('created_by', \Auth::user()->creatorId())
                                        ->first();
                                    if ($account) {
                                        $productData['expense_chartaccount_id'] = $account->id;
                                    }
                                }

                                $product = ProductService::create($productData);
                            }

                            BillProduct::create([
                                'bill_id' => $bill->id,
                                'product_id' => $product->id,
                                'quantity' => $line['RawLine']['ItemBasedExpenseLineDetail']['Qty'] ?? 1,
                                'price' => $line['Amount'],
                                'description' => $line['Description'],
                                // tax, discount as needed
                            ]);
                        } else {
                            // This is an account line - insert into bill_accounts
                            $account = ChartOfAccount::where('code', $line['AccountId'])
                                ->where('created_by', \Auth::user()->creatorId())
                                ->first();

                            if ($account) {
                                BillAccount::create([
                                    'chart_account_id' => $account->id,
                                    'price' => $line['Amount'],
                                    'description' => $line['Description'],
                                    'type' => 'Bill',
                                    'ref_id' => $bill->id,
                                ]);
                            }
                        }
                    }

                    // Insert payments
                    foreach ($qbBill['Payments'] as $payment) {
                        // Determine payment method based on payment data
                        $paymentMethod = $payment['PaymentMethod'];

                        // If payment method is null, try to determine from payment type or account
                        if (!$paymentMethod) {
                            // Check if it's a check payment
                            if (isset($payment['RawPayment']['CheckPayment'])) {
                                $paymentMethod = 'Check';
                            }
                            // Check deposit account type
                            elseif (isset($payment['RawPayment']['PayFromAccountRef'])) {
                                $accountId = $payment['RawPayment']['PayFromAccountRef']['value'];
                                $account = collect($accountsList)->firstWhere('Id', $accountId);
                                if ($account) {
                                    $accountType = strtolower($account['AccountType'] ?? '');
                                    if (strpos($accountType, 'bank') !== false || strpos($accountType, 'checking') !== false) {
                                        $paymentMethod = 'Bank Transfer';
                                    } elseif (strpos($accountType, 'credit') !== false) {
                                        $paymentMethod = 'Credit Card';
                                    } else {
                                        $paymentMethod = 'Cash';
                                    }
                                } else {
                                    $paymentMethod = 'Cash'; // Default fallback
                                }
                            }
                            // Default to Cash if nothing else matches
                            else {
                                $paymentMethod = 'Cash';
                            }
                        }
                        // Map account_id from QuickBooks payment account using the same logic as billsWithPayments()
                        $accountId = 0; // Default to 0
                        $paymentAccount = null;
                        if (isset($payment['RawPayment']['CreditCardPayment']['CCAccountRef'])) {
                            $paymentAccount = $payment['RawPayment']['CreditCardPayment']['CCAccountRef'];
                        } elseif (isset($payment['RawPayment']['CheckPayment']['BankAccountRef'])) {
                            $paymentAccount = $payment['RawPayment']['CheckPayment']['BankAccountRef'];
                        } elseif (isset($payment['RawPayment']['PayFromAccountRef'])) {
                            $paymentAccount = $payment['RawPayment']['PayFromAccountRef'];
                        }

                        if ($paymentAccount && isset($paymentAccount['value'])) {
                            $qbAccountId = $paymentAccount['value'];
                            $localAccount = ChartOfAccount::where('code', $qbAccountId)
                                ->where('created_by', \Auth::user()->creatorId())
                                ->first();
                            if ($localAccount) {
                                $accountId = $localAccount->id;
                            }
                        }

                        BillPayment::create([
                            'bill_id' => $bill->id,
                            'date' => $payment['TxnDate'],
                            'amount' => $payment['TotalAmount'],
                            'account_id' => $accountId,
                            'payment_method' => $paymentMethod,
                            'reference' => $payment['PaymentId'],
                            'description' => 'QuickBooks Bill Payment',
                        ]);
                    }

                    if (!empty($qbBill['Payments'])) {
                        $bill->status = 4;
                        $bill->send_date = $qbBill['TxnDate'];
                        $bill->save();
                    }
                    $bill->save();

                    $imported++;
                }

                DB::commit();
            } catch (\Exception $e) {
                dd($e);
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Import failed: ' . $e->getMessage(),
                ], 500);
            }

            return response()->json([
                'status' => 'success',
                'message' => "Bills import completed. Imported: {$imported}, Skipped: {$skipped}, Failed: {$failed}",
                'imported' => $imported,
                'skipped' => $skipped,
                'failed' => $failed,
            ]);

        } catch (\Exception $e) {
            dd($e);
            return response()->json([
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
    // 📊 Fetch Journal Entries (with all available fields)
    public function journalReport(Request $request)
    {
        
            $companyId = $this->qbController->realmId();
            $accessToken = $this->qbController->accessToken();

            $accessToken = $this->qbController->accessToken(); // must return valid OAuth token
            $baseUrl = "{$this->qbController->baseUrl}/v3/company/{$companyId}";

            // Optional filters
            $startDate = now()->startOfYear()->toDateString();
            $endDate = now()->toDateString();
            $startDate = $request->input('start_date', '2010-6-06');
            $endDate   = $request->input('end_date', now()->format('Y-m-d'));
            $accountingMethod = $request->input('accounting_method', 'Accrual');

            // Build JournalReport API URL with date filters
            $url = "{$baseUrl}/reports/JournalReport?start_date={$startDate}&end_date={$endDate}&accounting_method={$accountingMethod}";

        // $url = "https://quickbooks.api.intuit.com/v3/company/{$companyId}/query?query=" . urlencode($query);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'Accept' => 'application/json',
            'Content-Type' => 'application/text'
        ])->get($url);

        if ($response->failed()) {
            return response()->json(['error' => $response->body()], 500);
        }

        $data = $response->json();

        // Process the report rows to group into journal entries
        $rows = $data['Rows']['Row'] ?? [];
        $groupedEntries = [];
        $entryBuffer = [];
        $currentDateValue = null;
        $entityType = null;
        $entityId = null;
        $name = null;
        $transtype = null;

        foreach ($rows as $row) {
            $type = $row['type'] ?? null;
            if ($type === 'Data') {
                // Collect data rows for one journal entry
                $colData = $row['ColData'] ?? [];
                $firstValue = $colData[0]['value'] ?? null;

                // Skip empty rows
                if (empty($firstValue) && empty($currentDateValue)) {
                    continue;
                }

                // If we haven't captured date yet, use this one
                if ($currentDateValue === null && !empty($firstValue)) {
                    $currentDateValue = $firstValue;
                }
                 $tratype = $colData[1]['value'] ?? null;
                if (!empty($tratype)) {
                    $transtype = $tratype;
                }else{
                    $colData[1]['value'] = $transtype;
                }

                $entityName = $colData[3]['value'] ?? null;

                if (!empty($entityName)) {
                    [$entityType, $entityId] = $this->mapQuickBooksEntity($entityName);
                    $name = $colData[3]['value'] ?? null;
                    // Add them as new keys in the same index
                    $colData[3]['emp_id'] = $entityId;
                    $colData[3]['type']   = $entityType;
                }else{
                    $colData[3]['value'] = $name;
                    $colData[3]['emp_id'] = $entityId;
                    $colData[3]['type']   = $entityType;
                }
                

                $colData[0]['value'] = $currentDateValue;

                // Add to group
                $entryBuffer[] = $colData;
                // $entryBuffer[] = $row['ColData'] ?? [];
            } elseif ($type === 'Section') {
                // "Section" means the current entry group has ended
                if (!empty($entryBuffer)) {
                    $groupedEntries[] = $entryBuffer;
                    $entryBuffer = [];
                    $currentDateValue = null;
                    $entityType = null;
                    $entityId = null;
                    $name = null;
                    $transtype = null;
                }
            } else {
                // Ignore rows without a recognized type
                continue;
            }
        }

        // Add any leftover data (in case the last entry doesn’t end with Section)
        if (!empty($entryBuffer)) {
            $groupedEntries[] = $entryBuffer;
        }
        // dd($groupedEntries);
        // Process any remaining entry
        $createdEntries = [];
        foreach ($groupedEntries as $entryData) {
            $createdEntry = $this->createJournalEntry($entryData);
            if ($createdEntry) {
                $createdEntries[] = $createdEntry;
            }
        }

        // Create journal entries from grouped data
        // $createdEntries = [];
        // foreach ($groupedEntries as $entryData) {
        //     $createdEntry = $this->createJournalEntry($entryData);
        //     if ($createdEntry) {
        //         $createdEntries[] = $createdEntry;
        //     }
        // }

        // Return success response
        return response()->json([
            'success' => true,
            'message' => 'Journal entries imported successfully',
            'imported_entries' => count($createdEntries),
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ]);
    }

    private function mapQuickBooksEntity($data)
    {
        $name = $data; // example: "Tania's Nursery"

        if (!empty($name)) {
            // Try to find in Vendors
            $vendor = Vender::where('name', $name)->first();
            if ($vendor) {
                 return ['vendor', $vendor->id];
            } else {
                // Try to find in Customers
                $customer = Customer::where('name', $name)->first();
                if ($customer) {
                     return ['customer', $customer->id];
                } else {
                    // Try to find in Employees
                    $employee = Employee::where('name', $name)->first();
                    if ($employee) {
                        return ['employee', $employee->id];
                    }
                }
            }
        }
        return [null, null];
    }


    private function createJournalEntry($entryData)
    {
        try {
            // Extract data from the first row (assuming it's the header row for the entry)
            $firstRow = $entryData[0] ?? [];
            $date = $firstRow[0]['value'] ?? now()->toDateString();
            $transactionType = $firstRow[1]['value'] ?? 'Journal';
            $num = $firstRow[2]['value'] ?? '';
            $name = $firstRow[3]['value'] ?? '';
            $memo = $firstRow[4]['value'] ?? '';
            $accountName = $firstRow[5]['value'] ?? '';
            $debit = $firstRow[6]['value'] ?? 0;
            $credit = $firstRow[7]['value'] ?? 0;
            $entityType = $firstRow[1]['value'] ?? '';
            $entityId = $firstRow[3]['emp_id'] ?? null;
            $entityName = $firstRow[3]['value'] ?? '';


            $totalDebit = 0;
            $totalCredit = 0;

            // Calculate totals from all rows
            foreach ($entryData as $row) {
                $debitVal = floatval($row[6]['value'] ?? 0);
                $creditVal = floatval($row[7]['value'] ?? 0);
                $totalDebit += $debitVal;
                $totalCredit += $creditVal;
            }

            if ($totalCredit != $totalDebit) {
                // Skip unbalanced entries
                return null;
            }

            $journal = new JournalEntry();
            $journal->journal_id = $this->journalNumber();
            $journal->date = date('Y-m-d', strtotime($date));
            $journal->reference = $num;
            $journal->description = $memo;
            $journal->created_by = Auth::user()->creatorId();
            $journal->owned_by = Auth::user()->ownedId();
            $journal->save();
            $journal->created_at = date('Y-m-d H:i:s', strtotime($date));
            $journal->updated_at = date('Y-m-d H:i:s', strtotime($date));
            $journal->save();

            foreach ($entryData as $row) {
                $accountName = $row[5]['value'] ?? '';
                $debit = floatval($row[6]['value'] ?? 0);
                $credit = floatval($row[7]['value'] ?? 0);
                $memo = $row[4]['value'] ?? '';

                $account = $this->ensureCOA($accountName);
                if (!$account) {
                    dd($accountName);
                    continue;
                }

                $journalItem = new JournalItem();
                $journalItem->journal = $journal->id;
                $journalItem->account = $account->id;
                $journalItem->description = $memo;
                $journalItem->debit = $debit;
                $journalItem->credit = $credit;
                $journalItem->type = $entityType;
                $journalItem->name = $entityName;
                if ($entityType === 'customer') {
                    $journalItem->customer_id = $entityId;
                } elseif ($entityType === 'vendor') {
                    $journalItem->vendor_id = $entityId;
                } elseif ($entityType === 'employee') {
                    $journalItem->employee_id = $entityId;
                }
                $journalItem->save();
                $journalItem->created_at = date('Y-m-d H:i:s', strtotime($date));
                $journalItem->updated_at = date('Y-m-d H:i:s', strtotime($date));
                $journalItem->save();

                $bankAccounts = BankAccount::where('chart_account_id', '=', $account->id)->get();
                if (!empty($bankAccounts)) {
                    foreach ($bankAccounts as $bankAccount) {
                        $old_balance = $bankAccount->opening_balance;
                        if ($journalItem->debit > 0) {
                            $new_balance = $old_balance - $journalItem->debit;
                        }
                        if ($journalItem->credit > 0) {
                            $new_balance = $old_balance + $journalItem->credit;
                        }
                        if (isset($new_balance)) {
                            $bankAccount->opening_balance = $new_balance;
                            $bankAccount->save();
                        }
                    }
                }

                if ($debit > 0) {
                    $data = [
                        'account_id' => $account->id,
                        'transaction_type' => 'Debit',
                        'transaction_amount' => $debit,
                        'reference' => 'Journal',
                        'reference_id' => $journal->id,
                        'reference_sub_id' => $journalItem->id,
                        'date' => $journal->date,
                    ];
                } elseif ($credit > 0) {
                    $data = [
                        'account_id' => $account->id,
                        'transaction_type' => 'Credit',
                        'transaction_amount' => $credit,
                        'reference' => 'Journal',
                        'reference_id' => $journal->id,
                        'reference_sub_id' => $journalItem->id,
                        'date' => $journal->date,
                    ];
                } else {
                    continue;
                }
                $this->addTransactionLines($data, 'create');
            }

            return $journal;
        } catch (\Exception $e) {
            // Log error and skip
            dd($e->getMessage());
            \Log::error('Error creating journal entry: ' . $e->getMessage());
            return null;
        }
    }
    public static function addTransactionLines($data, $action)
    {
        $existingTransaction = TransactionLines::where('reference_id', $data['reference_id'])
            ->where('reference_sub_id', $data['reference_sub_id'])->where('reference', $data['reference'])
            ->first();
        if ($existingTransaction && $action == 'edit') {
            $transactionLines = $existingTransaction;
        } else {
            $transactionLines = new TransactionLines();
        }
        $transactionLines->account_id = $data['account_id'];
        $transactionLines->reference = $data['reference'];
        $transactionLines->reference_id = $data['reference_id'];
        $transactionLines->reference_sub_id = $data['reference_sub_id'];
        $transactionLines->date = $data['date'];
        $transactionLines->product_id = @$data['product_id'] ?? @$transactionLines->product_id;
        $transactionLines->product_type = @$data['product_type'] ?? @$transactionLines->product_type;
        $transactionLines->product_item_id = @$data['product_item_id'] ?? @$transactionLines->product_item_id;
        if ($data['transaction_type'] == "Credit") {
            $transactionLines->credit = $data['transaction_amount'];
            $transactionLines->debit = 0;
        } else {
            $transactionLines->credit = 0;
            $transactionLines->debit = $data['transaction_amount'];
        }
        $transactionLines->created_by = Auth::user()->creatorId();
        $transactionLines->created_at = date('Y-m-d H:i:s', strtotime($data['date']));
        $transactionLines->updated_at = date('Y-m-d H:i:s', strtotime($data['date']));
        $transactionLines->save();
        $transactionLines->created_at = date('Y-m-d H:i:s', strtotime($data['date']));
        $transactionLines->updated_at = date('Y-m-d H:i:s', strtotime($data['date']));
        $transactionLines->save();
    }
    private function journalNumber()
    {
        $latest = JournalEntry::where('created_by', '=', Auth::user()->creatorId())->latest()->first();
        if (!$latest) {
            return 1;
        }
        return $latest->journal_id + 1;
    }

    private function ensureCOA($fullName)
    {
 
        $account = ChartOfAccount::where('name', $fullName)->where('created_by', Auth::user()->creatorId())->first();
        if (!$account) {
            $accountId = $this->getAccountIdByFullName($fullName);
           
            if($accountId){
                $account = ChartOfAccount::where('id', $accountId)->where('created_by', Auth::user()->creatorId())->first();
                return $account;
            }
            // dd($account);
          $typeMapping = [
                'accounts payable (a/p)' => 'Liabilities',
                'accounts payable' => 'Liabilities',
                'credit card' => 'Liabilities',
                'long term liabilities' => 'Liabilities',
                'other current liabilities' => 'Liabilities',
                'loan payable' => 'Liabilities',
                'notes payable' => 'Liabilities',
                'board of equalization payable' => 'Liabilities',
                'arizona dept. of revenue payable' => 'Liabilities',
                'accounts receivable (a/r)' => 'Assets',
                'accounts receivable' => 'Assets',
                'bank' => 'Assets',
                'checking' => 'Assets',
                'savings' => 'Assets',
                'undeposited funds' => 'Assets',
                'inventory asset' => 'Assets',
                'other current assets' => 'Assets',
                'fixed assets' => 'Assets',
                'truck' => 'Assets',
                'equity' => 'Equity',
                'opening balance equity' => 'Equity',
                'retained earnings' => 'Equity',
                'income' => 'Income',
                'other income' => 'Income',
                'sales of product income' => 'Income',
                'service/fee income' => 'Income',
                'sales' => 'Income',
                'cost of goods sold' => 'Costs of Goods Sold',
                'cogs' => 'Costs of Goods Sold',
                'expenses' => 'Expenses',
                'expense' => 'Expenses',
                'other expense' => 'Expenses',
                'marketing' => 'Expenses',
                'insurance' => 'Expenses',
                'utilities' => 'Expenses',
                'rent or lease' => 'Expenses',
                'meals and entertainment' => 'Expenses',
                'bank charges' => 'Expenses',
                'depreciation' => 'Expenses',
            ];

            // Convert to lowercase for comparison
            $typeName = strtolower(trim($fullName));
            $systemTypeName = 'Other'; // Default
            $detailType = 'Other';

            // Check for exact match first
            if (isset($typeMapping[$typeName])) {
                $systemTypeName = $typeMapping[$typeName];
                $detailType = $typeName;
            } else {
                // Fuzzy match: if it contains the word "expense"
                foreach ($typeMapping as $key => $value) {
                    $typeName = str_replace([':', ',', '&', '|'], ' ', $typeName);
                    $typeName = preg_replace('/\s+/', ' ', trim($typeName));
                    if (str_contains($typeName, strtolower($key))) {
                        $systemTypeName = $value;
                        $detailType = $key;
                        break;
                    }
                }
                
                // Additional fallback: if the name itself contains "expense"
                // if (str_contains($typeName, 'expense')) {
                //     $systemTypeName = 'Expenses';
                // }
                    // $type = ChartOfAccountType::firstOrCreate(
                    //     ['name' => 'Other', 'created_by' => Auth::user()->creatorId()]
                    // );
                    // $subType = ChartOfAccountSubType::firstOrCreate([
                    //     'type' => $type->id,
                    //     'name' => 'Other',
                    //     'created_by' => Auth::user()->creatorId(),
                    // ]);
                    // $account = ChartOfAccount::create([
                    //     'name' => $fullName,
                    //     'type' => $type->id,
                    //     'sub_type' => $subType->id,
                    //     'created_by' => Auth::user()->creatorId(),
                    // ]);
                }       
                 $debugNames = [
                    'Legal & Professional Fees:Lawyer',
                    'Landscaping Services:Job Materials:Plants and Soil',
                    'Landscaping Services:Job Materials:Fountains and Garden Lighting',
                    'Legal & Professional Fees:Accounting',
                    'Landscaping Services:Job Materials:Sprinklers and Drip Systems',
                ];

                // if (!in_array($fullName, $debugNames)) {
                //     dd($fullName, $systemTypeName, $detailType, $typeName);
                // }
                // else{
                //     dd($fullName,$systemTypeName,$detailType,$typeName,$typeMapping,'sds');
                // }
                 $type = ChartOfAccountType::firstOrCreate(
                        ['name' => $systemTypeName, 'created_by' => Auth::user()->creatorId()]
                    );

                    $subType = ChartOfAccountSubType::firstOrCreate([
                        'type' => $type->id,
                        'name' => $detailType ?: 'Other',
                        'created_by' => Auth::user()->creatorId(),
                    ]);
                    $acct = ChartOfAccount::where('name', $fullName)
                            ->where('type', $type->id)
                            ->where('sub_type', $subType->id)
                            ->where('created_by', Auth::user()->creatorId())
                            ->first();
                if (!$acct) {
                    $account = ChartOfAccount::create([
                        'name' => $fullName,
                        'type' => $type->id,
                        'sub_type' => $subType->id,
                        'created_by' => Auth::user()->creatorId(),
                    ]);
                }


           
        }
        return $account;
    }

    public function getAccountIdByFullName($fullName)
    {
        $parts = explode(':', $fullName);
        $parentId = null;
        $account = null;
        foreach ($parts as $part) {
            $part = trim($part);
            ;

            if (is_null($parentId)) {
                // Find top-level account (no parent)
                $account = ChartOfAccount::where('name', $part)
                    ->where(function ($q) {
                        $q->whereNull('parent')->orWhere('parent', 0);
                    })
                    ->first();
                    // dd($part,$parts,$account);
            } else {
                // Find parent name first in chart_of_account_parents
                $parentRow = \DB::table('chart_of_account_parents')
                    ->where('id', $parentId)
                    ->first();

                // Now find next child account using parent_id
                $account = ChartOfAccount::where('name', $part)
                    ->where('parent', $parentId)
                    ->first();
            }

            // If not found, stop
            if (!$account) {
                return null;
            }

            // Now find this account’s parent row id for next iteration
            $parentRow = \DB::table('chart_of_account_parents')
                ->where('account', $account->id)
                ->first();

            $parentId = $parentRow ? $parentRow->id : null;
        }

        return $account ? $account->id : null;
    }
}