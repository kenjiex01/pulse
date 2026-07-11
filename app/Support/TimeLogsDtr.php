<?php

namespace App\Support;

use App\Models\Campus;
use RuntimeException;

class TimeLogsDtr
{
    public static function campusFormat(Campus $campus): array
    {
        $format = config('time_logs_dtr.campuses.'.$campus->campus_code);

        if (! is_array($format)) {
            throw new RuntimeException('DTR upload format is not configured for '.$campus->campus_name.'.');
        }

        $parser = (string) ($format['parser'] ?? 'flat');

        if ($parser === 'flat' && empty($format['columns'])) {
            throw new RuntimeException('DTR upload format is not configured for '.$campus->campus_name.'.');
        }

        if ($parser === 'san_mateo_card_report' && blank($format['parse_sheet_marker'] ?? null)) {
            throw new RuntimeException('DTR upload format is not configured for '.$campus->campus_name.'.');
        }

        if (blank($format['file_extension'] ?? null)) {
            throw new RuntimeException('DTR upload format is not configured for '.$campus->campus_name.'.');
        }

        return $format;
    }

    public static function acceptedExtension(Campus $campus): string
    {
        return (string) (self::campusFormat($campus)['file_extension'] ?? 'xls');
    }

    public static function fileTypeLabel(Campus $campus): string
    {
        return strtoupper(self::acceptedExtension($campus));
    }
}
