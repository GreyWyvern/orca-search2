************************************************************************
* Orca Search v2.4                                                     *
*  A robust auto-spidering search engine for single/multiple sites     *
* Copyright (C) 2006-2016 GreyWyvern                                   *
*                                                                      *
* This program is free software; you can redistribute it and/or modify *
* it under the terms of the GNU General Public License as published by *
* the Free Software Foundation; either version 2 of the License, or    *
* (at your option) any later version.                                  *
*                                                                      *
* This program is distributed in the hope that it will be useful,      *
* but WITHOUT ANY WARRANTY; without even the implied warranty of       *
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        *
* GNU General Public License for more details.                         *
*                                                                      *
* You should have received a copy of the GNU General Public License    *
* along with this program; if not, write to the Free Software          *
* Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307 *
* USA                                                                  *
************************************************************************


*** Changelog
  - See "changelog.txt" for this script's complete revision history


*** Quick Start
  - See "quickstart.txt" for short installation instructions suitable
    for advanced users.


*** Upgrading
  - See "upgrade.txt" for step-by-step instructions to guide you in
    successfully upgrading from a previous version


*** FAQ
  - See "faq.txt" for this script's FAQ


*** Contents
 1. Script Requirements
 2. Introduction
 3. Installation
   i. Ensure you have all files
   ii. Edit config.ini.php
   iii. Upload the files
   iv. Activate the Control Panel
 4. Spider Configuration
   i. Logging in and setting up
   ii. Starting URIs
   iii. Choosing a Trigger
   iv. Automatic Categorisation
   v. URI Matching
   vi. Remove Title Strings
   vii. Remove Elements
   viii. Starting the engine
   ix. Additional filetype plugins
 5. Entry List Panel
   i. Filtering and Sorting
   ii. Status types
   iii. New entries
   iv. The Action dropdown menu
   v. Data Locking
 6. Searching
   i. Output format
   ii. Customisation
   iii. Standalone search boxes
 7. Search Options
   i. The Basics
   ii. URI Matching
   iii. Latin Accent Matching
   iv. Adjusting Match Relevance
   v. Miscellaneous
 8. Crontab Spidering
 9. Sitemap Extension
10. JWriter Extension


************************************************************************
************************************************************************
1. Script Requirements

PHP 5.1+
MySQL 3.23+


************************************************************************
************************************************************************
2. Introduction

  Welcome to the Orca Search script.  This script will index the pages
at a single domain, or group of specified domains by spidering the
contents at an interval you specify.  You may even set up the spider to
be run at a specific time via *NIX cron tab.

  Note: The Orca Search is a full-text search, rather than an index-word
search; this provides a few key benefits such as: complete language
independence, fast spidering and highly accurate searching. However, as
a result of this design, the script will only usefully scale to sites
with 1,000 ~ 2,000 pages. Decent spider crawl and search return times
have been reported for indexes with up to 10,000 pages, but such results
are not typical.

  What follows are detailed instructions to help you make full use of
this script.  If you are an advanced user and don't need to hear a
rehash of uploading, CHMODing and editing text files, you may use the
Quick Start guides in the quickstart.txt file.  It contains short, step-
by-step instructions describing how to set up the script itself, and the
JWriter and Sitemap extensions.

  For everyone else, I recommend reading this entire readme.txt file to
make sure you don't miss out on features you might have just skimmed
over.  It's long, but it's worth it.  Besides, I spent so much time
writing it! :)  Don't let me down, eh?  Are you ready?

  Once the script is up and running, you'll need to teach your spider
what to eat and what to leave alone.  This will take some tweaking for
days or weeks to come, but eventually, what you'll end up with is an
automatic self-updating search system you never have to think about
again!  Well, maybe not "never", but pretty close :)

*****      Please report any bugs to:  wyvern@greywyvern.com       *****


************************************************************************
************************************************************************
3. Installation

  i. Ensure you have all files

  The Orca Search v2.0 attempts to be completely modular, unlike the
other scripts in the Orca series.  Most pieces of the script are 100%
swappable with newer or modified versions, should they become available.
Each file has a specific function which can be built upon by future
modders.  As such, there are three types of files for this script:
Core, Output and Tools.

So let's run through all the files you got in your Orca Search package:

There are seven (7) Core files.  These files MUST be installed in order
for the basic search script to work:

config.ini.php - User variables file
config.php     - Global configuration file
control.css    - CSS for Control Panel
control.php    - The Control Panel
head.php       - Accepts search requests and builds an array of results
lang.txt       - Language file for spider and control panel
spider.php     - Crawls your site and indexes pages

The default language file is English.  To use a different language file,
if available, name the file "lang.txt" and overwrite the existing text
file.  You may need to adjust the Control Panel Display Charset as per
instructions in the language file you are now using (explained later).
The control panel script will always look for a file named "lang.txt" in
the same directory in which the control.php file resides.

