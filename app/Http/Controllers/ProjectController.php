<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

// multi tenant controller
class ProjectController extends Controller
{
    public function listProjects(Request $request): Response
    {
        return response()->noContent(501);
    }

    public function createProject(Request $request): Response
    {
        return response()->noContent(501);
    }

    public function showProject(Request $request, int $projectId): Response
    {
        return response()->noContent(501);
    }

    public function updateProject(Request $request, int $projectId): Response
    {
        return response()->noContent(501);
    }

    public function deleteProject(Request $request, int $projectId): Response
    {
        return response()->noContent(501);
    }

    public function addMember(Request $request, int $projectId, int $userId): Response
    {
        return response()->noContent(501);
    }

    public function removeMember(Request $request, int $projectId, int $userId): Response
    {
        return response()->noContent(501);
    }
}
