<?php

namespace App\Http\Controllers;

use App\Events\UserDataChanged;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User as UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection as UserResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class UserController extends Controller
{

    public function index(Request $request): UserResourceCollection
    {
        return UserResource::collection(UserModel::query()->paginate());
    }

    public function show(UserModel $user): UserResource
    {
        return new UserResource($user);
    }

    public function showById(int $id): JsonResponse
    {
        $user = UserModel::with('roles')->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->toArray(),
            'created_at' => $user->created_at?->toISOString(),
        ], Response::HTTP_OK);
    }

    public function updateById(int $id, Request $request): JsonResponse
    {
        $user = UserModel::with('roles')->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $id],
            'password' => ['sometimes', 'string', 'min:8'],
        ]);

        $user->update($validated);
        $user->refresh();

        UserDataChanged::dispatch($user, 'updated');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->toArray(),
            'created_at' => $user->created_at?->toISOString(),
        ], Response::HTTP_OK);
    }

    public function update(UserModel $user): JsonResponse
    {
        $validated = request()->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $user->id],
            'password' => ['sometimes', 'string', 'min:8'],
        ]);

        $user->update($validated);
        $user->refresh();

        UserDataChanged::dispatch($user, 'updated');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ], Response::HTTP_OK);
    }

    public function destroy(UserModel $user): JsonResponse
    {
        UserModel::query()->where('id', $user->id)->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function authorize(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = UserModel::with('roles')->where('email', $request->input('email'))->first();

        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'authorized' => false,
                'message' => 'Invalid credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'authorized' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->toArray(),
                'created_at' => $user->created_at?->toISOString(),
            ],
        ], Response::HTTP_OK);
    }
}
