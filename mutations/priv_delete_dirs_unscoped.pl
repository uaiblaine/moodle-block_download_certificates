# PRIV_delete_dirs_unscoped: Deleting one user's data deletes every user's task directories.
s#'userid = :userid', \['userid' => \$userid\]#'userid = :userid OR 1 = 1', ['userid' => \$userid]#s;
