<?php


$input_file_name = 'Cooperation-Agreement_Client.rtf';// 'NDA_CompanyName.rtf';


$rtf_path = '/var/www/html/wp-content/uploads/legal';
$output_path = '/var/www/html/wp-content/uploads/legal';

$command_line = "export HOME=/tmp && \
	/usr/bin/soffice                    \
  --headless                           \
  --convert-to pdf:writer_pdf_Export   \
  --outdir $output_path                \
  $rtf_path/$input_file_name";

$output = shell_exec("$command_line 2>&1");
print "<pre>$command_line\n\n$output</pre>";