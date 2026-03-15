<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'role' => 'required|in:'.implode(',', User::roles()),
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $payload = [
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ];

        if (Schema::hasColumn('users', 'name')) {
            $payload['name'] = $request->name;
        }

        if (Schema::hasColumn('users', 'first_name') && Schema::hasColumn('users', 'last_name')) {
            $nameParts = preg_split('/\s+/', trim((string) $request->name)) ?: [];
            $payload['first_name'] = $nameParts[0] ?? $request->name;
            $payload['last_name'] = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : 'User';
        }

        if (Schema::hasColumn('users', 'role')) {
            $payload['role'] = $request->role;
        }

        if (Schema::hasColumn('users', 'role_id') && Schema::hasTable('roles')) {
            $roleName = $request->role === User::ROLE_LGU ? 'lgu_manager' : $request->role;
            $role = Role::query()->firstOrCreate(['name' => $roleName]);
            $payload['role_id'] = $role->id;
        }

        $user = User::create($payload);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
