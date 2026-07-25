<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A personal access client is a prerequisite for createToken(); the
        // real bootstrap/CI path creates it too (see Makefile / CI smoke test).
        Client::create([
            'name' => 'Test Personal Access Client',
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => [],
            'grant_types' => ['personal_access'],
            'revoked' => false,
        ]);
    }

    // ---------------------------------------------------------------------
    // Registration
    // ---------------------------------------------------------------------

    public function test_user_can_register_and_receives_a_working_token(): void
    {
        $response = $this->postJson(route('auth.register'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'token_type',
                    'expires_at',
                    'user' => ['id', 'name', 'email', 'created_at'],
                ],
            ])
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'john@example.com')
            ->assertJsonMissingPath('data.user.password');

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);

        // The issued token must actually authenticate a protected request.
        $token = $response->json('data.access_token');
        $this->withToken($token)->getJson(route('auth.user'))->assertOk();
    }

    public function test_registration_token_has_an_expiry(): void
    {
        $response = $this->postJson(route('auth.register'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertNotNull($response->json('data.expires_at'));
    }

    public function test_register_requires_all_fields(): void
    {
        $this->postJson(route('auth.register'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_register_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $this->postJson(route('auth.register'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_normalizes_email_case_and_whitespace(): void
    {
        $this->postJson(route('auth.register'), [
            'name' => 'John Doe',
            'email' => '  John@Example.COM ',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
        $this->assertDatabaseMissing('users', ['email' => '  John@Example.COM ']);
    }

    public function test_register_uniqueness_is_case_insensitive(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $this->postJson(route('auth.register'), [
            'name' => 'John Doe',
            'email' => 'JOHN@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_rejects_invalid_email(): void
    {
        $this->postJson(route('auth.register'), [
            'name' => 'John Doe',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_rejects_unconfirmed_password(): void
    {
        $this->postJson(route('auth.register'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different456',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[DataProvider('weakPasswords')]
    public function test_register_rejects_weak_passwords(string $password): void
    {
        $this->postJson(route('auth.register'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function weakPasswords(): array
    {
        return [
            'too short' => ['pass12'],
            'letters only' => ['passwordonly'],
            'numbers only' => ['12345678'],
        ];
    }

    // ---------------------------------------------------------------------
    // Login
    // ---------------------------------------------------------------------

    public function test_user_can_login(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson(route('auth.login'), [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['access_token', 'token_type', 'expires_at', 'user' => ['id', 'name', 'email']],
            ]);

        $token = $response->json('data.access_token');
        $this->withToken($token)->getJson(route('auth.user'))->assertOk();
    }

    public function test_login_is_case_insensitive_for_email(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $this->postJson(route('auth.login'), [
            'email' => 'JOHN@Example.com',
            'password' => 'password123',
        ])->assertOk();
    }

    public function test_login_does_not_start_a_server_session(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson(route('auth.login'), [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        // Stateless auth: no session cookie, and the web (session) guard stays a guest.
        $sessionCookie = (string) config('session.cookie');
        $cookieNames = collect($response->headers->getCookies())->map->getName();
        $this->assertFalse($cookieNames->contains($sessionCookie));
        $this->assertGuest('web');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $this->postJson(route('auth.login'), [
            'email' => 'john@example.com',
            'password' => 'wrongpassword',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $this->postJson(route('auth.login'), [
            'email' => 'nobody@example.com',
            'password' => 'password123',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // ---------------------------------------------------------------------
    // Profile
    // ---------------------------------------------------------------------

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->accessToken;

        $this->withToken($token)->getJson(route('auth.user'))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    }

    public function test_unauthenticated_user_cannot_get_profile(): void
    {
        $this->getJson(route('auth.user'))->assertStatus(401);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withToken('not-a-real-token')
            ->getJson(route('auth.user'))
            ->assertStatus(401);
    }

    // ---------------------------------------------------------------------
    // Logout / revocation
    // ---------------------------------------------------------------------

    public function test_logout_revokes_the_token_and_blocks_further_access(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $token = $this->postJson(route('auth.login'), [
            'email' => 'john@example.com',
            'password' => 'password123',
        ])->json('data.access_token');

        // The token works before logout.
        $this->withToken($token)->getJson(route('auth.user'))->assertOk();

        $this->withToken($token)->postJson(route('auth.logout'))->assertOk();

        // The token record is revoked in the database...
        $this->assertDatabaseHas('oauth_access_tokens', [
            'user_id' => $user->id,
            'revoked' => true,
        ]);
        $this->assertDatabaseMissing('oauth_access_tokens', [
            'user_id' => $user->id,
            'revoked' => false,
        ]);

        // The api guard memoizes the resolved user within a single test process;
        // a real HTTP request is stateless, so forget guards to re-resolve.
        $this->app['auth']->forgetGuards();

        // ...and the same token can no longer reach a protected endpoint.
        $this->withToken($token)->getJson(route('auth.user'))->assertStatus(401);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson(route('auth.logout'))->assertStatus(401);
    }

    // ---------------------------------------------------------------------
    // Rate limiting
    // ---------------------------------------------------------------------

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $payload = ['email' => 'john@example.com', 'password' => 'wrongpassword'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('auth.login'), $payload)->assertStatus(422);
        }

        $this->postJson(route('auth.login'), $payload)->assertStatus(429);
    }

    public function test_register_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            // Invalid bodies still consume the limiter (throttle runs before validation).
            $this->postJson(route('auth.register'), [])->assertStatus(422);
        }

        $this->postJson(route('auth.register'), [])->assertStatus(429);
    }
}
