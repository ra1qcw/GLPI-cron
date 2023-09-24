<?php 
//v0.3
//state 0=ready; 1=deploy; 2=unknown; 3=finished; 4=error?;  5=canceled; 
chdir(__DIR__);
include('../inc/includes.php');
$config = new PluginGlpiinventoryConfig();

////////////////////////////////////////////////////////////
//Maximum deployment tasks.
$maxWakeUp   = 5; 
//Get maximum deployment tasks from GLPI config (uncomment to enable).
// $maxWakeUp   = $config->getValue('wakeup_agent_max');  
//Send request to IPv4 only addreses of agent. 
$iIPv4Only = true; 
//cURL connect timeout
$iConnectTimeout = 2; 
//cURL total timeout
$iTimeout = 4;  
//Limit script execution time.
$iScriptTime = 55; 
//Aggressive deployment. Good for short packages. 
$iShortDeploys = False; 
////////////////////////////////////////////////////////////

global $DB;

function is_ip($str,$ipv4) {
    $ret = filter_var($str, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    if (!$ipv4) {$ret=true;}
    return $ret;
}

function cDeploy() { //count of deploying agents
$req = $GLOBALS['DB']->request([ 
         'SELECT' => ['agents_id'],
         'FROM'   => 'glpi_plugin_glpiinventory_taskjobstates',
         'WHERE'  => [
            'itemtype'  => ['=','PluginGlpiinventoryDeployPackage'],
            'state'     => '1'
		    ],
		'GROUPBY' => ['agents_id']
        ]);

return count($req);
}

$agent  = new Agent();
echo 'Max wakeup: '.$maxWakeUp.'  ';
$iStart = microtime(true); // Script timer
$iterator = $DB->request([
         'SELECT' => ['agents_id'],
         'FROM'   => 'glpi_plugin_glpiinventory_taskjobstates',
         'WHERE'  => [
            'itemtype'  => ['=','PluginGlpiinventoryDeployPackage'],
            'state'     => '0'
		    ],
		'GROUPBY' => ['agents_id']
        ]);
// List of agents
$iStack= array();
foreach ($iterator as $res)   {   array_push( $iStack , $res['agents_id'] );  }

$iDeploy = cDeploy();
echo 'Deploying now agents: '.$iDeploy.'  ';
$iTotal = count($iStack);
echo 'Total prepared agents: '.$iTotal.'  ';
$iFree=$maxWakeUp-$iDeploy;
echo 'Free slots: '.$iFree.'  ';
$iCount = min(($maxWakeUp-$iDeploy),$iTotal);
echo 'Count to wake: '.$iCount.'  ';
if ($iCount<=0) { exit;}


echo "\r\n";

if (count($iStack)==0) {  echo "No Agents."; exit; }

$iOK = 0; //Count of awakened by script agents
shuffle($iStack);

foreach ($iStack as $res)
	    {
	    echo 	'id: '.$res."\r\n";
	    flush();
	     $ch = curl_init();
	     $agent->getFromDB($res);
	     $addrs = $agent->getAgentURLs();
	     foreach ($addrs as $addr) 
			{
			if (is_ip(parse_url($addr, PHP_URL_HOST),$iIPv4Only)and(parse_url($addr, PHP_URL_SCHEME)=='http')) 
			    {
				echo 'Addr: '.$addr."\r\n"; flush();
				curl_setopt($ch, CURLOPT_URL, $addr.'/now');
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $iConnectTimeout);
				curl_setopt($ch, CURLOPT_TIMEOUT, $iTimeout);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				$iRes=curl_exec($ch);
				if ($iRes) { $iOK++ ; echo "Agent OK\r\n"; break; } 
			    }
			}
	    curl_close($ch);
	    echo "\r\nFree solts left: ".(  $maxWakeUp-$iDeploy-$iOK ) ."\r\n" ; 
	    echo "\r\n";
	    flush();

	    if ( (($maxWakeUp-$iDeploy-$iOK)<=0 ) and $iShortDeploys  )   
		    { sleep(3);  $iOK = 1;   $iDeploy = cDeploy();   }

	    if ( ($maxWakeUp-$iDeploy-$iOK)<=0 ) 
		    {echo "No free slots. \r\n"; break;}
	    if ((microtime(true)-$iStart)>=$iScriptTime ) {echo "Script time limit.\r\n"; break; }
	    }

echo 'END.';
?>
