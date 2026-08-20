<div class="compensation-reference-content">
    <p class="muted">Dải tham chiếu synthetic cho local/demo, không phải lương luật định hoặc Payroll Item đã finalized.</p>
    <div class="table-scroll"><table><thead><tr><th>Phòng ban</th><th>Chức vụ</th><th>Level</th><th>Lương cứng</th><th>KPI mục tiêu</th><th>Phụ cấp</th><th>Target Gross</th></tr></thead><tbody>
    @foreach($rows as $row)<tr><td>{{ $row['unit_name'] }}</td><td>{{ $row['position_title'] }}</td><td>Level {{ $row['career_level'] }}</td><td>{{ number_format($row['base_salary_minor'],0,',','.') }} ₫</td><td>{{ number_format($row['kpi_target_minor'],0,',','.') }} ₫</td><td>{{ number_format($row['fixed_allowance_minor'],0,',','.') }} ₫</td><td><strong>{{ number_format($row['target_gross_minor'],0,',','.') }} ₫</strong></td></tr>@endforeach
    </tbody></table></div>
    <div class="notice"><strong>Ý nghĩa:</strong> Lương cứng là fixed/base contractual compensation; KPI là variable target; phụ cấp gắn với vai trò. Target Gross = các target trên, không phải thực nhận. Payroll thực tế tiếp tục áp dụng attendance, overtime, bonus, insurance, tax, adjustment và deduction theo luồng Salary assignment → Payroll Input → Calculation → Breakdown → Net Pay.</div>
</div>
