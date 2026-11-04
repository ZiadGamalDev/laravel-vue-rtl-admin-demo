<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        return UserResource::collection(User::query()->orderBy('id')->get());
    }

    public function update(Request $request, User $user): UserResource|JsonResponse
    {
        $this->authorize('viewAny', User::class);

        if ($request->user()->is($user)) {
            return response()->json(['message' => __('messages.own_role')], 403);
        }
        $this->authorize('updateRole', $user);

        $validated = $request->validate(['role' => ['required', Rule::enum(Role::class)]]);
        $user->forceFill(['role' => $validated['role']])->save();

        return new UserResource($user);
    }
}
