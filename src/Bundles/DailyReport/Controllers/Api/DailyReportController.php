<?php

declare(strict_types=1);

namespace kintai\Bundles\DailyReport\Controllers\Api;

use kintai\Core\Api\Paginator;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\DailyReportRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

final class DailyReportController
{
    public function __construct(private readonly DailyReportRepositoryInterface $reports) {}

    /**
     * GET /api/v1/daily-reports
     * Filtres : store_id, date, status, from, to, author_id
     */
    public function index(Request $request): Response
    {
        [$page, $limit] = Paginator::params($request);
        $storeId  = $request->query('store_id');
        $date     = $request->query('date');
        $status   = $request->query('status');
        $from     = $request->query('from');
        $to       = $request->query('to');
        $authorId = $request->query('author_id');

        if ($storeId !== null && $from !== null && $to !== null) {
            $items = $this->reports->findByStoreAndDateRange((int) $storeId, $from, $to);
        } elseif ($storeId !== null && $date !== null) {
            $report = $this->reports->findByStoreAndDate((int) $storeId, $date);
            $items  = $report !== null ? [$report] : [];
        } elseif ($storeId !== null && $status !== null) {
            $items = $this->reports->findByStoreAndStatus((int) $storeId, $status);
        } elseif ($storeId !== null) {
            $items = $this->reports->findByStore((int) $storeId);
        } elseif ($authorId !== null) {
            $items = $this->reports->findByAuthor((int) $authorId);
        } else {
            $items = $this->reports->findAllActive();
        }

        return Response::json(Paginator::paginate($items, $page, $limit));
    }

    /** GET /api/v1/daily-reports/{id} */
    public function show(Request $request): Response
    {
        $report = $this->reports->findById((int) $request->param('id'));
        if ($report === null) {
            throw new NotFoundException('Rapport introuvable.');
        }
        return Response::json($report);
    }

    /** POST /api/v1/daily-reports */
    public function store(Request $request): Response
    {
        $data = array_merge($request->json() ?? [], [
            'status'     => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return Response::json($this->reports->save($data), 201);
    }

    /** PUT /api/v1/daily-reports/{id} */
    public function update(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->reports->findById($id) === null) {
            throw new NotFoundException('Rapport introuvable.');
        }
        return Response::json($this->reports->save(array_merge($request->json() ?? [], [
            'id'         => $id,
            'updated_at' => date('Y-m-d H:i:s'),
        ])));
    }

    /** DELETE /api/v1/daily-reports/{id} */
    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->reports->findById($id) === null) {
            throw new NotFoundException('Rapport introuvable.');
        }
        $this->reports->delete($id);
        return Response::empty();
    }

    /** POST /api/v1/daily-reports/{id}/submit */
    public function submit(Request $request): Response
    {
        $id     = (int) $request->param('id');
        $report = $this->reports->findById($id);
        if ($report === null) {
            throw new NotFoundException('Rapport introuvable.');
        }

        return Response::json($this->reports->save(array_merge($report, [
            'status'       => 'submitted',
            'submitted_at' => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ])));
    }

    /** POST /api/v1/daily-reports/{id}/validate */
    public function validate(Request $request): Response
    {
        $id     = (int) $request->param('id');
        $report = $this->reports->findById($id);
        if ($report === null) {
            throw new NotFoundException('Rapport introuvable.');
        }

        $authUser = $request->getAttribute('auth_user');
        $now      = date('Y-m-d H:i:s');

        return Response::json($this->reports->save(array_merge($report, [
            'status'       => 'validated',
            'validated_by' => (int) ($authUser['id'] ?? 0),
            'validated_at' => $now,
            'updated_at'   => $now,
        ])));
    }
}
