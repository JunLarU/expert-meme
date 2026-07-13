<?php

namespace App\Controllers\Admin;

use App\Models\AuditLog;
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
use App\Models\ValuationClient;
use App\Models\ValuationMessage;
use App\Models\ValuationUnit;
use Whis\Http\Controller;

class Dashboard extends Controller
{
    private const DASHBOARD_LIMIT = 10;

    public function index()
    {
        if (isGuest()) {
            return redirect('/login');
        }

        $user = auth();
        $isAdmin = $this->currentUserIsAdmin();

        /*
         * ===============================
         * JUMBOTRON
         * ===============================
         */
        $jumbotronAll = $this->safeRows(fn() => HomeJumbotronSlide::byPage('home'));
        $jumbotronPublished = $this->safeRows(fn() => HomeJumbotronSlide::published('home'));

        $jumbotronSlides = $this->limitRows(
            $jumbotronPublished,
            self::DASHBOARD_LIMIT
        );

        /*
         * ===============================
         * PROYECTOS
         * ===============================
         */
        $projects = $this->safeRows(fn() => Project::allProjects());
        $publishedProjects = $this->safeRows(fn() => Project::published());
        $projectsForPage = $this->safeRows(fn() => Project::forProjectsPage());
        $projectMarkers = $this->safeRows(fn() => Project::mapMarkers());

        $recentProjects = $this->limitRows(
            $this->sortByDateDesc($projects),
            self::DASHBOARD_LIMIT
        );

        /*
         * ===============================
         * DATOS RELACIONADOS A PROYECTOS
         * ===============================
         */
        $projectMedia = $this->safeRows(fn() => ProjectMedia::all('created_at', true) ?? []);
        $projectTags = $this->safeRows(fn() => ProjectTag::all('created_at', true) ?? []);
        $projectFacts = $this->safeRows(fn() => ProjectFact::all('created_at', true) ?? []);
        $projectScopeItems = $this->safeRows(fn() => ProjectScopeItem::all('created_at', true) ?? []);
        $projectResultStats = $this->safeRows(fn() => ProjectResultStat::all('created_at', true) ?? []);

        /*
         * ===============================
         * MÓDULOS SOLO ADMIN
         * ===============================
         */
        $clients = [];
        $activeClients = [];
        $featuredClients = [];

        $valuationUnits = [];
        $activeValuationUnits = [];
        $primaryValuationUnits = [];
        $valuationClients = [];
        $activeValuationClients = [];
        $featuredValuationClients = [];
        $carouselValuationClients = [];
        $pageValuationClients = [];
        $valuationMessages = [];
        $valuationMessageStats = [];
        $recentValuationMessages = [];
        $newValuationMessageAlert = null;

        $associationSlides = [];
        $activeAssociationSlides = [];
        $associationSlidesForHome = [];
        $associationSlidesForAbout = [];
        $recentAssociationSlides = [];

        $officeWorkshops = [];
        $publishedOfficeWorkshops = [];
        $officeWorkshopMarkers = [];
        $recentOfficeWorkshops = [];

        $users = [];
        $userStats = [];

        if ($isAdmin) {
            /*
             * ===============================
             * CLIENTES
             * ===============================
             */
            $clients = $this->safeRows(fn() => Client::ordered());
            $activeClients = $this->safeRows(fn() => Client::active());
            $featuredClients = $this->safeRows(fn() => Client::featured());

            /*
             * ===============================
             * VALUACIÓN
             * ===============================
             */
            $valuationUnits = $this->safeRows(fn() => ValuationUnit::ordered());
            $activeValuationUnits = array_values(array_filter($valuationUnits, fn(array $unit) => (int) ($unit['is_active'] ?? 0) === 1));
            $primaryValuationUnits = array_values(array_filter($valuationUnits, fn(array $unit) => (int) ($unit['is_primary'] ?? 0) === 1));

            $valuationClients = $this->safeRows(fn() => ValuationClient::ordered());
            $activeValuationClients = $this->safeRows(fn() => ValuationClient::active());
            $featuredValuationClients = $this->safeRows(fn() => ValuationClient::featured());
            $carouselValuationClients = $this->safeRows(fn() => ValuationClient::carousel());
            $pageValuationClients = $this->safeRows(fn() => ValuationClient::visibleInValuation());

            $valuationMessages = $this->safeRows(fn() => ValuationMessage::forAdmin());
            $valuationMessageStats = $this->safeStats(fn() => ValuationMessage::stats());
            $recentValuationMessages = $this->limitRows($valuationMessages, self::DASHBOARD_LIMIT);
            $newValuationMessages = $this->sortByDateDesc(array_values(array_filter($valuationMessages, fn(array $message) => (string) ($message['status'] ?? '') === ValuationMessage::STATUS_NEW)));
            $newValuationMessageAlert = $this->newValuationMessageAlert($newValuationMessages);

            /*
             * ===============================
             * ASOCIACIONES Y CERTIFICACIONES
             * ===============================
             */
            $associationSlides = $this->safeRows(fn() => AssociationCertificationSlide::ordered());

            $activeAssociationSlides = array_values(array_filter(
                $associationSlides,
                fn(array $slide) => (int) ($slide['is_active'] ?? 0) === 1
            ));

            $associationSlidesForHome = array_values(array_filter(
                $activeAssociationSlides,
                fn(array $slide) => (int) ($slide['show_in_home'] ?? 0) === 1
            ));

            $associationSlidesForAbout = array_values(array_filter(
                $activeAssociationSlides,
                fn(array $slide) => (int) ($slide['show_in_about'] ?? 0) === 1
            ));

            $recentAssociationSlides = $this->limitRows(
                $associationSlides,
                self::DASHBOARD_LIMIT
            );

            /*
             * ===============================
             * OFICINAS Y TALLERES
             * ===============================
             */
            $officeWorkshops = $this->safeRows(fn() => OfficeWorkshop::allItems());
            $publishedOfficeWorkshops = $this->safeRows(fn() => OfficeWorkshop::published());
            $officeWorkshopMarkers = $this->safeRows(fn() => OfficeWorkshop::mapMarkers());
            $recentOfficeWorkshops = $this->limitRows($officeWorkshops, self::DASHBOARD_LIMIT);

            /*
             * ===============================
             * USUARIOS
             * ===============================
             */
            $users = $this->safeRows(fn() => User::forAdmin());
            $userStats = $this->safeStats(fn() => User::stats());
        }

        /*
         * ===============================
         * MENSAJES
         * ===============================
         */
        $messages = $this->safeRows(fn() => Message::forAdmin());
        $messageStats = $this->safeStats(fn() => Message::stats());

        $recentMessages = $this->limitRows(
            $messages,
            self::DASHBOARD_LIMIT
        );

        $newMessages = $this->sortByDateDesc(array_values(array_filter(
            $messages,
            fn(array $message) => (string) ($message['status'] ?? '') === 'new'
        )));

        $newMessageAlert = $this->newMessageAlert($newMessages);

        /*
         * ===============================
         * MAPA
         * ===============================
         * Para managers solo se muestran pines provenientes de proyectos.
         * Para admin se suman también oficinas y talleres.
         */
        $visibleMapMarkers = $isAdmin
            ? array_merge($projectMarkers, $officeWorkshopMarkers)
            : $projectMarkers;

        $mapProjects = $this->countByValue($projectMarkers, 'type', Project::MAP_PROJECT);
        $mapOffices = $this->countByValue($projectMarkers, 'type', Project::MAP_OFFICE)
            + $this->countByValue($officeWorkshopMarkers, 'type', OfficeWorkshop::TYPE_OFFICE);
        $mapWorkshops = $this->countByValue($projectMarkers, 'type', Project::MAP_WORKSHOP)
            + $this->countByValue($officeWorkshopMarkers, 'type', OfficeWorkshop::TYPE_WORKSHOP);
        $mapStates = $this->uniqueStates($visibleMapMarkers);

        /*
         * ===============================
         * AUDITORÍA
         * ===============================
         * Solo admin puede ver movimientos internos.
         */
        $allAuditLogs = $isAdmin
            ? $this->safeRows(fn() => AuditLog::all('created_at', true) ?? [])
            : [];

        $auditLogs = $this->limitRows(
            $allAuditLogs,
            self::DASHBOARD_LIMIT
        );

        return view('pages/admin/dashboard', 'Dashboard', [
            'user'    => $user,
            'isAdmin' => $isAdmin,

            'stats' => [
                /*
                 * Jumbotron
                 */
                'jumbotron_total'     => count($jumbotronAll),
                'jumbotron_published' => $this->countByValue($jumbotronAll, 'status', HomeJumbotronSlide::STATUS_PUBLISHED),
                'jumbotron_draft'     => $this->countByValue($jumbotronAll, 'status', HomeJumbotronSlide::STATUS_DRAFT),
                'jumbotron_hidden'    => $this->countByValue($jumbotronAll, 'status', HomeJumbotronSlide::STATUS_HIDDEN),
                'jumbotron_active'    => count($jumbotronPublished),
                'jumbotron_expired'   => $this->countExpiredSlides($jumbotronAll),

                /*
                 * Proyectos
                 */
                'projects_total'         => count($projects),
                'projects_published'     => count($publishedProjects),
                'projects_draft'         => $this->countByValue($projects, 'status', Project::STATUS_DRAFT),
                'projects_hidden'        => $this->countByValue($projects, 'status', Project::STATUS_HIDDEN),
                'projects_archived'      => $this->countByValue($projects, 'status', Project::STATUS_ARCHIVED),
                'projects_in_page'       => count($projectsForPage),
                'projects_home'          => $this->countFlag($projects, 'show_in_home'),
                'projects_home_featured' => $this->countFlag($projects, 'is_home_featured'),
                'projects_featured'      => $this->countFlag($projects, 'is_featured'),
                'projects_map'           => $this->countFlag($projects, 'show_on_map'),

                /*
                 * Multimedia y datos de proyectos
                 */
                'project_media_total'   => count($projectMedia),
                'project_media_images'  => $this->countByValue($projectMedia, 'media_type', ProjectMedia::TYPE_IMAGE),
                'project_media_videos'  => $this->countByValue($projectMedia, 'media_type', ProjectMedia::TYPE_VIDEO),
                'project_tags_total'    => count($projectTags),
                'project_facts_total'   => count($projectFacts),
                'project_scope_total'   => count($projectScopeItems),
                'project_results_total' => count($projectResultStats),

                /*
                 * Clientes: solo admin
                 */
                'clients_total'    => count($clients),
                'clients_active'   => count($activeClients),
                'clients_featured' => count($featuredClients),
                'clients_inactive' => max(0, count($clients) - count($activeClients)),

                /*
                 * Valuación: solo admin
                 */
                'valuation_units_total'    => count($valuationUnits),
                'valuation_units_active'   => count($activeValuationUnits),
                'valuation_units_primary'  => count($primaryValuationUnits),
                'valuation_units_inactive' => max(0, count($valuationUnits) - count($activeValuationUnits)),
                'valuation_clients_total'    => count($valuationClients),
                'valuation_clients_active'   => count($activeValuationClients),
                'valuation_clients_featured' => count($featuredValuationClients),
                'valuation_clients_carousel' => count($carouselValuationClients),
                'valuation_clients_page'     => count($pageValuationClients),
                'valuation_clients_inactive' => max(0, count($valuationClients) - count($activeValuationClients)),
                'valuation_messages_total'       => (int) ($valuationMessageStats['total'] ?? count($valuationMessages)),
                'valuation_messages_new'         => (int) ($valuationMessageStats['new'] ?? 0),
                'valuation_messages_read'        => (int) ($valuationMessageStats['read'] ?? 0),
                'valuation_messages_in_progress' => (int) ($valuationMessageStats['in_progress'] ?? 0),
                'valuation_messages_answered'    => (int) ($valuationMessageStats['answered'] ?? 0),
                'valuation_messages_archived'    => (int) ($valuationMessageStats['archived'] ?? 0),
                'valuation_messages_spam'        => (int) ($valuationMessageStats['spam'] ?? 0),
                'valuation_messages_urgent'      => (int) ($valuationMessageStats['urgent'] ?? 0),

                /*
                 * Asociaciones y certificaciones: solo admin
                 */
                'associations_total'    => count($associationSlides),
                'associations_active'   => count($activeAssociationSlides),
                'associations_home'     => count($associationSlidesForHome),
                'associations_about'    => count($associationSlidesForAbout),
                'associations_inactive' => max(0, count($associationSlides) - count($activeAssociationSlides)),

                /*
                 * Oficinas y talleres: solo admin
                 */
                'office_workshops_total'     => count($officeWorkshops),
                'office_workshops_published' => count($publishedOfficeWorkshops),
                'office_workshops_draft'     => $this->countByValue($officeWorkshops, 'status', OfficeWorkshop::STATUS_DRAFT),
                'office_workshops_hidden'    => $this->countByValue($officeWorkshops, 'status', OfficeWorkshop::STATUS_HIDDEN),
                'office_workshops_archived'  => $this->countByValue($officeWorkshops, 'status', OfficeWorkshop::STATUS_ARCHIVED),
                'office_workshops_offices'   => $this->countByValue($officeWorkshops, 'type', OfficeWorkshop::TYPE_OFFICE),
                'office_workshops_workshops' => $this->countByValue($officeWorkshops, 'type', OfficeWorkshop::TYPE_WORKSHOP),
                'office_workshops_map'       => $this->countFlag($officeWorkshops, 'show_on_map'),

                /*
                 * Mensajes
                 */
                'messages_total'       => (int) ($messageStats['total'] ?? count($messages)),
                'messages_new'         => (int) ($messageStats['new'] ?? 0),
                'messages_read'        => (int) ($messageStats['read'] ?? 0),
                'messages_in_progress' => (int) ($messageStats['in_progress'] ?? 0),
                'messages_answered'    => (int) ($messageStats['answered'] ?? 0),
                'messages_archived'    => (int) ($messageStats['archived'] ?? 0),
                'messages_spam'        => (int) ($messageStats['spam'] ?? 0),
                'messages_urgent'      => (int) ($messageStats['urgent'] ?? 0),

                /*
                 * Mapa
                 */
                'map_total'     => count($visibleMapMarkers),
                'map_projects'  => $mapProjects,
                'map_offices'   => $isAdmin ? $mapOffices : 0,
                'map_workshops' => $isAdmin ? $mapWorkshops : 0,
                'map_states'    => count($mapStates),

                /*
                 * Usuarios: solo admin
                 */
                'users_total'   => (int) ($userStats['total'] ?? count($users)),
                'users_admin'   => (int) ($userStats['admin'] ?? 0),
                'users_manager' => (int) ($userStats['manager'] ?? 0),

                /*
                 * Auditoría
                 */
                'audit_total' => count($allAuditLogs),
            ],

            'recentProjects'       => $recentProjects,
            'recentMessages'          => $recentMessages,
            'newMessageAlert'         => $newMessageAlert,
            'recentValuationMessages' => $recentValuationMessages,
            'newValuationMessageAlert'=> $newValuationMessageAlert,
            'jumbotronSlides'         => $jumbotronSlides,
            'associationSlides'       => $recentAssociationSlides,
            'valuationUnits'          => $this->limitRows($valuationUnits, self::DASHBOARD_LIMIT),
            'valuationClients'        => $this->limitRows($valuationClients, self::DASHBOARD_LIMIT),
            'officeWorkshops'         => $recentOfficeWorkshops,
            'auditLogs'            => $auditLogs,
            'mapStates'            => $mapStates,
            'users'                => $users,
        ], 'layouts/admin/layout');
    }

