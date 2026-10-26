<?php

declare(strict_types=1);

namespace App\Domain\Returns\Policies;

use App\Domain\Returns\Models\OrderReturn;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class OrderReturnPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OrderReturn');
    }

    public function view(AuthUser $authUser, OrderReturn $orderReturn): bool
    {
        return $authUser->can('View:OrderReturn');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OrderReturn');
    }

    public function update(AuthUser $authUser, OrderReturn $orderReturn): bool
    {
        return $authUser->can('Update:OrderReturn');
    }

    public function delete(AuthUser $authUser, OrderReturn $orderReturn): bool
    {
        return $authUser->can('Delete:OrderReturn');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:OrderReturn');
    }

    public function restore(AuthUser $authUser, OrderReturn $orderReturn): bool
    {
        return $authUser->can('Restore:OrderReturn');
    }

    public function forceDelete(AuthUser $authUser, OrderReturn $orderReturn): bool
    {
        return $authUser->can('ForceDelete:OrderReturn');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OrderReturn');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OrderReturn');
    }

    public function replicate(AuthUser $authUser, OrderReturn $orderReturn): bool
    {
        return $authUser->can('Replicate:OrderReturn');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OrderReturn');
    }
}
