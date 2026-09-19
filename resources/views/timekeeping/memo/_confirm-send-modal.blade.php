@include('partials.modal', [
    'id' => 'memo-send-confirm-modal',
    'title' => 'Confirm Send',
    'panelClass' => 'max-w-md',
    'body' => '
        <p class="text-sm text-gray-600" data-memo-send-confirm-message></p>
        <div class="mt-4 flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
            <button type="button" class="btn-secondary w-full sm:w-auto" data-memo-send-confirm-cancel data-modal-close>Cancel</button>
            <button type="button" class="btn-primary w-full sm:w-auto" data-memo-send-confirm-proceed>Send</button>
        </div>
    ',
])
