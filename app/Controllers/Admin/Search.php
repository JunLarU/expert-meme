<?php

namespace App\Controllers\Admin;

use App\Models\AssociationCertificationSlide;
use App\Models\Client;
use App\Models\HomeJumbotronSlide;
use App\Models\Message;
use App\Models\OfficeWorkshop;
use App\Models\Project;
use App\Models\ProjectFact;
use App\Models\ProjectMedia;
use App\Models\ProjectResultStat;
use App\Models\ProjectScopeItem;
use App\Models\ProjectTag;
use App\Models\User;
use Whis\Http\Controller;
use Whis\Http\Request;

class Search extends Controller
{
    private const MIN_QUERY_LENGTH = 2;
    private const LIMIT_PER_MODULE = 10;
    private const MAX_QUERY_LENGTH = 120;

    public function index(?Request $request = null)
    {
        if (isGuest()) {
            return redirect('/login');
        }

        $request = $this->resolveRequest($request);
        $user    = auth();
        $isAdmin = $this->isAdminUser($user);

        $query        = $this->queryFromRequest($request);
        $searched     = $query !== '';
        $queryIsValid = $this->isValidQuery($query);

        $groups      = [];
        $resultCount = 0;

        if ($queryIsValid) {
            $groups      = $this->searchAll($query, $isAdmin);
            $resultCount = $this->countResults($groups);
        }

        return view('pages/admin/search-results', 'Búsqueda', [
            'user'         => $user,
            'query'        => $query,
            'searched'     => $searched,
            'queryIsValid' => $queryIsValid,
            'minLength'    => self::MIN_QUERY_LENGTH,
            'groups'       => $groups,
            'resultCount'  => $resultCount,
            'moduleCount'  => count($groups),
            'isAdmin'      => $isAdmin,
        ], 'layouts/admin/layout');
    }

