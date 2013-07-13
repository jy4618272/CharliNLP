<?PHP

require_once "data_wiktionary.php";

//These are the scripts that actually grab the information from:
//	- Wordnet = data_wordnet.php
//	- Freebase = data_freebase.php
//	- Wiktionary = data.php

//	- Cache = ...
//		Removed cache. Data was "too processed" in order to be useful in subsequent requests.

//	- Admin's Cache = data_cache.php


//This just contains the "word" class, this is a dynamic "linked list"
//style construct which contains the information from the above sources.
//It allows the object to dynamically alter both the information the object
// returns but also the structure of the overall linked list.

class word {

public $information;
private $limitby;
private $certain;
public $refined;
public $word;

function __construct($word,$next=false) {
//The word is a string of the word.
//$next is the next word in the sentence, else false.

$this->word = $word;

$this->limitby = array();

$this->information = array();

$this->certain = array();

//We need to get information:

if($word!=="") {

$this->information = data::get($word,$next);

}
else
{
//echo "no word.";
}

//We now have a huge array containing lots of information, except we only want a small refined list
//of information...

$this->refined = array();

//print_r($this->information["special"]);
//sleep(1);

$special = $this->information["special"];
$association = $this->information["association"];

unset($this->information["association"]);
unset($this->information["special"]);

foreach($this->information as &$record) {
//$record["word"] = $record["word"];
$record["special"] = $special[strtolower($record["type"])];
$record["association"] = $association[strtolower($record["type"])];
switch(strtolower($record["type"])) {

//case "skip":
//Goes straight into refined information.
//$this->refined[]=$record;
//break;

case "adj":
case "adjective":
$this->refined["adj"]["subtype"][] = $record["subtype"];
$this->refined["adj"]["source"][] = $record["source"];
$this->refined["adj"]["word"] = $this->word;
$this->refined["adj"]["type"] = "adj";
$this->refined["adj"]["nextobj"] = $record["nextobj"];
break;

case "adv":
case "adverb":
$this->refined["adv"]["subtype"][] = $record["subtype"];
$this->refined["adv"]["source"][] = $record["source"];
$this->refined["adv"]["word"] = $this->word;
$this->refined["adv"]["type"] = "adv";
$this->refined["adv"]["nextobj"] = $record["nextobj"];
break;

case "proper noun":
$this->refined["noun"]["proper"] = true;
case "noun":
$keep["source"] = $record["source"];
$keep["similar"] = $record["similar"];
$keep["priority"] = $record["priority"];
$keep["subtype"] = $record["subtype"];
$this->refined["noun"]["senses"][] = $keep;
$this->refined["noun"]["similar"][] = $record["similar"];
$this->refined["noun"]["source"][] = $record["source"];
$this->refined["noun"]["subtype"][] = $record["subtype"];
$this->refined["noun"]["type"] = "noun";
$this->refined["noun"]["word"] = $this->word;
$this->refined["noun"]["nextobj"] = $record["nextobj"];
break;

case "verb":
$keep["source"] = $record["source"];
$keep["similar"] = $record["similar"];
$keep["priority"] = $record["priority"];
$keep["subtype"] = $record["subtype"];
$this->refined["verb"]["senses"][] = $keep;
$this->refined["verb"]["similar"][] = $record["similar"];
$this->refined["verb"]["source"][] = $record["source"];
$this->refined["verb"]["subtype"][] = $record["subtype"];
$this->refined["verb"]["type"] = "verb";
$this->refined["verb"]["word"] = $this->word;
if(is_array($record["special"])==false) {
//echo "{$this->word}";
//print_r($this);
}
if(@in_array("past of",$record["special"])) {
$this->refined["verb"]["past"] = $record["special"][array_search("past of",$record["special"])+1];
}
if(@in_array("intransitive",$record["special"])) {
$this->refined["verb"]["intransitive"] = true;
}
if(@in_array("transitive",$record["special"])) {
$this->refined["verb"]["transitive"] = true;
}
if($this->refined["verb"]["transitive"]==false&&$this->refined["verb"]["intransitive"]==false) {
$this->refined["verb"]["intransitive"] = true;
$this->refined["verb"]["transitive"] = true;
}
$this->refined["verb"]["nextobj"] = $record["nextobj"];
break;

default:
if(isset($record["nextobj"])) {
//probably a super type, best to just stick this on the refined data list.
unset($record["special"]);
unset($record["association"]);
if($record["type"]!="skip") {
$this->refined[$record["type"]] = $record;
$this->refined[$record["type"]]["word"] = $this->word;
}
else
{
$this->refined[] = $record;
}
}
break;

}
}



}


function combi_current($step=0) {
//This will return a combination of the words and stuff:
//need to be careful not to get a stack overflow here.
//as it "bubbles" down the entire linked list.

if(count($this->information)==0) {
return false;
}

$current = current($this->information);
$current["self"] = $this;
$current["key"] = key($this->information);

if($current["nextobj"]=="end.") {
return array($current);
}

if($current["type"]=="skip") {
echo get_class($current["nextobj"]);
return $current["nextobj"]->combi_current($step);
}

$next = $current["nextobj"]->combi_current($step+1);
$current_combi = array_merge(array($current),$next);

return $current_combi;

}

function combi_next() {
//This bubbles down the array, and basically moves the
//internal pointer down slightly, to the next combination.

if(count($this->information)==0) {
return false;
}

$current = current($this->information);

//echo key($this->information).",";

if($current["nextobj"]=="end.") {
$next = next($this->information);
if($next===false) {
reset($this->information);
return false;
}
else
{
return true;
}
}
else
{
if(get_class($current["nextobj"])!=="word") {
//echo "none word found.";
//echo print_r($current);
//ie();
return true;
}
$rt = $current["nextobj"]->combi_next();
if($rt===false) {
$next = next($this->information);
if($next==false) {
reset($this->information);
return false;
}
else
{
return true;
}
}
else
{
return true;
}
}

return true;

}

function __toString() {
return $this->word;
}

function __sleep() {
return array("word");
}

}