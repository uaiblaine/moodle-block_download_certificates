# CE_finished_directory: A failed or expired row keeps its leftover directory.
s#(foreach \(\$finished as \$taskid\) \{\s+)task_storage::delete\(\(int\) \$taskid\);#$1#s;
