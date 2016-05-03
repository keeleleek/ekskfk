<?php

	/**
	 * Configuration variables
	 */
	$ALLOW_DEBUG_MSG = true;
	
	$LENGTH = 3; # textgrid (and sound) length in seconds
	$PPS = 200;  # Pixels Per Second (i.e. how wide is the annotation table)
	
	$DB_HOST = "localhost"; # the database server's host name
	$DB_USER = ""; # the user name (with search privileges)
	$DB_PASS = ""; # the password
	$DB_NAME = "ekskfk"; # the name of the database
	$DB_PORT = 3306; # the port number to connect to the database server
	$DB_SOCKET = "/var/lib/mysql/mysql.sock"; # the socket to be used to connect to the database server
	$DB_FLAGS = null; # connection options, read http://php.net/manual/en/mysqli.real-connect.php


	# turn on strict error reporting if debug is set
	if( $ALLOW_DEBUG_MSG and isset($_GET['debug'] )) {
       ini_set('display_errors',1);
       error_reporting(E_ALL|E_STRICT);
	
		print_r($_GET);
	}

	# set the internal encoding to UTF-8!
	iconv_set_encoding('all', 'UTF8');

?><!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
  <meta content="text/html; charset=utf-8" http-equiv="content-type">
  <title>Otsing EKSKFK-s</title>
  <link rel="stylesheet" type="text/css" href="ekskfk.css" />  
</head>

<body>
<form name="searchform" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="get">

<table style="text-align: left; width: 589px; height: 158px;" border="1"
cellpadding="2" cellspacing="2">
  <tbody>
    <tr>
      <td style="text-align: right;"></td>
      <td>EKSKFK otsimootor 0.8</td>
    </tr>
    <tr>
      <td style="text-align: right;">otsisõna</td>
      <td><input name="text" type="text" value="<?php echo $_GET['text'] ?>"></td>
    </tr>
    <tr><?php 
	
	# PRINT RADIO BUTTONS FOR SELECTING REGEXP SEARCHING 
	# sanity check for value (value on || off)
	#if( $_GET['regexp'] != 'off') {
	#	$_GET['regexp'] = 'on';
	#}
	#foreach( array( 'regulaaravaldis' => 'on', 'täpne' => 'off' ) as $key => $value) {
	#	printf("<td><input name=\"regexp\" value=\"%s\" type=\"radio\"%s>%s</td>\n ", $value, ($value == $_GET['regexp'] ) ? ' checked="checked"' : '', $key);
	#}
	# END OF PRINT RADIO BUTTONS FOR SELECTING REGEXP SEARCHING 
	
	?></tr>
    <tr>
      <td style="text-align: right; vertical-align: top;">otsitakse</td>
      <td><?php
	
	# PRINT RADIO BUTTONS FOR SELECT
	# sanity check for value
	if( $_GET['searchfor'] != 'word' and $_GET['searchfor'] != 'sampa' and $_GET['searchfor'] != 'cv' ) {
		$_GET['searchfor'] = 'word';
	}
	foreach( array( 'ortograafilist kuju' => 'word','SAMPA transkriptsiooni' => 'sampa', 'CV transkriptsiooni' => 'cv' ) as $key => $value) {
		printf("<input name=\"searchfor\" value=\"%s\" type=\"radio\"%s>%s<br>\n ", $value, ($value == $_GET['searchfor']) ? ' checked="checked"' : '', $key);
	}
	# END OF PRINT RADIO BUTTONS FOR SELECT
	
		?></td>
    </tr>
    <tr>
      <td style="text-align: right; vertical-align: top;">korpustest</td>
      <td><?php 
	
	# PRINT CHECK BUTTONS FOR SELECTING CORPUSES FOR SEARCHING 
	# sanity check for value 
	if( !isset($_GET['korpused']) or !is_array($_GET['korpused']) or count($_GET['korpused']) > 3 or count($_GET['korpused']) < 0) {
		$_GET['korpused'][0] = 'dialoogid';
		$_GET['korpused'][1] = 'monoloogid';
	} #else {
