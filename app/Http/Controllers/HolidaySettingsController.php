<?php

namespace App\Http\Controllers;

use App\Models\TimekeepingHoliday;
use App\Models\TimekeepingHolidayGroup;
use App\Models\TimekeepingHolidayYear;
use App\Models\TimekeepingYear;
use App\Services\SysLogService;
use App\Support\HolidaySettings as HolidaySettingsSupport;
use App\Support\LiveTable;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HolidaySettingsController extends Controller
{
    public function index(Request $request): View
    {
        HolidaySettingsSupport::authorize($request->user(), 'view');

        $subTab = HolidaySettingsSupport::resolveSubTab($request->string('sub')->toString() ?: null);
        $search = $request->string('search')->trim()->toString();
        $subConfig = HolidaySettingsSupport::subTabConfig($subTab);

        $records = match ($subTab) {
            'groups' => HolidaySettingsSupport::groupListQuery(),
            'year' => HolidaySettingsSupport::yearListQuery(),
            default => HolidaySettingsSupport::holidayListQuery(),
        };

        $records = $records
            ->when($search !== '', function ($query) use ($subTab, $search) {
                $query->where(function ($searchQuery) use ($subTab, $search) {
                    match ($subTab) {
                        'groups' => $searchQuery
                            ->where('timekeeping_holiday_group_code', 'like', '%'.$search.'%')
                            ->orWhere('description', 'like', '%'.$search.'%'),
                        'year' => $searchQuery->where('timekeeping_year', 'like', '%'.$search.'%'),
                        default => $searchQuery
                            ->where('timekeeping_holiday_code', 'like', '%'.$search.'%')
                            ->orWhere('description', 'like', '%'.$search.'%')
                            ->orWhere('short_description', 'like', '%'.$search.'%'),
                    };
                });
            })
            ->paginate(LiveTable::perPage($request, 15))
            ->withQueryString();

        if (! $request->ajax()) {
            SysLogService::record(
                action: 'read',
                table: $subConfig['log_table'],
                description: 'Viewed Holiday Settings — '.$subConfig['label'].' ('.$records->total().' records)',
            );
        }

        $viewData = [
            'moduleTab' => 'holiday-settings',
            'subTab' => $subTab,
            'subTabs' => HolidaySettingsSupport::subTabs(),
            'subConfig' => $subConfig,
            'tabs' => TimekeepingPolicySupport::moduleTabs(),
            'moduleConfig' => TimekeepingPolicySupport::moduleTabConfig('holiday-settings'),
            'records' => $records,
            'search' => $search,
            'holidayOptions' => HolidaySettingsSupport::holidayOptions(),
            'openEditId' => old('edit_record_id', $request->input('edit')),
            'openCreate' => ($request->session()->get('errors')?->any() && old('form_context') === "create-$subTab") || $request->boolean('create'),
            'openYearId' => old('edit_year_id', $request->input('year')),
        ];

        if ($request->ajax()) {
            return view("timekeeping.holiday-settings.{$subTab}._results", $viewData);
        }

        return view('timekeeping.policy.index', $viewData);
    }

    public function storeHoliday(Request $request): RedirectResponse
    {
        HolidaySettingsSupport::authorize($request->user(), 'add');

        $validated = HolidaySettingsSupport::validateHoliday($request->all());
        $record = TimekeepingHoliday::query()->create(HolidaySettingsSupport::holidayPayload($validated));

        SysLogService::record(
            action: 'create',
            table: 'tbl_timekeeping_holidays',
            recordId: $record->timekeeping_holiday_id,
            newValues: $record->toArray(),
            description: 'Created Holiday: '.$record->timekeeping_holiday_code,
        );

        return redirect()
            ->to(HolidaySettingsSupport::moduleIndexRoute('holidays'))
            ->with('success', 'Holiday created successfully.');
    }

    public function updateHoliday(Request $request, int $holiday): RedirectResponse
    {
        HolidaySettingsSupport::authorize($request->user(), 'update');

        $record = HolidaySettingsSupport::findHolidayOrFail($holiday);
        $oldValues = $record->toArray();
        $validated = HolidaySettingsSupport::validateHoliday($request->all(), $record->timekeeping_holiday_id);
        $record->update(HolidaySettingsSupport::holidayPayload($validated));

        SysLogService::record(
            action: 'update',
            table: 'tbl_timekeeping_holidays',
            recordId: $record->timekeeping_holiday_id,
            oldValues: $oldValues,
            newValues: $record->fresh()->toArray(),
            description: 'Updated Holiday: '.$record->timekeeping_holiday_code,
        );

        return redirect()
            ->to(HolidaySettingsSupport::moduleIndexRoute('holidays'))
            ->with('success', 'Holiday updated successfully.');
    }

    public function destroyHoliday(Request $request, int $holiday): RedirectResponse
    {
        HolidaySettingsSupport::authorize($request->user(), 'delete');

        $record = HolidaySettingsSupport::findHolidayOrFail($holiday);

        if (HolidaySettingsSupport::holidayInUse($record)) {
            return redirect()
                ->to(HolidaySettingsSupport::moduleIndexRoute('holidays'))
                ->with('error', 'You cannot delete holidays that are assigned to a holiday group.');
        }

        $oldValues = $record->toArray();
        $record->delete();

        SysLogService::record(
            action: 'delete',
            table: 'tbl_timekeeping_holidays',
            recordId: $record->timekeeping_holiday_id,
            oldValues: $oldValues,
            description: 'Deleted Holiday: '.$record->timekeeping_holiday_code,
        );

        return redirect()
            ->to(HolidaySettingsSupport::moduleIndexRoute('holidays'))
            ->with('success', 'Holiday deleted successfully.');
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        HolidaySettingsSupport::authorize($request->user(), 'add');

        $validated = HolidaySettingsSupport::validateGroup($request->all());
        $record = TimekeepingHolidayGroup::query()->create(HolidaySettingsSupport::groupPayload($validated));
        HolidaySettingsSupport::syncGroupHolidays($record, HolidaySettingsSupport::groupHolidayIds($validated));

        SysLogService::record(
            action: 'create',
            table: 'tbl_timekeeping_holiday_groups',
            recordId: $record->timekeeping_holiday_group_id,
            newValues: $record->fresh('holidays')->toArray(),
            description: 'Created Holiday Group: '.$record->timekeeping_holiday_group_code,
        );

        return redirect()
            ->to(HolidaySettingsSupport::moduleIndexRoute('groups'))
            ->with('success', 'Holiday group created successfully.');
    }

    public function updateGroup(Request $request, int $group): RedirectResponse
    {
        HolidaySettingsSupport::authorize($request->user(), 'update');

        $record = HolidaySettingsSupport::findGroupOrFail($group);
        $oldValues = $record->fresh('holidays')->toArray();
        $validated = HolidaySettingsSupport::validateGroup($request->all(), $record->timekeeping_holiday_group_id);

        $record->update(HolidaySettingsSupport::groupPayload($validated));
        HolidaySettingsSupport::syncGroupHolidays($record, HolidaySettingsSupport::groupHolidayIds($validated));

        SysLogService::record(
            action: 'update',
            table: 'tbl_timekeeping_holiday_groups',
            recordId: $record->timekeeping_holiday_group_id,
            oldValues: $oldValues,
            newValues: $record->fresh('holidays')->toArray(),
            description: 'Updated Holiday Group: '.$record->timekeeping_holiday_group_code,
        );

        return redirect()
            ->to(HolidaySettingsSupport::moduleIndexRoute('groups'))
            ->with('success', 'Holiday group updated successfully.');
    }

    public function destroyGroup(Request $request, int $group): RedirectResponse
    {
        HolidaySettingsSupport::authorize($request->user(), 'delete');

        $record = HolidaySettingsSupport::findGroupOrFail($group);

        if (HolidaySettingsSupport::groupInUse($record)) {
            return redirect()
                ->to(HolidaySettingsSupport::moduleIndexRoute('groups'))
                ->with('error', 'You cannot delete holiday groups that are assigned to employees.');
        }

        $oldValues = $record->fresh('holidays')->toArray();
        $record->delete();

        SysLogService::record(
            action: 'delete',
            table: 'tbl_timekeeping_holiday_groups',
            recordId: $record->timekeeping_holiday_group_id,
            oldValues: $oldValues,
            description: 'Deleted Holiday Group: '.$record->timekeeping_holiday_group_code,
        );

        return redirect()
            ->to(HolidaySettingsSupport::moduleIndexRoute('groups'))
            ->with('success', 'Holiday group deleted successfully.');
    }

    public function storeYear(Request $request): RedirectResponse
    {
        HolidaySettingsSupport::authorize($request->user(), 'add');

        $validated = HolidaySettingsSupport::validateYear($request->all());
        $record = TimekeepingYear::query()->create(HolidaySettingsSupport::yearPayload($validated));
        HolidaySettingsSupport::seedRecurringHolidaysForYear($record);

        SysLogService::record(
            action: 'create',
            table: 'tbl_timekeeping_years',
            recordId: $record->timekeeping_year_id,
            newValues: $record->fresh('holidayYears')->toArray(),
            description: 'Created Holiday Year: '.$record->timekeeping_year,
        );

        return redirect()
            ->to(HolidaySettingsSupport::moduleIndexRoute('year', ['year' => $record->timekeeping_year_id]))
            ->with('success', 'Year created successfully. Recurring holidays were added automatically.');
    }

    public function updateYear(Request $request, int $year): RedirectResponse
    {
        HolidaySettingsSupport::authorize($request->user(), 'update');

        $record = HolidaySettingsSupport::findYearOrFail($year);
        $oldValues = $record->toArray();
        $validated = HolidaySettingsSupport::validateYear($request->all(), $record->timekeeping_year_id);
        $record->update(HolidaySettingsSupport::yearPayload($validated));

        SysLogService::record(
            action: 'update',
            table: 'tbl_timekeeping_years',
            recordId: $record->timekeeping_year_id,
            oldValues: $oldValues,
            newValues: $record->fresh()->toArray(),
            description: 'Updated Holiday Year: '.$record->timekeeping_year,
        );

        return redirect()
            ->to(HolidaySettingsSupport::moduleIndexRoute('year', ['year' => $record->timekeeping_year_id]))
            ->with('success', 'Year updated successfully.');
    }

    public function destroyYear(Request $request, int $year): RedirectResponse
    {
        HolidaySettingsSupport::authorize($request->user(), 'delete');

        $record = HolidaySettingsSupport::findYearOrFail($year);

        if (HolidaySettingsSupport::yearInUse($record)) {
            return redirect()
                ->to(HolidaySettingsSupport::moduleIndexRoute('year'))
                ->with('error', 'You cannot delete the current year or past years.');
        }

        $oldValues = $record->fresh('holidayYears')->toArray();
        $record->delete();

        SysLogService::record(
            action: 'delete',
            table: 'tbl_timekeeping_years',
            recordId: $record->timekeeping_year_id,
            oldValues: $oldValues,
            description: 'Deleted Holiday Year: '.$record->timekeeping_year,
        );

        return redirect()
            ->to(HolidaySettingsSupport::moduleIndexRoute('year'))
            ->with('success', 'Year deleted successfully.');
    }

    public function storeYearEntry(Request $request, int $year): RedirectResponse
    {
        HolidaySettingsSupport::authorize($request->user(), 'update');

        $yearRecord = HolidaySettingsSupport::findYearOrFail($year);
        $validated = HolidaySettingsSupport::validateYearEntryAdd($request->all(), $yearRecord->timekeeping_year_id);
        $holiday = HolidaySettingsSupport::findHolidayOrFail((int) $validated['timekeeping_holiday_id']);

        if (HolidaySettingsSupport::yearEntryExistsForYear($yearRecord->timekeeping_year_id, $holiday->timekeeping_holiday_id)) {
            return redirect()
                ->to(HolidaySettingsSupport::moduleIndexRoute('year', ['year' => $yearRecord->timekeeping_year_id]))
                ->with('error', 'This holiday is already assigned to this year.');
        }

        $entry = HolidaySettingsSupport::createYearEntryFromHoliday($holiday, $yearRecord->timekeeping_year_id);

        SysLogService::record(
            action: 'create',
            table: 'tbl_timekeeping_holiday_years',
            recordId: $entry->timekeeping_holiday_year_id,
            newValues: $entry->toArray(),
            description: 'Added holiday '.$holiday->timekeeping_holiday_code.' to year '.$yearRecord->timekeeping_year,
        );

        return redirect()
            ->to(HolidaySettingsSupport::moduleIndexRoute('year', ['year' => $yearRecord->timekeeping_year_id]))
            ->with('success', 'Holiday added to year successfully.');
    }

    public function updateYearEntry(Request $request, int $year, int $entry): RedirectResponse
    {
        HolidaySettingsSupport::authorize($request->user(), 'update');

        $yearRecord = HolidaySettingsSupport::findYearOrFail($year);
        $record = HolidaySettingsSupport::findYearEntryOrFail($yearRecord->timekeeping_year_id, $entry);
        $oldValues = $record->toArray();
        $validated = HolidaySettingsSupport::validateYearEntryEdit($request->all(), $yearRecord->timekeeping_year_id, $record->timekeeping_holiday_year_id);
        $record->update(HolidaySettingsSupport::yearEntryPayload($validated));

        SysLogService::record(
            action: 'update',
            table: 'tbl_timekeeping_holiday_years',
            recordId: $record->timekeeping_holiday_year_id,
            oldValues: $oldValues,
            newValues: $record->fresh()->toArray(),
            description: 'Updated year holiday '.$record->timekeeping_holiday_code.' for '.$yearRecord->timekeeping_year,
        );

        return redirect()
            ->to(HolidaySettingsSupport::moduleIndexRoute('year', ['year' => $yearRecord->timekeeping_year_id]))
            ->with('success', 'Year holiday updated successfully.');
    }

    public function destroyYearEntry(Request $request, int $year, int $entry): RedirectResponse
    {
        HolidaySettingsSupport::authorize($request->user(), 'delete');

        $yearRecord = HolidaySettingsSupport::findYearOrFail($year);
        $record = HolidaySettingsSupport::findYearEntryOrFail($yearRecord->timekeeping_year_id, $entry);

        $recordId = $record->timekeeping_holiday_year_id;
        $holidayCode = $record->timekeeping_holiday_code;
        $yearValue = $yearRecord->timekeeping_year;
        $oldValues = $record->toArray();

        $record->delete();

        SysLogService::record(
            action: 'delete',
            table: 'tbl_timekeeping_holiday_years',
            recordId: $recordId,
            oldValues: $oldValues,
            description: 'Deleted year holiday '.$holidayCode.' from year '.$yearValue,
        );

        return redirect()
            ->to(HolidaySettingsSupport::moduleIndexRoute('year', ['year' => $yearRecord->timekeeping_year_id]))
            ->with('success', 'Holiday '.$holidayCode.' removed from year '.$yearValue.'.');
    }
}
