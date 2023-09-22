=== WP Database Backup - Unlimited Database & Files Backup by Backup for WP ===
Contributors: walkeprashant, databasebackup
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: Database backup, db backup, backup, database, WordPress Database Backup, WP db backup, wp database backup, wp backup, wordpress backup, mysql backup, automatically database backup, website backup, website database backup, restore database backup, Store database backup on dropbox, backup on google drive, backup on amazon s3,ftp, file, file backup,  files backup, backup file, WP file backup,email notification.
Requires at least: 3.1+
Tested up to: 6.3
Stable tag: 6.6

Create & Restore Database Backup easily on single click. Manual or automated backups (backup to Dropbox, Google drive, Amazon s3,FTP,Email).

== Description ==

WP Database Backup plugin helps you to create Database Backup and Restore Database Backup easily on single click. Manual or Automated Database Backups And also store database backup on safe place- Dropbox,FTP,Email,Google drive, Amazon S3

= Features =
<ul>
<li>Create Database Backup
WP Database Backup plugin helps you to create Database Backup easily on single click.</li>

<li>Auto Backup
Backup automatically on a repeating <strong>schedule</strong></li>

<li>Download backup file direct from your WordPress dashboard</li>

<li>Easy To Install(Very easy to use)
WP Database Backup is super easy to install. </li>

<li>Simple to configure(very less configuration), less than a minute.</li>

<li>Restore Database Backup
WP Database Backup plugin helps you to Restore Database Backup easily on single click.</li>
<li>Multiple storage destinations</li>
<li>Store database backup on safe place- <strong> Dropbox, Google drive, Amazon s3,FTP,Email</strong></li>
<li>Reporting- Sends emailed backups and backup reports to any email addresses</li>
<li><strong>Exclude Table</strong></li>
<li>Database backup list pagination</li>
<li>Search and Replace in database backup file.</li>
<li>Search backup from list(Date/ Database Size)</li>
<li>Sort backup list (Date/ Database Size)</li>
<li>Save database backup file in zip format on local server And Send database backup file to destination in zip format</li>
<li>Documentation</li>
</ul>

= Upgrade to PRO =
<ul>
<li>With <a target="_blank" href="https://backupforwp.com/pricing/#price">Backup For WP PRO</a> You will get access to more professional Settings, options .</li>
<li>*New - Data Anonymization </li>
<li>*New - Backup time and scheduling  </li>
<li>*New - Fast, personal support </li>

</ul>

= Support =
* <a target="_blank" href="https://backupforwp.com/support">https://backupforwp.com/support</a>

== Installation ==
1. Download the plugin file, unzip and place it in your wp-content/plugins/ folder. You can alternatively upload it via the WordPress plugin backend.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. WP Database Backup menu will appear in Dashboard->Backups. Click on it & get started to use.

