<?php

declare(strict_types=1);

namespace App\Domain\Review\Policies;

use App\Domain\Review\Models\ProductQuestion;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ProductQuestionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProductQuestion');
    }

    public function view(AuthUser $authUser, ProductQuestion $productQuestion): bool
    {
        return $authUser->can('View:ProductQuestion');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProductQuestion');
    }

    public function update(AuthUser $authUser, ProductQuestion $productQuestion): bool
    {
        return $authUser->can('Update:ProductQuestion');
    }

    public function delete(AuthUser $authUser, ProductQuestion $productQuestion): bool
    {
        return $authUser->can('Delete:ProductQuestion');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ProductQuestion');
    }

    public function restore(AuthUser $authUser, ProductQuestion $productQuestion): bool
    {
        return $authUser->can('Restore:ProductQuestion');
    }

    public function forceDelete(AuthUser $authUser, ProductQuestion $productQuestion): bool
    {
        return $authUser->can('ForceDelete:ProductQuestion');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProductQuestion');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProductQuestion');
    }

    public function replicate(AuthUser $authUser, ProductQuestion $productQuestion): bool
    {
        return $authUser->can('Replicate:ProductQuestion');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProductQuestion');
    }
}
