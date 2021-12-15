<?php
class Backup {
    private $log = '/src/to/your/log/backup.log'; // location and filename of log file, where errors and messages will be stored
    private $file = '/src/to/your/gz/backup.sql.gz'; // location and filename of your gzipped backup file
    private $filesize = 0;
    private $server = null;
    private $mysqldump = '/usr/bin/mysqldump'; // location and filename of mysqldump on your server
    private $db_user = 'mysql_user'; // mysql database user name
    private $db_pass = 'mysql_password'; // mysql database user password
    private $db_database = 'mysql_database_name'; // mysql database name
    private $ftp = [
        'ftp1' => [
            'hostname' => 'hostname', // ftp hostname: ex. example.com or ip address
            'port' => 21, // ftp port
            'user' => 'ftp_user', // ftp user name
            'password' => 'ftp_password', // ftp user password
            'dir' => 'ftp_directory/', // ftp directory. leave blank to upload directly to location after login
            'filename' => 'backup-%day%.sql.qz' // your backup file name. %day% will be replaced with current month day, so you can keep up to 31 backups. they will be replaced in next months
        ]
    ];
    private $backblaze = [
        'account_id' => '',
        'master_application_key' => '',
        'bucket_name' => '',
        'bucket_id' => '',
        'filename' => 'backup-%day%.sql.qz' // same as in ftp
    ];
    
    public function __construct() {
        set_time_limit(0);

        $this->exec = $this->mysqldump . ' -u'.$this->db_user.' --password="'.$this->db_pass.'" --databases '.$this->db_database.' | gzip > '.$this->file;
    }
    
    /*
        Method to launch creating mysql dump file
        Then sends it to ftp and backblaze b2 bucket
    */
    
    public function Create() {
        exec($this->exec);
        
        $this->send2ftp();
        $this->send2b2();
    }
    
    /*
        Method to send backup file to ftp servers
        $server is key from settings in private $ftp variable
    */
    public function send2ftp($server = 'ftp1') {
        $this->server = $server;
        
        if (!isset($this->ftp[$server])) {
            $this->log('No FTP settings');
            
            return;
        }
        
        if (!$this->checkBackupFile())
            return;
        
        $conn_id = ftp_connect($this->ftp[$server]['hostname'], !empty($this->ftp[$server]['port']) ? $this->ftp[$server]['port'] : 21);
        
        if ($conn_id === false) {
            $this->log('Unsuccessful ftp connect');
            
            return;
        }
        
        $login_result = ftp_login($conn_id, $this->ftp[$server]['user'], $this->ftp[$server]['password']);
        
        if ($login_result === false) {
            $this->log('Unsuccessful ftp login');
            
            return;
        }
        
        ftp_pasv($conn_id, true);

        $remote_file = $this->ftp[$server]['dir'].str_replace('%day%', date('j'), $this->ftp[$server]['filename']);

        if (ftp_put($conn_id, $remote_file, $this->file, FTP_BINARY)) 
            $this->log('Backup sent successfuly as '.$remote_file);
        else
            $this->log('Unsuccessful sending as '.$remote_file);

        ftp_close($conn_id);
    }
    
    /*
        Method to send backup file to Backblaze B2 servers
        See docs: https://www.backblaze.com/b2/docs/
    */
    public function send2b2() {
        $this->server = 'b2';
        
        if (empty($this->backblaze['account_id'])) {
            $this->log('No B2 settings');
            
            return;
        }
        
        if (!$this->checkBackupFile())
            return;
        
        $remote_file = str_replace('%day%', date('j'), $this->backblaze['filename']);
        
        // you need to download backblaze class manually from: https://github.com/gliterd/backblaze-b2
        // and require it once here (it needs additional libraries)
        // so the best way is to use composer: $ composer require gliterd/backblaze-b2
        // and leave it for vendor autoload script
        $client = new \BackblazeB2\Client($this->backblaze['account_id'], $this->backblaze['master_application_key']);

        try {
            $client->deleteFile([
                'BucketName' => $this->backblaze['bucket_name'],
                'FileName' => $remote_file
            ]);
        } catch(\BackblazeB2\Exceptions\B2Exception $e) {
            $this->log('Unsuccessful erasing remote file '.$remote_file.'. Exception: '.$e->getMessage());
        } catch(\BackblazeB2\Exceptions\NotFoundException $e) {
            $this->log('There was no remote file '.$remote_file.'. Exception: '.$e->getMessage());
        }

        try {
            $upload = $client->upload([
                'BucketId' => $this->backblaze['bucket_id'],
                'FileName' => $remote_file,
                'Body' => fopen($this->file, 'r')
            ]);
        } catch(\BackblazeB2\Exceptions\B2Exception $e) {
            $this->log('Unsuccessful sending '.$remote_file.'. Exception: '.$e->getMessage());

            return;
        }

        if (!empty($upload->getId()))
            $this->log('Backup sent successfuly as '.$remote_file);
        else
            $this->log('Backup was not sent');

    }
    
    private function checkBackupFile() {
        if (!is_file($this->file)) {
            $this->log($this->file.' does not exist');
            
            return false;
        }
        
        $this->filesize = filesize($this->file);
        
        if (!$this->filesize) {
            $this->log($this->file.' has 0 bytes');
            
            return false;
        }
        
        return true;
    }
    
    private function log($msg) {
        file_put_contents($this->log, date("Y-m-d H:i:s")." - ".$msg." [".$this->file." (".$this->FormattedSize($this->filesize).") -> ".$this->server."]\r\n", FILE_APPEND);
    }
    
    private function FormattedSize($filesize, $true = false) {
        $weight = array('bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');

        if (is_array($filesize)) {
            $size = 0;

            foreach ($filesize as $fs)
                $size+=$fs;
            unset($filesize);
            $filesize = $size;
        }

        if (!$true) {
            for ($i = 0; $filesize >= 1024; $i++)
                $filesize /= 1024;
            return number_format($filesize, 2) . ' ' . $weight[$i];
        }

        return $filesize;
    }
}