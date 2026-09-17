<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class Impersonation
{
    public const SESSION_KEY = 'impersonator_id';

    public static function start(User $actor, User $target): void
    {
        Auth::login($target);
        session()->put(self::SESSION_KEY, $actor->id);
    }

    public static function stop(): ?User
    {
        $impersonatorId = session()->pull(self::SESSION_KEY);

        if (! $impersonatorId) {
            return null;
        }

        $impersonator = User::query()->find($impersonatorId);

        if (! $impersonator) {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();

            return null;
        }

        Auth::login($impersonator);

        return $impersonator;
    }

    public static function id(): ?int
    {
        $id = session(self::SESSION_KEY);

        return $id ? (int) $id : null;
    }

    public static function active(): bool
    {
        return self::id() !== null;
    }
}
