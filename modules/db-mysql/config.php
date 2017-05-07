<?php
/**
 * db-mysql config file
 * @package db-mysql
 * @version 0.0.1
 * @upgrade true
 */

return [
    '__name' => 'db-mysql',
    '__version' => '0.0.1',
    '__git' => 'https://github.com/getphun/db-mysql',
    '__files' => [
        'modules/db-mysql' => [
            'install',
            'remove',
            'update'
        ]
    ],
    '__dependencies' => [
        'core'
    ],
    '_services' => [],
    '_autoload' => [
        'classes' => [
            'Model' => 'modules/db-mysql/library/Model.php',
            'DbMysql\\Library\\Connector' => 'modules/db-mysql/library/Connector.php'
        ],
        'files' => []
    ]
];