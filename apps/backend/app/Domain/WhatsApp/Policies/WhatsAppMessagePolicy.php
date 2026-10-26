<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Policies;

use App\Domain\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class WhatsAppMessagePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WhatsAppMessage');
    }

    public function view(AuthUser $authUser, WhatsAppMessage $message): bool
    {
        return $authUser->can('View:WhatsAppMessage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WhatsAppMessage');
    }

    public function update(AuthUser $authUser, WhatsAppMessage $message): bool
    {
        return $authUser->can('Update:WhatsAppMessage');
    }

    public function delete(AuthUser $authUser, WhatsAppMessage $message): bool
    {
        return $authUser->can('Delete:WhatsAppMessage');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:WhatsAppMessage');
    }

    public function restore(AuthUser $authUser, WhatsAppMessage $message): bool
    {
        return $authUser->can('Restore:WhatsAppMessage');
    }

    public function forceDelete(AuthUser $authUser, WhatsAppMessage $message): bool
    {
        return $authUser->can('ForceDelete:WhatsAppMessage');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WhatsAppMessage');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WhatsAppMessage');
    }

    public function replicate(AuthUser $authUser, WhatsAppMessage $message): bool
    {
        return $authUser->can('Replicate:WhatsAppMessage');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WhatsAppMessage');
    }
}