    private function searchAll(string $query, bool $isAdmin): array
    {
        $groups = [];

        $this->addGroup($groups, $this->searchModule(
            'jumbotron',
            'Jumbotron',
            '▣',
            $this->safeRows(fn() => HomeJumbotronSlide::byPage('home')),
            ['id', 'title', 'eyebrow', 'subtitle', 'body', 'button_label', 'button_url', 'status', 'slug', 'background_alt'],
            fn(array $row) => [
                'title'    => $this->text($row, 'title', 'Slide sin título'),
                'subtitle' => $this->joinNonEmpty([
                    'Estado: ' . $this->statusLabel($this->text($row, 'status', 'draft')),
                    $this->text($row, 'eyebrow'),
                    $this->text($row, 'button_label') !== '' ? 'CTA activo' : 'Sin CTA',
                ]),
                'url'      => '/admin/jumbotron/' . (int) ($row['id'] ?? 0),
                'badge'    => $this->statusLabel($this->text($row, 'status', 'draft')),
            ],
            $query
        ));

        $this->addGroup($groups, $this->searchModule(
            'projects',
            'Proyectos',
            '▤',
            $this->safeRows(fn() => Project::allProjects()),
            [
                'id', 'title', 'subtitle', 'brief', 'summary', 'description', 'category', 'category_badge',
                'client_name', 'client_type', 'service', 'specialty', 'material_system', 'location_display',
                'city', 'state', 'country', 'project_year', 'map_state', 'map_title', 'map_kind',
                'map_location', 'map_summary', 'status', 'slug', 'seo_title', 'seo_description',
            ],
            fn(array $row) => [
                'title'    => $this->text($row, 'title', 'Proyecto sin título'),
                'subtitle' => $this->joinNonEmpty([
                    $this->statusLabel($this->text($row, 'status', 'draft')),
                    $this->text($row, 'category'),
                    $this->text($row, 'location_display'),
                    $this->text($row, 'project_year'),
                ]),
                'url'      => '/admin/proyectos/' . (int) ($row['id'] ?? 0),
                'badge'    => $this->statusLabel($this->text($row, 'status', 'draft')),
            ],
            $query
        ));

        $this->addGroup($groups, $this->searchModule(
            'messages',
            'Mensajes',
            '✉',
            $this->safeRows(fn() => Message::forAdmin()),
            ['id', 'name', 'company', 'email', 'phone', 'service', 'subject', 'project_location', 'message', 'status', 'priority', 'source_page'],
            fn(array $row) => [
                'title'    => $this->text($row, 'subject', 'Mensaje de contacto'),
                'subtitle' => $this->joinNonEmpty([
                    $this->text($row, 'name', 'Contacto'),
                    $this->text($row, 'email'),
                    'Estado: ' . $this->messageStatusLabel($this->text($row, 'status', 'new')),
                    'Prioridad: ' . $this->priorityLabel($this->text($row, 'priority', 'normal')),
                ]),
                'url'      => '/admin/mensajes/' . (int) ($row['id'] ?? 0),
                'badge'    => $this->messageStatusLabel($this->text($row, 'status', 'new')),
            ],
            $query
        ));

        $this->addGroup($groups, $this->searchProjectData($query));

        if ($isAdmin) {
            $this->addGroup($groups, $this->searchModule(
                'clients',
                'Clientes',
                '◈',
                $this->safeRows(fn() => Client::ordered()),
                ['id', 'name', 'slug', 'url', 'logo_alt', 'initials', 'description', 'industry'],
                fn(array $row) => [
                    'title'    => $this->text($row, 'name', 'Cliente sin nombre'),
                    'subtitle' => $this->joinNonEmpty([
                        (int) ($row['is_active'] ?? 0) === 1 ? 'Activo' : 'Inactivo',
                        (int) ($row['is_featured'] ?? 0) === 1 ? 'Destacado' : '',
                        $this->text($row, 'industry'),
                    ]),
                    'url'      => '/admin/clientes/' . (int) ($row['id'] ?? 0),
                    'badge'    => (int) ($row['is_active'] ?? 0) === 1 ? 'Activo' : 'Inactivo',
                ],
                $query
            ));

            $this->addGroup($groups, $this->searchModule(
                'associations',
                'Asociaciones y certificaciones',
                '▥',
                $this->safeRows(fn() => AssociationCertificationSlide::ordered()),
                ['id', 'title', 'short_title', 'slug', 'url', 'image_alt', 'description'],
                fn(array $row) => [
                    'title'    => $this->text($row, 'title', 'Asociación o certificación'),
                    'subtitle' => $this->joinNonEmpty([
                        (int) ($row['is_active'] ?? 0) === 1 ? 'Activa' : 'Inactiva',
                        (int) ($row['show_in_home'] ?? 0) === 1 ? 'Home' : '',
                        (int) ($row['show_in_about'] ?? 0) === 1 ? 'Nosotros' : '',
                    ]),
                    'url'      => '/admin/asociaciones-certificaciones/' . (int) ($row['id'] ?? 0),
                    'badge'    => (int) ($row['is_active'] ?? 0) === 1 ? 'Activa' : 'Inactiva',
                ],
                $query
            ));

            $this->addGroup($groups, $this->searchModule(
                'office_workshops',
                'Oficinas y talleres',
                '⌖',
                $this->safeRows(fn() => OfficeWorkshop::allItems()),
                [
                    'id', 'title', 'slug', 'type', 'status', 'summary', 'description', 'address', 'city', 'state',
                    'postal_code', 'contact_name', 'phone', 'email', 'whatsapp', 'opening_hours', 'google_maps_url',
                    'map_title', 'map_kind', 'map_location', 'map_summary', 'map_image_alt',
                ],
                fn(array $row) => [
                    'title'    => $this->text($row, 'title', 'Oficina o taller'),
                    'subtitle' => $this->joinNonEmpty([
                        $this->officeTypeLabel($this->text($row, 'type', 'office')),
                        $this->statusLabel($this->text($row, 'status', 'draft')),
                        $this->joinNonEmpty([$this->text($row, 'city'), $this->text($row, 'state')], ', '),
                    ]),
                    'url'      => '/admin/oficinas-talleres/' . (int) ($row['id'] ?? 0),
                    'badge'    => $this->officeTypeLabel($this->text($row, 'type', 'office')),
                ],
                $query
            ));

            $this->addGroup($groups, $this->searchModule(
                'users',
                'Usuarios',
                '◉',
                $this->safeRows(fn() => User::forAdmin()),
                ['id', 'name', 'email', 'role'],
                fn(array $row) => [
                    'title'    => $this->text($row, 'name', 'Usuario'),
                    'subtitle' => $this->joinNonEmpty([
                        $this->text($row, 'email'),
                        $this->roleLabel($this->text($row, 'role', 'manager')),
                    ]),
                    'url'      => '/admin/usuarios/' . (int) ($row['id'] ?? 0),
                    'badge'    => $this->roleLabel($this->text($row, 'role', 'manager')),
                ],
                $query
            ));
        }

        usort($groups, function (array $a, array $b) {
            $scoreCompare = (int) ($b['max_score'] ?? 0) <=> (int) ($a['max_score'] ?? 0);

            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            return (int) ($b['total'] ?? 0) <=> (int) ($a['total'] ?? 0);
        });

        return $groups;
    }

