<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Notifications\LogisticsResetPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class LogisticsPasswordResetController extends Controller
{
    private function credentials(
        string $email,
        bool $lock = false
    ): array {
        return [
            'email' => $email,

            'logistics_scope' => function ($query) use ($lock) {
                $query
                    ->whereExists(function ($roles) {
                        $roles
                            ->selectRaw('1')
                            ->from('role_user')
                            ->join(
                                'roles',
                                'roles.id',
                                '=',
                                'role_user.role_id'
                            )
                            ->whereColumn(
                                'role_user.user_id',
                                'users.id'
                            )
                            ->where('roles.name', 'logistics');
                    })
                    ->whereExists(function ($providers) {
                        $providers
                            ->selectRaw('1')
                            ->from('logistics_providers')
                            ->whereColumn(
                                'logistics_providers.user_id',
                                'users.id'
                            );
                    });

                if ($lock) {
                    $query->lockForUpdate();
                }
            },
        ];
    }

    public function requestForm()
    {
        return view('logistics.auth.password', [
            'mode' => 'request',
        ]);
    }

    public function sendLink(Request $request)
    {
        $data = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
            ],
        ]);

        $email = Str::lower(trim($data['email']));

        try {
            Password::broker('users')->sendResetLink(
                $this->credentials($email),
                function ($user, $token) {
                    $user->notify(
                        new LogisticsResetPassword($token)
                    );
                }
            );
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'email' => 'Password reset email is currently unavailable. Please try again later.',
            ]);
        }

        // Keep the response identical for unknown, non-logistics,
        // and temporarily throttled accounts.
        return back()->with(
            'status',
            'If this email belongs to a logistics account, a reset link will be sent. Please check your inbox and spam folder.'
        );
    }

    public function resetForm(
        Request $request,
        string $token
    ) {
        $data = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
            ],
        ]);

        return view('logistics.auth.password', [
            'mode' => 'reset',
            'token' => $token,
            'email' => $data['email'],
        ]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:72',
                'confirmed',
            ],
        ]);

        $credentials = $this->credentials(
            Str::lower(trim($data['email'])),
            true
        );

        $credentials['token'] = $data['token'];
        $credentials['password'] = $data['password'];
        $credentials['password_confirmation'] =
            $request->input('password_confirmation');

        $status = DB::transaction(function () use ($credentials) {
            return Password::broker('users')->reset(
                $credentials,
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    if (config('session.driver') === 'database') {
                        DB::connection(
                            config('session.connection')
                        )
                            ->table(
                                config('session.table', 'sessions')
                            )
                            ->where(
                                'user_id',
                                $user->getAuthIdentifier()
                            )
                            ->delete();
                    }

                    event(new PasswordReset($user));
                }
            );
        }, 3);

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'This reset link is invalid or expired. Please request a new link.',
            ]);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('logistics.login')
            ->with(
                'status',
                'Password reset successfully. Sign in using your new password.'
            );
    }
}
