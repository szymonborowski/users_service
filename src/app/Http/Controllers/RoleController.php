<?php

namespace App\Http\Controllers;

use App\Events\UserDataChanged;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class RoleController extends Controller
{
    #[OA\Get(
        path: '/api/roles',
        summary: 'List all roles',
        tags: ['Roles'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of roles',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'description', type: 'string'),
                            new OA\Property(property: 'level', type: 'integer'),
                        ]
                    )
                )
            )
        ]
    )]
    public function index(): JsonResponse
    {
        $roles = Role::orderByDesc('level')->get(['id', 'name', 'description', 'level']);

        return response()->json($roles);
    }

    #[OA\Post(
        path: '/api/users/{userId}/roles',
        summary: 'Assign role to user',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['role'],
                properties: [
                    new OA\Property(property: 'role', type: 'string', example: 'author'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Role assigned'),
            new OA\Response(response: 404, description: 'User or role not found'),
        ]
    )]
    public function assignRole(Request $request, int $userId): JsonResponse
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $role = Role::where('name', $validated['role'])->first();
        $user->roles()->syncWithoutDetaching([$role->id]);

        UserDataChanged::dispatch($user->load('roles'), 'updated');

        return response()->json([
            'message' => 'Role assigned',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->toArray(),
            ],
        ]);
    }

    #[OA\Delete(
        path: '/api/users/{userId}/roles/{roleName}',
        summary: 'Remove role from user',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'roleName', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Role removed'),
            new OA\Response(response: 404, description: 'User or role not found'),
        ]
    )]
    public function removeRole(int $userId, string $roleName): JsonResponse
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $role = Role::where('name', $roleName)->first();

        if (!$role) {
            return response()->json(['message' => 'Role not found'], Response::HTTP_NOT_FOUND);
        }

        $user->roles()->detach($role->id);

        UserDataChanged::dispatch($user->load('roles'), 'updated');

        return response()->json([
            'message' => 'Role removed',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->toArray(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/users/{userId}/roles',
        summary: 'Get user roles',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User roles',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function getUserRoles(int $userId): JsonResponse
    {
        $user = User::with('roles')->find($userId);

        if (!$user) {
            return response()->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'roles' => $user->roles->pluck('name')->toArray(),
        ]);
    }
}