    private function searchProjectData(string $query): array
    {
        $results = [];

        $sources = [
            [
                'label'  => 'Multimedia de proyecto',
                'rows'   => $this->safeRows(fn() => ProjectMedia::all('created_at', true) ?? []),
                'fields' => ['id', 'project_id', 'title', 'description', 'file_url', 'poster_url', 'alt_text', 'aria_label', 'media_type', 'display_area'],
                'title'  => fn(array $row) => $this->text($row, 'title', 'Archivo multimedia'),
                'badge'  => fn(array $row) => $this->mediaTypeLabel($this->text($row, 'media_type', 'media')),
            ],
            [
                'label'  => 'Tag de proyecto',
                'rows'   => $this->safeRows(fn() => ProjectTag::all('created_at', true) ?? []),
                'fields' => ['id', 'project_id', 'name', 'slug', 'type'],
                'title'  => fn(array $row) => $this->text($row, 'name', 'Tag'),
                'badge'  => fn(array $row) => $this->text($row, 'type', 'tag'),
            ],
            [
                'label'  => 'Dato destacado de proyecto',
                'rows'   => $this->safeRows(fn() => ProjectFact::all('created_at', true) ?? []),
                'fields' => ['id', 'project_id', 'label', 'value', 'icon'],
                'title'  => fn(array $row) => $this->joinNonEmpty([$this->text($row, 'label'), $this->text($row, 'value')], ': '),
                'badge'  => fn(array $row) => 'Fact',
            ],
            [
                'label'  => 'Alcance de proyecto',
                'rows'   => $this->safeRows(fn() => ProjectScopeItem::all('created_at', true) ?? []),
                'fields' => ['id', 'project_id', 'number_label', 'title', 'description', 'icon'],
                'title'  => fn(array $row) => $this->text($row, 'title', 'Alcance'),
                'badge'  => fn(array $row) => 'Alcance',
            ],
            [
                'label'  => 'Resultado de proyecto',
                'rows'   => $this->safeRows(fn() => ProjectResultStat::all('created_at', true) ?? []),
                'fields' => ['id', 'project_id', 'value', 'label', 'description'],
                'title'  => fn(array $row) => $this->joinNonEmpty([$this->text($row, 'value'), $this->text($row, 'label', 'Resultado')], ' '),
                'badge'  => fn(array $row) => 'Resultado',
            ],
        ];

        foreach ($sources as $source) {
            foreach ($source['rows'] as $row) {
                $score = $this->scoreRow($row, $source['fields'], $query);

                if ($score <= 0) {
                    continue;
                }

                $projectId = (int) ($row['project_id'] ?? 0);

                $results[] = [
                    'title'    => (string) ($source['title'])($row),
                    'subtitle' => $this->joinNonEmpty([
                        (string) $source['label'],
                        $projectId > 0 ? 'Proyecto #' . $projectId : '',
                    ]),
                    'excerpt'  => $this->excerpt($this->haystack($row, $source['fields']), $query),
                    'url'      => $projectId > 0 ? '/admin/proyectos/' . $projectId : '/admin/proyectos',
                    'badge'    => (string) ($source['badge'])($row),
                    'score'    => $score,
                ];
            }
        }

        usort($results, fn(array $a, array $b) => (int) ($b['score'] ?? 0) <=> (int) ($a['score'] ?? 0));

        return [
            'key'       => 'project_data',
            'title'     => 'Datos relacionados de proyectos',
            'icon'      => '#',
            'total'     => count($results),
            'max_score' => (int) ($results[0]['score'] ?? 0),
            'results'   => array_slice($results, 0, self::LIMIT_PER_MODULE),
        ];
    }

