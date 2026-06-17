<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base policy mapping Filament's resource abilities to spatie permissions named
 * "{prefix}.{view|create|update|delete|forceDelete}". The "admin" role bypasses
 * all of these via Gate::before (see AppServiceProvider).
 */
abstract class ResourcePolicy
{
    abstract protected function prefix(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->prefix().'.view');
    }

    public function view(User $user, Model $record): bool
    {
        return $user->can($this->prefix().'.view');
    }

    public function create(User $user): bool
    {
        return $user->can($this->prefix().'.create');
    }

    public function update(User $user, Model $record): bool
    {
        return $user->can($this->prefix().'.update');
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can($this->prefix().'.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can($this->prefix().'.delete');
    }

    public function restore(User $user, Model $record): bool
    {
        return $user->can($this->prefix().'.delete');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can($this->prefix().'.delete');
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $user->can($this->prefix().'.forceDelete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can($this->prefix().'.forceDelete');
    }

    public function replicate(User $user, Model $record): bool
    {
        return $user->can($this->prefix().'.create');
    }

    public function reorder(User $user): bool
    {
        return $user->can($this->prefix().'.update');
    }
}
