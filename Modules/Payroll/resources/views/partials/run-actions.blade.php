<section
    data-workforce-region
    data-refresh-scope="payroll-run-actions"
    data-refresh-url="{{ route('admin.payroll.runs.fragments.show', [
        'run' => $viewModel->summary['id'],
        'fragment' => 'actions',
        'company_id' => $companyId,
    ]) }}"
    data-company-id="{{ $companyId }}"
    data-entity-id="{{ $viewModel->summary['id'] }}"
    data-record-version="{{ $viewModel->summary['version'] }}"
    data-calculation-version="{{ $viewModel->summary['calculation_version'] }}"
>
  <i>/</i>

         <span>
            Payroll/resources/views/partials/run-actions
        </span>
    {{-- ================================================================
        PAYROLL RUN ACTIONS
    ================================================================ --}}
    <div class="flex flex-wrap gap-3">
        @forelse($viewModel->actions as $action)

            {{-- ========================================================
                AUTHORIZATION
            ======================================================== --}}
            @can(
                'payroll-run.' . $action['action'] . '-record',
                [$companyId, $viewModel->summary['id']]
            )
                <form
                    class="flex gap-2"
                    method="POST"
                    action="{{ route('admin.payroll.runs.mutate', [
                        'run' => $viewModel->summary['id'],
                        'action' => $action['action'],
                    ]) }}"
                    @if($action['confirmation'])
                        onsubmit="return confirm(@js($action['confirmation']))"
                    @endif
                >
                    @csrf

                    {{-- Company Context --}}
                    <input
                        type="hidden"
                        name="company_id"
                        value="{{ $companyId }}"
                    >

                    {{-- Optimistic Lock Version --}}
                    <input
                        type="hidden"
                        name="version"
                        value="{{ $viewModel->summary['version'] }}"
                    >

                    {{-- Idempotency --}}
                    <input
                        type="hidden"
                        name="idempotency_key"
                        value="{{ (string) \Illuminate\Support\Str::uuid() }}"
                    >


                    {{-- =================================================
                        REASON
                    ================================================= --}}
                    @if($action['requires_reason'])
                        <input
                            class="rounded border border-zinc-700 bg-zinc-950 px-3"
                            name="reason"
                            required
                            minlength="3"
                            maxlength="2000"
                            placeholder="Lý do từ chối"
                        >
                    @endif


                    {{-- =================================================
                        ACTION BUTTON
                    ================================================= --}}
                    <button
                        type="submit"
                        class="{{ $action['action'] === 'reject'
                            ? 'rounded border border-red-700 px-4 py-2 text-red-300'
                            : 'rounded bg-amber-500 px-4 py-2 font-semibold text-black'
                        }}"
                    >
                        {{ $action['label'] }}
                    </button>
                </form>
            @endcan

        @empty
            <p class="text-zinc-500">
                Không có action hợp lệ ở trạng thái hiện tại.
                Run chỉ cho phép xem và export.
            </p>
        @endforelse
    </div>

</section>