<?php

declare(strict_types=1);

namespace App\Guards;

use Psr\Http\Message\ServerRequestInterface as Request;

class GuardChain
{
    /**
     * @var GuardInterface[]
     */
    private array $guards = [];

    public function addGuard(GuardInterface $guard): self
    {
        $this->guards[] = $guard;
        usort($this->guards, function ($a, $b) {
            return $a->getPriority() - $b->getPriority();
        });
        return $this;
    }

    public function verifyAll(Request $request): GuardChainResult
    {
        $results = [];
        $finalResult = GuardResult::pass();

        foreach ($this->guards as $guard) {
            $result = $guard->verify($request);
            $results[$guard->getName()] = $result->toArray();

            if ($result->isFailed()) {
                $finalResult = $result;
                break;
            }
        }

        return new GuardChainResult($finalResult, $results);
    }

    public function getGuards(): array
    {
        return $this->guards;
    }

    public static function createDefault(): self
    {
        $chain = new self();
        $chain->addGuard(new PlatformGuard());
        $chain->addGuard(new CommercialGuard());
        $chain->addGuard(new RedLineGuard());
        return $chain;
    }
}