As of version 2.3a, the Orca Search uses the PHPMailer class to send
email.  Installation of the phpmailer.php file is only required if you
wish to send Spider Result or Query Log email.

phpmailer      - PHPMailer class
                 (From: http://sourceforge.net/projects/phpmailer)

There are four (4) Output files.  Three of them, "body.xhtml.php",
"body.xhtml.css" and "body.xhtml.lang.txt" make a set, while
"body.rss20.php" stands alone.  Only one set of these needs to be
installed depending on what type of output you want your search script
to generate:

body.xhtml.php      -\
body.xhtml.css      --}- Generates XHTML output from a result array
body.xhtml.lang.txt -/

body.rss20.php      - Generates an RSS 2.0 feed of search results

There are four (4) Tools files. The first three files are for a tool
called the JWriter. This tool will take the data from your search
database and compress it into a javascript file which can be used to
search a site which has been mirrored for offline use by a program such
as HTTrack.  I've designed the JWriter mainly using output from this
mirroring program so I highly recommend it: <http://www.httrack.com/>

The JWriter tool uses these files:

egg.js                 - Offline Javascript target file
jwriter.php            - JWriter workhorse
_search.html           - Sample offline search page

The fourth file is the target file for Sitemap writing:

sitemap.xml            - Empty Sitemap file

Google is currently beta testing a sitemap service, where you can submit
an XML or gzipped XML list of pages at the site you want indexed.  The
script will output this XML data into the sitemap.xml file.  There is
also the option to gzip this data to save space and bandwidth.  If you
select this option, you will need to rename this file with an .xml.gz
extension.

Among all of these files there is a small file named "_search.php".
This file shows a sample XHTML search result page (using the
"body.xhtml.php" output file) and where each piece of the search engine
is included.  Following the same system of PHP includes, you should be
able to embed the search engine into your already existing webpage
layout.

The .zip file also includes an .htaccess file which turns off your
server's zlib output compression within the os2/ directory.  The
spider's progressive output requires delivery of the page to the browser
in plain HTML.  You don't need to upload this file if you are having no
problems viewing the spider's incremental output, when triggered from
the Control Panel.  Servers usually have zlib compression turned off by
default.

  ii. Edit config.ini.php

  Before uploading, open the "config.ini.php" file.  There is a short
list of variables you need to assign manually in order for the script to
work on your server.

First, there are five variables under the MySQL header.  These variables
allow the script to access the MySQL database system on your server to
manage script data and store search indices.  If you don't know these
variables offhand, ask your host.

The first four variables will be specific to your server and MySQL
installation.  However, the fifth variable will be used as a prefix for
creating three tables in your database.  You can give this variable any
name you want, as long as it is only letters and numbers and does not
begin with a number.

Next there are two variables under the Admin header.  These will be the
login name and password for the Control Panel.  Change them to something
hard to guess!

Once everything is the way you like it, you can save and close the
"config.ini.php" file.

  iii. Upload the files

  Create a directory in your public HTTP area to contain the search
script files. The default is "os2" but you can specify any directory
you want, provided you change the include statements in any search page
you create, and in appropriate places in the Control Panel, to point to
the new directory.

 ******************************** NOTE ********************************
 * For the purpose of convenience, the remainder of this manual will  *
 * assume you are using the default "os2" directory and the XHTML     *
 * Output files!                                                      *
 **********************************************************************

Upload the following Core files into the "os2" directory:
config.ini.php
config.php
control.css
control.php
head.php
lang.txt
spider.php

Upload the PHPMailer class file into the "os2" directory if you want
the script to send email reports:
phpmailer.php

Then upload the following Output files into the "os2" directory:
body.xhtml.php
body.xhtml.lang.txt
body.xhtml.css

Finally, upload the following file into the parent directory of "os2":
_search.php

After you have uploaded all the files, your directory structure should
look like this:

/_search.php
/os2/body.xhtml.php
/os2/body.xhtml.lang.txt
/os2/body.xhtml.css
/os2/config.ini.php
/os2/config.php
/os2/control.css
/os2/control.php
/os2/head.php
/os2/lang.txt
/os2/phpmailer.php
/os2/spider.php


  iv. Activate the Control Panel

  When the above files have been uploaded, visit "os2/control.php" via
HTTP with your web browser.  If you are prompted with a login screen,
script setup was a success!  The Control Panel is now installed and
ready to be configured.


************************************************************************
************************************************************************
4. Spider Configuration

  i. Logging in and setting up

  Log in using the username and password you entered in the
