<?php

namespace App\Services;

use App\Models\City;
use App\Models\BasicComputation;
use App\Models\College;
use App\Models\Country;
use App\Models\DeductionType;
use App\Models\Designation;
use App\Models\EmployeeDepartment;
use App\Models\EmploymentType;
use App\Models\IncomeType;
use App\Models\NdRateGroup;
use App\Models\PayType;
use App\Models\Position;
use App\Models\Program;
use App\Models\Province;
use App\Models\Rank;
use App\Models\RateGroup;
use App\Models\Region;
use Illuminate\Support\Collection;

class EmployeeFormOptions
{
    public function resolve(?int $campusId = null, ?string $regionName = null, ?string $provinceName = null): array
    {
        $regions = Region::query()->active()->orderBy('region_name')->get();
        $selectedRegion = $regionName
            ? $regions->first(fn ($region) => strcasecmp($region->region_name, $regionName) === 0)
            : null;

        $provinces = $selectedRegion
            ? Province::query()->active()->where('region_id', $selectedRegion->region_id)->orderBy('province_name')->get()
            : collect();

        $selectedProvince = $provinceName && $provinces->isNotEmpty()
            ? $provinces->first(fn ($province) => strcasecmp($province->province_name, $provinceName) === 0)
            : null;

        $cities = $selectedProvince
            ? City::query()->active()->where('province_id', $selectedProvince->province_id)->orderBy('city_name')->get()
            : collect();

        return [
            'designations' => Designation::query()->active()->orderBy('designation_name')->get(),
            'positions' => Position::query()->active()->orderBy('position_name')->get(),
            'ranks' => Rank::query()->active()->orderBy('rank_name')->get(),
            'employmentTypes' => EmploymentType::query()->active()->orderBy('sort_order')->orderBy('type_name')->get(),
            'employeeDepartments' => EmployeeDepartment::query()->active()->orderBy('sort_order')->orderBy('department_name')->get(),
            'payTypes' => PayType::query()->orderBy('pay_type_id')->get(),
            'basicComputations' => BasicComputation::query()->orderBy('basic_computation_id')->get(),
            'rateGroups' => RateGroup::query()->orderBy('description')->get(),
            'ndRateGroups' => NdRateGroup::query()->orderBy('description')->get(),
            'incomeTypes' => IncomeType::query()->where('is_active', true)->orderBy('description')->get(),
            'deductionTypes' => DeductionType::query()->where('is_active', true)->orderBy('description')->get(),
            'colleges' => College::query()
                ->active()
                ->when($campusId, fn ($query) => $query->where('campus_id', $campusId))
                ->with('campus')
                ->orderBy('college_name')
                ->get(),
            'programs' => Program::query()
                ->active()
                ->when($campusId, fn ($query) => $query->where('campus_id', $campusId))
                ->with('campus')
                ->orderBy('program_name')
                ->orderBy('program_id')
                ->get()
                ->unique(fn (Program $program) => $program->campus_id.'|'.$program->program_name)
                ->values(),
            'countries' => Country::query()->active()->orderBy('country_name')->get(),
            'regions' => $regions,
            'provinces' => $provinces,
            'cities' => $cities,
            'selectedRegionId' => $selectedRegion?->region_id,
            'selectedProvinceId' => $selectedProvince?->province_id,
            'campusId' => $campusId,
        ];
    }

    public function legacyOption(Collection $options, string $valueField, ?string $currentValue): array
    {
        if (blank($currentValue)) {
            return [];
        }

        $exists = $options->contains(fn ($option) => (string) $option->{$valueField} === (string) $currentValue);

        return $exists ? [] : [['value' => $currentValue, 'label' => $currentValue.' (Legacy)']];
    }
}
