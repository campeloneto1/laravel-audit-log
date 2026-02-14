<?php

namespace Campelo\AuditLog\Notifications;

use Illuminate\Notifications\Notifiable;

class AuditLogNotifiable
{
    use Notifiable;

    public function routeNotificationForMail(): ?string
    {
        return config('audit-log.notifications.mail.to');
    }

    public function routeNotificationForSlack(): ?string
    {
        return config('audit-log.notifications.slack.webhook_url');
    }
}
