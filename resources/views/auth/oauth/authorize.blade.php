<x-guest-layout>
    <h1 class="text-xl font-semibold">Cấp quyền ứng dụng</h1>
    <p class="mt-3 text-sm"><strong>{{ $client->name }}</strong> yêu cầu quyền truy cập tài khoản của bạn.</p>
    <ul class="mt-4 list-disc pl-5 text-sm space-y-1">@foreach ($scopes as $scope)<li>{{ $scope->description }}</li>@endforeach</ul>
    <div class="mt-6 flex gap-3">
        <form method="POST" action="{{ route('passport.authorizations.approve') }}">@csrf<input type="hidden" name="state" value="{{ $request->state }}"><input type="hidden" name="client_id" value="{{ $client->getKey() }}"><input type="hidden" name="auth_token" value="{{ $authToken }}"><x-primary-button>Cho phép</x-primary-button></form>
        <form method="POST" action="{{ route('passport.authorizations.deny') }}">@csrf @method('DELETE')<input type="hidden" name="state" value="{{ $request->state }}"><input type="hidden" name="client_id" value="{{ $client->getKey() }}"><input type="hidden" name="auth_token" value="{{ $authToken }}"><button class="rounded border px-4 py-2 text-sm">Từ chối</button></form>
    </div>
</x-guest-layout>
