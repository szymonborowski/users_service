<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Users API',
    version: '1.0.0',
    description: 'API for managing users'
)]
#[OA\Server(
    url: 'https://users.microservices.local',
    description: 'Production'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
#[OA\SecurityScheme(
    securityScheme: 'internalApiKey',
    type: 'apiKey',
    in: 'header',
    name: 'X-Internal-Api-Key'
)]
class OpenApi
{
}
