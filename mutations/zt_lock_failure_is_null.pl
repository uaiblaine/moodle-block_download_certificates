# ZT_lock_failure_is_null: a status write whose row lock was not had reports false, as if the status had changed.
s#if \(!\$lock\) \{\s+return null;#if (!\$lock) {\n            return false;#s;
