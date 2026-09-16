<?php

declare(strict_types=1);

namespace AgatCeramic\Architecture;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final readonly class ArchitectureViolation
{
    public function __construct(
        public string $path,
        public int $line,
        public string $rule,
        public string $message,
    ) {}
}

final readonly class ArchitectureReviewItem
{
    public function __construct(
        public string $path,
        public int $line,
        public string $subject,
        public int $lines,
        public ?int $cyclomaticComplexity,
        public string $reason,
    ) {}
}

final readonly class ArchitectureReport
{
    /**
     * @param  list<ArchitectureViolation>  $violations
     * @param  list<ArchitectureViolation>  $allowlistedViolations
     * @param  list<ArchitectureReviewItem>  $reviewItems
     */
    public function __construct(
        public array $violations,
        public array $allowlistedViolations,
        public array $reviewItems,
    ) {}
}

final class ArchitectureGuard
{
    public const CLASS_LINE_REVIEW_THRESHOLD = 250;

    public const METHOD_LINE_REVIEW_THRESHOLD = 40;

    public const METHOD_COMPLEXITY_REVIEW_THRESHOLD = 10;

    /** @var list<string> */
    private const MUTATING_METHODS = [
        'attach',
        'create',
        'decrement',
        'delete',
        'detach',
        'forceCreate',
        'forceDelete',
        'increment',
        'insert',
        'restore',
        'save',
        'saveMany',
        'sync',
        'syncWithoutDetaching',
        'update',
        'updateOrCreate',
        'upsert',
    ];

    /** @var list<array{path: string, rule: string, line: int, task: string, reason: string}> */
    private array $allowlist;

    /**
     * @param  list<array{path: string, rule: string, line: int, task: string, reason: string}>  $allowlist
     */
    public function __construct(
        private readonly string $appDirectory,
        array $allowlist = [],
    ) {
        $this->allowlist = $allowlist;
    }

    public function inspect(): ArchitectureReport
    {
        $violations = $this->validateAllowlist();
        $reviewItems = [];

        foreach ($this->phpFiles() as $file) {
            [$fileViolations, $fileReviewItems] = $this->inspectFile($file);
            array_push($violations, ...$fileViolations);
            array_push($reviewItems, ...$fileReviewItems);
        }

        [$activeViolations, $allowlistedViolations, $allowlistViolations] = $this->applyAllowlist($violations);
        array_push($activeViolations, ...$allowlistViolations);

        usort($activeViolations, $this->compareViolations(...));
        usort($allowlistedViolations, $this->compareViolations(...));
        usort($reviewItems, static fn (ArchitectureReviewItem $left, ArchitectureReviewItem $right): int => [
            $left->path,
            $left->line,
            $left->subject,
        ] <=> [
            $right->path,
            $right->line,
            $right->subject,
        ]);

        return new ArchitectureReport($activeViolations, $allowlistedViolations, $reviewItems);
    }

    /** @return list<string> */
    private function phpFiles(): array
    {
        if (! is_dir($this->appDirectory)) {
            throw new RuntimeException("Application directory does not exist: {$this->appDirectory}");
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->appDirectory));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return array{list<ArchitectureViolation>, list<ArchitectureReviewItem>}
     */
    private function inspectFile(string $file): array
    {
        $source = file_get_contents($file);

        if ($source === false) {
            throw new RuntimeException("Unable to read PHP source: {$file}");
        }

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $statements = $parser->parse($source);

        if ($statements === null) {
            return [[], []];
        }

        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver);
        $statements = $traverser->traverse($statements);

        $relativePath = $this->relativePath($file);
        $layer = $this->layerForPath($relativePath);
        $finder = new NodeFinder;
        $violations = $this->dependencyViolations($finder, $statements, $relativePath, $layer);

        if ($layer === 'controller') {
            array_push($violations, ...$this->controllerViolations($finder, $statements, $relativePath));
        }

