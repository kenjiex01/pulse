<div class="grid gap-3 sm:grid-cols-2">
    <div>
        <label for="sent-memo-type" class="form-label">Memo type</label>
        <select id="sent-memo-type" class="form-input w-full" data-sent-memo-type>
            <option value="">All memo types</option>
            @foreach ($memoForms as $form)
                <option value="{{ $form->company_document_form_id }}">{{ $form->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="sent-control-number" class="form-label">Control number</label>
        <input
            id="sent-control-number"
            type="search"
            class="form-input w-full"
            placeholder="2026-INV/MM-106"
            data-sent-control-number
            autocomplete="off"
        >
    </div>
</div>
