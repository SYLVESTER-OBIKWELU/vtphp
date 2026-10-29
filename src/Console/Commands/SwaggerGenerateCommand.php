<?php

declare(strict_types=1);

namespace VtPhp\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use VtPhp\Console\Command;
use VtPhp\OpenApi\OpenApiGenerator;

/**
 * Writes the OpenAPI document to `swagger.output`, for serving a fixed copy
 * (SWAGGER_GENERATE_ALWAYS=false) or handing to other tools (Postman, client
 * generators).
 */
#[AsCommand(name: 'swagger:generate', description: 'Write the OpenAPI (Swagger) document to a file')]
final class SwaggerGenerateCommand extends Command
{
    protected function handle(): int
    {
        $relative = (string) $this->app->make('config')->get('swagger.output', 'storage/api-docs/openapi.json');
        $path = $this->app->basePath($relative);
        $document = $this->app->make(OpenApiGenerator::class)->generate();

        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");

        $count = 0;

        foreach ((array) ($document['paths'] ?? []) as $operations) {
            $count += count((array) $operations);
        }

        $this->info("API docs written to {$relative} ({$count} operations).");

        return self::SUCCESS;
    }
}
