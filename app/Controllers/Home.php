<?php
namespace App\Controllers;

use App\Models\Client;
use App\Models\HomeJumbotronSlide;
use App\Models\Message;
use App\Models\OfficeWorkshop;
use App\Models\Project;
use Whis\Http\Controller;
use Whis\Http\Request;
use Whis\Http\Response;

class Home extends Controller
{
    private const PROJECTS_PER_PAGE     = 9;
    private const PROJECTS_MAX_PER_PAGE = 90;
    public function home()
    {
        $featuredProject = Project::homeFeatured();

        /*
         * Si no hay proyecto marcado como destacado,
         * usamos el más reciente como destacado.
         *
         * Si solo existe 1 proyecto publicado en home,
         * ese será el destacado y no se repetirá abajo.
         */
        if (! $featuredProject) {
            $latestForFeatured = Project::latestForHome(1);
            $featuredProject   = $latestForFeatured[0] ?? null;
        }

        $featuredProjectId = $featuredProject
            ? (int) ($featuredProject['id'] ?? 0)
            : null;

        $latestProjects = Project::latestForHome(3, $featuredProjectId);

        return view('pages/main/home', 'Inicio', [
            'jumbotronSlides' => HomeJumbotronSlide::published('home'),
            'clients'         => Client::active(),

            'featuredProject' => $featuredProject,
            'latestProjects'  => $latestProjects,
        ], 'layouts/main');
    }

    public function contactSend(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required',
            'email'   => 'required|email',
            'message' => 'required',
            'asunto'  => 'required',
        ], true, [
            'name'    => [
                'required' => 'El nombre es obligatorio.',
            ],
            'email'   => [
                'required' => 'El correo electrónico es obligatorio.',
                'email'    => 'El correo electrónico no es válido.',
            ],
            'message' => [
                'required' => 'El mensaje es obligatorio.',
            ],
            'asunto'  => [
                'required' => 'El asunto es obligatorio.',
            ],
        ]);

        $input = $request->data();

        $subjectValue = trim((string) ($input['asunto'] ?? ''));
        $subjectLabel = $this->contactSubjectLabel($subjectValue);

        try {
            Message::create([
                'name'             => $this->cleanText($input['name'] ?? ''),
                'company'          => $this->cleanText($input['company'] ?? ''),
                'email'            => $this->cleanText($input['email'] ?? ''),
                'phone'            => $this->cleanText($input['phone'] ?? ''),
                'service'          => $subjectValue,
                'subject'          => $subjectLabel,
                'project_location' => $this->cleanText($input['project_location'] ?? ''),
                'message'          => $this->cleanText($input['message'] ?? ''),
                'source_page'      => 'contacto',
                'source_url'       => $this->currentUrl(),
                'referrer_url'     => $_SERVER['HTTP_REFERER'] ?? null,
                'status'           => Message::STATUS_NEW,
                'priority'         => Message::PRIORITY_NORMAL,
                'assigned_to'      => null,
                'ip_address'       => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'       => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'read_at'          => null,
                'answered_at'      => null,
                'archived_at'      => null,
                'created_at'       => date('Y-m-d H:i:s'),
                'updated_at'       => date('Y-m-d H:i:s'),
            ]);

            return Response::json([
                'ok'      => true,
                'message' => 'Tu mensaje fue enviado correctamente.',
            ]);
        } catch (\Throwable $th) {
            return Response::json([
                'ok'      => false,
                'message' => 'No se pudo guardar tu mensaje. Inténtalo de nuevo.',
            ])->setStatus(500);
        }
    }

    private function contactSubjectLabel(string $value): string
    {
        return match ($value) {
            'proyectos'           => 'Proyectos',
            'construccion'        => 'Construcción',
            'valuacion'           => 'Valuación',
            'diseno-estructural'  => 'Diseño estructural',
            'supervision-tecnica' => 'Supervisión técnica',
            default               => $value !== '' ? ucfirst(str_replace('-', ' ', $value)) : 'Mensaje de contacto',
        };
    }

    private function cleanText(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = preg_replace('/\s+/', ' ', $value);

        return $value !== null ? trim($value) : null;
    }

    private function currentUrl(): string
    {
        $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            ? 'https'
            : 'http';

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri  = $_SERVER['REQUEST_URI'] ?? '';

        return $host !== '' ? "{$scheme}://{$host}{$uri}" : $uri;
    }

    public function nosotros()
    {
        return view('pages/main/nosotros', 'Nosotros');
    }

    public function servicios()
    {
        return view('pages/main/servicios', 'Servicios');
    }

    public function proyectos()
    {
        $limit = self::PROJECTS_PER_PAGE;

        $projects = Project::forProjectsPagePaginated($limit, 0);
        $total    = Project::countForProjectsPage();

        $projectCards = array_values(array_map(
            fn(array $project, int $index) => Project::toProjectCard($project, $index + 1),
            $projects,
            array_keys($projects)
        ));

        return view('pages/main/proyectos', 'Proyectos', [
            'initialProjects' => $projectCards,
            'projectsTotal'   => $total,
            'projectsPerPage' => $limit,
            'projectsHasMore' => $total > count($projectCards),
        ], 'layouts/main');
    }

    public function projectsJson()
    {
        $input = $this->getInputData();

        $limit = (int) ($input['limit'] ?? self::PROJECTS_PER_PAGE);
        $limit = max(1, min(self::PROJECTS_MAX_PER_PAGE, $limit));

        $offset = (int) ($input['offset'] ?? 0);
        $offset = max(0, $offset);

        $projects = Project::forProjectsPagePaginated($limit, $offset);
        $total    = Project::countForProjectsPage();

        $projectCards = array_values(array_map(
            fn(array $project, int $index) => Project::toProjectCard($project, $offset + $index + 1),
            $projects,
            array_keys($projects)
        ));

        $nextOffset = $offset + count($projectCards);

        return Response::json([
            'ok'          => true,
            'message'     => 'Proyectos cargados correctamente.',
            'projects'    => $projectCards,
            'limit'       => $limit,
            'offset'      => $offset,
            'next_offset' => $nextOffset,
            'total'       => $total,
            'has_more'    => $nextOffset < $total,
        ]);
    }

    public function projectsMapJson()
    {
        return Response::json([
            'ok'      => true,
            'message' => 'Pines de proyectos cargados correctamente.',
            'markers' => Project::mapMarkers(),
        ]);
    }

    public function officeWorkshopsMapJson()
    {
        return Response::json([
            'ok'      => true,
            'message' => 'Pines de oficinas y talleres cargados correctamente.',
            'markers' => OfficeWorkshop::mapMarkers(),
        ]);
    }

    public function contacto()
    {
        return view('pages/main/contacto', 'Contacto', [
            'officeWorkshops' => $this->contactOfficeWorkshops(),
        ]);
    }

    private function contactOfficeWorkshops(): array
    {
        try {
            if (method_exists(OfficeWorkshop::class, 'publicItems')) {
                $items = OfficeWorkshop::publicItems();
            } else {
                $items = OfficeWorkshop::published();
            }
        } catch (\Throwable $th) {
            return [];
        }

        return array_values(array_filter($items, function (array $item): bool {
            if (! empty($item['deleted_at'])) {
                return false;
            }

            return ($item['status'] ?? '') === OfficeWorkshop::STATUS_PUBLISHED;
        }));
    }

    public function store()
    {
        return view('pages/main/form', 'Formulario');
    }
}
