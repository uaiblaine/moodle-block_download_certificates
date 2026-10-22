# CE_old_row_directory: An old row is deleted but its directory is left.
s#task_storage::delete\(\(int\) \$taskid\);(\s+\$DB->delete_records)#$1#s;