    private function newMessageAlert(array $newMessages): ?array
    {
        $count = count($newMessages);

        if ($count <= 0) {
            return null;
        }

        $firstMessage = $newMessages[0] ?? [];
        $firstId      = (int) ($firstMessage['id'] ?? 0);

        if ($count === 1) {
            $name = trim((string) ($firstMessage['name'] ?? 'Contacto'));
            $subject = trim((string) ($firstMessage['subject'] ?? ''));

            if ($subject === '') {
                $subject = trim((string) ($firstMessage['service'] ?? 'Mensaje de contacto'));
            }

            return [
                'count'       => 1,
                'url'         => $firstId > 0 ? '/admin/mensajes/' . $firstId : '/admin/mensajes',
                'title'       => 'Tienes 1 mensaje nuevo',
                'description' => $this->joinNonEmpty([
                    $name !== '' ? 'De ' . $name : '',
                    $subject,
                ]),
                'cta'         => $firstId > 0 ? 'Abrir mensaje' : 'Ver mensajes',
                'is_single'   => $firstId > 0,
            ];
        }

        return [
            'count'       => $count,
            'url'         => '/admin/mensajes',
            'title'       => 'Tienes ' . $count . ' mensajes nuevos',
            'description' => 'Revisa la bandeja de mensajes para dar seguimiento a los contactos recientes.',
            'cta'         => 'Ver mensajes',
            'is_single'   => false,
        ];
    }

