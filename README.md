# EKSKFK

EKSKFK is a Praat textgrid to database converter and search engine web frontend. It is used in the [Phonetics Laboratory at the University of Tartu](http://www.keel.ut.ee/en/languages-resourceslanguages-resources/phonetic-corpus-estonian-spontaneous-speech).

## Dependencies

* Praat is not needed
* Annotated Praat files need to be in WAV format
* Praat annotation needs to be in Textgrid format
* PHP is needed for the web front-end
* MySQL or MariaDB is needed for the backend
* Perl is needed for the backend
* rsync is needed for the backend
* GNU/Linux compliant system

## Overview

The tool set consists of two parts - a search engine web application (the frontend) and a database backend. The database backend consists of a bunch of scripts that converts Textgrids and loads the derived data to a MySQL/MariaDB database.

The search engine is a web application written in PHP. It enables search in one or many Praat Textgrid annotation layers. The found annotations (e.g. the search results) are displayed in a layered fashion similar to that in the Praat user interface. The results' sound files and Textgrid annotations can be downloaded separately or all together as a compressed zip file.

### Using the database backend

A shell script is used to automatically run many scripts. Information about the process is output to the file ```ekskfk_uuendus.info``` and can be inspected during the execution.

The shell script ```uuenda_ekskfk.sh``` runs the following scripts:
* ```uuenda_textgridid.pl``` synchronises the Textgrid files marked as public
* here could be many Textgrid manipulating scripts (e.g. anonymizing names etc)
* ```textgrid2sql.pl``` extracts the Textgrid data and outputs it in a SQL compliant csv file
* ```extract_ekskfk_search_with_morphs.pl``` extracts search indexes from the Textgrid data
* a mysql command creates a new database and loads the data into it
* a rsync command syncronises the WAV files

After running the ```uuenda_ekskfk.sh``` script, the updated information resides in a separate database table (named ```ekskfk2```). The new and old database can easily be swapped with the script ```vaheta_ekskfk.pl```. The original state of the old database can thus be restored if after inspection the new database is found to have defects. Note that the sound files are not restorable in this way.

### Using the search engine web frontend

The frontend application should be simple enough to be intuitively used without any further description. Open ```frontend/ekskfk.php``` in your web browser.

## Installing and configuring the tools

The instructions are separated for the backend and the frontend.

### Installing the backend

Copy the folder named ```backend``` to a good location. The location should be accessible (read/write/execute) by all the users of the system but not by the web server. The rest of the installation depends mainly on the organization of the Praat files.

#### Backend configuration

Configuration depends very strongly on the organization of the sound files and their annotation structure.

### Installing the frontend

Copy the frontend folder to a location reachable by the web server. 

#### Frontend configuration

Configuration variables and database credentials must be filled in at the top of each of the files:
* ```ekskfk.php```
* ```textgrid.php```
* ```wav.php```

The variables are explained in the files where they are set.


## Copyright and license

The work was written by Kristian Kankainen in 2013 and is licensed under the GNU GPLv3.
