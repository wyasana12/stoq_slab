<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Region extends Model
{
    use HasFactory;

    protected $table = 'region';
    protected $guarded = [
        'id'
    ];

    public $incrementing = false;
    public $keyType = 'string';

    public static function getAddress(string $id): string
    {
        return Cache::remember('Address.' . $id, 60 * 60 * 24, function () use ($id): string { // 1 day
            $data = explode('.', $id);
            $address = '';
            $id = '';
            $arrayid = [];
            $last_key = count($data) - 1;
            foreach ($data as $key => $item) {
                if ($key == $last_key) {
                    $id .= $item;
                    $arrayid[] = $id;
                } else {
                    $id .= $item . '.';
                    $arrayid[] = substr($id, 0, -1);
                }
            }

            $region = self::whereIn('id', $arrayid)->pluck('name')->toArray();
            $address = implode(', ', array_reverse($region));

            return ucwords(strtolower($address));
        });
    }

    public static function getRegionData(string $id): array
    {
        return Cache::remember('RegionData.' . $id, 60 * 60 * 24, function () use ($id): array { // 1 day
            $data = explode('.', $id);
            $id = '';
            $arrayid = [];
            $last_key = count($data) - 1;
            foreach ($data as $key => $item) {
                if ($key == $last_key) {
                    $id .= $item;
                    $arrayid[] = $id;
                } else {
                    $id .= $item . '.';
                    $arrayid[] = substr($id, 0, -1);
                }
            }

            $region = self::whereIn('id', $arrayid)->pluck('name')->toArray();
            $region = array_reverse($region);

            return [
                'desa' => $region[0] ?? null,
                'kecamatan' => $region[1] ?? null,
                'kabupaten' => $region[2] ?? null,
                'provinsi' => $region[3] ?? null,
            ];
        });
    }
}
