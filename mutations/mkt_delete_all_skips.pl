# MKT_delete_all_skips: delete_all() removes no task directory.
s#(preg_match\('/\^\[1-9\]\[0-9\]\*\$/', \$name\)\) \{\n\s+)self::delete\(\(int\) \$name\);#$1;#s;
