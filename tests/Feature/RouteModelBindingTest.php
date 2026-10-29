<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use App\Models\User;
use VtPhp\Foundation\Application;
use VtPhp\Routing\Router;
use VtPhp\Tests\Support\FeatureTestCase;

final class RouteModelBindingTest extends FeatureTestCase
{
    private User $ada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ada = User::query()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => null]);
    }

    private function routes(): \Closure
    {
        return function (Application $app): void {
            $router = $app->make(Router::class);
            $router->get('/bound/{user}', fn (User $user): array => ['email' => $user->email]);
            $router->get('/bound/{user}/raw/{note}', fn (User $user, string $note): array => [$user->name, $note]);
            $router->get('/unbound/{id}', fn (int $id): array => ['id' => $id]);
            $router->get('/find-or-fail/{id}', fn (int $id): array => User::query()->findOrFail($id)->toArray());
        };
    }

    public function test_a_model_typed_parameter_receives_the_model(): void
    {
        $response = $this->request('GET', "/bound/{$this->ada->id}", configure: $this->routes());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['email' => 'ada@example.com'], $this->body($response));
    }

    public function test_bound_and_plain_parameters_mix(): void
    {
        $this->assertSame(['Ada', 'hello'], $this->body($this->request('GET', "/bound/{$this->ada->id}/raw/hello", configure: $this->routes())));
        $this->assertSame(['id' => 7], $this->body($this->request('GET', '/unbound/7', configure: $this->routes())), 'Non-model params are untouched.');
    }

    public function test_a_missing_model_is_a_404(): void
    {
        $response = $this->request('GET', '/bound/999', configure: $this->routes());

        $this->assertErrorCode($response, 404, 'NOT_FOUND');
        $this->assertSame('User not found.', $this->body($response)['error']['message']);
    }

    public function test_a_non_numeric_id_is_a_404_not_a_database_error(): void
    {
        $this->assertErrorCode($this->request('GET', '/bound/abc', configure: $this->routes()), 404, 'NOT_FOUND');
        $this->assertErrorCode($this->request('GET', '/bound/1%20OR%201=1', configure: $this->routes()), 404, 'NOT_FOUND');
    }

    public function test_find_or_fail_is_rendered_as_a_404(): void
    {
        $response = $this->request('GET', '/find-or-fail/999', configure: $this->routes());

        $this->assertErrorCode($response, 404, 'NOT_FOUND');
        $this->assertSame('User not found.', $this->body($response)['error']['message']);
    }
}
