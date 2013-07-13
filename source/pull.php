<?PHP

@ob_end_clean();

error_reporting(30719);
ini_set("display_errors","On");

//echo "working..";

chdir("/home/httpstuff/public_html/charli/jaya3");

//This file will pull the information from the database,
//in response to a contextID.

//It does this by looking through the leadio's and listing all
//the replies it has saved. it will then pick one.

//unless the input is new, and there is no entry in the system already
//then the system will look for something similar, by looking at all the
//types, and listing all the responses, the one that has the most similar
//types will be used

//unless no output can be found, in which case the computer returns
//this to the front end, and it is up to the front end what to do next
// - whether to look up a response to an earlier contextid, or whether
//to end the conversation.

//before this phrase is sent back to the user,
//if it's an exact match, then the words will be substituted straight back
//in to the phrase, otherwise the system will match the known types
//with the types of the two nouns and verb, and will make a guess
//as to where the nouns and verb should go in the sentence in order to make
//it sound correct.

require_once "context.php";
require_once "database.php";
require_once "helpers.php";
require_once "combinatorial_logic.php";

set_time_limit(3);

$context = context::load($_GET['context']);
//$context = context::load($argv[1]);

$response = array();

$similars = array();

foreach($context["leadio"] as $lio) {

$row = database::query("io",$lio);

$response = array_merge($response,json_decode($row->response,true));
$similar = json_decode($row->similar,true);
$similars = array_merge($similars,$similar);
foreach($similar as $sim) {
$sim = database::query("io",$sim);
$response = array_merge($response,json_decode($sim->response,true));
}

}

$similars = array_unique($similars);
$response = array_unique($response);

//We have an array of possible responses:
if(count($response)>0) {

$rand = array_rand($response);

for($a=0;$a<count($response);$a++) {
//Pick one at random:

//print_r($response[$rand]);

//Make sure that this matches what the user has said:
//and then send back to the front end.
if(in_array($response[$rand],$similars)) {$rand++; continue;}
$ret = io_output($response[$rand],$context);
if($ret==true) {die();}
$rand++;
if($rand>=count($response)) {$rand = 0;}
}

}

//We want to just check to see if the sentence is directly referenced in the system:
if(strpos($context["sentence"],"%noun1%")===false&&strpos($context["sentence"],"%noun2%")===false&&strpos($context["sentence"],"%verb%")===false) {
$result = database::query("io","pattern=".$context["sentence"]);
if($result!==false) {
        
	$response = json_decode($result->response,true);
	$similar = json_decode($result-similar,true);
        if(count($response)>0) {
                //Pick one at random:
		$rand = array_rand($response);
                for($a=0;$a<count($response);$a++) {
                        //Make sure that this matches what the user has said:
                        //and then send back to the front end.
			if(in_array($response[$rand],$similar)) {$rand++; continue;}
                        $ret = io_output($response[$rand],$context);
			if($ret==true) {die();}
			$rand++;
			if($rand>=count($response)) {$rand=0;}
                }
        }

}
}

//We don't have any responses yet:
//We want to scan the whole table:
//but including every possible combination:
if(count($context["types"])>0) {
$noun1 = array();
$noun2 = array();
$verb = array();

foreach($context["types"] as $c) {
$noun1 = array_merge($noun1,explode(", ",$c[0]));
$noun2 = array_merge($noun2,explode(", ",$c[1]));
$verb[] = $c[2];
}

$noun1 = array_unique($noun1);
$noun2 = array_unique($noun2);
$verb = array_unique($verb);

//print_r($noun2);

$c = array_combinations($noun1,$noun2,$verb);

//Now we want to do a super search:
$results = array();

echo "('leadio':'";

foreach($c as $a) {
//echo "like ".$a[0]." 2 like ".$a[1]." verb like ".$a[2]." \n\r";
$result = database::query("io","noun1like=%".$a[0]."%","noun2like=%".$a[1]."%","verblike=%".$a[2]."%","pattern=".$context["sentence"]);
echo "noun1like=%".$a[0]."%","noun2like=%".$a[1]."%","verblike=%".$a[2]."%","pattern=".$context["sentence"];
if($result==false) {continue;}
//echo $result->length."\n\r";
do {
//echo $result."\n\r";
if(isset($results[$result.""])) {
$results[$result.""]++;
}
else
{
$results[$result.""] = 1;
}
}while($result->next());
}

echo "'}";

//We have an array of all results, with their value indicating the number
//of type hits.

if(count($results)>0) {

arsort($results);
reset($results);

do {
	$best = key($results);

	$row = database::query("io",$best);
	
	$response = json_decode($row->response,true);
	$similar = json_decode($row->similar,true);
	
	if(count($response)>0) {
		//Pick one at random:
		$rand = array_rand($response);
		for($a=0;$a<count($rand);$a++) {
			//Make sure that this matches what the user has said:
			//and then send back to the front end.
			if(in_array($response[$rand],$similar)) {$rand++; continue;}
			$ret = io_output($response[$rand],$context);
			if($ret==true) {die();}
			$rand++;
			if($rand>=count($response)) {$rand=0;}
		}
	}

}while(next($results));

}

io_output_error();
die();
}

function io_output_error() {
echo "{'error':'no sentence found.'}\n\r";
}

function io_output($response,$context) {
//We want to piece together this phrase
//so we can send it back to the user...

//The response is simply the uid of the row:
//so let's get the row:

$row = database::query("io",$response);

$noun1 = $row->noun1;
$noun2 = $row->noun2;
$verb = $row->verb;

$n1="";
$n2="";
$v="";

$pattern = $row->pattern;

if(strpos($pattern,"%noun1%")!==false) {
if($row->noun1swap!=="") {
//The pattern uses the noun1.
if($row->noun1swap=="noun1") {
$n1 = $context["exact"][0];
}
if($row->noun1swap=="noun2") {
$n1 = $context["exact"][1];
}
}
else
{
$n1 = $noun1;
}
if($n1=="") {return false;}
$pattern = str_replace("%noun1%",$n1,$pattern);
}

if(strpos($pattern,"%noun2%")!==false) {
	if($row->noun2swap!=="") {
		//The pattern uses the noun1.
		if($row->noun2swap=="noun1") {
			$n2 = $context["exact"][0];
		}
		if($row->noun2swap=="noun2") {
			$n2 = $context["exact"][1];
		}
	}
	else
	{
		$n2 = $noun2;
	}
	if($n2=="") {return false;}
	$pattern = str_replace("%noun2%",$n2,$pattern);
}

if(strpos($pattern,"%verb%")!==false) {
if($row->verbswap!=="") {
//The pattern uses the noun1.
if($row->verbswap=="verb") {
$v = $context["exact"][3];
}
}
else
{
$v = $verb;
}
if($v=="") {return false;}
$pattern = str_replace("%verb%",$v,$pattern);
}

$context["exact"][0]=$n1;
$context["exact"][1]=$n2;
$context["exact"][2]=$v;

//Update leadios

$similar = json_decode($row->similar,true);
$context["leadio"] = $similar;
$context["leadio"][] = $row."";

echo "{'sentence':'".$pattern."','context':'".(context::save($context))."'}\n\r";
return true;

}
