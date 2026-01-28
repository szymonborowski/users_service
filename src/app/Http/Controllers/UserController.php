<?php

namespace App\Http\Controllers;

use App\Events\UserDataChanged;
use App\Http\Resources\UserResource;
use App\Models\User as UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection as UserResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use OpenApi\Attributes as OA;

class UserController extends Controller
{

    #[OA\Get(
        path: '/api/users',
        summary: 'List users',
        tags: ['Users'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of users',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/User')
                        ),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): UserResourceCollection
    {
        return UserResource::collection(UserModel::query()->paginate());
    }

    #[OA\Get(
        path: '/api/users/{id}',
        summary: 'Get user',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            ref: '#/components/schemas/User'
                        )
                    ]
                )
            )
        ]
    )]
    public function show(UserModel $user): UserResource
    {
        return new UserResource($user);
    }

    public function showById(int $id): JsonResponse
    {
        $user = UserModel::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at?->toISOString(),
        ], Response::HTTP_OK);
    }

    #[OA\Post(
        path: '/api/users',
        summary: 'Create new user',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'P@ssw0rd!'),
                ]
            )
        ),
        tags: ['Users'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'User created',
                content: new OA\JsonContent(ref: '#/components/schemas/User')
            )
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $validated = validator($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ])->validate();

        $user = UserModel::create($validated);

        UserDataChanged::dispatch($user, 'created');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at?->toISOString(),
        ], Response::HTTP_CREATED);
    }

    #[OA\Put(
        path: '/api/users/{id}',
        summary: 'Update user',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Updated Name'),
                    new OA\Property(property: 'email', type: 'string', example: 'updated@example.com'),
                ]
            )
        ),
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User updated'),
            new OA\Response(response: 404, description: 'User not found')
        ]
    )]
    public function update(UserModel $user): JsonResponse
    {
        UserModel::query()->where('id', $user->id)->update(request()->all());

        $user->refresh();
        UserDataChanged::dispatch($user, 'updated');

        return response()->json(null, Response::HTTP_OK);
    }

    #[OA\Delete(
        path: '/api/users/{id}',
        summary: 'Delete user',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'User deleted'),
            new OA\Response(response: 404, description: 'User not found')
        ]
    )]
    public function destroy(UserModel $user): JsonResponse
    {
        UserModel::query()->where('id', $user->id)->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    #[OA\Post(
        path: '/api/auth/check',
        summary: 'Validate user credentials',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'P@ssw0rd!'),
                ]
            )
        ),
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Credentials valid',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'authorized', type: 'boolean', example: true),
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Invalid credentials',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'authorized', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid credentials'),
                    ]
                )
            )
        ]
    )]
    public function authorize(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = UserModel::query()->where('email', $request->input('email'))->first();

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
                'created_at' => $user->created_at?->toISOString(),
            ],
        ], Response::HTTP_OK);
    }
}
