<?php

namespace App\Repositories;

use App\DTOs\AdminAuthResponse;
use App\DTOs\ApiResponse;
use App\DTOs\BackupResponse;
use App\DTOs\SystemHealthResponse;
use App\DTOs\SystemInfoResponse;
use App\DTOs\SystemStatsResponse;
use App\DTOs\UserResponse;
use App\Services\AdminActivityLogService;
use App\Services\AdminApiService;
use App\Services\AdminBackupService;
use App\Services\AdminLoginHistoryService;
use App\Services\AdminSettingsService;
use App\Services\AdminSystemService;
use App\Services\AdminUserManagementService;

/**
 * Admin Repository - Unified interface for all admin API operations
 */
class AdminRepository
{
    public function __construct(
        private AdminApiService $authService,
        private AdminUserManagementService $userService,
        private AdminBackupService $backupService,
        private AdminSystemService $systemService,
        private AdminActivityLogService $activityService,
        private AdminSettingsService $settingsService,
        private AdminLoginHistoryService $loginHistoryService
    ) {
    }

    // Authentication methods
    public function login(string $email, string $password, ?string $deviceName = null): AdminAuthResponse
    {
        return $this->authService->login($email, $password, $deviceName);
    }

    public function logout(bool $revokeAll = false): ApiResponse
    {
        return $this->authService->logout($revokeAll);
    }

    public function refreshToken(): AdminAuthResponse
    {
        return $this->authService->refreshToken();
    }

    public function getCurrentAdmin(): ApiResponse
    {
        return $this->authService->getCurrentAdmin();
    }

    public function isTokenExpired(): bool
    {
        return $this->authService->isTokenExpired();
    }

    public function getStoredToken(): ?string
    {
        return $this->authService->getStoredToken();
    }

    // User management methods
    public function getUsers(array $params = []): array
    {
        return $this->userService->getUsers($params);
    }

    public function getUser(string $userId): ?UserResponse
    {
        return $this->userService->getUser($userId);
    }

    public function updateUser(string $userId, array $data): ?UserResponse
    {
        return $this->userService->updateUser($userId, $data);
    }

    public function changeUserRole(string $userId, string $role): ApiResponse
    {
        return $this->userService->changeUserRole($userId, $role);
    }

    public function suspendUser(string $userId, string $reason, ?string $suspendedUntil = null): ApiResponse
    {
        return $this->userService->suspendUser($userId, $reason, $suspendedUntil);
    }

    public function unsuspendUser(string $userId): ApiResponse
    {
        return $this->userService->unsuspendUser($userId);
    }

    public function deleteUser(string $userId): bool
    {
        return $this->userService->deleteUser($userId);
    }

    public function getUserActivity(string $userId, array $params = []): array
    {
        return $this->userService->getUserActivity($userId, $params);
    }

    public function getUserLoginHistory(string $userId, array $params = []): array
    {
        return $this->loginHistoryService->getUserLoginHistory($userId, $params);
    }

    // Login history methods
    public function getLoginHistory(array $params = []): array
    {
        return $this->loginHistoryService->getLoginHistory($params);
    }

    public function getLoginHistoryForUser(string $userId, array $params = []): array
    {
        return $this->loginHistoryService->getUserLoginHistory($userId, $params);
    }

    public function getLoginStatistics(): array
    {
        return $this->loginHistoryService->getLoginStatistics();
    }

    public function cleanupLoginHistory(?string $olderThan = null): ApiResponse
    {
        return $this->loginHistoryService->cleanupLoginHistory($olderThan);
    }

    // Backup management methods
    public function getBackups(): array
    {
        return $this->backupService->getBackups();
    }

    public function createBackup(?string $description = null, bool $includeFiles = false): ?BackupResponse
    {
        return $this->backupService->createBackup($description, $includeFiles);
    }

    public function downloadBackup(string $backupId): ApiResponse
    {
        return $this->backupService->downloadBackup($backupId);
    }

    public function importBackup(string $backupId, bool $confirm = false, bool $restoreFiles = false): ApiResponse
    {
        return $this->backupService->importBackup($backupId, $confirm, $restoreFiles);
    }

    public function deleteBackup(string $backupId): bool
    {
        return $this->backupService->deleteBackup($backupId);
    }

    public function cleanupEmailLogs(string $olderThan = '30 days'): ApiResponse
    {
        return $this->backupService->cleanupEmailLogs($olderThan);
    }

    // System information methods
    public function getSystemInfo(): ?SystemInfoResponse
    {
        return $this->systemService->getSystemInfo();
    }

    public function getSystemHealth(): ?SystemHealthResponse
    {
        return $this->systemService->getSystemHealth();
    }

    public function getSystemStats(): ?SystemStatsResponse
    {
        return $this->systemService->getSystemStats();
    }

    // Activity log methods
    public function getActivityLogs(array $params = []): array
    {
        return $this->activityService->getActivityLogs($params);
    }

    public function getAdminActivityLogs(array $params = []): array
    {
        return $this->activityService->getAdminActivityLogs($params);
    }

    // Settings methods
    public function getSettings(): ApiResponse
    {
        return $this->settingsService->getSettings();
    }

    public function updateSettings(array $settings): ApiResponse
    {
        return $this->settingsService->updateSettings($settings);
    }
}
