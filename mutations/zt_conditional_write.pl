# ZT_conditional_write: update_if_status() writes without comparing the current status.
s#if \(\$DB->get_field\(self::TABLE, 'status', \['id' => \$taskid\]\) !== \$expected\) \{#if (false) {#s;
