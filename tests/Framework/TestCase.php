<?php

declare(strict_types=1);

abstract class TestCase
{
    protected int $assertionCount = 0;
    private array $results = [];
    private bool $currentFailed = false;

    public function name(): string
    {
        return static::class;
    }

    protected function setUp(): void
    {
    }

    protected function tearDown(): void
    {
    }

    public function execute(): array
    {
        $methods = $this->collectTestMethods();
        foreach ($methods as $method) {
            $this->currentFailed = false;
            $this->assertionCount = 0;
            StateReset::resetAll();
            $this->resetServer();
            $this->setUp();
            $startTime = microtime(true);
            $error = null;
            try {
                $this->{$method}();
            } catch (AssertionFailedException $e) {
                $error = $e->getMessage();
                $this->currentFailed = true;
            } catch (Throwable $e) {
                $error = sprintf('%s: %s (code %d) in %s:%d',
                    get_class($e),
                    $e->getMessage(),
                    $e->getCode(),
                    basename($e->getFile()),
                    $e->getLine()
                );
                $this->currentFailed = true;
            }
            $elapsed = round((microtime(true) - $startTime) * 1000, 2);
            $this->tearDown();
            $this->results[] = [
                'method' => $method,
                'passed' => !$this->currentFailed,
                'assertions' => $this->assertionCount,
                'error' => $error,
                'elapsed_ms' => $elapsed,
            ];
        }
        return $this->results;
    }

    private function collectTestMethods(): array
    {
        $methods = [];
        $ref = new ReflectionClass($this);
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
            if (str_starts_with($m->getName(), 'test_')) {
                $methods[] = $m->getName();
            }
        }
        sort($methods);
        return $methods;
    }

    private function resetServer(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/customer/list',
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'CRM-UnitTest/1.0',
            'HTTP_ACCEPT_LANGUAGE' => 'zh-CN',
            'HTTP_ACCEPT_ENCODING' => 'gzip',
        ];
    }

    protected function mockRequest(array $overrides = []): void
    {
        $this->resetServer();
        foreach ($overrides as $key => $value) {
            $_SERVER[$key] = $value;
        }
    }

    protected function setUri(string $uri): void
    {
        $_SERVER['REQUEST_URI'] = $uri;
    }

    protected function setPlatform(string $platform): void
    {
        $_SERVER[PlatformGuard::HEADER_PLATFORM] = $platform;
    }

    protected function setToken(string $token): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
    }

    protected function setDeviceFingerprint(string $fp): void
    {
        $_SERVER['HTTP_X_DEVICE_FINGERPRINT'] = $fp;
    }

    protected function setClientIp(string $ip): void
    {
        $_SERVER['REMOTE_ADDR'] = $ip;
    }

    protected function assertTrue($cond, string $message = ''): void
    {
        $this->assertionCount++;
        if ($cond !== true) {
            throw new AssertionFailedException($message ?: 'Expected true, got ' . var_export($cond, true));
        }
    }

    protected function assertFalse($cond, string $message = ''): void
    {
        $this->assertionCount++;
        if ($cond !== false) {
            throw new AssertionFailedException($message ?: 'Expected false, got ' . var_export($cond, true));
        }
    }

    protected function assertEquals($expected, $actual, string $message = ''): void
    {
        $this->assertionCount++;
        if ($expected != $actual) {
            throw new AssertionFailedException($message ?: sprintf('Expected %s, got %s', var_export($expected, true), var_export($actual, true)));
        }
    }

    protected function assertSame($expected, $actual, string $message = ''): void
    {
        $this->assertionCount++;
        if ($expected !== $actual) {
            throw new AssertionFailedException($message ?: sprintf('Expected identical %s, got %s', var_export($expected, true), var_export($actual, true)));
        }
    }

    protected function assertNotSame($expected, $actual, string $message = ''): void
    {
        $this->assertionCount++;
        if ($expected === $actual) {
            throw new AssertionFailedException($message ?: sprintf('Expected not identical to %s', var_export($expected, true)));
        }
    }

    protected function assertStringEndsWith(string $suffix, string $string, string $message = ''): void
    {
        $this->assertionCount++;
        if (!str_ends_with($string, $suffix)) {
            throw new AssertionFailedException($message ?: sprintf('Expected %s to end with %s', $string, $suffix));
        }
    }

    protected function assertNull($val, string $message = ''): void
    {
        $this->assertionCount++;
        if ($val !== null) {
            throw new AssertionFailedException($message ?: 'Expected null, got ' . var_export($val, true));
        }
    }

    protected function assertNotNull($val, string $message = ''): void
    {
        $this->assertionCount++;
        if ($val === null) {
            throw new AssertionFailedException($message ?: 'Expected not null');
        }
    }

    protected function assertContains($needle, array $haystack, string $message = ''): void
    {
        $this->assertionCount++;
        if (!in_array($needle, $haystack, true)) {
            throw new AssertionFailedException($message ?: sprintf('%s not found in %s', var_export($needle, true), var_export($haystack, true)));
        }
    }

    protected function assertNotContains($needle, array $haystack, string $message = ''): void
    {
        $this->assertionCount++;
        if (in_array($needle, $haystack, true)) {
            throw new AssertionFailedException($message ?: sprintf('%s should not be in %s', var_export($needle, true), var_export($haystack, true)));
        }
    }

    protected function assertCount(int $expected, array $haystack, string $message = ''): void
    {
        $this->assertionCount++;
        if (count($haystack) !== $expected) {
            throw new AssertionFailedException($message ?: sprintf('Expected count %d, got %d', $expected, count($haystack)));
        }
    }

    protected function assertException(callable $fn, string $message = ''): void
    {
        $this->assertionCount++;
        $thrown = null;
        try {
            $fn();
        } catch (Throwable $e) {
            $thrown = $e;
        }
        if ($thrown === null) {
            throw new AssertionFailedException($message ?: 'Expected an exception, none thrown');
        }
    }

    protected function assertNoException(callable $fn, string $message = ''): void
    {
        $this->assertionCount++;
        try {
            $fn();
        } catch (Throwable $e) {
            throw new AssertionFailedException($message ?: sprintf('Expected no exception, got %s: %s', get_class($e), $e->getMessage()));
        }
    }

    protected function assertErrorCode(int $expectedCode, callable $fn, string $message = ''): Throwable
    {
        $this->assertionCount++;
        try {
            $fn();
        } catch (Throwable $e) {
            if ((int)$e->getCode() !== $expectedCode) {
                throw new AssertionFailedException(
                    $message ?: sprintf('Expected error code %d, got %d (%s)', $expectedCode, $e->getCode(), $e->getMessage())
                );
            }
            return $e;
        }
        throw new AssertionFailedException($message ?: sprintf('Expected error code %d, no exception thrown', $expectedCode));
    }

    protected function assertGreaterThan($expected, $actual, string $message = ''): void
    {
        $this->assertionCount++;
        if (!($actual > $expected)) {
            throw new AssertionFailedException($message ?: sprintf('Expected %s > %s', var_export($actual, true), var_export($expected, true)));
        }
    }

    protected function assertGreaterThanOrEqual($expected, $actual, string $message = ''): void
    {
        $this->assertionCount++;
        if (!($actual >= $expected)) {
            throw new AssertionFailedException($message ?: sprintf('Expected %s >= %s', var_export($actual, true), var_export($expected, true)));
        }
    }
}

class AssertionFailedException extends Exception
{
}
