<?php

namespace App\Services;

use App\Models\Slider;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class SliderManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): Slider
    {
        return DB::transaction(function () use ($actor, $attributes): Slider {
            $slider = Slider::query()->create(collect($attributes)->except('banner_ids')->all());
            $this->syncBanners($slider, $this->bannerIds($attributes));
            $this->auditLogService->record($actor, 'slider.created', $slider);

            return $slider->refresh()->load('banners');
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Slider $slider, array $attributes): Slider
    {
        return DB::transaction(function () use ($actor, $slider, $attributes): Slider {
            $slider->fill(collect($attributes)->except('banner_ids')->all())->save();
            if (array_key_exists('banner_ids', $attributes)) {
                $this->syncBanners($slider, $this->bannerIds($attributes));
            }
            $this->auditLogService->record($actor, 'slider.updated', $slider);

            return $slider->load('banners');
        });
    }

    public function delete(User $actor, Slider $slider): void
    {
        DB::transaction(function () use ($actor, $slider): void {
            $this->auditLogService->record($actor, 'slider.deleted', $slider);
            $slider->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<int>
     */
    private function bannerIds(array $attributes): array
    {
        $value = $attributes['banner_ids'] ?? [];
        if (! is_array($value)) {
            throw new LogicException('Validated banner_ids must be an array.');
        }
        $ids = [];
        foreach ($value as $id) {
            if (! is_int($id)) {
                throw new LogicException('Validated banner_ids must contain integer IDs.');
            }
            $ids[] = $id;
        }

        return $ids;
    }

    /** @param list<int> $bannerIds */
    private function syncBanners(Slider $slider, array $bannerIds): void
    {
        $positions = [];
        foreach ($bannerIds as $index => $bannerId) {
            $positions[$bannerId] = ['position' => $index + 1];
        }
        // Reordering would transiently violate the unique (slider_id, position) constraint.
        $slider->banners()->detach();
        $slider->banners()->attach($positions);
    }
}
