<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Policies;

use App\Domain\Inventory\Models\StockAlert;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class StockAlertPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StockAlert');
    }

    public function view(AuthUser $authUser, StockAlert $stockAlert): bool
    {
        return $authUser->can('View:StockAlert');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StockAlert');
    }

    public function update(AuthUser $authUser, StockAlert $stockAlert): bool
    {
        return $authUser->can('Update:StockAlert');
    }

    public function delete(AuthUser $authUser, StockAlert $stockAlert): bool
    {
        return $authUser->can('Delete:StockAlert');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:StockAlert');
    }

    public function restore(AuthUser $authUser, StockAlert $stockAlert): bool
    {
        return $authUser->can('Restore:StockAlert');
    }

    public function forceDelete(AuthUser $authUser, StockAlert $stockAlert): bool
    {
        return $authUser->can('ForceDelete:StockAlert');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:StockAlert');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:StockAlert');
    }

    public function replicate(AuthUser $authUser, StockAlert $stockAlert): bool
    {
        return $authUser->can('Replicate:StockAlert');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:StockAlert');
    }
}
