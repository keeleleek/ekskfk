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
	 * Return an excerpt of an WAV file
	 * 
	 * Returns an excerpt from the WAV file $file beginning at 
	 * $start seconds and ending at $stop seconds.
	 * 
	 * @author Kristian Kankainen, MTÜ Keeleleek
	 * @version 1.0
	 * @package phpraat
	 * 
	 * @param $file path to WAV file
	 * @param $start start time in seconds
	 * @param $stop end time in seconds
	 * @return WAV file
	 */
	function extractWav( $file, $start, $stop ) {
		
	
	# first open the file we will extract from
	$sound = fopen( $file, 'rb'); # else die
	
	## RIFF HEADER CHUNK (12 BITS)
	# lets copy the first four bytes, which should be RIFF in ASCII character
	$RIFF['id'] = fread( $sound, 4 );
#	if( $RIFF['id'] != 'RIFF' ) {
#		echo "invalid file id";
#		die();
#	}
	
	# the next four bytes is the length of the original file, we'll keep it for proof checking
	$RIFF['size'] =  fread( $sound, 4);
	
	# now comes the format block, which for us should be WAVE
	$RIFF['format'] = fread( $sound, 4 );
#	if( $RIFF['format'] != 'WAVE' ) {
#		echo "invalid file id";
#		die();
#	}
	
	## FORMAT CHUNK 
	$FORMAT['id'] = fread( $sound, 4 ); # should contain the letters 'fmt '
	$FORMAT['size'] = fread( $sound, 4 ); # should be 16 for PCM (it shows the size of the FORMAT chunk, eg the DATA chunk should start after this amount of bits (16)
	$FORMAT['format'] = fread( $sound, 2 ); # should be 1 (meaning no compression)
	$FORMAT['num_channels'] = fread( $sound, 2 ); # should be 1 for our files
#	$FORMAT['num_channels'] = pack('v', 1);
	$FORMAT['sample_rate'] = fread( $sound, 4 ); # should be 44100
	$FORMAT['byte_rate'] = fread( $sound, 4 ); # should be 88200   SampleRate * NumChannels * BitsPerSample/8
	$FORMAT['block_align'] = fread( $sound, 2 ); # NumChannels * BitsPerSample/8
	$FORMAT['bits_per_sample'] = fread( $sound, 2 ); #  8 bits = 8, 16 bits = 16, etc.
	
	
	## DATA CHUNK #####
	$DATA['id'] = fread( $sound, 4 ); # should contain the letters 'data'
	$DATA['size'] = fread( $sound, 4 ); # the size of the rest of the data chunk
	
	# the rest of the file is the actual data
	# now we have to find the convert the start and stop value from seconds to bits
	# bit = sample_rate * second
	$sample_rate = unpack( 'V', $FORMAT['byte_rate']); $sample_rate = $sample_rate[1];
	# the sound is arranged in blocks in the file, we can't break a block in any way (will scramble the sound)
	# to do this, we cheat with this formula floor(TIME_IN_SEC / BITS_PER_SAMPLE, 3) * BITS_PER_SAMPLE
	$bits_per_sample = unpack( 'v', $FORMAT['bits_per_sample']); $bits_per_sample = $bits_per_sample[1];
	#$start_bit_rel = $sample_rate * (  $start / $bits_per_sample  );
	#$stop_bit_rel = $sample_rate * (  $stop / $bits_per_sample  );
	#$start_bit_rel = $sample_rate * ( $bits_per_sample * floor($start / $bits_per_sample) );
	#$stop_bit_rel = $sample_rate * ( $bits_per_sample * floor($stop / $bits_per_sample) );
	$start_bit_rel = floor($start * 88200 / 8) * 8;
	$stop_bit_rel = floor($stop * 88200 / 8) * 8;
	# these are relative, if we add 44 bits, we should get absolute values
	
	# the difference is the new $DATA['size']
	$data_size = $stop_bit_rel - $start_bit_rel;
	$DATA['size'] = pack('V', $data_size);
	# and the RIFF size is the (data size  + 8) + (format size + 8) + 4
	$format_size = unpack( 'V', $FORMAT['size']); $format_size = $format_size[1];
	$riff_size = $data_size + 8 + $format_size + 8 + 4;
	$RIFF['size'] = pack('V', $riff_size);
	
	
	#ob_start(); # turn on output buffering
	# now we have to jump to the start position
	#fseek($sound, $start_bit_rel);
	# and get the data until the stop positon
	#while( !feof($sound)) {
	#$DATA['data'] = fread($sound, $start_bit_rel - $stop_bit_rel);
	#	echo $buffer;
	#}
	$DATA['data'] = file_get_contents($file, FILE_BINARY, NULL, ($start_bit_rel + 44), ($stop_bit_rel - $start_bit_rel));
	#ob_end_clean(); # turn off output buffering
	#ob_flush();
	#flush();
	
	# if everything is fine, let's print the file
	if( $RIFF['id'] == 'RIFF' and $RIFF['format'] == 'WAVE' ) {
		# turn on output buffering so that the file won't get distorted some how
		ob_start();
		
		header('Content-Type: audio/x-wav');
		header("Content-Description: File Transfer"); 
		header("Content-Transfer-Encoding: binary"); 
		header('Content-Disposition: attachment; filename="ekskfk_' . $_GET['text'] . '_' . $_GET['result'] . '.wav"'); # preg_replace('/\W/i', '', $_GET['text'])
		header("Cache-Control: no-cache, must-revalidate");
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
		
		echo $RIFF['id'];
		echo $RIFF['size'];
		echo $RIFF['format'];
	
		echo $FORMAT['id'];
		echo $FORMAT['size'];
		echo $FORMAT['format'];
		echo $FORMAT['num_channels'];
		echo $FORMAT['sample_rate'];
		echo $FORMAT['byte_rate'];
		echo $FORMAT['block_align'];
		echo $FORMAT['bits_per_sample'];
		
		echo $DATA['id'];
		echo $DATA['size'];
		echo $DATA['data'];
		
		# turn off output buffering, so that the client will get the file, and nothing but the _whole_ file
		ob_end_flush();
		
	} else { # the file isn't a correct wave file
		# let's print a 404 file not found
		#header('Content-Type: audio/x-wav');
		#header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
		echo '404';
	}
# we're finished, let's close the sound file
	fclose( $sound );
	
}

