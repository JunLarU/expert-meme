<?php
namespace App\Controllers;

use App\Models\OfficeWorkshop;
use Whis\Http\Controller;
use Whis\Http\Response;

class OfficeWorkshops extends Controller
{
    public function index()
    {
        $items = OfficeWorkshop::publicItems();

        return $this->jsonSuccess('Oficinas y talleres encontrados.', [
            'items' => $items,
        ]);
    }

    public function show(string $id)
    {
        $item = OfficeWorkshop::findBySlugOrId($id);

        if (! $item || ! $this->isPublicOfficeWorkshop($item)) {
            if ($this->expectsJson()) {
                return $this->jsonError('Oficina o taller no encontrado.', [], 404);
            }

            return Response::text('Oficina o taller no encontrado.')->setStatus(404);
        }

        $payload = OfficeWorkshop::toPublicPayload($item);

        if ($this->expectsJson()) {
            return $this->jsonSuccess('Oficina o taller encontrado.', [
                'item' => $payload,
            ]);
        }

        return view('pages/office-workshops/entry', $payload['seo_title'] ?: $payload['title'], [
            'item' => $item,
            'officeWorkshop' => $payload,
        ], 'layouts/main');
    }

    public function map()
    {
        return json([
            'ok'      => true,
            'markers' => OfficeWorkshop::mapMarkers(),
        ]);
    }

    private function isPublicOfficeWorkshop(array $item): bool
    {
        if (! empty($item['deleted_at'])) {
            return false;
        }

        return ($item['status'] ?? '') === OfficeWorkshop::STATUS_PUBLISHED;
    }
}
