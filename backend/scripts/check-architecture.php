<?php

declare(strict_types=1);

use AgatCeramic\Architecture\ArchitectureGuard;

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/architecture/ArchitectureGuard.php';

/** @var list<array{path: string, rule: string, line: int, task: string, reason: string}> $allowlist */
$allowlist = require __DIR__.'/architecture-allowlist.php';
$report = (new ArchitectureGuard(dirname(__DIR__).'/app', $allowlist))->inspect();

if ($report->violations === []) {
    fwrite(STDOUT, "Architecture guard: no blocking violations.\n");
} else {
    fwrite(STDERR, "Architecture guard: blocking violations:\n");

    foreach ($report->violations as $violation) {
        fwrite(STDERR, sprintf(
            "- %s:%d [%s] %s\n",
            $violation->path,
            $violation->line,
            $violation->rule,
            $violation->message,
        ));
    }
}

if ($report->allowlistedViolations !== []) {
    fwrite(STDOUT, "\nAllowlisted architecture debt:\n");

    foreach ($report->allowlistedViolations as $violation) {
        fwrite(STDOUT, sprintf("- %s:%d [%s] %s\n", $violation->path, $violation->line, $violation->rule, $violation->message));
    }
}

fwrite(STDOUT, sprintf(
    "\nArchitecture review report (class > %d lines, method > %d lines, method complexity > %d):\n",
    ArchitectureGuard::CLASS_LINE_REVIEW_THRESHOLD,
    ArchitectureGuard::METHOD_LINE_REVIEW_THRESHOLD,
    ArchitectureGuard::METHOD_COMPLEXITY_REVIEW_THRESHOLD,
));

if ($report->reviewItems === []) {
    fwrite(STDOUT, "- no review-threshold findings\n");
} else {
    foreach ($report->reviewItems as $item) {
        $complexity = $item->cyclomaticComplexity === null ? '' : ", complexity {$item->cyclomaticComplexity}";
        fwrite(STDOUT, sprintf(
            "- %s:%d %s (%d lines%s): %s\n",
            $item->path,
            $item->line,
            $item->subject,
            $item->lines,
            $complexity,
            $item->reason,
        ));
    }
}

exit($report->violations === [] ? 0 : 1);
