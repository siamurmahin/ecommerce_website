<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Policies;

use App\Domain\Catalog\Models\SearchQuery;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SearchQueryPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SearchQuery');
    }

    public function view(AuthUser $authUser, SearchQuery $searchQuery): bool
    {
        return $authUser->can('View:SearchQuery');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SearchQuery');
    }

    public function update(AuthUser $authUser, SearchQuery $searchQuery): bool
    {
        return $authUser->can('Update:SearchQuery');
    }

    public function delete(AuthUser $authUser, SearchQuery $searchQuery): bool
    {
        return $authUser->can('Delete:SearchQuery');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SearchQuery');
    }

    public function restore(AuthUser $authUser, SearchQuery $searchQuery): bool
    {
        return $authUser->can('Restore:SearchQuery');
    }

    public function forceDelete(AuthUser $authUser, SearchQuery $searchQuery): bool
    {
        return $authUser->can('ForceDelete:SearchQuery');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SearchQuery');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SearchQuery');
    }

    public function replicate(AuthUser $authUser, SearchQuery $searchQuery): bool
    {
        return $authUser->can('Replicate:SearchQuery');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SearchQuery');
    }
}
