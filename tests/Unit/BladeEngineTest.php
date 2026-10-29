<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VtPhp\Config\Repository;
use VtPhp\Tests\Support\TempDirectory;
use VtPhp\View\BladeEngine;

final class BladeEngineTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = TempDirectory::create('vtphp-view');
        mkdir($this->tmp.'/views/partials', 0777, true);
    }

    protected function tearDown(): void
    {
        TempDirectory::delete($this->tmp);
    }

    /**
     * @param list<string>|null $paths
     */
    private function engine(?array $paths = null): BladeEngine
    {
        return new BladeEngine(new Repository(['view' => [
            'paths' => $paths ?? [$this->tmp.'/views'],
            'compiled' => $this->tmp.'/compiled',
        ]]));
    }

    public function test_it_escapes_output_by_default_and_allows_raw_output(): void
    {
        file_put_contents($this->tmp.'/views/hello.blade.php', 'Hi {{ $name }} / {!! $html !!}');

        $this->assertSame(
            'Hi &lt;script&gt;alert(1)&lt;/script&gt; / <b>bold</b>',
            $this->engine()->render('hello', ['name' => '<script>alert(1)</script>', 'html' => '<b>bold</b>']),
        );
    }

    public function test_control_structures_and_dot_notation_paths(): void
    {
        file_put_contents(
            $this->tmp.'/views/partials/list.blade.php',
            "@if (\$items)\n@foreach (\$items as \$item)\n[{{ \$item }}]\n@endforeach\n@else\nempty\n@endif\n",
        );

        $this->assertSame('[a][b]', preg_replace('/\s+/', '', $this->engine()->render('partials.list', ['items' => ['a', 'b']])));
        $this->assertSame('empty', trim($this->engine()->render('partials.list', ['items' => []])));
    }

    public function test_the_compiled_directory_is_created(): void
    {
        file_put_contents($this->tmp.'/views/hello.blade.php', 'ok');
        $this->engine()->render('hello');

        $this->assertDirectoryExists($this->tmp.'/compiled');
    }

    public function test_a_missing_view_raises(): void
    {
        $this->expectException(\Throwable::class);

        $this->engine()->render('does.not.exist');
    }

    public function test_the_shipped_email_templates_render(): void
    {
        $engine = $this->engine([__DIR__.'/../../resources/views']);
        $user = (object) ['name' => 'Ada <Admin>', 'email' => 'ada@example.com'];

        $welcome = $engine->render('emails.welcome', ['user' => $user]);
        $this->assertStringContainsString('Welcome, Ada &lt;Admin&gt;!', $welcome);
        $this->assertStringContainsString('ada@example.com', $welcome);

        $reset = $engine->render('emails.reset-password', ['user' => $user, 'token' => 't', 'url' => 'https://x/reset?token=t&email=a']);
        $this->assertStringContainsString('href="https://x/reset?token=t&amp;email=a"', $reset);

        $verify = $engine->render('emails.verify-email', ['user' => $user, 'url' => 'https://x/verify/1/abc']);
        $this->assertStringContainsString('href="https://x/verify/1/abc"', $verify);
    }
}
