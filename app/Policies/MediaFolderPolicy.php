<?php

namespace App\Policies;

use App\Models\MediaFolder;
use App\Models\User;

class MediaFolderPolicy
{
    protected function isAuthorized(User $user): bool
    {
        return str_ends_with($user->email, '@larashop.test')
            || $user->email === 'admin@larashop.test';
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->isAuthorized($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MediaFolder $mediaFolder): bool
    {
        return $this->isAuthorized($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->isAuthorized($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MediaFolder $mediaFolder): bool
    {
        return $this->isAuthorized($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MediaFolder $mediaFolder): bool
    {
        return $this->isAuthorized($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MediaFolder $mediaFolder): bool
    {
        return $this->isAuthorized($user);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MediaFolder $mediaFolder): bool
    {
        return $this->isAuthorized($user);
    }
}
