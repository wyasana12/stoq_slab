<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Create a new class instance.
     */

    protected UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function getAllUsers()
    {
        return $this->userRepository->getAllPaginated();
    }

    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $data['password'] = Hash::make($data['password']);

            $user = $this->userRepository->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'username' => $data['username'],
                'phone_number' => $data['phone_number'],
                'region_id' => $data['region_id'],
                'street' => $data['street'],
                'postal_code' => $data['postal_code'],
                'birth_date' => $data['birth_date'],
                'password' => $data['password'],
                'warehouse_id' => $data['warehouse_id'],
            ]);

            $this->userRepository->assignRole($user, $data['role_id']);

            return $user;
        });
    }

    public function updateUser(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $this->userRepository->update($user, $data);

            if (isset($data['role_id'])) {
                $this->userRepository->assignRole($user, $data['role_id']);
            }


            return $user;
        });
    }

    public function toggleUserStatus(User $user, bool $status)
    {
        if ($user->trashed()) {
            throw new \Exception("Tidak bisa mengubah status user yang sudah dihapus.");
        }

        if (!$status) {
            $user->tokens()->delete();
            Cache::forget("auth_user_{$user->id}");
        }

        return $this->userRepository->status($user, $status);
    }

    public function removeUsers(User $user)
    {
        $user = $user->fresh();

        if ((bool) $user->is_active === true) {
            throw new \Exception("User sedang aktif dan tidak dapat dihapus. Nonaktifkan terlebih dahulu.");
        }

        if (Auth::id() === $user->id) {
            throw new \Exception("Anda tidak dapat menghapus akun Anda sendiri.");
        }

        return $this->userRepository->destroy($user);
    }

    public function getUserDetail(User $user): User
    {
        return $this->userRepository->getById($user);
    }
}