"config.ini.php" file.  Once you log in, you'll be confronted by the
Spider setup area.  Scroll down and set all your spidering options, they
should be well explained by the help text included in the form.  Some of
the more obscure form elements will be explained below.  Make sure you
"Submit" to save your changes!

If you have trouble entering or viewing special characters in any of the
fields, the problem may be that the Control Panel is not being served in
the character set of your input.  If this is the case, click the Tools
button in the menu and change the Display Charset to your preference.

If you load a different language file than the default (English), make
sure to check which character set(s) it is compatible with.  The Control
Panel Display Charset *must* be changed to match the language file or
else dialogues may not display properly.


  ii. Starting URIs

  Choosing a good starting URI (you may also specify multiple starting
URIs) is important.  You want to choose a URI which contains links to as
many other pages on your site as possible.  Usually this is the home
page or some sort of sitemap page.

However, note that the spider will not travel up in the directory tree.
So if you start your spider in a deeper directory, links to pages in
directories above it will be discarded.  See these examples:

http://www.example.com/
  - The spider is free to use links to any directory at example.com

http://www.example.com/~user/
  - The spider can only use links which stay in the /~user/ directory

http://www.example.com/~user/
http://www.example.com/
  - The spider will use both locations as starting URIs and because of
    the second URI, the spider can use links to any directory

Keep in mind that links which are found within any domain will only be
followed if that domain is within your Allowed Domains list, further
down the Spider Panel.


  iii. Choosing a Trigger

  You can choose one of two ways to trigger your spider.  By default,
the Orca Search uses an internal interval triggered by use of the search
interface.  In simple terms, the script keeps track of the last time it
spidered; when someone uses your search engine, the script checks to see
if a specified amount of time has passed; if so, a spider is triggered
as the search finishes.

While this is a simple means for causing a recurring spider, it also
introduces a time-creep into the schedule.  Since the trigger is
determined by search interface use, which could happen at any time,
the effective interval will always be some value slightly above the
interval you specify.

If you require your spider to run *only* when you want it to - perhaps
to quarantine it to a time of day when server load is the lowest - you
can use the Crontab Trigger option.  See section 8 of this readme file
for more information on this topic.

Starting in version 2.2, the Orca Search has an option called Seamless
Spidering, which is on by default.  Formerly, when a spider was
triggered (either by internal interval or crontab), the search index
would lock and the search interface could not be used until the crawl
was completed.  This was an especially serious problem if your crawls
took a long time.

With Seamless Spidering enabled, the spider will make a copy of the
index table to work with while the original index table remains open to
searching.  When the crawl is completed successfully, the original index
table is overwritten by the updated table.

Because a copy of the index table is used, you must have enough MySQL
storage space to hold TWO complete indexes simultaneously.  Unless your
index is extremely large, this shouldn't be a problem, but contact your
host if you are at all concerned, or if spiders don't seem to finish
properly if Seamless Spidering is enabled.


  iv. Automatic Categorisation

  There is a special textarea in the spider options labelled "Automatic
Categorisation".  You can use this field to make the spider
automatically assign certain categories to newly found pages.  The field
uses this special syntax:

CategoryName:::URIMatch

or

CategoryName;;;TitleMatch

Type the name of the category you want automatically assigned first,
then choose whether a URI or Title string match will work best.  If URI,
add three colons, if Title, add three semi-colons.  After the colons/
semi-colons, type a plain text matching string which will trigger the
assignment of this category.  These matches will be compared to the
entire title or entire URI (including the "http://").  Here are some
examples:

    a) Products:::products/

  This rule will match these example URIs:
    http://www.example.com/products/
    http://www.example.com/products/item1.php
    http://www.example.com/donotindex/products/item1.php

  If any of these URIs are found, they will automatically be assigned to
  the "Products" category.

    b) My Blog;;;My Blog

  This rule will match these example Titles:
    My Blog
    My Blog - A Day At The Farm
    Add a Comment to My Blog

  If any of these Titles are found, they will automatically be assigned
  to the "My Blog" category.

Because of the three colons and semi-colons system being used, you
cannot assign category names which contain these character sequences.
However, the match string may contain them, if needed.

The results of the spider can also be emailed to you.  The Email Results
field accepts email addresses in the same format as PHP's mail()
function.  Read more about it here:
  http://php.net/manual/en/function.mail.php


  v. URI Matching

A good way to limit where the spider goes is by using the URI Matches
textareas.  By limiting what URIs the spider can request, you'll save
valuable CPU cycles and data transfer during each spider.

The first textarea is labelled "Require URI Matches" and is the more
powerful of the two.  Any scoured URI found by the spider which does not
match at least one of the lines you enter here will be ignored.  For
instance, you can limit the spider to pages named "blog.php" with this
rule:

/blog.php

Only URIs which contain that text will be requested and indexed.  Think
of this list as a "strict whitelist".

