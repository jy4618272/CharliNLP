<?PHP

class data_Wordnet {

function get($word,$next) {

$word = strtolower($word);

$information = array();

if(preg_match(":^[\d]{1,50}$:",$word)) {
//This is a quantity, and so mark it as such:

$information = array();
$information[] = array("type"=>"article","subtype"=>"quantity");
return true;
}

$cache = mysql::get("words_wordnet","word",$word);
if(count($cache)>0) {

$information = json_decode($cache[0]["data"],true);

foreach($information as &$record) {
$record["nextobj"] = $next;
}

return $information;

}

$over = array();
if(file_exists("wordnet\\bin\\wn.exe")) {
exec('wordnet\\bin\\wn.exe "'.$word.'" -over -a -o',$over);
}
else
{
exec('wn "'.$word.'" -over -a -o',$over);
}
//usleep(1000000/1);
//exec('wn "'.$word.'" -over -a -o',$over);
//$information["watchfor"] = array();
foreach($over as $line) {
	if(strpos($line,".")!==false) {
	
	$matches = array();
	//preg_match(':([^\.])\. \(([^\)])\) \{([^\}])\} \<([^\>])\> ([^-]) -- \(([^\)])\):',$line,$matches);
	
	preg_match(':([^\.]{1,5})\. \(([^\)]{1,5})\) \{([^\}]{8})\} \<([^\.]{1,5})\.([^\>]{1,50})\> (.{1,1000}) -- \(([^\)]{1,1000})\):',$line,$matches);
	if(count($matches)==0) {
	//preg_match(':([^\.]{1,5})\.() \{([^\}]{8})\} \<([^\.]{1,5})\.([^\>]{1,50})\> ([^-]{1,500}) -- \(([^\)]{1,1000})\):',$line,$matches);
preg_match(':([^\.]{1,5})\.() \{([^\}]{8})\} \<([^\.]{1,5})\.([^\>]{1,50})\> (.{1,1000}) -- \(([^\)]{1,1000})\):',$line,$matches);
	}
	$record = array();
	$record["type"] = strtolower($matches[4]);
	$record["priority"] = $matches[2];
	$record["subtype"] = $matches[5];
	$matches[6] = preg_replace(':[0-9]:','',$matches[6]);
	$record["similar"] = explode(", ",$matches[6]);
	//$record["nextobj"] = $next;
	$record["source"] = "wordnet";
	$information[] = $record;
	//$record["source_location"] = $matches[1];
	}
}

mysql::add("words_wordnet",0,$word,json_encode($information));

foreach($information as &$record) {
$record["nextobj"] = $next;
}

return $information;

}

}