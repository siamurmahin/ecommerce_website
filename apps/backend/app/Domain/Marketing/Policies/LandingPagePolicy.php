<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Policies;

use App\Domain\Marketing\Models\LandingPage;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LandingPagePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LandingPage');
    }

    public function view(AuthUser $authUser, LandingPage $landingPage): bool
    {
        return $authUser->can('View:LandingPage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LandingPage');
    }

    public function update(AuthUser $authUser, LandingPage $landingPage): bool
    {
        return $authUser->can('Update:LandingPage');
    }

    public function delete(AuthUser $authUser, LandingPage $landingPage): bool
    {
        return $authUser->can('Delete:LandingPage');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LandingPage');
    }

    public function restore(AuthUser $authUser, LandingPage $landingPage): bool
    {
        return $authUser->can('Restore:LandingPage');
    }

    public function forceDelete(AuthUser $authUser, LandingPage $landingPage): bool
    {
        return $authUser->can('ForceDelete:LandingPage');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LandingPage');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LandingPage');
    }

    public function replicate(AuthUser $authUser, LandingPage $landingPage): bool
    {
        return $authUser->can('Replicate:LandingPage');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LandingPage');
    }
}
