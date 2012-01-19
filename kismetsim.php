#!/usr/bin/php -q
<?php
/**
  * Listens for requests and forks on each connection
  */

include 'settings.php';

$__server_listening = true;

error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();
declare(ticks = 1);

become_daemon();

/* nobody/nogroup, change to your host's uid/gid of the non-priv user */
change_identity(65534, 65534);

/* handle signals */
pcntl_signal(SIGTERM, 'sig_handler');
pcntl_signal(SIGINT, 'sig_handler');
pcntl_signal(SIGCHLD, 'sig_handler');

/* change this to your own host / port */
server_loop($server, $port);

/**
  * Change the identity to a non-priv user
  */
function change_identity($uid, $gid)
{
    if( !posix_setgid( $gid ) )
    {
        print "Unable to setgid to " . $gid . "!\n";
        exit;
    }

    if( !posix_setuid( $uid ) )
    {
        print "Unable to setuid to " . $uid . "!\n";
        exit;
    }
}

/**
  * Creates a server socket and listens for incoming client connections
  * @param string $address The address to listen on
  * @param int $port The port to listen on
  */
function server_loop($address, $port)
{
    GLOBAL $__server_listening;

    if(($sock = socket_create(AF_INET, SOCK_STREAM, 0)) < 0)
    {
        echo "failed to create socket: ".socket_strerror($sock)."\n";
        exit();
    }

    if(($ret = socket_bind($sock, $address, $port)) < 0)
    {
        echo "failed to bind socket: ".socket_strerror($ret)."\n";
        exit();
    }

    if( ( $ret = socket_listen( $sock, 0 ) ) < 0 )
    {
        echo "failed to listen to socket: ".socket_strerror($ret)."\n";
        exit();
    }

    socket_set_nonblock($sock);
   
    echo "waiting for clients to connect\n";

    while ($__server_listening)
    {
        $connection = @socket_accept($sock);
        if ($connection === false)
        {
            usleep(100);
        }elseif ($connection > 0)
        {
            handle_client($sock, $connection);
        }else
        {
            echo "error: ".socket_strerror($connection);
            die;
        }
    }
}

/**
  * Signal handler
  */
function sig_handler($sig)
{
    switch($sig)
    {
        case SIGTERM:
        case SIGINT:
            exit();
        break;

        case SIGCHLD:
            pcntl_waitpid(-1, $status);
        break;
    }
}

/**
  * Handle a new client connection
  */
function handle_client($ssock, $csock)
{
    GLOBAL $__server_listening;

    $pid = pcntl_fork();

    if ($pid == -1)
    {
        /* fork failed */
        echo "fork failure!\n";
        die;
    }elseif ($pid == 0)
    {
        /* child process */
        $__server_listening = false;
        socket_close($ssock);
        interact($csock);
        socket_close($csock);
    }else
    {
        socket_close($csock);
    }
}

function interact($socket)
{
	showWelcome($socket);
	$command = '';
	$isRunning = true;
	$networks = 0;
	$starttime = time();
	$endtime = $starttime + 1200;
	$currtime = time();
	$nettime = time();
	$errorcode = '';
	
	while(time() < $endtime && $errorcode == '' && $isRunning)
	{
		/* TALK TO YOUR CLIENT */
				
		if(time() > $currtime + 5)
		{
			@socket_write($socket, '*INFO: ' . $networks . "\n");
			$currtime = time();
		}
		
//		if(time() > $nettime + 3)
//		{
			outputNetworkInfo($socket, $networks);
			$networks++;
			$nettime = time();
//		}
		
		$errorcode = socket_last_error($socket);
	}
}

/**
  * Become a daemon by forking and closing the parent
  */
function become_daemon()
{
    $pid = pcntl_fork();
   
    if ($pid == -1)
    {
        /* fork failed */
        echo "fork failure!\n";
        exit();
    }elseif ($pid)
    {
        /* close the parent */
        exit();
    }else
    {
        /* child becomes our daemon */
        posix_setsid();
        chdir('/');
        umask(0);
        return posix_getpid();

    }
}

/**
 * Functions to support kismetsim processing
 */
function process_command($socket, $command)
{
	switch($command)
	{
		case 'quit':
			return false;
			break;
	}
	
	return true;
}

function showWelcome($socket)
{
	@socket_write($socket, '*KISMET: 0.0.0 1311505204 Kismet 20050815211952 1 2007.01.R1 
*PROTOCOLS: KISMET,ERROR,ACK,PROTOCOLS,CAPABILITY,TERMINATE,TIME,ALERT,NETWORK,CLIENT,GPS,INFO,REMOVE,STATUS,PACKET,STRING,WEPKEY,CARD' . "\n");
}

function outputNetworkInfo($socket, $networks)
{
	$network = '*NETWORK: ' . generateMACAddress() . ' 0.0.0.0 0 2 11.0 0 979.770020 -81.571976 41.404366 979.770020 0.000000 41.404499 -81.571899 1018.049988 0.000000 WAP Demo ' . $networks . "\n";
    echo $network . "\n";
	@socket_write($socket, $network);
}

function generateMACAddress()
{
    $mac = '';
    for($a = 0; $a < 6; $a++) 
    {
        if($a > 0)
            $mac .= ':';
        $mac .= sprintf('%02d', rand(0, 255));
    }

    return $mac;
}
 
?>
