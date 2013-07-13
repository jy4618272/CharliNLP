<?PHP

require_once "data_freebase.php";
require_once "combinatorial_logic.php";

//This is a very basic implimentation of semantics
//it doesn't do any "correctness" checking
//it simply uses past information to create generalisations
//to help the system work out meaning from the senses that have been
//tagged.

//This links to Freebase, and a semantics database
//It will just look up types, and when it finds a good
//match it will use the semantics database to rank them,
//and then increase the weighting on the one it matches
//this, whilst very basic should help weight the types,
//where there's no way of throwing away poorly "typed" information.

class semantic {

function parse($combination) {
//The combination in question is just an array of info.
//We changed from objects to arrays in the get_combination function
//as an array was more suited at this point...

$verbs = array();
$nouns = array();

$sentence = "";

foreach($combination as $c) {

if(isset($c["elements"])) {
if($c["type"]=="verb"&&count($verbs)<1) {

//Going to use senses from wordnet, which probably will have got information
//because wikitionary has lots of verbs, it's just lacking in "pop culture" nouns...

$senses = array();

$word = "";

foreach($c["elements"] as $b) {
if($b["type"]=="verb") {
$word = $b["word"];
$senses = array_merge($senses,$b["subtype"]);
}
}

$verbs[] = array("word"=>$word,"senses"=>array_unique($senses));

//print_r($verbs[0]["senses"]);

$sentence.="%verb% ";

continue;

}

if($c["type"]=="noun"&&count($nouns)<2) {

//We want to get senses...
//We'll use freebase for this though,
//rather than anything from wordnet, or wikitionary
//just because they're really good at "typing"
//pop culture references, which is exactly what i
//need in this part of the project.

$phrase = "";
$short = "";

foreach($c["elements"] as $b) {
//echo $b["type"];
if($b["type"]!=="preposition") {
$phrase.="".$b["word"]." ";
}
if($b["type"]=="noun") {
$short.="".$b["word"]." ";
}
}

$phrase = substr($phrase,0,-1);
$short = substr($short,0,-1);

//Returns an array of types

$results = freebase::lookup($phrase,true);
if(count($results)==0) {$results = freebase::lookup($short,true);}
if(count($results)==0) {$results = freebase::lookup($phrase,false);}
if(count($results)==0) {$results = freebase::lookup($short,false);}
if(count($results)==0) {continue;}	//Given up trying to find something
		
//print_r($results);

$types = array();

foreach($results as $result) {
sort($result["types"]);
$types[] = implode(", ",$result["types"]);
}

$nouns[] = array("word"=>$phrase,"senses"=>$results,"types"=>$types);

//echo "noun: ".$short;

if(count($nouns)==1) {
$sentence.="%noun1% ";
}
else
{
$sentence.="%noun2% ";
}

continue;

}

foreach($c["elements"] as $b) {
$sentence.=$b["word"]." ";
}

continue;

}

$sentence.=$c["word"]." ";

}

$sentence = substr($sentence,0,-1);

echo $sentence;

echo "\n\rwhere %noun1% = ".$nouns[0]["word"]."";
echo "\n\rwhere %noun2% = ".$nouns[1]["word"]."";
echo "\n\rwhere %verb% = ".$verbs[0]["word"]."";
//We have lots of senses, we need combinatorial logic, to loop through each
//combination and find out the weightings from the database...

//I could use the combinatorial logic from before, but this wouldn't
//work because we're working with arrays here of a fixed length, and 
//fixed "route" though the combinations, where as the other data structure
//is designed to allow the "route" to change based on the combination currently
//being explored, where as these senses don't change based on previous assumptions.

//print_r($verbs[0]["word"]);

$combinations = array_combinations((array) $nouns[0]["types"],(array) $nouns[1]["types"],(array) $verbs[0]["senses"]);

//If there's only one valid meaning:
if(count($combinations)==1) {
//return just this one:
update_semantics($combinations);
semantics_print($combinations);
return array("sentence"=>$sentence,"types"=>$combinations,"exact"=>array($nouns[0]["word"],$nouns[1]["word"],$verbs[0]["word"]));
}

//Otherwise we need to rank the information based on the Semantics database we have:

$ranks = array();

$a = 0;

foreach($combinations as $c) {
//We will split each section down...
$noun1 = $c[0];
$noun2 = $c[1];
$verb = $c[2];

$noun1 = split(", ",$noun1);
$noun2 = split(", ",$noun2);

//We need to do a search using each type seperately,
//to ensure we get an acurate weighting.
$c2 = array_combinations((array) $noun1, (array) $noun2, (array) $verb);

$weight = 0;

foreach($c2 as $check) {
//var_dump();
$weight += get_mysql_nnv("semantics","noun1",$check[0],"noun2",$check[1],"verb",$check[2]);
}

$ranks[$weight/count($c2)][] = $c;

}

arsort($ranks);

$best = reset($ranks);

update_semantics($best);

//print_r($best);

semantics_print($best);

return array("sentence"=>$sentence,"types"=>$best,"exact"=>array($nouns[0]["word"],$nouns[1]["word"],$verbs[0]["word"]));

}

}

