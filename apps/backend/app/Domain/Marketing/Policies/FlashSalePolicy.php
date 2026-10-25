<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Policies;

use App\Domain\Marketing\Models\FlashSale;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class FlashSalePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FlashSale');
    }

    public function view(AuthUser $authUser, FlashSale $flashSale): bool
    {
        return $authUser->can('View:FlashSale');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FlashSale');
    }

    public function update(AuthUser $authUser, FlashSale $flashSale): bool
    {
        return $authUser->can('Update:FlashSale');
    }

    public function delete(AuthUser $authUser, FlashSale $flashSale): bool
    {
        return $authUser->can('Delete:FlashSale');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:FlashSale');
    }

    public function restore(AuthUser $authUser, FlashSale $flashSale): bool
    {
        return $authUser->can('Restore:FlashSale');
    }

    public function forceDelete(AuthUser $authUser, FlashSale $flashSale): bool
    {
        return $authUser->can('ForceDelete:FlashSale');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:FlashSale');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:FlashSale');
    }

    public function replicate(AuthUser $authUser, FlashSale $flashSale): bool
    {
        return $authUser->can('Replicate:FlashSale');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FlashSale');
    }
}
