<?php

use App\Models\User;
use App\Services\SettingsManager;
use Database\Seeders\RbacSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('database can be seeded and contains default settings', function () {
    $this->seed(SettingSeeder::class);

    // Verify that the table is populated
    $this->assertDatabaseCount('settings', 58);

    // Verify some values
    expect(setting('general.site_name'))->toBe('LaraShop');
    expect(setting('theme.primary_color'))->toBe('#4f46e5');
});

test('unauthenticated users can retrieve public settings but not private settings', function () {
    $this->seed(SettingSeeder::class);

    // Hit the storefront endpoint
    $response = $this->getJson('/api/settings/public');
    $response->assertSuccessful();

    // Assert public settings are present
    $response->assertJsonFragment(['general.site_name' => 'LaraShop']);
    $response->assertJsonFragment(['theme.primary_color' => '#4f46e5']);

    // Assert private/sensitive settings are completely missing
    $data = $response->json();
    expect($data)->not->toHaveKey('payment.bkash');
    expect($data)->not->toHaveKey('payment.sslcommerz');
    expect($data)->not->toHaveKey('notifications.smtp');
    expect($data)->not->toHaveKey('security.recaptcha');
});

test('settings are cached and do not trigger database queries on subsequent reads', function () {
    $this->seed(SettingSeeder::class);

    // Clear any cached settings
    Cache::flush();

    // 1st request cycle simulation
    $manager1 = new SettingsManager;

    DB::enableQueryLog();
    DB::flushQueryLog(); // Ensure query log is clean
    $name1 = $manager1->get('general.site_name');
    $queries1 = DB::getQueryLog();
    DB::disableQueryLog();

    // First read must hit database
    expect($queries1)->not->toBeEmpty();
    expect($name1)->toBe('LaraShop');

    // 2nd request cycle simulation (new manager instance to bypass local memoization)
    $manager2 = new SettingsManager;

    DB::enableQueryLog();
    DB::flushQueryLog(); // Clean query log before the cached read
    $name2 = $manager2->get('general.site_name');
    $queries2 = DB::getQueryLog();
    DB::disableQueryLog();

    // Second read should be retrieved from cache and not trigger DB queries
    expect($queries2)->toBeEmpty();
    expect($name2)->toBe('LaraShop');
});

test('updating a setting invalidates the cache', function () {
    $this->seed(SettingSeeder::class);

    $manager = app(SettingsManager::class);
    expect($manager->get('general.site_name'))->toBe('LaraShop');

    // Update
    $manager->set('general.site_name', 'Updated Shop');

    // Resolve new instance to bypass request-level memoization
    $newManager = new SettingsManager;
    expect($newManager->get('general.site_name'))->toBe('Updated Shop');
});

test('setMany updates multiple settings and invalidates cache in a batch', function () {
    $this->seed(SettingSeeder::class);

    $manager = app(SettingsManager::class);

    // Batch update
    $manager->setMany([
        'general.site_name' => 'Bulk Shop Name',
        'theme.primary_color' => '#000000',
    ]);

    // Verify updated values in a new request context
    $newManager = new SettingsManager;
    expect($newManager->get('general.site_name'))->toBe('Bulk Shop Name');
    expect($newManager->get('theme.primary_color'))->toBe('#000000');
});

test('non-admin users cannot access or update admin settings', function () {
    $this->seed(SettingSeeder::class);
    $this->seed(RbacSeeder::class);

    // Unauthenticated
    $this->getJson('/api/admin/settings')->assertUnauthorized();
    $this->patchJson('/api/admin/settings', ['settings' => ['general.site_name' => 'Hack']])->assertUnauthorized();

    // Authenticated non-admin
    $user = User::factory()->create(['email' => 'customer@example.com']);
    Sanctum::actingAs($user);

    $this->getJson('/api/admin/settings')->assertForbidden();
    $this->patchJson('/api/admin/settings', ['settings' => ['general.site_name' => 'Hack']])->assertForbidden();
});

test('admin users can access and update settings', function () {
    $this->seed(SettingSeeder::class);
    $this->seed(RbacSeeder::class);

    // Admin user matching the gate definition
    $admin = User::factory()->create(['email' => 'admin@larashop.test']);
    $admin->assignRole('super-admin');
    Sanctum::actingAs($admin);

    // Get all settings
    $response = $this->getJson('/api/admin/settings');
    $response->assertSuccessful();
    $response->assertJsonFragment(['key' => 'general.site_name']);

    // Get settings by group
    $responseGroup = $this->getJson('/api/admin/settings/general');
    $responseGroup->assertSuccessful();
    $responseGroup->assertJsonFragment(['key' => 'general.site_name']);

    // Bulk update settings
    $responseUpdate = $this->patchJson('/api/admin/settings', [
        'settings' => [
            'general.site_name' => 'Admin Approved Shop',
            'theme.primary_color' => '#ffffff',
        ],
    ]);
    $responseUpdate->assertSuccessful();

    // Verify they are updated
    $newManager = new SettingsManager;
    expect($newManager->get('general.site_name'))->toBe('Admin Approved Shop');
    expect($newManager->get('theme.primary_color'))->toBe('#ffffff');
});

test('updating invalid settings keys returns validation errors', function () {
    $this->seed(SettingSeeder::class);
    $this->seed(RbacSeeder::class);

    $admin = User::factory()->create(['email' => 'admin@larashop.test']);
    $admin->assignRole('super-admin');
    Sanctum::actingAs($admin);

    $response = $this->patchJson('/api/admin/settings', [
        'settings' => [
            'general.invalid_key_name' => 'Some Value',
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('settings.general.invalid_key_name');
});
