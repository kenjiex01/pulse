@php
    $hasAccess = $permissions !== null;
    $fullControl = $hasAccess && $permissions->full_control;
@endphp

<td class="text-center">@if ($fullControl || ($hasAccess && $permissions->can_add))<span class="badge-success">Yes</span>@else<span class="badge-muted">No</span>@endif</td>
<td class="text-center">@if ($fullControl || ($hasAccess && $permissions->can_edit))<span class="badge-success">Yes</span>@else<span class="badge-muted">No</span>@endif</td>
<td class="text-center">@if ($fullControl || ($hasAccess && $permissions->can_update))<span class="badge-success">Yes</span>@else<span class="badge-muted">No</span>@endif</td>
<td class="text-center">@if ($fullControl || ($hasAccess && $permissions->can_delete))<span class="badge-success">Yes</span>@else<span class="badge-muted">No</span>@endif</td>
<td class="text-center">@if ($fullControl)<span class="badge-success">Yes</span>@else<span class="badge-muted">No</span>@endif</td>
