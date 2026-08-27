<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Phase 4 (docs/PHASE_4_VENDOR_SYSTEM.md): creates a real login-capable employee - a `users` row (role
 * seller, so they log in through the same seller/login endpoint) plus the `employees` row linking them to
 * their employer's seller_id and (optionally) a branch.
 */
class EmployeeService
{
    public function create(int $sellerId, array $data): Employee
    {
        return DB::transaction(function () use ($sellerId, $data) {
            $user = User::forceCreate([
                'username' => $data['name'],
                'mobile' => $data['mobile'],
                'email' => $data['email'] ?? null,
                'password' => Hash::make($data['password']),
                'disk' => 'public',
                'serviceable_cities' => '',
                'type' => 'phone',
                'role_id' => Role::SELLER,
                'active' => 1,
                'status' => 1,
            ]);

            return Employee::forceCreate([
                'seller_id' => $sellerId,
                'branch_id' => $data['branch_id'] ?? null,
                'user_id' => $user->id,
                'position' => $data['position'] ?? null,
                'permissions' => isset($data['permissions']) ? json_encode($data['permissions']) : null,
                'status' => Employee::STATUS_ACTIVE,
                'disk' => 'public',
            ]);
        });
    }
}