    private function searchModule(
        string $key,
        string $title,
        string $icon,
        array $rows,
        array $fields,
        callable $mapper,
        string $query
    ): array {
        $results = [];

        foreach ($rows as $row) {
            $score = $this->scoreRow($row, $fields, $query);

            if ($score <= 0) {
                continue;
            }

            $mapped = $mapper($row);

            $results[] = [
                'title'    => (string) ($mapped['title'] ?? 'Resultado'),
                'subtitle' => (string) ($mapped['subtitle'] ?? ''),
                'excerpt'  => $this->excerpt($this->haystack($row, $fields), $query),
                'url'      => (string) ($mapped['url'] ?? '/admin'),
                'badge'    => (string) ($mapped['badge'] ?? $title),
                'score'    => $score,
            ];
        }

        usort($results, fn(array $a, array $b) => (int) ($b['score'] ?? 0) <=> (int) ($a['score'] ?? 0));

        return [
            'key'       => $key,
            'title'     => $title,
            'icon'      => $icon,
            'total'     => count($results),
            'max_score' => (int) ($results[0]['score'] ?? 0),
            'results'   => array_slice($results, 0, self::LIMIT_PER_MODULE),
        ];
    }

    private function addGroup(array &$groups, array $group): void
    {
        if ((int) ($group['total'] ?? 0) <= 0) {
            return;
        }

        $groups[] = $group;
    }

    private function scoreRow(array $row, array $fields, string $query): int
    {
        $normalizedQuery = $this->normalize($query);
        $haystack        = $this->normalize($this->haystack($row, $fields));

        if ($normalizedQuery === '' || $haystack === '') {
            return 0;
        }

        $score = 0;

        if ($haystack === $normalizedQuery) {
            $score += 120;
        }

        if (str_contains($haystack, $normalizedQuery)) {
            $score += 60;
        }

        $importantFields = ['title', 'name', 'subject', 'email', 'slug'];

        foreach ($importantFields as $field) {
            $value = $this->normalize($this->text($row, $field));

            if ($value === '') {
                continue;
            }

            if ($value === $normalizedQuery) {
                $score += 80;
            } elseif (str_contains($value, $normalizedQuery)) {
                $score += 45;
            }
        }

        $words = array_values(array_filter(
            explode(' ', $normalizedQuery),
            fn(string $word) => mb_strlen($word) >= 2
        ));

        foreach ($words as $word) {
            if (str_contains($haystack, $word)) {
                $score += 10;
            }
        }

        return $score;
    }

    private function haystack(array $row, array $fields): string
    {
        $parts = [];

        foreach ($fields as $field) {
            $value = $row[$field] ?? '';

            if (is_scalar($value)) {
                $parts[] = (string) $value;
            }
        }

        return trim(implode(' ', array_filter($parts, fn(string $part) => trim($part) !== '')));
    }

