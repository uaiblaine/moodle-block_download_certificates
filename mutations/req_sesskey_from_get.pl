# REQ_sesskey_from_get: The session key is also read from the URL.
s#\$body = data_submitted\(\);#\$body = (object) (\$_POST + \$_GET);#s;
