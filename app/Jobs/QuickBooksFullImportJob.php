<?php

namespace App\Jobs;

use App\Http\Controllers\QuickBooksApiController;
use App\Http\Controllers\QuickBooksImportController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class QuickBooksFullImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour timeout
    public $tries = 3;

    protected $importOrder = [
        'customers' => 'Importing Customers',
        'vendors' => 'Importing Vendors',
        'chartOfAccounts' => 'Importing Chart of Accounts',
        'items' => 'Importing Items/Products',
        'invoices' => 'Importing Invoices',
        'bills' => 'Importing Bills',
        'expenses' => 'Importing Expenses',
        'journalReport' => 'Importing Journal Reports',
    ];

    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Set the authenticated user for the job
        \Auth::loginUsingId($this->userId);

        $controller = new QuickBooksImportController();
        $totalSteps = count($this->importOrder);
        $currentStep = 0;

        // Initialize progress with empty logs array
        $this->initializeProgress($totalSteps);

        Log::info('QuickBooks Full Import Job started for user: ' . $this->userId);

        try {
            foreach ($this->importOrder as $method => $description) {
                $currentStep++;

                // Log that we're starting this step
                $this->logInfo("Starting {$description}...");
                $this->updateProgress($currentStep, $totalSteps, $description);

                Log::info("Starting {$description} for user {$this->userId}");

                // Refresh token if needed before each import
                $this->refreshTokenIfNeeded();

                // Add rate limiting delay if needed
                $this->handleRateLimit();

                // Call the import method
                $result = $this->$method($controller);

                if ($result instanceof \Illuminate\Http\JsonResponse) {
                    $resultData = json_decode($result->getContent(), true);
                    if (($resultData['status'] ?? '') === 'error') {
                        $errorMsg = "Error in {$description}: " . ($resultData['message'] ?? 'Unknown error');
                        $this->logError($errorMsg);
                        Log::error($errorMsg);
                        // Update progress with error
                        $this->updateProgress($currentStep, $totalSteps, $description . ' (Failed)');
                        continue;
                    }
                }

                // Log success immediately
                $successMsg = "{$description} completed successfully";
                $this->logSuccess($successMsg);
                Log::info($successMsg);

                // Update progress with the successful step
                $this->updateProgress($currentStep, $totalSteps, $description . ' ✓');
            }

            // Final success update
            $this->logSuccess('All imports completed successfully!');
            $this->updateProgress($totalSteps, $totalSteps, 'Import completed successfully', 'completed');
            Log::info('QuickBooks Full Import Job completed successfully for user: ' . $this->userId);

        } catch (\Exception $e) {
            $errorMsg = 'QuickBooks Full Import Job Failed: ' . $e->getMessage();
            Log::error($errorMsg, [
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->userId
            ]);

            $this->logError($errorMsg);
            $this->updateProgress($currentStep, $totalSteps, $errorMsg, 'failed');
        }
    }

    protected function customers($controller)
    {
        return $controller->customers();
    }

    protected function vendors($controller)
    {
        return $controller->vendors();
    }

    protected function chartOfAccounts($controller)
    {
        return $controller->chartOfAccounts();
    }

    protected function items($controller)
    {
        return $controller->items();
    }

    protected function invoices($controller)
    {
        return $controller->importInvoices(new Request());
    }

    protected function bills($controller)
    {
        return $controller->importBills(new Request());
    }

    protected function expenses($controller)
    {
        return $controller->importExpenses(new Request());
    }

    protected function journalReport($controller)
    {
        $request = new Request();
        $request->merge([
            'start_date' => '2010-01-01',
            'end_date' => now()->format('Y-m-d'),
            // 'accounting_method' => 'Accrual'
        ]);
        return $controller->journalReport($request);
    }

    protected function initializeProgress($totalSteps)
    {
        $cacheKey = 'qb_import_progress_' . $this->userId;

        // Get existing progress (controller may have already initialized it)
        $existingProgress = Cache::get($cacheKey, []);
        $existingLogs = $existingProgress['logs'] ?? [];

        // Add initialization log to existing logs
        $existingLogs[] = '[INFO] Import job started at ' . now()->toDateTimeString();

        $progress = [
            'status' => 'running',
            'current_step' => 0,
            'total_steps' => $totalSteps,
            'current_import' => 'Initializing import...',
            'logs' => $existingLogs,
            'percentage' => 0,
        ];
        Cache::put($cacheKey, $progress, 3600);
    }

    protected function updateProgress($currentStep, $totalSteps, $currentImport, $status = 'running')
    {
        $cacheKey = 'qb_import_progress_' . $this->userId;
        $percentage = round(($currentStep / $totalSteps) * 100);

        // Get existing progress to preserve logs
        $progress = Cache::get($cacheKey, []);

        $progress['status'] = $status;
        $progress['current_step'] = $currentStep;
        $progress['total_steps'] = $totalSteps;
        $progress['current_import'] = $currentImport;
        $progress['percentage'] = $percentage;

        // Only keep last 100 logs to avoid cache bloat
        if (isset($progress['logs']) && count($progress['logs']) > 100) {
            $progress['logs'] = array_slice($progress['logs'], -100);
        }

        Cache::put($cacheKey, $progress, 3600);
    }

    protected function logSuccess($message)
    {
        $cacheKey = 'qb_import_progress_' . $this->userId;
        $progress = Cache::get($cacheKey, []);

        // Ensure logs array exists
        if (!isset($progress['logs'])) {
            $progress['logs'] = [];
        }

        // Add log with timestamp
        $progress['logs'][] = '[SUCCESS] ' . $message . ' at ' . now()->toDateTimeString();

        Cache::put($cacheKey, $progress, 3600);
    }

    protected function logError($message)
    {
        $cacheKey = 'qb_import_progress_' . $this->userId;
        $progress = Cache::get($cacheKey, []);

        // Ensure logs array exists
        if (!isset($progress['logs'])) {
            $progress['logs'] = [];
        }

        // Add log with timestamp
        $progress['logs'][] = '[ERROR] ' . $message . ' at ' . now()->toDateTimeString();

        Cache::put($cacheKey, $progress, 3600);
    }

    protected function logInfo($message)
    {
        $cacheKey = 'qb_import_progress_' . $this->userId;
        $progress = Cache::get($cacheKey, []);

        // Ensure logs array exists
        if (!isset($progress['logs'])) {
            $progress['logs'] = [];
        }

        // Add log with timestamp
        $progress['logs'][] = '[INFO] ' . $message . ' at ' . now()->toDateTimeString();

        Cache::put($cacheKey, $progress, 3600);
    }

    protected function handleRateLimit()
    {
        // QuickBooks API rate limit is 500 requests per minute
        // Add a small delay between imports to avoid hitting limits
        sleep(1); // 1 second delay between each import step
    }

    protected function refreshTokenIfNeeded()
    {
        try {
            // Get token from database instead of session (sessions don't work in queued jobs)
            $tokenRecord = \App\Models\QuickBooksToken::where('user_id', $this->userId)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$tokenRecord) {
                throw new \Exception('No QuickBooks tokens available for user ' . $this->userId);
            }

            // Check if token is expired or about to expire (within 5 minutes)
            if ($tokenRecord->expires_at && now()->addMinutes(5)->greaterThan($tokenRecord->expires_at)) {
                // Token is about to expire, refresh it
                $this->logInfo('QuickBooks token expiring soon, refreshing...');

                $qbController = new QuickBooksApiController();

                // The controller's refreshToken method should handle updating the database
                $newTokens = $qbController->refreshToken($tokenRecord->refresh_token);

                if ($newTokens) {
                    $this->logSuccess('QuickBooks access token refreshed successfully');
                } else {
                    throw new \Exception('Failed to refresh access token');
                }
            }
        } catch (\Exception $e) {
            $this->logError('Token refresh failed: ' . $e->getMessage());
            throw $e;
        }
    }
}