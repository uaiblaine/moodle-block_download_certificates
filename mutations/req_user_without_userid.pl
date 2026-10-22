# REQ_user_without_userid: A user request without a user is accepted.
s#if \(!\$filter->userid\) \{(\s+throw new \\moodle_exception\('invalidparameters')#if (false) {$1#s;
