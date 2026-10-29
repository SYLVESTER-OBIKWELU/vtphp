<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use VtPhp\Exceptions\AuthorizationException;
use VtPhp\Exceptions\ValidationException;
use VtPhp\Foundation\Application;
use VtPhp\Foundation\CoreServiceProvider;
use VtPhp\Http\FormRequest;
use VtPhp\Http\Request;

final class StoreArticleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:20',
            'status' => 'sometimes|in:draft,published',
        ];
    }
}

final class ForbiddenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return ['title' => 'required'];
    }
}

final class ArticleController
{
    /**
     * @return array<string, mixed>
     */
    public function store(StoreArticleRequest $request): array
    {
        return $request->validated();
    }
}

final class FormRequestTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application(dirname(__DIR__, 2));
        $this->app->instance(\VtPhp\Config\Repository::class, new \VtPhp\Config\Repository([
            'database' => ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'pdo_sqlite', 'path' => ':memory:']]],
        ]));
        $this->app->register(CoreServiceProvider::class);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function bindRequest(array $body): void
    {
        $factory = new Psr17Factory();
        $psr = $factory->createServerRequest('POST', 'http://localhost/articles')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream(json_encode($body, JSON_THROW_ON_ERROR)));

        $this->app->instance(ServerRequestInterface::class, $psr);
    }

    public function test_a_form_request_is_validated_when_injected(): void
    {
        $this->bindRequest(['title' => 'Hello', 'status' => 'draft', 'author_id' => 999]);

        $result = $this->app->call([ArticleController::class, 'store']);

        $this->assertSame(['title' => 'Hello', 'status' => 'draft'], $result, 'Unvalidated keys are dropped.');
    }

    public function test_invalid_input_stops_before_the_action_runs(): void
    {
        $this->bindRequest(['title' => str_repeat('x', 50), 'status' => 'archived']);

        try {
            $this->app->call([ArticleController::class, 'store']);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame(['title', 'status'], array_keys($e->getErrors()));
        }
    }

    public function test_an_unauthorized_request_is_rejected_with_403(): void
    {
        $this->bindRequest(['title' => 'Hello']);

        $this->expectException(AuthorizationException::class);

        $this->app->make(ForbiddenRequest::class);
    }

    public function test_validated_reads_a_single_key_with_a_default(): void
    {
        $this->bindRequest(['title' => 'Hello']);
        $request = $this->app->make(StoreArticleRequest::class);

        $this->assertSame('Hello', $request->validated('title'));
        $this->assertSame('draft', $request->validated('status', 'draft'));
        $this->assertSame('Hello', $request->input('title'), 'A FormRequest is still a Request.');
    }

    public function test_request_validate_returns_only_validated_fields(): void
    {
        $factory = new Psr17Factory();
        $request = Request::fromPsr($factory->createServerRequest('GET', '/')->withQueryParams(['q' => 'php', 'debug' => '1']));

        $this->assertSame(['q' => 'php'], $request->validate(['q' => 'required|string']));

        $this->expectException(ValidationException::class);
        $request->validate(['page' => 'required|integer']);
    }
}
