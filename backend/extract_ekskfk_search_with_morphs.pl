#!/usr/bin/perl
use utf8;
use strict;
use Benchmark ':hireswallclock';
use warnings;
use bignum;

my $timer_start = new Benchmark;
my $timer_end = undef;

my $line_nr = 0;
my $prev_file = 'jibrish';

#my $row = {};

my $id = 1;
my $file = undef;
my $xmin = undef;
my $xmax = undef;
my $name = undef;
my $text = undef;

my @words = ();
my @sampas = ();
my @cvs = ();
my @morphs = ();

my $cv_text = '';
my $sampa_text = '';

sub extract {
    my $AoH = shift;
    my $xmin = shift;
    my $xmax = shift;
    my $text = '';
    my @i_to_delete = ();

    for my $i (0 .. $#$AoH) {
	my $row = $AoH->[$i];

	# we want to find the tagmemes inside of our word
	if ($row->{xmin} >= $xmin and $row->{xmin} < $xmax) {
	    $text .= $row->{text};
	    push @i_to_delete, $i;
	} elsif ($row->{xmax} > $xmax) {
	    # the file is ordered, we don't have to search any further
	    last;
	}
    }

    # now we'll pop away what we found from the AoH
    # basically we splice out subsequent indexes in one go
    # and the poor lonelies have to go home alone
    # if nothing is to be popped, we don't pop
    unless (scalar @i_to_delete) {
	return $text;
    }

    @i_to_delete = sort {$b <=> $a} @i_to_delete; # descending order
    my $length = 1;
    my $offset = undef;
    for my $i (0 .. $#i_to_delete) {
	if ($i < $#i_to_delete) { # not the last index
	    if (($i_to_delete[$i] - $i_to_delete[$i+1]) == 1) { # the two are subsequent
		$length++;
	    } else { # they are not subsequent, split current
		$offset = $i_to_delete[$i];
		splice(@$AoH, $offset, $length);
		$length = 1;
	    }
	} else { # last index
	    $offset = $i_to_delete[$i];
	    splice(@$AoH, $offset, $length);
	    $length = 1;
	}
    }

    # now return the tagmemes we extracted
    return $text;
}


sub trim($) {
    $_ = shift;
    s/^\s+//;
    s/\s+$//;
    s/  / /g;
    s/  / /g;
    return $_;
}

binmode(STDOUT,   ':utf8' ) or die "Couldn't open STDOUT with UTF-8: $!";
open   (SQL_FILE, '>:encoding(UTF-8)', '/tmp/ekskfk/ekskfk_search.sql') or die "Faili ei saadud tekitada: $!";
open   (IN_FILE,  '<:encoding(UTF-8)', '/tmp/ekskfk/ekskfk_textgrid.sql') or die "Faili ei ole olemas: $!";

# for each row
while (my $line = <IN_FILE>) { 
    $line_nr++;

    unless ($line =~ /(.*?)\t(.*?)\t(.*?)\t(.*?)\t(.*?)\t(.*?)\n/) {
        die "syntax error on line $line_nr (expecting 6 cols)!\n";
    } else { # we have a 'valid' line, save it in corresponding array
	my $row = {};
	$row->{line}  = $1;
	$row->{file}  = $2;
	$row->{xmin}  = $3;
	$row->{xmax}  = $4;
	$row->{layer} = $5;
	$row->{text}  = $6;
	
        if ($row->{file} eq $prev_file or $prev_file eq 'jibrish') {
            $prev_file = $row->{file};
	    # we skip empties right away, comments are dealt with later on
	    if ($row->{layer} eq 'word' and $row->{text} ne '') {
		push @words, $row;
	    } elsif ( $row->{layer} eq 'sampa' and $row->{text} ne '') {
		push @sampas, $row;
	    } elsif ( $row->{layer} eq 'cv' and $row->{text} ne '') {
		push @cvs, $row;
	    } elsif ( $row->{layer} eq 'morph'){ # and $row->{text} ne '') { # comments have empty morphs?
		push @morphs, $row;
	    }
        }
        if ($row->{file} ne $prev_file or eof IN_FILE) {
            # new file, first extract cv and sampa with same xmin and xmax
            # then print out in sql file, then start with new file

            print "extracting from: $prev_file\n";
            print 'found: ' . scalar @words . ' words (' . scalar @morphs . ' morphs): ' . scalar @sampas . ' sampas, ' . scalar @cvs . " cvs\n";
	    if (scalar @cvs ne scalar @sampas) {
		print "WARNING: cv and sampa mismatch!\n";
	    }
	    if (scalar @words ne scalar @morphs) {
		print "WARNING: word and morph mismatch!\n";
	    }
 
            for my $word_cnt ( 0 .. $#words ) { # for each word,
		my %word = %{$words[$word_cnt]};

                # if word is not a word (eg it is a pause or comment) then skip it
                if ( $word{text} =~ /^[.#]/) {
                    next; # skip non-word
                }

                # now let's clean up the word (if it includes comments)
                if ( $word{text} =~ m$(.+?)[?#/]$g ) {
                    $word{text} = $1;
                }

		# now find and extract the cvs and sampas for the current word
                my $sampa_text = extract(\@sampas, $word{xmin}, $word{xmax});
		my $cv_text = extract(\@cvs, $word{xmin}, $word{xmax});
		
		# the morphemes should be 1-1 mapped with the words
#		print $morphs[$word_cnt];
                my $morph_text = extract(\@morphs, $word{xmin}, $word{xmax});

		# now print the word and its cvs and sampas to the sql file
                print SQL_FILE "$id\t$prev_file\t$word{text}\t$word{xmin}\t$word{xmax}\t$sampa_text\t$cv_text\t$morph_text\n";
                $id++;
                $cv_text = '';
                $sampa_text = '';
                $morph_text = '';

            }

            # check if all cvs and sampas has been extracted
#	    for my $href (@sampas) {
#		print "{ ";
#		for my $key (keys %$href) {
#		    print "$key: $href->{$key} ";
#		}
#		print "}\n";
#	    }

            # then flush words and start again
            @words = ();
            @morphs = ();
            @sampas = ();
            @cvs = ();
            $prev_file = 'jibrish';
        }
    } 
} # EOF

$timer_end = new Benchmark;
print "\nexecution time took " . timestr( timediff($timer_end, $timer_start) ) . "\n\n";
