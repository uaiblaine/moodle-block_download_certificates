# GZ_ready_write_lost: A lost conditional write at the last batch is ignored: the directory of a failed row stays.
s#\$this->lost_write\(\$taskid, \$moved\);(\s+\})#$1#s;
