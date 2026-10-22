<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait ResolvesUser
{
    protected function actor(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isActive() && $user->hasVerifiedEmail(), 403);

        return $user;
    }
}
