<?php

namespace Database\Seeders;

use App\Models\Province;
use App\Models\Region;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all regions
        $regions = [
            'NCR' => Region::where('region_code', 'NCR')->first(),
            '01' => Region::where('region_code', '01')->first(),
            '02' => Region::where('region_code', '02')->first(),
            '03' => Region::where('region_code', '03')->first(),
            '04A' => Region::where('region_code', '04A')->first(),
            '04B' => Region::where('region_code', '04B')->first(),
            '05' => Region::where('region_code', '05')->first(),
            '06' => Region::where('region_code', '06')->first(),
            '07' => Region::where('region_code', '07')->first(),
            '08' => Region::where('region_code', '08')->first(),
            '09' => Region::where('region_code', '09')->first(),
            '10' => Region::where('region_code', '10')->first(),
            '11' => Region::where('region_code', '11')->first(),
            '12' => Region::where('region_code', '12')->first(),
            '13' => Region::where('region_code', '13')->first(),
            'CAR' => Region::where('region_code', 'CAR')->first(),
            'BARMM' => Region::where('region_code', 'BARMM')->first(),
        ];

        $provinces = [
            // NCR
            ['region_id' => $regions['NCR']->region_id ?? null, 'province_code' => 'NCR-MM', 'province_name' => 'Metro Manila'],
            
            // Region I - Ilocos
            ['region_id' => $regions['01']->region_id ?? null, 'province_code' => '01-ILN', 'province_name' => 'Ilocos Norte'],
            ['region_id' => $regions['01']->region_id ?? null, 'province_code' => '01-ILS', 'province_name' => 'Ilocos Sur'],
            ['region_id' => $regions['01']->region_id ?? null, 'province_code' => '01-LUN', 'province_name' => 'La Union'],
            ['region_id' => $regions['01']->region_id ?? null, 'province_code' => '01-PAN', 'province_name' => 'Pangasinan'],
            
            // Region II - Cagayan Valley
            ['region_id' => $regions['02']->region_id ?? null, 'province_code' => '02-BTN', 'province_name' => 'Batanes'],
            ['region_id' => $regions['02']->region_id ?? null, 'province_code' => '02-CAG', 'province_name' => 'Cagayan'],
            ['region_id' => $regions['02']->region_id ?? null, 'province_code' => '02-ISB', 'province_name' => 'Isabela'],
            ['region_id' => $regions['02']->region_id ?? null, 'province_code' => '02-NUV', 'province_name' => 'Nueva Vizcaya'],
            ['region_id' => $regions['02']->region_id ?? null, 'province_code' => '02-QUR', 'province_name' => 'Quirino'],
            
            // Region III - Central Luzon
            ['region_id' => $regions['03']->region_id ?? null, 'province_code' => '03-AUR', 'province_name' => 'Aurora'],
            ['region_id' => $regions['03']->region_id ?? null, 'province_code' => '03-BUL', 'province_name' => 'Bulacan'],
            ['region_id' => $regions['03']->region_id ?? null, 'province_code' => '03-BAN', 'province_name' => 'Bataan'],
            ['region_id' => $regions['03']->region_id ?? null, 'province_code' => '03-NUE', 'province_name' => 'Nueva Ecija'],
            ['region_id' => $regions['03']->region_id ?? null, 'province_code' => '03-PAM', 'province_name' => 'Pampanga'],
            ['region_id' => $regions['03']->region_id ?? null, 'province_code' => '03-TAR', 'province_name' => 'Tarlac'],
            ['region_id' => $regions['03']->region_id ?? null, 'province_code' => '03-ZAM', 'province_name' => 'Zambales'],
            
            // Region IV-A - CALABARZON
            ['region_id' => $regions['04A']->region_id ?? null, 'province_code' => '04A-CAV', 'province_name' => 'Cavite'],
            ['region_id' => $regions['04A']->region_id ?? null, 'province_code' => '04A-LAG', 'province_name' => 'Laguna'],
            ['region_id' => $regions['04A']->region_id ?? null, 'province_code' => '04A-BAT', 'province_name' => 'Batangas'],
            ['region_id' => $regions['04A']->region_id ?? null, 'province_code' => '04A-RIZ', 'province_name' => 'Rizal'],
            ['region_id' => $regions['04A']->region_id ?? null, 'province_code' => '04A-QUE', 'province_name' => 'Quezon'],
            
            // Region IV-B - MIMAROPA
            ['region_id' => $regions['04B']->region_id ?? null, 'province_code' => '04B-MAR', 'province_name' => 'Marinduque'],
            ['region_id' => $regions['04B']->region_id ?? null, 'province_code' => '04B-OCC', 'province_name' => 'Occidental Mindoro'],
            ['region_id' => $regions['04B']->region_id ?? null, 'province_code' => '04B-ORI', 'province_name' => 'Oriental Mindoro'],
            ['region_id' => $regions['04B']->region_id ?? null, 'province_code' => '04B-PAL', 'province_name' => 'Palawan'],
            ['region_id' => $regions['04B']->region_id ?? null, 'province_code' => '04B-ROM', 'province_name' => 'Romblon'],
            
            // Region V - Bicol
            ['region_id' => $regions['05']->region_id ?? null, 'province_code' => '05-ALB', 'province_name' => 'Albay'],
            ['region_id' => $regions['05']->region_id ?? null, 'province_code' => '05-CAM', 'province_name' => 'Camarines Norte'],
            ['region_id' => $regions['05']->region_id ?? null, 'province_code' => '05-CAS', 'province_name' => 'Camarines Sur'],
            ['region_id' => $regions['05']->region_id ?? null, 'province_code' => '05-CAT', 'province_name' => 'Catanduanes'],
            ['region_id' => $regions['05']->region_id ?? null, 'province_code' => '05-MAS', 'province_name' => 'Masbate'],
            ['region_id' => $regions['05']->region_id ?? null, 'province_code' => '05-SOR', 'province_name' => 'Sorsogon'],
            
            // Region VI - Western Visayas
            ['region_id' => $regions['06']->region_id ?? null, 'province_code' => '06-AKL', 'province_name' => 'Aklan'],
            ['region_id' => $regions['06']->region_id ?? null, 'province_code' => '06-ANT', 'province_name' => 'Antique'],
            ['region_id' => $regions['06']->region_id ?? null, 'province_code' => '06-CAP', 'province_name' => 'Capiz'],
            ['region_id' => $regions['06']->region_id ?? null, 'province_code' => '06-GUI', 'province_name' => 'Guimaras'],
            ['region_id' => $regions['06']->region_id ?? null, 'province_code' => '06-ILI', 'province_name' => 'Iloilo'],
            ['region_id' => $regions['06']->region_id ?? null, 'province_code' => '06-NEC', 'province_name' => 'Negros Occidental'],
            
            // Region VII - Central Visayas
            ['region_id' => $regions['07']->region_id ?? null, 'province_code' => '07-CEB', 'province_name' => 'Cebu'],
            ['region_id' => $regions['07']->region_id ?? null, 'province_code' => '07-BOH', 'province_name' => 'Bohol'],
            ['region_id' => $regions['07']->region_id ?? null, 'province_code' => '07-NER', 'province_name' => 'Negros Oriental'],
            ['region_id' => $regions['07']->region_id ?? null, 'province_code' => '07-SIG', 'province_name' => 'Siquijor'],
            
            // Region VIII - Eastern Visayas
            ['region_id' => $regions['08']->region_id ?? null, 'province_code' => '08-BIL', 'province_name' => 'Biliran'],
            ['region_id' => $regions['08']->region_id ?? null, 'province_code' => '08-EAS', 'province_name' => 'Eastern Samar'],
            ['region_id' => $regions['08']->region_id ?? null, 'province_code' => '08-LEY', 'province_name' => 'Leyte'],
            ['region_id' => $regions['08']->region_id ?? null, 'province_code' => '08-NOR', 'province_name' => 'Northern Samar'],
            ['region_id' => $regions['08']->region_id ?? null, 'province_code' => '08-SAM', 'province_name' => 'Samar'],
            ['region_id' => $regions['08']->region_id ?? null, 'province_code' => '08-SOU', 'province_name' => 'Southern Leyte'],
            ['region_id' => $regions['08']->region_id ?? null, 'province_code' => '08-WES', 'province_name' => 'Western Samar'],
            
            // Region IX - Zamboanga Peninsula
            ['region_id' => $regions['09']->region_id ?? null, 'province_code' => '09-ZAN', 'province_name' => 'Zamboanga del Norte'],
            ['region_id' => $regions['09']->region_id ?? null, 'province_code' => '09-ZAS', 'province_name' => 'Zamboanga del Sur'],
            ['region_id' => $regions['09']->region_id ?? null, 'province_code' => '09-ZSI', 'province_name' => 'Zamboanga Sibugay'],
            
            // Region X - Northern Mindanao
            ['region_id' => $regions['10']->region_id ?? null, 'province_code' => '10-BUK', 'province_name' => 'Bukidnon'],
            ['region_id' => $regions['10']->region_id ?? null, 'province_code' => '10-CAM', 'province_name' => 'Camiguin'],
            ['region_id' => $regions['10']->region_id ?? null, 'province_code' => '10-LAN', 'province_name' => 'Lanao del Norte'],
            ['region_id' => $regions['10']->region_id ?? null, 'province_code' => '10-MIS', 'province_name' => 'Misamis Occidental'],
            ['region_id' => $regions['10']->region_id ?? null, 'province_code' => '10-MIO', 'province_name' => 'Misamis Oriental'],
            
            // Region XI - Davao
            ['region_id' => $regions['11']->region_id ?? null, 'province_code' => '11-DAV', 'province_name' => 'Davao del Sur'],
            ['region_id' => $regions['11']->region_id ?? null, 'province_code' => '11-DAO', 'province_name' => 'Davao Oriental'],
            ['region_id' => $regions['11']->region_id ?? null, 'province_code' => '11-DAN', 'province_name' => 'Davao del Norte'],
            ['region_id' => $regions['11']->region_id ?? null, 'province_code' => '11-DAD', 'province_name' => 'Davao de Oro'],
            ['region_id' => $regions['11']->region_id ?? null, 'province_code' => '11-DAW', 'province_name' => 'Davao Occidental'],
            
            // Region XII - SOCCSKSARGEN
            ['region_id' => $regions['12']->region_id ?? null, 'province_code' => '12-COT', 'province_name' => 'Cotabato'],
            ['region_id' => $regions['12']->region_id ?? null, 'province_code' => '12-SAR', 'province_name' => 'Sarangani'],
            ['region_id' => $regions['12']->region_id ?? null, 'province_code' => '12-SOU', 'province_name' => 'South Cotabato'],
            ['region_id' => $regions['12']->region_id ?? null, 'province_code' => '12-SUL', 'province_name' => 'Sultan Kudarat'],
            
            // Region XIII - Caraga
            ['region_id' => $regions['13']->region_id ?? null, 'province_code' => '13-AGU', 'province_name' => 'Agusan del Norte'],
            ['region_id' => $regions['13']->region_id ?? null, 'province_code' => '13-AGS', 'province_name' => 'Agusan del Sur'],
            ['region_id' => $regions['13']->region_id ?? null, 'province_code' => '13-DIN', 'province_name' => 'Dinagat Islands'],
            ['region_id' => $regions['13']->region_id ?? null, 'province_code' => '13-SUR', 'province_name' => 'Surigao del Norte'],
            ['region_id' => $regions['13']->region_id ?? null, 'province_code' => '13-SUS', 'province_name' => 'Surigao del Sur'],
            
            // CAR - Cordillera Administrative Region
            ['region_id' => $regions['CAR']->region_id ?? null, 'province_code' => 'CAR-ABR', 'province_name' => 'Abra'],
            ['region_id' => $regions['CAR']->region_id ?? null, 'province_code' => 'CAR-APA', 'province_name' => 'Apayao'],
            ['region_id' => $regions['CAR']->region_id ?? null, 'province_code' => 'CAR-BEN', 'province_name' => 'Benguet'],
            ['region_id' => $regions['CAR']->region_id ?? null, 'province_code' => 'CAR-IFU', 'province_name' => 'Ifugao'],
            ['region_id' => $regions['CAR']->region_id ?? null, 'province_code' => 'CAR-KAL', 'province_name' => 'Kalinga'],
            ['region_id' => $regions['CAR']->region_id ?? null, 'province_code' => 'CAR-MOU', 'province_name' => 'Mountain Province'],
            
            // BARMM - Bangsamoro Autonomous Region
            ['region_id' => $regions['BARMM']->region_id ?? null, 'province_code' => 'BARMM-BAS', 'province_name' => 'Basilan'],
            ['region_id' => $regions['BARMM']->region_id ?? null, 'province_code' => 'BARMM-LAN', 'province_name' => 'Lanao del Sur'],
            ['region_id' => $regions['BARMM']->region_id ?? null, 'province_code' => 'BARMM-MAG', 'province_name' => 'Maguindanao'],
            ['region_id' => $regions['BARMM']->region_id ?? null, 'province_code' => 'BARMM-SUL', 'province_name' => 'Sulu'],
            ['region_id' => $regions['BARMM']->region_id ?? null, 'province_code' => 'BARMM-TAW', 'province_name' => 'Tawi-Tawi'],
        ];

        foreach ($provinces as $province) {
            if ($province['region_id']) {
                Province::firstOrCreate(
                    ['province_code' => $province['province_code']],
                    [
                        'region_id' => $province['region_id'],
                        'province_name' => $province['province_name'],
                        'is_active' => true
                    ]
                );
            }
        }
    }
}
