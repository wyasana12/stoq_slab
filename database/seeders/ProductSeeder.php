<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $realProducts = [
            'Indomie Goreng Spesial 85gr',
            'Beras Premium Maknyuss 5kg',
            'Minyak Goreng Bimoli 2L',
            'Premium Linen Spray Anti-Bacterial 250ml',
            'Kopi Bubuk Kapal Api 165gr',
            'Gula Pasir Gulaku 1kg',
            'Susu UHT Ultra Milk Coklat 1L',
            'Teh Pucuk Harum 350ml',
            'Aqua Air Mineral Botol 600ml',
            'Tepung Terigu Segitiga Biru 1kg',
            'Kecap Manis Bango 520ml',
            'Saus Sambal ABC 340ml',
            'Pewangi Pakaian Downy 720ml',
            'Deterjen Rinso Anti Noda 700gr',
            'Sari Roti Tawar Kupas',
            'Indomilk Kental Manis 370gr',
            'Sabun Mandi Cair Lifebuoy 450ml',
            'Shampoo Clear Anti Ketombe 160ml',
            'Pasta Gigi Pepsodent 190gr',
            'Tissue Wajah Tessa 250 Sheets',
            'Ethanol 96% Pelarut 1L',
            'PEG-40 Hydrogenated Castor Oil 500ml',
            'Sirup Marjan Rasa Melon 460ml',
            'Biskuit Roma Kelapa 300gr',
            'Coklat SilverQueen Almond 62gr',
            'Yakult Minuman Probiotik 5x65ml',
            'Margarine Blue Band Serbaguna 200gr',
            'Mie Sedap Kuah Soto 90gr',
            'Pembersih Lantai Super Pell 770ml',
            'Obat Nyamuk Hit Spray 600ml'
        ];

        foreach ($realProducts as $productName) {
            Product::factory()->create([
                'name' => $productName,
            ]);
        }
    }
}