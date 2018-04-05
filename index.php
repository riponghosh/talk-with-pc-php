<?php
/* Catbot version 0.3.3 - An HTML-based Chatterbot.
Copyright (C) 2006 Cody Jung

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

Read the full license in the file LICENSE that should've been
included in the package.
*/
?>
<html>
<head>
<title>Talk to PC</title>
<link rel="StyleSheet" type="text/css" href="catbotcss.css">

<?php

//////////////////////
//////Settings////////
/////////////////////

if (file_exists("config.php")){

include("config.php");	

} else {
	$configured = "0";
}

///////////////////////////////

if($configured != "1"){
echo 'Please be sure to run the <a href="setup.php">setup</a>.<br />If you already have run it, make sure that <b>$configured</b> is set to TRUE in config.php.';
exit();
} else {


//Connect to MySQL
// We do this before everything else, because it's used throughout the script.
$link = mysqli_connect($host, $username, $password)
   or die("<b>Can't connect to the database.</b>");
// Select the correct Database
mysqli_select_db($link,$database);

//Check to see if there's a user-inputed message. If not, assume that we just opened the window, and display "Connected!" 
if (isset($_POST['msg'])) {
$usermessage = $_POST['msg'];
$default = FALSE;
} else {
$usermessage = 'Connected!';
$default = TRUE;
}

//Don't allow HTML to be used
//Note that I'm not using striptags - because I want to keep any text that's inside tags (to prevent accidental deletion)
$htmlcharacters = array("<", ">", "&amp;", "&lt;", "&gt;", "&");
$usermessage = str_replace($htmlcharacters, "", $usermessage);

//Strip out the slashes
$msg = stripslashes($usermessage);

//Check if the input starts with an exclamation point -- Menu command
$isexpoint = $msg{0};

//If it's a command, do nothing. Otherwise take out a few characters and convert it to lowercase.
//Note - Make sure that you don't change the case of commands,
//Some rely on base64, which is case-sensitive.
//Also, we're removing punctuation for non-commands (easier string comparison)
if ($isexpoint == "!"){
} else {
$badthings = array("=", "#", "~", "!", "?", ".", ",", "<", ">", "/", ";", ":", '"', "'", "[", "]", "{", "}", "@", "$", "%", "^", "&", "*", "(", ")", "-", "_", "+", "|", "`");
$msg = str_replace($badthings, "", $msg);
$msg = mb_strtolower($msg);
}

//Converts contractions to full words, as well as taking out some common misspellings.
//Keep them in this order (for the most part), otherwise things get messed up, and...blah.
//For example, if "won't" was after "n't", then it would convert to "wo not" instead of "wouldn't". 
$contractions = array("can't", "won't", "n't", "'ll", "'d", "'re", "'ve", "i'm", "it's", "he's", "she's", "what's", "who's", " u ", " r ", " ur ", " im ");
$fullwords = array("cannot", "will not", " not", " will", " would", " are", " have", "i am", "it is", "he is", "she is", "what is", "who is", " you ", " are ", " you are ", " i am ");
$msg = str_replace($contractions, $fullwords, $msg);

//Explode the input and count the number of words - Used for other commands, don't delete this.
//I'm not using str_word_count because...well...I don't like it.
$msgarray = explode(" ", $msg);
$words = count($msgarray);



//If the input is a Command, include menu.php and forgo the regular database connection/string comparison crap.
if ($isexpoint == "!" && $allowcommands == TRUE) {
include 'commands/menu.php';
//Also, set the post that the last reply was a command. (This is for the reply addition system).
$command = 1;
} elseif ($isexpoint == "!" && $allowcommands == FALSE) {
$catbotreply = "Commands have been disabled.";
} else {


$command = 0;


//Since the input isn't a command, then
// Query the Database

//First, check for an exact match to the database.
$equery = "SELECT * FROM `replies` WHERE `trigger` = '$msg';";
$exact = mysqli_query($link,$equery);
//If it returns a row, use that.
if(mysqli_num_rows($exact) !== 0){
$result = $exact or die('Query failed: ' . mysql_error());
} else {
//Otherwise, go with a "Best match" method.
$query = "SELECT *, MATCH (`trigger`) AGAINST ('$msg') AS score FROM `replies` WHERE MATCH (`trigger`) AGAINST ('$msg');";
$result = mysqli_query($link,$query) or die('Query failed: ' . mysql_error());
}

$replyarray = mysqli_fetch_assoc($result);

//Check if someone has asked something. If not, assume they've just opened the window, and say "Connected!" back to them.
if (isset($_POST['msg'])) {
$catbotreply = $replyarray['reply'];
} else {
$catbotreply = 'Connected!';
}

//If there's no reply found, choose a random autoreply.
$noreplyfound = array('...', 'lol', 'I see.', 'Er...', '?', 'What?');

//If CatBot can't find the reply, and the userinput is only 1 word, repeat the word with a question mark (to make him sound a little more intelligent.)
//If it's more than one word, use the auto-replies from above.
if($catbotreply == "" && $words == 1){
$catbotreply = ucfirst($msg) . "?";
} elseif($catbotreply == "") {
$catbotreply = $noreplyfound[rand(0,5)];
}

//If the message is more than 20 words, warn them.
if ($words > 20){
$catbotreply = "Whoa! This isn't an email! You don't need to type so much!";
}

}

/* End of Command/talk seperation */

//Reduce CatBot's replies down the same way we did before. (For "learning")
$simplecatbot = mb_strtolower($catbotreply);
$simplecatbot = str_replace($contractions, $fullwords, $simplecatbot);
$badthings = array("!", "?", ".", ",");
$simplecatbot = str_replace($badthings, "", $simplecatbot);


//See if there was the last response from catbot.
if (isset($_POST["lastcatbot"])){
$lastcatbot = $_POST["lastcatbot"];
}else{
$lastcatbot = "Connect!";
}

//See if the last response was to a command
if(isset($_POST["lastwascomm"])){
$lastwascomm = $_POST["lastwascomm"];
}else{
$lastwascomm = 0;
}

//If this time isn't a command, and last time wasn't a command, and it's not the default message, log the user's reply.

if ($command == 0 && $lastwascomm == 0 && $default == FALSE){

//We've already connected to the database
//Check to see if the current trigger/response is already in the 'pending replies' database.
$query = "SELECT * FROM `pending` WHERE `trigger` = '$lastcatbot' AND `reply` = '$usermessage';";
$result = mysqli_query($link,$query);
$alreadythere = mysqli_fetch_assoc($result);
if ($alreadythere == FALSE){
//If it's a new reply, add it into the pending database.
$query = "INSERT INTO `pending` ( `trigger` , `reply` , `rnumber` ) VALUES ('$lastcatbot', '$usermessage', '1');";
mysqli_query($link,$query);

} else {
//Otherwise, increment the rnumber by 1
$query = "UPDATE `pending` SET rnumber=rnumber+1 WHERE `trigger` = '$lastcatbot';";
$result = mysqli_query($link,$query);

//See if there are any replies that have been used at least 3 times.
//If they have, move them from the Pending database to the regular database.
//Replies learned this way have a usercontrib value of '3'
$query = "SELECT * FROM `pending` WHERE `rnumber` > 2;";
$result = mysqli_query($link,$query);
$overtwo = mysqli_fetch_assoc($result);


if ($overtwo == FALSE){
} else {
$transfertrigger = $overtwo['trigger'];
$transferreply = $overtwo['reply'];
$rid = $overtwo['rid'];
$query = "INSERT INTO `replies` ( `trigger`, `reply`, `usercontrib` ) VALUES ('$transfertrigger', '$transferreply', '3');";
mysqli_query($link,$query);

$query = "DELETE FROM `pending` WHERE `rid` = '$rid';";
mysqli_query($link,$query);
}

}

}

/* ---- Logging ----- */

//Check if it isn't the default message. If it is, start logging.
if($logging == FALSE || $default == TRUE || $command == TRUE){
}else{
//Get the Date/Time for the timestamp
$timestamp = date('M j, Y g:i.s a');
//Get the user's IP
$userip = $_SERVER["REMOTE_ADDR"];
//Define the log filename and the log entry.
$logentry = $timestamp . " --- " . $userip . " --- " . $msg . " --- " . $catbotreply . "\r\n";
//Open the log file
$handle = fopen($logfile, 'ab');
//Write the log entry
fwrite($handle, $logentry);
fclose($handle);
} 
}
?>
</head>
<body onLoad="document.catbot.msg.focus()">

