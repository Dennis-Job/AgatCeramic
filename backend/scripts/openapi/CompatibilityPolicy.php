<?php

declare(strict_types=1);

namespace AgatCeramic\OpenApi;

use RuntimeException;

final class CompatibilityPolicy
{
    public function breakingChangePolicyError(string $baseVersion, string $candidateVersion, ?string $migrationPlanPath): ?string
    {
        $base = $this->parseVersion($baseVersion);
        $candidate = $this->parseVersion($candidateVersion);

        if ($candidate[0] <= $base[0]) {
            return "A patch or minor version change ({$baseVersion} -> {$candidateVersion}) cannot approve a breaking wire contract; increase the major version.";
        }

        if ($migrationPlanPath === null) {
            return "Major version {$candidateVersion} requires --migration-plan with an explicit version section and Breaking change heading.";
        }

        $contents = file_get_contents($migrationPlanPath);
        if ($contents === false) {
            throw new RuntimeException("Cannot read migration plan {$migrationPlanPath}.");
        }

        if (! $this->documentsBreakingVersion($contents, $candidateVersion)) {
            return "Migration plan {$migrationPlanPath} must contain a '{$candidateVersion}' version section followed by a 'Breaking change' heading.";
        }

        return null;
    }

    /** @return array{int, int, int} */
    private function parseVersion(string $version): array
    {
        if (preg_match('/^v?(\d+)\.(\d+)(?:\.(\d+))?$/', $version, $matches) !== 1) {
            throw new RuntimeException("OpenAPI info.version '{$version}' must use vMAJOR.MINOR or vMAJOR.MINOR.PATCH.");
        }

        return [(int) $matches[1], (int) $matches[2], (int) ($matches[3] ?? 0)];
    }

    private function documentsBreakingVersion(string $contents, string $version): bool
    {
        $normalizedVersion = preg_quote(ltrim($version, 'vV'), '/');
        $pattern = '/^##\s+v?'.$normalizedVersion.'(?:\s|$)(.*?)(?=^##\s+v?\d+\.\d+|\z)/msi';

        if (preg_match($pattern, $contents, $section) !== 1) {
            return false;
        }

        return preg_match('/^#{2,4}\s+Breaking change\s*$/mi', $section[0]) === 1;
    }
}
