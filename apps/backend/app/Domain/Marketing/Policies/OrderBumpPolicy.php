<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Policies;

use App\Domain\Marketing\Models\OrderBump;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class OrderBumpPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OrderBump');
    }

    public function view(AuthUser $authUser, OrderBump $orderBump): bool
    {
        return $authUser->can('View:OrderBump');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OrderBump');
    }

    public function update(AuthUser $authUser, OrderBump $orderBump): bool
    {
        return $authUser->can('Update:OrderBump');
    }

    public function delete(AuthUser $authUser, OrderBump $orderBump): bool
    {
        return $authUser->can('Delete:OrderBump');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:OrderBump');
    }

    public function restore(AuthUser $authUser, OrderBump $orderBump): bool
    {
        return $authUser->can('Restore:OrderBump');
    }

    public function forceDelete(AuthUser $authUser, OrderBump $orderBump): bool
    {
        return $authUser->can('ForceDelete:OrderBump');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OrderBump');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OrderBump');
    }

    public function replicate(AuthUser $authUser, OrderBump $orderBump): bool
    {
        return $authUser->can('Replicate:OrderBump');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OrderBump');
    }
}
