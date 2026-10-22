# TS_delete_owns_path: delete() removes a directory that resolves outside the task base.
s#is_dir\(\$directory\) && self::owns_path\(\$directory\)#is_dir(\$directory)#s;
