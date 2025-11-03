<?php

namespace Core;

/**
 * Policy Base Class
 * Authorization policies for models
 */
abstract class Policy
{
    /**
     * Determine if the given action can be performed before other checks
     */
    public function before($user, $ability)
    {
        // Admin users can do anything
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Determine if action is allowed after all checks
     */
    public function after($user, $ability, $result)
    {
        return $result;
    }

    /**
     * Check if user can view any models
     */
    public function viewAny($user)
    {
        return true;
    }

    /**
     * Check if user can view the model
     */
    public function view($user, $model)
    {
        return true;
    }

    /**
     * Check if user can create models
     */
    public function create($user)
    {
        return true;
    }

    /**
     * Check if user can update the model
     */
    public function update($user, $model)
    {
        return $this->owns($user, $model);
    }

    /**
     * Check if user can delete the model
     */
    public function delete($user, $model)
    {
        return $this->owns($user, $model);
    }

    /**
     * Check if user can restore the model
     */
    public function restore($user, $model)
    {
        return $this->owns($user, $model);
    }

    /**
     * Check if user can permanently delete
     */
    public function forceDelete($user, $model)
    {
        return $this->owns($user, $model);
    }

    /**
     * Check if user owns the model
     */
    protected function owns($user, $model)
    {
        if (!isset($model->user_id)) {
            return false;
        }

        return $user->id === $model->user_id;
    }

    /**
     * Authorize an action
     */
    public function authorize($user, $ability, $model = null)
    {
        // Run before hook
        $before = $this->before($user, $ability);
        if ($before !== null) {
            return $before;
        }

        // Check if method exists
        if (!method_exists($this, $ability)) {
            return false;
        }

        // Run ability check
        $result = $model 
            ? $this->$ability($user, $model)
            : $this->$ability($user);

        // Run after hook
        return $this->after($user, $ability, $result);
    }

    /**
     * Throw authorization exception if not authorized
     */
    public function authorizeOrFail($user, $ability, $model = null)
    {
        if (!$this->authorize($user, $ability, $model)) {
            throw new \Exception("Unauthorized action: {$ability}");
        }

        return true;
    }
}
