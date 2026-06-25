<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Province;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all provinces
        $provinces = [];
        $provinceCodes = [
            'NCR-MM', '01-ILN', '01-ILS', '01-LUN', '01-PAN',
            '02-BTN', '02-CAG', '02-ISB', '02-NUV', '02-QUR',
            '03-AUR', '03-BUL', '03-BAN', '03-NUE', '03-PAM', '03-TAR', '03-ZAM',
            '04A-CAV', '04A-LAG', '04A-BAT', '04A-RIZ', '04A-QUE',
            '04B-MAR', '04B-OCC', '04B-ORI', '04B-PAL', '04B-ROM',
            '05-ALB', '05-CAM', '05-CAS', '05-CAT', '05-MAS', '05-SOR',
            '06-AKL', '06-ANT', '06-CAP', '06-GUI', '06-ILI', '06-NEC',
            '07-CEB', '07-BOH', '07-NER', '07-SIG',
            '08-BIL', '08-EAS', '08-LEY', '08-NOR', '08-SAM', '08-SOU', '08-WES',
            '09-ZAN', '09-ZAS', '09-ZSI',
            '10-BUK', '10-CAM', '10-LAN', '10-MIS', '10-MIO',
            '11-DAV', '11-DAO', '11-DAN', '11-DAD', '11-DAW',
            '12-COT', '12-SAR', '12-SOU', '12-SUL',
            '13-AGU', '13-AGS', '13-DIN', '13-SUR', '13-SUS',
            'CAR-ABR', 'CAR-APA', 'CAR-BEN', 'CAR-IFU', 'CAR-KAL', 'CAR-MOU',
            'BARMM-BAS', 'BARMM-LAN', 'BARMM-MAG', 'BARMM-SUL', 'BARMM-TAW',
        ];
        
        foreach ($provinceCodes as $code) {
            $provinces[$code] = Province::where('province_code', $code)->first();
        }

        $cities = [
            // Metro Manila (NCR-MM) - All 17 cities/municipalities
            ['province_id' => $provinces['NCR-MM']->province_id ?? null, 'city_code' => 'NCR-MNL', 'city_name' => 'Manila', 'type' => 'city', 'postal_code' => '1000'],
            ['province_id' => $provinces['NCR-MM']->province_id ?? null, 'city_code' => 'NCR-QCZ', 'city_name' => 'Quezon City', 'type' => 'city', 'postal_code' => '1100'],
            ['province_id' => $provinces['NCR-MM']->province_id ?? null, 'city_code' => 'NCR-MKT', 'city_name' => 'Makati', 'type' => 'city', 'postal_code' => '1200'],
            ['province_id' => $provinces['NCR-MM']->province_id ?? null, 'city_code' => 'NCR-MND', 'city_name' => 'Mandaluyong', 'type' => 'city', 'postal_code' => '1550'],
            ['province_id' => $provinces['NCR-MM']->province_id ?? null, 'city_code' => 'NCR-PAS', 'city_name' => 'Pasig', 'type' => 'city', 'postal_code' => '1600'],
            ['province_id' => $provinces['NCR-MM']->province_id ?? null, 'city_code' => 'NCR-TAG', 'city_name' => 'Taguig', 'type' => 'city', 'postal_code' => '1630'],
            ['province_id' => $provinces['NCR-MM']->province_id ?? null, 'city_code' => 'NCR-PSY', 'city_name' => 'Pasay', 'type' => 'city', 'postal_code' => '1300'],
            ['province_id' => $provinces['NCR-MM']->province_id ?? null, 'city_code' => 'NCR-MAL', 'city_name' => 'Malabon', 'type' => 'city', 'postal_code' => '1470'],
            ['province_id' => $provinces['NCR-MM']->province_id ?? null, 'city_code' => 'NCR-VAL', 'city_name' => 'Valenzuela', 'type' => 'city', 'postal_code' => '1440'],
            ['province_id' => $provinces['NCR-MM']->province_id ?? null, 'city_code' => 'NCR-MAR', 'city_name' => 'Marikina', 'type' => 'city', 'postal_code' => '1800'],
            ['province_id' => $provinces['NCR-MM']->province_id ?? null, 'city_code' => 'NCR-PAR', 'city_name' => 'Parañaque', 'type' => 'city', 'postal_code' => '1700'],
            ['province_id' => $provinces['NCR-MM']->province_id ?? null, 'city_code' => 'NCR-LAS', 'city_name' => 'Las Piñas', 'type' => 'city', 'postal_code' => '1740'],
            ['province_id' => $provinces['NCR-MM']->province_id ?? null, 'city_code' => 'NCR-MUN', 'city_name' => 'Muntinlupa', 'type' => 'city', 'postal_code' => '1770'],
            ['province_id' => $provinces['NCR-MM']->province_id ?? null, 'city_code' => 'NCR-NAV', 'city_name' => 'Navotas', 'type' => 'city', 'postal_code' => '1485'],
            ['province_id' => $provinces['NCR-MM']->province_id ?? null, 'city_code' => 'NCR-SJDM', 'city_name' => 'San Juan', 'type' => 'city', 'postal_code' => '1500'],
            ['province_id' => $provinces['NCR-MM']->province_id ?? null, 'city_code' => 'NCR-CAL', 'city_name' => 'Caloocan', 'type' => 'city', 'postal_code' => '1400'],
            ['province_id' => $provinces['NCR-MM']->province_id ?? null, 'city_code' => 'NCR-PAT', 'city_name' => 'Pateros', 'type' => 'municipality', 'postal_code' => '1620'],
            
            // Ilocos Norte (01-ILN)
            ['province_id' => $provinces['01-ILN']->province_id ?? null, 'city_code' => '01-ILN-LAO', 'city_name' => 'Laoag', 'type' => 'city', 'postal_code' => '2900'],
            ['province_id' => $provinces['01-ILN']->province_id ?? null, 'city_code' => '01-ILN-BAT', 'city_name' => 'Batac', 'type' => 'city', 'postal_code' => '2906'],
            ['province_id' => $provinces['01-ILN']->province_id ?? null, 'city_code' => '01-ILN-PAG', 'city_name' => 'Pagudpud', 'type' => 'municipality', 'postal_code' => '2919'],
            ['province_id' => $provinces['01-ILN']->province_id ?? null, 'city_code' => '01-ILN-PAO', 'city_name' => 'Paoay', 'type' => 'municipality', 'postal_code' => '2902'],
            ['province_id' => $provinces['01-ILN']->province_id ?? null, 'city_code' => '01-ILN-SAR', 'city_name' => 'Sarrat', 'type' => 'municipality', 'postal_code' => '2914'],
            
            // Ilocos Sur (01-ILS)
            ['province_id' => $provinces['01-ILS']->province_id ?? null, 'city_code' => '01-ILS-VIG', 'city_name' => 'Vigan', 'type' => 'city', 'postal_code' => '2700'],
            ['province_id' => $provinces['01-ILS']->province_id ?? null, 'city_code' => '01-ILS-CAN', 'city_name' => 'Candon', 'type' => 'city', 'postal_code' => '2710'],
            ['province_id' => $provinces['01-ILS']->province_id ?? null, 'city_code' => '01-ILS-NAG', 'city_name' => 'Narvacan', 'type' => 'municipality', 'postal_code' => '2704'],
            ['province_id' => $provinces['01-ILS']->province_id ?? null, 'city_code' => '01-ILS-SAN', 'city_name' => 'Santa', 'type' => 'municipality', 'postal_code' => '2703'],
            
            // La Union (01-LUN)
            ['province_id' => $provinces['01-LUN']->province_id ?? null, 'city_code' => '01-LUN-SFM', 'city_name' => 'San Fernando', 'type' => 'city', 'postal_code' => '2500'],
            ['province_id' => $provinces['01-LUN']->province_id ?? null, 'city_code' => '01-LUN-AGO', 'city_name' => 'Agoo', 'type' => 'municipality', 'postal_code' => '2504'],
            ['province_id' => $provinces['01-LUN']->province_id ?? null, 'city_code' => '01-LUN-BAU', 'city_name' => 'Bauang', 'type' => 'municipality', 'postal_code' => '2501'],
            
            // Pangasinan (01-PAN)
            ['province_id' => $provinces['01-PAN']->province_id ?? null, 'city_code' => '01-PAN-DAG', 'city_name' => 'Dagupan', 'type' => 'city', 'postal_code' => '2400'],
            ['province_id' => $provinces['01-PAN']->province_id ?? null, 'city_code' => '01-PAN-URD', 'city_name' => 'Urdaneta', 'type' => 'city', 'postal_code' => '2428'],
            ['province_id' => $provinces['01-PAN']->province_id ?? null, 'city_code' => '01-PAN-ALM', 'city_name' => 'Alaminos', 'type' => 'city', 'postal_code' => '2404'],
            ['province_id' => $provinces['01-PAN']->province_id ?? null, 'city_code' => '01-PAN-SAN', 'city_name' => 'San Carlos', 'type' => 'city', 'postal_code' => '2420'],
            ['province_id' => $provinces['01-PAN']->province_id ?? null, 'city_code' => '01-PAN-MAL', 'city_name' => 'Malasiqui', 'type' => 'municipality', 'postal_code' => '2421'],
            ['province_id' => $provinces['01-PAN']->province_id ?? null, 'city_code' => '01-PAN-BAY', 'city_name' => 'Bayambang', 'type' => 'municipality', 'postal_code' => '2423'],
            ['province_id' => $provinces['01-PAN']->province_id ?? null, 'city_code' => '01-PAN-MAN', 'city_name' => 'Mangatarem', 'type' => 'municipality', 'postal_code' => '2413'],
            ['province_id' => $provinces['01-PAN']->province_id ?? null, 'city_code' => '01-PAN-LIN', 'city_name' => 'Lingayen', 'type' => 'municipality', 'postal_code' => '2401'],
            ['province_id' => $provinces['01-PAN']->province_id ?? null, 'city_code' => '01-PAN-BIN', 'city_name' => 'Binmaley', 'type' => 'municipality', 'postal_code' => '2417'],
            ['province_id' => $provinces['01-PAN']->province_id ?? null, 'city_code' => '01-PAN-CAL', 'city_name' => 'Calasiao', 'type' => 'municipality', 'postal_code' => '2418'],
            ['province_id' => $provinces['01-PAN']->province_id ?? null, 'city_code' => '01-PAN-MAO', 'city_name' => 'Manaoag', 'type' => 'municipality', 'postal_code' => '2430'],
            ['province_id' => $provinces['01-PAN']->province_id ?? null, 'city_code' => '01-PAN-STA', 'city_name' => 'Santa Barbara', 'type' => 'municipality', 'postal_code' => '2419'],
            ['province_id' => $provinces['01-PAN']->province_id ?? null, 'city_code' => '01-PAN-ASIN', 'city_name' => 'Asingan', 'type' => 'municipality', 'postal_code' => '2439'],
            ['province_id' => $provinces['01-PAN']->province_id ?? null, 'city_code' => '01-PAN-ROS', 'city_name' => 'Rosales', 'type' => 'municipality', 'postal_code' => '2441'],
            ['province_id' => $provinces['01-PAN']->province_id ?? null, 'city_code' => '01-PAN-TAY', 'city_name' => 'Tayug', 'type' => 'municipality', 'postal_code' => '2445'],
            ['province_id' => $provinces['01-PAN']->province_id ?? null, 'city_code' => '01-PAN-VIL', 'city_name' => 'Villasis', 'type' => 'municipality', 'postal_code' => '2427'],
            
            // Batanes (02-BTN)
            ['province_id' => $provinces['02-BTN']->province_id ?? null, 'city_code' => '02-BTN-BAS', 'city_name' => 'Basco', 'type' => 'municipality', 'postal_code' => '3900'],
            ['province_id' => $provinces['02-BTN']->province_id ?? null, 'city_code' => '02-BTN-ITB', 'city_name' => 'Itbayat', 'type' => 'municipality', 'postal_code' => '3905'],
            
            // Cagayan (02-CAG)
            ['province_id' => $provinces['02-CAG']->province_id ?? null, 'city_code' => '02-CAG-TUG', 'city_name' => 'Tuguegarao', 'type' => 'city', 'postal_code' => '3500'],
            ['province_id' => $provinces['02-CAG']->province_id ?? null, 'city_code' => '02-CAG-APP', 'city_name' => 'Aparri', 'type' => 'municipality', 'postal_code' => '3515'],
            ['province_id' => $provinces['02-CAG']->province_id ?? null, 'city_code' => '02-CAG-SAN', 'city_name' => 'Sanchez-Mira', 'type' => 'municipality', 'postal_code' => '3518'],
            
            // Isabela (02-ISB)
            ['province_id' => $provinces['02-ISB']->province_id ?? null, 'city_code' => '02-ISB-ILG', 'city_name' => 'Ilagan', 'type' => 'city', 'postal_code' => '3300'],
            ['province_id' => $provinces['02-ISB']->province_id ?? null, 'city_code' => '02-ISB-SAN', 'city_name' => 'Santiago', 'type' => 'city', 'postal_code' => '3311'],
            ['province_id' => $provinces['02-ISB']->province_id ?? null, 'city_code' => '02-ISB-CAU', 'city_name' => 'Cauayan', 'type' => 'city', 'postal_code' => '3305'],
            ['province_id' => $provinces['02-ISB']->province_id ?? null, 'city_code' => '02-ISB-ECH', 'city_name' => 'Echague', 'type' => 'municipality', 'postal_code' => '3309'],
            
            // Nueva Vizcaya (02-NUV)
            ['province_id' => $provinces['02-NUV']->province_id ?? null, 'city_code' => '02-NUV-BAY', 'city_name' => 'Bayombong', 'type' => 'municipality', 'postal_code' => '3700'],
            ['province_id' => $provinces['02-NUV']->province_id ?? null, 'city_code' => '02-NUV-SOL', 'city_name' => 'Solano', 'type' => 'municipality', 'postal_code' => '3709'],
            
            // Quirino (02-QUR)
            ['province_id' => $provinces['02-QUR']->province_id ?? null, 'city_code' => '02-QUR-CAB', 'city_name' => 'Cabarroguis', 'type' => 'municipality', 'postal_code' => '3400'],
            ['province_id' => $provinces['02-QUR']->province_id ?? null, 'city_code' => '02-QUR-DIF', 'city_name' => 'Diffun', 'type' => 'municipality', 'postal_code' => '3401'],
            
            // Bulacan (03-BUL)
            ['province_id' => $provinces['03-BUL']->province_id ?? null, 'city_code' => '03-BUL-MAL', 'city_name' => 'Malolos', 'type' => 'city', 'postal_code' => '3000'],
            ['province_id' => $provinces['03-BUL']->province_id ?? null, 'city_code' => '03-BUL-MEY', 'city_name' => 'Meycauayan', 'type' => 'city', 'postal_code' => '3020'],
            ['province_id' => $provinces['03-BUL']->province_id ?? null, 'city_code' => '03-BUL-SJO', 'city_name' => 'San Jose del Monte', 'type' => 'city', 'postal_code' => '3023'],
            ['province_id' => $provinces['03-BUL']->province_id ?? null, 'city_code' => '03-BUL-MAR', 'city_name' => 'Marilao', 'type' => 'municipality', 'postal_code' => '3019'],
            ['province_id' => $provinces['03-BUL']->province_id ?? null, 'city_code' => '03-BUL-BOC', 'city_name' => 'Bocaue', 'type' => 'municipality', 'postal_code' => '3018'],
            ['province_id' => $provinces['03-BUL']->province_id ?? null, 'city_code' => '03-BUL-BAL', 'city_name' => 'Baliuag', 'type' => 'city', 'postal_code' => '3006'],
            
            // Nueva Ecija (03-NUE)
            ['province_id' => $provinces['03-NUE']->province_id ?? null, 'city_code' => '03-NUE-PAL', 'city_name' => 'Palayan', 'type' => 'city', 'postal_code' => '3132'],
            ['province_id' => $provinces['03-NUE']->province_id ?? null, 'city_code' => '03-NUE-CAB', 'city_name' => 'Cabanatuan', 'type' => 'city', 'postal_code' => '3100'],
            ['province_id' => $provinces['03-NUE']->province_id ?? null, 'city_code' => '03-NUE-SAN', 'city_name' => 'San Jose', 'type' => 'city', 'postal_code' => '3121'],
            
            // Pampanga (03-PAM)
            ['province_id' => $provinces['03-PAM']->province_id ?? null, 'city_code' => '03-PAM-ANG', 'city_name' => 'Angeles', 'type' => 'city', 'postal_code' => '2009'],
            ['province_id' => $provinces['03-PAM']->province_id ?? null, 'city_code' => '03-PAM-SFM', 'city_name' => 'San Fernando', 'type' => 'city', 'postal_code' => '2000'],
            ['province_id' => $provinces['03-PAM']->province_id ?? null, 'city_code' => '03-PAM-MAB', 'city_name' => 'Mabalacat', 'type' => 'city', 'postal_code' => '2010'],
            
            // Tarlac (03-TAR)
            ['province_id' => $provinces['03-TAR']->province_id ?? null, 'city_code' => '03-TAR-TAR', 'city_name' => 'Tarlac City', 'type' => 'city', 'postal_code' => '2300'],
            
            // Zambales (03-ZAM)
            ['province_id' => $provinces['03-ZAM']->province_id ?? null, 'city_code' => '03-ZAM-OLO', 'city_name' => 'Olongapo', 'type' => 'city', 'postal_code' => '2200'],
            ['province_id' => $provinces['03-ZAM']->province_id ?? null, 'city_code' => '03-ZAM-IB', 'city_name' => 'Iba', 'type' => 'municipality', 'postal_code' => '2201'],
            ['province_id' => $provinces['03-ZAM']->province_id ?? null, 'city_code' => '03-ZAM-SUB', 'city_name' => 'Subic', 'type' => 'municipality', 'postal_code' => '2209'],
            ['province_id' => $provinces['03-ZAM']->province_id ?? null, 'city_code' => '03-ZAM-CAS', 'city_name' => 'Castillejos', 'type' => 'municipality', 'postal_code' => '2208'],
            
            // Cavite (04A-CAV)
            ['province_id' => $provinces['04A-CAV']->province_id ?? null, 'city_code' => '04A-CAV-BAC', 'city_name' => 'Bacoor', 'type' => 'city', 'postal_code' => '4102'],
            ['province_id' => $provinces['04A-CAV']->province_id ?? null, 'city_code' => '04A-CAV-IMU', 'city_name' => 'Imus', 'type' => 'city', 'postal_code' => '4103'],
            ['province_id' => $provinces['04A-CAV']->province_id ?? null, 'city_code' => '04A-CAV-DAS', 'city_name' => 'Dasmariñas', 'type' => 'city', 'postal_code' => '4114'],
            ['province_id' => $provinces['04A-CAV']->province_id ?? null, 'city_code' => '04A-CAV-GEN', 'city_name' => 'General Trias', 'type' => 'city', 'postal_code' => '4107'],
            ['province_id' => $provinces['04A-CAV']->province_id ?? null, 'city_code' => '04A-CAV-TAG', 'city_name' => 'Tagaytay', 'type' => 'city', 'postal_code' => '4120'],
            ['province_id' => $provinces['04A-CAV']->province_id ?? null, 'city_code' => '04A-CAV-TRE', 'city_name' => 'Trece Martires', 'type' => 'city', 'postal_code' => '4109'],
            ['province_id' => $provinces['04A-CAV']->province_id ?? null, 'city_code' => '04A-CAV-CAV', 'city_name' => 'Cavite City', 'type' => 'city', 'postal_code' => '4100'],
            ['province_id' => $provinces['04A-CAV']->province_id ?? null, 'city_code' => '04A-CAV-KAW', 'city_name' => 'Kawit', 'type' => 'municipality', 'postal_code' => '4104'],
            ['province_id' => $provinces['04A-CAV']->province_id ?? null, 'city_code' => '04A-CAV-ROS', 'city_name' => 'Rosario', 'type' => 'municipality', 'postal_code' => '4106'],
            ['province_id' => $provinces['04A-CAV']->province_id ?? null, 'city_code' => '04A-CAV-NOV', 'city_name' => 'Noveleta', 'type' => 'municipality', 'postal_code' => '4105'],
            ['province_id' => $provinces['04A-CAV']->province_id ?? null, 'city_code' => '04A-CAV-SIL', 'city_name' => 'Silang', 'type' => 'municipality', 'postal_code' => '4118'],
            ['province_id' => $provinces['04A-CAV']->province_id ?? null, 'city_code' => '04A-CAV-AMD', 'city_name' => 'Amadeo', 'type' => 'municipality', 'postal_code' => '4119'],
            ['province_id' => $provinces['04A-CAV']->province_id ?? null, 'city_code' => '04A-CAV-IND', 'city_name' => 'Indang', 'type' => 'municipality', 'postal_code' => '4122'],
            ['province_id' => $provinces['04A-CAV']->province_id ?? null, 'city_code' => '04A-CAV-ALF', 'city_name' => 'Alfonso', 'type' => 'municipality', 'postal_code' => '4123'],
            ['province_id' => $provinces['04A-CAV']->province_id ?? null, 'city_code' => '04A-CAV-MAG', 'city_name' => 'Magallanes', 'type' => 'municipality', 'postal_code' => '4124'],
            ['province_id' => $provinces['04A-CAV']->province_id ?? null, 'city_code' => '04A-CAV-GEA', 'city_name' => 'General Emilio Aguinaldo', 'type' => 'municipality', 'postal_code' => '4125'],
            ['province_id' => $provinces['04A-CAV']->province_id ?? null, 'city_code' => '04A-CAV-MAR', 'city_name' => 'Maragondon', 'type' => 'municipality', 'postal_code' => '4112'],
            ['province_id' => $provinces['04A-CAV']->province_id ?? null, 'city_code' => '04A-CAV-NAI', 'city_name' => 'Naic', 'type' => 'municipality', 'postal_code' => '4110'],
            ['province_id' => $provinces['04A-CAV']->province_id ?? null, 'city_code' => '04A-CAV-TAN', 'city_name' => 'Tanza', 'type' => 'municipality', 'postal_code' => '4108'],
            ['province_id' => $provinces['04A-CAV']->province_id ?? null, 'city_code' => '04A-CAV-CAR', 'city_name' => 'Carmona', 'type' => 'municipality', 'postal_code' => '4116'],
            ['province_id' => $provinces['04A-CAV']->province_id ?? null, 'city_code' => '04A-CAV-GMA', 'city_name' => 'General Mariano Alvarez', 'type' => 'municipality', 'postal_code' => '4117'],
            
            // Laguna (04A-LAG)
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-CAL', 'city_name' => 'Calamba', 'type' => 'city', 'postal_code' => '4027'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-SPE', 'city_name' => 'San Pedro', 'type' => 'city', 'postal_code' => '4023'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-BIN', 'city_name' => 'Biñan', 'type' => 'city', 'postal_code' => '4024'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-SRO', 'city_name' => 'Santa Rosa', 'type' => 'city', 'postal_code' => '4026'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-LOS', 'city_name' => 'Los Baños', 'type' => 'municipality', 'postal_code' => '4030'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-CAB', 'city_name' => 'Cabuyao', 'type' => 'city', 'postal_code' => '4025'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-SAN', 'city_name' => 'San Pablo', 'type' => 'city', 'postal_code' => '4000'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-STA', 'city_name' => 'Santa Cruz', 'type' => 'municipality', 'postal_code' => '4009'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-PIL', 'city_name' => 'Pila', 'type' => 'municipality', 'postal_code' => '4010'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-VIC', 'city_name' => 'Victoria', 'type' => 'municipality', 'postal_code' => '4011'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-NAG', 'city_name' => 'Nagcarlan', 'type' => 'municipality', 'postal_code' => '4002'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-LIL', 'city_name' => 'Liliw', 'type' => 'municipality', 'postal_code' => '4004'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-MAJ', 'city_name' => 'Majayjay', 'type' => 'municipality', 'postal_code' => '4005'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-LUI', 'city_name' => 'Luisiana', 'type' => 'municipality', 'postal_code' => '4034'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-CAV', 'city_name' => 'Cavinti', 'type' => 'municipality', 'postal_code' => '4013'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-PAK', 'city_name' => 'Pakil', 'type' => 'municipality', 'postal_code' => '4017'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-PAN', 'city_name' => 'Pangil', 'type' => 'municipality', 'postal_code' => '4018'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-SIN', 'city_name' => 'Siniloan', 'type' => 'municipality', 'postal_code' => '4019'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-FAM', 'city_name' => 'Famy', 'type' => 'municipality', 'postal_code' => '4021'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-KAL', 'city_name' => 'Kalayaan', 'type' => 'municipality', 'postal_code' => '4035'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-BAY', 'city_name' => 'Bay', 'type' => 'municipality', 'postal_code' => '4033'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-ALM', 'city_name' => 'Alaminos', 'type' => 'municipality', 'postal_code' => '4001'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-RIZ', 'city_name' => 'Rizal', 'type' => 'municipality', 'postal_code' => '4003'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-LOP', 'city_name' => 'Lumban', 'type' => 'municipality', 'postal_code' => '4014'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-MAB', 'city_name' => 'Mabitac', 'type' => 'municipality', 'postal_code' => '4020'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-PAE', 'city_name' => 'Paete', 'type' => 'municipality', 'postal_code' => '4016'],
            ['province_id' => $provinces['04A-LAG']->province_id ?? null, 'city_code' => '04A-LAG-PAT', 'city_name' => 'Pagsanjan', 'type' => 'municipality', 'postal_code' => '4008'],
            
            // Batangas (04A-BAT)
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-BAT', 'city_name' => 'Batangas City', 'type' => 'city', 'postal_code' => '4200'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-LIP', 'city_name' => 'Lipa', 'type' => 'city', 'postal_code' => '4217'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-TAN', 'city_name' => 'Tanauan', 'type' => 'city', 'postal_code' => '4232'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-LEM', 'city_name' => 'Lemery', 'type' => 'municipality', 'postal_code' => '4239'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-TAA', 'city_name' => 'Taal', 'type' => 'municipality', 'postal_code' => '4208'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-SJU', 'city_name' => 'San Juan', 'type' => 'municipality', 'postal_code' => '4226'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-LOB', 'city_name' => 'Lobo', 'type' => 'municipality', 'postal_code' => '4229'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-ROS', 'city_name' => 'Rosario', 'type' => 'municipality', 'postal_code' => '4225'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-IBB', 'city_name' => 'Ibaan', 'type' => 'municipality', 'postal_code' => '4230'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-TAY', 'city_name' => 'Taysan', 'type' => 'municipality', 'postal_code' => '4228'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-LAU', 'city_name' => 'Laurel', 'type' => 'municipality', 'postal_code' => '4221'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-AGO', 'city_name' => 'Agoncillo', 'type' => 'municipality', 'postal_code' => '4211'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-ALI', 'city_name' => 'Alitagtag', 'type' => 'municipality', 'postal_code' => '4205'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-BAL', 'city_name' => 'Balayan', 'type' => 'municipality', 'postal_code' => '4213'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-BAU', 'city_name' => 'Bauan', 'type' => 'municipality', 'postal_code' => '4201'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-CAC', 'city_name' => 'Calaca', 'type' => 'municipality', 'postal_code' => '4212'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-CAT', 'city_name' => 'Calatagan', 'type' => 'municipality', 'postal_code' => '4215'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-CUA', 'city_name' => 'Cuenca', 'type' => 'municipality', 'postal_code' => '4222'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-LIA', 'city_name' => 'Lian', 'type' => 'municipality', 'postal_code' => '4216'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-MAL', 'city_name' => 'Malvar', 'type' => 'municipality', 'postal_code' => '4233'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-MAT', 'city_name' => 'Mataasnakahoy', 'type' => 'municipality', 'postal_code' => '4234'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-NAS', 'city_name' => 'Nasugbu', 'type' => 'municipality', 'postal_code' => '4231'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-PAD', 'city_name' => 'Padre Garcia', 'type' => 'municipality', 'postal_code' => '4224'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-SJO', 'city_name' => 'San Jose', 'type' => 'municipality', 'postal_code' => '4227'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-SLU', 'city_name' => 'San Luis', 'type' => 'municipality', 'postal_code' => '4210'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-SNI', 'city_name' => 'San Nicolas', 'type' => 'municipality', 'postal_code' => '4207'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-STO', 'city_name' => 'Santo Tomas', 'type' => 'city', 'postal_code' => '4234'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-TAS', 'city_name' => 'Talisay', 'type' => 'municipality', 'postal_code' => '4220'],
            ['province_id' => $provinces['04A-BAT']->province_id ?? null, 'city_code' => '04A-BAT-TUI', 'city_name' => 'Tuy', 'type' => 'municipality', 'postal_code' => '4214'],
            
            // Rizal (04A-RIZ)
            ['province_id' => $provinces['04A-RIZ']->province_id ?? null, 'city_code' => '04A-RIZ-ANT', 'city_name' => 'Antipolo', 'type' => 'city', 'postal_code' => '1870'],
            ['province_id' => $provinces['04A-RIZ']->province_id ?? null, 'city_code' => '04A-RIZ-CAI', 'city_name' => 'Cainta', 'type' => 'municipality', 'postal_code' => '1900'],
            ['province_id' => $provinces['04A-RIZ']->province_id ?? null, 'city_code' => '04A-RIZ-TAY', 'city_name' => 'Taytay', 'type' => 'municipality', 'postal_code' => '1920'],
            ['province_id' => $provinces['04A-RIZ']->province_id ?? null, 'city_code' => '04A-RIZ-ANG', 'city_name' => 'Angono', 'type' => 'municipality', 'postal_code' => '1930'],
            ['province_id' => $provinces['04A-RIZ']->province_id ?? null, 'city_code' => '04A-RIZ-BIN', 'city_name' => 'Binangonan', 'type' => 'municipality', 'postal_code' => '1940'],
            ['province_id' => $provinces['04A-RIZ']->province_id ?? null, 'city_code' => '04A-RIZ-SMT', 'city_name' => 'San Mateo', 'type' => 'municipality', 'postal_code' => '1850'],
            ['province_id' => $provinces['04A-RIZ']->province_id ?? null, 'city_code' => '04A-RIZ-ROD', 'city_name' => 'Rodriguez', 'type' => 'municipality', 'postal_code' => '1860'],
            ['province_id' => $provinces['04A-RIZ']->province_id ?? null, 'city_code' => '04A-RIZ-CAR', 'city_name' => 'Cardona', 'type' => 'municipality', 'postal_code' => '1950'],
            ['province_id' => $provinces['04A-RIZ']->province_id ?? null, 'city_code' => '04A-RIZ-JAL', 'city_name' => 'Jalajala', 'type' => 'municipality', 'postal_code' => '1990'],
            ['province_id' => $provinces['04A-RIZ']->province_id ?? null, 'city_code' => '04A-RIZ-MOR', 'city_name' => 'Morong', 'type' => 'municipality', 'postal_code' => '1960'],
            ['province_id' => $provinces['04A-RIZ']->province_id ?? null, 'city_code' => '04A-RIZ-PIL', 'city_name' => 'Pililla', 'type' => 'municipality', 'postal_code' => '1910'],
            ['province_id' => $provinces['04A-RIZ']->province_id ?? null, 'city_code' => '04A-RIZ-TAN', 'city_name' => 'Tanay', 'type' => 'municipality', 'postal_code' => '1980'],
            ['province_id' => $provinces['04A-RIZ']->province_id ?? null, 'city_code' => '04A-RIZ-TER', 'city_name' => 'Teresa', 'type' => 'municipality', 'postal_code' => '1880'],
            ['province_id' => $provinces['04A-RIZ']->province_id ?? null, 'city_code' => '04A-RIZ-BAR', 'city_name' => 'Baras', 'type' => 'municipality', 'postal_code' => '1970'],
            
            // Quezon (04A-QUE)
            ['province_id' => $provinces['04A-QUE']->province_id ?? null, 'city_code' => '04A-QUE-LUC', 'city_name' => 'Lucena', 'type' => 'city', 'postal_code' => '4301'],
            ['province_id' => $provinces['04A-QUE']->province_id ?? null, 'city_code' => '04A-QUE-TAY', 'city_name' => 'Tayabas', 'type' => 'city', 'postal_code' => '4327'],
            ['province_id' => $provinces['04A-QUE']->province_id ?? null, 'city_code' => '04A-QUE-LUB', 'city_name' => 'Lucban', 'type' => 'municipality', 'postal_code' => '4328'],
            ['province_id' => $provinces['04A-QUE']->province_id ?? null, 'city_code' => '04A-QUE-GUM', 'city_name' => 'Gumaca', 'type' => 'municipality', 'postal_code' => '4307'],
            ['province_id' => $provinces['04A-QUE']->province_id ?? null, 'city_code' => '04A-QUE-LOP', 'city_name' => 'Lopez', 'type' => 'municipality', 'postal_code' => '4316'],
            
            // Marinduque (04B-MAR)
            ['province_id' => $provinces['04B-MAR']->province_id ?? null, 'city_code' => '04B-MAR-BOA', 'city_name' => 'Boac', 'type' => 'municipality', 'postal_code' => '4900'],
            ['province_id' => $provinces['04B-MAR']->province_id ?? null, 'city_code' => '04B-MAR-GAS', 'city_name' => 'Gasan', 'type' => 'municipality', 'postal_code' => '4905'],
            
            // Occidental Mindoro (04B-OCC)
            ['province_id' => $provinces['04B-OCC']->province_id ?? null, 'city_code' => '04B-OCC-MAM', 'city_name' => 'Mamburao', 'type' => 'municipality', 'postal_code' => '5106'],
            ['province_id' => $provinces['04B-OCC']->province_id ?? null, 'city_code' => '04B-OCC-SAB', 'city_name' => 'Sablayan', 'type' => 'municipality', 'postal_code' => '5104'],
            
            // Oriental Mindoro (04B-ORI)
            ['province_id' => $provinces['04B-ORI']->province_id ?? null, 'city_code' => '04B-ORI-CAL', 'city_name' => 'Calapan', 'type' => 'city', 'postal_code' => '5200'],
            ['province_id' => $provinces['04B-ORI']->province_id ?? null, 'city_code' => '04B-ORI-ROX', 'city_name' => 'Roxas', 'type' => 'municipality', 'postal_code' => '5212'],
            
            // Palawan (04B-PAL)
            ['province_id' => $provinces['04B-PAL']->province_id ?? null, 'city_code' => '04B-PAL-PPS', 'city_name' => 'Puerto Princesa', 'type' => 'city', 'postal_code' => '5300'],
            ['province_id' => $provinces['04B-PAL']->province_id ?? null, 'city_code' => '04B-PAL-ELN', 'city_name' => 'El Nido', 'type' => 'municipality', 'postal_code' => '5313'],
            ['province_id' => $provinces['04B-PAL']->province_id ?? null, 'city_code' => '04B-PAL-COR', 'city_name' => 'Coron', 'type' => 'municipality', 'postal_code' => '5316'],
            
            // Romblon (04B-ROM)
            ['province_id' => $provinces['04B-ROM']->province_id ?? null, 'city_code' => '04B-ROM-ROM', 'city_name' => 'Romblon', 'type' => 'municipality', 'postal_code' => '5500'],
            ['province_id' => $provinces['04B-ROM']->province_id ?? null, 'city_code' => '04B-ROM-ODI', 'city_name' => 'Odiongan', 'type' => 'municipality', 'postal_code' => '5505'],
            
            // Albay (05-ALB)
            ['province_id' => $provinces['05-ALB']->province_id ?? null, 'city_code' => '05-ALB-LEG', 'city_name' => 'Legazpi', 'type' => 'city', 'postal_code' => '4500'],
            ['province_id' => $provinces['05-ALB']->province_id ?? null, 'city_code' => '05-ALB-LIG', 'city_name' => 'Ligao', 'type' => 'city', 'postal_code' => '4504'],
            ['province_id' => $provinces['05-ALB']->province_id ?? null, 'city_code' => '05-ALB-TAB', 'city_name' => 'Tabaco', 'type' => 'city', 'postal_code' => '4511'],
            
            // Camarines Norte (05-CAM)
            ['province_id' => $provinces['05-CAM']->province_id ?? null, 'city_code' => '05-CAM-DAE', 'city_name' => 'Daet', 'type' => 'municipality', 'postal_code' => '4600'],
            ['province_id' => $provinces['05-CAM']->province_id ?? null, 'city_code' => '05-CAM-LAB', 'city_name' => 'Labo', 'type' => 'municipality', 'postal_code' => '4604'],
            
            // Camarines Sur (05-CAS)
            ['province_id' => $provinces['05-CAS']->province_id ?? null, 'city_code' => '05-CAS-NAG', 'city_name' => 'Naga', 'type' => 'city', 'postal_code' => '4400'],
            ['province_id' => $provinces['05-CAS']->province_id ?? null, 'city_code' => '05-CAS-IRI', 'city_name' => 'Iriga', 'type' => 'city', 'postal_code' => '4431'],
            ['province_id' => $provinces['05-CAS']->province_id ?? null, 'city_code' => '05-CAS-PIL', 'city_name' => 'Pili', 'type' => 'municipality', 'postal_code' => '4418'],
            ['province_id' => $provinces['05-CAS']->province_id ?? null, 'city_code' => '05-CAS-NAB', 'city_name' => 'Nabua', 'type' => 'municipality', 'postal_code' => '4434'],
            
            // Catanduanes (05-CAT)
            ['province_id' => $provinces['05-CAT']->province_id ?? null, 'city_code' => '05-CAT-VIR', 'city_name' => 'Virac', 'type' => 'municipality', 'postal_code' => '4800'],
            ['province_id' => $provinces['05-CAT']->province_id ?? null, 'city_code' => '05-CAT-BAR', 'city_name' => 'Bato', 'type' => 'municipality', 'postal_code' => '4801'],
            
            // Masbate (05-MAS)
            ['province_id' => $provinces['05-MAS']->province_id ?? null, 'city_code' => '05-MAS-MAS', 'city_name' => 'Masbate City', 'type' => 'city', 'postal_code' => '5400'],
            ['province_id' => $provinces['05-MAS']->province_id ?? null, 'city_code' => '05-MAS-MIL', 'city_name' => 'Milagros', 'type' => 'municipality', 'postal_code' => '5410'],
            ['province_id' => $provinces['05-MAS']->province_id ?? null, 'city_code' => '05-MAS-CAT', 'city_name' => 'Cataingan', 'type' => 'municipality', 'postal_code' => '5415'],
            
            // Sorsogon (05-SOR)
            ['province_id' => $provinces['05-SOR']->province_id ?? null, 'city_code' => '05-SOR-SOR', 'city_name' => 'Sorsogon City', 'type' => 'city', 'postal_code' => '4700'],
            ['province_id' => $provinces['05-SOR']->province_id ?? null, 'city_code' => '05-SOR-GUB', 'city_name' => 'Gubat', 'type' => 'municipality', 'postal_code' => '4710'],
            ['province_id' => $provinces['05-SOR']->province_id ?? null, 'city_code' => '05-SOR-BUL', 'city_name' => 'Bulan', 'type' => 'municipality', 'postal_code' => '4706'],
            ['province_id' => $provinces['05-SOR']->province_id ?? null, 'city_code' => '05-SOR-CAG', 'city_name' => 'Casiguran', 'type' => 'municipality', 'postal_code' => '4702'],
            ['province_id' => $provinces['05-SOR']->province_id ?? null, 'city_code' => '05-SOR-CAS', 'city_name' => 'Castilla', 'type' => 'municipality', 'postal_code' => '4713'],
            ['province_id' => $provinces['05-SOR']->province_id ?? null, 'city_code' => '05-SOR-DON', 'city_name' => 'Donsol', 'type' => 'municipality', 'postal_code' => '4715'],
            ['province_id' => $provinces['05-SOR']->province_id ?? null, 'city_code' => '05-SOR-IRO', 'city_name' => 'Irosin', 'type' => 'municipality', 'postal_code' => '4707'],
            ['province_id' => $provinces['05-SOR']->province_id ?? null, 'city_code' => '05-SOR-JUB', 'city_name' => 'Juban', 'type' => 'municipality', 'postal_code' => '4703'],
            ['province_id' => $provinces['05-SOR']->province_id ?? null, 'city_code' => '05-SOR-MAG', 'city_name' => 'Magallanes', 'type' => 'municipality', 'postal_code' => '4705'],
            ['province_id' => $provinces['05-SOR']->province_id ?? null, 'city_code' => '05-SOR-MAT', 'city_name' => 'Matnog', 'type' => 'municipality', 'postal_code' => '4708'],
            ['province_id' => $provinces['05-SOR']->province_id ?? null, 'city_code' => '05-SOR-PIL', 'city_name' => 'Pilar', 'type' => 'municipality', 'postal_code' => '4714'],
            ['province_id' => $provinces['05-SOR']->province_id ?? null, 'city_code' => '05-SOR-PRI', 'city_name' => 'Prieto Diaz', 'type' => 'municipality', 'postal_code' => '4711'],
            ['province_id' => $provinces['05-SOR']->province_id ?? null, 'city_code' => '05-SOR-SAN', 'city_name' => 'Santa Magdalena', 'type' => 'municipality', 'postal_code' => '4709'],
            ['province_id' => $provinces['05-SOR']->province_id ?? null, 'city_code' => '05-SOR-BAC', 'city_name' => 'Barcelona', 'type' => 'municipality', 'postal_code' => '4712'],
            ['province_id' => $provinces['05-SOR']->province_id ?? null, 'city_code' => '05-SOR-BUS', 'city_name' => 'Bulusan', 'type' => 'municipality', 'postal_code' => '4704'],
            
            // Aklan (06-AKL)
            ['province_id' => $provinces['06-AKL']->province_id ?? null, 'city_code' => '06-AKL-KAL', 'city_name' => 'Kalibo', 'type' => 'municipality', 'postal_code' => '5600'],
            ['province_id' => $provinces['06-AKL']->province_id ?? null, 'city_code' => '06-AKL-BOR', 'city_name' => 'Boracay (Malay)', 'type' => 'municipality', 'postal_code' => '5608'],
            
            // Antique (06-ANT)
            ['province_id' => $provinces['06-ANT']->province_id ?? null, 'city_code' => '06-ANT-SAN', 'city_name' => 'San Jose', 'type' => 'municipality', 'postal_code' => '5700'],
            ['province_id' => $provinces['06-ANT']->province_id ?? null, 'city_code' => '06-ANT-CUL', 'city_name' => 'Culasi', 'type' => 'municipality', 'postal_code' => '5708'],
            
            // Capiz (06-CAP)
            ['province_id' => $provinces['06-CAP']->province_id ?? null, 'city_code' => '06-CAP-ROX', 'city_name' => 'Roxas', 'type' => 'city', 'postal_code' => '5800'],
            ['province_id' => $provinces['06-CAP']->province_id ?? null, 'city_code' => '06-CAP-PIL', 'city_name' => 'Pilar', 'type' => 'municipality', 'postal_code' => '5809'],
            
            // Guimaras (06-GUI)
            ['province_id' => $provinces['06-GUI']->province_id ?? null, 'city_code' => '06-GUI-JOR', 'city_name' => 'Jordan', 'type' => 'municipality', 'postal_code' => '5045'],
            ['province_id' => $provinces['06-GUI']->province_id ?? null, 'city_code' => '06-GUI-BUE', 'city_name' => 'Buenavista', 'type' => 'municipality', 'postal_code' => '5044'],
            
            // Iloilo (06-ILI)
            ['province_id' => $provinces['06-ILI']->province_id ?? null, 'city_code' => '06-ILI-ILO', 'city_name' => 'Iloilo City', 'type' => 'city', 'postal_code' => '5000'],
            ['province_id' => $provinces['06-ILI']->province_id ?? null, 'city_code' => '06-ILI-PAS', 'city_name' => 'Passi', 'type' => 'city', 'postal_code' => '5037'],
            ['province_id' => $provinces['06-ILI']->province_id ?? null, 'city_code' => '06-ILI-LEG', 'city_name' => 'Leganes', 'type' => 'municipality', 'postal_code' => '5003'],
            ['province_id' => $provinces['06-ILI']->province_id ?? null, 'city_code' => '06-ILI-OTN', 'city_name' => 'Oton', 'type' => 'municipality', 'postal_code' => '5020'],
            
            // Negros Occidental (06-NEC)
            ['province_id' => $provinces['06-NEC']->province_id ?? null, 'city_code' => '06-NEC-BAC', 'city_name' => 'Bacolod', 'type' => 'city', 'postal_code' => '6100'],
            ['province_id' => $provinces['06-NEC']->province_id ?? null, 'city_code' => '06-NEC-CAD', 'city_name' => 'Cadiz', 'type' => 'city', 'postal_code' => '6121'],
            ['province_id' => $provinces['06-NEC']->province_id ?? null, 'city_code' => '06-NEC-SIL', 'city_name' => 'Silay', 'type' => 'city', 'postal_code' => '6116'],
            ['province_id' => $provinces['06-NEC']->province_id ?? null, 'city_code' => '06-NEC-VIC', 'city_name' => 'Victorias', 'type' => 'city', 'postal_code' => '6119'],
            ['province_id' => $provinces['06-NEC']->province_id ?? null, 'city_code' => '06-NEC-SAG', 'city_name' => 'Sagay', 'type' => 'city', 'postal_code' => '6122'],
            
            // Cebu (07-CEB)
            ['province_id' => $provinces['07-CEB']->province_id ?? null, 'city_code' => '07-CEB-CEB', 'city_name' => 'Cebu City', 'type' => 'city', 'postal_code' => '6000'],
            ['province_id' => $provinces['07-CEB']->province_id ?? null, 'city_code' => '07-CEB-MAN', 'city_name' => 'Mandaue', 'type' => 'city', 'postal_code' => '6014'],
            ['province_id' => $provinces['07-CEB']->province_id ?? null, 'city_code' => '07-CEB-LAP', 'city_name' => 'Lapu-Lapu', 'type' => 'city', 'postal_code' => '6015'],
            ['province_id' => $provinces['07-CEB']->province_id ?? null, 'city_code' => '07-CEB-TAL', 'city_name' => 'Talisay', 'type' => 'city', 'postal_code' => '6045'],
            ['province_id' => $provinces['07-CEB']->province_id ?? null, 'city_code' => '07-CEB-TOL', 'city_name' => 'Toledo', 'type' => 'city', 'postal_code' => '6038'],
            
            // Bohol (07-BOH)
            ['province_id' => $provinces['07-BOH']->province_id ?? null, 'city_code' => '07-BOH-TAG', 'city_name' => 'Tagbilaran', 'type' => 'city', 'postal_code' => '6300'],
            ['province_id' => $provinces['07-BOH']->province_id ?? null, 'city_code' => '07-BOH-TAL', 'city_name' => 'Talibon', 'type' => 'municipality', 'postal_code' => '6325'],
            ['province_id' => $provinces['07-BOH']->province_id ?? null, 'city_code' => '07-BOH-UBI', 'city_name' => 'Ubay', 'type' => 'municipality', 'postal_code' => '6315'],
            
            // Negros Oriental (07-NER)
            ['province_id' => $provinces['07-NER']->province_id ?? null, 'city_code' => '07-NER-DUM', 'city_name' => 'Dumaguete', 'type' => 'city', 'postal_code' => '6200'],
            ['province_id' => $provinces['07-NER']->province_id ?? null, 'city_code' => '07-NER-BAY', 'city_name' => 'Bayawan', 'type' => 'city', 'postal_code' => '6221'],
            ['province_id' => $provinces['07-NER']->province_id ?? null, 'city_code' => '07-NER-TAN', 'city_name' => 'Tanjay', 'type' => 'city', 'postal_code' => '6204'],
            
            // Siquijor (07-SIG)
            ['province_id' => $provinces['07-SIG']->province_id ?? null, 'city_code' => '07-SIG-SIQ', 'city_name' => 'Siquijor', 'type' => 'municipality', 'postal_code' => '6225'],
            ['province_id' => $provinces['07-SIG']->province_id ?? null, 'city_code' => '07-SIG-LAZ', 'city_name' => 'Lazi', 'type' => 'municipality', 'postal_code' => '6228'],
            
            // Biliran (08-BIL)
            ['province_id' => $provinces['08-BIL']->province_id ?? null, 'city_code' => '08-BIL-NAV', 'city_name' => 'Naval', 'type' => 'municipality', 'postal_code' => '6543'],
            
            // Eastern Samar (08-EAS)
            ['province_id' => $provinces['08-EAS']->province_id ?? null, 'city_code' => '08-EAS-BOR', 'city_name' => 'Borongan', 'type' => 'city', 'postal_code' => '6800'],
            ['province_id' => $provinces['08-EAS']->province_id ?? null, 'city_code' => '08-EAS-GUI', 'city_name' => 'Guiuan', 'type' => 'municipality', 'postal_code' => '6809'],
            
            // Leyte (08-LEY)
            ['province_id' => $provinces['08-LEY']->province_id ?? null, 'city_code' => '08-LEY-TAC', 'city_name' => 'Tacloban', 'type' => 'city', 'postal_code' => '6500'],
            ['province_id' => $provinces['08-LEY']->province_id ?? null, 'city_code' => '08-LEY-ORM', 'city_name' => 'Ormoc', 'type' => 'city', 'postal_code' => '6541'],
            ['province_id' => $provinces['08-LEY']->province_id ?? null, 'city_code' => '08-LEY-BAY', 'city_name' => 'Baybay', 'type' => 'city', 'postal_code' => '6521'],
            
            // Northern Samar (08-NOR)
            ['province_id' => $provinces['08-NOR']->province_id ?? null, 'city_code' => '08-NOR-CAT', 'city_name' => 'Catarman', 'type' => 'municipality', 'postal_code' => '6400'],
            ['province_id' => $provinces['08-NOR']->province_id ?? null, 'city_code' => '08-NOR-ALL', 'city_name' => 'Allen', 'type' => 'municipality', 'postal_code' => '6405'],
            
            // Samar (08-SAM)
            ['province_id' => $provinces['08-SAM']->province_id ?? null, 'city_code' => '08-SAM-CAT', 'city_name' => 'Catbalogan', 'type' => 'city', 'postal_code' => '6700'],
            ['province_id' => $provinces['08-SAM']->province_id ?? null, 'city_code' => '08-SAM-CAL', 'city_name' => 'Calbayog', 'type' => 'city', 'postal_code' => '6710'],
            
            // Southern Leyte (08-SOU)
            ['province_id' => $provinces['08-SOU']->province_id ?? null, 'city_code' => '08-SOU-MAA', 'city_name' => 'Maasin', 'type' => 'city', 'postal_code' => '6600'],
            ['province_id' => $provinces['08-SOU']->province_id ?? null, 'city_code' => '08-SOU-SOG', 'city_name' => 'Sogod', 'type' => 'municipality', 'postal_code' => '6606'],
            
            // Western Samar (08-WES)
            ['province_id' => $provinces['08-WES']->province_id ?? null, 'city_code' => '08-WES-CAL', 'city_name' => 'Calbiga', 'type' => 'municipality', 'postal_code' => '6715'],
            ['province_id' => $provinces['08-WES']->province_id ?? null, 'city_code' => '08-WES-PAR', 'city_name' => 'Paranas', 'type' => 'municipality', 'postal_code' => '6716'],
            
            // Zamboanga del Norte (09-ZAN)
            ['province_id' => $provinces['09-ZAN']->province_id ?? null, 'city_code' => '09-ZAN-DIP', 'city_name' => 'Dipolog', 'type' => 'city', 'postal_code' => '7100'],
            ['province_id' => $provinces['09-ZAN']->province_id ?? null, 'city_code' => '09-ZAN-DAP', 'city_name' => 'Dapitan', 'type' => 'city', 'postal_code' => '7101'],
            
            // Zamboanga del Sur (09-ZAS)
            ['province_id' => $provinces['09-ZAS']->province_id ?? null, 'city_code' => '09-ZAS-ZAM', 'city_name' => 'Zamboanga City', 'type' => 'city', 'postal_code' => '7000'],
            ['province_id' => $provinces['09-ZAS']->province_id ?? null, 'city_code' => '09-ZAS-PAG', 'city_name' => 'Pagadian', 'type' => 'city', 'postal_code' => '7016'],
            ['province_id' => $provinces['09-ZAS']->province_id ?? null, 'city_code' => '09-ZAS-MOL', 'city_name' => 'Molave', 'type' => 'municipality', 'postal_code' => '7023'],
            
            // Zamboanga Sibugay (09-ZSI)
            ['province_id' => $provinces['09-ZSI']->province_id ?? null, 'city_code' => '09-ZSI-IPI', 'city_name' => 'Ipil', 'type' => 'municipality', 'postal_code' => '7001'],
            ['province_id' => $provinces['09-ZSI']->province_id ?? null, 'city_code' => '09-ZSI-KAB', 'city_name' => 'Kabasalan', 'type' => 'municipality', 'postal_code' => '7005'],
            
            // Bukidnon (10-BUK)
            ['province_id' => $provinces['10-BUK']->province_id ?? null, 'city_code' => '10-BUK-MAL', 'city_name' => 'Malaybalay', 'type' => 'city', 'postal_code' => '8700'],
            ['province_id' => $provinces['10-BUK']->province_id ?? null, 'city_code' => '10-BUK-VAL', 'city_name' => 'Valencia', 'type' => 'city', 'postal_code' => '8709'],
            
            // Camiguin (10-CAM)
            ['province_id' => $provinces['10-CAM']->province_id ?? null, 'city_code' => '10-CAM-MAM', 'city_name' => 'Mambajao', 'type' => 'municipality', 'postal_code' => '9100'],
            
            // Lanao del Norte (10-LAN)
            ['province_id' => $provinces['10-LAN']->province_id ?? null, 'city_code' => '10-LAN-ILG', 'city_name' => 'Iligan', 'type' => 'city', 'postal_code' => '9200'],
            ['province_id' => $provinces['10-LAN']->province_id ?? null, 'city_code' => '10-LAN-TUB', 'city_name' => 'Tubod', 'type' => 'municipality', 'postal_code' => '9209'],
            
            // Misamis Occidental (10-MIS)
            ['province_id' => $provinces['10-MIS']->province_id ?? null, 'city_code' => '10-MIS-OZA', 'city_name' => 'Ozamiz', 'type' => 'city', 'postal_code' => '7200'],
            ['province_id' => $provinces['10-MIS']->province_id ?? null, 'city_code' => '10-MIS-ORO', 'city_name' => 'Oroquieta', 'type' => 'city', 'postal_code' => '7207'],
            
            // Misamis Oriental (10-MIO)
            ['province_id' => $provinces['10-MIO']->province_id ?? null, 'city_code' => '10-MIO-CAG', 'city_name' => 'Cagayan de Oro', 'type' => 'city', 'postal_code' => '9000'],
            ['province_id' => $provinces['10-MIO']->province_id ?? null, 'city_code' => '10-MIO-ELO', 'city_name' => 'El Salvador', 'type' => 'city', 'postal_code' => '9017'],
            ['province_id' => $provinces['10-MIO']->province_id ?? null, 'city_code' => '10-MIO-GIN', 'city_name' => 'Gingoog', 'type' => 'city', 'postal_code' => '9014'],
            
            // Davao del Sur (11-DAV)
            ['province_id' => $provinces['11-DAV']->province_id ?? null, 'city_code' => '11-DAV-DAV', 'city_name' => 'Davao City', 'type' => 'city', 'postal_code' => '8000'],
            ['province_id' => $provinces['11-DAV']->province_id ?? null, 'city_code' => '11-DAV-DIG', 'city_name' => 'Digos', 'type' => 'city', 'postal_code' => '8002'],
            ['province_id' => $provinces['11-DAV']->province_id ?? null, 'city_code' => '11-DAV-SAN', 'city_name' => 'Santa Cruz', 'type' => 'municipality', 'postal_code' => '8001'],
            
            // Davao Oriental (11-DAO)
            ['province_id' => $provinces['11-DAO']->province_id ?? null, 'city_code' => '11-DAO-MAT', 'city_name' => 'Mati', 'type' => 'city', 'postal_code' => '8200'],
            ['province_id' => $provinces['11-DAO']->province_id ?? null, 'city_code' => '11-DAO-BAN', 'city_name' => 'Banaybanay', 'type' => 'municipality', 'postal_code' => '8208'],
            
            // Davao del Norte (11-DAN)
            ['province_id' => $provinces['11-DAN']->province_id ?? null, 'city_code' => '11-DAN-TAG', 'city_name' => 'Tagum', 'type' => 'city', 'postal_code' => '8100'],
            ['province_id' => $provinces['11-DAN']->province_id ?? null, 'city_code' => '11-DAN-PAN', 'city_name' => 'Panabo', 'type' => 'city', 'postal_code' => '8105'],
            ['province_id' => $provinces['11-DAN']->province_id ?? null, 'city_code' => '11-DAN-ISL', 'city_name' => 'Island Garden City of Samal', 'type' => 'city', 'postal_code' => '8119'],
            
            // Davao de Oro (11-DAD)
            ['province_id' => $provinces['11-DAD']->province_id ?? null, 'city_code' => '11-DAD-NAB', 'city_name' => 'Nabunturan', 'type' => 'municipality', 'postal_code' => '8800'],
            ['province_id' => $provinces['11-DAD']->province_id ?? null, 'city_code' => '11-DAD-MON', 'city_name' => 'Monkayo', 'type' => 'municipality', 'postal_code' => '8805'],
            
            // Davao Occidental (11-DAW)
            ['province_id' => $provinces['11-DAW']->province_id ?? null, 'city_code' => '11-DAW-MAL', 'city_name' => 'Malita', 'type' => 'municipality', 'postal_code' => '8012'],
            ['province_id' => $provinces['11-DAW']->province_id ?? null, 'city_code' => '11-DAW-SAN', 'city_name' => 'Santa Maria', 'type' => 'municipality', 'postal_code' => '8011'],
            
            // Cotabato (12-COT)
            ['province_id' => $provinces['12-COT']->province_id ?? null, 'city_code' => '12-COT-KID', 'city_name' => 'Kidapawan', 'type' => 'city', 'postal_code' => '9400'],
            ['province_id' => $provinces['12-COT']->province_id ?? null, 'city_code' => '12-COT-MAK', 'city_name' => 'M\'lang', 'type' => 'municipality', 'postal_code' => '9402'],
            
            // Sarangani (12-SAR)
            ['province_id' => $provinces['12-SAR']->province_id ?? null, 'city_code' => '12-SAR-ALA', 'city_name' => 'Alabel', 'type' => 'municipality', 'postal_code' => '9501'],
            ['province_id' => $provinces['12-SAR']->province_id ?? null, 'city_code' => '12-SAR-GEN', 'city_name' => 'General Santos', 'type' => 'city', 'postal_code' => '9500'],
            
            // South Cotabato (12-SOU)
            ['province_id' => $provinces['12-SOU']->province_id ?? null, 'city_code' => '12-SOU-KOR', 'city_name' => 'Koronadal', 'type' => 'city', 'postal_code' => '9506'],
            ['province_id' => $provinces['12-SOU']->province_id ?? null, 'city_code' => '12-SOU-GEN', 'city_name' => 'General Santos', 'type' => 'city', 'postal_code' => '9500'],
            ['province_id' => $provinces['12-SOU']->province_id ?? null, 'city_code' => '12-SOU-TUP', 'city_name' => 'Tupi', 'type' => 'municipality', 'postal_code' => '9503'],
            
            // Sultan Kudarat (12-SUL)
            ['province_id' => $provinces['12-SUL']->province_id ?? null, 'city_code' => '12-SUL-ISU', 'city_name' => 'Isulan', 'type' => 'municipality', 'postal_code' => '9805'],
            ['province_id' => $provinces['12-SUL']->province_id ?? null, 'city_code' => '12-SUL-TAC', 'city_name' => 'Tacurong', 'type' => 'city', 'postal_code' => '9800'],
            
            // Agusan del Norte (13-AGU)
            ['province_id' => $provinces['13-AGU']->province_id ?? null, 'city_code' => '13-AGU-BUT', 'city_name' => 'Butuan', 'type' => 'city', 'postal_code' => '8600'],
            ['province_id' => $provinces['13-AGU']->province_id ?? null, 'city_code' => '13-AGU-CAB', 'city_name' => 'Cabadbaran', 'type' => 'city', 'postal_code' => '8605'],
            
            // Agusan del Sur (13-AGS)
            ['province_id' => $provinces['13-AGS']->province_id ?? null, 'city_code' => '13-AGS-PRO', 'city_name' => 'Prosperidad', 'type' => 'municipality', 'postal_code' => '8500'],
            ['province_id' => $provinces['13-AGS']->province_id ?? null, 'city_code' => '13-AGS-BAY', 'city_name' => 'Bayugan', 'type' => 'city', 'postal_code' => '8502'],
            
            // Dinagat Islands (13-DIN)
            ['province_id' => $provinces['13-DIN']->province_id ?? null, 'city_code' => '13-DIN-SAN', 'city_name' => 'San Jose', 'type' => 'municipality', 'postal_code' => '8427'],
            
            // Surigao del Norte (13-SUR)
            ['province_id' => $provinces['13-SUR']->province_id ?? null, 'city_code' => '13-SUR-SUR', 'city_name' => 'Surigao City', 'type' => 'city', 'postal_code' => '8400'],
            ['province_id' => $provinces['13-SUR']->province_id ?? null, 'city_code' => '13-SUR-DAP', 'city_name' => 'Dapa', 'type' => 'municipality', 'postal_code' => '8417'],
            
            // Surigao del Sur (13-SUS)
            ['province_id' => $provinces['13-SUS']->province_id ?? null, 'city_code' => '13-SUS-TAN', 'city_name' => 'Tandag', 'type' => 'city', 'postal_code' => '8300'],
            ['province_id' => $provinces['13-SUS']->province_id ?? null, 'city_code' => '13-SUS-BIS', 'city_name' => 'Bislig', 'type' => 'city', 'postal_code' => '8311'],
            
            // Abra (CAR-ABR)
            ['province_id' => $provinces['CAR-ABR']->province_id ?? null, 'city_code' => 'CAR-ABR-BAN', 'city_name' => 'Bangued', 'type' => 'municipality', 'postal_code' => '2800'],
            ['province_id' => $provinces['CAR-ABR']->province_id ?? null, 'city_code' => 'CAR-ABR-BOL', 'city_name' => 'Boliney', 'type' => 'municipality', 'postal_code' => '2815'],
            
            // Apayao (CAR-APA)
            ['province_id' => $provinces['CAR-APA']->province_id ?? null, 'city_code' => 'CAR-APA-KAB', 'city_name' => 'Kabugao', 'type' => 'municipality', 'postal_code' => '3807'],
            ['province_id' => $provinces['CAR-APA']->province_id ?? null, 'city_code' => 'CAR-APA-LUN', 'city_name' => 'Luna', 'type' => 'municipality', 'postal_code' => '3813'],
            
            // Benguet (CAR-BEN)
            ['province_id' => $provinces['CAR-BEN']->province_id ?? null, 'city_code' => 'CAR-BEN-BAG', 'city_name' => 'Baguio', 'type' => 'city', 'postal_code' => '2600'],
            ['province_id' => $provinces['CAR-BEN']->province_id ?? null, 'city_code' => 'CAR-BEN-LAO', 'city_name' => 'La Trinidad', 'type' => 'municipality', 'postal_code' => '2601'],
            ['province_id' => $provinces['CAR-BEN']->province_id ?? null, 'city_code' => 'CAR-BEN-ITG', 'city_name' => 'Itogon', 'type' => 'municipality', 'postal_code' => '2604'],
            
            // Ifugao (CAR-IFU)
            ['province_id' => $provinces['CAR-IFU']->province_id ?? null, 'city_code' => 'CAR-IFU-LAG', 'city_name' => 'Lagawe', 'type' => 'municipality', 'postal_code' => '3600'],
            ['province_id' => $provinces['CAR-IFU']->province_id ?? null, 'city_code' => 'CAR-IFU-BAN', 'city_name' => 'Banaue', 'type' => 'municipality', 'postal_code' => '3601'],
            
            // Kalinga (CAR-KAL)
            ['province_id' => $provinces['CAR-KAL']->province_id ?? null, 'city_code' => 'CAR-KAL-TAB', 'city_name' => 'Tabuk', 'type' => 'city', 'postal_code' => '3800'],
            ['province_id' => $provinces['CAR-KAL']->province_id ?? null, 'city_code' => 'CAR-KAL-RIZ', 'city_name' => 'Rizal', 'type' => 'municipality', 'postal_code' => '3808'],
            
            // Mountain Province (CAR-MOU)
            ['province_id' => $provinces['CAR-MOU']->province_id ?? null, 'city_code' => 'CAR-MOU-BON', 'city_name' => 'Bontoc', 'type' => 'municipality', 'postal_code' => '2616'],
            ['province_id' => $provinces['CAR-MOU']->province_id ?? null, 'city_code' => 'CAR-MOU-SAG', 'city_name' => 'Sagada', 'type' => 'municipality', 'postal_code' => '2619'],
            
            // Basilan (BARMM-BAS)
            ['province_id' => $provinces['BARMM-BAS']->province_id ?? null, 'city_code' => 'BARMM-BAS-ISL', 'city_name' => 'Isabela', 'type' => 'city', 'postal_code' => '7300'],
            ['province_id' => $provinces['BARMM-BAS']->province_id ?? null, 'city_code' => 'BARMM-BAS-LAM', 'city_name' => 'Lamitan', 'type' => 'city', 'postal_code' => '7302'],
            
            // Lanao del Sur (BARMM-LAN)
            ['province_id' => $provinces['BARMM-LAN']->province_id ?? null, 'city_code' => 'BARMM-LAN-MAR', 'city_name' => 'Marawi', 'type' => 'city', 'postal_code' => '9700'],
            ['province_id' => $provinces['BARMM-LAN']->province_id ?? null, 'city_code' => 'BARMM-LAN-MAL', 'city_name' => 'Malabang', 'type' => 'municipality', 'postal_code' => '9300'],
            
            // Maguindanao (BARMM-MAG)
            ['province_id' => $provinces['BARMM-MAG']->province_id ?? null, 'city_code' => 'BARMM-MAG-COT', 'city_name' => 'Cotabato City', 'type' => 'city', 'postal_code' => '9600'],
            ['province_id' => $provinces['BARMM-MAG']->province_id ?? null, 'city_code' => 'BARMM-MAG-SHA', 'city_name' => 'Shariff Aguak', 'type' => 'municipality', 'postal_code' => '9608'],
            
            // Sulu (BARMM-SUL)
            ['province_id' => $provinces['BARMM-SUL']->province_id ?? null, 'city_code' => 'BARMM-SUL-JOL', 'city_name' => 'Jolo', 'type' => 'municipality', 'postal_code' => '7400'],
            ['province_id' => $provinces['BARMM-SUL']->province_id ?? null, 'city_code' => 'BARMM-SUL-PAT', 'city_name' => 'Patikul', 'type' => 'municipality', 'postal_code' => '7401'],
            
            // Tawi-Tawi (BARMM-TAW)
            ['province_id' => $provinces['BARMM-TAW']->province_id ?? null, 'city_code' => 'BARMM-TAW-BON', 'city_name' => 'Bongao', 'type' => 'municipality', 'postal_code' => '7500'],
            ['province_id' => $provinces['BARMM-TAW']->province_id ?? null, 'city_code' => 'BARMM-TAW-SIT', 'city_name' => 'Sitangkai', 'type' => 'municipality', 'postal_code' => '7506'],
        ];

        foreach ($cities as $city) {
            if ($city['province_id']) {
                City::firstOrCreate(
                    ['city_code' => $city['city_code']],
                    [
                        'province_id' => $city['province_id'],
                        'city_name' => $city['city_name'],
                        'type' => $city['type'],
                        'postal_code' => $city['postal_code'] ?? null,
                        'is_active' => true
                    ]
                );
            }
        }
    }
}
