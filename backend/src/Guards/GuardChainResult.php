<?php

declare(strict_types=1);

namespace App\Guards;

class GuardChainResult
{
    private GuardResult $finalResult;
    private array $allResults;

    public function __construct(GuardResult $finalResult, array $allResults)
    {
        $this->finalResult = $finalResult;
        $this->allResults = $allResults;
    }

    public function isAllPassed(): bool
    {
        return $this->finalResult->isPassed();
    }

    public function getFinalResult(): GuardResult
    {
        return $this->finalResult;
    }

    public function getAllResults(): array
    {
        return $this->allResults;
    }

    public function toArray(): array
    {
        return [
            'all_passed' => $this->isAllPassed(),
            'final_result' => $this->finalResult->toArray(),
            'all_results' => $this->allResults,
        ];
    }
}
