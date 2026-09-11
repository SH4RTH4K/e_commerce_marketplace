<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationDeployment;
use App\Services\ApplicationUpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class ApplicationUpdateController extends Controller
{
    public function index(ApplicationUpdateService $updates)
    {
        $settings = $updates->settings();
        $status = null;
        $error = null;

        if ($settings->enabled) {
            try {
                $status = $updates->status($settings);
            } catch (\Throwable $exception) {
                $error = $exception->getMessage();
            }
        } else {
            $status = $updates->status($settings);
        }

        $history = Schema::hasTable('application_deployments')
            ? ApplicationDeployment::query()->latest()->limit(20)->get()
            : collect();

        $subjects = $updates->deploymentCommitSubjects($history);
        $history->each(function (ApplicationDeployment $deployment) use ($subjects): void {
            $commit = $deployment->deployed_commit ?: $deployment->target_commit;
            $deployment->setAttribute('commit_subject', $subjects[$commit] ?? null);
        });

        return Inertia::render('Admin/GitRepository', [
            'settings' => [
                'enabled' => (bool) $settings->enabled,
                'provider' => $settings->provider,
                'repository_type' => $settings->repository_type,
                'repository_url' => $settings->repository_url,
                'branch' => $settings->branch,
                'remote_name' => $settings->remote_name,
                'authentication' => $settings->authentication,
                'has_secret' => filled($settings->encrypted_secret),
                'run_migrations' => (bool) $settings->run_migrations,
                'clear_cache' => (bool) $settings->clear_cache,
                'health_check' => (bool) $settings->health_check,
            ],
            'status' => $status,
            'error' => $error,
            'history' => $history->map(fn (ApplicationDeployment $deployment): array => [
                'id' => $deployment->id,
                'branch' => $deployment->branch,
                'previous_commit' => $deployment->previous_commit,
                'target_commit' => $deployment->target_commit,
                'deployed_commit' => $deployment->deployed_commit,
                'status' => $deployment->status,
                'commits_applied' => $deployment->commits_applied,
                'migration_status' => $deployment->migration_status,
                'health_status' => $deployment->health_status,
                'rollback_of' => $deployment->rollback_of,
                'commit_subject' => $deployment->commit_subject,
                'started_at' => $deployment->started_at?->toISOString(),
                'completed_at' => $deployment->completed_at?->toISOString(),
            ])->values(),
        ]);
    }

    public function saveSettings(Request $request, ApplicationUpdateService $updates)
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'repository_type' => ['required', 'in:public,private'],
            'repository_url' => ['required', 'string', 'max:255', 'regex:#^(https://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+(?:\.git)?|git@github\.com:[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\.git)$#'],
            'branch' => ['required', 'regex:/^[A-Za-z0-9._-]{1,100}$/'],
            'remote_name' => ['required', 'regex:/^[A-Za-z0-9._-]{1,50}$/'],
            'authentication' => ['required', 'in:none,ssh,pat'],
            'secret' => ['nullable', 'string', 'max:10000'],
            'run_migrations' => ['nullable', 'boolean'],
            'clear_cache' => ['nullable', 'boolean'],
            'health_check' => ['nullable', 'boolean'],
        ]);

        $settings = $updates->settings();

        if ($data['repository_type'] === 'private' && ! in_array($data['authentication'], ['ssh', 'pat'], true)) {
            return back()->withErrors(['authentication' => 'Private repositories require an SSH deploy key or personal access token.'])->withInput();
        }

        if ($data['repository_type'] === 'private' && blank($data['secret'] ?? null) && ! $settings->encrypted_secret) {
            return back()->withErrors(['secret' => 'A credential is required for a private repository.'])->withInput();
        }

        if ($data['repository_type'] === 'public' && $data['authentication'] !== 'none') {
            return back()->withErrors(['authentication' => 'Public repositories use None authentication.'])->withInput();
        }

        $settings->fill(collect($data)->except('secret')->all());
        $settings->enabled = ! empty($data['enabled']);
        $settings->run_migrations = ! empty($data['run_migrations']);
        $settings->clear_cache = ! empty($data['clear_cache']);
        $settings->health_check = ! empty($data['health_check']);

        if ($data['repository_type'] === 'public') {
            $settings->authentication = 'none';
            $settings->encrypted_secret = null;
            $settings->secret_fingerprint = null;
        } elseif (filled($data['secret'] ?? null)) {
            $updates->saveSecret($settings, $data['secret']);
        }

        $settings->save();

        return back()->with('status', 'Git repository settings saved. Test the connection before checking for updates.');
    }

    public function test(ApplicationUpdateService $updates)
    {
        try {
            $settings = $updates->settings();
            abort_unless($settings->enabled, 422, 'Enable the Git repository integration first.');
            $result = $updates->fetch($settings);

            return back()->with('status', 'Connection successful. Remote branch is available at '.substr((string) $result['remote'], 0, 12).'.');
        } catch (\Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function check(ApplicationUpdateService $updates)
    {
        try {
            $settings = $updates->settings();
            abort_unless($settings->enabled, 422, 'Enable the Git repository integration first.');
            $result = $updates->fetch($settings);
            $this->rememberStatus($settings, $result);

            return back()->with('status', $result['message']);
        } catch (\Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function pull(ApplicationUpdateService $updates)
    {
        try {
            $settings = $updates->settings();
            abort_unless($settings->enabled, 422, 'Enable the Git repository integration first.');
            $result = $updates->pullLatest($settings);
            $this->rememberStatus($settings, $result);

            return back()->with('status', 'The latest reviewed code was pulled successfully.');
        } catch (\Throwable $exception) {
            return back()->with('error', 'Pull failed: '.$exception->getMessage());
        }
    }

    public function deploy(ApplicationUpdateService $updates)
    {
        try {
            $settings = $updates->settings();
            abort_unless($settings->enabled, 422, 'Enable the Git repository integration first.');
            $deployment = $updates->deploy($settings, (int) auth()->id());

            return back()->with('status', 'Deployment completed successfully at commit '.substr((string) $deployment->deployed_commit, 0, 12).'.');
        } catch (\Throwable $exception) {
            return back()->with('error', 'Deployment failed: '.$exception->getMessage());
        }
    }

    public function discardAndDeploy(Request $request, ApplicationUpdateService $updates)
    {
        $request->validate(['confirmation' => ['required', 'in:DISCARD LOCAL CHANGES']]);

        try {
            $settings = $updates->settings();
            abort_unless($settings->enabled, 422, 'Enable the Git repository integration first.');
            $deployment = $updates->deploy($settings, (int) auth()->id(), true);

            return back()->with('status', 'Tracked local changes were discarded and deployment completed at commit '.substr((string) $deployment->deployed_commit, 0, 12).'.');
        } catch (\Throwable $exception) {
            return back()->with('error', 'Deployment failed: '.$exception->getMessage());
        }
    }

    public function rollback(ApplicationDeployment $deployment, ApplicationUpdateService $updates)
    {
        try {
            $result = $updates->rollback($deployment, (int) auth()->id());

            return back()->with('status', 'Source rollback completed at commit '.substr((string) $result->deployed_commit, 0, 12).'. Database migrations were not reversed.');
        } catch (\Throwable $exception) {
            return back()->with('error', 'Rollback failed: '.$exception->getMessage());
        }
    }

    private function rememberStatus($settings, array $result): void
    {
        $settings->last_checked_commit = $result['remote'] ?? null;
        $settings->last_checked_at = now();
        $settings->last_status = $result['status'] ?? null;
        $settings->last_message = $result['message'] ?? null;
        $settings->save();
    }
}
