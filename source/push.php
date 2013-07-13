<?PHP

@ob_end_clean();

chdir("..");
//echo getcwd();

declare(ticks = 1);

set_time_limit(3);

$tickss = 0;

function profiler()
{
$GLOBALS["tickss"]++;
}

function shutdown() {
global $a, $b, $start_time, $tickss, $queries;

$end_time = microtime(true);

$score = (($b^5)/$a)/$b;

$score = round($score*1000)/1000;

if($score>1) {$score = 1;}

echo "\n\r---\n\r\n\r(\n\r\tstatements: $tickss,\n\r\tmysql queries: ".$queries.",\n\r\ttime: ".(round(($end_time-$start_time)*1000))."msecs,\n\r\tmemory: ".(round((memory_get_peak_usage(true)/1024)))."kb\n\r)"; //,\n\r\tcombinations: $a,\n\r\tsolutions: $b\n\r)";
}

//register_shutdown_function('shutdown');

register_tick_function('profiler');

$start_time = microtime(true);

error_reporting(1);
ini_set("display_errors","Off");

require_once "context.php";
require_once "parse.php";
require_once "helpers.php";
require_once "rank.php";
require_once "semantics.php";
require_once "io_input.php";

$context = context::load($_GET['context']);
//$context = context::load($argv[2]);
//print_r($context);
//data::get($argv[1],"next");
//file_put_contents($argv[1].".txt",print_r(data::get($argv[1],"next"),true));
//$sentence = parse::sentence($argv[1]);

$sentence = parse::sentence($_GET['input']);

ob_start();

$a = 0;
$b = 0;
do {
$combination = parse::get_combination($sentence);
if($combination==false) {break;}
$a++;
$combination = $recombine->apply($combination);
//file_put_contents("combination.txt",serialize($combination));
if(rank($combination)==1) {
//ob_start();
$sem = semantic::parse($combination);
ob_end_clean();
$context = io_input::input($sem,$context);
echo "{'context':'".(context::save($context))."'".$changes."}\n\r";
die();

}
else
{

//echo "tried.";
}

}while(1);

//Need to add this manually:

$result = io_input::exact($_GET["input"],"","","","","","",$context);

$row = database::query("io",$result);

ob_end_clean();

$similar = json_decode($row->similar,true);
$similar[] = $result."";
$similar = array_unique($similar);
$row->similar = json_encode($similar);

//We want to update the leading io to point to these new linked data:

$context = context::load($_GET['context']);

$leadio = $context["leadio"];
//var_dump($context);
//print_r($context);

foreach($leadio as $lio) {
$row = database::query("io",$lio);
$response = json_decode($row->response,true);
//print_r($response);
$response[] = $result;
$response = array_unique($response);
//print_r($response);
$row->response = json_encode($response);
}

//global $changes;
$changes = ",'leadio':'".($result)." added to ".(implode(",",$leadio))."'";

$context["leadio"] = array($result);

echo "{'context':'".(context::save($context))."'".$changes."}\n\r";

die();

?>