The second textarea can be considered a "blacklist" and is called
"Ignore URI Matches".  Any URI the spider finds which matches at least
one of the lines given here will be ignored without even requesting it
from any server.  So, say you have both index.html and index.php pages
on your site, but since one redirects to the other, you don't want the
spider to request the same page twice.  Just add this line here:

/index.html

It is important to note that these matching lines, from both sections,
are compared against the *entire* URI, including the domain name and
even the "http://".  In the case above, the line would also match these
URIs:

http://www.example.com/index.html
http://www.example.com/directory1/index.html
http://index.html.com/default.htm

Mistakes like the third URI are possible without some double checking,
so make sure all matches you input provide the least amount of possible
error.


  vi. Remove Title Strings

On some dynamic sites, each page is generated with a standard title
prefix or suffix.  For example, your pages might all have titles like:
  "My Company Inc. - [page title]"

After a straight index, when a user does a search using the interface,
all the search results will begin with:
  "1. My Company Inc. - Widgets"
  "2. My Company Inc. - Cleaning Your Widget"
  "3. My Company Inc. - Widgets For All"

Obvously this redundancy doesn't make any sense, as well as looking
pretty unhelpful.  Using the Remove Title Strings textarea, you can
specify strings of plain text which will be stripped from each title,
leaving your search result titles short and to-the-point.

So if you insert the string "My Company Inc. - " as a line in this
textarea, after spidering your search result titles will now look like:
  "1. Widgets"
  "2. Cleaning Your Widget"
  "3. Widgets For All"


  vii. Remove Elements

Sometimes you will have pages which you would like to index, but you'd
also like to exclude some content on these pages.  The content may be
redundant, better explained elsewhere or simply not useful as a search
result.  What is needed is a way to remove certain HTML elements along
with all their contents before the page is indexed.  You can do this
with the Remove Elements text area.

Rather than implement a proprietary exclusion method, the Orca Search
uses a small subset of the tried-and-true CSS selector model.  There are
five types of element you can exclude by adding entries here, each entry
separated by spaces.

    a) element

  This is the basic, plain exclusion rule.  All elements named <element>
  will be removed from the source along with their contents, before the
  page data is indexed.  Some common elements are included in the Remove
  Elements text area by default.

    b) element#id

  This is an element specific exclusion rule and works just like the
  corresponding CSS selector.  The element named <element>, which also
  has its id attribute set to "id", will be removed from the source
  along with its contents.

    c) element.class

  The same as b) except this rule matches elements with class attributes
  of "class".  Multiple elements can have the same class and single
  elements can have more than one class (eg. class="class1 class2").
  The rule above works in both situations.

    d) #id

  This is a non-specific exclusion rule.  An element of any name which
  has an id of "id" will be removed from the source along with its
  contents.

    e) .class

  The same as d) except matching a class attribute rather than id.  Once
  again, this only matches single classes.  An example rule of this type
  (".noindex") is included in the Remove Elements text area by default.


  viii. Starting the engine

After the spider has been found and you have set all your desired Spider
options, hit the "Go" button in the form up top to begin the spider.
Then watch it crawl!  Because crawling a site requires a lot of error
tolerance, if anything goes wrong with this search script, it will
probably happen now.  If an error does happen, the spider will stop and
a message will be displayed.  As I aim to make this script work with as
many different PHP installations and URI formats as possible, if you
could email me any error messages I would be very grateful :)  As we
move on, I will assume the spider completed its crawl sucessfully.

Unless you were really meticulous, you'll probably notice that the
spider ate a lot more or a lot less than you were expecting it to.  This
is normal.  Just look through the list of files the spider ate, adjust
your rules on the Spider page, and try again.  You don't have to get it
perfect just yet though, since you can make manual edits using the Entry
List section.

Remember, that if the spider crawled and indexed some pages it wasn't
supposed to, those pages will not be purged if you add a blocking rule
and spider again.  They will only be marked as "Blocked" or "Unread".
You will need to manually delete them from the Entry List panel.


  ix. Additional filetype plugins

Spider plugins are ways you can extend the function of the spider by
adding new file types and making a few simple changes to the script.
These plugins are usually small php files which handle the spider output
for certain MIME-types.  These php files should be placed in the
os2/plugins/ directory; create this directory if you haven't already.

When indexing new file types, often the means for extracting the text
requires an executable on your server to run against an actual file.
Because data downloaded from the internet isn't really a file yet, the
script needs a temporary directory to which it can upload files.

In your os2/ directory (or wherever else you put the script) create a
child directory called "temp".  The Orca Search package comes with this
empty folder by default, so you may have uploaded it already.

See each individual plugin's help text file for their various
installation instructions.  To finalize each installation, you will need
to include the file in your config.ini.php file.  An example plugin
include line looks like this:

