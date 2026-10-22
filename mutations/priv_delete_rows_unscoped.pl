# PRIV_delete_rows_unscoped: Deleting one user's data deletes every user's rows.
s#\$DB->delete_records\(self::TABLE, \['userid' => \$userid\]\);#\$DB->delete_records(self::TABLE);#s;
