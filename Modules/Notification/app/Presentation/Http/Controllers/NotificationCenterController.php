<?php

namespace Modules\Notification\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Notification\Application\Commands\CreateNotificationTemplate\CreateNotificationTemplateCommand;
use Modules\Notification\Application\Commands\MarkAllNotificationsRead\MarkAllNotificationsReadCommand;
use Modules\Notification\Application\Commands\MarkNotificationRead\MarkNotificationReadCommand;
use Modules\Notification\Application\Commands\PublishNotificationTemplate\PublishNotificationTemplateCommand;
use Modules\Notification\Application\Commands\RetryNotificationDelivery\RetryNotificationDeliveryCommand;
use Modules\Notification\Application\Commands\UpdateNotificationPreference\UpdateNotificationPreferenceCommand;
use Modules\Notification\Application\Queries\NotificationDeliveryHistory\NotificationDeliveryHistoryQuery;
use Modules\Notification\Application\Queries\NotificationDetail\NotificationDetailQuery;
use Modules\Notification\Application\Queries\NotificationInbox\NotificationInboxQuery;
use Modules\Notification\Application\Queries\NotificationTemplateList\NotificationTemplateListQuery;
use Modules\Notification\Application\Queries\UnreadNotificationCount\UnreadNotificationCountQuery;
use Modules\Notification\Policies\NotificationPolicy;
use Modules\Notification\Presentation\Http\Requests\NotificationCenterRequest;

final readonly class NotificationCenterController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries, private NotificationPolicy $policy) {}

    public function index(Request $r): View
    {
        [$c,$u] = $this->context($r, 'notification.view');

        return view('notification::inbox', ['vm' => $this->queries->ask(new NotificationInboxQuery($c, $u, $r->string('filter')->toString() ?: null, $r->string('category')->toString() ?: null, max(1, $r->integer('page', 1)), 20)), 'companyId' => $c]);
    }

    public function show(Request $r, int $message): View
    {
        [$c,$u] = $this->context($r, 'notification.view');

        return view('notification::show', ['vm' => $this->queries->ask(new NotificationDetailQuery($c, $u, $message)), 'companyId' => $c]);
    }

    public function unread(Request $r): JsonResponse
    {
        [$c,$u] = $this->context($r, 'notification.view');

        return response()->json(['unread' => $this->queries->ask(new UnreadNotificationCountQuery($c, $u))]);
    }

    public function markRead(NotificationCenterRequest $r, int $message): RedirectResponse
    {
        [$c,$u] = $this->context($r, 'notification.view');
        $this->commands->dispatch(new MarkNotificationReadCommand($c, $u, $message));

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAll(NotificationCenterRequest $r): RedirectResponse
    {
        [$c,$u] = $this->context($r, 'notification.view');
        $this->commands->dispatch(new MarkAllNotificationsReadCommand($c, $u));

        return back()->with('success', 'All notifications marked as read.');
    }

    public function templates(Request $r): View
    {
        [$c] = $this->context($r, 'notification.template.manage');

        return view('notification::templates', ['vm' => $this->queries->ask(new NotificationTemplateListQuery($c)), 'companyId' => $c]);
    }

    public function storeTemplate(NotificationCenterRequest $r): RedirectResponse
    {
        [$c,$u] = $this->context($r, 'notification.template.manage');
        $this->commands->dispatch(new CreateNotificationTemplateCommand($c, $u, $r->text('code'), $r->text('name'), $r->text('category'), $r->text('subject'), $r->text('body'), $r->boolean('mandatory')));

        return back()->with('success', 'Template created.');
    }

    public function publishTemplate(NotificationCenterRequest $r, int $template): RedirectResponse
    {
        [$c,$u] = $this->context($r, 'notification.template.manage');
        $this->commands->dispatch(new PublishNotificationTemplateCommand($c, $template, $u));

        return back()->with('success', 'Template published and immutable.');
    }

    public function deliveries(Request $r): View
    {
        [$c] = $this->context($r, 'notification.delivery.view');

        return view('notification::deliveries', ['vm' => $this->queries->ask(new NotificationDeliveryHistoryQuery($c)), 'companyId' => $c]);
    }

    public function retry(NotificationCenterRequest $r, int $delivery): RedirectResponse
    {
        [$c] = $this->context($r, 'notification.retry');
        $this->commands->dispatch(new RetryNotificationDeliveryCommand($c, $delivery));

        return back()->with('success', 'Failed channel retried.');
    }

    public function preference(NotificationCenterRequest $r): RedirectResponse
    {
        [$c,$u] = $this->context($r, 'notification.preference.manage');
        $this->commands->dispatch(new UpdateNotificationPreferenceCommand($c, $u, $r->text('category', '*'), $r->boolean('database_enabled'), $r->boolean('broadcast_enabled'), $r->boolean('email_enabled')));

        return back()->with('success', 'Preferences updated.');
    }

    /** @return array{int,int} */
    private function context(Request $r, string $permission): array
    {
        $c = $r->integer('company_id');
        abort_if($c < 1, 422);
        $user = $r->user();
        abort_if($user === null, 403);
        $this->policy->authorize($user, $c, $permission);
        $id = $user->getAuthIdentifier();
        abort_unless(is_numeric($id), 403);

        return [$c, (int) $id];
    }
}
