<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Project\ProjectStructureService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ProjectStructureHistoryController
{
    public function __construct(private readonly ProjectStructureService $projectStructureService)
    {
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $history = $this->projectStructureService->getStructureHistory((int) $args['id']);

        return ApiResponse::success($response, $history);
    }
}