function get_mysql_nnv() {
//$query = "INSERT INTO `words` VALUES (0, '".$phonetic."', '".mysql_real_escape_string($name)."', '".mysql_real_escape_string($data)."');";

$args = func_get_args();

$query = "select * from `".$args[0]."` where ".$args[1]." like '".mysql_real_escape_string($args[2])."' and ".$args[3]." like '".mysql_real_escape_string($args[4])."' and ".$args[5]." like '".mysql_real_escape_string($args[6])."' limit 0,1000"; 

$GLOBALS["queries"]++;

$result = mysql_query($query);

if (!$result) {
 $message = 'Invalid query: ' . mysql_error() . "\n";
 $message .= 'Whole query: ' . $query;
 die($message);
}

if (mysql_num_rows($result) == 0) {
return 0;
}

$results = array();

while ($row = mysql_fetch_assoc($result)) {
mysql_free_result($result);
return $row["weighting"];
}

return 0;
}

function update_mysql_nnv() {
//$query = "UPDATE `words` where'".$phonetic."', '".mysql_real_escape_string($name)."', '".mysql_real_escape_string($data)."');";

$args = func_get_args();

$query = "UPDATE `".$args[0]."` SET ".$args[7]."='".mysql_real_escape_string($args[8])."' WHERE ".$args[1]."='".mysql_real_escape_string($args[2])."' and ".$args[3]."='".mysql_real_escape_string($args[4])."' and ".$args[5]."='".mysql_real_escape_string($args[6])."'"; 

$GLOBALS["queries"]++;

$result = mysql_query($query);

if (!$result) {
 $message = 'Invalid query: ' . mysql_error() . "\n";
 $message .= 'Whole query: ' . $query;
 die($message);
}

return true;

}

function add_mysql_nnv() {
//$query = "UPDATE `words` where'".$phonetic."', '".mysql_real_escape_string($name)."', '".mysql_real_escape_string($data)."');";

$args = func_get_args();

$query = "INSERT INTO `".$args[0]."` (`".$args[1]."`,`".$args[3]."`,`".$args[5]."`,`".$args[7]."`) VALUES('".mysql_real_escape_string($args[2])."','".mysql_real_escape_string($args[4])."','".mysql_real_escape_string($args[6])."','".mysql_real_escape_string($args[8])."')"; 

$GLOBALS["queries"]++;

$result = mysql_query($query);

if (!$result) {
 $message = 'Invalid query: ' . mysql_error() . "\n";
 $message .= 'Whole query: ' . $query;
 die($message);
}

return true;

}


function update_semantics($ar) {

foreach($ar as $a) {

$noun1 = $a[0];
$noun2 = $a[1];
$verb = $a[2];

$noun1 = split(", ",$noun1);
$noun2 = split(", ",$noun2);

//We need to update using each type seperately,
//to ensure we get an acurate weighting.
$c2 = array_combinations((array) $noun1, (array) $noun2, (array) $verb);

$weight = 0;

foreach($c2 as $check) {

$noun1 = $check[0];
$noun2 = $check[1];
$verb = $check[2];

$weight = get_mysql_nnv("semantics","noun1",$noun1,"noun2",$noun2,"verb",$verb);
if($weight!==0) {
update_mysql_nnv("semantics","noun1",$noun1,"noun2",$noun2,"verb",$verb,"weighting",$weight+1);
}
else
{
add_mysql_nnv("semantics","noun1",$noun1,"noun2",$noun2,"verb",$verb,"weighting",$weight+1);
}
}

}

}

function semantics_print($c) {
foreach($c as $a) {

echo "\n\rwhere %noun1% typeof ".$a[0]."";
echo "\n\rwhere %noun2% typeof ".$a[1]."";
echo "\n\rwhere %verb% typeof ".$a[2]."";

}
}