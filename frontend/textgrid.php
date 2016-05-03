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



	/**
	 * TextGrid extracting works by first executing a search the same 
	 * way as the search program does (the code is copied, but should
	 * some day get refactorized). If the search finds any results, the 
	 * time data is used for extracting an excerpt of length $LENGTH 
	 * from the TextGrid database.
	 * 
	 * @author Kristian Kankainen, MTÜ Keeleleek
	 * @version 1.0
	 * @package phpraat
	 * @param $_GET['text'] The search string
	 * @param $_GET['submit'] The submit button
	 * @return TextGrid file or 404
	 */
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
		
		# to make everything a bit more secure agains .* rippings of the corpus, we organize it by cv
		#$search_query .= ' ORDER BY \'cv\'';
		$search_query .= ' LIMIT ' . ($_GET['result'] - 1) . ', 1';
		
		#echo $search_query;
		# query the search database
		$result = $search->query($search_query);
		
		
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
				# for each result
				while ( $textgrid_row = $textgrid_result->fetch_array(MYSQLI_ASSOC) ) {
					# sort the results into textgrid layers
					$textgrid_layer[$textgrid_row['item']][] = $textgrid_row;
				}
				
				# after collecting all info, we have print it out
				header('Content-Type: text/plain');
				header("Content-Description: File Transfer"); 
				header('Content-Disposition: attachment; filename="ekskfk_' . $_GET['text'] . '_' . $_GET['result'] . '.TextGrid"'); # preg_replace('/\W/i', '', $_GET['text'])
				header("Cache-Control: no-cache, must-revalidate");
				header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
				
				# first the TextGrid header
				echo "File type = \"ooTextFile\"\n";
				echo "Object class = \"TextGrid\"\n";
				echo "\n";
				echo "xmin = 0\n";
				echo "xmax = " . $LENGTH . "\n"; # 3 sekundit
				echo "tiers? <exists>\n";
				echo "size = " . count($textgrid_layer) . "\n";
				echo "item []:\n";
				
				$item_counter = 0;
				
				# turn on output buffering, so the file won't be distorted some how
				ob_start();
				
				foreach ( $textgrid_layer as $layer_name => $layer_content ) {
					echo "\titem [" . ++$item_counter . "]:\n";
					echo "\t\tclass = \"IntervalTier\"\n";
					echo "\t\tname = \"" . $layer_name . "\"\n";
					echo "\t\txmin = 0\n";
					echo "\t\txmax = " . $LENGTH . "\n"; # 3 sekundit
					echo "\t\tintervals: size = " . count($layer_content) . "\n";
					$interval_counter = 0;
					
					foreach( $layer_content as $x => $y ) {
						echo "\t\tintervals [" . ++$interval_counter . "]:\n";
						
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
						
						echo "\t\t\txmin = " . ($y['xmin'] - $xstart) . "\n";
						echo "\t\t\txmax = " . ($y['xmax'] - $xstart) . "\n"; # insert $sound_file_length
						echo "\t\t\ttext = \"" . $y['text'] . "\"\n"; 
						
					} # end of textgrid layer segments
				} # end of textgrid layers
				
				echo  "\n";
				
				# turn off output buffering so that the client can have the complete file
				ob_end_flush();
				
				# now we have to clear the textgrid_layer for the next round
				$textgrid_layer = array();
				
			} else {
				echo 'no textgrid was found';
			}
		}
	}
?>
