# Backup
Simple class to backup mysql database to remote ftp server and backblaze bucket.

## Requirements
The PHP class requires access to the `exec` function to run `mysqldump` on the server.
Optional dependence: https://github.com/gliterd/backblaze-b2

## Install

Download file and require it in your project:

``` php
require '/location/to/Backup.php';
```

## Usage
Set up all the required connection data to the database and to external servers in the class.
Then use:
``` php
$backup = new \Backup();
$backup->Create();
```
