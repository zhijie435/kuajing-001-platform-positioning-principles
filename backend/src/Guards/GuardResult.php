<?php

declare(strict_types=1);

namespace App\Guards;

class GuardResult
{
    private bool $passed;
    private int $code;
    private string $message;
    private array $data;
    private string $guardName;

    public function __construct(
        bool $passed,
        int $code = 0,
        string $message = '',
        array $data = [],
        string $guardName = ''
    ) {
        $this->passed = $passed;
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
        $this->guardName = $guardName;
    }

    public static function pass(array $data = [], string $guardName = ''): self
    {
        return new self(true, 0, '校验通过', $data, $guardName);
    }

    public static function fail(int $code, string $message, array $data = [], string $guardName = ''): self
    {
        return new self(false, $code, $message, $data, $guardName);
    }

    public function isPassed(): bool
    {
        return $this->passed;
    }

    public function isFailed(): bool
    {
        return !$this->passed;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getGuardName(): string
    {
        return $this->guardName;
    }

    public function toArray(): array
    {
        return [
            'passed' => $this->passed,
            'code' => $this->code,
            'message' => $this->message,
            'data' => $this->data,
            'guard_name' => $this->guardName,
        ];
    }
}
