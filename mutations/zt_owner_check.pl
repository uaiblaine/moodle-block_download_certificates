# ZT_owner_check: get_owned() hands any user's row to anyone.
s#if \(!\$task \|\| \(int\) \$task->userid !== \$userid\) \{#if (!\$task) {#s;
