<?PHP

//Create a database class object. This can be called statically or by dynamically
//create objects using this class.

class database {

//Define these variables as private and static
//private means they are inaccessible from outside of the object
//and public means they are avaliable outside the scope of the class.
private $results;
private $table;
public $mysql;
static $fk = array();
public $length;

//This creates a "Database" object, which will store the results set returned from
//the database.

function __construct($results,$table) {
//We are just going to store the results:
$this->results = $results;
//The results...
$this->table = $table;
//And count the number of records:
$this->length = count($results);
}


//This function will get the name and value of the primary key:

function getprimary() {
//Get the current result set:
$result = current($this->results);
//Get all the keys from this result set:
$keys = array_keys($result);
//Return an array, where the first result is $keys[0]
//which will be the first key in the database, which
//is the string of the primary key field, this is followed
//by reset(result) which returns the value of that primary
//key.
return array($keys[0],reset($result));
}


//Gets the value of a key from the current row of the table:

function __get($key) {
//Get the current row:
$result = current($this->results);
//Check to see if the value is defined:
if(isset($result[$key])) {

//We need to find out whether the field is a foreign key - 
//if it is, then it would make it easier to code, if
//this class returned an object of the record that is being
//pointed to

//we can do a search in the self::$fk;
foreach(self::$fk as $fk) {
//See if this foreign key record, matches the key that the user is requesting:
if($fk["TABLE_NAME"] == $this->table && $fk["COLUMN_NAME"] == $key) {
//It does match, so see what foreign key this matches to:
//Return a query object, which will contain the referenced information.
$GLOBALS["queries"]++;
return database::query($fk["REFERENCED_TABLE_NAME"],$fk["REFERENCED_COLUMN_NAME"]."=".$result[$key]);
}
}

//Return the value:
return $result[$key];
}
else
{
//If it doesn't exist, return null.
return null;
}
}

//Sets the value of a key in the current row of the table,
//and pushes this change to the database.

function __toString() {
//Get the primary key,
$primary = $this->getprimary();
//store it as part of the query:
return $primary[0]."=".$primary[1]."";
}

function __set($key,$value) {
//Create a pointer to the current record:
//We need to use different code to achieve this:
$record=&$this->records[key($this->records)];
//We can't change a primary key, so double check to make sure that this isn't a primary key:
//Using the list syntax allows me to map the first element of the array to $primary:
list($primary,$primaryval) = $this->getprimary();
//If the program is trying to alter the primary key, then exit the function now:
if($primary==$key) {return false;}
//otherwise, update the record's key:
$record[$key] = $value;
//We need to push this change the the database,
//Whilst this slows the program down more than just pushing updates
//at the end, the delay is not noticable.
$result = mysql_query("UPDATE `".$this->table."` SET `".$key."`='".mysql_real_escape_string($value)."' WHERE `".$primary."`='".$primaryval."'");
$GLOBALS["queries"]++;
//If an error occured, display this error, and then close.
if (!$result) {
    $message  = 'Invalid query: ' . mysql_error() . "\n";
    $message .= 'Whole query: ' . $query;
    die($message);
}
//the update was pushed to mysql okay, return true.
return true;
}

//This function will delete a row in the database:

function remove() {
//First we get the primary key,
list($primary,$primaryval) = $this->getprimary();
//Now submit the mysql:
$result = mysql_query("DELETE FROM `".$this->table."` WHERE `".$primary."`='".$primaryval."'");

$GLOBALS["queries"]++;

if (!$result) {
    $message  = 'Invalid query: ' . mysql_error() . "\n";
    $message .= 'Whole query: ' . $query;
    die($message);
}
//Clear this row from php's memory:
unset($this->records[key($this->records)]);
//Return true, it's all good.
return true;
}

static function close() {
//Close the connection to the database.
mysql_close();
}

function next() {
return next($this->results);
}

static function add() {
//Add an element to the database:
//First get the functions arguments:
$args = func_get_args();
//Now get the first argument this will be the database name
$database = $args[0];

//Remove this argument:
unset($args[0]);

//create an array of keys and values:
$keys = array();
$values = array();

//Foreach argument:
foreach($args as $arg) {
//If it is a class object:
if(@get_class($arg)=="database") {
//Get the primary key and value,
$primary = $arg->getprimary();
//and store in the array
$keys[] = "`".$primary[0]."`";
$values[] ="'".mysql_real_escape_string($primary[1])."'";
}
else
{
//Otherwise split up the value, and
list($key,$value) = explode("=",$arg,2);
//store the value and key in the array.
$keys[] = "`".$key."`";
$values[] ="'".$value."'";
}
}

//format the query into mysql:
$query = "INSERT INTO `".$database."` (".implode(", ",$keys).") VALUES (".implode(", ",$values).")";
$result = mysql_query($query);
$GLOBALS["queries"]++;

//Get the new auto number of this record:
$auto_number = mysql_insert_id();

//Check to see if an error occured, if it's not display an error on screen:
if (!$result) {
    $message  = 'Invalid query: ' . mysql_error() . "\n";
    $message .= 'Whole query: ' . $query;
	echo $message;
    //die($message);
}

//Get a list of primary keys:
$query = "SHOW KEYS FROM `".$database."`";
$result = mysql_query($query);
$GLOBALS["queries"]++;

//Check to see if an error occured, if it's not display an error on screen:
if (!$result) {
    $message  = 'Invalid query: ' . mysql_error() . "\n";
    $message .= 'Whole query: ' . $query;
	echo $message;
    //die($message);
}

//Get the first primary key,
$primary = mysql_fetch_array($result,MYSQL_ASSOC);

//Get the key name:
$primary = $primary["Column_name"];

//Query the database, to get the newly created object:
//and return this:
return database::query($database,$primary."=".$auto_number);

}

static function query() {
//Get the arguments passed to the script:
$args = func_get_args();
//Store the database name in memory
$database = $args[0];

//Delete the argument from the array:
unset($args[0]);

//Create an indexed array of the arguments:
$query = array();

//Loop through all arguments,
foreach($args as $arg) {
//If the argument is an object:
if(@get_class($arg)=="database") {
//Get the primary key,
$primary = $arg->getprimary();
//store it as part of the query:
$query[] = $primary[0]."='".mysql_real_escape_string($primary[1])."'";
}
else
{
//If it's not an object, then split the input up,
list($key,$value) = explode("=",$arg,2);
//And add this to the query:
$value=$key." LIKE '".mysql_real_escape_string($value)."'";
$query[] = $value;
}
}

//Send this query off:
$query = "SELECT * FROM `".$database."` WHERE ".implode(" and ",$query)."";
$result = mysql_query($query);
$GLOBALS["queries"]++;

//If an error occured, show the error on screen:
if (!$result) {
    $message  = 'Invalid query: ' . mysql_error() . "\n";
    $message .= 'Whole query: ' . $query;
	echo $message;
    //die($message);
}

//Create an array to store the results in:
$results_array = array();

//While there is a record to return:
while ($row = mysql_fetch_array($result,MYSQL_ASSOC)) {
//Store the file in the array:
$results_array[] = $row;
}

//If there are records,
if(count($results_array)>0) {
//Return the record:
//Pass the database name into the newly created object.
return new database($results_array,$database);
}
else
{
//Otherwise if there were no records, return false.
return false;
}

}

}

?>