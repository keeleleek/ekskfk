#!/usr/bin/perl
use utf8;
use strict;
use Benchmark ':hireswallclock';
use bignum;
#use warnings; # should show warnings only for unitialized value which is okay because they are used in defined statements

my $id = 1; # holds the database unique primary number (id)
my $timer_start = new Benchmark;
my $timer_end = undef;
my $line = undef;
my $line_nr = 0;
my $file_name = undef;
my $tiers_size = undef;
my $item_nr = undef;
my $item_name = undef;
my $interval_xmax = undef;
my $interval_xmin = undef;
my $interval_text = undef;
my $interval_nr = undef;
my $interval_size = undef;
my $phonemes = undef;
my $sampas = undef;

sub quotemysql {
    # escapes single backslashes \ with another \    that means   \ => \\
    # this is because of MySQLs LOAD DATA INFILE standard behaviour
    $_ = $_[0];
    s#[\\]#\\$&#g;
    return $_;
}


open (SQL_FILE, '>:encoding(UTF-8)', '/tmp/ekskfk/ekskfk_textgrid.sql') or die $!;
binmode( STDOUT, ':utf8'  ) or die $!;

#old print SQL_FILE "TRUNCATE TABLE ekskfk.textgrid;\n"; # everything must go

foreach (@ARGV) { # for each file
    # check if file is proper
    $file_name = $_;
    unless ($file_name =~ /\.TextGrid$/) {
	print "# skipping non TextGrid file " . $file_name . "\n";
	next;
    }
    open (FILE, '<:encoding(UTF-16)',$file_name) or die $!;
    $line_nr = 0;
    $tiers_size = undef; $item_nr = undef; $interval_xmax = undef; $interval_xmin = undef; $interval_text = undef; $interval_nr = undef; $interval_size = undef; $phonemes = undef; $sampas = undef;

    print "processing file: $file_name\n";

    while ($line = <FILE>) { # for each line
	$line_nr++;
	if ($line =~ /^size = (\d+)/) { # size = \d    ie how many tiers exist
	    $tiers_size = $1;
	}
	elsif ($line =~ /\s*intervals: size = (\d+)/) { # size = \d    ie how many intervals exist
	    $interval_size = $1;
	    if ($item_name eq 'häälikud') {
		$phonemes = $interval_size;
	    }
	    elsif ($item_name eq 'cv') {
		$sampas = $interval_size;
	    }
	}
	elsif ($line =~ /\s*item\s+\[(\d+)\]:/) { # item [\d]:
	    $item_nr = $1;
	    if ($item_nr >= 1 and defined $interval_text) { # if not first item
		if ($interval_size ne $interval_nr) {
		    print "# WARNING [" . $file_name . ":" . $line_nr . "] last interval doesn't match with intervals size (in item " . $item_nr . " name " . $item_name . ")\n";
		}
		# this prints the last interval (of the previous item)
# old		print SQL_FILE 'INSERT INTO ekskfk.textgrid SET file="' . quotemysql($file_name) . '", item="' . quotemysql($item_name) . '", xmin="' . quotemysql($interval_xmin) . '", xmax="' . quotemysql($interval_xmax) . '", text="' . quotemysql($interval_text) . '";' . "\n";
		print SQL_FILE quotemysql($id) . "\t" . quotemysql($file_name) . "\t" . quotemysql($interval_xmin) . "\t" . quotemysql($interval_xmax) . "\t" . quotemysql($item_name) . "\t" . quotemysql($interval_text) . "\n";
		$id += 1; # increment the unique primary number for the next database insert
		$interval_nr = undef;
		$interval_text = undef;
		$interval_xmax = undef;
		$interval_xmin = undef;
	    }
	}
	elsif ($line =~ /\s*name\s+=\s+"(.*)"/) { # name = ".*"
	    $item_name = lc $1; # lower case name
	    if ($item_name =~ /s[oõ]na,?/)    { $item_name = 'word'; print "# WARNING '$item_name' was wrongly spelled as '$&' in file: $file_name\n"; }
	    elsif ($item_name =~ /silbid.*/)  { $item_name = 'syllable'; print "# WARNING '$item_name' was wrongly spelled as '$&' in file: $file_name\n"; }
	    elsif ($item_name =~ /häälik.*/)  { $item_name = 'sampa'; print "# WARNING '$item_name' was wrongly spelled as '$&' in file: $file_name\n"; }
	    elsif ($item_name =~ /häälelaad/) { $item_name = 'phonation'; print "# WARNING '$item_name' was wrongly spelled as '$&' in file: $file_name\n"; }
	    elsif ($item_name =~ /muu/)       { $item_name = 'other'; print "# WARNING '$item_name' was wrongly spelled as '$&' in file: $file_name\n"; }
	    elsif ($item_name =~ /paralingvistiline/) { $item_name = 'paralinguistic'; print "# WARNING '$item_name' was wrongly spelled as '$&' in file: $file_name\n"; }
	    elsif ($item_name =~ /lausungid/) { $item_name = 'utterance'; print "# WARNING '$item_name' was wrongly spelled as '$&' in file: $file_name\n"; }
	    elsif ($item_name =~ /taktid/)    { $item_name = 'foot'; print "# WARNING '$item_name' was wrongly spelled as '$&' in file: $file_name\n"; }
	    elsif ($item_name =~ /morf.*/)    { $item_name = 'morph'; print "# WARNING '$item_name' was wrongly spelled as '$&' in file: $file_name\n"; }
	    elsif ($item_name =~ /tp/)        { $item_name = 'TP'; print "# WARNING '$item_name' was wrongly spelled as '$&' in file: $file_name\n"; }
	    elsif ($item_name =~ /cv/)        { $item_name = 'cv'; print "# WARNING '$item_name' was wrongly spelled as '$&' in file: $file_name\n"; }
	    else { print "# ERROR item name $item_name is not recognized by the database!\n" }
	    
	    unless (defined $item_nr) { # this should be very odd
		print "# ERROR [" . $file_name . ":" . $line_nr . "]  undefined item_nr has name???\n";
	    }
	}
	elsif ($line =~ /\s*xmin = (\d*\.?\d+)/) { # xmin = float
	    $interval_xmin = $1;
	}
	elsif ($line =~ /\s*xmax = (\d*\.?\d+)/) { # xmax = float
	    $interval_xmax = $1;
	}
	elsif ($line =~ /\s*text = "(.*)"/) { # text = ".*"
	    $interval_text = $1;
	}
	elsif ($line =~ /\s*intervals\s+\[(\d+)\]:/) { # intervals [\d]:
	    if ( $interval_nr >= 1 and defined $interval_text) {
# old		print SQL_FILE 'INSERT INTO ekskfk.textgrid SET file="' . quotemysql($file_name) . '", item="' . quotemysql($item_name) . '", xmin="' . quotemysql($interval_xmin) . '", xmax="' . quotemysql($interval_xmax) . '", text="' . quotemysql($interval_text) . '";' . "\n";
		print SQL_FILE quotemysql($id) . "\t" . quotemysql($file_name) . "\t" . quotemysql($interval_xmin) . "\t" . quotemysql($interval_xmax) . "\t" . quotemysql($item_name) . "\t" . quotemysql($interval_text) . "\n";
		$id += 1; # increment the unique primary number for the next database insert
	    }
	    $interval_nr = $1;
	    $interval_text = undef; $interval_xmin = undef; $interval_xmax = undef;
	}
    }
    # end of file
    if ($tiers_size ne $item_nr) {
	print "# WARNING [" . $file_name . ":" . $line_nr . "] last item nr doesn't match with items tiers size given\n";
    }
    unless ($phonemes eq $sampas) {
	print "# WARNING häälikud and cv mismatch in file: $file_name\n";
    }
    print "\n";
    close FILE;
}

