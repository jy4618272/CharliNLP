<?PHP

$queries = 0;

function disconnect_from_mysql() {
global $connection;
//mysql_close($connection);
//unset($connection);
//$connection = null;
}

register_shutdown_function("disconnect_from_mysql");

$connection = mysql_pconnect("127.0.0.1","root","PASSWORD") or die("unable to connect");

mysql_select_db("jaya") or die ("Unable to select database!"); 

class mysql {

function all() {


//$query = "INSERT INTO `words` VALUES (0, '".$phonetic."', '".mysql_real_escape_string($name)."', '".mysql_real_escape_string($data)."');";

$args = func_get_args();

$query = "select * from `".$args[0]."` limit 0,1000"; 

$GLOBALS["queries"]++;

$result = mysql_query($query);

if (!$result) {
 $message = 'Invalid query: ' . mysql_error() . "\n";
 $message .= 'Whole query: ' . $query;
 die($message);
}

if (mysql_num_rows($result) == 0) {
return array();
}

$results = array();

while ($row = mysql_fetch_assoc($result)) {
$results[]=$row;
}

mysql_free_result($result);

return $results;

}

function get() {


//$query = "INSERT INTO `words` VALUES (0, '".$phonetic."', '".mysql_real_escape_string($name)."', '".mysql_real_escape_string($data)."');";

$args = func_get_args();

$query = "select * from `".$args[0]."` where ".$args[1]." like '".mysql_real_escape_string($args[2])."' limit 0,1000"; 

$GLOBALS["queries"]++;

$result = mysql_query($query);

if (!$result) {
 $message = 'Invalid query: ' . mysql_error() . "\n";
 $message .= 'Whole query: ' . $query;
 die($message);
}

if (mysql_num_rows($result) == 0) {
return array();
}

$results = array();

while ($row = mysql_fetch_assoc($result)) {
$results[]=$row;
}

mysql_free_result($result);

return $results;

}

function set() {
//UPDATE `jaya`.`words` SET `word`='!Kungg' WHERE `uid`='6701';
$args = func_get_args();

$query = "UPDATE `".$args[0]."` SET `".$args[1]."`='".mysql_real_escape_string($args[2])."' where `".$args[3]."`='".mysql_real_escape_string($args[4])."'"; 

$GLOBALS["queries"]++;

$result = mysql_query($query);

if (!$result) {
 $message = 'Invalid query: ' . mysql_error() . "\n";
 $message .= 'Whole query: ' . $query;
 die($message);
}

return true;

}

function add() {

$args = func_get_args();

$add = "";

$table = $args[0];
unset($args[0]);

foreach($args as $arg) {
if(!is_string($arg)) {
$add.="".mysql_real_escape_string($arg).", ";
}
else
{
$add.="'".mysql_real_escape_string($arg)."', ";
}
}

$add = substr($add,0,-2);

$query = "INSERT INTO `".$table."` VALUES (".$add.");";

$GLOBALS["queries"]++;

$result = mysql_query($query);

// Check result
// This shows the actual query sent to MySQL, and the error. Useful for debugging.
if (!$result) {
 $message = 'Invalid query: ' . mysql_error() . "\n";
 $message .= 'Whole query: ' . $query;
 echo($message);
}
}

}