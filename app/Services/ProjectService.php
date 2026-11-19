<?php

namespace App\Services;

class ProjectService
{
    public function listProjects(int $actorId): void {}

    public function createProject(int $actorId, string $name): void {}

    public function showProject(int $actorId, int $projectId): void {}

    public function updateProject(int $actorId, int $projectId, string $name): void {}

    public function deleteProject(int $actorId, int $projectId): void {}

    public function addMember(int $actorId, int $projectId, int $userId): void {}

    public function removeMember(int $actorId, int $projectId, int $userId): void {}
}
