<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;

final class Campaign
{
    public static function isVotingOpen(): bool
    {
        if (Settings::bool('voting_manual_closed', false)) {
            return false;
        }
        $now = new DateTimeImmutable('now');
        return $now >= new DateTimeImmutable((string) Config::get('campaign.voting_starts_at'))
            && $now <= new DateTimeImmutable((string) Config::get('campaign.voting_ends_at'));
    }

    public static function isRegistrationOpen(): bool
    {
        if (Settings::bool('registration_manual_closed', false)) {
            return false;
        }
        $now = new DateTimeImmutable('now');
        return $now >= new DateTimeImmutable((string) Config::get('campaign.registration_starts_at'))
            && $now <= new DateTimeImmutable((string) Config::get('campaign.registration_ends_at'));
    }
}
