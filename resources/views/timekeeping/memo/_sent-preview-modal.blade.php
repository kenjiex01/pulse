@include('partials.modal', [
    'id' => 'memo-preview-modal',
    'title' => 'Sent memo',
    'description' => trim($employee->full_name.' · '.$form->name.($control_no !== '' ? ' · '.$control_no : '')),
    'open' => true,
    'panelClass' => 'max-w-4xl modal-panel-document-preview',
    'bodyClass' => 'modal-body-document-preview',
    'body' => view('timekeeping.memo._sent-preview-content', [
        'employee' => $employee,
        'form' => $form,
        'submission' => $submission,
        'control_no' => $control_no,
        'violation_label' => $violation_label,
        'sent_at' => $sent_at,
    ])->render(),
])
