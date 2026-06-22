<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\BoundaryRuleRepository;
use App\Repository\ViolationLogRepository;
use App\Model\BoundaryRule;
use Psr\Http\Message\ServerRequestInterface;

class BoundaryService
{
    public function __construct(
        private BoundaryRuleRepository $ruleRepo,
        private ViolationLogRepository $violationRepo,
    ) {}

    public function checkBoundary(ServerRequestInterface $request): array
    {
        $violations = [];
        $rules = $this->ruleRepo->getEnabled();

        foreach ($rules as $rule) {
            $check = $this->checkRule($rule, $request);
            if (!$check['passed']) {
                $violations[] = $check;
                $this->logViolation([
                    'rule_id' => $rule->id,
                    'rule_type' => $rule->ruleType,
                    'violation_detail' => $check['detail'],
                    'client_ip' => $this->getClientIp($request),
                    'request_path' => $request->getUri()->getPath(),
                ]);
            }
        }

        return [
            'passed' => empty($violations),
            'violations' => $violations,
        ];
    }

    private function checkRule(BoundaryRule $rule, ServerRequestInterface $request): array
    {
        return match ($rule->ruleType) {
            'domain_whitelist' => $this->checkDomainWhitelist($rule, $request),
            'ip_blacklist' => $this->checkIpBlacklist($rule, $request),
            'concurrent_max' => $this->checkConcurrentMax($rule),
            default => ['passed' => true, 'rule_type' => $rule->ruleType, 'detail' => ''],
        };
    }

    private function checkDomainWhitelist(BoundaryRule $rule, ServerRequestInterface $request): array
    {
        $host = $request->getHeaderLine('Host');
        $origin = $request->getHeaderLine('Origin');
        $referer = $request->getHeaderLine('Referer');

        if (empty($host) && empty($origin) && empty($referer)) {
            return ['passed' => true, 'rule_type' => 'domain_whitelist', 'detail' => ''];
        }

        $allowedDomains = array_map('trim', explode(',', $rule->ruleValue));
        $checkHost = $host ?: parse_url($origin ?: $referer, PHP_URL_HOST) ?: '';

        $passed = true;
        if ($checkHost) {
            $passed = false;
            foreach ($allowedDomains as $allowed) {
                $pattern = str_replace('\*', '.*', preg_quote($allowed, '/'));
                if (preg_match("/^{$pattern}$/i", $checkHost)) {
                    $passed = true;
                    break;
                }
            }
        }

        return [
            'passed' => $passed,
            'rule_type' => 'domain_whitelist',
            'detail' => $passed ? '' : "Domain '{$checkHost}' not in whitelist",
        ];
    }

    private function checkIpBlacklist(BoundaryRule $rule, ServerRequestInterface $request): array
    {
        $clientIp = $this->getClientIp($request);
        $blacklisted = array_map('trim', explode(',', $rule->ruleValue));
        $passed = !in_array($clientIp, $blacklisted, true);

        return [
            'passed' => $passed,
            'rule_type' => 'ip_blacklist',
            'detail' => $passed ? '' : "IP '{$clientIp}' is blacklisted",
        ];
    }

    private function checkConcurrentMax(BoundaryRule $rule): array
    {
        $maxConcurrent = (int)$rule->ruleValue;
        $tempFile = sys_get_temp_dir() . '/crm_concurrent_count';

        $count = 0;
        if (file_exists($tempFile)) {
            $count = (int)file_get_contents($tempFile);
        }

        $passed = $count < $maxConcurrent;

        return [
            'passed' => $passed,
            'rule_type' => 'concurrent_max',
            'detail' => $passed ? '' : "Concurrent connections ({$count}) exceed limit ({$maxConcurrent})",
        ];
    }

    public function logViolation(array $data): int
    {
        return $this->violationRepo->insert($data);
    }

    public function getRules(): array
    {
        return array_map(fn($rule) => $rule->toArray(), $this->ruleRepo->getAll());
    }

    public function updateRule(int $id, array $data): bool
    {
        return $this->ruleRepo->update($id, $data);
    }

    public function getViolations(int $page = 1, int $perPage = 20): array
    {
        return $this->violationRepo->getPaginated($page, $perPage);
    }

    private function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR']
            ?? $request->getHeaderLine('X-Forwarded-For')
            ?? $request->getHeaderLine('X-Real-IP')
            ?? '127.0.0.1';
    }
}
