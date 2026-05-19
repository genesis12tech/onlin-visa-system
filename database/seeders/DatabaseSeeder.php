<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SuperAdminSeeder::class,     // env-based super admin — production use
            CountrySeeder::class,        // reference data — no dependencies
            DocumentTypeSeeder::class,   // reference data — no dependencies
            VisaTypeSeeder::class,       // depends on countries + document types
            VisaFeeSeeder::class,        // depends on visa types
            FormTemplateSeeder::class,   // depends on visa types
            RoleSeeder::class,           // roles must exist before users are assigned
            UserSeeder::class,           // staff + demo accounts with roles assigned
            VisaApplicationSeeder::class, // sample applications + daily metrics
        ]);
    }
}
