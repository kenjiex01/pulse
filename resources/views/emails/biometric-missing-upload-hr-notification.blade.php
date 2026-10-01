<x-mail::message>
# Biometric Collector — S3 status

**Date:** {{ $referenceDateLabel }}

@if (count($missingCampuses) > 0)
The following active campus(es) have **no biometric log upload to S3 today** (same list as **No upload today** on the People360 dashboard):

@foreach ($missingCampuses as $campus)
- {{ $campus['campus_name'] }} ({{ $campus['campus_code'] }})
@endforeach
@else
All **{{ $activeCampusCount }}** active campus(es) uploaded biometric logs to S3 today.
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
