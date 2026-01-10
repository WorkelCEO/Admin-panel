<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClearLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:clear {--keep-days=7 : Number of days of logs to keep}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear old log files from storage/logs directory';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $logsPath = storage_path('logs');
        
        if (!File::exists($logsPath)) {
            $this->error('Logs directory does not exist!');
            return Command::FAILURE;
        }

        $keepDays = (int) $this->option('keep-days');
        $cutoffDate = now()->subDays($keepDays);
        
        $this->info("Clearing logs older than {$keepDays} days...");
        
        $files = File::files($logsPath);
        $clearedCount = 0;
        $totalSize = 0;
        
        foreach ($files as $file) {
            $fileDate = File::lastModified($file);
            $fileSize = File::size($file);
            
            // Clear files older than cutoff date or if the file is the main laravel.log and it's too large
            if ($fileDate < $cutoffDate->timestamp || 
                ($file->getFilename() === 'laravel.log' && $fileSize > 100 * 1024 * 1024)) { // 100MB
                File::delete($file);
                $clearedCount++;
                $totalSize += $fileSize;
                $this->line("Deleted: {$file->getFilename()} (" . $this->formatBytes($fileSize) . ")");
            }
        }
        
        if ($clearedCount > 0) {
            $this->info("Cleared {$clearedCount} log file(s), freed " . $this->formatBytes($totalSize));
        } else {
            $this->info("No log files to clear.");
        }
        
        return Command::SUCCESS;
    }

    /**
     * Format bytes to human-readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
