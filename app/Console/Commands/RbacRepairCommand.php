<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RbacRepairCommand extends Command
{
    protected $signature = 'jaringanku:rbac-repair
        {--tenant= : Tenant slug}
        {--dry-run : Preview only, do not write}
        {--verify : Verify current matrix without writing}';

    protected $description = 'Repair and verify the default tenant role permission matrix for role-aware UI.';

    public function handle(): int
    {
        $tenantSlug = trim((string) $this->option('tenant'));

        if ($tenantSlug === '') {
            $this->error('--tenant wajib diisi.');
            return self::FAILURE;
        }

        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();

        if (! $tenant) {
            $this->error("Tenant {$tenantSlug} tidak ditemukan.");
            return self::FAILURE;
        }

        $catalog = DB::table('permissions')->orderBy('slug')->get(['id', 'slug']);
        if ($catalog->isEmpty()) {
            $this->error('Permission catalog kosong.');
            return self::FAILURE;
        }

        $catalogBySlug = $catalog->keyBy(fn ($row) => (string) $row->slug);
        $roles = Role::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('slug', ['owner','admin','finance','cs','noc','warehouse','viewer','technician'])
            ->with('permissions:id,slug')
            ->orderBy('id')
            ->get();

        if ($roles->isEmpty()) {
            $this->error('Role tenant tidak ditemukan.');
            return self::FAILURE;
        }

        $this->table(
            ['Role', 'Current', 'Expected', 'Mode'],
            $roles->map(function (Role $role) use ($catalogBySlug) {
                return [
                    $role->slug,
                    $role->permissions->count(),
                    count($this->expectedSlugs((string) $role->slug, $catalogBySlug->keys()->all())),
                    $this->option('verify') ? 'VERIFY' : ($this->option('dry-run') ? 'DRY-RUN' : 'APPLY'),
                ];
            })->all()
        );

        if ($this->option('verify')) {
            return $this->verify($roles, $catalogBySlug->keys()->all());
        }

        if ($this->option('dry-run')) {
            foreach ($roles as $role) {
                $this->line($role->slug.': '.implode(', ', $this->expectedSlugs((string) $role->slug, $catalogBySlug->keys()->all())));
            }
            $this->info('DRY-RUN PASS - no changes made.');
            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($roles, $catalogBySlug): void {
                foreach ($roles as $role) {
                    $expected = $this->expectedSlugs((string) $role->slug, $catalogBySlug->keys()->all());
                    $ids = collect($expected)->map(fn (string $slug) => (int) $catalogBySlug[$slug]->id)->all();
                    $role->permissions()->sync($ids);
                }

                $fresh = Role::query()
                    ->whereIn('id', $roles->pluck('id'))
                    ->with('permissions:id,slug')
                    ->get();

                $failures = $this->matrixFailures($fresh, $catalogBySlug->keys()->all());

                if ($failures !== []) {
                    throw new RuntimeException('RBAC verification failed: '.implode(' | ', $failures));
                }
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('RBAC REPAIR: PASS');
        return self::SUCCESS;
    }

    private function verify($roles, array $catalog): int
    {
        $failures = $this->matrixFailures($roles, $catalog);

        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->error($failure);
            }
            return self::FAILURE;
        }

        $this->info('RBAC VERIFY: PASS');
        return self::SUCCESS;
    }

    private function matrixFailures($roles, array $catalog): array
    {
        $failures = [];

        foreach ($roles as $role) {
            $actual = $role->permissions->pluck('slug')->map(fn ($x) => (string) $x)->sort()->values()->all();
            $expected = collect($this->expectedSlugs((string) $role->slug, $catalog))->sort()->values()->all();

            if ($actual !== $expected) {
                $failures[] = $role->slug.' mismatch (actual '.count($actual).', expected '.count($expected).')';
            }
        }

        return $failures;
    }

    private function expectedSlugs(string $role, array $catalog): array
    {
        $role = Str::lower($role);

        $allowed = array_values(array_filter($catalog, function (string $slug) use ($role): bool {
            if (in_array($role, ['owner', 'admin'], true)) {
                return true;
            }

            if ($role === 'finance') {
                return $slug === 'dashboard.view'
                    || $slug === 'customers.view'
                    || Str::startsWith($slug, 'billing.')
                    || Str::startsWith($slug, 'reports.');
            }

            if ($role === 'cs') {
                return $slug === 'dashboard.view'
                    || Str::startsWith($slug, 'customers.')
                    || $slug === 'billing.view'
                    || Str::startsWith($slug, 'field_ops.');
            }

            if ($role === 'noc') {
                return $slug === 'dashboard.view'
                    || $slug === 'customers.view'
                    || $slug === 'field_ops.view'
                    || Str::startsWith($slug, 'network.')
                    || Str::startsWith($slug, 'operations.')
                    || $slug === 'reports.view';
            }

            if ($role === 'warehouse') {
                return $slug === 'dashboard.view'
                    || $slug === 'field_ops.view'
                    || Str::startsWith($slug, 'inventory.');
            }

            if ($role === 'technician') {
                return $slug === 'dashboard.view'
                    || $slug === 'customers.view'
                    || $slug === 'inventory.view'
                    || Str::startsWith($slug, 'field_ops.');
            }

            if ($role === 'viewer') {
                return Str::endsWith($slug, '.view');
            }

            return false;
        }));

        sort($allowed);
        return $allowed;
    }
}
