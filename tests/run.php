<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$filters = array_slice($argv, 1);
$runner = new Runner();
$exitCode = $runner->run($filters);
exit($exitCode);