include "plugins/index.pdf.php";


************************************************************************
************************************************************************
5. Entry List Panel

  Click the "Entry List" button in the menu to go to the Entry List.
Here you'll find a big list of every page your spider has indexed.  You
can go through and make any edits you want, like adding custom keywords,
titles and descriptions to entries, changing their category and even
manually unlisting and/or deleting them.

By default, the Entry List lists 100 entries per page.  To change this,
use the text field above the column containing the "Edit" buttons.  Your
selection will be remembered until you change it, even if you log out.

Get used to using this interface, as it will become your main interface
for managing the pages crawled by your spider.  If you have any
suggestions for making it easier to use, or for new features, be sure to
let me know :)


  i. Filtering and Sorting

It's important to note right away, that even if you have many thousands
of pages listed, you can use the various filters in the Filters row to
narrow down your list.  Make it a habit to experiment with the different
filters and using them together for very powerful matching.

The Entry List has four main columns: Title/URI; Category; Status; and
Edit/Sitemap.  You can sort the list based on Title, URI and Category;
just use the links along the top.  Initially, you will see each entry's
URI, Category and Status listed.


  ii. Status types

Each entry falls into one of five basic status types:

 OK - Page was successfully found during a normal spider, or was indexed
   successfully via respidering.

 Orphan - Page was not linked to from any other page within your
   specified Allowed Domains or the Spider depth was not deep enough to
   reach it.

 Added - Page was recently added manually and will not appear in search
   results until it is spidered

 Blocked - Page was at least one of:
   - blocked via robots.txt
   - blocked by a user-defined Ignore URI rule
   - socket error while requesting
   - URI was HTTP redirected elsewhere
   - contained a <meta> tag redirect
   - unnacceptable MIME-type

 Not Found - This page used to be indexed but can no longer be found; no
   forwarding address was given.

In addition to these status types, "OK" and "Orphan" pages can be either
"Indexed" or "Unread" (all "Added", "Blocked" and "Not Found" pages are
Unread by definition).  When a page is "Indexed", it contains searchable
body text and other meta information like a title, keywords and a
description.  Conversely, when a page is "Unread" it either contained no
indexable text, or was not an indexable MIME-type.

OK and Orphan pages which are Indexed will have their status listed in a
normal font, while Unread pages will appear in strike-through.

Finally, superceding all of these is the "Unlisted" status, which
prevents the page from appearing in search results no matter what status
it may have.  A page becomes Unlisted either because of matching Search
Panel rules or because it has been specifically set as "Unlisted" using
the Entry List Panel.


  iii. New entries

If you only ran the spider once before coming to the List page, you
will notice that all of the entry URI's down the left-hand of the page
are listed in boldface.  After each spider, new pages, or existing pages
with updated content are marked in bold.  You can filter the list to
view only pages updated since the last spider by checking the "New"
checkbox next to the "Filters" title and hitting the "Set" button.  If
there are no "New" pages in your list, this checkbox will be disabled.


  iv. The Action dropdown menu

Under the Filters toolbar, there is an Action dropdown.  Using this
dropdown in combination with the checkboxes along the left-hand side of
the list, you can perform many different actions on single or multiple
pages automatically.  There are many options here useful for changing
the attributes of many entries at once such as unlisting and relisting,
changing category and even respidering.


  v. Data Locking

By default the Orca Spider has a very rigid system of updating entry
information.  If the spider finds text which can be interpreted as a
"title", it will overwrite the entry's current title; if it cannot find
any such text, the existing title will be *retained* rather than
deleted.  The same system goes for Keywords and Description.  In this
fashion, you can easily include custom titles, keywords and
descriptions for pages which do not natively contain this information.

In certain cases you may want to assign custom title, description or
keyword data to an entry which *does* contain this information
already.  By default, your custom information will be overwritten by the
spider the next time it runs.

You can prevent the spider from overwriting these three items by putting
a "Data Lock" on the entry.  You can do this either through the Action
dropdown menu, or by clicking the Edit button for any entry and checking
the Data Lock option.  With this option enabled, the body text of this
entry will still be updated, but the title, description and keywords
will not.  This prevents your custom changes from being overwritten.

Entries which are Data Locked will display a copyright symbol next to
their Title/URI.


************************************************************************
************************************************************************
6. Searching

  i. Output format

  Now that you have indexed pages at your site, visit the "_search.php"
page with your web browser.  You should find a search input form waiting
to be used.  If you added more categories than just "Main" using the
Entry List page, the ability to filter results by category will appear
via a handy dropdown box.