[youtube https://www.youtube.com/watch?v=st8L90lTDwU]

== Screenshots ==

1. screenshot-1.png
2. screenshot-2.png
3. screenshot-3.png
4. screenshot-4.png

== Changelog ==

= 6.6 =
* 22-09-2023
* Added: Support for background backup for plain permalink structure #67
* Improvement: DB backup leads to fatal error for large tables #68
* Added: Button to stop the background backups process. #62
* Fixed: Error in php log and console #66
* Compatibility: Tested with WordPress 6.3 #61

= 6.5.1 =
* 04-08-2023
* Fixed: Warnings is showing on wp database-backup. #59

= 6.5 =
* 26-07-2023
* Fixed: Dropbox says "Not Configured" but its connected and authenticated. #51
* Added: Data anonymous on the clone website for the GDPR integration. #20

= 6.4 =
* 03-07-2023
* Fixed: The backup progress bar gets stuck while creating the backup #53
* Fixed: A Fatal error appears after clicking on "Create New Database backup" #52

= 6.3 =
* 06-06-2023
* Added: Added a option for scheduling the Complete Backup. #29
* Fixed: Issue related to timezone #47
* Fixed: Compatible with PHP Compatibility Checker plugin #45
* Fixed: After deleting list backup files, notice keeps showing even after reloading the page #38
* Fixed: Google Drive backup is not being configured, and there are also multiple issues. #44
* Improvement: FullBack process will work in background and will show current progess. #46
* Improvement: UI/UX Improvement for notifications. #46


= 6.2 =
* 04-04-2023
* Fixed: Undefinded variable $database_file error #33
* Added: ziparchive is not enable so show message #36
* Fixed: When we configued the email backup, then local backup is getting unconfigued. #37
* Fixed: Escaping is missing #40
* Fixed: Fatal error: Uncaught TypeError: ftp_quit(): Argument #1 ($ftp) must be of type FTP\Connection, bool given #41

= 6.1 =
* 21-01-2023
* Added Feature - Added full backup files and database

= 6.0 =
* 22-12-2022
* UI/UX and other Improvements

= 5.9 =
* 09-08-2022
* Sanitised multiple inputs and escape output to remove further risk of cross site script security.

= 5.8.2 =
* 06-01-2022
* Filter hook for Dropbox file name

= 5.8.1 =
* 01-08-2021
* Fix issue with PHP 8
* Added Feature - Create Database Backup On Upgrade/Update Plugin, Theme, WordPress.

= 5.8 =
* 30-07-2021
* Added Feature - Show WordPress/Database/Theme Version and Active Theme Name in log to get backup info.

= 5.7.2 =
* 24-07-2021
* Tested latest WordPress Version

= 5.7.1 =
* 17-01-2021
* Change support link
* Tested latest WordPress Version

= 5.7 =
* 13-11-2020
* Added Feature - Exclude Table : Select/Unselect All Option

= 5.6 =
* 24-09-2020
* Added Feature - Search-and-replace.

= 5.5.5 =
* 10-09-2020
* Added Feature - Download created Backup link on create message.

= 5.5.4 =
* 01-09-2020
* Fixed PHP Warning in System Information tab

= 5.5.3 =
* 16-08-2020
* Fixed issue Array to string conversion header notification

= 5.5.2 =
* 13-08-2020
* Tested latest WordPress Version
* UI Changes

= 5.5.1 =
* 10-05-2020
* Changes in config
* UI Changes

= 5.5 =
* 12-01-2020
* Added New Destination : Local Backup Path

= 5.4.2 =
* 07-12-2019
* UI Issue Fixed
* Error Fix

= 5.4.1 =
* 06-10-2019
* Update code for Backup-filenames without time
* Added Missing sort-icons.
* Resolved issue loading progress image

= 5.4=
* 28-09-2019
* Tested Compatible with WordPress 5.2.3
* Added Feature - Added red label for invalid backup list i.e empty backup file.

= 5.3 =
* 04-05-2019
* Added loading icon on create backup action for show in-progress backup action.

= 5.2 =
* 30-04-2019
* Security Changes - Fixed Vulnerability

= 5.1.2 =
* 22-04-2019
* Fixed Vulnerability - XSS issue

= 5.1.1 =
* 23-02-2019
* Fixed Vulnerability

= 5.1 =
* 02-02-2019
* Added Confirmation box to the restore and delete backup

= 5.0 =
* 13-01-2019
* Fixed WordPress Plugin Security Vulnerability : curl

= 4.6.5 =
* 12-11-2018
* Changelog order reversal

= 4.6.4 =
* 29-10-2018
* Resolved page breaks SSL wp-admin with insecure images issue

= 4.6.3 =
* 22-10-2018
* Fix Restore a database issue

= 4.6.2 =
* 21-08-2018
* Changes references and document URL

= 4.6.1 =
* 07-08-2018
* Fixed bug
* Changes in email notification template text

= 4.6 =
* 16-05-2018
* Lightweight - Remove unwanted file
* UI Changes

= 4.5.6 =
* 23-03-2018
* Added : display destination icon in backup list
* UI Changes

= 4.5.5 =
* 08-03-2018
* Add Setting -Enable/Disable .htaccess File In Storage Directory

= 4.5.4 =
* 08-01-2018
* Fixed urgent bug ticket #2680

= 4.5.3 =
* 08-01-2018
* Changed documentation/help url

= 4.5.2 =
* 26-10-2017
* Changed : Email(Destination) subject for identify domains.

= 4.5.1 =
* 28-09-2017
* Fixed issue : Update caused fatal error: Can’t return function value Dropbox_upload.php

= 4.5 =
* 27-09-2017
* Migrated to Dropbox API V2
* Dropbox API v1 shutdown - 28-09-2017

= 4.4.2 =
* 20-09-2017
* Fixed UI log popover display issue

= 4.4.1 =
* 29-08-2017
* If Class ZipArchive Not Present then create zip using PclZip

= 4.4 =
* 02-08-2017
* Added Setting : Remove local backup
* If Checked then it will remove local backup.

= 4.3.8 =
* 01-06-2017
* Changed icon

= 4.3.7 =
* 06-12-2016
* Changed label Minimum Local Backups to Maximum Local Backups in Setting Tab

= 4.3.6 =
* 21-10-2016
* Fixed CSRF vulnerabilities issue (suggestion by Tristan)

= 4.3.5 =
* 20-10-2016
* Add Option Enable/Disable destination.

= 4.3.4 =
* 09-09-2016
* Add Option Clear all old/temp database backup files (resolve issue for low disk space)

= 4.3.3 =
* 04-08-2016
* Fixed CSRF and XSS vuln issue (suggestion by Chen Ruiqi)

= 4.3.2 =
* 02-08-2016
* resolved urgent bug

= 4.3.1 =
* 01-08-2016
* Fixed CSRF and XSS vuln issue

= 4.3 =
* 07-07-2016
* Fixed Amazon s3 collapse tab issue
* Added documentation links in Help tab

= 4.2 =
* 24-06-2016
* Added New Feature : Amazon s3 integration.
* Fixed Vulnerability : prevent direct download

= 4.1 =
* 20-06-2016
* Added New Feature : Google drive integration.
* Changes in email notification template
* Add new Tab : Subscribe

= 4.0 =
* 04-05-2016
* Resolved : time out error.
* Resolved : undefined function ftp_connect error.
* Changed  : look and feel.

= 3.9.4 =
* 28-04-2016
* Added : Disk space checkup (memory available).

= 3.9.3 =
* 24-04-2016
* Added New Tab : System Check (i.e backup folder permission, execution time etc)

= 3.9.2 =
* 14-04-2016
* Compatible wordpress version 4.5 : Depricated function : mysql_real_escape_string. use _real_escape insted mysql_real_escape_string
* Resolved issue : PHP Strict Standards:  mktime(). use the time() function instead mktime().

= 3.9.1 =
<br>19-03-2016
<br>Resolved issue : The website is built under a directory i.e. www.xyz.com/demo

= 3.9 =
* 06-03-2016
* Uses mysqldump for faster back ups if they are available.

= 3.8 =
* 03-12-2015
* Added Twice Daily Auto Database Backup Frequency
* Resolved bug :Warning: Cannot modify header information(FTP)

= 3.7 =
* Removed PHP warnings

= 3.6 =
* Exclude Tables.
* Add drop down button for Quick setting view, Update notifications

= 3.5 =
* Backup zip label with the site name(Help when backing up multiple sites).
* Changes in Email Notification template.

= 3.4 =
* Improve security(CSRF protection): Validate that the contents of the form request came from the current site and not somewhere else.

= 3.3 =
<br>New Feature : Save database backup file in zip format on local server.

= 3.2 =
* Added Setting Tab.
* Added Feature: Minimum number of Local Database Backups(Setting Tab).
* Added Feature: Enable Error Log(Setting Tab).
* Resolved Error: Undefined index: page
* Changes in Email Notification template.

= 3.1 =
<br>Added Support and Documentation link in help section.
<br>Resolved .htasses file issue(remove create .htasses file code).

= 3.0 =
* Added log message.
* Send database backup to destination in zip format.
* Download database backup file in zip format.
* Resoled Database information tab issue.

= 2.4 =
* Added Hourly Auto Database Backup Frequency
* Added Advance Feature Tab.

= 2.3 =
* Added new tab- Database information.
* Include random number in the file name(Improve security).
* Added .htaccess file in db-backup directory for prevent database dump listing(Improve security).

= 2.2 =
* Added new Feature
* Database backup list pagination
* Search backup (Date/ Database Size)
* Sort backup list (Date/ Database Size)

= 2.1.3 =
* Fixed Vulnerability

= 2.1.2 =
* Fixed Dropbox issue ( it saves me a "backup" that weight 0KB.)


= 2.1.1 =
* Fixed bug: Conflict issue with Disqus Comments System, NextGen Gallery etc.

= 2.1 =
* Store Database Backup FTP, Dropbox, Email
* Email Notification
* fixed bug: Warning: Illegal offset type in /wp-admin/includes/template.php
* Fixed bug: Warning: Illegal string offset 'enable_autobackups

= 2.0 =
* Restore Database Backup on singe click
* Change look and feel

= 1.0.0 =
*Plugin Created

== Frequently Asked Questions ==

 = How to  create database Backup? =
 Follow the steps listed below to Create Database Backup

 <br>Create Backup:
  <br>1) Click on Create New Database Backup
  <br>2) Download Database Backup file.

