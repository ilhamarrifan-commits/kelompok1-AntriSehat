<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Akun demo untuk tugas besar. Ganti kata sandinya jika aplikasi dibuka ke publik.
        $accounts = [
            ['name' => 'Admin Klinik', 'email' => 'admin@antrisehat.test', 'password' => 'admin12345', 'role' => 'admin'],
            ['name' => 'Pasien Demo', 'email' => 'pasien@antrisehat.test', 'password' => 'pasien12345', 'role' => 'pasien'],
        ];

        foreach ($accounts as $account) {
            // forceFill agar kolom role tersimpan tanpa mengubah model User.
            User::firstOrNew(['email' => $account['email']])
                ->forceFill([
                    'name' => $account['name'],
                    'password' => Hash::make($account['password']),
                    'role' => $account['role'],
                ])
                ->save();
        }
    }
}
