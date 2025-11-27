<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class StartQueueWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:start 
                            {--timeout=3600 : The number of seconds a child process can run}
                            {--sleep=3 : Number of seconds to sleep when no job is available}
                            {--tries=3 : Number of times to attempt a job before logging it failed}
                            {--daemon : Run the worker in daemon mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the queue worker for database queue connection';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Queue Worker for Database Connection...');
        $this->info('Press Ctrl+C to stop the worker');
        $this->newLine();

        // Build the command options
        $options = [
            '--timeout' => $this->option('timeout'),
            '--sleep' => $this->option('sleep'),
            '--tries' => $this->option('tries'),
        ];

        if ($this->option('daemon')) {
            $options['--daemon'] = true;
        }

        // Log that worker is starting
        \Log::info('Queue worker started via queue:start command');

        // Call the queue:work command with database connection
        $this->call('queue:work', array_merge(['database'], $options));

        return 0;
    }
}

