<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

class UserRepository
{
    public function getAllPaginated()
    {
        return User::with(['warehouse:id,name', 'roles:id,name'])->select(['id', 'name', 'warehouse_id', 'is_active'])->latest()->get();
    }
    public function create(array $data): User
    {
        return User::create($data);
    }

    public function assignRole(User $user, string $role): void
    {
        $roleId = Role::find($role);

        $user->syncRoles($roleId);

        Cache::forget("auth_user_{$user->id}");
    }

    public function update(User $user, array $data)
    {
        return $user->update($data);
    }

    public function status(User $user, bool $status)
    { 
        return $user->update(['is_active' => $status]);
    }

    public function destroy(User $user)
    {
        return $user->delete();    
    }

    public function getById(User $user): User
    {
        return $user->load(['roles', 'warehouse', 'region']);
    }
}
