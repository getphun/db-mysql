<?php
/**
 * db-mysql config file
 * @package db-mysql
 * @version 0.0.2
 * @upgrade true
 */

return [
    '__name' => 'db-mysql',
    '__version' => '0.0.2',
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
    '_server' => [
        'MySQL 5.6.23' => 'DbMysql\\Library\\Server::software'
    ],
    '_services' => [],
    '_autoload' => [
        'classes' => [
            'Model' => 'modules/db-mysql/library/Model.php',
            'DbMysql\\Library\\Connector' => 'modules/db-mysql/library/Connector.php',
            'DbMysql\\Library\\Server'    => 'modules/db-mysql/library/Server.php'
        ],
        'files' => []
    ]
];