<?php

declare(strict_types=1);

namespace kintai\Core\Database;

use InvalidArgumentException;

final class DriverFactory
{
    /**
     * Creates and returns a new, unconnected persistence driver instance.
     *
     * This is the single point of knowledge about concrete driver classes.
     * Adding a new driver only requires modifying this class.
     *
     * @param string $driver One of: 'json', 'sqlite', 'mysql'.
     * @throws InvalidArgumentException For unknown driver names.
     */
    public function create(string $driver): PersistenceDriverInterface
    {
        return match ($driver) {
            'json'   => new JsonDriver(),
            'sqlite' => new SqliteDriver(),
            'mysql'  => new MysqlDriver(),
            default  => throw new InvalidArgumentException(
                "Unsupported persistence driver: [{$driver}]. Supported: json, sqlite, mysql."
            ),
        };
    }
}
