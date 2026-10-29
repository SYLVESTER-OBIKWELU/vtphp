<?php

declare(strict_types=1);

namespace VtPhp\Http;

use VtPhp\Exceptions\AuthorizationException;
use VtPhp\Validation\Validator;

/**
 * A self-validating request, like Laravel's FormRequest. Type-hint a
 * subclass in a controller method and it is authorized and validated as
 * soon as the container resolves it, so the action only runs on valid input:
 *
 *     public function store(StoreUserRequest $request): UserResource
 *     {
 *         return UserResource::make(User::query()->create($request->validated()));
 *     }
 */
abstract class FormRequest extends Request
{
    /** @var array<string, mixed>|null */
    private ?array $validatedData = null;

    /**
     * @return array<string, array<int, string>|string>
     */
    abstract public function rules(): array;

    /**
     * Return false to reject the request with a 403 before validating.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The input that the rules are applied to.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return $this->all();
    }

    /**
     * Called by the container right after the request is resolved.
     */
    public function validateResolved(): void
    {
        if (!$this->authorize()) {
            throw new AuthorizationException();
        }

        $this->validatedData = Validator::make($this->validationData(), $this->rules())->validated();
    }

    /**
     * Only the fields covered by rules() — safe to pass to Model::create().
     */
    public function validated(?string $key = null, mixed $default = null): mixed
    {
        if ($this->validatedData === null) {
            $this->validateResolved();
        }

        $data = (array) $this->validatedData;

        return $key === null ? $data : ($data[$key] ?? $default);
    }
}