# finally insert the command that extracts id, word, sampa, cv, file, xmin, xmax into ekskfk.search db
# first, insert all words into search db
##print SQL_FILE "INSERT INTO ekskfk.search (id, word, file, xmin, xmax) SELECT id, text, file, xmin, xmax FROM ekskfk.textgrid WHERE item='word';\n";

# secondly, fetch the sampa transcription for all words in search table into tmp table
##INSERT INTO ekskfk.search (sampa) 
##SELECT GROUP_CONCAT(tg.text ORDER BY tg.id SEPARATOR '') as sampa
##FROM ekskfk.tmp AS s, ekskfk.textgrid AS tg
##WHERE tg.xmin BETWEEN s.xmin AND s.xmax-0.000000000000001 AND s.file=tg.file AND tg.item='sampa'
##GROUP BY s.id

#SELECT s.id, s.word, GROUP_CONCAT(tg.text ORDER BY tg.id SEPARATOR '') as sampa
#FROM ekskfk.search AS s, ekskfk.textgrid AS tg
#WHERE tg.xmin BETWEEN s.xmin AND s.xmax-0.000000000000001 AND s.file=tg.file AND tg.item='sampa'
#GROUP BY s.xmin LIMIT 50






# My example is updating a payments table with a most recent payment_status row id.
#Payments table has a unique "id", and a "cur_stat_id" which is what we are trying to populate with the most recent matching record from a related table (payment_status).
#
#INSERT INTO payments (id, cur_stat_id) 
#SELECT id, x.statid FROM payments 
#JOIN (SELECT payment_id, max(id) as statid FROM payment_status GROUP BY payment_id) as x ON payments.id=x.payment_id ON DUPLICATE KEY UPDATE payments.cur_stat_id=x.statid 

close SQL_FILE;

$timer_end = new Benchmark;
print "\nexecution time took " . timestr( timediff($timer_end, $timer_start) ) . "\n\n";

# proof of concept
# matching last item nr with tier size WORKS
