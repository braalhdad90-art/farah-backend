<?php

namespace App\Console\Commands;

use App\Models\ProviderProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SecureProviderDocuments extends Command
{
    protected $signature = 'providers:secure-documents {--execute : Copy documents to private storage and remove public copies}';

    protected $description = 'Safely migrate provider identity documents from public to private storage';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $inspected = 0;
        $migrated = 0;
        $errors = 0;

        ProviderProfile::query()->orderBy('id')->chunkById(100, function ($profiles) use ($execute, &$inspected, &$migrated, &$errors): void {
            foreach ($profiles as $profile) {
                foreach (['identity_document_path', 'commercial_register_path'] as $attribute) {
                    $path = $profile->{$attribute};
                    if (! $path) {
                        continue;
                    }
                    $inspected++;

                    try {
                        $this->assertSafeRelativePath($path);
                        $public = Storage::disk('public');
                        $private = Storage::disk('local');
                        $hasPublic = $public->exists($path);
                        $hasPrivate = $private->exists($path);

                        if (! $hasPublic && ! $hasPrivate) {
                            throw new RuntimeException('Document is missing from both public and private storage.');
                        }
                        if (! $execute || ! $hasPublic) {
                            continue;
                        }

                        $contents = $public->get($path);
                        if ($hasPrivate && hash('sha256', $private->get($path)) !== hash('sha256', $contents)) {
                            throw new RuntimeException('A private file with the same path has different contents.');
                        }
                        if (! $hasPrivate && ! $private->put($path, $contents)) {
                            throw new RuntimeException('Private storage did not confirm the document write.');
                        }
                        if (hash('sha256', $private->get($path)) !== hash('sha256', $contents)) {
                            throw new RuntimeException('Private document verification failed; the public copy was retained.');
                        }

                        DB::transaction(function () use ($profile, $attribute): void {
                            ProviderProfile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
                            if (! Storage::disk('public')->delete($profile->{$attribute})) {
                                throw new RuntimeException('Could not remove the verified public document copy.');
                            }
                        });
                        $migrated++;
                    } catch (Throwable $exception) {
                        $errors++;
                        $this->error("Provider profile {$profile->id} ({$attribute}): {$exception->getMessage()}");
                    }
                }
            }
        });

        $this->info(($execute ? 'Migrated' : 'Would inspect').' '.$inspected.' provider document references; '.$migrated.' public copies removed; '.$errors.' errors.');
        if (! $execute) {
            $this->comment('Dry run only. Review the results, then run with --execute during a maintenance window.');
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function assertSafeRelativePath(string $path): void
    {
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)) {
            throw new RuntimeException('Stored document path is not a safe relative path.');
        }
    }
}
