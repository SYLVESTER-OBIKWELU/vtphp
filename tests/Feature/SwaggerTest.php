<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use VtPhp\Foundation\Application;
use VtPhp\Http\FormRequest;
use VtPhp\Http\JsonResponse;
use VtPhp\Middleware\Authenticate;
use VtPhp\Middleware\Can;
use VtPhp\OpenApi\Attributes\ApiDoc;
use VtPhp\Resources\JsonResource;
use VtPhp\Routing\Router;
use VtPhp\Tests\Support\FeatureTestCase;

final class DocsNote extends Model
{
    protected $casts = ['done' => 'boolean', 'due_on' => 'datetime', 'price' => 'decimal:2'];
}

/**
 * @property DocsNote $resource
 */
final class DocsNoteResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'done' => $this->resource->done,
            'due_on' => $this->resource->due_on,
            'price' => $this->resource->price,
            'is_pinned' => $this->resource->is_pinned,
        ];
    }
}

final class SearchNotesRequest extends FormRequest
{
    public function rules(): array
    {
        return ['q' => 'required|string|min:2', 'status' => 'in:open,done'];
    }
}

final class StoreNoteRequest extends FormRequest
{
    public function rules(): array
    {
        return ['title' => 'required|string|max:120', 'tags.*' => 'string'];
    }
}

#[ApiDoc(tags: ['Notes from the class'])]
final class DocsNoteController
{
    /**
     * Search notes.
     *
     * Matches the title and the body.
     */
    public function search(SearchNotesRequest $request): JsonResponse
    {
        return response()->json(['data' => [], 'count' => 0]);
    }

    public function store(StoreNoteRequest $request): DocsNoteResource
    {
        return DocsNoteResource::make(new DocsNote())->status(201);
    }

    #[ApiDoc(summary: 'Archive a note', tags: ['Archive'], deprecated: true, responses: [409 => 'Already archived.'])]
    public function archive(int $note): JsonResponse
    {
        if ($note === 0) {
            return response()->json(['message' => 'Nope', 'code' => 'ARCHIVE_FAILED', 'retry' => false], 409);
        }

        return response()->noContent();
    }

    #[ApiDoc(hidden: true)]
    public function secret(): JsonResponse
    {
        return response()->noContent();
    }
}

final class SwaggerTest extends FeatureTestCase
{
    private function enable(array $extra = []): void
    {
        $this->setEnvironment(['SWAGGER_ENABLED' => 'true'] + $extra);
    }

