<?php

declare(strict_types=1);

final class Runner
{
    private array $results = [];
    private float $startTime;
    private array $loadedClasses = [];

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    public function run(array $explicitClasses = []): int
    {
        $testFiles = $this->discoverTestFiles();
        foreach ($testFiles as $file) {
            $before = get_declared_classes();
            require_once $file;
            $after = get_declared_classes();
            $this->loadedClasses = array_merge($this->loadedClasses, array_diff($after, $before));
        }

        $testClasses = $this->collectTestClasses();

        if (!empty($explicitClasses)) {
            $testClasses = array_filter($testClasses, function ($c) use ($explicitClasses) {
                foreach ($explicitClasses as $filter) {
                    if (stripos($c, $filter) !== false) {
                        return true;
                    }
                }
                return false;
            });
        }

        foreach ($testClasses as $class) {
            $instance = new $class();
            if (!$instance instanceof TestCase) {
                continue;
            }
            $caseResults = $instance->execute();
            $this->results[] = [
                'class' => $class,
                'cases' => $caseResults,
            ];
        }

        return $this->report();
    }

    private function discoverTestFiles(): array
    {
        $dir = __DIR__ . '/..';
        $files = [];
        foreach (glob($dir . '/*Test.php') as $f) {
            $files[] = $f;
        }
        sort($files);
        return $files;
    }

    private function collectTestClasses(): array
    {
        $classes = [];
        foreach ($this->loadedClasses as $class) {
            $ref = new ReflectionClass($class);
            if ($ref->isAbstract()) {
                continue;
            }
            if ($ref->isSubclassOf(TestCase::class)) {
                $classes[] = $class;
            }
        }
        sort($classes);
        return $classes;
    }

    private function report(): int
    {
        $totalCases = 0;
        $passedCases = 0;
        $failedCases = 0;
        $totalAssertions = 0;
        $failures = [];

        foreach ($this->results as $suite) {
            foreach ($suite['cases'] as $case) {
                $totalCases++;
                $totalAssertions += $case['assertions'];
                if ($case['passed']) {
                    $passedCases++;
                } else {
                    $failedCases++;
                    $failures[] = [
                        'class' => $suite['class'],
                        'method' => $case['method'],
                        'error' => $case['error'],
                    ];
                }
            }
        }

        $elapsed = round((microtime(true) - $this->startTime) * 1000, 2);

        echo "\n";
        echo "════════════════════════════════════════════════════════════════\n";
        echo "  CRM 单元测试平台 — 平台定位 / 商用边界 / 三端红线 状态闭环\n";
        echo "════════════════════════════════════════════════════════════════\n";

        foreach ($this->results as $suite) {
            $suiteName = $suite['class'];
            echo "\n  " . $suiteName . "\n";
            echo "  " . str_repeat('-', mb_strlen($suiteName) + 2) . "\n";
            foreach ($suite['cases'] as $case) {
                $status = $case['passed'] ? '✓ PASS' : '✗ FAIL';
                printf("    %s  %-50s  (%d asserts, %sms)\n",
                    $status,
                    $case['method'],
                    $case['assertions'],
                    $case['elapsed_ms']
                );
            }
        }

        echo "\n";
        echo "────────────────────────────────────────────────────────────────\n";
        printf("  测试套件: %d    用例: %d (通过 %d / 失败 %d)    断言: %d    耗时: %sms\n",
            count($this->results),
            $totalCases,
            $passedCases,
            $failedCases,
            $totalAssertions,
            $elapsed
        );
        echo "────────────────────────────────────────────────────────────────\n";

        if (!empty($failures)) {
            echo "\n  失败详情:\n";
            foreach ($failures as $f) {
                echo "\n    [{$f['class']}::{$f['method']}]\n";
                echo "      " . $f['error'] . "\n";
            }
            echo "\n";
        }

        $overall = $failedCases === 0 ? 'ALL GREEN ✓' : 'HAS FAILURES ✗';
        $banner = $failedCases === 0
            ? "  状态闭环校验通过 — 平台定位、商用边界、三端红线均处于闭环保护中"
            : "  状态闭环存在缺口 — 请参照失败详情修复";
        echo "\n" . $banner . "\n";
        echo "  结果: " . $overall . "\n";
        echo "════════════════════════════════════════════════════════════════\n\n";

        return $failedCases > 0 ? 1 : 0;
    }
}
