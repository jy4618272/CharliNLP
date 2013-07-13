<?PHP

//This little script will (hopefully) when loaded via require:
//load and parse a text file with rules in (opted for text file because
//i just can't be bothered with another database, when it's 
//just over engineering)
//The system will keep these in memory.
//When a sentence structure is passed to the program it will
//be able to group similar words together in a group class.

class patterns {

public $arules = array();

function __construct($file) {
//static function = init.

$rules = file($file,FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
//We've loaded the file into an array.

//The format of the file is:
/*
--Repeat below for each rule--
	=group=		required static		at the start of each new "rule"
	noun		required dynamic	a word to label the new group with
	--Repeat below for each position in the group--
		\n		required static		a new line to symbolise the start of the rule's parameters
		type		required dynamic	a type that is part of this group
		|second type	not required dynamic	another type that could feature at this position
		{		required static		
		1		required dynamic	the minimum number of these types that can exist at this position
		,		not required static	
		100		not required dynamic	the max number of these types .......
		}		required static
	----
----

the system uses a sort of greedy regex matching, it won't try different combinations of numbers in the {}
because that would take a lot of processing power, that's why it only uses greedy matching.

patterns are applied in the order that they are listed in the rules file, and are applied from right to left.

*/

//Create a "shortcut"
$arules = array();

$label = "";

foreach($rules as $rule) {
if(strpos($rule,"#")===0) {
continue;
}
if(substr_count($rule,"=")==2) {
list(,$type,$label) = explode("=",$rule);
$pos = count($arules);
$arules[$pos]["label"] = $label;
continue;
}
if($label!=="") {
//We have a line with a rule on it: parse it:

$nrule = array();
list($types,$n) = explode("{",$rule);
$nrule["types"] = explode("|",$types);
list($nrule["min"],$nrule["max"]) = explode(",",str_replace("}","",$n));

if($nrule["max"]=="") {
$nrule["max"] = $nrule["min"];
}

if($nrule["min"]=="1"&&$nrule["max"]=="1"&&!isset($arules[$pos]["startat"])) {
$arules[$pos]["startat"] = count($arules[$pos])-1;
}

$arules[$pos][] = $nrule;

}
}

//print_r($arules);
//die();

//return $arules;

$this->arules = $arules;

}

function match($sentence) {
//Sneaky way of turning a recombine object, with variables,
//into a function recombine() whilst still being able to store
//variables out of "sight".

//This object takes an !array! of a combination of words,
//i think i called it a sense in the log, but this is completely
//the wrong word to use, as there are many senses associated with
//each word. ie. promotion can mean sales, and also a new job.

//It will output an array of grouped elements from the input array,
//labeled using a "type" index, from =group=label

$arules = $this->arules;

foreach($arules as $pos => $rule) {
$label = $rule["label"];
unset($rule["label"]);

//echo "applying rule $label\n";

for($start=0;$start<count($sentence);$start++) {

$lpos = $start;
$rpos = $start;

$compare = $rule["startat"];

$matches = 0;

for($pos=$start;$pos>=0;$pos--) {
//Start comparing backwards:
//echo "comparing {$sentence[$pos]['type']} to ".implode("|",$rule[$compare]["types"])."\n";
if(in_array($sentence[$pos]["type"],$rule[$compare]["types"])&&$matches<=$rule[$compare]["max"]) {
$matches++;
$lpos = $pos;
continue;
}
else
{
//It doesn't match, see if the number of matches was within the allowed range:
if($matches>=$rule[$compare]["min"]&&$matches<=$rule[$compare]["max"]) {
//It's okay move on:
$compare--;
if($compare<0) {break;}
$pos++;
$matches=0;
continue;
}
else
{
//it doesn't match. continue with the next start position.
continue 2;
}
}
}

for($compare--;$compare>0;$compare--) {
if($rule[$compare]["min"]>0) {
continue 2;
}
}

$compare = $rule["startat"];
$matches = 0;

for($pos=$start;$pos<=count($sentence)-1;$pos++) {
//Start comparing backwards:
if(in_array($sentence[$pos]["type"],$rule[$compare]["types"])&&$matches<=$rule["max"]) {
$matches++;
$rpos = $pos;
continue;
}
else
{
//It doesn't match, see if the number of matches was within the allowed range:
if($matches>=$rule[$compare]["min"]&&$matches<=$rule[$compare]["max"]) {
//It's okay move on:
$compare++;
if($compare>count($rule)-2) {break;}
$pos--;
$matches=0;
continue;
}
else
{
//it doesn't match. continue with the next start position.
continue 2;
}
}
}

for($compare++;$compare<count($rule)-2;$compare++) {
if($rule[$compare]["min"]>0) {
continue 2;
}
}

//echo "match\n";

//It all matches/
//echo "position $lpos to $rpos matches rule $label\n";
$positions[] = array("left"=>$lpos,"right"=>$rpos,"label"=>$label);

}

}

return $positions;

}

function apply($sentence) {
//This will apply a ruleset to a sentence, grouping and labeling the content.

$new_sentence = array();
$matches = $this->match($sentence);

foreach($matches as $match) {
for($start = $match["left"];$start<=$match["right"];$start++) {
if(!isset($sentence[$start])) {
continue 2;
}
}
for($start = $match["left"];$start<=$match["right"];$start++) {
$new_sentence[$match["left"]]["elements"][]=$sentence[$start];
$new_sentence[$match["left"]]["type"] = $match["label"];
unset($sentence[$start]);
}
}

//$new_sentence = array_merge($sentence,$new_sentence);
foreach($sentence as $key=>$value) {
$new_sentence[$key] = $value;
}
ksort($new_sentence);
$new_sentence = array_values($new_sentence);
return $new_sentence;

}

}



//load the rules:
$recombine = new patterns("patterns/recombination_logic.txt");
//print_r($arules);
//echo "k";
