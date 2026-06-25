<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_employees', function (Blueprint $table) {
            $table->string('suffix', 20)->nullable()->after('last_name');
            $table->string('user_type', 20)->nullable()->after('suffix');
            $table->string('college')->nullable()->after('department');
            $table->string('designation')->nullable()->after('position');
            $table->string('rank')->nullable()->after('designation');
            $table->string('program')->nullable()->after('rank');
            $table->date('birth_date')->nullable()->after('program');
            $table->string('place_of_birth')->nullable()->after('birth_date');
            $table->string('gender', 20)->nullable()->after('place_of_birth');
            $table->string('civil_status', 20)->nullable()->after('gender');
            $table->string('nationality')->nullable()->after('civil_status');
            $table->string('religion')->nullable()->after('nationality');
            $table->string('language_dialect')->nullable()->after('religion');
            $table->decimal('height_cm', 5, 2)->nullable()->after('language_dialect');
            $table->decimal('weight_kg', 5, 2)->nullable()->after('height_cm');
            $table->string('tin_number', 30)->nullable()->after('weight_kg');
            $table->string('sss_number', 30)->nullable()->after('tin_number');
            $table->string('philhealth_number', 30)->nullable()->after('sss_number');
            $table->string('pagibig_number', 30)->nullable()->after('philhealth_number');
            $table->string('gsis_number', 30)->nullable()->after('pagibig_number');
            $table->string('tax_status', 50)->nullable()->after('gsis_number');
            $table->string('home_phone', 30)->nullable()->after('phone');
            $table->string('work_phone', 30)->nullable()->after('home_phone');
            $table->string('fax_number', 30)->nullable()->after('work_phone');
            $table->string('emergency_contact_name')->nullable()->after('fax_number');
            $table->string('emergency_contact_relationship')->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_phone', 30)->nullable()->after('emergency_contact_relationship');
            $table->string('emergency_contact_email')->nullable()->after('emergency_contact_phone');
            $table->text('emergency_contact_address')->nullable()->after('emergency_contact_email');
            $table->string('address_line')->nullable()->after('emergency_contact_address');
            $table->string('country')->nullable()->after('address_line');
            $table->string('region')->nullable()->after('country');
            $table->string('province')->nullable()->after('region');
            $table->string('city_municipality')->nullable()->after('province');
            $table->string('barangay')->nullable()->after('city_municipality');
            $table->string('postal_code', 20)->nullable()->after('barangay');
            $table->json('extended_profile')->nullable()->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_employees', function (Blueprint $table) {
            $table->dropColumn([
                'suffix',
                'user_type',
                'college',
                'designation',
                'rank',
                'program',
                'birth_date',
                'place_of_birth',
                'gender',
                'civil_status',
                'nationality',
                'religion',
                'language_dialect',
                'height_cm',
                'weight_kg',
                'tin_number',
                'sss_number',
                'philhealth_number',
                'pagibig_number',
                'gsis_number',
                'tax_status',
                'home_phone',
                'work_phone',
                'fax_number',
                'emergency_contact_name',
                'emergency_contact_relationship',
                'emergency_contact_phone',
                'emergency_contact_email',
                'emergency_contact_address',
                'address_line',
                'country',
                'region',
                'province',
                'city_municipality',
                'barangay',
                'postal_code',
                'extended_profile',
            ]);
        });
    }
};
