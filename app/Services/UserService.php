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

    protected UserRepository $userRepository;

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

    public function getUserDetail(User $user): User
    {
        return $this->userRepository->getById($user);
    }
}
