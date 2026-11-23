<?php

namespace App\Services;

use App\Models\Doctor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class DoctorCacheService
{
    private int $ttl = 3600;

    public function getDoctors(
        int $branchId,
        ?string $specialty = null,
        int $page = 1,
        int $perPage = 15
    ): LengthAwarePaginator {
        $versionKey = "doctors:branch:{$branchId}:version";
        $version = Cache::get($versionKey, 1);

        $key = "doctors:branch:{$branchId}"
            . ":specialty:" . ($specialty ?? 'all')
            . ":page:{$page}"
            . ":per_page:{$perPage}"
            . ":version:{$version}";

        return Cache::remember($key, $this->ttl, function () use (
            $branchId,
            $specialty,
            $page,
            $perPage
        ) {
            return Doctor::query()
                ->active()
                ->when(
                    $specialty,
                    fn ($query) => $query->ofSpecialty($specialty)
                )
                ->atBranch($branchId)
                ->with([
                    'user:id,name',
                    'schedules' => fn ($query) => $query
                        ->where('branch_id', $branchId)
                        ->where('is_active', true),
                ])
                ->orderBy('id')
                ->paginate($perPage, ['*'], 'page', $page);
        });
    }

    public function clearBranch(int $branchId): void
    {
        $versionKey = "doctors:branch:{$branchId}:version";

        Cache::add($versionKey, 1);
        Cache::increment($versionKey);
    }
}
