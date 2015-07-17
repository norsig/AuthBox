<?php

return [

    'sqlite' => function() use ($models, $tables)
    {
        return [];
    },

    'mysql' => function() use ($models, $tables)
    {
        $q = [];

        $q[] = "DROP TABLE IF EXISTS `$tables[token]`";
        $q[] = "DROP TABLE IF EXISTS `$tables[user]`";

        return $q;
    },

    'pgsql' => function() use ($models, $tables)
    {
        return [];
    },

];
