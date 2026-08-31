<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Project\ProjectStructureService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

final class ProjectStructureHistoryController implements RequestHandler
{
    public function __construct(private readonly \App\Domain\Project\ProjectStructureService $projectStructureService)
    {
    }

    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $projectId = (int) $request->getAttribute('project_id');
        $history = $this->projectStructureService->getStructureHistory($request->getAttribute('project_id'));

        return ApiResponse::success($history, 'Project structure history retrieved');
    }
}