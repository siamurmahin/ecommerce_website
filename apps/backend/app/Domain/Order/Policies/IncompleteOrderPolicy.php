<?php

declare(strict_types=1);

namespace App\Domain\Order\Policies;

use App\Domain\Order\Models\IncompleteOrder;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class IncompleteOrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:IncompleteOrder');
    }

    public function view(AuthUser $authUser, IncompleteOrder $incompleteOrder): bool
    {
        return $authUser->can('View:IncompleteOrder');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:IncompleteOrder');
    }

    public function update(AuthUser $authUser, IncompleteOrder $incompleteOrder): bool
    {
        return $authUser->can('Update:IncompleteOrder');
    }

    public function delete(AuthUser $authUser, IncompleteOrder $incompleteOrder): bool
    {
        return $authUser->can('Delete:IncompleteOrder');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:IncompleteOrder');
    }

    public function restore(AuthUser $authUser, IncompleteOrder $incompleteOrder): bool
    {
        return $authUser->can('Restore:IncompleteOrder');
    }

    public function forceDelete(AuthUser $authUser, IncompleteOrder $incompleteOrder): bool
    {
        return $authUser->can('ForceDelete:IncompleteOrder');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:IncompleteOrder');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:IncompleteOrder');
    }

    public function replicate(AuthUser $authUser, IncompleteOrder $incompleteOrder): bool
    {
        return $authUser->can('Replicate:IncompleteOrder');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:IncompleteOrder');
    }
}
