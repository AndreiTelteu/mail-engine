<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface EmailSearchService
{
    public function search(User $user, string $query, ?string $folder = null, int $perPage = 20): LengthAwarePaginator;

    public function getRecent(User $user, int $perPage = 20): LengthAwarePaginator;
}
