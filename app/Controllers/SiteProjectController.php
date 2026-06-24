<?php

namespace App\Controllers\SiteApi;

use App\Models\Project;
use Whis\Http\Controller;
use Whis\Http\Request;
use Whis\Http\Response;

class SiteProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $limit = $this->intBetween($request->query('limit') ?? 12, 1, 60);
        $page  = $this->intBetween($request->query('page') ?? 1, 1, 9999);
        $q     = trim((string) ($request->query('q') ?? ''));

        /*
         * Ajusta esto según tu Model.
         * Si tu Model aún no tiene query builder, puedes reemplazar
         * esta parte por Model::all() y filtrar manualmente.
         */

        $projects = Project::all('created_at') ?? [];

        $projects = array_values(array_filter($projects, function ($project) use ($q) {
            if (! $this->isPublished($project)) {
                return false;
            }

            if ($q === '') {
                return true;
            }

            $haystack = mb_strtolower(
                ($project->title ?? '') . ' ' .
                ($project->name ?? '') . ' ' .
                ($project->summary ?? '') . ' ' .
                ($project->state ?? '') . ' ' .
                ($project->location ?? '')
            );

            return str_contains($haystack, mb_strtolower($q));
        }));

        $offset = ($page - 1) * $limit;
        $items  = array_slice($projects, $offset, $limit);

        return Response::json([
            'ok'       => true,
            'page'     => $page,
            'limit'    => $limit,
            'total'    => count($projects),
            'projects' => array_map(fn ($project) => $this->publicProject($project), $items),
        ]);
    }

    public function latest(Request $request): Response
    {
        $limit = $this->intBetween($request->query('limit') ?? 6, 1, 24);

        $projects = Project::all('created_at') ?? [];

        $projects = array_values(array_filter(
            $projects,
            fn ($project) => $this->isPublished($project)
        ));

        $projects = array_slice($projects, 0, $limit);

        return Response::json([
            'ok'       => true,
            'projects' => array_map(fn ($project) => $this->publicProject($project), $projects),
        ]);
    }

    public function show(int|string $id): Response
    {
        $project = Project::find($id);

        if (! $project || ! $this->isPublished($project)) {
            return Response::json([
                'ok'      => false,
                'message' => 'Proyecto no encontrado.',
            ])->setStatus(404);
        }

        return Response::json([
            'ok'      => true,
            'project' => $this->publicProject($project, true),
        ]);
    }

    public function map(): Response
    {
        $projects = Project::all('created_at') ?? [];

        $projects = array_values(array_filter($projects, function ($project) {
            return $this->isPublished($project)
                && $this->hasCoordinate($project->lat ?? null)
                && $this->hasCoordinate($project->lng ?? null);
        }));

        return Response::json([
            'ok'     => true,
            'markers' => array_map(fn ($project) => [
                'id'       => $project->id ?? null,
                'title'    => $project->title ?? $project->name ?? 'Proyecto',
                'type'     => $project->type ?? 'project',
                'state'    => $project->state ?? '',
                'location' => $project->location ?? '',
                'lat'      => (float) $project->lat,
                'lng'      => (float) $project->lng,
                'href'     => $this->projectHref($project),
                'summary'  => $project->summary ?? '',
            ], $projects),
        ]);
    }

    private function publicProject(mixed $project, bool $detailed = false): array
    {
        $data = [
            'id'       => $project->id ?? null,
            'title'    => $project->title ?? $project->name ?? 'Proyecto',
            'slug'     => $project->slug ?? null,
            'summary'  => $project->summary ?? '',
            'state'    => $project->state ?? '',
            'location' => $project->location ?? '',
            'year'     => $project->year ?? null,
            'image'    => $project->image ?? null,
            'href'     => $this->projectHref($project),
        ];

        if ($detailed) {
            $data['description'] = $project->description ?? '';
            $data['gallery']     = $this->gallery($project);
        }

        return $data;
    }

    private function isPublished(mixed $project): bool
    {
        $status = strtolower((string) ($project->status ?? 'published'));

        return in_array($status, ['published', 'public', 'activo', 'active'], true);
    }

    private function projectHref(mixed $project): string
    {
        $slug = $project->slug ?? $project->id ?? '';

        return '/proyecto/' . ltrim((string) $slug, '/');
    }

    private function gallery(mixed $project): array
    {
        $gallery = $project->gallery ?? [];

        if (is_string($gallery)) {
            $decoded = json_decode($gallery, true);

            if (is_array($decoded)) {
                return $decoded;
            }

            return array_values(array_filter(array_map('trim', explode(',', $gallery))));
        }

        return is_array($gallery) ? $gallery : [];
    }

    private function hasCoordinate(mixed $value): bool
    {
        return is_numeric($value);
    }

    private function intBetween(mixed $value, int $min, int $max): int
    {
        $value = (int) $value;

        return max($min, min($max, $value));
    }
}