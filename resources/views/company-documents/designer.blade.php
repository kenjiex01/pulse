@extends('layouts.app')

@section('title', 'Design: '.$form->name.' — '.config('app.name'))

@section('content')
    @include('partials.flash')

    <div
        class="company-document-designer -mx-4 -mt-4 flex flex-col bg-gray-100 sm:-mx-6 sm:-mt-6"
        style="height: calc(100vh - 3.5rem);"
        data-company-document-designer
        data-save-elements-url="{{ route('company-documents.designer.elements', $form) }}"
        data-save-approvals-url="{{ route('company-documents.designer.approvals', $form) }}"
        data-upload-image-url="{{ route('company-documents.designer.upload-image', $form) }}"
        data-asset-url="{{ route('company-documents.designer.asset', $form) }}"
        data-back-url="{{ route('company-documents.index') }}"
        data-form-name="{{ $form->name }}"
        data-form-code="{{ $form->code }}"
        data-form-description="{{ $form->description }}"
        data-submit-label="{{ $form->submit_label ?: 'Submit' }}"
        data-initial-elements='@json($initialElements)'
        data-initial-form-settings='@json($initialFormSettings)'
        data-initial-steps='@json($initialSteps)'
        data-palette='@json($palette)'
        data-merge-tag-samples='@json($mergeTagSamples)'
        data-merge-tag-labels='@json($mergeTagLabels)'
    >
        {{-- Top bar --}}
        <div class="shrink-0 bg-gradient-to-r from-[#0B318F] via-[#00A3E6] to-[#00A3E6] shadow-md">
            <div class="flex items-center gap-2 px-2 py-2 sm:px-3">
                <a href="{{ route('company-documents.index') }}" class="shrink-0 rounded-lg p-2 text-white/90 hover:bg-white/10 hover:text-white" title="Back">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </a>

                <div class="hidden min-w-0 flex-1 border-r border-white/20 pr-3 sm:block">
                    <p class="truncate text-sm font-semibold text-white">{{ $form->name }}</p>
                    <p class="truncate font-mono text-[10px] text-white/70">{{ $form->code }}</p>
                </div>

                <div class="flex h-10 shrink-0 items-center">
                    <button type="button" class="cd-designer-top-tab relative inline-flex h-10 items-center gap-2 px-3 text-xs font-semibold uppercase tracking-wider text-white sm:px-5" data-designer-tab="build">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        <span class="hidden sm:inline">Build</span>
                    </button>
                    @if ($form->supportsApprovalRouting())
                    <button type="button" class="cd-designer-top-tab relative inline-flex h-10 items-center gap-2 px-3 text-xs font-semibold uppercase tracking-wider text-white/85 hover:bg-white/10 sm:px-5" data-designer-tab="approvals">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span class="hidden sm:inline">Approvals</span>
                    </button>
                    @endif
                </div>

                <div class="ml-auto flex shrink-0 items-center gap-2">
                    <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-1.5 text-sm font-semibold text-[#00A3E6] shadow-sm hover:bg-blue-50" data-save-active>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                        Save
                    </button>
                </div>
            </div>
        </div>

        {{-- Build tab --}}
        <div class="flex min-h-0 flex-1 overflow-hidden" data-designer-panel="build">
            <aside class="cd-designer-palette flex w-[280px] shrink-0 flex-col bg-[#252b3b] text-white sm:w-[320px]" data-designer-palette>
                <div class="flex items-center justify-between border-b border-white/[0.08] px-4 py-3.5">
                    <h2 class="text-[15px] font-semibold tracking-tight">Form Elements</h2>
                </div>
                <div class="flex border-b border-white/[0.08] bg-[#252b3b]" data-palette-tabs></div>
                <div class="border-b border-white/[0.06] px-3 py-2">
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-white/25" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="search" placeholder="Search elements..." class="h-8 w-full rounded-md border border-white/[0.08] bg-[#1a1f2e] pl-8 pr-2 text-xs text-white placeholder:text-white/30 focus:border-[#00A3E6]/50 focus:outline-none focus:ring-1 focus:ring-[#00A3E6]/30" data-palette-search>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto" data-palette-list></div>
            </aside>

            <div class="relative flex min-w-0 flex-1 overflow-hidden">
                <div class="min-w-0 flex-1 overflow-y-auto p-4 sm:p-6" data-designer-canvas-wrap>
                    <div class="mx-auto w-full max-w-[816px]">
                        <div class="rounded-xl border border-gray-200 bg-white shadow-sm" data-form-card>
                            <div class="px-5 pt-5 sm:px-6 sm:pt-6">
                                <h2 class="text-center text-xl font-semibold text-gray-900" data-form-title>{{ $form->name }}</h2>
                                @if ($form->description)
                                    <p class="mt-1 whitespace-pre-wrap text-center text-sm text-gray-600" data-form-description>{{ $form->description }}</p>
                                @endif
                                <p class="mt-2 text-center text-xs font-medium text-[#0B318F]">Legal size bond paper (8.5 × 14 in)</p>
                                <div class="mt-3 flex items-center justify-center gap-2">
                                    <label for="canvas-background-color" class="text-xs font-medium text-gray-600">Canvas background</label>
                                    <input
                                        id="canvas-background-color"
                                        type="color"
                                        class="h-8 w-12 cursor-pointer rounded border border-gray-300 bg-white p-0.5"
                                        data-canvas-background-color
                                        value="{{ $initialFormSettings['canvas_background_color'] }}"
                                    >
                                </div>
                            </div>
                            <div class="bg-gray-100 p-4" data-designer-canvas></div>
                        </div>
                    </div>
                </div>

                <aside class="hidden w-[320px] shrink-0 flex-col overflow-hidden border-l border-white/10 bg-[#2c3345] text-white sm:flex" data-properties-panel>
                    <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                        <h3 class="text-sm font-semibold" data-properties-title>Properties</h3>
                        <button type="button" class="rounded p-1 text-white/50 hover:bg-white/10 hover:text-white" data-close-properties aria-label="Close">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto" data-properties-body></div>
                </aside>
            </div>
        </div>

        {{-- Approvals tab --}}
        @if ($form->supportsApprovalRouting())
        <div class="hidden min-h-0 flex-1 flex-col overflow-y-auto p-4 sm:p-6" data-designer-panel="approvals">
            <div class="mx-auto w-full max-w-4xl space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-600">Define who approves each memo after submission.</p>
                    <div class="flex gap-2">
                        <button type="button" class="btn-secondary" data-add-approval-step>Add step</button>
                        <button type="button" class="btn-primary" data-save-approvals>Save approvals</button>
                    </div>
                </div>
                <div class="space-y-3" data-approval-steps></div>
                <template data-approval-step-template>
                    @include('company-documents._approval-step-row', [
                        'stepIndex' => '__INDEX__',
                        'approvalModes' => $approvalModes,
                        'roles' => $roles,
                        'users' => $users,
                    ])
                </template>
            </div>
        </div>
        @endif
    </div>
@endsection
