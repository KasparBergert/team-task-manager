<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class SystemVersioning
{
    public static function set(string $table, bool $enabled): void
    {
        $context = ['table' => $table, 'operation' => $enabled ? 'enable' : 'disable'];

        try {
            $connection = Schema::getConnection();
            $context['connection'] = $connection->getName();
            $context['driver'] = $connection->getDriverTitle();

            if ($context['driver'] !== 'MariaDB') {
                Log::error('Cannot {operation} system versioning for {table}. The {driver} connection does not support MariaDB system versioning.', $context);

                return;
            }

            $tableType = $connection->scalar(
                'SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$connection->getTablePrefix().$table],
                useReadPdo: false,
            );

            $isVersioned = $tableType === 'SYSTEM VERSIONED';
            if ($isVersioned === $enabled) {
                return;
            }

            $tableName = $connection->getQueryGrammar()->wrapTable($table);
            $sql = $enabled
                ? "ALTER TABLE {$tableName} ADD SYSTEM VERSIONING"
                : "ALTER TABLE {$tableName} DROP SYSTEM VERSIONING";

            if (! $connection->statement($sql)) {
                throw new RuntimeException('The database returned false when executing '.$sql);
            }
        } catch (Throwable $exception) {
            Log::error('Failed to {operation} system versioning for {table}. {reason}', $context + [
                'reason' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }
}