#		if ( isset($_GET['korpused'][2] ) ) { $_GET['korpused'][2] = 'välitööd'; }
#		if ( isset($_GET['korpused'][1] ) ) { $_GET['korpused'][1] = 'monoloogid'; }
#		if ( isset($_GET['korpused'][0] ) ) { $_GET['korpused'][0] = 'dialoogid'; }
#	}

	foreach( array( 'dialoogid' => 'dialoogid', 'monoloogid' => 'monoloogid' ) as $key => $value) { # , 'välitööd' => 'välitööd'
		printf("<input name=\"korpused[]\" value=\"%s\" type=\"checkbox\"%s>%s<br>\n ", $value, (in_array($value, $_GET['korpused']) ) ? ' checked="checked"' : '', $key);
	}
	# END OF PRINT RADIO BUTTONS FOR SELECTING REGEXP SEARCHING 
	
	?></td>
    </tr>
    <tr>
      <td style="text-align: right; vertical-align: top;">vastuseid</td>
      <td><?php
	
	# PRINT RADIO BUTTONS FOR LIMIT
	# sanity check for value
	if( !is_numeric($_GET['limit']) or $_GET['limit'] <= 0 or $_GET['limit'] > 200 ) {
		$_GET['limit'] = 50;
	}
	foreach( array(20, 30, 50, 100, 200) as $num ) {
			printf("<input name=\"limit\" value=\"%d\" type=\"radio\"%s>%d ", $num, ($num == $_GET['limit']) ? ' checked="checked"' : '', $num);
		}
	# END OF PRINT RADIO BUTTONS FOR LIMIT
	
		?></td>
    </tr>
    <tr>
      <td></td>
      <td>
        <input type="submit" name="submit" value="Otsi"></td>
    </tr>
  </tbody>
</table>

</form>

