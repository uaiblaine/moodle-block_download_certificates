# CE_counts_unwritten: the cleanup counts a stuck row as failed although its lock was not had.
s#(\$failed \+= zip_tasks::fail\(\(int\) \$taskid, 'taskerror_timeout'\)) \? 1 : 0;#${1} ? 1 : 1;#s;