A feature of the Orca Search is that the body.xhtml.php file, which
displays the output, can be modified however you wish to fit your own
website style.  It takes all of the output from the "head.php" file,
interprets it, and displays it in a logical form.  If you want to make
your own output file(s), examine "head.php" for a description of the PHP
variables that file creates.

It is possible to create practically any output format from the
"head.php" output, even archiving it straight into another database!
All that's needed is an appropriate body file to crunch the output.


  ii. Customisation

The "_search.php" page is just a sample php page with the bare minimum
amount of PHP code and HTML to display the search output.  This is by
design to make it easy for you to embed the output into your own
existing website design.  There are four important steps to take when
embedding the search page.  Open the "_search.php" in your text editor
and we'll go through them now.

    a) First you should see that there are two included files right at
the top of the page: "os2/config.php" and "os2/head.php".  These files
set up the environment and handle the search process, so they must be
included in the results page *before* any HTML output.  This means
before the <html> tag, and before any other whitespace.  Otherwise you
will get many "Headers could not be sent" errors.

    b) After this is a very important <meta> element which declares the
charset of the search page.  You definitely want search requests entered
on this page to be in the *same* character encoding as the data you
spidered or else results may not display properly.  By using the
$vData['c.charset'] variable, the charset is filled in with the Display
Charset of the control panel by default.  If you require the search
results to display with a *different* character encoding than the
Control Panel, change it in the <meta> element here.

    c) Finally there is the actual output include itself.  In this case
it loads "os2/body.xhtml.php" and will display all the output associated
with each search request.  Place this include where the content usually
goes in your website layout.

That's it!  Any other PHP or HTML code is entirely up to you!


  iii. Standalone search boxes

If you want to add a searchbox elsewhere on your site, just use one of
these sample bits of HTML code:

    a) Search box with submit button:

<form action="_search.php" method="get">
  <input type="text" name="q">
  <input type="submit" value="Search">
</form>

... replace _search.php with the name of your search page.

    b) Without submit button (press enter to submit):

<form action="_search.php" method="get">
  <label>Search: <input type="text" name="q"></label>
</form>

... again replacing _search.php with the name of your search page.

To preselect a category for any search box, just add the following
<input> element to the form, replacing categoryname with the name of
your desired category:

<input type="hidden" name="c" value="categoryname">

_OR_ you can add the category drop down menu from the search output
page.  The category drop-down menu appears there only if you have more
than one category, and is dynamically generated depending on what
categories you have.  If you'd like to include this drop down in a
search box on another page, just copy the <select> element from the HTML
on the output page and paste it, as-is into your form.  It's that
simple!  However, you will need to recopy the HTML if you ever add more
categories.


************************************************************************
************************************************************************
7. Search Options

  i. The Basics

  After massaging your newly indexed database, you can set some search
options in the "Search" panel.  These options will affect what results
ultimately get sent to the user after a query.

You don't need to change anything here for the script to work properly.
These are mainly "tweak" type settings, so you'll probably want to come
back to it after you've run the search script for a few days.  If so,
feel free to skip to the next sections for now.

At the top of the page you'll find some simple cache settings.  If you
are hard up for MySQL database space, you can limit the size of the
cache and/or enable GZip compression if your server supports it.


  ii. URI Matching

Below that are the main search options.  The first large text box is
probably the element you'll end up editing most.  Entries with URIs
matching lines in this textarea are prevented from appearing in any
search results.  This is a useful complement to the "Ignore URIs" in the
Spider panel since the pages you block here will still be spidered for
links.  For example, if one of your pages is just a few links to other
pages on your site, it would be a good idea to list it here, since it
would be practically useless as a search result, but you still want the
spider to crawl those links.


  iii. Latin Accent Matching

A useful option here is Latin Accent Matching.  Latin Accent Matching
enables search queries such as "starkste" to match "stärkste".  It also
enables a search for either "starkste" or "stärkste" to match the
literal HTML text "st&auml;rkste". Because this feature allows for many
more possible matches, enabling Latin Accent Matching will slow down
search speeds significantly, especially if you are indexing many pages,
so think about whether you actually need it before enabling it.   This
option should ONLY be enabled if the pages you are indexing - AND the
page on which you are displaying search results - are in either UTF-8
(with UTF-8 Indexing enabled) or ISO-8859-1 encoding.


  iv. Adjusting Match Relevance

The script also allows you to change the weighting applied to any match
as you see fit.  The first group of values are arithmetic bonuses; they
will be added for each match found in a specific section of an entry, to
a maximum of three to prevent spamming.  To disable searching of any
location within an entry, just set the corresponding Match Weight to
zero (0).

The second group are geometric bonuses; the total score for each entry
will be multiplied by these bonuses for each instance they apply.  For
example, if someone searched for a query with three terms and a single
entry had all three, the final score for that entry will be multiplied
by the Multi-term Bonus twice; once for each additional term found.

