# GZ_finished_rows_run: A row that is no longer pending or processing is run again.
s#if \(!in_array\(\$task->status, \[zip_tasks::STATUS_PENDING, zip_tasks::STATUS_PROCESSING\], true\)\) \{#if (false) {#s;
