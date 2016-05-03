# This script swaps the ekskfk database content with ekskfk2
# swapping a second time restores the previous state of the database

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

mysql --user=mark_admin --password=123vanaema --default-character-set=utf8 ekskfk -e "RENAME TABLE ekskfk.textgrid TO ekskfk2.tmp, ekskfk2.textgrid TO ekskfk.textgrid, ekskfk2.tmp TO ekskfk2.textgrid, ekskfk.search TO ekskfk2.tmp, ekskfk2.search TO ekskfk.search, ekskfk2.tmp TO ekskfk2.search; SHOW WARNINGS;" >> ekskfk_uuendus.info


