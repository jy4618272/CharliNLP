<?PHP

require_once "data_freebase.php";
require_once "data_wordnet.php";

//this is a function that will just remove special {}[] characters from a string:
function rm_special_chars($string) {
return str_replace(array("{","}","[","]"),"",$string);
}

//This gets the information about a word:

class data {

function get($word,$next) {

//echo "Lookup '$word'\n\r";

$super_types = mysql::all("words_types");

foreach($super_types as $stype) {

if(preg_match("•"."^".$stype["pattern"].'$'."•",$word)==1) {
//This matches a super type.
//A super type is a type that isn't a word.
//This needs to be based on regex, rather than anything else,
//because these are "not words", they are "types"
//Ie. 100 or £520.50 or www.bing.com or oliver.jones@httpstuff.co.uk

$stype = json_decode($stype["data"],true);

$stype["nextobj"] = $next;
$stype["word"] = $word;

//print_r($stype);

return array($stype);

}

}


$wiktionary = mysql::get("words","word",$word);

$records = array();
$no_wordnet = array();

//Wordnet filler is just an array of all the stuff
//from wiktionary we want to "insert" into the 
//wordnet records.
$wordnet_filler = array();

if(count($wiktionary)==0) {
$word = str_replace(array("-","'"), "", $word);

$suffix = null;

$s_special = array();
$s_associations = array();

//We're going to split up the text:
for($a = 0;$a<strlen($word);$a++) {
$start = substr($word,0,$a);
$end = "-".substr($word,$a);
$wiktionary = mysql::get("words","word",$end);
if(count($wiktionary)>0) {
//We have a suffix:
$suffix = $wiktionary;
$wiktionary = mysql::get("words","word",$end);
if(count($wiktionary)>0) {
$word = $start;
break;
}
}
}

}


foreach($wiktionary as &$definition1) {
$definition = $definition1["data"];

$definition = json_decode($definition,true);
$type = $definition[0];
preg_match_all(":\{\{([^\}]+)\}\}:",$definition[1],$special2);
preg_match_all(":\[\[([^\]]+)\]\]:",$definition[1],$associations2);
$special = array();
$associations = array();
foreach($special2[1] as $str) {
$special = array_merge($special,explode("|",$str));
}
foreach($associations2[1] as $str) {
$associations = array_merge($associations,explode("|",$str));
}

if($suffix!==null) {
foreach($suffix as $definition) {

preg_match_all(":\[\[([^\]]+)\]\]:",$definition[1],$associations3);
$associations2 = array();
foreach($associations3[1] as $str) {
$associations2 = array_merge($associations2,explode("|",$str));
}

if(in_array($type,$associations2)) {
$special = array_merge($special,$associations2);
}

}
}

$special = array_map("strtolower", $special);
$associations = array_map("strtolower", $associations);

$special = array_map("rm_special_chars", $special);
$associations = array_map("rm_special_chars", $associations);

//We have a type, special indicators, amongst other things.
//We need to parse this information so that we can make use of it.
//Wiktionary is a mess, the syntax for the pages is so losely followed it's hard to get any
//useful information out of it, also because it's open source, it has been
//swampped by out dated useless information, for example run has 64 entrys (
//that's just within the first 500000 records) Using wiktionary actually
//increases the amount of work the computer has to do, but there is far more
//indepth (read: less acurate) information on wiktionary. We'll pull in information from wordnet,
//to supliment this wiktionary information, because the data is far more
//specific, requires less processing, is more acurate. In fact we'll
//disregard most wiktionary information unless we have no choice.
$str = $definition[1];
//$type = ...
$nextobj = $next;
$type = strtolower($type);

$s_special[$type] = array_merge((array)$s_special[$type],$special);
$s_associations[$type] = array_merge((array)$s_associations[$type],$associations);

$record = null;

switch($type) {
case "conjunction":
//This is just a word like "and"
$record["type"] = "conjunction";
$record["nextobj"] = $nextobj;
break;
case "interjection":
//This is just an interjection.
$record["type"] = "interjection";
$record["nextobj"] = $nextobj;
break;
case "{{initialism}}":
case "abbreviation":
case "{{abbreviation}}":
case "contraction":

if(!in_array("slang",$special)&&!in_array("internet",$special)&&!in_array("internet slang",$special)&&!in_array("text",$special)) {
//echo "skip";
continue;
}

//This is an acronym
//$record["type"] = "acronym";

//We need to work out from wiktionary what it stands for:
//echo $str;
$str = str_replace(array("[","]"),"",$str);
if(strpos("{",$str)<5) {
preg_match(":\} ([\w\s]+):",$str,$match);
}
else
{
preg_match(":\# ([\w\s]+):",$str,$match);
}
$words = $match[1];
if(strpos("{",$words)!==false) {
//Couldn't find out what the abbr stands for?
$record["type"] = "skip";
$record["nextobj"] = $nextobj;
//$definition = null;
continue;
}

$words = split(" ",$words);

$words = array_reverse($words);
ksort($words);

$wdobj = $nextobj;

foreach($words as $wd) {
$wdobj = new word($wd,$wdobj);
$record["type"] = "skip";
$record["nextobj"] = $wdobj;
}

break;
//case "proper noun":
case "noun":
if(in_array("abbreviation of",$special)) {
$abbr = array_search("abbreviation of",$special);
$abbr = $special[$abbr+1];
$word = new word($abbr,$nextobj);
$record["type"] = "skip";
$record["nextobj"] = $word;
}
if(in_array("misspelling of",$special)) {
$abbr = array_search("misspelling of",$special);
$abbr = $special[$abbr+1];
$word = new word($abbr,$nextobj);
$record["type"] = "skip";
$record["nextobj"] = $word;
}

//Test to see if it's a person:
if(in_array("given name",$associations)||in_array("given name",$special)) {
//This is a person...
$record["type"] = "noun";
$record["subtype"] = "person";
$record["nextobj"] = $nextobj;
$record["gender"] = "";

if(in_array("female",$associations)||in_array("female",$special)) {
$record["gender"] = "female";
}
else
{
if(in_array("male",$associations)||in_array("male",$special)) {
$record["gender"] = "male";
}
}
}
break;
case "verb":
if(in_array("abbreviation of",$special)) {
$abbr = array_search("abbreviation of",$special);
$abbr = $special[$abbr+1];
$word = new word($abbr,$nextobj);
$record["type"] = "skip";
$record["nextobj"] = $word;
}
if(in_array("misspelling of",$special)) {
$abbr = array_search("misspelling of",$special);
$abbr = $special[$abbr+1];
$word = new word($abbr,$nextobj);
$record["type"] = "skip";
$record["nextobj"] = $word;
}
break;
case "adverb":
//Adverbs we dont care about, we're going to get these from wordnet.
break;
case "adjective":
//Adjectives we dont care about either.
break;
case "preposition":
case "postposition":
case "article":
case "pronoun":
$record["type"] = $type;
$record["nextobj"] = $nextobj;
break;
default:
//echo "Found unusual type for '$word': $type\n\r";
continue;
break;
}

switch($type) {
case "proper noun":
case "noun":
case "verb":
case "adverb":
case "adjective":
//This is a noun: don't care much for wiktionary data
//as it's mostly useless due to the lack of quality
//enough moaning, as if we can't find anything in wordnet
//we will use the information stored in this wiktionary
//data.
$no_record["type"] = $type;
$no_record["source"] = "wiktionary";
$no_wordnet[] = $no_record;
break;
default:
//I included this default because i don't want an error
//to occur. I'm not sure whether PHP triggers an error
//in such an event, but i don't want to run the risk.
break;
}

if($record===null) {

}
else
{
$records[] = $record;
}

}

//echo $records;

$wordnet = data_wordnet::get($word,$next);

$records = array_merge($wordnet,$records);

if(count($records)==0) {
//there's nothing in wordnet, and nothing useful in wiktionary
//so we're going to have to check the no_wordnet array
//which stores all the information that's probably wrong
//from wiktionary, but we're going to have to roll with this
//anyway.

if(count($no_wordnet)>0) {
return $no_wordnet;
}

//We didn't find anything at all on this word, so just skip it:
$record["type"] = "skip";
$record["nextobj"] = $nextobj;
$record["source"] = "na";
$records[] = $record;
}

$records["special"] = $s_special;
$records["associations"] = $s_associations;

return $records;

}

}

