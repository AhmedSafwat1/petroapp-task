<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Station transfer events API',
    version: '1.0.0',
)]
#[OA\Server(
    url: 'http://localhost:8010/api',
    description: 'Default (set APP_URL / host in Swagger “Try it out” for other environments)',
)]
final class ApiSpecification {}
