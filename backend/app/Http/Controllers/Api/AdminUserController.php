<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection(
            User::query()->orderBy('name')->get()
        );
    }

    public function update(UpdateUserRoleRequest $request, User $user): UserResource
    {
        $role = UserRole::from($request->string('role')->toString());

        if ($user->isAdmin() && $role !== UserRole::Admin && $this->adminCount() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'Az utolsó admin szerepét nem lehet megváltoztatni.',
            ]);
        }

        $user->role = $role;
        $user->save();

        return new UserResource($user);
    }

    private function adminCount(): int
    {
        return User::query()->where('role', UserRole::Admin)->count();
    }
}
