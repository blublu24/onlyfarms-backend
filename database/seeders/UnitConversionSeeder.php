<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UnitConversion;

class UnitConversionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $conversions = [
            // Kamatis (Tomato)
            ['vegetable_slug' => 'kamatis', 'unit' => 'piece', 'standard_weight_kg' => 0.125],
            
            // Sibuyas (Onion)
            ['vegetable_slug' => 'sibuyas', 'unit' => 'piece', 'standard_weight_kg' => 0.110],
            
            // Bawang (Garlic)
            ['vegetable_slug' => 'bawang', 'unit' => 'packet', 'standard_weight_kg' => 0.250],
            
            // Kalabasa (Squash)
            ['vegetable_slug' => 'kalabasa', 'unit' => 'piece', 'standard_weight_kg' => 2.500],
            
            // Ampalaya (Bitter gourd)
            ['vegetable_slug' => 'ampalaya', 'unit' => 'piece', 'standard_weight_kg' => 0.250],
            
            // Talong (Eggplant)
            ['vegetable_slug' => 'talong', 'unit' => 'piece', 'standard_weight_kg' => 0.300],
            
            // Okra
            ['vegetable_slug' => 'okra', 'unit' => 'tali', 'standard_weight_kg' => 0.250],
            
            // Sitaw (String beans)
            ['vegetable_slug' => 'sitaw', 'unit' => 'tali', 'standard_weight_kg' => 0.250],
            
            // Kangkong (Water spinach)
            ['vegetable_slug' => 'kangkong', 'unit' => 'tali', 'standard_weight_kg' => 0.300],
            
            // Pechay
            ['vegetable_slug' => 'pechay', 'unit' => 'tali', 'standard_weight_kg' => 0.250],
            
            // Repolyo (Cabbage)
            ['vegetable_slug' => 'repolyo', 'unit' => 'piece', 'standard_weight_kg' => 1.000],
            
            // Carrots
            ['vegetable_slug' => 'carrots', 'unit' => 'piece', 'standard_weight_kg' => 0.060],
            
            // Sayote
            ['vegetable_slug' => 'sayote', 'unit' => 'piece', 'standard_weight_kg' => 0.300],
            
            // Patatas (Potato)
            ['vegetable_slug' => 'patatas', 'unit' => 'piece', 'standard_weight_kg' => 0.213],
            
            // Labanos (Radish)
            ['vegetable_slug' => 'labanos', 'unit' => 'piece', 'standard_weight_kg' => 0.200],
            
            // Upo (Bottle gourd)
            ['vegetable_slug' => 'upo', 'unit' => 'piece', 'standard_weight_kg' => 1.500],
            
            // Luya (Ginger)
            ['vegetable_slug' => 'luya', 'unit' => 'packet', 'standard_weight_kg' => 0.250],
            
            // Siling Green (Green chili)
            ['vegetable_slug' => 'siling_green', 'unit' => 'packet', 'standard_weight_kg' => 0.250],
            
            // Siling Red (Red chili)
            ['vegetable_slug' => 'siling_red', 'unit' => 'packet', 'standard_weight_kg' => 0.250],
            
            // Bell pepper
            ['vegetable_slug' => 'bell_pepper', 'unit' => 'piece', 'standard_weight_kg' => 0.120],
            
            // Lettuce
            ['vegetable_slug' => 'lettuce', 'unit' => 'tali', 'standard_weight_kg' => 0.400],
            
            // Cucumber
            ['vegetable_slug' => 'cucumber', 'unit' => 'piece', 'standard_weight_kg' => 0.180],
            
            // Broccoli
            ['vegetable_slug' => 'broccoli', 'unit' => 'piece', 'standard_weight_kg' => 0.250],
            
            // Gabi (Taro)
            ['vegetable_slug' => 'gabi', 'unit' => 'piece', 'standard_weight_kg' => 0.400],
            
            // Talbos ng Kamote (Sweet potato tops)
            ['vegetable_slug' => 'talbos_ng_kamote', 'unit' => 'tali', 'standard_weight_kg' => 0.250],
        ];

        foreach ($conversions as $conversion) {
            UnitConversion::updateOrCreate(
                [
                    'vegetable_slug' => $conversion['vegetable_slug'],
                    'unit' => $conversion['unit']
                ],
                $conversion
            );
        }

        $this->command->info('Unit conversions seeded successfully!');
    }
}