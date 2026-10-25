<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Policies;

use App\Domain\Marketing\Models\Bundle;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class BundlePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Bundle');
    }

    public function view(AuthUser $authUser, Bundle $bundle): bool
    {
        return $authUser->can('View:Bundle');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Bundle');
    }

    public function update(AuthUser $authUser, Bundle $bundle): bool
    {
        return $authUser->can('Update:Bundle');
    }

    public function delete(AuthUser $authUser, Bundle $bundle): bool
    {
        return $authUser->can('Delete:Bundle');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Bundle');
    }

    public function restore(AuthUser $authUser, Bundle $bundle): bool
    {
        return $authUser->can('Restore:Bundle');
    }

    public function forceDelete(AuthUser $authUser, Bundle $bundle): bool
    {
        return $authUser->can('ForceDelete:Bundle');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Bundle');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Bundle');
    }

    public function replicate(AuthUser $authUser, Bundle $bundle): bool
    {
        return $authUser->can('Replicate:Bundle');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Bundle');
    }
}
