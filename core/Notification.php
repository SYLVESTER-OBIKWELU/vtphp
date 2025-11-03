<?php

namespace Core;

/**
 * Notification System
 * Send notifications via multiple channels
 */
class Notification
{
    protected $notifiable;
    protected $channels = [];

    public function __construct($notifiable = null)
    {
        $this->notifiable = $notifiable;
    }

    /**
     * Send notification
     */
    public function send($notification)
    {
        $channels = $this->getChannels($notification);

        foreach ($channels as $channel) {
            $this->sendToChannel($channel, $notification);
        }

        return true;
    }

    /**
     * Get notification channels
     */
    protected function getChannels($notification)
    {
        if (method_exists($notification, 'via')) {
            return $notification->via();
        }

        return ['mail'];
    }

    /**
     * Send to specific channel
     */
    protected function sendToChannel($channel, $notification)
    {
        switch ($channel) {
            case 'mail':
                return $this->sendToMail($notification);
            
            case 'database':
                return $this->sendToDatabase($notification);
            
            case 'broadcast':
                return $this->sendToBroadcast($notification);
            
            case 'sms':
                return $this->sendToSms($notification);
            
            default:
                throw new \Exception("Unsupported notification channel: {$channel}");
        }
    }

    /**
     * Send via email
     */
    protected function sendToMail($notification)
    {
        if (method_exists($notification, 'toMail')) {
            $mailData = $notification->toMail($this->notifiable);
            
            if ($mailData instanceof \Core\Mail) {
                return $mailData->send();
            }

            // Build mail from data
            return Mail::make()
                ->to($this->notifiable->email ?? $this->notifiable)
                ->subject($mailData['subject'] ?? 'Notification')
                ->view($mailData['view'] ?? 'emails.notification', $mailData['data'] ?? [])
                ->send();
        }

        return false;
    }

    /**
     * Send to database
     */
    protected function sendToDatabase($notification)
    {
        if (!method_exists($notification, 'toArray')) {
            return false;
        }

        $data = $notification->toArray($this->notifiable);

        $db = Database::getInstance();
        $db->query(
            "INSERT INTO notifications (notifiable_type, notifiable_id, type, data, created_at) 
             VALUES (?, ?, ?, ?, ?)",
            [
                get_class($this->notifiable),
                $this->notifiable->id ?? 0,
                get_class($notification),
                json_encode($data),
                date('Y-m-d H:i:s')
            ]
        );

        return true;
    }

    /**
     * Send via broadcast
     */
    protected function sendToBroadcast($notification)
    {
        if (method_exists($notification, 'toBroadcast')) {
            $data = $notification->toBroadcast($this->notifiable);
            $channel = "user.{$this->notifiable->id}";
            
            return Broadcasting::send($channel, 'notification', $data);
        }

        return false;
    }

    /**
     * Send via SMS
     */
    protected function sendToSms($notification)
    {
        if (method_exists($notification, 'toSms')) {
            $smsData = $notification->toSms($this->notifiable);
            
            // Implement SMS sending via Twilio, Nexmo, etc.
            Log::info("SMS sent to {$this->notifiable->phone}: {$smsData['message']}");
            
            return true;
        }

        return false;
    }

    /**
     * Static send method
     */
    public static function sendTo($notifiable, $notification)
    {
        return (new static($notifiable))->send($notification);
    }

    /**
     * Create notification instance
     */
    public static function make($notifiable = null)
    {
        return new static($notifiable);
    }
}
