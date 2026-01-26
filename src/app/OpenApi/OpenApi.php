<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Users API',
    version: '1.0.0',
    description: 'API do zarządzania użytkownikami'
)]
#[OA\Server(
    url: 'https://users.microservices.local',
    description: 'Production'
)]
class OpenApi
{
}