= How to restore database backup? =
  Restore Backup:
  <br>Click on Restore Database Backup
  <br>OR
  <br>1)Login to phpMyAdmin
  <br>2)Click Databases and select the database that you will be importing your data into.
  <br>3)Across the top of the screen will be a row of tabs. Click the Import tab.
  <br>4)On the next screen will be a location of text file box, and next to that a button named Browse.
  <br>5)Click Browse. Locate the backup file stored on your computer.
  <br>6)Click the Go button

 = Always get an empty (0 bits) backup file? =
 This is generally caused by an access denied problem.
 <br>You don't have permission to write in the wp-content/uploads.
 <br>Please check if you have the read write permission on the folder.

= On Click Create New Database Backup it goes to blank page =
if the site is very large, it takes time to create the database backup. And if the server execution time is set to low value, you get go to blank page.
There may be chance your server max execution time is 30 second. Please check debug log file.
You will need to ask your hosting services to increase the execution time and the plugin will work fine for large data.
You can also try to increase execution time. Please make below changes – Add below line

php.ini

max_execution_time = 180 ;

Also Please make sure that you have write permission to Backup folder and also check your log file.

 = Want more features? =
 If you want more features then please contact us at 


== Upgrade Notice ==
* Sanitised multiple inputs and escape output to remove further risk of cross site script security.