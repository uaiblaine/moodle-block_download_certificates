# ZT_download_access_recheck: prepare_download() does not re-check the owner's access.
s#request::check_zip\(scope::for_user\(\(int\) \$task->userid\), \(string\) \$task->type, \$filter\);##s;
