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
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    #[OA\Get(
        path: '/api/users',
        summary: 'List users',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of users', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/User'))]
            )),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): UserResourceCollection
    {
        return UserResource::collection(UserModel::query()->paginate());
    }

    #[OA\Get(
        path: '/api/users/{user}',
        summary: 'Show a user',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'User details', content: new OA\JsonContent(ref: '#/components/schemas/User')),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function show(UserModel $user): UserResource
    {
        return new UserResource($user);
    }

    #[OA\Post(
        path: '/api/users',
        summary: 'Create a user',
        security: [['bearerAuth' => []], ['internalApiKey' => []]],
        tags: ['Users'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'email', 'password'],
            properties: [
                new OA\Property(property: 'name', type: 'string', minLength: 3, maxLength: 32),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', minLength: 8),
            ]
        )),
        responses: [
            new OA\Response(response: 201, description: 'User created', content: new OA\JsonContent(ref: '#/components/schemas/User')),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[a-zA-Z0-9_-]+$/', 'unique:users,name'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = UserModel::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole(Role::READER);

        UserDataChanged::dispatch($user, 'created');

        return (new UserResource($user->load('roles')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/api/internal/users/{id}',
        summary: 'Show a user by ID (internal)',
        security: [['internalApiKey' => []]],
        tags: ['Users (Internal)'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'User details'),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
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

    #[OA\Put(
        path: '/api/internal/users/{id}',
        summary: 'Update a user by ID (internal)',
        security: [['internalApiKey' => []]],
        tags: ['Users (Internal)'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 255),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', minLength: 8),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'User updated'),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function updateById(int $id, Request $request): JsonResponse
    {
        $user = UserModel::with('roles')->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:3', 'max:32', 'regex:/^[a-zA-Z0-9_-]+$/', "unique:users,name,$id"],
            'email' => ['sometimes', 'email', "unique:users,email,$id"],
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

    #[OA\Put(
        path: '/api/users/{user}',
        summary: 'Update a user',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 255),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', minLength: 8),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'User updated'),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function update(UserModel $user): JsonResponse
    {
        $validated = request()->validate([
            'name' => ['sometimes', 'string', 'min:3', 'max:32', 'regex:/^[a-zA-Z0-9_-]+$/', "unique:users,name,{$user->id}"],
            'email' => ['sometimes', 'email', "unique:users,email,{$user->id}"],
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

    #[OA\Delete(
        path: '/api/users/{user}',
        summary: 'Delete a user',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'User deleted'),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function destroy(UserModel $user): JsonResponse
    {
        UserModel::query()->where('id', $user->id)->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    #[OA\Delete(
        path: '/api/internal/users/{id}',
        summary: 'Delete a user by ID (internal)',
        security: [['internalApiKey' => []]],
        tags: ['Users (Internal)'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'User deleted'),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function destroyById(int $id): JsonResponse
    {
        $user = UserModel::query()->find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $user->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    #[OA\Post(
        path: '/api/internal/auth/check',
        summary: 'Verify user credentials (internal)',
        security: [['internalApiKey' => []]],
        tags: ['Auth (Internal)'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string'),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Credentials valid'),
            new OA\Response(response: 401, description: 'Invalid credentials'),
        ]
    )]
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
