<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissionsByModule = [
            'facilities' => ['facilities.view', 'facilities.manage'],
            'users' => ['users.view', 'users.manage'],
            'audit' => ['audit.view'],
            'patients' => ['patients.view', 'patients.create', 'patients.update', 'patients.archive'],
            'encounters' => ['encounters.view', 'encounters.create', 'encounters.update', 'encounters.sign'],
            'calendar' => ['calendar.view', 'calendar.manage'],
            'pharmacy' => ['prescriptions.view', 'prescriptions.create', 'pharmacy.dispense'],
            'orders' => ['orders.view', 'orders.create', 'orders.result'],
            'documents' => ['documents.view', 'documents.upload', 'documents.delete'],
            'billing' => ['billing.view', 'billing.manage', 'claims.manage'],
            'inventory' => ['inventory.view', 'inventory.manage'],
            'reports' => ['reports.view'],
        ];

        $permissionIds = [];
        foreach ($permissionsByModule as $module => $slugs) {
            foreach ($slugs as $slug) {
                $perm = Permission::query()->firstOrCreate(
                    ['slug' => $slug],
                    ['module' => $module, 'description' => ucfirst(str_replace('.', ' ', $slug))],
                );
                $permissionIds[$slug] = $perm->id;
            }
        }

        $roles = [
            'admin' => [
                'name' => 'Administrator',
                'description' => 'Full system administration access',
                'permissions' => array_keys($permissionIds),
            ],
            'clinician' => [
                'name' => 'Clinician / Physician',
                'description' => 'Clinical care, encounters, prescriptions, and orders',
                'permissions' => [
                    'facilities.view', 'patients.view', 'patients.create', 'patients.update',
                    'encounters.view', 'encounters.create', 'encounters.update', 'encounters.sign',
                    'calendar.view', 'calendar.manage',
                    'prescriptions.view', 'prescriptions.create',
                    'orders.view', 'orders.create', 'reports.view',
                    'documents.view', 'documents.upload', 'documents.delete',
                ],
            ],
            'nurse' => [
                'name' => 'Nurse',
                'description' => 'Patient triage, vitals, encounters, and orders',
                'permissions' => [
                    'facilities.view', 'patients.view', 'patients.update',
                    'encounters.view', 'encounters.create', 'encounters.update',
                    'calendar.view', 'prescriptions.view', 'orders.view',
                    'documents.view', 'documents.upload',
                ],
            ],
            'front_desk' => [
                'name' => 'Front Desk / Reception',
                'description' => 'Patient registration and appointment scheduling',
                'permissions' => [
                    'facilities.view', 'patients.view', 'patients.create', 'patients.update',
                    'calendar.view', 'calendar.manage', 'billing.view',
                    'documents.view', 'documents.upload',
                ],
            ],
            'billing' => [
                'name' => 'Billing & Claims',
                'description' => 'Patient charges, payments, EOB, and claims',
                'permissions' => [
                    'facilities.view', 'patients.view', 'encounters.view',
                    'billing.view', 'billing.manage', 'claims.manage', 'reports.view',
                    'documents.view', 'documents.upload',
                ],
            ],
            'lab' => [
                'name' => 'Laboratory / Imaging',
                'description' => 'Order fulfillment and diagnostic results',
                'permissions' => [
                    'facilities.view', 'patients.view', 'orders.view', 'orders.result',
                    'documents.view', 'documents.upload',
                ],
            ],
            'pharmacy' => [
                'name' => 'Pharmacy',
                'description' => 'Prescription dispensing and pharmacy inventory',
                'permissions' => [
                    'facilities.view', 'patients.view', 'prescriptions.view',
                    'pharmacy.dispense', 'inventory.view', 'inventory.manage',
                    'documents.view',
                ],
            ],
            'inventory' => [
                'name' => 'Inventory',
                'description' => 'Stock receipts and adjustments',
                'permissions' => [
                    'facilities.view', 'inventory.view', 'inventory.manage',
                ],
            ],
        ];

        foreach ($roles as $slug => $config) {
            $role = Role::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $config['name'], 'description' => $config['description']],
            );

            $ids = array_map(fn (string $p) => $permissionIds[$p], $config['permissions']);
            $role->permissions()->sync($ids);
        }
    }
}
