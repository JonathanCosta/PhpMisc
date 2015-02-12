<?php

//die('Disabled -> listing.php!  To prevent overwrites.');

// Define the names of your database and table
$sNewDb='TestNewiTunes';
$sNewTable='MusicTracks';

//Connect to add new database
$servername = "localhost";
$username = "username";
$password = "password";

// Create connection
$conn = new mysqli($servername, $username, $password);
// Check connection
if ($conn->connect_error) 
{
    die("Connection failed: " . $conn->connect_error . "<br />");
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS ".$sNewDb;
if ($conn->query($sql) === TRUE) 
{
    echo "Database created successfully <br />";
} 
else 
{
    echo "Error creating database: " . $conn->error . "<br />";
}

$conn->close();

// Connect to new db table
$con=mysqli_connect($servername, $username, $password, $sNewDb);

// Check connection
if (mysqli_connect_errno()) 
{
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
}

// This requires a library file (StreemeItunesTrackParser.class.php)located in the same folder
// Also copy your itunes xml file to this folder
require_once(dirname(__FILE__) . '/StreemeItunesTrackParser.class.php');
$file_path = dirname(__FILE__) . '/iTunesMusicLibrary.xml';
$parser = new StreemeItunesTrackParser($file_path);
$aFields=array();
$i=0;
$iRow=0;
$sFields='';
$aInserts=array();

// Loop through each xml row
while($row = $parser->getTrack()) 
{
	// Not all rows are audio tracks.  Only process the ones that are applicable
	if($row['Kind'] == 'MPEG audio file' || 
	$row['Kind'] == 'Purchased AAC audio file' || 
	$row['Kind'] == 'AAC audio file' || 
	$row['Kind'] == 'WAV audio file' ) 
	{
		// This mostly builds the table structure
		foreach($row as $key => $item)
		{
			$newkey=str_replace(' ','_',$key);
			if($newkey != $key)
			{
				$row[$newkey]=$item;
				unset($row[$key]);
				$key=$newkey;
			}

			if(array_key_exists($key, $aFields))
			{
				$sFieldtype=is_numeric($item)?'int':'varchar';
				if($aFields[$key]['length'] < strlen($item))
				{
					$aFields[$key]['length'] = ceil(strlen($item)/10)*10;
				}
			}
			else
			{
				$sFieldtype=is_numeric($item)?'int':'varchar';
				$aFields[$key] = array('length'=>ceil(strlen($item)/10)*10,'type'=>$sFieldtype);
				$sFields .= $i . '  ' . strtolower($key) . '<br />';
				$i++;
			}

			// This holds your track data
			$aInserts[$iRow][$key]=mysqli_real_escape_string ($con,$item);
		}
	}
	$iRow++;
}

// Now that we have the table structure available we can create the table
// Start forming the query string
$query='CREATE TABLE IF NOT EXISTS `'.$sNewTable.'` (';
foreach($aFields as $key => $aField)
{
	$query.="`".$key."` ".$aField['type']."(".$aField['length'].") NULL ,";
}
$query.= "UNIQUE KEY `Track_ID` (`Track_ID`)";
$query.=') DEFAULT CHARSET=latin1;';

// The query sting is ready. Perform the create table db action
$bGood = mysqli_query($con, $query);
echo $bGood ? 'Success <br />' : 'Fail <br />';

// Define the first part of the data insert query
$qryFields='';
$insertQry1="INSERT INTO ".$sNewTable." (";
foreach($aFields as $key => $aField)
{
	$qryFields.=$key.',';
}
$insertQry1.=substr($qryFields,0,-1).') VALUES ';

// First part of the insert query string is done
// Now for the actual data to be inserted
// Define some vars
$sQValues='';
$firstField=reset(array_keys($aFields));
$lastField=end(array_keys($aFields));
$lastItem=end(array_keys($aInserts));
$n=1;

// Set up a loop to insert blocks of 100 records at a time
foreach($aInserts as $key=>$aInsertChild)
{

	// Loop through defined table fields so none are left out
	// Build one record
	foreach($aFields as $fkey => $aField)
	{
		$sQValues.=$fkey==$firstField?'(':'';
		$sQValues.=isset($aInsertChild[$fkey])?key($aField)=='int'?$aInsertChild[$fkey]:"'".$aInsertChild[$fkey]."'":"''";
		$sQValues.=$fkey==$lastField?')':', ';
	}

	// Okay we have 100 records built (or the last block of records).  Lets save them to the table
	if($n % 100 == 0 || $key==$lastItem)
	{
		$sQValues.=';';
		$bGood = mysqli_query($con, $insertQry1.$sQValues.';');
		echo $bGood ? 'Success ' : 'Fail ';
		echo ($n/100).'<br />';
		$sQValues='';
	}
	else
	{
		$sQValues.=',';
	}

	$n++;
}

// Close the db connection
mysqli_close($con);

?>