<?php

declare(strict_types=1);

namespace App\Domain\Shipping\Policies;

use App\Domain\Shipping\Models\Thana;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ThanaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Thana');
    }

    public function view(AuthUser $authUser, Thana $thana): bool
    {
        return $authUser->can('View:Thana');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Thana');
    }

    public function update(AuthUser $authUser, Thana $thana): bool
    {
        return $authUser->can('Update:Thana');
    }

    public function delete(AuthUser $authUser, Thana $thana): bool
    {
        return $authUser->can('Delete:Thana');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Thana');
    }

    public function restore(AuthUser $authUser, Thana $thana): bool
    {
        return $authUser->can('Restore:Thana');
    }

    public function forceDelete(AuthUser $authUser, Thana $thana): bool
    {
        return $authUser->can('ForceDelete:Thana');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Thana');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Thana');
    }

    public function replicate(AuthUser $authUser, Thana $thana): bool
    {
        return $authUser->can('Replicate:Thana');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Thana');
    }
}
