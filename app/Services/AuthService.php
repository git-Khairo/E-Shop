<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /** @return array{user: User, token: string} */
    public function register(array $data): array
    {
        $user = User::create([
            'username' => $data['username'],
            'email'    => $data['email'],
            'password' => $data['password'], // cast to 'hashed' in the model
        ]);

        return [
            'user'  => $user,
            'token' => $user->createToken('api', ['*'], now()->addDays(30))->plainTextToken,
        ];
    }

    /** @return array{user: User, token: string} */
    public function login(array $data): array
    {
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return [
            'user'  => $user,
            'token' => $user->createToken('api', ['*'], now()->addDays(30))->plainTextToken,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
