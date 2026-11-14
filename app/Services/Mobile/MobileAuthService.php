<?php

namespace App\Services\Mobile;

use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Exceptions\MobileApiException;
use App\Models\Branch;
use App\Models\User;
use App\Support\Mobile\ActiveBranchResolver;
use App\Support\Saas\ClinicProvisioningService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileAuthService
{
    public function __construct(protected ActiveBranchResolver $branches) {}

    /**
     * @param  array{name: string, subdomain: string, owner_name: string, owner_email: string, password: string, package_id?: int|null}  $data
     * @return array{token: string, expires_at: ?string, user: User, branch: ?Branch}
     */
    public function register(array $data, ClinicProvisioningService $provisioning, ?string $deviceName, ?int $requestedBranchId = null): array
    {
        $packageId = $data['package_id'] ?? $provisioning->defaultPublicPackage()?->id;

        $result = $provisioning->provision(
            name: $data['name'],
            subdomain: $data['subdomain'],
            ownerName: $data['owner_name'],
            ownerEmail: $data['owner_email'],
            ownerPassword: $data['password'],
            status: TenantStatus::Trial,
            packageId: $packageId,
            startTrial: true,
        );

        activity_log('registered', $result['tenant'], [
            'subdomain' => $result['tenant']->subdomain,
            'owner_email' => $result['owner']->email,
        ], 'Public registration for '.$result['tenant']->name, 'auth', $result['owner']);

        return $this->issueToken($result['owner'], $deviceName, $requestedBranchId);
    }

    /**
     * @return array{token: string, expires_at: ?string, user: User, branch: ?Branch}
     */
    public function login(string $email, string $password, ?string $deviceName, string $ip, ?int $requestedBranchId = null): array
    {
        $this->ensureIsNotRateLimited($email, $ip);

        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($this->throttleKey($email, $ip));

            activity_log(
                'login_failed',
                $user,
                ['email' => $email],
                'Failed login attempt for '.$email,
                'auth',
                $user,
            );

            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        if ($user->status !== UserStatus::Active) {
            throw new MobileApiException(
                'This account is inactive. Please contact the administrator.',
                'account_inactive',
                422,
                ['email' => ['This account is inactive. Please contact the administrator.']]
            );
        }

        if ($user->isPlatformAdmin()) {
            throw new MobileApiException(
                'Akun platform hanya dapat masuk lewat web.',
                'platform_admin_web_only',
                403,
            );
        }

        RateLimiter::clear($this->throttleKey($email, $ip));

        activity_log('login', $user, [], 'Signed in', 'auth', $user);

        return $this->issueToken($user, $deviceName, $requestedBranchId);
    }

    /**
     * @return array{token: string, expires_at: ?string, user: User, branch: ?Branch}
     */
    protected function issueToken(User $user, ?string $deviceName, ?int $requestedBranchId = null): array
    {
        $name = trim((string) $deviceName);
        $name = $name === '' ? 'mobile' : mb_substr($name, 0, 255);

        $user->tokens()->where('name', $name)->delete();
        $issued = $user->createToken($name, ['mobile'], now()->addDays(30));

        try {
            $branch = $this->branches->resolve($user, $requestedBranchId, false);
        } catch (MobileApiException $exception) {
            $defaultBranchRejected = $requestedBranchId === null
                && in_array($exception->errorCode, ['branch_forbidden', 'branch_inactive'], true);

            if (! $defaultBranchRejected) {
                $issued->accessToken->delete();
                throw $exception;
            }

            $branch = null;
        }

        return [
            'token' => $issued->plainTextToken,
            'expires_at' => $issued->accessToken->expires_at?->toIso8601String(),
            'user' => $user,
            'branch' => $branch,
        ];
    }

    protected function ensureIsNotRateLimited(string $email, string $ip): void
    {
        $key = $this->throttleKey($email, $ip);

        if (! RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($key);

        abort(response()->json([
            'message' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ], 429));
    }

    protected function throttleKey(string $email, string $ip): string
    {
        return Str::transliterate(Str::lower($email).'|'.$ip);
    }
}