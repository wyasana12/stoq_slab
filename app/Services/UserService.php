<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Create a new class instance.
     */

    protected $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function getAllUsers(int $userPage = 10)
    {
        return $this->userRepository->getAllPaginated($userPage);
    }

    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $data['password'] = Hash::make($data['password']);

            $user = $this->userRepository->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'username' => $data['username'],
                'password' => $data['password'],
            ]);

            $this->userRepository->assignRole($user, $data['role_id']);
            $this->userRepository->assignWarehouse($user, $data['warehouse_id']);

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

            if (isset($data['warehouse_id'])) {
                $this->userRepository->assignWarehouse($user, $data['warehouse_id']);
            }

            return $user;
        });
    }

    public function getUserDetail(User $user): User
    {
        return $this->userRepository->getById($user);
    }
}