    private static function addNoteRoutes(Application $app): void
    {
        $router = $app->make(Router::class);
        $router->group(['prefix' => '/api/v1/notes', 'tag' => 'Notes', 'middleware' => [Authenticate::using('api')]], function (Router $router): void {
            $router->get('/search', [DocsNoteController::class, 'search'], 'notes.search');
            $router->post('/', [DocsNoteController::class, 'store'], 'notes.store', [Can::using('createNotes')]);
            $router->post('/{note}/archive', [DocsNoteController::class, 'archive'], 'notes.archive');
            $router->get('/secret', [DocsNoteController::class, 'secret'], 'notes.secret');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function spec(?\Closure $configure = null): array
    {
        $response = $this->request('GET', '/api-docs/openapi.json', configure: $configure);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return $this->body($response);
    }

    public function test_the_docs_are_off_by_default(): void
    {
        $this->assertSame(404, $this->request('GET', '/api-docs')->getStatusCode());
        $this->assertSame(404, $this->request('GET', '/api-docs/openapi.json')->getStatusCode());
    }

    public function test_the_swagger_ui_page(): void
    {
        $this->enable();
        $response = $this->request('GET', '/api-docs');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('text/html', $response->getHeaderLine('Content-Type'));
        $html = (string) $response->getBody();
        $this->assertStringContainsString('swagger-ui-bundle.js', $html);
        $this->assertStringContainsString('url: "/api-docs/openapi.json"', $html);

        preg_match('/nonce="([^"]+)"/', $html, $nonce);
        $csp = $response->getHeaderLine('Content-Security-Policy');
        $this->assertStringContainsString("'nonce-{$nonce[1]}'", $csp, 'Only this page\'s own inline script may run.');
        $this->assertStringContainsString("connect-src 'self'", $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
    }

    public function test_the_path_is_configurable(): void
    {
        $this->enable(['SWAGGER_PATH' => '/docs']);

        $this->assertSame(404, $this->request('GET', '/api-docs')->getStatusCode());
        $this->assertSame(200, $this->request('GET', '/docs')->getStatusCode());
        $this->assertSame(200, $this->request('GET', '/docs/openapi.json')->getStatusCode());
    }

    public function test_the_sample_app_is_described(): void
    {
        $this->enable();
        $spec = $this->spec();

        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertSame('VtPhp API', $spec['info']['title']);
        $this->assertSame(
            ['Health', 'Users', 'Authentication', 'API tokens', 'Password reset', 'Email verification'],
            array_column($spec['tags'], 'name'),
            'Sections come from the route groups, in order.',
        );
        $this->assertArrayNotHasKey('/api-docs', $spec['paths'], 'The docs do not document themselves.');

        $store = $spec['paths']['/api/v1/users']['post'];
        $this->assertSame(['Users'], $store['tags']);
        $this->assertSame('Create a user', $store['summary']);
        $this->assertSame('#/components/schemas/StoreUserRequest', $store['requestBody']['content']['application/json']['schema']['$ref']);
        $this->assertSame(['name', 'email', 'password', 'password_confirmation'], $spec['components']['schemas']['StoreUserRequest']['required']);
        $this->assertSame([201, 422, 429], array_keys($store['responses']));
        $this->assertSame('#/components/schemas/User', $store['responses']['201']['content']['application/json']['schema']['properties']['data']['$ref']);

        $user = $spec['components']['schemas']['User']['properties'];
        $this->assertSame(['type' => 'integer'], $user['id']);
        $this->assertSame(['type' => 'string', 'format' => 'email'], $user['email']);
        $this->assertSame(['type' => 'boolean'], $user['email_verified']);
        $this->assertSame('date-time', $user['created_at']['format']);

        $index = $spec['paths']['/api/v1/users']['get'];
        $this->assertSame([['cookieAuth' => []], ['bearerAuth' => []]], $index['security']);
        $this->assertSame(['page', 'per_page'], array_column($index['parameters'], 'name'));
        $this->assertSame('#/components/schemas/PaginationMeta', $index['responses']['200']['content']['application/json']['schema']['properties']['meta']['$ref']);
        $this->assertSame(['bearer', 'vtphp_session'], [$spec['components']['securitySchemes']['bearerAuth']['scheme'], $spec['components']['securitySchemes']['cookieAuth']['name']]);

        $update = $spec['paths']['/api/v1/users/{user}']['patch'];
        $this->assertSame(['name' => 'user', 'in' => 'path', 'required' => true, 'description' => 'The User id.', 'schema' => ['type' => 'integer']], $update['parameters'][0]);
        $this->assertSame([200, 401, 403, 404, 422, 429], array_keys($update['responses']), '403 because UpdateUserRequest::authorize() is overridden.');
        $this->assertSame('users.update.patch', $update['operationId']);
        $this->assertSame([204, 401, 403, 404, 429], array_keys($spec['paths']['/api/v1/users/{user}']['delete']['responses']), '403 from $this->authorize().');

        $token = $spec['paths']['/api/v1/tokens']['post'];
        $this->assertSame('Exchange an email and password for a new token.', $token['summary']);
        $created = $token['responses']['201']['content']['application/json']['schema']['properties']['data']['properties'];
        $this->assertSame(['type' => 'string', 'example' => 'Bearer'], $created['type']);
        $this->assertSame('These credentials do not match our records.', $token['responses']['401']['content']['application/json']['schema']['properties']['message']['example']);

        $health = $spec['paths']['/health']['get']['responses']['200']['content']['application/json']['schema'];
        $this->assertSame(['status' => ['type' => 'string'], 'version' => ['type' => 'string']], $health['properties'], 'From @return array{...}.');
    }

    public function test_custom_routes_attributes_and_query_parameters(): void
    {
        $this->enable();
        $spec = $this->spec(static fn (Application $app) => self::addNoteRoutes($app));

        $search = $spec['paths']['/api/v1/notes/search']['get'];
        $this->assertSame(['Notes from the class'], $search['tags'], 'A class-level #[ApiDoc] wins over the group.');
        $this->assertSame('Search notes.', $search['summary']);
        $this->assertSame('Matches the title and the body.', $search['description']);
        $this->assertSame([['bearerAuth' => []]], $search['security']);
        $this->assertSame(
            [['name' => 'q', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'minLength' => 2]], ['name' => 'status', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'enum' => ['open', 'done']]]],
            $search['parameters'],
            'GET form requests become query parameters.',
        );
        $this->assertSame(['type' => 'array', 'items' => []], $search['responses']['200']['content']['application/json']['schema']['properties']['data']);

        $store = $spec['paths']['/api/v1/notes']['post'];
        $this->assertSame([201, 401, 403, 422], array_keys($store['responses']), '201 from ->status(201), 403 from Can.');
        $this->assertSame(['type' => 'array', 'items' => ['type' => 'string']], $spec['components']['schemas']['StoreNoteRequest']['properties']['tags']);
        $note = $spec['components']['schemas']['DocsNote']['properties'];
        $this->assertSame(['boolean', 'number', 'boolean'], [$note['done']['type'], $note['price']['type'], $note['is_pinned']['type']], 'Typed from casts and names.');
        $this->assertSame('date-time', $note['due_on']['format']);

        $archive = $spec['paths']['/api/v1/notes/{note}/archive']['post'];
        $this->assertSame(['Archive'], $archive['tags']);
        $this->assertSame('Archive a note', $archive['summary']);
        $this->assertTrue($archive['deprecated']);
        $this->assertSame(['type' => 'integer'], $archive['parameters'][0]['schema']);
        $this->assertSame([204, 401, 404, 409], array_keys($archive['responses']));
        $this->assertSame(['type' => 'boolean', 'example' => false], $archive['responses']['409']['content']['application/json']['schema']['properties']['retry']);

        $this->assertArrayNotHasKey('/api/v1/notes/secret', $spec['paths'], '#[ApiDoc(hidden: true)]');
    }

    public function test_tags_on_api_resources_and_excluded_paths(): void
    {
        $this->enable();
        $spec = $this->spec(function (Application $app): void {
            $app->make('config')->set('swagger.exclude', ['/health']);
            $app->make('config')->set('swagger.tags', ['Accounts' => 'Everything about accounts.']);
            $router = $app->make(Router::class);
            $router->group(['tag' => 'Outer'], function (Router $router): void {
                $router->apiResource('/admin/accounts', \App\Http\Controllers\UserController::class, ['only' => ['index'], 'tag' => 'Accounts']);
                $router->get('/admin/ping', static fn (): array => ['ok' => true], 'admin.ping');
            });
        });

        $this->assertArrayNotHasKey('/health', $spec['paths']);
        $this->assertSame(['Accounts'], $spec['paths']['/admin/accounts']['get']['tags']);
        $this->assertSame(['Outer'], $spec['paths']['/admin/ping']['get']['tags'], 'Closures are listed too.');
        $this->assertContains(['name' => 'Accounts', 'description' => 'Everything about accounts.'], $spec['tags']);
    }

    public function test_swagger_generate_writes_a_file_that_can_be_served(): void
    {
        $this->enable(['SWAGGER_GENERATE_ALWAYS' => 'false']);
        $output = 'storage/api-docs/test-'.bin2hex(random_bytes(4)).'.json';
        $this->app->make('config')->set('swagger.output', $output);
        $path = $this->app->basePath($output);

        try {
            [$code, $console] = $this->forge(['command' => 'swagger:generate']);
            $this->assertSame(0, $code);
            $this->assertStringContainsString('operations', $console);

            $document = json_decode((string) file_get_contents($path), true);
            $document['info']['title'] = 'From the file';
            file_put_contents($path, json_encode($document));

            $spec = $this->spec(static function (Application $app) use ($output): void {
                $app->make('config')->set('swagger.output', $output);
            });
            $this->assertSame('From the file', $spec['info']['title']);
        } finally {
            @unlink($path);
        }
    }
}
