you will need to make sure that the permissions for
wp-content/plugins/soffice_pdf_helper/lock so that php (the www-user) can write in there
and also be able to write to a file called wp-content/plugins/soffice_pdf_helper/action_log.txt


if you install this plugin as an uploaded zip , most WP installs should do the automatically,if the system admin
 has set plugin folder to always be writable by php.
But if these files are not writable, then it will tell you so in the php error log

there is a test file you can run from the browser, called wp-content/plugins/soffice_pdf_helper/test_pdf.php
You may want to make sure that the folders its trying to use are the correct ones for your install,
But you can run by copying it to a place in your webserver you can run it from.
Its a stand alone program and if it works then office can run from your php