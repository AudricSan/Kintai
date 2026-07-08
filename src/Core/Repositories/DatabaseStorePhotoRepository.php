<?php

declare(strict_types=1);

namespace kintai\Core\Repositories;

use kintai\Domain\Eloquent\StorePhotoSubmission;
use kintai\Domain\Eloquent\StorePhotoImage;

final class DatabaseStorePhotoRepository implements StorePhotoRepositoryInterface
{
    public function findSubmissionById(int $id): ?array
    {
        $s = StorePhotoSubmission::find($id);
        return $s ? $s->toArray() : null;
    }

    public function findSubmissionsByStore(int $storeId): array
    {
        return StorePhotoSubmission::where('store_id', $storeId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    public function findAllSubmissions(?array $storeIds = null, int $limit = 50): array
    {
        $q = StorePhotoSubmission::whereNull('deleted_at');
        if ($storeIds !== null) {
            $q->whereIn('store_id', $storeIds);
        }
        return $q->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function findImagesBySubmission(int $submissionId): array
    {
        return StorePhotoImage::where('submission_id', $submissionId)
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }

    public function saveSubmission(array $data): array
    {
        if (!empty($data['id'])) {
            $s = StorePhotoSubmission::findOrFail($data['id']);
            $s->fill($data);
            $s->save();
        } else {
            $s = StorePhotoSubmission::create($data);
        }
        return $s->toArray();
    }

    public function saveImage(array $data): array
    {
        if (!empty($data['id'])) {
            $img = StorePhotoImage::findOrFail($data['id']);
            $img->fill($data);
            $img->save();
        } else {
            $img = StorePhotoImage::create($data);
        }
        return $img->toArray();
    }

    public function deleteSubmission(int $id): int
    {
        $s = StorePhotoSubmission::find($id);
        if ($s) {
            StorePhotoImage::where('submission_id', $id)->delete();
            return $s->delete() ? 1 : 0;
        }
        return 0;
    }

    public function deleteImage(int $id): int
    {
        $img = StorePhotoImage::find($id);
        if ($img) {
            return $img->delete() ? 1 : 0;
        }
        return 0;
    }

    public function getExpiredSubmissions(int $retentionDays): array
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
        return StorePhotoSubmission::whereNull('deleted_at')
            ->where('created_at', '<', $cutoff)
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    public function getPendingCompression(int $retentionDays): array
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
        return StorePhotoSubmission::whereNull('deleted_at')
            ->where('compressed', 0)
            ->where('created_at', '<', $cutoff)
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }
}
