<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\FiscalProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->with('roles')
            ->orderBy('name')
            ->paginate(15);

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        return view('users.form', [
            'user' => new User(),
            'roles' => Role::query()->where('is_active', true)->orderBy('name')->get(),
            'fiscalProfiles' => FiscalProfile::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'action' => route('web.users.store'),
            'method' => 'POST',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $roles = $data['roles'] ?? [];
        $fiscalProfiles = $data['fiscal_profiles'] ?? [];
        unset($data['roles'], $data['fiscal_profiles']);

        $user = User::query()->create($data);
        $user->roles()->sync($roles);
        $user->fiscalProfiles()->sync($fiscalProfiles);

        return redirect()->route('web.users.index')->with('status', 'Usuario creado.');
    }

    public function edit(User $user): View
    {
        return view('users.form', [
            'user' => $user->load('roles', 'fiscalProfiles'),
            'roles' => Role::query()->where('is_active', true)->orderBy('name')->get(),
            'fiscalProfiles' => FiscalProfile::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'action' => route('web.users.update', $user),
            'method' => 'PUT',
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        $roles = $data['roles'] ?? [];
        $fiscalProfiles = $data['fiscal_profiles'] ?? [];
        unset($data['roles'], $data['fiscal_profiles']);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);
        $user->roles()->sync($roles);
        $user->fiscalProfiles()->sync($fiscalProfiles);

        return redirect()->route('web.users.index')->with('status', 'Usuario actualizado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:10'],
            'roles' => ['array'],
            'roles.*' => ['integer', 'exists:roles,id'],
            'fiscal_profiles' => ['array'],
            'fiscal_profiles.*' => ['integer', 'exists:fiscal_profiles,id'],
        ]);
    }
}
