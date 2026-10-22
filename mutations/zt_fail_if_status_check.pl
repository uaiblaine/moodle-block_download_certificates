# ZT_fail_if_status_check: fail_if_status() fails the row whatever its status inside the lock.
s#\$expected !== null && !in_array\(\$DB->get_field\(self::TABLE, 'status', \['id' => \$taskid\]\), \$expected, true\)#false#s;
