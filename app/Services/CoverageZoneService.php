<?php

namespace App\Services;

use App\Models\CoverageZone;
use App\Models\User;
use App\Repositories\CoverageZoneRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CoverageZoneService
{
    public function __construct(private CoverageZoneRepository $zones) {}

    public function create(array $data, User $creator): CoverageZone
    {
        return DB::transaction(function () use ($data, $creator): CoverageZone {
            $zone = $this->zones->create($this->attributes($data) + [
                'codigo' => $this->generateCode($data['nombre'], $data['distrito']),
                'estado' => 'ACTIVA',
                'creado_por' => $creator->getKey(),
            ]);
            $this->zones->syncPlans($zone, $data['plan_ids'] ?? []);

            return $zone;
        });
    }

    public function update(CoverageZone $zone, array $data): CoverageZone
    {
        return DB::transaction(function () use ($zone, $data): CoverageZone {
            $locked = $this->zones->lock($zone->getKey());
            $locked->fill($this->attributes($data))->save();
            $this->zones->syncPlans($locked, $data['plan_ids'] ?? []);

            return $locked;
        });
    }

    public function setActive(CoverageZone $zone, bool $active): void
    {
        DB::transaction(function () use ($zone, $active): void {
            $locked = $this->zones->lock($zone->getKey());
            $locked->estado = $active ? 'ACTIVA' : 'INACTIVA';
            $locked->save();
        });
    }

    private function attributes(array $data): array
    {
        $attributes = Arr::only($data, ['nombre', 'distrito', 'provincia', 'departamento', 'descripcion']);
        foreach (['nombre', 'distrito', 'provincia', 'departamento', 'descripcion'] as $field) {
            if (array_key_exists($field, $attributes) && is_string($attributes[$field])) {
                $attributes[$field] = trim($attributes[$field]) ?: null;
            }
        }

        return $attributes;
    }

    private function generateCode(string $name, string $district): string
    {
        $namePart = $this->codePart($name, 12, 'ZONA');
        $districtPart = $this->codePart($district, 12, 'UBICACION');
        $base = "ZON-{$namePart}-{$districtPart}";
        $code = $base;
        $sequence = 1;

        while (CoverageZone::where('codigo', $code)->exists()) {
            $suffix = '-'.str_pad((string) ++$sequence, 2, '0', STR_PAD_LEFT);
            $code = rtrim(mb_substr($base, 0, 30 - mb_strlen($suffix)), '-').$suffix;
        }

        return $code;
    }

    private function codePart(string $value, int $length, string $fallback): string
    {
        $part = mb_strtoupper(Str::slug(trim($value), '-'));

        return rtrim(mb_substr($part ?: $fallback, 0, $length), '-');
    }
}
