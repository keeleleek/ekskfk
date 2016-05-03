#!/bin/sh

############################################################
##
##  This loading script is part of the TextgridSearch program
##  more information at: http://github.com/keeleleek/TextgridSearch
##  Author and copyright Kristian Kankainen 2016
##
########################################


############################################################
##  CONFIGURATION
########################################
## database settings
DBUSERNAME="" # database username
DBPASSWORD="" # database password
DBCHARSET="utf8" # database default-character-set

## path to folder with original wav and textgrid files
PRAATFILES="/path/to/wav/and/textgrid/files"
## path to the file where this scripts' information is output
INFOFILE="ekskfk_uuendus.info"

############################################################
##  END OF CONFIGURATION
########################################

# Output current time
echo "Current time is now " `date --iso-8601=seconds` > $INFOFILE
SECONDS=0

# Synchronise the Textgrid files marked public in EKSKFK_avalik_nousolek.Table
perl uuenda_textgridid.pl >> $INFOFILE
echo "Finished with script: uuenda_textgridid.pl"

# lippuse praati skript, mis asendab mõned originaalfailid
/storage/data2/fon_db/praat_linux/praat /storage/data2/fon_db/EKSKFK/SKK_nimede_varjamine.skript >> $INFOFILE
echo 'praat nimede varjamine valmis'

# Convert the Textgrid files to a MySQL table
perl textgrid2sql.pl SKK*/*.TextGrid >> $INFOFILE
echo "Finished with script: textgrid2sql.pl"

# Generate composite search information from the MySQL file
#perl extract_ekskfk_search.pl >> $INFOFILE
perl extract_ekskfk_search_with_morphs.pl >> $INFOFILE
echo "Finished with script: extrach_ekskfk_search_with_morphs.pl"

# Delete the second (swappable) database contents
mysql --user=$DBUSERNAME --password=$DBPASSWORD --default-character-set=$DBCHARSET ekskfk -e "TRUNCATE TABLE ekskfk2.textgrid; TRUNCATE TABLE ekskfk2.search"
echo "Deleted the second (swappable) database"

# Load the new Textgrid data into the database
mysql --user=$DBUSERNAME --password=DBPASSWORD --default-character-set=$DBCHARSET ekskfk -e "LOAD DATA INFILE '/tmp/ekskfk/ekskfk_textgrid.sql' INTO TABLE ekskfk2.textgrid; SHOW WARNINGS; LOAD DATA INFILE '/tmp/ekskfk/ekskfk_search.sql' INTO TABLE ekskfk2.search; SHOW WARNINGS;" >> $INFOFILE
echo "Loaded new Textgrids and search data into the database."

# Synchronise the sound files available to the web server
rsync -avs -i -h ./SKK0/*.wav /storage/www/html/temp/kristian/ekskfk/SKK0/
rsync -avs -i -h ./SKK1/*.wav /storage/www/html/temp/kristian/ekskfk/SKK1/
rsync -avs -i -h ./SKK2/*.wav /storage/www/html/temp/kristian/ekskfk/SKK2/
echo "Synchronised the WAV files available to the web server."

# Output time and elapsed time to the information file
echo "$(($duration / 60)) minutes and $(($duration % 60)) seconds elapsed."
echo "Current time is now " `date --iso-8601=seconds`
echo "That's all, have a good day!"

