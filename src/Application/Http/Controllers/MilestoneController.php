<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Responders\ApiResponse;
use App\Domain\Project\MilestoneService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

final class MilestoneController implements RequestHandler
{
    public function __construct(private readonly MilestoneService $milestoneService)
    {
    }

    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $projectId = (int) $request->getAttribute('project_id');
        $method = $request->getMethod();
        $data = $request->getParsedBody();

        switch ($method) {
            case 'GET':
                $milestoneId = $request->getAttribute('milestone_id');
                if ($milestoneId !== null) {
                    $milestone = $this->milestoneService->getById((int) $milestoneId);
                    return $milestone !== null
                        ? ApiResponse::success($milestone, 'Milestone retrieved')
                        : \App\Application\Http\Responders\ApiResponse::error('NOT_FOUND', 'Milestone not found', [], 404);
                }
                $milestones = $this->milestoneService->getByProjectId($request->getAttribute('project_id'));
                return \App\Application\Http\Responders\ApiResponse::success($milestones, 'Milestones retrieved');

            case 'POST':
                $data = $request->getParsedBody();
                $milestoneId = $this->milestoneService->create(
                    $data['code'] ?? '',
                    $data['title'] ?? '',
                    $request->getAttribute('project_id'),
                    $request->getAttribute('workspace_id'),
                    $data['planned_date'] ?? null,
                    $request->getAttribute('user_id')
                );
                return \App\Application\Http\Responders\ApiResponse::success(['id' => $milestoneId], 'Milestone created', 201);

            case 'PATCH':
                $milestoneId = $request->getAttribute('milestone_id');
                if ($milestoneId === null) {
                    return \App\Application\Http\Responders\ApiResponse::error('VALIDATION_ERROR', 'Milestone ID required', [], 400);
                }

                $data = $request->getParsedBody();
                if (isset($data['action']) && $data['action'] === 'close') {
                    $this->milestoneService->close((int) $milestoneId, $request->getAttribute('user_id'));
                    return \App\Application\Http\Responders\ApiResponse::success(new \stdClass(), 'Milestone closed');
                }

                if (isset($data['title'])) {
                    $this->milestoneService->updateTitle((int) $milestoneId, $data['title']);
                    return \App\Application\Http\Responders\ApiResponse::success(new \stdClass(), 'Milestone updated');
                }

                return \App\Application\Http\Responders\ApiResponse::error('VALIDATION_ERROR', 'Invalid action', [], 400);

            default:
                return \App\Application\Http\Responders\ApiResponse::error('METHOD_NOT_ALLOWED', 'Method not allowed', [], 405);
        }
    }
}