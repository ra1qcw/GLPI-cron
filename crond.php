<?php 
//state 0=ready; 1=deploy; 2=unknown; 3=finished; 4=error?;  5=canceled; 
chdir(__DIR__);
include('../inc/includes.php');

$maxWakeUp   = 10;
// $maxWakeUp   = $config->getValue('wakeup_agent_max');
$iIPv4Only = true;
$iConnectTimeout = 2;
$iTimeout = 4;
$iLoop = true;
$iScriptTime = 50;

function is_ip($str,$ipv4) {
    $ret = filter_var($str, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    if (!$ipv4) {$ret=true;}
    return $ret;
}

global $DB;
$config = new PluginGlpiinventoryConfig();
$agent  = new Agent();

echo 'Max wakeup: '.$maxWakeUp.'  ';
$iStart = microtime(true);

do {   //global loop

$iterator = $DB->request([ 
         'SELECT' => ['agents_id'],
         'FROM'   => 'glpi_plugin_glpiinventory_taskjobstates',
         'WHERE'  => [
            'itemtype'  => ['=','PluginGlpiinventoryDeployPackage'],
            'state'     => '1'
		    ],
		'GROUPBY' => ['agents_id']
        ]);
$iDeploy = count($iterator);
echo 'Deploying: '.$iDeploy.'  ';

$iterator = $DB->request([
         'SELECT' => ['agents_id'],
         'FROM'   => 'glpi_plugin_glpiinventory_taskjobstates',
         'WHERE'  => [
            'itemtype'  => ['=','PluginGlpiinventoryDeployPackage'],
            'state'     => '0'
		    ],
		'GROUPBY' => ['agents_id']
        ]);

$iTotal = count($iterator);
echo 'Total: '.$iTotal.'  ';

$iFree=$maxWakeUp-$iDeploy;
echo 'Free: '.$iFree.'  ';

$iCount = min(($maxWakeUp-$iDeploy),$iTotal);
echo 'Count to wake: '.$iCount.'  ';

if ($iCount<=0) { exit;}

$iStack= array();

//list of agents
foreach ($iterator as $res) 
    {   array_push( $iStack , $res['agents_id'] );  }
echo "\r\n";


$iRand = array_rand($iStack ,$iCount);

        foreach ($iRand as $res)
	    {
	    echo 	'id: '.$iStack[$res]."\r\n";
	    flush();
	     $ch = curl_init();
	     $agent->getFromDB($iStack[$res]);
	     $addrs = $agent->getAgentURLs();
	     foreach ($addrs as $addr) 
			{
			if (is_ip(parse_url($addr, PHP_URL_HOST),$iIPv4Only)and(parse_url($addr, PHP_URL_SCHEME)=='http')) 
			    {
				curl_setopt($ch, CURLOPT_URL, $addr.'/now');
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $iConnectTimeout);
				curl_setopt($ch, CURLOPT_TIMEOUT, $iTimeout);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //return True or False
				$iRes=curl_exec($ch);
				echo 'Addr: '.$addr."\r\n";		    
				if ($iRes) { echo "Agent OK\r\n"; break; } 
			    }
			}
	    curl_close($ch);
	    echo "\r\n";
	    flush();
	    if ((microtime(true)-$iStart)>=$iScriptTime ) {echo "Script time limit.\r\n"; break; }

	    }
	    if ($iLoop) { sleep(2); }
}   while (((microtime(true)-$iStart)<$iScriptTime )and($iLoop == true)); //enf of global loop

echo 'END.';
?>
