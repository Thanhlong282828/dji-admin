<section
    class="overflow-x-auto rounded-xl border border-zinc-800"
    data-workforce-region
    data-refresh-scope="payroll-run-items"
    data-refresh-url="{{ route('admin.payroll.runs.fragments.show', [
        'run' => $viewModel->summary['id'],
        'fragment' => 'items',
        'company_id' => $companyId,
    ]) }}"
    data-company-id="{{ $companyId }}"
    data-entity-id="{{ $viewModel->summary['id'] }}"
    data-record-version="{{ $viewModel->summary['version'] }}"
    data-calculation-version="{{ $viewModel->summary['calculation_version'] }}"
>

    {{-- ================================================================
        PAYROLL RUN ITEMS
    ================================================================ --}}
    <table class="w-full text-left">
        <thead>
            <tr>
                <th class="p-3">
                    Employee
                </th>

                <th>
                    Foundation Gross
                </th>

                <th>
                    Foundation Deduction
                </th>

                <th>
                    Foundation Net
                </th>

                <th>
                    Status
                </th>

                <th>
                    Actions
                </th>
            </tr>
        </thead>

        <tbody>
            @forelse($viewModel->items as $item)
                <tr class="border-t border-zinc-800">

                    {{-- Employee --}}
                    <td class="p-3">
                        {{ $item['employee_number'] }}
                        ·
                        {{ $item['employee_name'] }}
                    </td>


                    {{-- Foundation Gross --}}
                    <td>
                        {{ number_format($item['gross_minor']) }}
                    </td>


                    {{-- Foundation Deduction --}}
                    <td>
                        {{ number_format($item['deduction_minor']) }}
                    </td>


                    {{-- Foundation Net --}}
                    <td>
                        {{ number_format($item['net_minor']) }}
                    </td>


                    {{-- Status --}}
                    <td>
                        {{ $item['status'] }}
                    </td>


                    {{-- Actions --}}
                    <td>
                        <a
                            class="text-amber-500"
                            href="{{ route('admin.payroll.runs.items.show', [
                                'run' => $viewModel->summary['id'],
                                'item' => $item['id'],
                                'company_id' => $companyId,
                            ]) }}"
                        >
                            Detail
                        </a>
                    </td>
                </tr>

            @empty
                <tr>
                    <td
                        colspan="6"
                        class="p-8 text-center text-zinc-500"
                    >
                        Payroll Run chưa có kết quả tính toán.
                        Hãy Calculate để tạo Payroll Items.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

</section>