The Increase Tag Weight field is a list of elements to which the score
from the Weighted Elements will be added.  Use this field in combination
with that value to apply extra relevance to certain elements by adding
selectors in a space separated list.  Some example element selectors
will be included here by default.  This field takes the same selector
syntax as the Remove Elements field from the Spider Panel.  Take a look
at section "4.vii. Remove Elements" for more information.


  v. Miscellaneous

The Maximum Returned Results field determines the maximum number of
search results any single query can return.  If you set this to a value
other than zero (0), the script will use that value as a hard limit.
If you set it to zero, the script will try to calculate an appropriate
number of search results to return, based on the total number of pages
indexed.

When an entry is displayed as a search result, some text from the page
will be displayed along with it with the search term highlighted.  You
can limit the amount of this text by adjusting the Maximum Matched Text
Displayed value.  This option counts bytes in the string, not actual
glyphs; if you are searching pages containing UTF-8 multi-byte
characters, you may want to increase this value so more text can be
displayed.

Finally, by default, the script will not display pages which have the
"Orphan" status in any search results.  You can display them anyway by
enabling the Show Orphans option.

The other items in the form should be adequately explained in the inline
help text.


************************************************************************
************************************************************************
8. Crontab Spidering

  This section assumes you are hosting the spider on a *NIX server which
has a crontab daemon installed.  It also assumes you know how the basic
options of a crontab work.  See the Wikipedia entry on the crontab
command for more information: <http://en.wikipedia.org/wiki/Crontab>

To trigger a spider by crontab, you'll need to check the "Enable Cron"
checkbox in the Spider panel.  This only tells the script to expect a
crontab trigger, you'll have to set up the crontab yourself using the
server tools you have available.  Usually your host will have provided a
control panel which allows you to set up a crontab.

Note that enabling the crontab trigger will disable PHP-based automatic
spidering, spider result emailing and spider HTML output.  The spider
will depend entirely on you to provide the correct trigger.  Otherwise
it will do nothing but ignore requests to spider, unless explicitly
trigged through the Control Panel.

As for the value of the crontab itself, first you will need to know the
path to the PHP binary on your server.  It is usually "usr/bin/php" but
depending on who set up the server it may be somewhere else.  If so,
you'll need to find the correct path by asking your host.

Next, you need to know the full server path to the spider script.  It
needs to be on the same server as the crontab daemon and the PHP binary.
You can find this by examining a phpinfo(); file on your server.  It
should list your website's server path several times.

Once you know where the spider file is located, the value of the crontab
should look like this:

/usr/bin/php /path/to/the/spider.php

... where the first path is your path to the PHP binary and the second
is the path to your spider script.  This crontab will call the PHP
binary and tell it to execute your spider.  So set your crontab times
however you like and save this.

Your spider should now run by crontab, although make sure you receive an
email notification at first (usually automatic) so you know it is
running properly.

 ******************************** NOTE ********************************
 * If you have successfully set up the spider as a scheduled task on  *
 * a Windows server, please let me know so I can include such         *
 * instructions here in this manual.  Thanks.                         *
 **********************************************************************


************************************************************************
************************************************************************
9. Sitemap Extension

  If you're happy with your new search engine, let's look at the other
tools of the Orca Search script.  First, click on the Tools button in
the menu to go to the Tools panel.  Initially there won't be much to see
on this page.  Both the Sitemap and JWriter dialogues will be collapsed,
so check the "Enable Sitemap" box and hit Submit to make the Sitemap
options appear.

If you haven't uploaded the "sitemap.xml" file yet, you'll notice that
the script will be searching for it now and will tell you if it cannot
be found.  Since sitemaps are only applicable to pages beneath them in
the directory tree, it is recommended to upload the sitemap file into
the root HTML directory.

The default location for the sitemap might be incorrect, so make sure
it's pointing to the correct location.  The rest of the items in this
dialogue should be relatively self-explanatory.  I recommend enabling
gzipping and the Automatic Changefreq option.  Make sure to rename the
sitemap file from an .xml extension to an .xml.gz extension if you
enable gzipping.  See Google's sitemap FAQ for more information about
sitemaps and how search engines deal with them:
  <http://www.google.com/webmasters/sitemaps/docs/en/about.html>

Once the options are set up to your liking (remember to hit "Submit"!),
return to the Spider panel and initiate another spider.  Just before the
spider finishes, it will output a fresh sitemap, ready for submission to
search engines!

Enabling the sitemap will also add editing options to the Entry List
section which you may want to check out.  The last column will display a
small bar and a letter, indicating the sitemap priority and change
frequency respectively.  You can hover over any sitemap element here to
see more information.  Just hit the Edit button to manually adjust an
individual page's sitemap settings.


