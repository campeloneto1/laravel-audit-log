<?php

namespace Campelo\AuditLog\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AuditLogErrorNotification extends Notification
{
    use Queueable;

    public function __construct(
        public array $errorData
    ) {}

    public function via($notifiable): array
    {
        $channels = config('audit-log.notifications.channels', ['mail']);

        if (is_string($channels)) {
            $channels = array_filter(array_map('trim', explode(',', $channels)));
        }

        $available = [];

        foreach ($channels as $channel) {
            if ($channel === 'mail' && config('audit-log.notifications.mail.to')) {
                $available[] = 'mail';
            }

            if ($channel === 'slack' && config('audit-log.notifications.slack.webhook_url')) {
                $available[] = 'slack';
            }
        }

        return $available;
    }

    public function toMail($notifiable): MailMessage
    {
        $appName = config('app.name', 'Laravel');
        $description = $this->errorData['description'] ?? 'Unknown error';
        $url = $this->errorData['url'] ?? 'N/A';
        $user = $this->errorData['user_name'] ?? 'Guest';
        $time = $this->errorData['performed_at'] ?? now();
        $file = ($this->errorData['metadata']['file'] ?? 'unknown') . ':' . ($this->errorData['metadata']['line'] ?? '?');
        $responseCode = $this->errorData['response_code'] ?? 500;

        return (new MailMessage)
            ->subject("[{$appName}] Error {$responseCode}: " . class_basename($this->errorData['metadata']['exception_class'] ?? 'Exception'))
            ->greeting('Error Alert')
            ->line('An error occurred in your application.')
            ->line("**Error:** {$description}")
            ->line("**URL:** {$url}")
            ->line("**User:** {$user}")
            ->line("**File:** {$file}")
            ->line("**Time:** {$time}")
            ->line('Check your logs for more details.');
    }

    public function toSlack($notifiable)
    {
        $appName = config('app.name', 'Laravel');
        $description = $this->errorData['description'] ?? 'Unknown error';
        $url = $this->errorData['url'] ?? 'N/A';
        $user = $this->errorData['user_name'] ?? 'Guest';
        $time = $this->errorData['performed_at'] ?? now();
        $file = ($this->errorData['metadata']['file'] ?? 'unknown') . ':' . ($this->errorData['metadata']['line'] ?? '?');
        $responseCode = $this->errorData['response_code'] ?? 500;
        $exceptionClass = class_basename($this->errorData['metadata']['exception_class'] ?? 'Exception');

        // Use SlackMessage if available, otherwise return array for webhook
        if (class_exists(\Illuminate\Notifications\Messages\SlackMessage::class)) {
            return (new \Illuminate\Notifications\Messages\SlackMessage)
                ->error()
                ->content("Error in {$appName}")
                ->attachment(function ($attachment) use ($description, $url, $user, $file, $time, $responseCode, $exceptionClass) {
                    $attachment
                        ->title("[{$responseCode}] {$exceptionClass}")
                        ->content($description)
                        ->fields([
                            'URL' => $url,
                            'User' => $user,
                            'File' => $file,
                            'Time' => (string) $time,
                        ])
                        ->color('danger');
                });
        }

        // Fallback: return data array for custom handling
        return [
            'text' => "Error in {$appName}: [{$responseCode}] {$exceptionClass}",
            'attachments' => [
                [
                    'color' => 'danger',
                    'title' => $description,
                    'fields' => [
                        ['title' => 'URL', 'value' => $url, 'short' => false],
                        ['title' => 'User', 'value' => $user, 'short' => true],
                        ['title' => 'File', 'value' => $file, 'short' => true],
                        ['title' => 'Time', 'value' => (string) $time, 'short' => true],
                    ],
                ],
            ],
        ];
    }

    public function toArray($notifiable): array
    {
        return $this->errorData;
    }
}
