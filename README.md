# db-mysql

Adalah model provider untuk database MySQL. Modul ini membutuhkan konfigurasi pada
level aplikasi seperti di bawah:

```php
<?php

return [
    'name' => 'Phun',
    ...
    'db' => [
        'default' => [
            'host'      => 'localhost',
            'user'      => 'root',
            'passwd'    => '',
            'dbname'    => 'phun',
            'port'      => '3306',
            'socket'    => '/tmp/mysql.sock'
        ],
        'other_conn' => [...]
    ],
    
    // optional
    'db_model' => [
        'Core\\Model\\User' => 'default',
        'Core\\Model\\UserProfile' => [
            'read' => 'default'
        ],
        'Core\\Model\\UserFriend' => [
            'write' => 'other_conn'
        ],
        'Core\\Model\\UserSocial' => [
            'read' => 'default',
            'write' => 'other_conn'
        ]
    ],
    
    // optional
    'db_target' => [
        'read' => 'default',
        'write'=> 'other_conn'
    ]
];
```

Konfigurasi `db_model` adalah opsional dan bertugas untuk menentukan koneksi yang
mana yang akan digunakan oleh model untuk berkomunikasi dengan database.

Konfigurasi `db_target` adalah optional dan bertugas untuk menentukan koneksi yang
mana yang akan digunakan oleh model untuk berkomunikasi dengan database secara default.
Jika properti ini tidak diset, maka nilai `default` akan digunakan untuk koneksi menulis
dan membaca kecuali sudah didefinisikan di konfigurasi `db_model`.

Perlu diketahui bahwa modul ini mungkin tidak bisa bekerja dengan modul model
provider yang lain seperti `db-pgsql` dan lain-lain. Hal ini karena masing-masing
model provider mendefinisikan class `Model` secara bersamaan.