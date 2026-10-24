@extends('layouts.admin')
@section('title', 'API Documentation')
@section('content')
<style>
.adoc{--gold:#d4af37;color:#edf0f6;width:100%;max-width:1120px;margin:auto}.adoc *{box-sizing:border-box}.adoc-hero,.adoc-card{background:linear-gradient(145deg,#151920,#090b0f);border:1px solid #2d3139;border-radius:20px;box-shadow:0 20px 55px #0005}.adoc-hero{padding:clamp(20px,4vw,36px)}.adoc-kicker{color:var(--gold);font-size:12px;letter-spacing:.16em;text-transform:uppercase}.adoc h1{font-size:clamp(30px,5vw,52px);margin:8px 0}.adoc-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-top:18px}.adoc-card{padding:22px}.adoc-card p{color:#b9bec8;line-height:1.65}.adoc-action{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 16px;border:1px solid #9c7c27;border-radius:12px;color:#f4d87d;text-decoration:none}.adoc-action:focus-visible{outline:3px solid #f4d87d;outline-offset:3px}@media(max-width:640px){.adoc-grid{grid-template-columns:1fr}.adoc-hero,.adoc-card{padding:18px}}
</style>
<main class="adoc">
 <header class="adoc-hero"><span class="adoc-kicker">Protected developer portal</span><h1>API Documentation</h1><p>Điểm vào có kiểm soát cho Swagger UI và OpenAPI JSON. Trang này chỉ đọc contract; không tự gửi business request.</p></header>
 <section class="adoc-grid" aria-label="API documentation resources">
  <article class="adoc-card"><h2>Swagger UI</h2><p>Duyệt 24 operation được quản trị, schema và Sanctum Bearer contract trong giao diện tương tác được bảo vệ.</p><a class="adoc-action" href="{{ route('l5-swagger.default.api') }}">Mở Swagger UI</a></article>
  <article class="adoc-card"><h2>OpenAPI JSON</h2><p>Tải specification đã sinh để kiểm tra contract hoặc dùng với công cụ nội bộ. Endpoint áp dụng cùng permission.</p><a class="adoc-action" href="{{ url('/docs') }}">Mở OpenAPI JSON</a></article>
 </section>
</main>
@endsection
