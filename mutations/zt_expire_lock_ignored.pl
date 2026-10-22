# ZT_expire_lock_ignored: expire() removes the directory although its row was not written (lock not had).
s#if \(\$expired !== true\) \{#if (false) {#s;