    private function newValuationMessageAlert(array $newMessages): ?array
    {
        $count = count($newMessages);

        if ($count <= 0) {
            return null;
        }

        $firstMessage = $newMessages[0] ?? [];
        $firstId = (int) ($firstMessage['id'] ?? 0);

        if ($count === 1) {
            $name = trim((string) ($firstMessage['name'] ?? 'Contacto'));
            $type = trim((string) ($firstMessage['valuation_type_label'] ?? 'Solicitud de valuación'));

            return [
                'count' => 1,
                'url' => $firstId > 0 ? '/admin/valuacion/mensajes/' . $firstId : '/admin/valuacion/mensajes',
                'title' => 'Tienes 1 solicitud de valuación nueva',
                'description' => $this->joinNonEmpty([$name !== '' ? 'De ' . $name : '', $type]),
                'cta' => $firstId > 0 ? 'Abrir solicitud' : 'Ver solicitudes',
                'is_single' => $firstId > 0,
            ];
        }

        return [
            'count' => $count,
            'url' => '/admin/valuacion/mensajes',
            'title' => 'Tienes ' . $count . ' solicitudes de valuación nuevas',
            'description' => 'Revisa la bandeja de valuación para dar seguimiento a los contactos recientes.',
            'cta' => 'Ver solicitudes',
            'is_single' => false,
        ];
    }

