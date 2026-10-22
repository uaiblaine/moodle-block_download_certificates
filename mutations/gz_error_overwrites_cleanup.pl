# GZ_error_overwrites_cleanup: The error path fails the row whatever its status, over a reason the cleanup wrote.
s#(zip_tasks::fail_if_status\(\s+\$taskid,\s+)\[zip_tasks::STATUS_PENDING, zip_tasks::STATUS_PROCESSING\](,\s+zip_tasks::error_code)#${1}null${2}#s;
