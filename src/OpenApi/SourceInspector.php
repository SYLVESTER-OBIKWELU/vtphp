<?php

declare(strict_types=1);

namespace VtPhp\OpenApi;

/**
 * Reads a controller method's source code for what reflection can't tell:
 * which resource it returns, the status codes it uses, whether it paginates,
 * and the shape of literal `response()->json([...])` payloads.
 */
final class SourceInspector
{
    /** @var array<string, array<string, string>> file => short name => class */
    private array $imports = [];

    public function body(\ReflectionFunctionAbstract $function): string
    {
        $file = $function->getFileName();

        if ($file === false || !is_file($file)) {
            return '';
        }

        $lines = file($file) ?: [];

        return implode('', array_slice($lines, (int) $function->getStartLine() - 1, (int) $function->getEndLine() - (int) $function->getStartLine() + 1));
    }

    /**
     * Resolves a class name as written in $context's file (`UserResource`,
     * `Resources\UserResource`, `\App\...`) to its full name.
     *
     * @template T of object
     *
     * @param \ReflectionClass<T> $context
     */
    public function resolveClass(string $name, \ReflectionClass $context): ?string
    {
        if (str_starts_with($name, '\\')) {
            return class_exists($name) ? ltrim($name, '\\') : null;
        }

        [$first] = explode('\\', $name, 2);
        $imports = $this->imports((string) $context->getFileName());

        $candidates = isset($imports[$first])
            ? [$imports[$first].substr($name, strlen($first))]
            : [$context->getNamespaceName().'\\'.$name, $name];

        foreach ($candidates as $candidate) {
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array{summary: ?string, description: ?string}
     */
    public static function docComment(string|false $comment): array
    {
        if ($comment === false) {
            return ['summary' => null, 'description' => null];
        }

        $text = [];

        foreach (preg_split('/\R/', $comment) ?: [] as $line) {
            $line = preg_replace('#^\s*/?\*+/?\s?#', '', $line) ?? '';

            if (str_starts_with(ltrim($line), '@')) {
                break;
            }

            $text[] = rtrim($line);
        }

        $paragraphs = array_values(array_filter(
            array_map('trim', preg_split('/\n\s*\n/', trim(implode("\n", $text))) ?: []),
            static fn (string $p): bool => $p !== '',
        ));

        if ($paragraphs === []) {
            return ['summary' => null, 'description' => null];
        }

        $summary = preg_replace('/\s+/', ' ', array_shift($paragraphs));

        return [
            'summary' => $summary,
            'description' => $paragraphs !== [] ? implode("\n\n", $paragraphs) : null,
        ];
    }

    /**
     * Statements (split on `;`) of a method body.
     *
     * @return list<string>
     */
    public function statements(string $body): array
    {
        $statements = [];
        $current = '';
        $depth = 0;

        foreach (\PhpToken::tokenize('<?php '.$body) as $token) {
            if ($token->id === T_OPEN_TAG) {
                continue;
            }

            $text = $token->text;
            $depth += match ($text) {
                '(', '[' => 1,
                ')', ']' => -1,
                default => 0,
            };

            if (($text === ';' || $text === '{' || $text === '}') && $depth === 0) {
                if (trim($current) !== '') {
                    $statements[] = trim($current);
                }
                $current = '';

                continue;
            }

            $current .= $text;
        }

        if (trim($current) !== '') {
            $statements[] = trim($current);
        }

        return $statements;
    }

    /**
     * Describes a PHP array literal as a JSON schema: string, number and
     * boolean literals give typed (and example) properties, anything
     * computed is left open.
     *
     * @return array<string, mixed>|null
     */
    public function literalSchema(string $code): ?array
    {
        $tokens = array_values(array_filter(
            \PhpToken::tokenize('<?php '.$code),
            static fn (\PhpToken $t): bool => !$t->isIgnorable() && $t->id !== T_OPEN_TAG,
        ));
        $position = 0;

        if (($tokens[0]->text ?? '') !== '[') {
            return null;
        }

        return $this->parseValue($tokens, $position);
    }

    /**
     * @param list<\PhpToken> $tokens
     * @return array<string, mixed>
     */
    private function parseValue(array $tokens, int &$i): array
    {
        $token = $tokens[$i] ?? null;

        if ($token === null) {
            return [];
        }

        if ($token->text === '[') {
            return $this->parseArray($tokens, $i);
        }

        // A single literal followed by `,` or `]` has a known type.
        $next = $tokens[$i + 1]->text ?? '';
        $simple = in_array($next, [',', ']'], true);
        $schema = match (true) {
            $simple && $token->id === T_CONSTANT_ENCAPSED_STRING => ['type' => 'string', 'example' => stripcslashes(substr($token->text, 1, -1))],
            $simple && $token->id === T_LNUMBER => ['type' => 'integer', 'example' => (int) $token->text],
            $simple && $token->id === T_DNUMBER => ['type' => 'number', 'example' => (float) $token->text],
            $simple && in_array(strtolower($token->text), ['true', 'false'], true) => ['type' => 'boolean', 'example' => strtolower($token->text) === 'true'],
            default => [],
        };

        // Skip to the end of this value.
        $depth = 0;

        for (; $i < count($tokens); ++$i) {
            $text = $tokens[$i]->text;

            if ($depth === 0 && ($text === ',' || $text === ']')) {
                break;
            }

            $depth += match ($text) {
                '(', '[', '{' => 1,
                ')', ']', '}' => -1,
                default => 0,
            };
        }

        return $schema;
    }

    /**
     * @param list<\PhpToken> $tokens
     * @return array<string, mixed>
     */
    private function parseArray(array $tokens, int &$i): array
    {
        ++$i; // [
        $properties = [];
        $items = [];

        while ($i < count($tokens) && $tokens[$i]->text !== ']') {
            $key = null;

            if ($tokens[$i]->id === T_CONSTANT_ENCAPSED_STRING && ($tokens[$i + 1]->text ?? '') === '=>') {
                $key = stripcslashes(substr($tokens[$i]->text, 1, -1));
                $i += 2;
            }

            $value = $this->parseValue($tokens, $i);

            if ($key !== null) {
                $properties[$key] = $value === [] ? new \stdClass() : $value;
            } else {
                $items[] = $value;
            }

            if (($tokens[$i]->text ?? '') === ',') {
                ++$i;
            }
        }

        ++$i; // ]

        if ($properties !== []) {
            return ['type' => 'object', 'properties' => $properties];
        }

        return ['type' => 'array', 'items' => $items[0] ?? new \stdClass()];
    }

    /**
     * @return array<string, string>
     */
    private function imports(string $file): array
    {
        if (isset($this->imports[$file])) {
            return $this->imports[$file];
        }

        $imports = [];
        $code = is_file($file) ? (string) file_get_contents($file) : '';
        // Only the file header: `use` inside a class body imports traits.
        $header = preg_split('/^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s/m', $code, 2)[0] ?? '';

        preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m', $header, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $alias = ($match[2] ?? '') !== '' ? $match[2] : substr((string) strrchr('\\'.$match[1], '\\'), 1);
            $imports[$alias] = $match[1];
        }

        return $this->imports[$file] = $imports;
    }
}
