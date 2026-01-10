<?php

namespace App\DTOs;

/**
 * Login History Statistics Response DTO
 */
class LoginHistoryStatisticsResponse
{
    public function __construct(
        public readonly int $totalLogins,
        public readonly int $uniqueUsers,
        public readonly int $todayLogins,
        public readonly int $thisWeekLogins,
        public readonly int $thisMonthLogins,
        public readonly ?array $topUsers = null,
        public readonly ?array $loginByHour = null,
        public readonly ?array $loginByDay = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            totalLogins: $data['total_logins'] ?? 0,
            uniqueUsers: $data['unique_users'] ?? 0,
            todayLogins: $data['today_logins'] ?? 0,
            thisWeekLogins: $data['this_week_logins'] ?? 0,
            thisMonthLogins: $data['this_month_logins'] ?? 0,
            topUsers: $data['top_users'] ?? null,
            loginByHour: $data['login_by_hour'] ?? null,
            loginByDay: $data['login_by_day'] ?? null,
        );
    }
}