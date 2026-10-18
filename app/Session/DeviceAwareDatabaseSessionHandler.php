<?php

namespace App\Session;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Carbon;

/**
 * The database session driver with two idle lifetimes instead of one (see
 * App\Support\SessionLifetimes): a row with a user_id expires after the
 * account lifetime, a row without one (a room session tablet) after the much
 * longer device lifetime. The browser cookie gets the longer of the two
 * (session.lifetime), so the account timeout is enforced here, server-side.
 */
class DeviceAwareDatabaseSessionHandler extends DatabaseSessionHandler
{
    public function __construct(
        ConnectionInterface $connection,
        $table,
        protected int $accountMinutes,
        protected int $deviceMinutes,
        ?Container $container = null,
    ) {
        parent::__construct($connection, $table, $deviceMinutes, $container);
    }

    protected function expired($session)
    {
        if (! isset($session->last_activity)) {
            return false;
        }

        $minutes = empty($session->user_id) ? $this->deviceMinutes : $this->accountMinutes;

        return $session->last_activity < Carbon::now()->subMinutes($minutes)->getTimestamp();
    }

    public function gc($lifetime): int
    {
        $now = $this->currentTime();

        return $this->getQuery()
            ->where(fn ($q) => $q->whereNotNull('user_id')
                ->where('last_activity', '<=', $now - $this->accountMinutes * 60))
            ->orWhere(fn ($q) => $q->whereNull('user_id')
                ->where('last_activity', '<=', $now - $this->deviceMinutes * 60))
            ->delete();
    }
}
