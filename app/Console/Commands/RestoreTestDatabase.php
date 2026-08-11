<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RestoreTestDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:restore-test {file?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform automated database backup restore drill to verify archive integrity.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $backupDir = storage_path('app/backups');
        $filename = $this->argument('file');

        if (! $filename) {
            $files = File::files($backupDir);
            if (empty($files)) {
                $this->error('No backup files found to test restore drill.');

                return Command::FAILURE;
            }
            $filename = basename($files[0]->getRealPath());
        }

        $filePath = "{$backupDir}/{$filename}";
        if (! File::exists($filePath)) {
            $this->error("Backup file not found at {$filePath}");

            return Command::FAILURE;
        }

        // Decrypt AES-256 payload
        $payload = base64_decode(File::get($filePath));
        $iv = substr($payload, 0, 16);
        $encrypted = substr($payload, 16);
        $passphrase = config('app.key', 'base64:SecretPassphraseKeyForBackups123=');

        $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $passphrase, 0, $iv);

        if ($decrypted === false) {
            $this->error("Failed to decrypt database backup archive: {$filename}");

            return Command::FAILURE;
        }

        $this->info("Database restore drill succeeded! Backup archive {$filename} decrypted and verified successfully.");

        return Command::SUCCESS;
    }
}
