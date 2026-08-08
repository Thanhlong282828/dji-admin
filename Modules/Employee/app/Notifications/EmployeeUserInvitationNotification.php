<?php

namespace Modules\Employee\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class EmployeeUserInvitationNotification extends Notification
{
    public function __construct(private readonly string $token, private readonly int $employeeId) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Lời mời truy cập Schema HRM')
            ->greeting('Xin chào,')
            ->line('Bạn được mời kích hoạt tài khoản nhân viên trên Schema HRM.')
            ->action('Kích hoạt tài khoản', url('/employee-invitations/accept?token='.urlencode($this->token).'&employee='.$this->employeeId))
            ->line('Liên kết có hiệu lực trong 48 giờ và chỉ sử dụng một lần.');
    }
}
