{{-- Clean markup for printing (no app layout / Tailwind overflow quirks). --}}
<div id="report-print-document" hidden>
    @include('payroll.reports._report-document-body', ['preview' => $preview])
</div>
