<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VtPhp\Cookie\Cookie;
use VtPhp\Cookie\CookieJar;

final class CookieTest extends TestCase
{
    public function test_a_session_cookie_header_has_safe_defaults(): void
    {
        $this->assertSame(
            'theme=dark%20mode; Path=/; HttpOnly; SameSite=Lax',
            (new Cookie('theme', 'dark mode'))->toHeaderValue(),
        );
    }

    public function test_a_persistent_cookie_includes_expires_and_max_age(): void
    {
        $header = (new Cookie('remember', 'yes', expires: time() + 3600))->toHeaderValue();

        $this->assertMatchesRegularExpression('/Expires=\w{3}, \d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2} GMT/', $header);
        $this->assertMatchesRegularExpression('/Max-Age=(3599|3600)\b/', $header);
    }

    public function test_domain_secure_and_same_site_none_are_rendered(): void
    {
        $header = (new Cookie('id', 'x', domain: 'example.com', secure: true, httpOnly: false, sameSite: 'none'))->toHeaderValue();

        $this->assertStringContainsString('Domain=example.com', $header);
        $this->assertStringContainsString('; Secure', $header);
        $this->assertStringContainsString('SameSite=None', $header);
        $this->assertStringNotContainsString('HttpOnly', $header);
    }

    public function test_an_expired_cookie_reports_zero_max_age(): void
    {
        $this->assertStringContainsString('Max-Age=0', (new Cookie('gone', '', expires: time() - 60))->toHeaderValue());
    }

    public function test_jar_converts_minutes_to_an_expiry_timestamp(): void
    {
        $jar = new CookieJar();

        $this->assertSame(0, $jar->make('session', 'v', 0, secure: false)->expires);

        $expires = $jar->make('remember', 'v', 10, secure: false)->expires;
        $this->assertEqualsWithDelta(time() + 600, $expires, 2);
    }

    public function test_jar_queues_cookies_keyed_by_name(): void
    {
        $jar = new CookieJar();
        $jar->queue($jar->make('a', '1', secure: false));
        $jar->queue($jar->make('b', '2', secure: false));
        $jar->queue($jar->make('a', '3', secure: false));

        $queued = $jar->queued();
        $this->assertSame(['a', 'b'], array_keys($queued));
        $this->assertSame('3', $queued['a']->value);
    }

    public function test_forget_queues_an_expired_blank_cookie(): void
    {
        $jar = new CookieJar();
        $jar->forget('session');

        $cookie = $jar->queued()['session'];
        $this->assertSame('', $cookie->value);
        $this->assertLessThan(time(), $cookie->expires);
    }
}
