<?php
namespace App\Controllers;

use App\Models\AssociationCertificationSlide;
use App\Models\Client;
use App\Models\HomeJumbotronSlide;
use App\Models\Message;
use App\Models\ValuationMessage;
use App\Models\OfficeWorkshop;
use App\Models\Project;
use App\Models\ValuationClient;
use App\Models\ValuationUnit;
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
     */
        if (! $featuredProject) {
            $latestForFeatured = Project::latestForHome(1);
            $featuredProject   = $latestForFeatured[0] ?? null;
        }

        $featuredProjectId = $featuredProject
            ? (int) ($featuredProject['id'] ?? 0)
            : null;

        $latestProjects = Project::latestForHome(3, $featuredProjectId);

        /*
     * Agregamos tags ya procesados para home.
     * Así la vista no necesita closures ni lógica compleja.
     */
        if ($featuredProject) {
            $featuredProject = $this->projectWithDisplayTags($featuredProject, 1);
        }

        $latestProjects = array_values(array_map(
            fn(array $project, int $index) => $this->projectWithDisplayTags($project, $index + 2),
            $latestProjects,
            array_keys($latestProjects)
        ));

        return view('pages/main/home', 'Inicio', [
            'jumbotronSlides'   => HomeJumbotronSlide::published('home'),
            'associationSlides' => $this->associationSlidesFor('home'),
            'clients'           => Client::active(),

            'featuredProject'   => $featuredProject,
            'latestProjects'    => $latestProjects,
        ], 'layouts/main');
    }

    private function projectWithDisplayTags(array $project, int $position = 1): array
    {
        $card = Project::toProjectCard($project, $position);

        $project['display_tags']   = $card['tags'] ?? [];
        $project['display_number'] = $card['number'] ?? str_pad((string) $position, 2, '0', STR_PAD_LEFT);

        return $project;
    }
    public function valuacion()
    {
        $valuationUnits = $this->safeValuationRows(
            fn() => ValuationUnit::active()
        );

        $primaryValuationUnit = $this->safeValuationSingle(
            fn() => ValuationUnit::primary()
        );

        if (! $primaryValuationUnit && ! empty($valuationUnits)) {
            foreach ($valuationUnits as $unit) {
                if ((int) ($unit['is_primary'] ?? 0) === 1) {
                    $primaryValuationUnit = $unit;
                    break;
                }
            }

            if (! $primaryValuationUnit) {
                $primaryValuationUnit = $valuationUnits[0] ?? null;
            }
        }

        $primaryValuationUnitId = $primaryValuationUnit
            ? (int) ($primaryValuationUnit['id'] ?? 0)
            : 0;

        $secondaryValuationUnits = array_values(array_filter(
            $valuationUnits,
            fn(array $unit) => (int) ($unit['id'] ?? 0) !== $primaryValuationUnitId
        ));

        $valuationClients = $this->safeValuationRows(
            fn() => ValuationClient::visibleInValuation()
        );

        $valuationCarouselClients = $this->safeValuationRows(
            fn() => ValuationClient::carousel()
        );

        if (empty($valuationCarouselClients)) {
            $valuationCarouselClients = $valuationClients;
        }

        $featuredValuationClients = $this->safeValuationRows(
            fn() => ValuationClient::featured()
        );

        $valuationStats = $this->valuationStats($valuationClients);

        return view('pages/main/valuacion', 'Valuación', [
            /*
         * Unidades representadas
         */
            'valuationUnits'            => $valuationUnits,
            'primaryValuationUnit'      => $primaryValuationUnit,
            'secondaryValuationUnits'   => $secondaryValuationUnits,

            /*
         * Clientes / logos
         */
            'valuationClients'          => $valuationClients,
            'valuationCarouselClients'  => $valuationCarouselClients,
            'featuredValuationClients'  => $featuredValuationClients,

            /*
         * Compatibilidad con la vista que te pasé antes.
         * Si tu HTML usa $clients, también funcionará.
         */
            'clients'                   => $valuationCarouselClients,

            /*
         * Catálogos del modelo
         */
            'valuationClientTypes'      => ValuationClient::CLIENT_TYPES,
            'valuationRepresentedUnits' => ValuationClient::REPRESENTED_UNITS,
            'valuationServiceOptions'   => ValuationClient::SERVICE_OPTIONS,

            /*
         * Estadísticas para cards o contadores
         */
            'valuationStats'            => $valuationStats,
        ], 'layouts/main');
    }
    private function safeValuationRows(callable $callback): array
    {
        try {
            $rows = $callback();

            return is_array($rows) ? array_values($rows) : [];
        } catch (\Throwable $th) {
            return [];
        }
    }

    private function safeValuationSingle(callable $callback): ?array
    {
        try {
            $row = $callback();

            return is_array($row) ? $row : null;
        } catch (\Throwable $th) {
            return null;
        }
    }

    private function valuationStats(array $clients): array
    {
        $total      = count($clients);
        $withLogo   = 0;
        $featured   = 0;
        $carousel   = 0;
        $queretaro  = 0;
        $guanajuato = 0;

        $services = [];

        foreach ($clients as $client) {
            if (trim((string) ($client['logo_url'] ?? '')) !== '') {
                $withLogo++;
            }

            if ((int) ($client['is_featured'] ?? 0) === 1) {
                $featured++;
            }

            if ((int) ($client['show_in_carousel'] ?? 0) === 1) {
                $carousel++;
            }

            if (($client['state'] ?? '') === 'Querétaro') {
                $queretaro++;
            }

            if (($client['state'] ?? '') === 'Guanajuato') {
                $guanajuato++;
            }

            $clientServices = ValuationClient::parseServices($client['valuation_services'] ?? '');

            foreach ($clientServices as $service) {
                $services[$service] = ($services[$service] ?? 0) + 1;
            }
        }

        return [
            'total'      => $total,
            'with_logo'  => $withLogo,
            'featured'   => $featured,
            'carousel'   => $carousel,
            'queretaro'  => $queretaro,
            'guanajuato' => $guanajuato,
            'services'   => $services,
        ];
    }

    public function valuationContactSend(Request $request)
    {
        $input = $request->data();

        $payload = [
            'email'   => strtolower(trim((string) ($input['email'] ?? ''))),
            'name'    => $this->cleanText($input['name'] ?? '') ?? '',
            'phone'   => $this->cleanText($input['phone'] ?? '') ?? '',
            'asunto'  => trim((string) ($input['asunto'] ?? '')),
            'message' => trim((string) ($input['message'] ?? '')),
        ];

        $errors = $this->validateValuationContactPayload($payload);

        if (! empty($errors)) {
            return Response::json([
                'ok'      => false,
                'message' => 'Revisa los campos del formulario.',
                'errors'  => $errors,
            ])->setStatus(422);
        }

        $subjectLabel = $this->valuationContactSubjectLabel($payload['asunto']);

        try {
            ValuationMessage::create([
                'name'                 => $payload['name'],
                'email'                => $payload['email'],
                'phone'                => $payload['phone'] !== '' ? $payload['phone'] : null,
                'valuation_type'       => $payload['asunto'],
                'valuation_type_label' => $subjectLabel,
                'message'              => $this->cleanMultilineText($payload['message']),
                'source_page'          => 'valuacion',
                'source_url'           => $_SERVER['HTTP_REFERER'] ?? $this->currentUrl(),
                'referrer_url'         => $_SERVER['HTTP_REFERER'] ?? null,
                'status'               => ValuationMessage::STATUS_NEW,
                'priority'             => ValuationMessage::PRIORITY_NORMAL,
                'assigned_to'          => null,
                'ip_address'           => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'           => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'read_at'              => null,
                'answered_at'          => null,
                'archived_at'          => null,
                'created_at'           => date('Y-m-d H:i:s'),
                'updated_at'           => date('Y-m-d H:i:s'),
            ]);

            return Response::json([
                'ok'      => true,
                'message' => 'Tu solicitud de valuación fue enviada correctamente.',
            ]);
        } catch (\Throwable $th) {
            return Response::json([
                'ok'      => false,
                'message' => 'No se pudo guardar tu solicitud de valuación. Inténtalo de nuevo.',
            ])->setStatus(500);
        }
    }

    private function validateValuationContactPayload(array $payload): array
    {
        $errors = [];

        $email = trim((string) ($payload['email'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));
        $phone = trim((string) ($payload['phone'] ?? ''));
        $type = trim((string) ($payload['asunto'] ?? ''));
        $message = trim((string) ($payload['message'] ?? ''));

        if ($email === '') {
            $errors['email'] = 'El correo electrónico es obligatorio.';
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Escribe un correo electrónico válido.';
        } elseif (mb_strlen($email) > 150) {
            $errors['email'] = 'El correo electrónico no debe exceder 150 caracteres.';
        }

        if ($name === '') {
            $errors['name'] = 'El nombre o empresa es obligatorio.';
        } elseif (mb_strlen($name) < 3) {
            $errors['name'] = 'El nombre o empresa debe tener al menos 3 caracteres.';
        } elseif (mb_strlen($name) > 120) {
            $errors['name'] = 'El nombre o empresa no debe exceder 120 caracteres.';
        } elseif ($this->containsEmail($name) || $this->containsLink($name)) {
            $errors['name'] = 'El nombre o empresa no debe contener correos ni enlaces.';
        }

        if ($phone !== '') {
            $digits = preg_replace('/\D+/', '', $phone) ?? '';

            if (mb_strlen($phone) > 20) {
                $errors['phone'] = 'El teléfono no debe exceder 20 caracteres.';
            } elseif (strlen($digits) < 8 || strlen($digits) > 15) {
                $errors['phone'] = 'Escribe un teléfono válido.';
            }
        }

        if ($type === '') {
            $errors['asunto'] = 'Selecciona el tipo de valuación.';
        } elseif (! array_key_exists($type, ValuationMessage::VALUATION_TYPES)) {
            $errors['asunto'] = 'Selecciona un tipo de valuación válido.';
        }

        if ($message === '') {
            $errors['message'] = 'Cuéntanos qué necesitas valuar.';
        } elseif (mb_strlen($message) < 10) {
            $errors['message'] = 'El mensaje debe tener al menos 10 caracteres.';
        } elseif (mb_strlen($message) > 3000) {
            $errors['message'] = 'El mensaje no debe exceder 3000 caracteres.';
        }

        return $errors;
    }

    private function valuationContactSubjectLabel(string $value): string
    {
        return ValuationMessage::valuationTypeLabel($value);
    }

    private function cleanMultilineText(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = preg_replace("/\r\n|\r/", "\n", $value);
        $value = preg_replace("/\n{3,}/", "\n\n", (string) $value);
        $value = preg_replace("/[^\S\n]+/", ' ', (string) $value);

        return trim((string) $value);
    }

    private function containsEmail(string $value): bool
    {
        return preg_match('/[a-z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-z0-9-]+(?:\.[a-z0-9-]+)+/i', $value) === 1;
    }

    private function containsLink(string $value): bool
    {
        return preg_match('/(?:https?:\/\/|www\.|[a-z0-9-]+\.[a-z]{2,63})/i', $value) === 1;
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

    private function associationSlidesFor(string $page): array
    {
        try {
            return $page === 'about'
                ? AssociationCertificationSlide::forAbout()
                : AssociationCertificationSlide::forHome();
        } catch (\Throwable $th) {
            return [];
        }
    }

    public function nosotros()
    {
        return view('pages/main/nosotros', 'Nosotros', [
            'associationSlides' => $this->associationSlidesFor('about'),
        ], 'layouts/main');
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
    public function searchProjectsJson(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        if ($query === '') {
            return Response::json(['ok' => true, 'query' => '', 'projects' => [], 'total' => 0]);
        }

        $limit    = max(1, min(30, (int) $request->query('limit', 10)));
        $projects = Project::search2($query, $limit);

        $cards = array_values(array_map(
            fn(array $project, int $idx) => Project::toProjectCard($project, $idx + 1),
            $projects,
            array_keys($projects)
        ));

        return Response::json([
            'ok'       => true,
            'query'    => $query,
            'projects' => $cards,
            'total'    => count($cards),
        ]);
    }
}
