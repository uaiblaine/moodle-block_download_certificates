# REQ_user_keys: A user request keeps every posted key, not only userid.
s#array_intersect_key\(\$params, \['userid' => 1\]\)#\$params#s;
