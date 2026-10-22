# CE_delete_all_rows: Deleting one old row deletes every row.
s#\$DB->delete_records\(\$table, \['id' => \$taskid\]\);#\$DB->delete_records(\$table);#s;
