<?php

namespace App\Services;

use App\Models\ApplicationDeployment;
use App\Models\ApplicationUpdateSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class ApplicationUpdateService
{
    private const LOCK_FILE = 'application-update.lock';

    public function settings(): ApplicationUpdateSetting
    {
        return ApplicationUpdateSetting::query()->first() ?? ApplicationUpdateSetting::create([
            'enabled' => false,
            'provider' => 'github',
            'repository_type' => 'public',
            'repository_url' => config('application-update.repository_url'),
            'branch' => 'main',
            'remote_name' => 'origin',
            'authentication' => 'none',
        ]);
    }

    public function saveSecret(ApplicationUpdateSetting $settings, ?string $secret): void
    {
        $secret = trim((string) $secret);
        if ($secret === '') {
            return;
        }

        $settings->encrypted_secret = Crypt::encryptString($secret);
        $settings->secret_fingerprint = hash('sha256', $secret);
    }

    public function status(?ApplicationUpdateSetting $settings = null): array
    {
        $settings ??= $this->settings();
        $base = [
            'configured' => false,
            'status' => 'Disabled',
            'message' => 'Git repository integration is disabled.',
            'local' => null,
            'remote' => null,
            'commits' => [],
            'changed_files' => [],
            'local_changes' => [],
            'untracked_files' => [],
        ];

        if (! $settings->enabled) {
            return $base;
        }

        $this->validateConfiguration($settings);
        $this->assertRepository();

        $currentBranch = trim($this->git(['branch', '--show-current'])['output']);
        if ($currentBranch !== $settings->branch) {
            return array_merge($base, [
                'configured' => true,
                'status' => 'Branch Not Found',
                'message' => 'The deployed checkout is not currently on the configured branch.',
                'local' => trim($this->git(['rev-parse', 'HEAD'])['output']),
            ]);
        }

        $local = trim($this->git(['rev-parse', 'HEAD'])['output']);
        $remote = $this->git(['remote', 'get-url', $settings->remote_name]);
        $this->assertRepositoryMatches($settings, trim($remote['output']));

        $branch = $this->git(['rev-parse', '--verify', $settings->remote_name.'/'.$settings->branch], false);
        if ($branch['code'] !== 0) {
            return array_merge($base, [
                'configured' => true,
                'status' => 'Branch Not Found',
                'message' => 'The configured branch was not found on the configured remote. Test the connection first.',
                'local' => $local,
            ]);
        }

        $target = trim($branch['output']);
        $ahead = trim($this->git(['rev-list', '--count', $settings->remote_name.'/'.$settings->branch.'..HEAD'])['output']) !== '0';
        $behindCount = (int) trim($this->git(['rev-list', '--count', 'HEAD..'.$settings->remote_name.'/'.$settings->branch])['output']);
        $commits = $this->git(['log', '--format=%H%x1f%h%x1f%an%x1f%aI%x1f%s', 'HEAD..'.$settings->remote_name.'/'.$settings->branch, '-n', '50']);
        $rows = [];

        foreach (array_filter(preg_split('/\R/', trim($commits['output']))) as $line) {
            $parts = explode("\x1f", $line, 5);
            if (count($parts) === 5) {
                $rows[] = [
                    'hash' => $parts[0],
                    'short' => $parts[1],
                    'author' => $parts[2],
                    'date' => $parts[3],
                    'subject' => $parts[4],
                ];
            }
        }

        $worktreeLines = array_values(array_filter(preg_split('/\R/', trim($this->git(['status', '--porcelain'])['output']))));
        $trackedChanges = array_values(array_filter($worktreeLines, static fn (string $line): bool => ! str_starts_with($line, '??') && ! str_starts_with($line, '!!')));
        $clean = $trackedChanges === [];
        $localChangesMatchTarget = ! $clean
            && $this->git(['diff', '--quiet', $settings->remote_name.'/'.$settings->branch, '--'], false)['code'] === 0;
        $deployable = $clean || $localChangesMatchTarget;

        $status = $deployable
            ? ($ahead ? 'Diverged Branch' : ($behindCount > 0 ? 'Update Available' : 'Up to date'))
            : 'Local Changes Detected';

        $message = match ($status) {
            'Diverged Branch' => 'The server branch has commits that are not on GitHub. Resolve the branch history manually before deploying.',
            'Update Available' => $behindCount.' update(s) are available for review.',
            'Local Changes Detected' => 'Deployment is blocked because tracked server changes exist. Review them before continuing.',
            default => 'The server is synchronized with the configured GitHub branch.',
        };

        return [
            'configured' => true,
            'status' => $status,
            'message' => $message,
            'local' => $local,
            'remote' => $target,
            'commits' => $rows,
            'changed_files' => array_values(array_filter(preg_split('/\R/', trim($this->git(['diff', '--name-status', 'HEAD', $settings->remote_name.'/'.$settings->branch])['output'])))),
            'local_changes' => $trackedChanges,
            'untracked_files' => array_values(array_filter($worktreeLines, static fn (string $line): bool => str_starts_with($line, '??'))),
            'local_changes_match_target' => $localChangesMatchTarget,
        ];
    }

    public function fetch(ApplicationUpdateSetting $settings): array
    {
        $this->validateConfiguration($settings);
        $this->assertRepository();

        $remote = $this->git(['remote', 'get-url', $settings->remote_name]);
        $this->assertRepositoryMatches($settings, trim($remote['output']));
        $this->gitOrFail(['fetch', '--prune', $settings->remote_name, $settings->branch], 'Unable to fetch the configured GitHub repository. Check repository access and branch settings.');

        return $this->status($settings);
    }

    public function pullLatest(ApplicationUpdateSetting $settings): array
    {
        $handle = $this->lock();

        try {
            $status = $this->fetch($settings);
            if ($status['status'] !== 'Up to date' && $status['status'] !== 'Update Available') {
                throw new \RuntimeException($status['message']);
            }

            if ($status['status'] === 'Update Available') {
                $this->gitOrFail(['merge', '--ff-only', $settings->remote_name.'/'.$settings->branch], 'Fast-forward pull was not possible. Review the branch history manually.');
            }

            return $this->status($settings);
        } finally {
            $this->unlock($handle);
        }
    }

    public function deploy(ApplicationUpdateSetting $settings, int $adminId, bool $discardLocalChanges = false): ApplicationDeployment
    {
        $handle = $this->lock();

        try {
            $status = $this->fetch($settings);

            if ($discardLocalChanges && $status['status'] === 'Local Changes Detected') {
                $this->gitOrFail(['reset', '--hard', 'HEAD'], 'The local tracked changes could not be discarded.');
                $status = $this->fetch($settings);
            }

            if ($status['status'] !== 'Update Available') {
                throw new \RuntimeException($status['message']);
            }

            if (! empty($status['local_changes_match_target'])) {
                $this->gitOrFail(['reset', '--hard', $status['remote']], 'The checkout could not be synchronized with the remote update.');
            }

            $deployment = ApplicationDeployment::create([
                'repository_url' => $settings->repository_url,
                'branch' => $settings->branch,
                'previous_commit' => $status['local'],
                'target_commit' => $status['remote'],
                'status' => 'running',
                'commits_applied' => count($status['commits']),
                'started_by' => $adminId,
                'started_at' => now(),
                'migration_status' => 'not_run',
                'health_status' => 'not_run',
                'safe_log' => 'Repository access and fast-forward validation passed.',
            ]);

            try {
                $this->gitOrFail(['merge', '--ff-only', $settings->remote_name.'/'.$settings->branch], 'Fast-forward deployment was not possible.');
                $deployment->deployed_commit = trim($this->git(['rev-parse', 'HEAD'])['output']);

                if ($settings->run_migrations) {
                    $this->artisan('migrate', '--force');
                    $deployment->migration_status = 'successful';
                } else {
                    $deployment->migration_status = 'skipped';
                }

                if ($settings->clear_cache) {
                    $this->artisan('optimize:clear');
                    $deployment->safe_log .= "\nApplication caches cleared.";
                }

                if ($settings->health_check) {
                    DB::select('SELECT 1');
                    $deployment->health_status = 'passed';
                } else {
                    $deployment->health_status = 'skipped';
                }

                $deployment->status = 'success';
                $deployment->completed_at = now();
                $deployment->save();

                return $deployment;
            } catch (\Throwable $exception) {
                $deployment->status = 'failed';
                $deployment->failed_stage = $deployment->migration_status === 'successful' ? 'health_or_finish' : 'source_or_migration';
                $deployment->error_summary = $this->safeError($exception->getMessage());
                $deployment->completed_at = now();
                $deployment->save();
                throw $exception;
            }
        } finally {
            $this->unlock($handle);
        }
    }

    public function rollback(ApplicationDeployment $deployment, int $adminId): ApplicationDeployment
    {
        if ($deployment->status !== 'success' || ! $deployment->previous_commit) {
            throw new \RuntimeException('Only a successful deployment with a recorded previous commit can be rolled back.');
        }

        $settings = $this->settings();
        $handle = $this->lock();

        try {
            $this->assertRepository();
            $current = trim($this->git(['rev-parse', 'HEAD'])['output']);
            $this->gitOrFail(['-c', 'user.name=e_commerce_marketplace', '-c', 'user.email=deploy@localhost', 'revert', '--no-edit', '--no-commit', $deployment->previous_commit.'..'.$current], 'Source rollback failed. Resolve the source history manually.');
            $this->gitOrFail(['-c', 'user.name=e_commerce_marketplace', '-c', 'user.email=deploy@localhost', 'commit', '-m', 'Rollback application deployment #'.$deployment->id], 'Rollback commit failed.');

            return ApplicationDeployment::create([
                'repository_url' => $settings->repository_url,
                'branch' => $settings->branch,
                'previous_commit' => $current,
                'target_commit' => $deployment->previous_commit,
                'deployed_commit' => trim($this->git(['rev-parse', 'HEAD'])['output']),
                'status' => 'success',
                'started_by' => $adminId,
                'started_at' => now(),
                'completed_at' => now(),
                'rollback_of' => $deployment->id,
                'migration_status' => 'not_run',
                'health_status' => 'skipped',
                'safe_log' => 'Source rollback completed. Database migrations were not reversed.',
            ]);
        } finally {
            $this->unlock($handle);
        }
    }

    public function deploymentCommitSubjects(iterable $deployments): array
    {
        $subjects = [];

        foreach ($deployments as $deployment) {
            $commit = trim((string) ($deployment->deployed_commit ?: $deployment->target_commit));
            if (! preg_match('/^[a-f0-9]{7,40}$/i', $commit)) {
                continue;
            }

            $result = $this->git(['show', '-s', '--format=%s', $commit], false);
            if ($result['code'] === 0 && $result['output'] !== '') {
                $subjects[$commit] = Str::limit($result['output'], 180);
            }
        }

        return $subjects;
    }

    private function validateConfiguration(ApplicationUpdateSetting $settings): void
    {
        $urlPattern = '#^(https://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+(?:\.git)?|git@github\.com:[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\.git)$#';

        if ($settings->provider !== 'github' || ! in_array($settings->repository_type, ['public', 'private'], true) || ! preg_match($urlPattern, trim((string) $settings->repository_url))) {
            throw new \RuntimeException('Enter a valid GitHub repository URL.');
        }

        if (! preg_match('/^[A-Za-z0-9._-]{1,100}$/', (string) $settings->branch) || ! preg_match('/^[A-Za-z0-9._-]{1,50}$/', (string) $settings->remote_name)) {
            throw new \RuntimeException('Branch or remote name is invalid.');
        }

        if ($settings->repository_type === 'private' && ! in_array($settings->authentication, ['ssh', 'pat'], true)) {
            throw new \RuntimeException('Private repositories require SSH deploy key or personal access token authentication.');
        }
    }

    private function assertRepository(): void
    {
        if (! is_dir(base_path('.git'))) {
            throw new \RuntimeException('This installation is not currently managed by Git.');
        }
    }

    private function assertRepositoryMatches(ApplicationUpdateSetting $settings, string $actual): void
    {
        $normalize = static function (string $value): string {
            $value = strtolower(trim($value));
            $value = preg_replace('#^https://github\.com/#', 'github:', $value);
            $value = preg_replace('#^git@github\.com:#', 'github:', $value);

            return preg_replace('/\.git$/', '', rtrim($value, '/'));
        };

        if ($normalize($actual) !== $normalize((string) $settings->repository_url)) {
            throw new \RuntimeException('The configured repository does not match the repository deployed in this application directory.');
        }
    }

    private function git(array $arguments, bool $throw = true): array
    {
        $environment = [];
        $secret = $this->secret();
        $temporaryCredential = null;
        $settings = $this->settings();

        if ($secret !== null && $settings->authentication === 'ssh') {
            $temporaryCredential = storage_path('app/.git-deploy-key');
            File::ensureDirectoryExists(dirname($temporaryCredential));
            File::put($temporaryCredential, $secret);
            @chmod($temporaryCredential, 0600);
            $environment['GIT_SSH_COMMAND'] = 'ssh -i "'.$temporaryCredential.'" -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new';
        } elseif ($secret !== null && $settings->authentication === 'pat') {
            $temporaryCredential = storage_path(PHP_OS_FAMILY === 'Windows' ? 'app/.git-askpass.bat' : 'app/.git-askpass');
            File::ensureDirectoryExists(dirname($temporaryCredential));
            if (PHP_OS_FAMILY === 'Windows') {
                File::put($temporaryCredential, "@echo off\r\necho ".$secret."\r\n");
            } else {
                File::put($temporaryCredential, "#!/bin/sh\nprintf '%s\\n' ".escapeshellarg($secret)."\n");
                @chmod($temporaryCredential, 0700);
            }
            $environment['GIT_ASKPASS'] = $temporaryCredential;
            $environment['GIT_TERMINAL_PROMPT'] = '0';
        }

        try {
            $process = new Process(array_merge(['git'], $arguments), base_path(), $environment, null, 60);
            $process->run();
            $result = [
                'code' => $process->getExitCode() ?? 1,
                'output' => trim($process->getOutput()),
                'error' => trim($process->getErrorOutput()),
            ];

            if ($throw && $result['code'] !== 0) {
                throw new \RuntimeException($this->safeError($result['error'] ?: 'Git operation failed.'));
            }

            return $result;
        } finally {
            if ($temporaryCredential && is_file($temporaryCredential)) {
                @unlink($temporaryCredential);
            }
        }
    }

    private function gitOrFail(array $arguments, string $message): void
    {
        if ($this->git($arguments, false)['code'] !== 0) {
            throw new \RuntimeException($message);
        }
    }

    private function artisan(string ...$arguments): void
    {
        $process = new Process(array_merge([PHP_BINARY, base_path('artisan')], $arguments), base_path(), null, null, 300);
        $process->run();

        if ($process->getExitCode() !== 0) {
            throw new \RuntimeException('Approved deployment task failed.');
        }
    }

    private function secret(): ?string
    {
        $encrypted = $this->settings()->encrypted_secret;
        if (! $encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeError(string $error): string
    {
        return preg_replace('/(?:token|password|secret|private.?key|authorization)[^\s]*/i', '[REDACTED]', substr($error, 0, 2000));
    }

    private function lock()
    {
        $path = storage_path('app/'.self::LOCK_FILE);
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'c');

        if (! $handle || ! flock($handle, LOCK_EX | LOCK_NB)) {
            throw new \RuntimeException('Another application deployment is already in progress.');
        }

        return $handle;
    }

    private function unlock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
