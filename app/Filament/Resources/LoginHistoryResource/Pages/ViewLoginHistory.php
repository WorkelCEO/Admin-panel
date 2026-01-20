<?php

namespace App\Filament\Resources\LoginHistoryResource\Pages;

use App\Filament\Resources\LoginHistoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewLoginHistory extends ViewRecord
{
    protected static string $resource = LoginHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Read-only view, no actions
        ];
    }

    public static function canEdit($record): bool
    {
        return false; // Login history is read-only
    }
    
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Ensure date fields are properly formatted and null values are handled
        // DateTimePicker can accept Carbon instances or date strings, but not 'N/A'
        if (isset($data['logged_in_at'])) {
            if (empty($data['logged_in_at']) || 
                $data['logged_in_at'] === 'N/A' || 
                $data['logged_in_at'] === '' ||
                !is_string($data['logged_in_at']) ||
                !preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$data['logged_in_at'])) {
                $data['logged_in_at'] = null;
            } else {
                try {
                    // Try to parse as Carbon instance for DateTimePicker
                    $data['logged_in_at'] = \Carbon\Carbon::parse($data['logged_in_at']);
                } catch (\Carbon\Exceptions\InvalidFormatException $e) {
                    $data['logged_in_at'] = null;
                } catch (\Exception $e) {
                    $data['logged_in_at'] = null;
                }
            }
        }
        
        if (isset($data['logged_out_at'])) {
            if (empty($data['logged_out_at']) || 
                $data['logged_out_at'] === 'N/A' || 
                $data['logged_out_at'] === '' ||
                !is_string($data['logged_out_at']) ||
                !preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$data['logged_out_at'])) {
                $data['logged_out_at'] = null;
            } else {
                try {
                    // Try to parse as Carbon instance for DateTimePicker
                    $data['logged_out_at'] = \Carbon\Carbon::parse($data['logged_out_at']);
                } catch (\Carbon\Exceptions\InvalidFormatException $e) {
                    $data['logged_out_at'] = null;
                } catch (\Exception $e) {
                    $data['logged_out_at'] = null;
                }
            }
        }
        
        // Ensure other fields have proper defaults (non-date fields can use 'N/A')
        $data['user_email'] = $data['user_email'] ?? 'N/A';
        $data['ip_address'] = $data['ip_address'] ?? 'N/A';
        $data['device_name'] = $data['device_name'] ?? 'N/A';
        $data['location'] = $data['location'] ?? 'N/A';
        $data['action'] = $data['action'] ?? 'N/A';
        $data['failure_reason'] = $data['failure_reason'] ?? null;
        
        return $data;
    }

    protected function resolveRecord(int|string $record): Model
    {
        // The record is passed as base64-encoded JSON from the ViewAction
        try {
            $decoded = json_decode(base64_decode($record), true);
            
            if (!is_array($decoded)) {
                throw new \Exception('Invalid record data');
            }
            
            // Extract the actual data if it's nested
            $originalData = $decoded['data'] ?? $decoded;
            
            // Map API fields to expected form fields (same mapping as in ListLoginHistory)
            // Extract user email from nested user object
            $userEmail = null;
            if (isset($originalData['user']) && is_array($originalData['user'])) {
                $userEmail = $originalData['user']['email'] ?? null;
            } elseif (isset($originalData['user_email'])) {
                $userEmail = $originalData['user_email'];
            }
            
            // Extract device name from user agent (if not already extracted)
            $deviceName = $originalData['device_name'] ?? null;
            if (empty($deviceName) && !empty($originalData['user_agent'])) {
                $deviceName = $this->extractDeviceName($originalData['user_agent']);
            }
            
            $data = [
                'id' => $originalData['id'] ?? md5(json_encode($originalData)),
                'user_id' => $originalData['user_id'] ?? null,
                'user_email' => $userEmail,
                'ip_address' => $originalData['ip_address'] ?? null,
                'user_agent' => $originalData['user_agent'] ?? null,
                'logged_in_at' => $originalData['created_at'] ?? null, // API uses 'created_at' not 'logged_in_at'
                'logged_out_at' => null, // API doesn't provide logged_out_at
                'device_name' => $deviceName,
                'location' => null, // API doesn't provide location
                'is_active' => $originalData['successful'] ?? false,
                'action' => $originalData['action'] ?? null,
                'successful' => $originalData['successful'] ?? false,
                'failure_reason' => $originalData['failure_reason'] ?? null,
                'api_version' => $originalData['api_version'] ?? null,
            ];
            
            // Clean up date fields - ensure null values are preserved, never use 'N/A'
            if (empty($data['logged_in_at']) || 
                $data['logged_in_at'] === 'N/A' || 
                $data['logged_in_at'] === '' ||
                !is_string($data['logged_in_at']) ||
                !preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$data['logged_in_at'])) {
                $data['logged_in_at'] = null;
            } else {
                // Validate it's a parseable date
                try {
                    \Carbon\Carbon::parse($data['logged_in_at']);
                    $data['logged_in_at'] = (string) $data['logged_in_at'];
                } catch (\Exception $e) {
                    $data['logged_in_at'] = null;
                }
            }
            
            if (empty($data['logged_out_at']) || 
                $data['logged_out_at'] === 'N/A' || 
                $data['logged_out_at'] === '' ||
                !is_string($data['logged_out_at']) ||
                !preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$data['logged_out_at'])) {
                $data['logged_out_at'] = null;
            } else {
                // Validate it's a parseable date
                try {
                    \Carbon\Carbon::parse($data['logged_out_at']);
                    $data['logged_out_at'] = (string) $data['logged_out_at'];
                } catch (\Exception $e) {
                    $data['logged_out_at'] = null;
                }
            }
            
            // Create a model-like instance from the decoded data
            return new class($data) extends Model {
                protected $guarded = [];
                public $timestamps = false;
                public $incrementing = false;
                protected $primaryKey = 'id';
                
                // Don't cast dates automatically - handle them manually
                protected $casts = [];
                
                public function __construct(array $attributes = [])
                {
                    parent::__construct();
                    $this->fill($attributes);
                    // Ensure ID is set
                    if (isset($attributes['id'])) {
                        $this->setAttribute('id', $attributes['id']);
                    }
                }
                
                public function getRouteKeyName(): string
                {
                    return 'id';
                }
                
                // Override date accessors to handle null values properly and prevent Carbon parsing errors
                public function getAttribute($key)
                {
                    // Handle date fields first - ensure they're never 'N/A'
                    if (in_array($key, ['logged_in_at', 'logged_out_at'])) {
                        $value = parent::getAttribute($key);
                        
                        // Always return null for empty, 'N/A', or invalid values
                        if (empty($value) || $value === 'N/A' || $value === '' || $value === false) {
                            return null;
                        }
                        
                        // Return as string - Filament will parse it when needed
                        return is_string($value) ? $value : (string) $value;
                    }
                    
                    return parent::getAttribute($key);
                }
                
                // Override to prevent automatic date casting
                protected function castAttribute($key, $value)
                {
                    // Never cast date fields automatically - return as-is
                    if (in_array($key, ['logged_in_at', 'logged_out_at'])) {
                        // If it's null, empty, or 'N/A', return null
                        if (empty($value) || $value === 'N/A' || $value === '') {
                            return null;
                        }
                        // Return as string - don't let Eloquent auto-cast to Carbon
                        return is_string($value) ? $value : (string) $value;
                    }
                    
                    return parent::castAttribute($key, $value);
                }
            };
        } catch (\Exception $e) {
            // Fallback to empty model if decoding fails
            return new class extends Model {
                protected $guarded = [];
                public $timestamps = false;
                public $incrementing = false;
            };
        }
    }
    
    /**
     * Extract device name from user agent string
     */
    protected function extractDeviceName(?string $userAgent): ?string
    {
        if (empty($userAgent)) {
            return null;
        }
        
        // Simple device extraction from user agent
        $userAgentLower = strtolower($userAgent);
        
        if (str_contains($userAgentLower, 'mobile') || str_contains($userAgentLower, 'android') || str_contains($userAgentLower, 'iphone') || str_contains($userAgentLower, 'ipad')) {
            return 'Mobile';
        }
        
        if (str_contains($userAgentLower, 'windows')) {
            return 'Windows';
        }
        
        if (str_contains($userAgentLower, 'macintosh') || str_contains($userAgentLower, 'mac os') || str_contains($userAgentLower, 'darwin')) {
            return 'Mac';
        }
        
        if (str_contains($userAgentLower, 'linux')) {
            return 'Linux';
        }
        
        return 'Unknown';
    }
}