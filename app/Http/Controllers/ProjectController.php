<?php

namespace App\Http\Controllers;

use App\Helpers\ProjectValidator;
use App\Helpers\UserValidator;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// multi tenant controller
class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $service) {}

    public function showProjects(Request $request): Response
    {
        $projects = $this->service->showProjects(
            (int) $request->user()->getKey(),
        );

        return response($projects);
    }

    public function createProject(Request $request): Response
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $project = $this->service->createProject(
            (int) $request->user()->getKey(),
            $data['name'],
        );

        return response($project, 201);
    }

    public function updateProject(Request $request, string $projectId): Response
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $project = $this->service->updateProject(
            (int) $request->user()->getKey(),
            ProjectValidator::id($projectId),
            $data['name'],
        );

        return response($project);
    }

    public function deleteProject(Request $request, string $projectId): Response
    {
        $this->service->deleteProject(
            (int) $request->user()->getKey(),
            ProjectValidator::id($projectId),
        );

        return response()->noContent();
    }

    public function addMember(Request $request, string $projectId, string $userId): Response
    {
        $this->service->addMember(
            (int) $request->user()->getKey(),
            ProjectValidator::id($projectId),
            UserValidator::id($userId),
        );

        return response()->noContent();
    }

    public function removeMember(Request $request, string $projectId, string $userId): Response
    {
        $this->service->removeMember(
            (int) $request->user()->getKey(),
            ProjectValidator::id($projectId),
            UserValidator::id($userId),
        );

        return response()->noContent();
    }
}