    private function joinNonEmpty(array $parts, string $separator = ' · '): string
    {
        $parts = array_values(array_filter(array_map(
            fn($part) => trim((string) $part),
            $parts
        ), fn(string $part) => $part !== ''));

        return implode($separator, $parts);
    }

    private function safeRows(callable $loader): array
    {
        try {
            return $this->rows($loader());
        } catch (\Throwable $th) {
            return [];
        }
    }

    private function safeStats(callable $loader): array
    {
        try {
            $stats = $loader();

            return is_array($stats) ? $stats : [];
        } catch (\Throwable $th) {
            return [];
        }
    }

    private function rows(mixed $rows): array
    {
        return is_array($rows) ? array_values($rows) : [];
    }

    private function limitRows(array $rows, int $limit = self::DASHBOARD_LIMIT): array
    {
        return array_slice(array_values($rows), 0, $limit);
    }

    private function sortByDateDesc(array $rows, string $field = 'created_at'): array
    {
        usort($rows, function (array $a, array $b) use ($field) {
            $aTime = strtotime((string) ($a[$field] ?? '')) ?: 0;
            $bTime = strtotime((string) ($b[$field] ?? '')) ?: 0;

            if ($aTime === $bTime) {
                return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
            }

            return $bTime <=> $aTime;
        });

        return $rows;
    }

