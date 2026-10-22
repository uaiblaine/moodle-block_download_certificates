# PRIV_export_filepath: the export includes the upstream filepath column again.
s#(\s+)'error' => \$task->error,#${1}'filepath' => \$task->filepath,${1}'error' => \$task->error,#s;
