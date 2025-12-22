<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use App\Models\User;
use App\Models\Achievement;

class DatabaseMigrationSeedTest extends TestCase
{
    use DatabaseMigrations;

    public function test_migrations_and_seeders_run_successfully()
    {
        // Run the DatabaseSeeder (which includes AchievementSeeder and InitialDataSeeder)
        $this->artisan('db:seed')->assertExitCode(0);

        // Verify expected seeded data exists
        $this->assertGreaterThan(0, Achievement::count(), 'Achievements table should have rows after seeding');
        $this->assertGreaterThan(0, User::count(), 'Users table should have rows after seeding');
    }
}
