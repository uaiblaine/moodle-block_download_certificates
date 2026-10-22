# GZ_check_zip_each_run: A run does not re-check the request against the requester's current access.
s#request::check_zip\(\$scope, \(string\) \$task->type, \$filter\);##s;
