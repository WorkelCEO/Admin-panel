<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * EmailLog Model
 * 
 * This model represents email log data from the external API.
 * It's not a traditional Eloquent model backed by a database table,
 * but rather a data transfer object for API responses.
 */
class EmailLog extends Model
{
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'sender_email',
        'sender_name',
        'recipient_email',
        'recipient_name',
        'subject',
        'status',
        'error_message',
        'email_type',
        'metadata',
        'sent_at',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Get the table associated with the model.
     * This model doesn't use a database table.
     *
     * @return string
     */
    public function getTable()
    {
        return 'email_logs';
    }

    /**
     * Create a new instance from API response data.
     *
     * @param array $data
     * @return static
     */
    public static function fromApi(array $data): static
    {
        $instance = new static();
        $instance->forceFill($data);
        $instance->exists = true;
        
        return $instance;
    }

    /**
     * Get the status badge color.
     *
     * @return string
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            'success' => 'success',
            'error' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get the email type label.
     *
     * @return string
     */
    public function getEmailTypeLabel(): string
    {
        return match ($this->email_type) {
            'verification' => 'Verification',
            'project_invitation' => 'Project Invitation',
            'workspace_invitation' => 'Workspace Invitation',
            'workspace_invitation_by_email' => 'Workspace Invitation (Email)',
            'forgot_password' => 'Password Reset',
            'unread_chat' => 'Unread Chat',
            'user_registration_notification' => 'Registration Notification',
            default => str_replace('_', ' ', ucfirst($this->email_type)),
        };
    }

    /**
     * Check if the email was successfully sent.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if the email failed to send.
     *
     * @return bool
     */
    public function isError(): bool
    {
        return $this->status === 'error';
    }
}

