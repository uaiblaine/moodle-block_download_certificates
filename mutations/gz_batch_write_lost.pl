# GZ_batch_write_lost: A lost conditional write between batches is ignored: the next batch is queued anyway.
s#if \(\$this->lost_write\(\$taskid, \$moved\)\) \{\s+return;\s+\}(\s+\$this->queue\(\$taskid\);)#$1#s;
