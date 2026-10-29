<?php

declare(strict_types=1);

namespace VtPhp\Auth\Access;

use Illuminate\Support\Str;
use VtPhp\Container\Container;
use VtPhp\Exceptions\AuthorizationException;

/**
 * Decides who may do what, like Laravel's Gate.
 *
 * - Gates: named rules, `$gate->define('viewReports', fn (User $user) => $user->is_admin)`.
 * - Policies: one class per model whose methods are abilities,
 *   `NotePolicy::update(User $user, Note $note): bool`. A policy is found
 *   automatically as App\Policies\{Model}Policy, or registered with policy().
 *
 * Check with allows()/denies(), or authorize() to throw a 403.
 */
final class Gate
{
    /** @var array<string, \Closure> */
    private array $abilities = [];

    /** @var array<class-string, class-string> */
    private array $policies = [];

    /** @var array<class-string, object> */
    private array $policyInstances = [];

    /** @var list<\Closure> */
    private array $beforeCallbacks = [];

    /**
     * @param \Closure(): ?object $userResolver Who "the current user" is.
     */
    public function __construct(
        private readonly Container $container,
        private \Closure $userResolver,
    ) {
    }

    public function define(string $ability, \Closure $callback): void
    {
        $this->abilities[$ability] = $callback;
    }

    /**
     * @param class-string $model
     * @param class-string $policy
     */
    public function policy(string $model, string $policy): void
    {
        $this->policies[$model] = $policy;
    }

    /**
     * Runs before every check. Return true/false to decide on the spot (e.g.
     * let admins do anything), or null to carry on with the normal rules.
     *
     * @param \Closure(?object, string, array<int, mixed>): ?bool $callback
     */
    public function before(\Closure $callback): void
    {
        $this->beforeCallbacks[] = $callback;
    }

    public function has(string $ability): bool
    {
        return isset($this->abilities[$ability]);
    }

    /**
     * The same rules, checked for a specific user instead of the current one.
     */
    public function forUser(?object $user): self
    {
        $clone = clone $this;
        $clone->userResolver = static fn (): ?object => $user;

        return $clone;
    }

    public function allows(string $ability, mixed ...$arguments): bool
    {
        return $this->check($ability, $arguments);
    }

    public function denies(string $ability, mixed ...$arguments): bool
    {
        return !$this->check($ability, $arguments);
    }

    /**
     * @throws AuthorizationException (403) when the ability is denied
     */
    public function authorize(string $ability, mixed ...$arguments): void
    {
        if (!$this->check($ability, $arguments)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function check(string $ability, array $arguments): bool
    {
        $user = ($this->userResolver)();

        foreach ($this->beforeCallbacks as $callback) {
            $result = $callback($user, $ability, $arguments);

            if ($result !== null) {
                return (bool) $result;
            }
        }

        $policy = $this->policyFor($arguments[0] ?? null);

        if ($policy !== null) {
            return $this->checkPolicy($policy, $ability, $user, $arguments);
        }

        if (isset($this->abilities[$ability])) {
            return $this->callIfUserAllowed($this->abilities[$ability], $user, $arguments);
        }

        return false;
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function checkPolicy(object $policy, string $ability, ?object $user, array $arguments): bool
    {
        // A class name (e.g. Note::class for "create") picks the policy but
        // isn't passed on; a model instance is.
        if (is_string($arguments[0] ?? null)) {
            array_shift($arguments);
        }

        if (method_exists($policy, 'before') && ($user !== null || $this->acceptsGuests([$policy, 'before']))) {
            $result = $policy->before($user, $ability);

            if ($result !== null) {
                return (bool) $result;
            }
        }

        $method = Str::camel($ability);

        if (!method_exists($policy, $method)) {
            return false;
        }

        return $this->callIfUserAllowed(\Closure::fromCallable([$policy, $method]), $user, $arguments);
    }

    /**
     * Guests are denied unless the rule's first parameter accepts null
     * (e.g. `?User $user`), so public abilities must opt in.
     *
     * @param array<int, mixed> $arguments
     */
    private function callIfUserAllowed(\Closure $callback, ?object $user, array $arguments): bool
    {
        if ($user === null && !$this->acceptsGuests($callback)) {
            return false;
        }

        return (bool) $callback($user, ...$arguments);
    }

    /**
     * @param callable $callback
     */
    private function acceptsGuests(callable $callback): bool
    {
        $first = (new \ReflectionFunction(\Closure::fromCallable($callback)))->getParameters()[0] ?? null;

        return $first === null || $first->allowsNull();
    }

    private function policyFor(mixed $target): ?object
    {
        $class = is_object($target) ? $target::class : (is_string($target) && class_exists($target) ? $target : null);

        if ($class === null) {
            return null;
        }

        $policyClass = $this->policies[$class] ?? $this->guessPolicy($class);

        if ($policyClass === null) {
            return null;
        }

        return $this->policyInstances[$policyClass] ??= $this->container->make($policyClass);
    }

    /**
     * App\Models\Note → App\Policies\NotePolicy, if that class exists.
     */
    private function guessPolicy(string $model): ?string
    {
        $short = (new \ReflectionClass($model))->getShortName();
        $guess = "App\\Policies\\{$short}Policy";

        return class_exists($guess) ? $guess : null;
    }
}
