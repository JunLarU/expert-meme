<?php

namespace App\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\HomeJumbotronSlide;
use App\Models\MapMarker;
use App\Models\Message;
use App\Models\Project;
use Whis\Http\Controller;

class Dashboard extends Controller
{
    private const DASHBOARD_LIMIT = 10;

    public function index()
    {
        /*
         * Si este controlador ya está protegido con AuthMiddleware,
         * esta validación es opcional.
         *
         * Pero si la dejas, debe redirigir SOLO a invitados.
         */
        if (isGuest()) {
            return redirect('/login');
        }

        $user = auth();

        /*
         * ===============================
         * JUMBOTRON
         * ===============================
         */
        $publishedSlides = $this->rows(
            HomeJumbotronSlide::where('status', 'published', 'sort_order') ?? []
        );

        $activeSlides = $this->filterActiveByDates($publishedSlides);

        $jumbotronSlides = $this->limitRows(
            $activeSlides,
            self::DASHBOARD_LIMIT
        );

        /*
         * ===============================
         * PROYECTOS
         * ===============================
         */
        $projects = $this->rows(
            Project::all('created_at', true) ?? []
        );

        $projects = $this->withoutDeleted($projects);

        $recentProjects = $this->limitRows(
            $projects,
            self::DASHBOARD_LIMIT
        );

        /*
         * ===============================
         * CLIENTES
         * ===============================
         */
        $activeClients = $this->rows(
            Client::where('is_active', 1, 'sort_order') ?? []
        );

        $activeClients = $this->withoutDeleted($activeClients);

        /*
         * ===============================
         * MENSAJES
         * ===============================
         */
        $messages = $this->rows(
            Message::all('created_at', true) ?? []
        );

        $recentMessages = $this->limitRows(
            $messages,
            self::DASHBOARD_LIMIT
        );

        $newMessages = array_values(array_filter($messages, function (array $message) {
            return ($message['status'] ?? null) === 'new';
        }));

        /*
         * ===============================
         * MAPA
         * ===============================
         */
        $markers = $this->rows(
            MapMarker::all('sort_order') ?? []
        );

        $activeMarkers = array_values(array_filter($markers, function (array $marker) {
            return (int) ($marker['is_active'] ?? 0) === 1;
        }));

        $mapProjects = $this->countMarkersByType($activeMarkers, MapMarker::TYPE_PROJECT);
        $mapOffices = $this->countMarkersByType($activeMarkers, MapMarker::TYPE_OFFICE);
        $mapWorkshops = $this->countMarkersByType($activeMarkers, MapMarker::TYPE_WORKSHOP);

        $mapStates = $this->uniqueStates($activeMarkers);

        /*
         * ===============================
         * AUDITORÍA
         * ===============================
         */
        $auditLogs = $this->rows(
            AuditLog::all('created_at', true) ?? []
        );

        $auditLogs = $this->limitRows(
            $auditLogs,
            self::DASHBOARD_LIMIT
        );

        return view('pages/admin/dashboard', 'Dashboard', [
            'stats' => [
                'jumbotron_published' => count($publishedSlides),
                'projects_total'      => count($projects),
                'clients_active'      => count($activeClients),
                'messages_new'        => count($newMessages),
                'map_projects'        => $mapProjects,
                'map_offices'         => $mapOffices,
                'map_workshops'       => $mapWorkshops,
            ],

            'user' => $user,

            'recentProjects'  => $recentProjects,
            'recentMessages'  => $recentMessages,
            'jumbotronSlides' => $jumbotronSlides,
            'auditLogs'       => $auditLogs,
            'mapStates'       => $mapStates,
        ], 'layouts/admin/layout');
    }

    private function rows(?array $rows): array
    {
        return is_array($rows) ? array_values($rows) : [];
    }

    private function limitRows(array $rows, int $limit = self::DASHBOARD_LIMIT): array
    {
        return array_slice(array_values($rows), 0, $limit);
    }

    private function withoutDeleted(array $rows): array
    {
        return array_values(array_filter($rows, function (array $row) {
            return empty($row['deleted_at']);
        }));
    }

    private function filterActiveByDates(array $slides): array
    {
        $now = time();

        return array_values(array_filter($slides, function (array $slide) use ($now) {
            $startsAt = $slide['starts_at'] ?? null;
            $endsAt = $slide['ends_at'] ?? null;

            if (!empty($startsAt) && strtotime($startsAt) > $now) {
                return false;
            }

            if (!empty($endsAt) && strtotime($endsAt) < $now) {
                return false;
            }

            return true;
        }));
    }

    private function countMarkersByType(array $markers, string $type): int
    {
        return count(array_filter($markers, function (array $marker) use ($type) {
            return ($marker['type'] ?? null) === $type;
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
}