# ZT_fail_lock_ignored: fail_if_status() removes the directory although its row was not written (lock not had).
s#if \(\$failed !== true\) \{#if (false) {#s;