        return [$violations, $this->reviewItems($finder, $statements, $relativePath)];
    }

    /**
     * @param  array<Node>  $statements
     * @return list<ArchitectureViolation>
     */
    private function dependencyViolations(NodeFinder $finder, array $statements, string $path, string $layer): array
    {
        $forbiddenPrefixes = match ($layer) {
            'application' => ['App\\Http\\'],
            'model' => ['App\\Http\\', 'App\\Services\\', 'App\\Queries\\', 'App\\Jobs\\', 'App\\Console\\', 'App\\Mail\\'],
            'query' => ['App\\Http\\', 'App\\Services\\', 'App\\Jobs\\', 'App\\Console\\', 'App\\Mail\\'],
            'presentation' => ['App\\Services\\', 'App\\Queries\\', 'App\\Jobs\\', 'App\\Console\\', 'App\\Mail\\'],
            'integration' => ['App\\Http\\Controllers\\', 'App\\Http\\Requests\\', 'App\\Http\\Resources\\', 'App\\Http\\Responses\\'],
            default => [],
        };

        $violations = [];
        $seen = [];

        /** @var Name $name */
        foreach ($finder->findInstanceOf($statements, Name::class) as $name) {
            $dependency = $this->resolvedName($name);
            $forbidden = $this->isForbiddenFrameworkDependency($layer, $dependency);

            foreach ($forbiddenPrefixes as $prefix) {
                $forbidden = $forbidden || str_starts_with($dependency, $prefix);
            }

            if (! $forbidden) {
                continue;
            }

            $key = $dependency;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $violations[] = new ArchitectureViolation(
                $path,
                $name->getStartLine(),
                "dependency.{$layer}",
                "Layer {$layer} must not depend on {$dependency}.",
            );
        }

        return $violations;
    }

    /**
     * @param  array<Node>  $statements
     * @return list<ArchitectureViolation>
     */
    private function controllerViolations(NodeFinder $finder, array $statements, string $path): array
    {
        $violations = [];

        /** @var Expr\FuncCall $call */
        foreach ($finder->findInstanceOf($statements, Expr\FuncCall::class) as $call) {
            if (! $call->name instanceof Name) {
                continue;
            }

            $function = strtolower($call->name->getLast());
            $rule = match ($function) {
                'app', 'resolve' => 'controller.service_locator',
                'request' => 'controller.global_request',
                'auth' => 'controller.global_auth',
                default => null,
            };

            if ($rule !== null) {
                $violations[] = new ArchitectureViolation(
                    $path,
                    $call->getStartLine(),
                    $rule,
                    "Controller must not call the global {$function}() helper.",
                );
            }
        }

        /** @var Expr\StaticCall $call */
        foreach ($finder->findInstanceOf($statements, Expr\StaticCall::class) as $call) {
            if (! $call->class instanceof Name) {
                continue;
            }

            $class = $this->resolvedName($call->class);

            if ($class === 'Illuminate\\Support\\Facades\\DB') {
                $violations[] = new ArchitectureViolation(
                    $path,
                    $call->getStartLine(),
                    'controller.db_facade',
                    'Controller must not access the DB facade.',
                );
            }

            if (str_starts_with($class, 'App\\Models\\') && $this->isMutationName($call->name)) {
                $violations[] = new ArchitectureViolation(
                    $path,
                    $call->getStartLine(),
                    'controller.model_mutation',
                    "Controller must not mutate {$class} directly.",
                );
            }
        }

        /** @var Stmt\ClassMethod $method */
        foreach ($finder->findInstanceOf($statements, Stmt\ClassMethod::class) as $method) {
            $modelVariables = $this->modelParameterNames($method);
            $serviceVariables = $this->serviceParameterNames($method);

            do {
                $knownVariableCount = count($modelVariables);

                /** @var Expr\Assign $assignment */
                foreach ($finder->findInstanceOf($method->stmts ?? [], Expr\Assign::class) as $assignment) {
                    if ($assignment->var instanceof Expr\Variable
                        && is_string($assignment->var->name)
                        && $this->isModelExpression($assignment->expr, $modelVariables)) {
                        $modelVariables[$assignment->var->name] = true;
                    }
                }
            } while (count($modelVariables) > $knownVariableCount);

            /** @var Expr\MethodCall $call */
            foreach ($finder->findInstanceOf($method->stmts ?? [], Expr\MethodCall::class) as $call) {
                if (! $this->isMutationName($call->name)) {
                    continue;
                }

                $unknownVariableMutation = $call->var instanceof Expr\Variable
                    && is_string($call->var->name)
                    && ! isset($serviceVariables[$call->var->name]);

                if (! $unknownVariableMutation && ! $this->isModelExpression($call->var, $modelVariables)) {
                    continue;
                }

                $violations[] = new ArchitectureViolation(
                    $path,
                    $call->getStartLine(),
                    'controller.model_mutation',
                    'Controller must not mutate an Eloquent model directly.',
                );
            }
        }

        return $violations;
    }

    /**
     * @param  array<Node>  $statements
     * @return list<ArchitectureReviewItem>
     */
    private function reviewItems(NodeFinder $finder, array $statements, string $path): array
    {
        $items = [];

        /** @var Stmt\Class_ $class */
        foreach ($finder->findInstanceOf($statements, Stmt\Class_::class) as $class) {
            $classLines = $class->getEndLine() - $class->getStartLine() + 1;
            $className = $class->namespacedName?->toString() ?? $class->name?->toString() ?? 'anonymous class';

            if ($classLines > self::CLASS_LINE_REVIEW_THRESHOLD) {
                $items[] = new ArchitectureReviewItem(
                    $path,
                    $class->getStartLine(),
                    $className,
                    $classLines,
                    null,
                    'class exceeds '.self::CLASS_LINE_REVIEW_THRESHOLD.' lines',
                );
            }

            foreach ($class->getMethods() as $method) {
                $methodLines = $method->getEndLine() - $method->getStartLine() + 1;
                $complexity = $this->cyclomaticComplexity($finder, $method);
                $reasons = [];

                if ($methodLines > self::METHOD_LINE_REVIEW_THRESHOLD) {
                    $reasons[] = 'method exceeds '.self::METHOD_LINE_REVIEW_THRESHOLD.' lines';
                }

                if ($complexity > self::METHOD_COMPLEXITY_REVIEW_THRESHOLD) {
                    $reasons[] = 'cyclomatic complexity exceeds '.self::METHOD_COMPLEXITY_REVIEW_THRESHOLD;
                }

                if ($reasons !== []) {
                    $items[] = new ArchitectureReviewItem(
                        $path,
                        $method->getStartLine(),
                        $className.'::'.$method->name->toString().'()',
                        $methodLines,
                        $complexity,
                        implode('; ', $reasons),
                    );
                }
            }
        }

        return $items;
    }

    private function cyclomaticComplexity(NodeFinder $finder, Stmt\ClassMethod $method): int
    {
        $decisionNodes = $finder->find($method->stmts ?? [], static function (Node $node): bool {
            if ($node instanceof Stmt\If_
                || $node instanceof Stmt\ElseIf_
                || $node instanceof Stmt\For_
                || $node instanceof Stmt\Foreach_
                || $node instanceof Stmt\While_
                || $node instanceof Stmt\Do_
                || $node instanceof Stmt\Catch_
                || $node instanceof Expr\BinaryOp\BooleanAnd
                || $node instanceof Expr\BinaryOp\BooleanOr
                || $node instanceof Expr\BinaryOp\LogicalAnd
                || $node instanceof Expr\BinaryOp\LogicalOr
                || $node instanceof Expr\BinaryOp\Coalesce
                || $node instanceof Expr\Ternary) {
                return true;
            }

            return $node instanceof Stmt\Case_ && $node->cond !== null;
        });

        return count($decisionNodes) + 1;
    }

    /** @return array<string, true> */
    private function modelParameterNames(Stmt\ClassMethod $method): array
    {
        return $this->parameterNamesWithPrefix($method, 'App\\Models\\');
    }

    /** @return array<string, true> */
    private function serviceParameterNames(Stmt\ClassMethod $method): array
    {
        return $this->parameterNamesWithPrefix($method, 'App\\Services\\');
    }

    /** @return array<string, true> */
    private function parameterNamesWithPrefix(Stmt\ClassMethod $method, string $prefix): array
    {
        $variables = [];

        foreach ($method->params as $parameter) {
            if (! $parameter->var instanceof Expr\Variable || ! is_string($parameter->var->name)) {
                continue;
            }

            $types = $parameter->type instanceof Node\UnionType || $parameter->type instanceof Node\IntersectionType
                ? $parameter->type->types
                : [$parameter->type];

            foreach ($types as $type) {
                if ($type instanceof Name && str_starts_with($this->resolvedName($type), $prefix)) {
                    $variables[$parameter->var->name] = true;
                }
            }
        }

        return $variables;
    }

    private function isMutationName(Node\Identifier|Expr|null $name): bool
    {
        return $name instanceof Node\Identifier && in_array($name->toString(), self::MUTATING_METHODS, true);
    }

    /** @param array<string, true> $modelVariables */
    private function isModelExpression(Expr $expression, array $modelVariables): bool
    {
        if ($expression instanceof Expr\Variable && is_string($expression->name)) {
            return isset($modelVariables[$expression->name]);
        }

        if ($expression instanceof Expr\New_ && $expression->class instanceof Name) {
            return str_starts_with($this->resolvedName($expression->class), 'App\\Models\\');
        }

        if ($expression instanceof Expr\StaticCall && $expression->class instanceof Name) {
            return str_starts_with($this->resolvedName($expression->class), 'App\\Models\\');
        }

        return $expression instanceof Expr\MethodCall
            && $this->isModelExpression($expression->var, $modelVariables);
    }

    private function resolvedName(Name $name): string
    {
        $resolvedName = $name->getAttribute('resolvedName');

        return $resolvedName instanceof Name ? $resolvedName->toString() : $name->toString();
    }

    private function isForbiddenFrameworkDependency(string $layer, string $dependency): bool
    {
        if ($layer !== 'application') {
            return false;
        }

        if ($dependency === 'Illuminate\\Http\\Request'
            || str_starts_with($dependency, 'Illuminate\\Http\\Resources\\')) {
            return true;
        }

        $isFrameworkHttpType = str_starts_with($dependency, 'Illuminate\\Http\\')
            || str_starts_with($dependency, 'Symfony\\Component\\HttpFoundation\\');

        return $isFrameworkHttpType && str_ends_with($dependency, 'Response');
    }

    private function relativePath(string $file): string
    {
        return 'app/'.str_replace('\\', '/', substr($file, strlen(rtrim($this->appDirectory, DIRECTORY_SEPARATOR)) + 1));
    }

    private function layerForPath(string $path): string
    {
        return match (true) {
            str_starts_with($path, 'app/Http/Controllers/') => 'controller',
            str_starts_with($path, 'app/Http/Resources/'), str_starts_with($path, 'app/Http/Responses/') => 'presentation',
            str_starts_with($path, 'app/Services/') => 'application',
            str_starts_with($path, 'app/Models/') => 'model',
            str_starts_with($path, 'app/Queries/') => 'query',
            str_starts_with($path, 'app/Jobs/'), str_starts_with($path, 'app/Console/'), str_starts_with($path, 'app/Mail/'), str_starts_with($path, 'app/Logging/') => 'integration',
            default => 'other',
        };
    }

    /** @return list<ArchitectureViolation> */
    private function validateAllowlist(): array
    {
        $violations = [];

        foreach ($this->allowlist as $index => $entry) {
            $validTask = preg_match('/^TASK-(?:A)?\d{3,}$/', $entry['task']) === 1;
            $valid = $entry['path'] !== ''
                && $entry['rule'] !== ''
                && $entry['line'] > 0
                && $validTask
                && trim($entry['reason']) !== '';

            if (! $valid) {
                $violations[] = new ArchitectureViolation(
                    'scripts/architecture-allowlist.php',
                    $index + 1,
                    'allowlist.invalid',
                    'Allowlist entries require exact path, rule, line, removal task (TASK-A000/TASK-000) and reason.',
                );
            }
        }

        return $violations;
    }

    /**
     * @param  list<ArchitectureViolation>  $violations
     * @return array{list<ArchitectureViolation>, list<ArchitectureViolation>, list<ArchitectureViolation>}
     */
    private function applyAllowlist(array $violations): array
    {
        $active = [];
        $allowlisted = [];
        $matchedEntries = [];

        foreach ($violations as $violation) {
            $matched = false;

            foreach ($this->allowlist as $index => $entry) {
                if ($entry['path'] === $violation->path
                    && $entry['rule'] === $violation->rule
                    && $entry['line'] === $violation->line) {
                    $matched = true;
                    $matchedEntries[$index] = true;
                    break;
                }
            }

            if ($matched) {
                $allowlisted[] = $violation;
            } else {
                $active[] = $violation;
            }
        }

        $stale = [];

        foreach ($this->allowlist as $index => $entry) {
            if (isset($matchedEntries[$index])) {
                continue;
            }

            $stale[] = new ArchitectureViolation(
                'scripts/architecture-allowlist.php',
                $index + 1,
                'allowlist.stale',
                "Allowlist entry {$entry['path']}:{$entry['line']} [{$entry['rule']}] no longer matches a violation.",
            );
        }

        return [$active, $allowlisted, $stale];
    }

    private function compareViolations(ArchitectureViolation $left, ArchitectureViolation $right): int
    {
        return [$left->path, $left->line, $left->rule] <=> [$right->path, $right->line, $right->rule];
    }
}
