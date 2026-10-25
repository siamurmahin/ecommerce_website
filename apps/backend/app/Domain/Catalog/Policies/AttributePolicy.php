<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Policies;

use App\Domain\Catalog\Models\Attribute;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AttributePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Attribute');
    }

    public function view(AuthUser $authUser, Attribute $attribute): bool
    {
        return $authUser->can('View:Attribute');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Attribute');
    }

    public function update(AuthUser $authUser, Attribute $attribute): bool
    {
        return $authUser->can('Update:Attribute');
    }

    public function delete(AuthUser $authUser, Attribute $attribute): bool
    {
        return $authUser->can('Delete:Attribute');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Attribute');
    }

    public function restore(AuthUser $authUser, Attribute $attribute): bool
    {
        return $authUser->can('Restore:Attribute');
    }

    public function forceDelete(AuthUser $authUser, Attribute $attribute): bool
    {
        return $authUser->can('ForceDelete:Attribute');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Attribute');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Attribute');
    }

    public function replicate(AuthUser $authUser, Attribute $attribute): bool
    {
        return $authUser->can('Replicate:Attribute');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Attribute');
    }
}