/**
 * The program starts here!
 * 
 * Wav extracting works by first executing a search the same way as the
 * search program does (the code is copied, but should some day get
 * refactorized). If the search finds any results, the time data is
 * used for extracting an excerpt of length $LENGTH from the wav file.
 * 
 * Inspiration was probably found from this article:
 * Perkins, Phillip. 2005. Create an audio stitching tool in PHP. TechRepublic.
 *  http://www.techrepublic.com/article/create-an-audio-stitching-tool-in-php/
 * 
 * @author Kristian Kankainen, MTÜ Keeleleek
 * @version 1.0
 * @package phpraat
 * @param $_GET['text'] The search string
 * @param $_GET['submit'] The submit button
 * @return Wav file or 404
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
		#$search_query .= ' ORDER BY cv';
		$search_query .= ' LIMIT ' . ($_GET['result'] - 1) . ', 1';
		
		
		# query the search database
		$result = $search->query($search_query);
		
		# print the results (actually, here we will do the textgrid extraction
		$row = $result->fetch_array(MYSQLI_ASSOC);
			# xmiddle is the time for the middle of the word (segment)
			$xmiddle = ($row['xmin'] + $row['xmax']) / 2;
			# the start time for the segment to extract
			$xstart = $xmiddle - ($LENGTH / 2);
	#		if($xstart < 0) {$xstart = 0;}
			# the end time for the segment to extract
			$xstop = $xmiddle + ($LENGTH / 2);
	#		if($xstop < 0) {$xstop = 0;}
			
		extractWav('/storage/www/html/temp/kristian/ekskfk/' . str_replace('.TextGrid', '.wav', $row['file']), $xstart, $xstop);
		#extractWav('/storage/www/html/temp/kristian/ekskfk/' . str_replace('.TextGrid', '.wav', 'SKK0/SKK001-003_M.TextGrid'), 1.02, 5);
		#	echo str_replace('.TextGrid', '.wav', $row['file']) . ' ' . $xstart . ' ' . $xstop;
		#print_r($row);
	}


?>
