<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web;

use kintai\Core\Repositories\FeedbackRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\UI\ViewRenderer;

final class FeedbackController
{
    private const CATEGORIES = ['shift', 'schedule', 'app', 'other'];

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly FeedbackRepositoryInterface $feedbacks,
        private readonly ShiftRepositoryInterface $shifts,
        private readonly ShiftTypeRepositoryInterface $shiftTypes,
        private readonly StoreRepositoryInterface $stores,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly UserRepositoryInterface $users,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * POST /employee/feedback — soumet un feedback depuis la modale footer.
     */
    public function submit(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user') ?? [];
        $userId = (int) ($user['id'] ?? 0);

        $memberships = $this->storeUsers->findByUser($userId);
        if (empty($memberships)) {
            return Response::redirect($this->base() . '/employee?fb_error=no_store');
        }
        $storeId = (int) $memberships[0]['store_id'];

        $category  = $request->post('category', 'other');
        $message   = trim((string) $request->post('message', ''));
        $rawRating = (string) $request->post('rating', '');
        $anonymous = $request->post('anonymous', '0') === '1';
        $shiftId   = (int) $request->post('shift_id', 0);

        if (!in_array($category, self::CATEGORIES, true)) {
            $category = 'other';
        }

        $returnTo = trim((string) $request->post('return_to', ''));
        $safeBase = $this->base();
        if ($returnTo === '' || !str_starts_with($returnTo, '/') || str_contains($returnTo, '//')) {
            $returnTo = $safeBase . '/employee';
        }

        if ($message === '') {
            return Response::redirect($returnTo . '?fb_error=empty_message');
        }

        $rating = ($rawRating !== '' && ctype_digit($rawRating) && (int) $rawRating >= 1 && (int) $rawRating <= 5)
            ? (int) $rawRating
            : null;

        if ($category === 'shift') {
            if ($shiftId <= 0) {
                return Response::redirect($returnTo . '?fb_error=no_shift');
            }
            $shift = $this->shifts->findById($shiftId);
            if (!$shift || (int) ($shift['user_id'] ?? 0) !== $userId) {
                return Response::redirect($returnTo . '?fb_error=invalid_shift');
            }
            if ($this->feedbacks->findByShift($shiftId) !== null) {
                return Response::redirect($returnTo . '?fb_error=duplicate');
            }
        } else {
            $shiftId = null;
        }

        $saved = $this->feedbacks->save([
            'store_id'   => $storeId,
            'user_id'    => $anonymous ? null : $userId,
            'shift_id'   => $shiftId,
            'category'   => $category,
            'rating'     => $rating,
            'message'    => $message,
            'anonymous'  => $anonymous ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->auditLogger->log(
            $request,
            'feedback.submitted',
            'employee_feedback',
            (int) ($saved['id'] ?? 0),
            ['category' => $category, 'anonymous' => $anonymous ? 'yes' : 'no'],
            $storeId,
            $anonymous ? null : $userId
        );

        // Retourner sur la page d'origine (passée en champ caché) ou le dashboard
        $returnTo = trim((string) $request->post('return_to', ''));
        $safeBase = $this->base();
        // Valider que le chemin ne sort pas du domaine (commence par / et ne contient pas //)
        if ($returnTo === '' || !str_starts_with($returnTo, '/') || str_contains($returnTo, '//')) {
            $returnTo = $safeBase . '/employee';
        }
        return Response::redirect($returnTo . '?fb_success=sent');
    }

    /**
     * GET /employee/feedback/past-shifts — API JSON des shifts passés de l'employé.
     */
    public function pastShifts(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user') ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $today  = date('Y-m-d');

        $typeNames = [];
        foreach ($this->shiftTypes->findAll() as $st) {
            $typeNames[(int) $st['id']] = $st['name'] ?? '';
        }

        $past = [];
        foreach ($this->shifts->findByUser($userId) as $s) {
            $date = $s['shift_date'] ?? '';
            if ($date < $today) {
                $typeName = $typeNames[(int) ($s['shift_type_id'] ?? 0)] ?? '—';
                $past[]   = [
                    'id'    => (int) $s['id'],
                    'date'  => $date,
                    'label' => $date . ' — ' . $typeName
                             . ' ' . substr($s['start_time'] ?? '', 0, 5)
                             . '–' . substr($s['end_time'] ?? '', 0, 5),
                ];
            }
        }

        usort($past, fn($a, $b) => strcmp($b['date'], $a['date']));

        return Response::json(array_values($past));
    }

    /**
     * GET /admin/feedbacks — liste des feedbacks côté admin.
     */
    public function index(Request $request): Response
    {
        $managedIds = $request->getAttribute('managed_store_ids');

        if ($managedIds === null) {
            $all = $this->feedbacks->findAll();
        } else {
            $all = [];
            foreach ($managedIds as $sid) {
                foreach ($this->feedbacks->findByStore((int) $sid) as $fb) {
                    $all[] = $fb;
                }
            }
        }

        $filterStoreId = (int) ($request->query('store_id') ?? 0);
        if ($filterStoreId > 0) {
            $all = array_values(array_filter($all, fn($fb) => (int) $fb['store_id'] === $filterStoreId));
        }

        usort($all, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        $usersMap = [];
        foreach ($this->users->findAll() as $u) {
            $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            $usersMap[(int) $u['id']] = $name ?: ($u['email'] ?? '#' . $u['id']);
        }

        $storesMap = [];
        foreach ($this->stores->findAll() as $s) {
            $storesMap[(int) $s['id']] = $s['name'] ?? '';
        }

        $shiftTypesMap = [];
        foreach ($this->shiftTypes->findAll() as $st) {
            $shiftTypesMap[(int) $st['id']] = $st['name'] ?? '';
        }

        $shiftsMap = [];
        foreach ($this->shifts->findAll() as $s) {
            $shiftsMap[(int) $s['id']] = $s;
        }

        if ($managedIds === null) {
            $filterableStores = $this->stores->findAll();
        } else {
            $filterableStores = array_values(array_filter(
                $this->stores->findAll(),
                fn($s) => in_array((int) $s['id'], $managedIds, true)
            ));
        }

        return Response::html($this->view->render('admin.feedbacks', [
            'title'             => __('feedbacks'),
            'feedbacks'         => $all,
            'users_map'         => $usersMap,
            'stores_map'        => $storesMap,
            'shifts_map'        => $shiftsMap,
            'shift_types_map'   => $shiftTypesMap,
            'filterable_stores' => $filterableStores,
            'filter_store_id'   => $filterStoreId,
        ], 'layout.app'));
    }

    /**
     * POST /admin/feedbacks/{id}/delete — supprime un feedback.
     */
    public function delete(Request $request): Response
    {
        $id       = (int) $request->param('id');
        $feedback = $this->feedbacks->findById($id);

        if (!$feedback) {
            return Response::redirect($this->base() . '/admin/feedbacks?error=not_found');
        }

        $managedIds = $request->getAttribute('managed_store_ids');
        if ($managedIds !== null && !in_array((int) $feedback['store_id'], $managedIds, true)) {
            return Response::redirect($this->base() . '/admin/feedbacks?error=forbidden');
        }

        $this->feedbacks->delete($id);

        $this->auditLogger->log(
            $request,
            'feedback.deleted',
            'employee_feedback',
            $id,
            ['category' => $feedback['category'] ?? ''],
            (int) $feedback['store_id']
        );

        return Response::redirect($this->base() . '/admin/feedbacks?success=deleted');
    }

    private function base(): string
    {
        $sn   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $base = rtrim(str_replace('\\', '/', dirname($sn)), '/');
        return ($base === '.' || $base === '\\') ? '' : $base;
    }
}
