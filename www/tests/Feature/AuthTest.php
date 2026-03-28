<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Client::create([
            'name' => 'Test Personal Access Client',
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => [],
            'grant_types' => ['personal_access'],
            'revoked' => false,
        ]);
    }

    public function test_user_can_register(): void
    {
        $response = $this->postJson(route('auth.register'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'access_token',
                'token_type',
            ]);

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }

    public function test_register_requires_all_fields(): void
    {
        $response = $this->postJson(route('auth.register'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_register_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson(route('auth.register'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson(route('auth.login'), [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'access_token',
                'token_type',
            ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson(route('auth.login'), [
            'email' => 'john@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson(route('auth.login'), [
            'email' => 'nobody@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $response = $this->getJson(route('auth.user'));

        $response->assertStatus(200)
            ->assertJson(['id' => $user->id, 'email' => $user->email]);
    }

    public function test_unauthenticated_user_cannot_get_profile(): void
    {
        $response = $this->getJson(route('auth.user'));

        $response->assertStatus(401);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $response = $this->postJson(route('auth.logout'));

        $response->assertStatus(200)
            ->assertJson(['message' => 'Успішно вийшли з системи.']);
    }
}