************************************************************************
************************************************************************
10. JWriter Extension

  The Orca Search, in all its glory, only works with a PHP/MySQL backend
to support it.  However, if you need it to work, in a limited fashion,
with an offline copy, the script can compress and output a copy of the
database in javascript.  This is great for having your search engine
still function if you mirror your site to CD, for example.

As a mirroring program, I recommend HTTrack, especially since the
JWriter has been designed using this program's output.  Visit their
website: <http://www.httrack.com/> for more information.  Despite
favouring HTTrack, the Javascript output should work well enough with
any decent offline mirroring program, as long as it does not rename
downloaded HTML-content files (other than their extensions).

 ******************************** NOTE ********************************
 * The JWriter works best with sites that do not use query strings to *
 * reference content pages.  Many popular Content Management Systems, *
 * such as PostNuke, use a query string system for page access:       *
 *                                                                    *
 * - http://www.example.com/modules.php?op=modload&name=gallery       *
 *                                                                    *
 * Such a link would be renamed to a static HTML file by HTTrack like *
 * so:                                                                *
 *                                                                    *
 * - /modules0y5sj.html                                               *
 *                                                                    *
 * However, the URI with the query string would remain in the JWriter *
 * output with just the extension altered, requiring you to manually  *
 * change the URIs in the egg.js file.                                *
 *                                                                    *
 * - /modules.html?op=modload&name=gallery                            *
 *                                                                    *
 **********************************************************************

First you will need to upload the JWriter tools files.  These are the
"egg.js" and "jwriter.php" files and the "_search.html" sample page.
Upload "egg.js" into the "os2" directory and place "_search.html" into
the parent directory, like so:

/_search.html
/os2/egg.js
/os2/jwriter.php

PHP will need permission to write to "egg.js" so apply the appropriate
properties to this file, like CHMOD'ing to 766 or 777.

Next, head back to the Control Panel and check the Enable JWriter
checkbox to make the JWriter interface appear.  There are two parts to
the interface: an options form above, and a "Write to File" button
below.  Some of these items may not appear initially if the paths to the
jwriter.php and egg.js files are not correct.  Make sure these are
pointed to the correct locations.

Although the options are relatively well-explained via the inline help,
you may find that you'll understand them better if you actually mirror
your site and examine the output.  File extensions get renamed, full
URI's become relative, and "/" URIs are given an actual filename,
usually index.html.  All of these actions can be accounted for using the
JWriter, just set them up to match.

Since the JWriter wraps up searching and output all in one, you'll need
to specify how many search results you want per page, and the HTML
template to use for search results; just like is required in the
"body.xhtml.php" file.  Like the XHTML output file, the HTML template
here uses a system of replace-codes to insert result-data in the correct
HTML elements.  These codes in both systems are identical and are as
follows:

{R_NUMBER}      - Number of the result in the result list
{R_RELEVANCE}   - Relevance score to one decimal place
{R_URI}         - Result URI
{R_FILETYPE}    - Result filetype
{R_CATEGORY}    - Result Category
{R_TITLE}       - Result Title
{R_DESCRIPTION} - Result Description
  - If there is no page description, the first 200 characters of body
    text will be used instead
{R_MATCH}       - A selection of body text with highlighted matches
  - If there is no match-text, the contents of the description will be
    used instead

These codes will be globally replaced in the HTML result template, thus
any single code can be used more than once and all instances will be
replaced.

When you are ready, hit the "Write to File" button to watch the script
go to work.  A percentage complete reading will be listed and, when 100%
complete, some simple statistics about the compression process will be
displayed.

If the script times out and gets stuck at a certain percentage for a few
minutes, the problem may be that PHP does not have enough memory to
complete the compression.  You can try to increase the memory allowed to
the script by editing the "JWriter tweak" section in the config.ini.php
file.  Simply uncomment the "ini_set" line and if necessary, set the 16M
(16 megabytes) value to an amount which can accomodate the size of your
index table.  Keep in mind that changing your memory_limit value may be
disallowed by your host.  In such a case, setting a value here will have
no effect.

If your "egg laying" was sucessful, you can head back to the control
panel where you can continue on with other modifications.  Download the
egg.js file and include it in a <script> tag where you want your search
results to appear like so:

<script type="text/javascript" src="egg.js" charset="ISO-8859-1">
</script>

Change the "ISO-8859-1" to the charset used by the majority of pages you
have indexed.  It is especially important to change this to UTF-8 if you
have UTF-8 Indexing enabled.  Otherwise some special characters might
display incorrectly.

The script will capture the same query strings that the online version
uses and output search results using the exact same format as the
default body.xhtml.php file.  These search scripts should be very close
in functionality, but due to the compression process, results won't be
identical, and there are no phrase matches.

END