<div class="logo">
	<strong style="padding-top: 50px;font-size: 30px;vertical-align: middle;display: block;">Talk TO PC</strong>
</div>
<br /><br />

<div class="chatwindow">
<div align="center">

<form id="form_id" name="catbot" action="<?php echo($_SERVER["PHP_SELF"]); ?>" method="post">
<b>Msg:</b>
<input type="text" name="msg" size="30" class="text" id="output">
<input type="submit" name="submit" value="Send!" class="bttn" id="submit">
<input type="hidden" name="lastcatbot" value="<?php echo $simplecatbot; ?>">
<input type="hidden" name="lastwascomm" value="<?php echo $command; ?>">
</form>

<hr>
<div class="leftalign">
<b class="yousay">You say:</b> <?php echo stripslashes($usermessage); ?><br /><br />

<b class="catbotsays">PC replies:</b> <span id="main_reply"><?php echo $catbotreply;?></span><br /><br />
<p></p>
</div>
</div>
</div>

<br /><br />
<div class="copyrights">
To add a reply to Pc, use the !addreply command.
<hr>
</div>

<script type="text/javascript">
	var reply=document.getElementById("main_reply").innerHTML;
	var msg = new SpeechSynthesisUtterance(reply);
	window.speechSynthesis.speak(msg)




	var recognition = new webkitSpeechRecognition();
recognition.continuous = true;

var output = document.getElementById('output');
recognition.onresult = function(event) {
  output.value = event.results[0][0].transcript;
  document.getElementById('submit').click();
  
  
	sub();
};
function sub(){
	document.getElementById("form_id").submit();
}

recognition.start();

// setTimeout(function(){ window.location['reload']();}, 10000);
</script>
</body>
</html>