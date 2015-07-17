<?php

return [

    'sqlite' => function() use ($models, $tables)
    {
        return [];
    },

    'mysql' => function() use ($models, $tables)
    {
        $q = [];

        // User

        $sql = "CREATE TABLE IF NOT EXISTS `$tables[user]` (
        `id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        `password` VARCHAR(255),
        `plain_password` VARCHAR(255),
        `role` VARCHAR(255),
        `is_active` BOOLEAN NOT NULL DEFAULT FALSE";

        foreach ($models['user']->getAdditionalFields() as $n => $p) {
            $sql .= ",\n`$n` VARCHAR(255)";
        }

        $sql .= "\n);";

        $q[] = $sql;

        $q[] = "CREATE INDEX `$tables[user]_id_idx` ON `$tables[user]` (`id`);";

        // Token

        $q[] = "CREATE TABLE IF NOT EXISTS `$tables[token]` (
        `key` VARCHAR(64) NOT NULL PRIMARY KEY,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        `expires_at` DATETIME,
        `id_user` INTEGER NOT NULL REFERENCES `$tables[user]` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
        `type` VARCHAR(32)
        );";

        $q[] = "CREATE INDEX `$tables[token]_key_idx` ON `$tables[token]` (`key`);";
        $q[] = "CREATE INDEX `$tables[token]_id_user_idx` ON `$tables[token]` (`id_user`);";
        $q[] = "CREATE INDEX `$tables[token]_id_user_type_idx` ON `$tables[token]` (`id_user`, `type`);";

        return $q;
    },

    'pgsql' => function() use ($models, $tables)
    {
        return [];
    },

];
