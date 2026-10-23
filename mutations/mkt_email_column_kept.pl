# MKT_email_column_kept: search_page() returns the e-mail although it is hidden.
s#'email' => \$showemail \? clean_param\(\(string\) \$row->email, PARAM_TEXT\) : '',#'email' => clean_param((string) \$row->email, PARAM_TEXT),#s;
