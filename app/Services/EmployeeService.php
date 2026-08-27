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

            $employee = Employee::forceCreate([
                'seller_id' => $sellerId,
                'branch_id' => $data['branch_id'] ?? null,
                'user_id' => $user->id,
                'position' => $data['position'] ?? null,
                'permissions' => isset($data['permissions']) ? json_encode($data['permissions']) : null,
                'status' => Employee::STATUS_ACTIVE,
                'disk' => 'public',
            ]);

            // Phase 15 (docs/SECURITY_AUDIT.md): a new login-capable account is a privilege-adjacent event
            // (same class Phase 2 already logs for super-admin grants) - worth a record even though the
            // request itself just returns a normal success response.
            auditLog('employee.created', ['seller_id' => $sellerId, 'new_user_id' => $user->id, 'employee_id' => $employee->id]);

            return $employee;
        });
    }
}