    private function countByValue(array $rows, string $field, string $value): int
    {
        return count(array_filter($rows, function (array $row) use ($field, $value) {
            return (string) ($row[$field] ?? '') === $value;
        }));
    }

    private function countFlag(array $rows, string $field): int
    {
        return count(array_filter($rows, function (array $row) use ($field) {
            return (int) ($row[$field] ?? 0) === 1;
        }));
    }

    private function countExpiredSlides(array $slides): int
    {
        return count(array_filter($slides, function (array $slide) {
            return HomeJumbotronSlide::isExpired($slide);
        }));
    }

    private function uniqueStates(array $markers): array
    {
        $states = [];

        foreach ($markers as $marker) {
            $state = trim((string) ($marker['state'] ?? ''));

            if ($state !== '') {
                $states[$state] = $state;
            }
        }

        natcasesort($states);

        return array_values($states);
    }

    private function currentUserIsAdmin(): bool
    {
        return $this->currentUserRole() === User::ROLE_ADMIN;
    }

    private function currentUserRole(): string
    {
        return strtolower(trim((string) $this->currentUserValue('role', '')));
    }

    private function currentUserValue(string $key, mixed $default = null): mixed
    {
        $user = auth();

        if (! $user) {
            return $default;
        }

        if (is_array($user)) {
            return $user[$key] ?? $default;
        }

        if (is_object($user)) {
            if (method_exists($user, 'toArray')) {
                $data = $user->toArray();

                if (is_array($data) && array_key_exists($key, $data)) {
                    return $data[$key];
                }
            }

            try {
                $value = $user->{$key};

                return $value ?? $default;
            } catch (\Throwable $th) {
                return $default;
            }
        }

        return $default;
    }
}
