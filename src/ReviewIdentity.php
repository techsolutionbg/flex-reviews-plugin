<?php
declare(strict_types=1);
namespace Flex\Reviews;

use Flex\Extensions\{Plugin, PluginManager};
use Flex\Accounts\AccountSessionReader;

final class ReviewIdentity
{
    /** Identity is read exclusively from validated server-side sessions. */
    public static function resolve(?object $platformUser): ?array
    {
        try {
            $plugin = Plugin::query()->find('flex/accounts');
            if ($plugin instanceof Plugin && $plugin->getAttribute('status') === PluginManager::STATUS_ACTIVE
                && class_exists(AccountSessionReader::class)) {
                $account = AccountSessionReader::user();
                if ($account !== null) return ['id' => $account['id'], 'source' => 'account',
                    'name' => trim($account['first_name'] . ' ' . $account['last_name']), 'email' => $account['email']];
            }
        } catch (\Throwable) {
            // Accounts is optional; its absence does not prevent guest reviews.
        }
        return $platformUser === null ? null : ['id' => $platformUser->id, 'source' => 'user',
            'name' => $platformUser->name, 'email' => $platformUser->email];
    }
}