    private function excerpt(string $text, string $query, int $max = 180): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?: '');

        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $normalizedText  = $this->normalize($text);
        $normalizedQuery = $this->normalize($query);
        $position        = $normalizedQuery !== '' ? mb_strpos($normalizedText, $normalizedQuery) : false;

        if ($position === false) {
            return mb_substr($text, 0, $max) . '...';
        }

        $start   = max(0, (int) $position - 60);
        $excerpt = mb_substr($text, $start, $max);

        return ($start > 0 ? '...' : '') . $excerpt . (mb_strlen($text) > $start + $max ? '...' : '');
    }

    private function queryFromRequest(?Request $request): string
    {
        $query = '';

        if ($request) {
            $query = (string) ($request->query('q') ?? '');
        }

        if ($query === '' && function_exists('request')) {
            try {
                $query = (string) (request()->query('q') ?? '');
            } catch (\Throwable $th) {
                $query = '';
            }
        }

        if ($query === '') {
            $query = (string) ($_GET['q'] ?? '');
        }

        $query = str_replace("\0", '', $query);
        $query = trim(preg_replace('/\s+/', ' ', $query) ?: '');

        return mb_substr($query, 0, self::MAX_QUERY_LENGTH);
    }

    private function resolveRequest(?Request $request): ?Request
    {
        if ($request) {
            return $request;
        }

        if (function_exists('request')) {
            try {
                $resolved = request();

                return $resolved instanceof Request ? $resolved : null;
            } catch (\Throwable $th) {
                return null;
            }
        }

        return null;
    }

    private function isValidQuery(string $query): bool
    {
        return mb_strlen(trim($query)) >= self::MIN_QUERY_LENGTH;
    }

    private function countResults(array $groups): int
    {
        return array_sum(array_map(fn(array $group) => (int) ($group['total'] ?? 0), $groups));
    }

    private function safeRows(callable $loader): array
    {
        try {
            $rows = $loader();

            return is_array($rows) ? array_values($rows) : [];
        } catch (\Throwable $th) {
            return [];
        }
    }

    private function text(array $row, string $key, string $fallback = ''): string
    {
        $value = $row[$key] ?? $fallback;

        if (! is_scalar($value)) {
            return $fallback;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : $fallback;
    }

    private function normalize(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        if ($converted !== false) {
            $value = $converted;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';

        return trim(preg_replace('/\s+/', ' ', $value) ?: '');
    }

    private function joinNonEmpty(array $parts, string $separator = ' · '): string
    {
        $parts = array_values(array_filter(array_map(
            fn($part) => trim((string) $part),
            $parts
        ), fn(string $part) => $part !== ''));

        return implode($separator, $parts);
    }

    private function isAdminUser(mixed $user): bool
    {
        $role = '';

        if (is_array($user)) {
            $role = (string) ($user['role'] ?? '');
        }

        if (is_object($user)) {
            if (method_exists($user, 'toArray')) {
                $data = $user->toArray();

                if (is_array($data)) {
                    $role = (string) ($data['role'] ?? $role);
                }
            }

            if ($role === '') {
                try {
                    $role = (string) ($user->role ?? '');
                } catch (\Throwable $th) {
                    $role = '';
                }
            }
        }

        return strtolower(trim($role)) === 'admin';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'published' => 'Publicado',
            'hidden'    => 'Oculto',
            'archived'  => 'Archivado',
            default     => 'Borrador',
        };
    }

    private function messageStatusLabel(string $status): string
    {
        return match ($status) {
            'read'        => 'Leído',
            'in_progress' => 'Seguimiento',
            'answered'    => 'Respondido',
            'archived'    => 'Archivado',
            'spam'        => 'Spam',
            default       => 'Nuevo',
        };
    }

    private function priorityLabel(string $priority): string
    {
        return match ($priority) {
            'low'    => 'Baja',
            'high'   => 'Alta',
            'urgent' => 'Urgente',
            default  => 'Normal',
        };
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'admin'   => 'Admin',
            'manager' => 'Manager',
            default   => 'Usuario',
        };
    }

    private function officeTypeLabel(string $type): string
    {
        return $type === 'workshop' ? 'Taller' : 'Oficina';
    }

    private function mediaTypeLabel(string $type): string
    {
        return $type === 'video' ? 'Video' : 'Imagen';
    }
}