<?php

	if( isset( $_GET['submit'] ) && isset( $_GET['text'] ) ) {
		#######  PARAMETERS ###
		#$LENGTH = 3; # textgrid (and sound) length in seconds
		#$PPS = 200;     # Pixels Per Second
		###################
		$result_number = 0; # this is the counter for the wav file extractor
		
		# first we make some security checks to see if the data given is sane
		
		# a search is possible, make a connection to the ekskfk.search database
		$search = mysqli_init();
		$search->real_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT, $DB_SOCKET, $DB_FLAGS);
		if(mysqli_connect_errno()) {
			# if there were errors, we excuse ourselves and don't continue with the search
			echo "<p>Vabandame, andmebaasiga ühendamine läks nässu, proovige uuesti või kontakteeruge meiega (kristianPUNKTkankainenÄTgmailPUNKTcom)</p>\n";
			exit();
		}
		$search->set_charset("utf8"); // set character set to utf8
		
		# and lets connect to the ekskfk.textgrid database now as well
		$textgrid = mysqli_init();
		$textgrid->real_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT, $DB_SOCKET, $DB_FLAGS);
		if(mysqli_connect_errno()) {
			# if there were errors, we excuse ourselves and don't continue with the search
			echo "<p>Vabandame, andmebaasiga ühendamine läks nässu, proovige uuesti või kontakteeruge meiega (kristianPUNKTkankainenÄTgmailPUNKTcom)</p>\n";
			exit();
		}
		$textgrid->set_charset("utf8"); // set character set to utf8
		
		# no connection errors? then we continue with the search
		#######################################################
		# step 1                                                                                                                                                   #
		# pre-process the variables and make the search-string for the ekskfk.search database    #
		#######################################################
		$_GET['text'] = $search->real_escape_string($_GET['text']);
		# should we circumfix the searchstring with word-border marks?? if so, add that code here (ask Pärtel)
		if( $_GET['searchfor'] != 'word' and $_GET['searchfor'] != 'sampa' and $_GET['searchfor'] != 'cv' ) {
			$_GET['searchfor'] = 'word';
		}
		$search_query = 'SELECT * FROM search WHERE ' . $_GET['searchfor'] . ' ';
		#if( $_GET['regexp'] == 'on' ) {
			$search_query .= 'REGEXP "^' . $_GET['text'] . '$"';
		#} else {
		#	$search_query .= '= \'' . $_GET['text'] . '\'';
		#}
		
		# corpus selection
		# if both of the corpuses are selected, we exclude the third one
		#if( count($_GET['korpused']) == 2 ) {
		#	foreach( array_diff(array( 'dialoogid', 'monoloogid', 'välitööd' ), $_GET['korpused']) as $value ) {
				# print out the korpus not to search in 
		#		switch($value) {
		#			case 'dialoogid':
		#				$value = 0;
		#				break;
		#			case 'monoloogid':
		#				$value = 1;
		#				break;
		#			case 'välitööd':
		#				$value = 2;
		#				break;
		#		}
				$search_query .= ' AND \'file\' NOT LIKE \'SKK2%\'';
		#	}
		#}
		
		# to make everything a bit more secure agains .* rippings of the corpus, we organize it by cv
		#$search_query .= ' ORDER BY \'word\' DESC';
		
		$search_query .= ' LIMIT 0, ' . $_GET['limit'];
		
		# query the search database
		$result = $search->query($search_query);
		
		# if debug mode is set, print the search query
		if( isset($_GET['debug']) ) {
			echo $search_query;
		}
		
		# print some info of what was found
		echo "<br>leiti $result->num_rows rida andmebaasist<br><br>";
		
		# print the results (actually, here we will do the textgrid extraction
		while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
			# first some maths
			
			#this number holds the number for the wav file
			$result_number++;
			
			# xmiddle is the time for the middle of the word (segment)
			$xmiddle = ($row['xmin'] + $row['xmax']) / 2;
			# the start time for the segment to extract
			$xstart = $xmiddle - ($LENGTH / 2);
			# the end time for the segment to extract
			$xstop = $xmiddle + ($LENGTH / 2);
			
			# make the query to the textgrid database
			$textgrid_search_query = "SELECT * FROM `textgrid` WHERE `file` = '" . $row['file'] . "' AND `xmax` >= " . $xstart . " AND `xmin` < " . $xstop . " ORDER BY item, id";
			$textgrid_result = $textgrid->query($textgrid_search_query);
			
			# if we get some results (which we should, actually (due to the first search got some
			if( $textgrid_result->num_rows ) {
				# print the result number (for easy orientation)
				echo $result_number;
				
				# for each result, collect the textgrid layers
				while ( $textgrid_row = $textgrid_result->fetch_array(MYSQLI_ASSOC) ) {
					# sort the results into textgrid layers
					$textgrid_layer[$textgrid_row['item']][] = $textgrid_row;
				}
				
				# after collecting all info, we have print it out
				foreach ( $textgrid_layer as $layer_name => $layer_content ) {
					# the table width shall be 200 px per second
					echo "<table width=\"" . $LENGTH * $PPS . "px\" id=\"words\"><caption>" . $layer_name . "</caption><tr>\n"; 
					
					#print_r($layer_content);
					foreach( $layer_content as $x => $y ) {
						# the table cell width should be approximately as many hundred pixels, as the word is long in seconds
						# if the word (segment) falls outside our xstart or xstop values, we have to chop it to make fit
						if( $y['xmin'] < $xstart) {
							$y['xmin'] = $xstart;
							# $width = $y['xmax'] - $xstart;
							# lets hide or dim the text as well
							#$y['text'] = '';
						}
						if( $y['xmax'] > $xstop) {
							$y['xmax'] = $xstop;
							#$width = $xstop - $y['xmin'];
							# lets hide or dim the text as well
							#$y['text'] = '...';
						}
						# the width is in seconds, lets convert it to pixels (1 sec = 200 px), and also we have to remove one pixel for the table border!
						$width = round( ($y['xmax'] - $y['xmin']) * $PPS) - 1;
						$length_ms = round( ($y['xmax'] - $y['xmin']) * 1000, 0);
						
						# print the table cell for the segment
						echo "<td title=\"[" . $length_ms . ' ms] ' . $y['text'] . "\" width=\"$width\">";
						# if the text is a comment, we should dim it a bit
						#if (  ) {
							
						#}
						echo $y['text'] . "</td>\n";
					} # end of textgrid layer segments
					echo "</tr>\n</table>\n";
				} # end of textgrid layers
				# now let's print a ruler, for the user to see a millisecond scale
				echo "<table width=\"" . $LENGTH * $PPS . "px\" id=\"words\">\n<caption>milliseconds</caption><tr>\n"; 
				for( $i = 1; $i <= $LENGTH * 4; $i++) {
					$width_px = ($PPS / 4) - 1;
					$width_ms = $i / 4 * 1000;
					echo "<td width=\"$width_px\" title=\"$width_ms ms\">$width_ms</td>\n";
				}
				echo "</tr>\n</table>\n\n";
				
				
				# now we have to clear the textgrid_layer for the next round
				$textgrid_layer = array();
					
				# lets make a link to the sound file segment
				echo '<a href="wav.php?' . $_SERVER['QUERY_STRING'] . '&result=' . $result_number . '">laadi helilõik alla</a>';
				
				# lets make a link to the textgrid segment
				echo ' | <img src="praat_white.gif" height="12" width="12"><a href="textgrid.php?' . $_SERVER['QUERY_STRING'] . '&result=' . $result_number . '">laadi TextGrid alla</a>';
				echo '<hr>';
			} else {
				echo 'no textgrid was found';
			}
			echo '<br><br>';
		}
	
	
	
	}

?>

</body>
</html>
