<?php

namespace App\Http\Controllers;

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
    public function store(): JsonResponse
    {
        $user = new UserModel(request()->all());
        $user->save();

        return response()->json($user, Response::HTTP_CREATED);
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
        path: '/api/users/authorize',
        summary: 'Authorize user by email and password',
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
        tags: ['Users'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authorized',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'authorised', type: 'boolean', example: true),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Unauthorized',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'authorised', type: 'boolean', example: false),
                    ]
                )
            )
        ]
    )]
    public function authorize(Request $request): JsonResponse
    {
        $responseData['authorised'] = false;
        $status = Response::HTTP_FORBIDDEN;

        $request->validate([
            'email' => ['required', 'email:rfc,dns'],
            'password' => ['required', Password::min(8)->letters()->mixedCase()->numbers()->symbols()]],
        );

        $user = UserModel::query()->where('email', $request->query('email'))->first();

        if ($user->email && Hash::check($request->query('password'), $user->password)) {
            $responseData['authorised'] = true;
            $status = Response::HTTP_OK;
        }

        return response()->json($responseData, $status);
    }
}
