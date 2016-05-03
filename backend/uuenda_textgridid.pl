#!/usr/bin/perl
use utf8;
use strict;
#use Benchmark; #':hireswallclock';
use warnings;
#use bignum;
use Data::Dumper;
use File::Copy;
use Cwd;
binmode( STDOUT, ':utf8'  ) or die $!;

my @nousolek;
my @tmp;
my $foldername;
my $filename;

open( TABLE, '<', "../EKSKFK_avalik_nousolek.Table" ) or die $!;

###
### kopeerib kõik textgridid ning wavid avalik_otsingusse mis ei ole märgitud "ei.*" EKSKFK_avalik_nousolek.Table'is
###


# read the file into a simple array of arrays
foreach my $line (<TABLE>) {
	push @nousolek, [ split (/\t/, $line) ];
}

# for each line in the file
for my $i ( 1 .. $#nousolek ) {
	$foldername = "SKK$nousolek[$i][0]";
	$filename = $nousolek[$i][1];
	# if it isn't marked with anything negative, then copy it
	unless ( $nousolek[$i][2] =~ /ei.*/ ) {
		print "copying $filename to avalik ... ";
		copy("../$foldername/$filename.TextGrid", "$foldername/$filename.TextGrid") or die "Copy failed: $! ($foldername/$filename.TextGrid)";
		copy("../$foldername/$filename.wav", "$foldername/$filename.wav") or die "Copy failed: $! ($foldername/$filename.wav)";
		# if the file's syllable layer isn't finished, we are to remove it 
		if ( $nousolek[$i][5] =~ /ei.*/ ) {
			# remove syllables fram textgrid
			print "without syllables ... ";
			system("/storage/data2/fon_db/praat_linux/praat", 
						 "/storage/data2/fonoteek/foneetilised_andmebaasid/EKSKFK/avalik_otsing/SKK_eemalda_silbikiht.praat", 
						 getcwd() . "/$foldername/$filename.TextGrid");
		}
		print "done!\n";
	}
	else { # the file is marked negative and should be removed
		unlink "$foldername/$filename.TextGrid";
		print "removed $foldername/$filename.TextGrid\n";
	}
}

#print Dumper(@nousolek);

