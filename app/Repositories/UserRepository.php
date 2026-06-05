<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

class UserRepository
{
    public function getAllPaginated(int $perPage = 10)
    {
        return User::with(['warehouse:id,name', 'roles:id,name'])->select(['id', 'name', 'warehouse_id'])->latest()->paginate($perPage);
    }
    public function create(array $data): User
    {
        return User::create($data);
    }

    public function assignRole(User $user, string $role): void
    {
        $roleId = Role::find($role);

        $user->assignRole($roleId);

        Cache::forget("auth_user_{$user->id}");
    }

    public function assignWarehouse(User $user, string $warehouse)
    {
        $user->warehouse_id = $warehouse;
        $user->save();
    }

    public function update(User $user, array $data)
    {
        return $user->update($data);
    }

    public function getById(User $user): User
    {
        return $user->load(['roles', 'warehouse']);
    }
}
