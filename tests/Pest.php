<?php

declare(strict_types=1);

/**
 * Global IDE type-hinting wrapper for Pest closures.
 *
 * @mixin TestCase
 *
 * @property User $user
 * @property Idea $idea
 * @property string $keypair
 * @property string $publicKey
 * @property string $secretKey
 * @property DiscordWebhookProvider $provider
 */

use App\Models\Idea;
use App\Models\User;
use App\Webhooks\DiscordWebhookProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Browser', 'Unit', 'Feature');

// pest()->extend(TestCase::class)
// ->use(RefreshDatabase::class)
//  ->in('Feature');
/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/
function actingAsAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

function actingAsRegularUser(): User
{
    return User::factory()->create(['is_admin' => false]);
}
function something(): void
{
    // ..
}
